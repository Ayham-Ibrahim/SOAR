<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Playback tokens, shared with the Cloudflare media Worker
 * (cloudflare/media-worker/src/token.js) — both sides must agree byte for
 * byte, which tests/Unit/VideoTokenTest pins with a shared test vector.
 *
 *   token = base64url(json) . "." . base64url(hmac_sha256(base64url(json), secret))
 *   json  = {"u": user_id, "v": video_id, "sid": playback_session_id, "exp": unix_ts}
 *
 * base64url: + and / become - and _, no = padding.
 */
class VideoToken
{
    public function __construct(private readonly string $secret)
    {
        if ($secret === '') {
            throw new InvalidArgumentException('VIDEO_TOKEN_SECRET is not set.');
        }
    }

    public function issue(int $userId, int $videoId, string $sessionId, int $expiresAt): string
    {
        $payload = self::base64UrlEncode(json_encode([
            'u' => $userId,
            'v' => $videoId,
            'sid' => $sessionId,
            'exp' => $expiresAt,
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->sign($payload);
    }

    /**
     * The claims of a well-formed token with a valid signature, or null.
     * Expiry and the session are the caller's to check.
     *
     * @return array{u: int, v: int, sid: string, exp: int}|null
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$payload, $signature] = $parts;

        if (! hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $decoded = self::base64UrlDecode($payload);
        $claims = $decoded === null ? null : json_decode($decoded, true);

        if (! is_array($claims)
            || ! is_int($claims['u'] ?? null)
            || ! is_int($claims['v'] ?? null)
            || ! is_string($claims['sid'] ?? null)
            || ! is_int($claims['exp'] ?? null)) {
            return null;
        }

        return $claims;
    }

    private function sign(string $payload): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $payload, $this->secret, true));
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
