<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSService
{
    protected $apiKey;
    protected $baseUrl;
    protected $senderId;
    protected $templateId;

    public function __construct()
    {
        $this->apiKey = config('hypermsg.msgplus_api_key');
        $this->baseUrl = rtrim(config('hypermsg.msgplus_base_url'), '/');
        $this->senderId = config('hypermsg.msgplus_sender_id');
        $this->templateId = config('hypermsg.msgplus_template_id');
    }

    public function sendOTP($phoneNumber, $otpCode, $type = 'register')
    {
        // 1. تحقق من التهيئة
        if (empty($this->apiKey) || empty($this->baseUrl) || empty($this->senderId) || empty($this->templateId)) {
            Log::error('SMSService configuration incomplete');
            return false;
        }

        // الرقم رح يجي بهذةة الصيغة : +963911112222  انا بدي شيل ال+
        $phoneNumber = preg_replace('/^\+/', '', trim($phoneNumber));

        // 2. تحقق من صيغة الرقم
        if (!preg_match('/^963[0-9]{9}$/', $phoneNumber)) {
            Log::error('Invalid phone number', ['phone' => $phoneNumber]);
            return false;
        }

        // 3. تحضير بيانات الرسالة حسب Postman
        $payload = [
            'sender_id' => $this->senderId,
            'template_id' => $this->templateId,
            'numbers' => [$phoneNumber],
            'vars' => [
                'P1' => $otpCode,
                // 'P2' => $text,
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'X-Timestamp' => (string) time(),
                ])
                ->post($this->baseUrl . '/send', $payload);

            if ( $response->json('success') === true) {
                Log::info('SMS sent', [
                    'phone' => $phoneNumber,
                    'response' => $response->json()
                ]);
                return true;
            }

            Log::error('SMS failed', [
                'phone' => $phoneNumber,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('SMS exception', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }


}
