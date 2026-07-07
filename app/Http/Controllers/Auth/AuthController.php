<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmForgotPasswordRequest;
use App\Http\Requests\Auth\ConfirmLoginRequest;
use App\Http\Requests\Auth\ConfirmRegistrationRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendOTPRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request->validated());

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([
            'otp_required' => true,
        ], $result['message'], 201);
    }

    /**
     * تأكيد التسجيل مع OTP
     */
    public function confirmRegistration(ConfirmRegistrationRequest $request)
    {
        $result = $this->authService->confirmRegistration($request->validated());

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success($result['data'], 'Registration confirmed successfully');
    }

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->validated());

        if (! $result['success']) {
            return $this->error($result['message'], 401);
        }

        if ($result['otp_required'] ?? false) {
            return $this->success([
                'otp_required' => true,
            ], $result['message']);
        }

        return $this->success($result['data'], 'Login successfully');
    }

    /**
     * تأكيد الحساب أثناء Login
     */
    public function confirmLogin(ConfirmLoginRequest $request)
    {
        $result = $this->authService->confirmLogin($request->validated());

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success($result['data'], 'Login confirmed successfully');
    }

    public function logout(LogoutRequest $request)
    {
        $result = $this->authService->logout($request->validated());

        return $this->success($result, 'Logout success');
    }

    /**
     * نسيان كلمة المرور - إرسال OTP فقط
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $result = $this->authService->forgotPassword($request->validated());

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * تأكيد OTP لنسيان كلمة المرور
     */
    public function confirmForgotPassword(ConfirmForgotPasswordRequest $request)
    {
        $result = $this->authService->confirmForgotPassword($request->validated());

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * تغيير كلمة المرور بعد التأكيد
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $result = $this->authService->resetPassword($request->validated());

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * إعادة إرسال OTP
     */
    public function resendOTP(ResendOTPRequest $request)
    {
        $result = $this->authService->resendOTP($request->phone, $request->type);

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * delete authenticated account
     */
    public function deleteAccount(Request $request)
    {
        $result = $this->authService->deleteAccount($request->user());

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], 'Account deleted successfully');
    }
}
