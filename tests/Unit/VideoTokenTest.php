<?php

namespace Tests\Unit;

use App\Services\VideoToken;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VideoTokenTest extends TestCase
{
    /**
     * Shared with cloudflare/media-worker/test/worker.test.js: the Worker
     * must accept exactly this token for this secret.
     */
    private const VECTOR_SECRET = 'soaar-shared-test-vector-secret';

    private const VECTOR_TOKEN = 'eyJ1Ijo0MiwidiI6Nywic2lkIjoiOWYxYzJlNGEtNWI2ZC00ZTdmLThhOWItMGMxZDJlM2Y0YTViIiwiZXhwIjoxODkzNDU2MDAwfQ'
        .'.JfBW5m8hWckZOFrAK1MN--KDnv19w58tWMBdOkp4vEU';

    public function test_it_matches_the_test_vector_shared_with_the_worker(): void
    {
        $tokens = new VideoToken(self::VECTOR_SECRET);

        $this->assertSame(
            self::VECTOR_TOKEN,
            $tokens->issue(42, 7, '9f1c2e4a-5b6d-4e7f-8a9b-0c1d2e3f4a5b', 1893456000),
        );

        $this->assertSame(
            ['u' => 42, 'v' => 7, 'sid' => '9f1c2e4a-5b6d-4e7f-8a9b-0c1d2e3f4a5b', 'exp' => 1893456000],
            $tokens->verify(self::VECTOR_TOKEN),
        );
    }

    public function test_the_token_is_base64url_without_padding(): void
    {
        // A sid full of '?' and '>' forces + and / in standard base64.
        $token = (new VideoToken('secret'))->issue(1, 2, '???>>>???', 1700000000);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/', $token);
    }

    public function test_a_round_trip_returns_the_claims(): void
    {
        $tokens = new VideoToken('secret');

        $this->assertSame(
            ['u' => 5, 'v' => 9, 'sid' => 'abc', 'exp' => 2000000000],
            $tokens->verify($tokens->issue(5, 9, 'abc', 2000000000)),
        );
    }

    public function test_tampered_or_foreign_tokens_are_rejected(): void
    {
        $tokens = new VideoToken('secret');
        [$payload, $signature] = explode('.', $tokens->issue(5, 9, 'abc', 2000000000));

        // Same claims but another video, re-encoded without re-signing.
        $forged = VideoToken::base64UrlEncode('{"u":5,"v":10,"sid":"abc","exp":2000000000}');

        $this->assertNull($tokens->verify($forged.'.'.$signature));
        $this->assertNull($tokens->verify($payload.'.'.strrev($signature)));
        $this->assertNull($tokens->verify($payload));
        $this->assertNull($tokens->verify($payload.'.'.$signature.'.extra'));
        $this->assertNull($tokens->verify(''));
        $this->assertNull((new VideoToken('other-secret'))->verify($payload.'.'.$signature));
    }

    public function test_a_signed_payload_with_the_wrong_shape_is_rejected(): void
    {
        $payload = VideoToken::base64UrlEncode('{"u":"5","v":9,"sid":"abc","exp":2000000000}');
        $signature = VideoToken::base64UrlEncode(hash_hmac('sha256', $payload, 'secret', true));

        $this->assertNull((new VideoToken('secret'))->verify($payload.'.'.$signature));
    }

    public function test_an_empty_secret_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VideoToken('');
    }
}
