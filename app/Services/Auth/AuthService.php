<?php

namespace App\Services\Auth;

use App\Models\Device;
use App\Models\ParentModel;
use App\Models\User;
use App\Services\FileStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /** Returned in errors.code so the app can show the "contact the admin to switch device" screen. */
    public const SESSION_ACTIVE = 'session_active';

    public const LOGOUT_NOT_ALLOWED = 'logout_not_allowed';

    public function __construct(private readonly OTPService $otpService)
    {
    }

    /**
     * Register a new user and send an OTP to confirm the phone number.
     */
    public function register(array $data): array
    {
        DB::beginTransaction();

        try {
            $existingUser = User::where('phone', $data['phone'])->first();

            if ($existingUser) {
                return [
                    'success' => false,
                    'message' => 'رقم الهاتف مسجل مسبقاً',
                ];
            }

            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'gender' => $data['gender'] ?? null,
                'age' => $data['age'] ?? null,
                'avatar' => isset($data['avatar']) ? FileStorage::storeFile($data['avatar'], 'avatars', 'img') : null,
                'password' => Hash::make($data['password']),
                'phone_verified_at' => null,
                'governorate_id' => $data['governorate_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'school_id' => $data['school_id'] ?? null,
                'study_type_id' => $data['study_type_id'] ?? null,
            ]);

            if ($data['fcm_token'] ?? false) {
                $user->registerDevice($data['fcm_token']);
            }

            try {
                $this->otpService->generateOTP($data['phone'], 'register');

                DB::commit();

                return [
                    'success' => true,
                    'otp_required' => true,
                    'message' => 'تم إنشاء الحساب بنجاح. تم إرسال كود التحقق',
                ];
            } catch (\Exception $e) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'فشل في إرسال كود التحقق',
                ];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'فشل في إنشاء الحساب',
            ];
        }
    }

    /**
     * Confirm registration by verifying the OTP sent to the phone number.
     */
    public function confirmRegistration(array $data): array
    {
        DB::beginTransaction();

        try {
            $user = User::where('phone', $data['phone'])->first();

            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'الحساب غير موجود',
                ];
            }

            if ($user->isPhoneVerified()) {
                return [
                    'success' => false,
                    'message' => 'الحساب مفعل مسبقاً',
                ];
            }

            $verification = $this->otpService->verifyOTP($data['phone'], $data['otp_code'], 'register');

            if (! $verification['success']) {
                return $verification;
            }

            $user->update(['phone_verified_at' => Carbon::now()]);

            $tokens = $this->issueTokens($user);

            if (! $tokens) {
                DB::rollBack();

                return $this->sessionActiveError();
            }

            [$accessToken, $refreshToken] = $tokens;

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'type' => 'user',
                    'user' => $user->fresh(),
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => 189216000,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration confirmation failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'فشل في تفعيل الحساب',
            ];
        }
    }

    /**
     * Log a user or parent in with phone + password.
     */
    public function login(array $credentials): array
    {
        $account = $this->getModel($credentials['type'])::where('phone', $credentials['phone'])->first();

        if (! $account || ! Hash::check($credentials['password'], $account->password)) {
            return [
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة',
            ];
        }

        // Before the device is registered or an OTP is sent, so a blocked
        // attempt leaves nothing behind on the account.
        if ($this->isLockedStudent($account)) {
            return $this->sessionActiveError();
        }

        if ($credentials['fcm_token'] ?? false) {
            $account->registerDevice($credentials['fcm_token']);
        }

        if ($credentials['type'] === 'user' && $account->is_admin) {
            $accessToken = $account->createToken('admin-access', ['dashboard'], now()->addYears(6))->plainTextToken;
            $refreshToken = $account->createToken('admin-refresh', ['refresh-dashboard'], now()->addYears(6))->plainTextToken;

            return [
                'success' => true,
                'data' => [
                    'type' => 'user',
                    'user' => $account,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => 189216000,
                ],
            ];
        }

        if (! $account->isPhoneVerified()) {
            try {
                $this->otpService->generateOTP($credentials['phone'], 'register');

                return [
                    'success' => true,
                    'otp_required' => true,
                    'message' => 'الحساب غير مفعل. تم إرسال كود التحقق',
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'فشل في إرسال كود التحقق',
                ];
            }
        }

        $tokens = $this->issueTokens($account);

        if (! $tokens) {
            return $this->sessionActiveError();
        }

        [$accessToken, $refreshToken] = $tokens;

        return [
            'success' => true,
            'data' => [
                'type' => $credentials['type'],
                'user' => $account,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => 189216000,
            ],
        ];
    }

    /**
     * Confirm an unverified account's phone number during login.
     */
    public function confirmLogin(array $data): array
    {
        DB::beginTransaction();

        try {
            $account = $this->getModel($data['type'])::where('phone', $data['phone'])->first();

            if (! $account) {
                return [
                    'success' => false,
                    'message' => 'الحساب غير موجود',
                ];
            }

            if ($this->isLockedStudent($account)) {
                DB::rollBack();

                return $this->sessionActiveError();
            }

            $verification = $this->otpService->verifyOTP($data['phone'], $data['otp_code'], 'register');

            if (! $verification['success']) {
                return $verification;
            }

            $account->update(['phone_verified_at' => Carbon::now()]);

            $tokens = $this->issueTokens($account);

            if (! $tokens) {
                DB::rollBack();

                return $this->sessionActiveError();
            }

            [$accessToken, $refreshToken] = $tokens;

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'type' => $data['type'],
                    'user' => $account->fresh(),
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => 189216000,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Login confirmation failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'فشل في تأكيد الحساب',
            ];
        }
    }

    /**
     * Revoke the current access token, optionally unregistering a device.
     */
    public function logout(array $data = []): array
    {
        /** @var User|ParentModel $account */
        $account = Auth::user();

        // Revoking a student's token would free the account for a login from
        // any other device — only an admin session reset may do that.
        if ($account instanceof User && ! $account->is_admin) {
            return [
                'success' => false,
                'message' => 'لا يمكن تسجيل الخروج من حساب الطالب. لتغيير الجهاز يرجى التواصل مع الإدارة',
                'errors' => ['code' => self::LOGOUT_NOT_ALLOWED],
            ];
        }

        if ($data['fcm_token'] ?? false) {
            Device::removeByToken($account, $data['fcm_token']);
        }

        $account->currentAccessToken()->delete();

        return ['success' => true, 'message' => 'تم تسجيل الخروج بنجاح'];
    }

    public function updateProfile(User $account, array $data): User
    {
        $updates = [
            'name' => $data['name'] ?? $account->name,
        ];

        if (isset($data['avatar'])) {
            $updates['avatar'] = FileStorage::fileExists($data['avatar'], $account->avatar, 'avatars', 'img');
        }

        $account->update($updates);

        return $account->fresh();
    }

    /**
     * Send an OTP to reset the password.
     */
    public function forgotPassword(array $data): array
    {
        $account = $this->getModel($data['type'])::where('phone', $data['phone'])->first();

        if (! $account) {
            return [
                'success' => false,
                'message' => 'رقم الهاتف غير مسجل',
            ];
        }

        try {
            $this->otpService->generateOTP($data['phone'], 'reset_password');

            return [
                'success' => true,
                'message' => 'تم إرسال كود التحقق',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : 'فشل في إرسال كود التحقق',
            ];
        }
    }

    /**
     * Verify the OTP for a password reset request.
     */
    public function confirmForgotPassword(array $data): array
    {
        $account = $this->getModel($data['type'])::where('phone', $data['phone'])->first();

        if (! $account) {
            return [
                'success' => false,
                'message' => 'رقم الهاتف غير مسجل',
            ];
        }

        $verification = $this->otpService->verifyOTP($data['phone'], $data['otp_code'], 'reset_password');

        if (! $verification['success']) {
            return $verification;
        }

        return [
            'success' => true,
            'message' => 'تم التحقق بنجاح، يمكنك الآن تغيير كلمة المرور',
        ];
    }

    /**
     * Set a new password after a confirmed reset request.
     */
    public function resetPassword(array $data): array
    {
        $account = $this->getModel($data['type'])::where('phone', $data['phone'])->first();

        if (! $account) {
            return [
                'success' => false,
                'message' => 'رقم الهاتف غير مسجل',
            ];
        }

        $account->update(['password' => Hash::make($data['password'])]);

        return [
            'success' => true,
            'message' => 'تم إعادة تعيين كلمة المرور بنجاح',
        ];
    }

    /**
     * Resend an OTP code.
     */
    public function resendOTP(string $phone, string $type): array
    {
        try {
            $this->otpService->generateOTP($phone, $type);

            return [
                'success' => true,
                'message' => 'تم إعادة إرسال كود التحقق',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'فشل في إرسال كود التحقق',
            ];
        }
    }

    /**
     * Delete the currently authenticated account and its tokens.
     */
    public function deleteAccount(User|ParentModel $account): array
    {
        try {
            $account->tokens()->delete();
            $account->delete();

            return [
                'success' => true,
                'message' => 'تم حذف الحساب بنجاح',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'فشل في حذف الحساب',
            ];
        }
    }

    /**
     * Resolve the model class for the given account type.
     *
     * @return class-string<User|ParentModel>
     */
    private function getModel(string $type): string
    {
        return match ($type) {
            'parent' => ParentModel::class,
            default => User::class,
        };
    }

    /**
     * Issue access and refresh tokens valid for six years, replacing any
     * previous ones. Returns null for a student who already holds a live
     * session — re-checked under a row lock so two simultaneous logins
     * can't both get through.
     *
     * @return array{0: string, 1: string}|null
     */
    private function issueTokens(User|ParentModel $account): ?array
    {
        return DB::transaction(function () use ($account) {
            $account->newQuery()->whereKey($account->getKey())->lockForUpdate()->first();

            if ($this->isLockedStudent($account)) {
                return null;
            }

            $account->tokens()->delete();

            $accessToken = $account->createToken('mobile-access', ['access-api'], now()->addYears(6))->plainTextToken;
            $refreshToken = $account->createToken('mobile-refresh', ['refresh-token'], now()->addYears(6))->plainTextToken;

            return [$accessToken, $refreshToken];
        });
    }

    /**
     * A student account is bound to the device holding its session; a new
     * one can't be opened until an admin resets it (admin/students/{id}/reset-session).
     */
    private function isLockedStudent(User|ParentModel $account): bool
    {
        return $account instanceof User && ! $account->is_admin && $account->hasActiveSession();
    }

    private function sessionActiveError(): array
    {
        return [
            'success' => false,
            'status' => 403,
            'message' => 'هذا الحساب مسجل الدخول على جهاز آخر. لتسجيل الدخول من جهاز جديد يرجى التواصل مع الإدارة',
            'errors' => ['code' => self::SESSION_ACTIVE],
        ];
    }
}
