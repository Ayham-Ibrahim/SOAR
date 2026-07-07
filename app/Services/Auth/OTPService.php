<?php

namespace App\Services\Auth;

use App\Models\OTPCode;
use Carbon\Carbon;

class OTPService
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Generate and send OTP code.
     * @param mixed $phone
     * @param mixed $type
     * @throws \Exception
     * @return OTPCode
     */
    public function generateOTP($phone, $type = 'register')
    {
        // delete existing OTP codes for this phone and type
        OTPCode::where('phone', $phone)
            ->where('type', $type)
            ->delete();

        // create a new 4-digit OTP code
        $otpCode   = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $otpRecord = OTPCode::create([
            'phone'      => $phone,
            'code'       => $otpCode,
            'type'       => $type,
            'expires_at' => Carbon::now()->addMinutes(4),
            'attempts'   => 0,
        ]);

        $sent = $this->sendOTP($phone, $otpCode, $type);

        if (! $sent) {
            throw new \Exception('فشل في إرسال كود التحقق');
        }

        return $otpRecord;
    }

    /**
     * Verify the provided OTP code.
     * @param mixed $phone
     * @param mixed $code
     * @param mixed $type
     * @return array{message: string, success: bool}
     */
    public function verifyOTP($phone, $code, $type = 'register')
    {
        $otpRecord = OTPCode::where('phone', $phone)
            ->where('code', $code)
            ->where('type', $type)
            ->first();

        if (! $otpRecord) {
            return [
                'success' => false,
                'message' => 'كود التحقق غير صحيح',
            ];
        }

        // check if OTP is expired
        if (Carbon::now()->gt($otpRecord->expires_at)) {
            $otpRecord->delete();
            return [
                'success' => false,
                'message' => 'كود التحقق منتهي الصلاحية',
            ];
        }

        // check the number of attempts (maximum 5 attempts)
        if ($otpRecord->attempts >= 4) {
            $otpRecord->delete();
            return [
                'success' => false,
                'message' => 'تم تجاوز عدد المحاولات المسموح بها',
            ];
        }

        // increment the number of attempts
        $otpRecord->increment('attempts');

        // delete the code after successful use
        $otpRecord->delete();

        return [
            'success' => true,
            'message' => 'تم التحقق بنجاح',
        ];
    }

    /**
     * Send the OTP via the Telegram bot to the configured group.
     */
    protected function sendOTP($phone, $otpCode, $type = 'register')
    {
        return $this->telegramService->sendOTP($otpCode, $type);
    }

    /**
     * Resend OTP code.
     * @param mixed $phone
     * @param mixed $type
     * @throws \Exception
     * @return OTPCode
     */
    public function resendOTP($phone, $type = 'register')
    {
        return $this->generateOTP($phone, $type);
    }
}
