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
                'school_id' => $data['school_id'] ?? null,
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

            [$accessToken, $refreshToken] = $this->issueTokens($user);

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'type' => 'user',
                    'user' => $user->fresh(),
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => 7200,
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

        if ($credentials['fcm_token'] ?? false) {
            $account->registerDevice($credentials['fcm_token']);
        }

        if ($credentials['type'] === 'user' && $account->is_admin) {
            $accessToken = $account->createToken('admin-access', ['dashboard'], now()->addHours(10))->plainTextToken;
            $refreshToken = $account->createToken('admin-refresh', ['refresh-dashboard'], now()->addHours(2))->plainTextToken;

            return [
                'success' => true,
                'data' => [
                    'type' => 'user',
                    'user' => $account,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => 600,
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

        [$accessToken, $refreshToken] = $this->issueTokens($account);

        return [
            'success' => true,
            'data' => [
                'type' => $credentials['type'],
                'user' => $account,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => 7200,
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

            $verification = $this->otpService->verifyOTP($data['phone'], $data['otp_code'], 'register');

            if (! $verification['success']) {
                return $verification;
            }

            $account->update(['phone_verified_at' => Carbon::now()]);

            [$accessToken, $refreshToken] = $this->issueTokens($account);

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'type' => $data['type'],
                    'user' => $account->fresh(),
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => 7200,
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

        if ($data['fcm_token'] ?? false) {
            Device::removeByToken($account, $data['fcm_token']);
        }

        $account->currentAccessToken()->delete();

        return ['message' => 'تم تسجيل الخروج بنجاح'];
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
                'message' => 'فشل في إرسال كود التحقق',
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
     * Issue a short-lived access token and a long-lived refresh token.
     *
     * @return array{0: string, 1: string}
     */
    private function issueTokens(User|ParentModel $account): array
    {
        $accessToken = $account->createToken('mobile-access', ['access-api'], now()->addHours(2))->plainTextToken;
        $refreshToken = $account->createToken('mobile-refresh', ['refresh-token'], now()->addYear())->plainTextToken;

        return [$accessToken, $refreshToken];
    }
}
