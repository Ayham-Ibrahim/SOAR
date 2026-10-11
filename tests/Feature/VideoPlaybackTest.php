<?php

namespace Tests\Feature;

use App\Models\PlaybackSession;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithVideoPipeline;
use Tests\TestCase;

class VideoPlaybackTest extends TestCase
{
    use InteractsWithVideoPipeline, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpVideoPipeline();
    }

    private function play(User $user, string $device = 'device-a', array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user, [$user->is_admin ? 'dashboard' : 'access-api']);

        return $this->withHeader('X-Device-Id', $device)->postJson("/api/lessons/{$this->lesson->id}/playback", $body);
    }

    private function tokenFrom(TestResponse $response): string
    {
        preg_match('#^https://media\.soaar\.tech/p/([^/]+)/hls/\d+/master\.m3u8$#', $response->json('hls_url'), $m);
        $this->assertNotEmpty($m, 'unexpected hls_url: '.$response->json('hls_url'));

        return $m[1];
    }

    private function fetchKey(int $videoId, string $token): TestResponse
    {
        return $this->flushHeaders()->get("/api/videos/{$videoId}/key?t=".urlencode($token));
    }

    public function test_a_subscriber_gets_a_personal_short_lived_link(): void
    {
        $video = $this->hlsVideo();
        $student = $this->subscribedStudent(['name' => 'Sara Ali', 'phone' => '+963 944 123 456']);

        $response = $this->play($student)
            ->assertOk()
            ->assertJsonStructure(['hls_url', 'expires_at', 'watermark_text'])
            ->assertJsonPath('watermark_text', 'Sara Ali · 3456');

        $claims = (new VideoToken(self::TOKEN_SECRET))->verify($this->tokenFrom($response));
        $session = PlaybackSession::sole();

        $this->assertSame($student->id, $claims['u']);
        $this->assertSame($video->id, $claims['v']);
        $this->assertSame($session->id, $claims['sid']);
        $this->assertSame($session->expires_at->getTimestamp(), $claims['exp']);
        $this->assertEqualsWithDelta(now()->addMinutes(120)->getTimestamp(), $claims['exp'], 5);
        $this->assertStringContainsString("/hls/{$video->id}/master.m3u8", $response->json('hls_url'));
        $this->assertSame('device-a', $session->device_id);
    }

    public function test_a_student_without_a_subscription_is_refused(): void
    {
        $this->hlsVideo();

        $this->play(User::factory()->create())->assertForbidden();
        $this->assertSame(0, PlaybackSession::count());
    }

    public function test_a_free_video_plays_without_a_subscription(): void
    {
        $this->hlsVideo(['is_free' => true]);

        $this->play(User::factory()->create())->assertOk();
    }

    public function test_an_admin_can_preview_any_video(): void
    {
        $this->hlsVideo();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->play($admin, 'dash-1')->assertOk();
        $this->play($admin, 'dash-2')->assertOk(); // no device limit for previews
    }

    public function test_a_video_still_processing_returns_409(): void
    {
        $this->hlsVideo(['processing_status' => Video::PROCESSING]);

        $this->play($this->subscribedStudent())->assertStatus(409);
    }

    public function test_a_lesson_without_an_hls_video_returns_409(): void
    {
        Video::create([
            'lesson_id' => $this->lesson->id,
            'title' => 'YouTube',
            'source' => Video::SOURCE_YOUTUBE,
            'youtube_video_id' => 'dQw4w9WgXcQ',
        ]);

        $this->play($this->subscribedStudent())->assertStatus(409);
    }

    public function test_a_specific_video_of_the_lesson_can_be_asked_for(): void
    {
        $this->hlsVideo(['order' => 1]);
        $second = $this->hlsVideo(['order' => 2]);

        $response = $this->play($this->subscribedStudent(), 'device-a', ['video_id' => $second->id])->assertOk();

        $this->assertStringContainsString("/hls/{$second->id}/", $response->json('hls_url'));
    }

    public function test_the_device_id_header_is_required(): void
    {
        $this->hlsVideo();
        Sanctum::actingAs($this->subscribedStudent(), ['access-api']);

        $this->postJson("/api/lessons/{$this->lesson->id}/playback")->assertUnprocessable();
    }

    public function test_a_second_device_is_refused_while_the_first_is_watching(): void
    {
        $this->hlsVideo();
        $student = $this->subscribedStudent();

        $this->play($student, 'phone')->assertOk();
        $this->play($student, 'phone')->assertOk(); // same device: fine
        $this->play($student, 'tablet')->assertStatus(429)->assertJsonPath('errors.code', 'VIDEO_DEVICE_LIMIT');

        // Once the first device's sessions expire, the other may watch.
        $this->travel(121)->minutes();
        $this->play($student, 'tablet')->assertOk();
    }

    public function test_with_the_takeover_flag_a_new_device_revokes_the_old_one(): void
    {
        config(['video.revoke_other_devices' => true]);
        $video = $this->hlsVideo();
        $student = $this->subscribedStudent();

        $phoneToken = $this->tokenFrom($this->play($student, 'phone')->assertOk());
        $this->play($student, 'tablet')->assertOk();

        $this->fetchKey($video->id, $phoneToken)->assertForbidden();
    }

    public function test_the_key_is_served_for_a_valid_token(): void
    {
        $video = $this->hlsVideo();
        $token = $this->tokenFrom($this->play($this->subscribedStudent()));

        $response = $this->fetchKey($video->id, $token)->assertOk();

        $this->assertSame(str_repeat("\x01", 16), $response->getContent());
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_the_key_endpoint_needs_no_bearer_token_but_refuses_bad_ones(): void
    {
        $video = $this->hlsVideo();
        $token = $this->tokenFrom($this->play($this->subscribedStudent()));
        [$payload, $signature] = explode('.', $token);

        $this->fetchKey($video->id, $payload.'.'.strrev($signature))->assertForbidden()->assertContent('');
        $this->fetchKey($video->id, 'nonsense')->assertForbidden();
        $this->flushHeaders()->get("/api/videos/{$video->id}/key")->assertForbidden();
    }

    public function test_a_token_for_one_video_cannot_fetch_another_videos_key(): void
    {
        $this->hlsVideo(['order' => 1]);
        $other = $this->hlsVideo(['order' => 2]);
        $token = $this->tokenFrom($this->play($this->subscribedStudent()));

        $this->fetchKey($other->id, $token)->assertForbidden();
    }

    public function test_the_key_is_refused_after_expiry_or_revocation(): void
    {
        $video = $this->hlsVideo();
        $token = $this->tokenFrom($this->play($this->subscribedStudent()));

        PlaybackSession::query()->update(['revoked_at' => now()]);
        $this->fetchKey($video->id, $token)->assertForbidden();

        PlaybackSession::query()->update(['revoked_at' => null]);
        $this->fetchKey($video->id, $token)->assertOk();

        $this->travel(121)->minutes();
        $this->fetchKey($video->id, $token)->assertForbidden();
    }

    public function test_a_validly_signed_token_whose_session_belongs_to_someone_else_is_refused(): void
    {
        $video = $this->hlsVideo();
        $this->play($this->subscribedStudent())->assertOk();
        $session = PlaybackSession::sole();

        $forged = (new VideoToken(self::TOKEN_SECRET))->issue(
            $session->user_id + 1000,
            $video->id,
            $session->id,
            $session->expires_at->getTimestamp(),
        );

        $this->fetchKey($video->id, $forged)->assertForbidden();
    }

    public function test_the_content_key_never_appears_in_catalog_responses(): void
    {
        $this->hlsVideo(['thumbnail_key' => 'thumbs/1.jpg']);
        Sanctum::actingAs($this->subscribedStudent(), ['access-api']);

        $body = $this->getJson("/api/courses/{$this->course->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('encryption_key', $body);
        $this->assertStringNotContainsString('original_key', $body);
        $this->assertStringNotContainsString(base64_encode(str_repeat("\x01", 16)), $body);
    }
}
