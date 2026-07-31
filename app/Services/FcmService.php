<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Notification;
use App\Models\ParentModel;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM Service
 * 
 * Handles Firebase Cloud Messaging for push notifications.
 * Supports sending to single or multiple devices.
 */
class FcmService
{
    protected string $firebaseProjectId;
    protected string $credentialsPath;

    /**
     * Initialize Firebase configuration.
     */
    protected function initConfig(): void
    {
        $this->firebaseProjectId = env('FIREBASE_PROJECT_ID');
        $this->credentialsPath = storage_path('app/' . env('FIREBASE_CREDENTIALS_FILE'));
    }

    /**
     * Send notification to a single token.
     *
     * @param string $token FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return bool
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $this->initConfig();

        if (! file_exists($this->credentialsPath)) {
            Log::warning('FCM credentials file not found', ['path' => $this->credentialsPath]);
            return false;
        }

        $credentials = json_decode(file_get_contents($this->credentialsPath), true);

        if (! isset($credentials['private_key'], $credentials['client_email'])) {
            Log::warning('FCM credentials file is invalid', ['path' => $this->credentialsPath]);
            return false;
        }

        $authToken = $this->getAccessToken($credentials);
        $url = "https://fcm.googleapis.com/v1/projects/{$this->firebaseProjectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $token,
                'data' => array_map('strval', array_merge($data, [
                    'title' => $title,
                    'body' => $body,
                    'click_action' => "FLUTTER_NOTIFICATION_CLICK"
                ])),
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => "high",
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'content-available' => 1,
                            'badge' => 5,
                            'priority' => "high",
                        ]
                    ]
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $authToken,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        Log::debug('FCM Response', ['body' => $response->body(), 'status' => $response->status()]);

        return $response->successful();
    }

    /**
     * Send notification to a user (all their devices).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        $tokens = $user->devices()->whereNotNull('fcm_token')->pluck('fcm_token')->filter()->unique()->values()->all();
        $successCount = 0;

        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $successCount++;
            }
        }

        Log::info("FCM sent to User #{$user->id}", [
            'total_devices' => count($tokens),
            'successful' => $successCount,
        ]);

        return $successCount;
    }

    /**
     * Send notification to a parent (all their devices).
     */
    public function sendToParent(ParentModel $parent, string $title, string $body, array $data = []): int
    {
        $tokens = $parent->devices()->whereNotNull('fcm_token')->pluck('fcm_token')->filter()->unique()->values()->all();
        $successCount = 0;

        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $successCount++;
            }
        }

        Log::info("FCM sent to Parent #{$parent->id}", [
            'total_devices' => count($tokens),
            'successful' => $successCount,
        ]);

        return $successCount;
    }
    /**
     * Send notification to multiple tokens (for broadcast notifications).
     *
     * @param array $tokens Array of FCM tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array ['success' => int, 'failed' => int]
     */
    public function sendToMultipleTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $successCount = 0;
        $failedCount = 0;

        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        Log::info("FCM batch send completed", [
            'total' => count($tokens),
            'success' => $successCount,
            'failed' => $failedCount,
        ]);

        return [
            'success' => $successCount,
            'failed' => $failedCount,
        ];
    }

    protected function getAccessToken(array $credentials): string
    {
        $jwtPayload = [
            'iss' => $credentials['client_email'],
            'sub' => $credentials['client_email'],
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => time(),
            'exp' => time() + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ];

        $privateKey = $credentials['private_key'];
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $encodedHeader = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $encodedPayload = rtrim(strtr(base64_encode(json_encode($jwtPayload)), '+/', '-_'), '=');
        $signatureInput = $encodedHeader . '.' . $encodedPayload;
        openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $encodedSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $jwt = $signatureInput . '.' . $encodedSignature;

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Unable to obtain FCM access token: ' . $response->body());
        }

        return $response->json('access_token');
    }
}
