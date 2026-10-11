<?php

namespace App\Services;

use App\Exceptions\VideoPipelineException;
use App\Jobs\ProcessVideoJob;
use App\Models\User;
use App\Models\Video;
use Aws\Exception\AwsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The dashboard's direct-to-R2 multipart upload. The API only hands out
 * pre-signed part URLs and stitches the parts together; the file body
 * never passes through it (Cloudflare's proxy caps bodies at 100 MB).
 */
class VideoUploadService
{
    private const EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
    ];

    public function __construct(private readonly VideoStorage $storage)
    {
    }

    /**
     * @param  array{filename: string, size: int, content_type: string, lesson_id: int, title?: string|null}  $data
     */
    public function start(User $uploader, array $data): Video
    {
        $partSize = (int) config('video.part_size');
        $totalParts = (int) ceil($data['size'] / $partSize);

        return DB::transaction(function () use ($uploader, $data, $partSize, $totalParts) {
            $video = Video::create([
                'lesson_id' => $data['lesson_id'],
                'title' => $data['title'] ?? (pathinfo($data['filename'], PATHINFO_FILENAME) ?: $data['filename']),
                'source' => Video::SOURCE_HLS,
                'uploaded_by' => $uploader->id,
                'processing_status' => Video::UPLOADING,
                'original_filename' => $data['filename'],
                'size' => $data['size'],
                'content_type' => $data['content_type'],
                'part_size' => $partSize,
                'total_parts' => $totalParts,
                'is_downloadable' => false,
            ]);

            $key = sprintf('originals/%d/%s.%s', $video->id, Str::uuid(), self::EXTENSIONS[$data['content_type']]);

            $video->update([
                'original_key' => $key,
                'upload_id' => $this->storage->createMultipartUpload($key, $data['content_type']),
            ]);

            return $video;
        });
    }

    /**
     * @param  list<int>  $partNumbers
     * @return array{parts: list<array{part_number: int, url: string}>, expires_at: string}
     */
    public function signParts(Video $video, array $partNumbers): array
    {
        $this->ensureUploading($video);

        $ttl = (int) config('video.part_url_ttl');
        $expiresAt = Carbon::now()->addMinutes($ttl);

        $parts = array_map(fn (int $number) => [
            'part_number' => $number,
            'url' => $this->storage->presignPart($video->original_key, $video->upload_id, $number, $ttl),
        ], $partNumbers);

        return ['parts' => $parts, 'expires_at' => $expiresAt->toIso8601String()];
    }

    /**
     * What R2 already holds, so a re-selected file resumes where it stopped.
     *
     * @return array{part_size: int, total_parts: int, uploaded: list<array{part_number: int, etag: string, size: int}>}
     */
    public function uploadedParts(Video $video): array
    {
        $this->ensureUploading($video);

        try {
            $uploaded = $this->storage->listParts($video->original_key, $video->upload_id);
        } catch (AwsException $e) {
            if (! VideoStorage::isNoSuchUpload($e)) {
                throw $e;
            }

            // Aborted, or dropped by the bucket's 1-day lifecycle rule.
            $video->update([
                'processing_status' => Video::FAILED,
                'upload_id' => null,
                'error' => 'انتهت صلاحية عملية الرفع. يرجى رفع الفيديو من جديد.',
            ]);

            throw new VideoPipelineException('عملية الرفع غير موجودة أو انتهت صلاحيتها.', 404);
        }

        return [
            'part_size' => $video->part_size,
            'total_parts' => $video->total_parts,
            'uploaded' => $uploaded,
        ];
    }

    /**
     * @param  list<array{part_number: int, etag: string}>  $parts
     */
    public function complete(Video $video, array $parts): Video
    {
        $this->ensureUploading($video);

        usort($parts, fn (array $a, array $b) => $a['part_number'] <=> $b['part_number']);

        $this->storage->completeMultipartUpload($video->original_key, $video->upload_id, $parts);

        $storedSize = $this->storage->size($video->original_key);

        if ($storedSize !== $video->size) {
            $this->storage->delete($video->original_key);

            $video->update([
                'processing_status' => Video::FAILED,
                'upload_id' => null,
                'error' => 'حجم الملف المرفوع لا يطابق الحجم المتوقع. يرجى رفع الفيديو من جديد.',
            ]);

            throw new VideoPipelineException('حجم الملف المرفوع لا يطابق الحجم المتوقع.', 422);
        }

        $video->update([
            'processing_status' => Video::PROCESSING,
            'upload_id' => null,
            'error' => null,
        ]);

        ProcessVideoJob::dispatch($video->id);

        return $video;
    }

    /** Cancels an unfinished upload and forgets the video. */
    public function abort(Video $video): void
    {
        if (! $video->isHls() || ! in_array($video->processing_status, [Video::UPLOADING, Video::FAILED], true)) {
            throw VideoPipelineException::conflict('لا يمكن إلغاء رفع هذا الفيديو.');
        }

        if ($video->upload_id) {
            $this->storage->abortMultipartUpload($video->original_key, $video->upload_id);
        }

        $this->storage->deleteVideoMedia($video);
        $video->delete();
    }

    private function ensureUploading(Video $video): void
    {
        if (! $video->isHls() || $video->processing_status !== Video::UPLOADING || ! $video->upload_id) {
            throw VideoPipelineException::conflict('الفيديو ليس في مرحلة الرفع.');
        }
    }
}
