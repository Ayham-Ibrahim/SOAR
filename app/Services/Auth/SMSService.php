<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSService
{
    public function sendOTP($phoneNumber, $otpCode, $type = 'register')
    {
        // الرقم رح يجي بهذةة الصيغة : +963911112222  انا بدي شيل ال+
        $phoneNumber = preg_replace('/^\+/', '', trim($phoneNumber));

        // تحقق من صيغة الرقم
        if (!preg_match('/^963[0-9]{9}$/', $phoneNumber)) {
            Log::error('Invalid phone number', ['phone' => $phoneNumber]);
            throw new \InvalidArgumentException(
                'عذراً، إرسال رمز التحقق متاح فقط للأرقام السورية. يرجى مراجعة الإدارة لإتمام العملية.'
            );
        }

        return config('hypermsg.sms_driver') === 'msgplus'
            ? $this->sendViaMsgPlus($phoneNumber, $otpCode)
            : $this->sendViaMtn($phoneNumber, $this->getMessageByType($type, $otpCode));
    }

    /**
     * MTN Syria concatenated sender. Msg must be UCS-2 hex (4 hex digits per char).
     */
    protected function sendViaMtn(string $phoneNumber, string $message): bool
    {
        $url = config('hypermsg.mtn_url');
        $user = config('hypermsg.mtn_user');
        $pass = config('hypermsg.mtn_pass');
        $sender = config('hypermsg.mtn_sender');

        if (empty($url) || empty($user) || empty($pass) || empty($sender)) {
            Log::error('SMSService MTN configuration incomplete');
            return false;
        }

        try {
            $response = Http::timeout(30)->get($url, [
                'User' => $user,
                'Pass' => $pass,
                'From' => $sender,
                'Gsm' => $phoneNumber,
                'Msg' => strtoupper(bin2hex(mb_convert_encoding($message, 'UCS-2BE', 'UTF-8'))),
                'Lang' => (int) config('hypermsg.mtn_lang'),
            ]);

            $body = trim($response->body());

            if ($response->successful() && $this->isMtnSuccess($body)) {
                Log::info('SMS sent (MTN)', ['phone' => $phoneNumber, 'response' => $body]);
                return true;
            }

            Log::error('SMS failed (MTN)', [
                'phone' => $phoneNumber,
                'status' => $response->status(),
                'response' => $body,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('SMS exception (MTN)', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * MTN returns a plain-text body; anything that looks like an error is treated as failure.
     */
    protected function isMtnSuccess(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        return !preg_match('/error|fail|invalid|denied|wrong|^-\d+/i', $body);
    }

    protected function sendViaMsgPlus(string $phoneNumber, string $otpCode): bool
    {
        $apiKey = config('hypermsg.msgplus_api_key');
        $baseUrl = rtrim((string) config('hypermsg.msgplus_base_url'), '/');
        $senderId = config('hypermsg.msgplus_sender_id');
        $templateId = config('hypermsg.msgplus_template_id');

        if (empty($apiKey) || empty($baseUrl) || empty($senderId) || empty($templateId)) {
            Log::error('SMSService MsgPlus configuration incomplete');
            return false;
        }

        $payload = [
            'sender_id' => $senderId,
            'template_id' => $templateId,
            'numbers' => [$phoneNumber],
            'vars' => [
                'P1' => $otpCode,
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                    'X-Timestamp' => (string) time(),
                ])
                ->post($baseUrl . '/send', $payload);

            if ($response->json('success') === true) {
                Log::info('SMS sent (MsgPlus)', [
                    'phone' => $phoneNumber,
                    'response' => $response->json()
                ]);
                return true;
            }

            Log::error('SMS failed (MsgPlus)', [
                'phone' => $phoneNumber,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('SMS exception (MsgPlus)', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function getMessageByType($type, $otpCode)
    {
        $messages = [
            'register' => "كود التحقق لإنشاء الحساب في SOAR: {$otpCode}\nصالح لمدة 4 دقائق",
            'reset_password' => "كود التحقق لإعادة تعيين كلمة المرور في SOAR: {$otpCode}\nصالح لمدة 4 دقائق",
        ];

        return $messages[$type] ?? "كود التحقق الخاص بك في SOAR: {$otpCode}\nصالح لمدة 4 دقائق";
    }
}
