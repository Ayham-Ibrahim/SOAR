<?php

namespace App\Services\Auth;

use Illuminate\Http\Client\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected $botToken;
    protected $apiUrl;
    protected $chatId;

    /**
     * Constructor to initialize Telegram Bot credentials.
     */
    public function __construct()
    {
        $this->botToken = config('hypermsg.bot_token');
        $this->apiUrl = config('hypermsg.api_url');
        $this->chatId = config('hypermsg.chat_id');
    }

    /**
     * Send OTP via Telegram to the configured group.
     */
    public function sendOTP($otpCode, $type = 'register')
    {
        // التأكد من أن معرف المجموعة مهيّأ
        if (empty($this->chatId)) {
            Log::error('Telegram chat id is not configured');
            return false;
        }

        // الحصول على الرسالة حسب نوع العملية
        $message = $this->getMessageByType($type, $otpCode);

        // إرسال الرسالة إلى مجموعة تيليجرام
        return $this->sendMessage($this->chatId, $message);
    }

    /**
     * Send a text message to a specific Telegram user.
     */
    public function sendMessage($chatId, $text)
    {
        try {
            // بناء URL الطلب
            $url = $this->apiUrl . $this->botToken . '/sendMessage';

            // إرسال الطلب إلى Telegram API
            $response = Http::timeout(30)->post($url, [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
            
            // التحقق من نجاح الطلب
            if ($response->successful()) {
                Log::info('Telegram message sent successfully', [
                    'chat_id' => $chatId,
                    'response' => $response->json()
                ]);
                return true;
            } else {
                Log::error('Failed to send Telegram message', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send a message with inline keyboard (optional).
     */
    public function sendMessageWithKeyboard($chatId, $text, $keyboard)
    {
        try {
            $url = $this->apiUrl . $this->botToken . '/sendMessage';

            $response = Http::timeout(30)->post($url, [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ]);

            return $response->successful() && $response->json('ok');
        } catch (\Exception $e) {
            Log::error('Exception while sending keyboard message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get message template based on type.
     */
    private function getMessageByType($type, $otpCode)
    {
        $messages = [
            'register' => "🔐 <b>كود التحقق لتسجيل الدخول</b>\n\n"
                        . "كود التحقق الخاص بك هو: <b>{$otpCode}</b>\n\n"
                        . "⏱ هذا الكود صالح لمدة 4 دقائق\n"
                        . "⚠️ لا تشارك هذا الكود مع أي شخص",

            'reset_password' => "🔄 <b>إعادة تعيين كلمة المرور</b>\n\n"
                              . "كود التحقق الخاص بك هو: <b>{$otpCode}</b>\n\n"
                              . "⏱ هذا الكود صالح لمدة 4 دقائق\n"
                              . "⚠️ لا تشارك هذا الكود مع أي شخص",

            'login' => "🔑 <b>تسجيل الدخول</b>\n\n"
                     . "كود التحقق الخاص بك هو: <b>{$otpCode}</b>\n\n"
                     . "⏱ هذا الكود صالح لمدة 4 دقائق\n"
                     . "⚠️ لا تشارك هذا الكود مع أي شخص",
        ];

        return $messages[$type] ?? "🔔 <b>كود التحقق</b>\n\nكود التحقق الخاص بك هو: <b>{$otpCode}</b>\n\n⏱ صالح لمدة 4 دقائق";
    }

    /**
     * Send a photo to Telegram user.
     */
    public function sendPhoto($chatId, $photoUrl, $caption = '')
    {
        try {
            $url = $this->apiUrl . $this->botToken . '/sendPhoto';

            $response = Http::timeout(30)->post($url, [
                'chat_id' => $chatId,
                'photo' => $photoUrl,
                'caption' => $caption,
            ]);

            return $response->successful() && $response->json('ok');
        } catch (\Exception $e) {
            Log::error('Exception while sending photo', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verify if the bot token is valid.
     */
    public function verifyBotToken()
    {
        try {
            $url = $this->apiUrl . $this->botToken . '/getMe';

            $response = Http::get($url);

            if ($response->successful() && $response->json('ok')) {
                $botInfo = $response->json('result');
                Log::info('Bot verified successfully', [
                    'bot_name' => $botInfo['first_name'] ?? 'Unknown',
                    'username' => $botInfo['username'] ?? 'Unknown'
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Bot verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Set webhook for receiving messages.
     */
    public function setWebhook($webhookUrl)
    {
        try {
            $url = $this->apiUrl . $this->botToken . '/setWebhook';

            $response = Http::post($url, [
                'url' => $webhookUrl,
            ]);

            return $response->successful() && $response->json('ok');
        } catch (\Exception $e) {
            Log::error('Failed to set webhook', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Remove webhook.
     */
    public function removeWebhook()
    {
        try {
            $url = $this->apiUrl . $this->botToken . '/deleteWebhook';

            $response = Http::post($url);

            return $response->successful() && $response->json('ok');
        } catch (\Exception $e) {
            Log::error('Failed to remove webhook', ['error' => $e->getMessage()]);
            return false;
        }
    }
}