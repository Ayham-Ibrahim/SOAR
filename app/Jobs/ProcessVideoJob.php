<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\VideoStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Turns an uploaded original into AES-128 encrypted HLS with up to three
 * renditions, plus a thumbnail. The playlists carry the placeholder key
 * URI __KEY_URI__, which the media Worker rewrites per request; the key
 * itself only lives (encrypted) in videos.encryption_key and is never
 * uploaded.
 *
 * Runs on its own queue with a single worker so FFmpeg can't starve the
 * API:  php artisan queue:work video --queue=video --timeout=7200 --tries=1
 * Needs about twice the original's size free under storage/app/tmp.
 */
class ProcessVideoJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $videoId)
    {
        $this->onConnection(config('video.queue_connection'));
        $this->onQueue(config('video.queue'));
    }

    public function handle(VideoStorage $storage): void
    {
        $video = Video::find($this->videoId);

        // Deleted while queued, or picked up twice.
        if (! $video || $video->processing_status !== Video::PROCESSING) {
            return;
        }

        $workDir = storage_path("app/tmp/video-{$video->id}");
        File::deleteDirectory($workDir);
        File::ensureDirectoryExists($workDir.'/out');

        try {
            $input = $workDir.'/input.'.(pathinfo($video->original_key, PATHINFO_EXTENSION) ?: 'mp4');
            $storage->download($video->original_key, $input);

            $probe = $this->probe($input);
            $renditions = $this->renditions($probe['height']);

            $key = random_bytes(16);
            $this->writeKeyInfo($workDir, $key);

            $this->transcode($workDir, $input, $renditions, $probe['has_audio']);

            $thumbnail = $workDir.'/thumb.jpg';
            $this->thumbnail($input, $thumbnail, $probe['duration']);

            $storage->uploadMany($this->outputFiles($workDir.'/out', $video->hlsPrefix()));

            $thumbnailKey = null;
            if (is_file($thumbnail)) {
                $thumbnailKey = "thumbs/{$video->id}.jpg";
                $storage->uploadMany([$thumbnailKey => [$thumbnail, 'image/jpeg']]);
            }

            $video->update([
                'processing_status' => Video::READY,
                'encryption_key' => base64_encode($key),
                'duration_seconds' => (int) round($probe['duration']),
                'height' => $probe['height'],
                'thumbnail_key' => $thumbnailKey,
                'hls_ready_at' => now(),
                'error' => null,
            ]);

            if (! config('video.keep_originals')) {
                $storage->delete($video->original_key);
            }
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Video processing failed', [
            'video_id' => $this->videoId,
            'exception' => $exception ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);

        $video = Video::find($this->videoId);
        if (! $video) {
            return;
        }

        $video->update([
            'processing_status' => Video::FAILED,
            'error' => $exception instanceof VideoProcessingError
                ? $exception->getMessage()
                : 'فشلت معالجة الفيديو. تأكد من أن الملف سليم ثم أعد رفعه.',
        ]);

        try {
            app(VideoStorage::class)->deletePrefix($video->hlsPrefix());
        } catch (Throwable $e) {
            Log::warning('Could not remove partial HLS output', ['video_id' => $video->id, 'message' => $e->getMessage()]);
        }
    }

    /**
     * @return array{duration: float, height: int, has_audio: bool}
     */
    private function probe(string $input): array
    {
        $output = $this->run([
            config('video.ffprobe'), '-v', 'error',
            '-show_entries', 'stream=codec_type,height:format=duration',
            '-of', 'json',
            $input,
        ], 300);

        $data = json_decode($output, true) ?: [];
        $streams = collect($data['streams'] ?? []);

        $height = (int) ($streams->firstWhere('codec_type', 'video')['height'] ?? 0);
        if ($height === 0) {
            throw new VideoProcessingError('الملف المرفوع لا يحتوي على مسار فيديو صالح.');
        }

        return [
            'duration' => (float) ($data['format']['duration'] ?? 0),
            'height' => $height,
            'has_audio' => $streams->contains('codec_type', 'audio'),
        ];
    }

    /**
     * The ladder up to the source height; never upscaled, never empty.
     *
     * @return list<array{0: int, 1: int}> [height, max kbit/s]
     */
    private function renditions(int $sourceHeight): array
    {
        $ladder = config('video.renditions');

        $fitting = array_values(array_filter($ladder, fn (array $r) => $r[0] <= $sourceHeight));

        return $fitting ?: [$ladder[0]];
    }

    /** keyinfo: key URI written into the playlists, key file, IV. */
    private function writeKeyInfo(string $workDir, string $key): void
    {
        File::put($workDir.'/enc.key', $key);
        File::put($workDir.'/keyinfo', implode("\n", [
            '__KEY_URI__',
            str_replace('\\', '/', $workDir.'/enc.key'),
            bin2hex(random_bytes(16)),
        ])."\n");
    }

    /**
     * @param  list<array{0: int, 1: int}>  $renditions
     */
    private function transcode(string $workDir, string $input, array $renditions, bool $hasAudio): void
    {
        $count = count($renditions);

        $split = $count > 1
            ? '[0:v]split='.$count.implode('', array_map(fn ($i) => "[s{$i}]", range(0, $count - 1))).';'
            : '';
        $scales = [];
        foreach ($renditions as $i => [$height]) {
            $source = $count > 1 ? "[s{$i}]" : '[0:v]';
            $scales[] = "{$source}scale=-2:{$height}[v{$i}]";
        }

        $args = [config('video.ffmpeg'), '-y', '-i', $input, '-filter_complex', $split.implode(';', $scales)];

        foreach (array_keys($renditions) as $i) {
            File::ensureDirectoryExists("{$workDir}/out/v{$i}");
            array_push($args, '-map', "[v{$i}]");
            if ($hasAudio) {
                array_push($args, '-map', '0:a:0');
            }
        }

        array_push($args, '-c:v', 'libx264', '-preset', 'veryfast', '-profile:v', 'main', '-crf', '23');

        foreach ($renditions as $i => [, $kbps]) {
            array_push($args, "-maxrate:v:{$i}", "{$kbps}k", "-bufsize:v:{$i}", ($kbps * 2).'k');
        }

        array_push($args, '-force_key_frames', 'expr:gte(t,n_forced*6)', '-sc_threshold', '0');

        if ($hasAudio) {
            array_push($args, '-c:a', 'aac', '-b:a', '128k', '-ac', '2');
        }

        $streamMap = implode(' ', array_map(
            fn ($i) => $hasAudio ? "v:{$i},a:{$i}" : "v:{$i}",
            array_keys($renditions),
        ));

        array_push($args,
            '-f', 'hls',
            '-hls_time', '6',
            '-hls_playlist_type', 'vod',
            '-hls_key_info_file', 'keyinfo',
            '-hls_segment_filename', 'out/v%v/seg_%04d.ts',
            '-master_pl_name', 'master.m3u8',
            '-var_stream_map', $streamMap,
            'out/v%v/index.m3u8',
        );

        $this->run($args, $this->timeout, $workDir);

        if (! is_file($workDir.'/out/master.m3u8')) {
            throw new RuntimeException('FFmpeg finished without writing master.m3u8.');
        }
    }

    private function thumbnail(string $input, string $output, float $duration): void
    {
        try {
            $this->run([
                config('video.ffmpeg'), '-y',
                '-ss', (string) round($duration * 0.1, 2),
                '-i', $input,
                '-frames:v', '1',
                '-vf', 'scale=640:-2',
                $output,
            ], 300);
        } catch (Throwable $e) {
            // A missing thumbnail shouldn't sink an otherwise good video.
            Log::warning('Thumbnail extraction failed', ['video_id' => $this->videoId, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Playlists and segments only — enc.key and keyinfo live outside out/.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function outputFiles(string $outDir, string $prefix): array
    {
        $types = ['m3u8' => 'application/vnd.apple.mpegurl', 'ts' => 'video/mp2t'];
        $files = [];

        foreach (File::allFiles($outDir) as $file) {
            $type = $types[strtolower($file->getExtension())] ?? null;
            if ($type === null) {
                continue;
            }

            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $files[$prefix.$relative] = [$file->getPathname(), $type];
        }

        return $files;
    }

    /**
     * @param  list<string>  $command  never a shell string
     */
    private function run(array $command, int $timeout, ?string $cwd = null): string
    {
        $process = new Process($command, $cwd, null, null, $timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
