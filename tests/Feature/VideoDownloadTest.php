<?php

namespace Tests\Feature;

use App\Models\PlaybackSession;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoDownload;
use App\Services\VideoToken;
use Aws\Result;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithVideoPipeline;
use Tests\TestCase;

class VideoDownloadTest extends TestCase
{
    use InteractsWithVideoPipeline, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpVideoPipeline();
        $this->course->update(['allow_download' => true]);
    }

    private function downloadableVideo(array $attributes = []): Video
    {
        return $this->hlsVideo($attributes + [
            'is_downloadable' => true,
            'renditions' => [144, 240, 360, 720],
        ]);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user, [$user->is_admin ? 'dashboard' : 'access-api']);

        return $this;
    }

    private function download(User $user, string $device = 'phone', array $body = []): TestResponse
    {
        return $this->as($user)
            ->withHeader('X-Device-Id', $device)
            ->postJson("/api/lessons/{$this->lesson->id}/download", $body);
    }

    private function sync(User $user, array $ids, string $device = 'phone'): TestResponse
    {
        return $this->as($user)
            ->withHeader('X-Device-Id', $device)
            ->postJson('/api/downloads/sync', ['download_ids' => $ids]);
    }

    private function tokenFrom(string $url): string
    {
        preg_match('#/p/([^/]+)/hls/#', $url, $m);

        return $m[1];
    }

    public function test_a_subscriber_downloads_the_default_quality(): void
    {
        $video = $this->downloadableVideo();
        $student = $this->subscribedStudent(['name' => 'Sara', 'phone' => '+963944123456']);

        $response = $this->download($student)
            ->assertOk()
            ->assertJsonPath('video_id', $video->id)
            ->assertJsonPath('height', 360)
            ->assertJsonPath('available_heights', [144, 240, 360, 720])
            ->assertJsonPath('watermark_text', 'Sara · 3456');

        // 360p is the third rendition: v2.
        $this->assertMatchesRegularExpression(
            "#^https://media\\.soaar\\.tech/p/[^/]+/hls/{$video->id}/v2/index\\.m3u8$#",
            $response->json('playlist_url'),
        );

        $download = VideoDownload::sole();
        $this->assertSame($download->id, $response->json('download_id'));
        $this->assertSame('phone', $download->device_id);
        $this->assertEqualsWithDelta(now()->addDays(7)->getTimestamp(), $download->offline_until->getTimestamp(), 5);
        $this->assertEqualsWithDelta(now()->addMinutes(360)->getTimestamp(), strtotime($response->json('download_expires_at')), 5);

        $session = PlaybackSession::sole();
        $this->assertSame(PlaybackSession::DOWNLOAD, $session->purpose);

        // The token fetches the key, like a streaming one.
        $claims = (new VideoToken(self::TOKEN_SECRET))->verify($this->tokenFrom($response->json('playlist_url')));
        $this->assertSame($session->id, $claims['sid']);

        $this->flushHeaders()
            ->get("/api/videos/{$video->id}/key?t=".$this->tokenFrom($response->json('playlist_url')))
            ->assertOk();
    }

    public function test_a_chosen_quality_is_used_and_an_unknown_one_refused(): void
    {
        $video = $this->downloadableVideo();
        $student = $this->subscribedStudent();

        $response = $this->download($student, 'phone', ['height' => 720])->assertOk()->assertJsonPath('height', 720);
        $this->assertStringEndsWith("/hls/{$video->id}/v3/index.m3u8", $response->json('playlist_url'));

        $this->download($student, 'phone', ['height' => 1080])->assertUnprocessable();
    }

    public function test_the_default_falls_back_to_the_lowest_quality(): void
    {
        config(['video.download_default_height' => 100]);
        $this->downloadableVideo(['renditions' => [360, 720]]);

        $this->download($this->subscribedStudent())->assertOk()->assertJsonPath('height', 360);
    }

    public function test_downloading_needs_both_flags_and_access(): void
    {
        $video = $this->downloadableVideo();
        $student = $this->subscribedStudent();

        $video->update(['is_downloadable' => false]);
        $this->download($student)->assertForbidden();

        $video->update(['is_downloadable' => true]);
        $this->course->update(['allow_download' => false]);
        $this->download($student)->assertForbidden();

        $this->course->update(['allow_download' => true]);
        $this->download(User::factory()->create())->assertForbidden(); // not subscribed

        $this->assertSame(0, VideoDownload::count());
    }

    public function test_a_free_video_downloads_without_a_subscription(): void
    {
        $this->downloadableVideo(['is_free' => true]);

        $this->download(User::factory()->create())->assertOk();
    }

    public function test_a_video_still_processing_returns_409(): void
    {
        $this->downloadableVideo(['processing_status' => Video::PROCESSING]);

        $this->download($this->subscribedStudent())->assertStatus(409);
    }

    public function test_the_device_id_header_is_required(): void
    {
        $this->downloadableVideo();

        $this->as($this->subscribedStudent())
            ->postJson("/api/lessons/{$this->lesson->id}/download")
            ->assertUnprocessable();
    }

    public function test_offline_playback_never_outlives_the_subscription(): void
    {
        $this->downloadableVideo();
        $student = $this->subscribedStudent();
        Subscription::query()->update(['expires_at' => now()->addDays(3)]);

        $this->download($student)->assertOk();

        $this->assertEqualsWithDelta(
            now()->addDays(3)->getTimestamp(),
            VideoDownload::sole()->offline_until->getTimestamp(),
            5,
        );
    }

    public function test_downloading_again_on_the_same_device_reuses_the_download(): void
    {
        $this->downloadableVideo();
        $student = $this->subscribedStudent();

        $first = $this->download($student)->json('download_id');
        $second = $this->download($student, 'phone', ['height' => 144])->json('download_id');

        $this->assertSame($first, $second);
        $this->assertSame(144, VideoDownload::sole()->height);
    }

    public function test_downloads_are_kept_to_one_device(): void
    {
        $this->downloadableVideo();
        $student = $this->subscribedStudent();

        $id = $this->download($student, 'phone')->assertOk()->json('download_id');

        $this->download($student, 'tablet')
            ->assertStatus(429)
            ->assertJsonPath('errors.code', 'VIDEO_DOWNLOAD_DEVICE_LIMIT');

        // Deleting the phone's copy frees the slot.
        $this->as($student)->deleteJson("/api/downloads/{$id}")->assertNoContent();
        $this->download($student, 'tablet')->assertOk();
    }

    public function test_a_download_does_not_block_streaming_on_another_device(): void
    {
        $this->downloadableVideo();
        $student = $this->subscribedStudent();

        $this->download($student, 'phone')->assertOk();

        $this->as($student)
            ->withHeader('X-Device-Id', 'tablet')
            ->postJson("/api/lessons/{$this->lesson->id}/playback")
            ->assertOk();
    }

    public function test_sync_renews_valid_downloads(): void
    {
        $this->downloadableVideo();
        $student = $this->subscribedStudent();
        $id = $this->download($student)->json('download_id');

        $this->travel(5)->days();

        $this->sync($student, [$id])
            ->assertOk()
            ->assertJsonPath('downloads.0.download_id', $id)
            ->assertJsonPath('downloads.0.valid', true);

        $this->assertEqualsWithDelta(now()->addDays(7)->getTimestamp(), VideoDownload::sole()->offline_until->getTimestamp(), 5);
    }

    public function test_sync_revokes_downloads_that_lost_access(): void
    {
        $video = $this->downloadableVideo();
        $student = $this->subscribedStudent();
        $id = $this->download($student)->json('download_id');

        Subscription::query()->update(['revoked_at' => now()]);

        $this->sync($student, [$id])
            ->assertOk()
            ->assertJsonPath('downloads.0.valid', false)
            ->assertJsonPath('downloads.0.offline_until', null);

        $this->assertNotNull(VideoDownload::sole()->revoked_at);

        // Once revoked it stays revoked, even if access comes back.
        Subscription::query()->update(['revoked_at' => null]);
        $this->sync($student, [$id])->assertJsonPath('downloads.0.valid', false);

        // And a video switched off for download is revoked too.
        $other = $this->subscribedStudent();
        $otherId = $this->download($other, 'other-phone')->json('download_id');
        $video->update(['is_downloadable' => false]);
        $this->sync($other, [$otherId], 'other-phone')->assertJsonPath('downloads.0.valid', false);
    }

    public function test_sync_rejects_other_devices_other_students_and_unknown_ids(): void
    {
        $this->downloadableVideo();
        $student = $this->subscribedStudent();
        $id = $this->download($student)->json('download_id');

        $this->sync($student, [$id], 'tablet')->assertJsonPath('downloads.0.valid', false);
        $this->sync($this->subscribedStudent(), [$id])->assertJsonPath('downloads.0.valid', false);
        $this->sync($student, ['not-a-download'])->assertJsonPath('downloads.0.valid', false);

        // None of those touched the real copy.
        $this->assertNull(VideoDownload::sole()->revoked_at);
        $this->sync($student, [$id])->assertJsonPath('downloads.0.valid', true);
    }

    public function test_a_student_cannot_delete_someone_elses_download(): void
    {
        $this->downloadableVideo();
        $id = $this->download($this->subscribedStudent())->json('download_id');

        $this->as($this->subscribedStudent())->deleteJson("/api/downloads/{$id}")->assertNotFound();
        $this->assertNull(VideoDownload::sole()->revoked_at);
    }

    public function test_qualities_of_older_videos_are_read_from_their_master_playlist(): void
    {
        $video = $this->hlsVideo(['is_downloadable' => true]); // no renditions recorded

        $this->r2Returns(new Result(['Body' => Utils::streamFor(
            "#EXTM3U\n#EXT-X-VERSION:3\n"
            ."#EXT-X-STREAM-INF:BANDWIDTH=1020800,RESOLUTION=640x360\nv0/index.m3u8\n\n"
            ."#EXT-X-STREAM-INF:BANDWIDTH=3220800,RESOLUTION=1280x720\nv1/index.m3u8\n"
        )]));

        $this->download($this->subscribedStudent())
            ->assertOk()
            ->assertJsonPath('available_heights', [360, 720])
            ->assertJsonPath('height', 360);

        $this->assertSame([360, 720], $video->refresh()->renditions);
        $this->assertSame(['GetObject'], $this->r2CommandNames());
    }

    public function test_renditions_are_not_exposed_in_the_api(): void
    {
        $this->downloadableVideo();
        Sanctum::actingAs($this->subscribedStudent(), ['access-api']);

        $this->getJson("/api/courses/{$this->course->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.lessons.0.videos.0.renditions');
    }
}
