<?php

namespace Tests\Feature;

use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use App\Services\VideoStorage;
use Aws\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithVideoPipeline;
use Tests\TestCase;

class ProcessVideoJobTest extends TestCase
{
    use InteractsWithVideoPipeline, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpVideoPipeline();
    }

    public function test_a_failure_marks_the_video_failed_and_removes_partial_output(): void
    {
        $video = $this->hlsVideo(['processing_status' => Video::PROCESSING, 'encryption_key' => null]);

        $this->r2Returns(
            new Result(['Contents' => [['Key' => "hls/{$video->id}/v0/seg_0000.ts"]]]),
            new Result([]),
        );

        (new ProcessVideoJob($video->id))->failed(new RuntimeException('ffmpeg exploded at /tmp/x'));

        $video->refresh();
        $this->assertSame(Video::FAILED, $video->processing_status);
        $this->assertNotNull($video->error);
        $this->assertStringNotContainsString('/tmp/x', $video->error);
        $this->assertSame(['ListObjectsV2', 'DeleteObjects'], $this->r2CommandNames());
    }

    public function test_the_job_runs_on_the_video_queue_with_a_long_timeout(): void
    {
        $job = new ProcessVideoJob(1);

        $this->assertSame('video', $job->queue);
        $this->assertSame(7200, $job->timeout);
        $this->assertSame(1, $job->tries);
        $this->assertTrue($job->failOnTimeout);
    }

    public function test_a_video_no_longer_processing_is_skipped(): void
    {
        $video = $this->hlsVideo(); // already ready

        (new ProcessVideoJob($video->id))->handle(app(VideoStorage::class));

        $this->assertSame([], $this->r2CommandNames());
    }

    /**
     * End to end with the real FFmpeg (skipped where it isn't installed):
     * a 720p clip becomes encrypted 360p + 720p HLS with the key-URI
     * placeholder, and the key file is never uploaded.
     */
    public function test_a_real_clip_becomes_encrypted_hls(): void
    {
        $finder = new ExecutableFinder;
        if (! $finder->find('ffmpeg') || ! $finder->find('ffprobe')) {
            $this->markTestSkipped('ffmpeg/ffprobe not installed.');
        }

        $source = storage_path('app/tmp/test-source.mp4');
        File::ensureDirectoryExists(dirname($source));
        (new Process([
            'ffmpeg', '-y', '-f', 'lavfi', '-i', 'testsrc=size=1280x720:rate=25:duration=8',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=8',
            '-c:v', 'libx264', '-c:a', 'aac', '-shortest', $source,
        ]))->mustRun();

        $storage = new class($source) extends VideoStorage
        {
            public array $uploaded = [];

            public function __construct(private string $source)
            {
            }

            public function download(string $key, string $path): void
            {
                copy($this->source, $path);
            }

            public function uploadMany(array $files, int $concurrency = 10): void
            {
                foreach ($files as $key => [$path, $type]) {
                    $this->uploaded[$key] = [file_get_contents($path), $type];
                }
            }
        };

        $video = $this->hlsVideo(['processing_status' => Video::PROCESSING, 'encryption_key' => null]);

        try {
            (new ProcessVideoJob($video->id))->handle($storage);
        } finally {
            File::delete($source);
        }

        $video->refresh();
        $prefix = "hls/{$video->id}/";

        $this->assertSame(Video::READY, $video->processing_status);
        $this->assertSame(720, $video->height);
        $this->assertEqualsWithDelta(8, $video->duration_seconds, 1);
        $this->assertSame(16, strlen(base64_decode($video->encryption_key)));
        $this->assertSame("thumbs/{$video->id}.jpg", $video->thumbnail_key);

        $master = $storage->uploaded[$prefix.'master.m3u8'];
        $this->assertSame('application/vnd.apple.mpegurl', $master[1]);
        $this->assertStringContainsString('v0/index.m3u8', $master[0]);
        $this->assertStringContainsString('v1/index.m3u8', $master[0]);
        $this->assertStringNotContainsString('v2/index.m3u8', $master[0]); // no 1080p from a 720p source

        $variant = $storage->uploaded[$prefix.'v0/index.m3u8'][0];
        $this->assertStringContainsString('METHOD=AES-128,URI="__KEY_URI__"', $variant);
        $this->assertSame('video/mp2t', $storage->uploaded[$prefix.'v0/seg_0000.ts'][1]);

        foreach (array_keys($storage->uploaded) as $key) {
            $this->assertStringNotContainsString('enc.key', $key);
            $this->assertStringNotContainsString('keyinfo', $key);
        }

        $this->assertDirectoryDoesNotExist(storage_path("app/tmp/video-{$video->id}"));
    }
}
