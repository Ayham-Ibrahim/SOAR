<?php

namespace App\Services;

use App\Models\Video;
use Aws\CommandPool;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

/**
 * Every R2 call of the video pipeline goes through here. The bucket is
 * private: objects are only reachable through pre-signed, short-lived
 * requests (browser part uploads, thumbnails) or the media Worker.
 *
 * Tests swap the client for one with an Aws\MockHandler.
 */
class VideoStorage
{
    public function __construct(
        private ?S3Client $client = null,
        private ?string $bucket = null,
    ) {
    }

    public function client(): S3Client
    {
        return $this->client ??= Storage::disk('s3')->getClient();
    }

    public function bucket(): string
    {
        return $this->bucket ??= (string) config('filesystems.disks.s3.bucket');
    }

    public function createMultipartUpload(string $key, string $contentType): string
    {
        return $this->client()->createMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'ContentType' => $contentType,
        ])['UploadId'];
    }

    /** A URL the browser PUTs one part's bytes to, no credentials needed. */
    public function presignPart(string $key, string $uploadId, int $partNumber, int $ttlMinutes): string
    {
        $command = $this->client()->getCommand('UploadPart', [
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
        ]);

        return (string) $this->client()->createPresignedRequest($command, "+{$ttlMinutes} minutes")->getUri();
    }

    /**
     * Parts R2 already holds for this upload, across every page.
     *
     * @return list<array{part_number: int, etag: string, size: int}>
     */
    public function listParts(string $key, string $uploadId): array
    {
        $parts = [];
        $marker = null;

        do {
            $result = $this->client()->listParts(array_filter([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'UploadId' => $uploadId,
                'PartNumberMarker' => $marker,
            ], fn ($value) => $value !== null));

            foreach ($result['Parts'] ?? [] as $part) {
                $parts[] = [
                    'part_number' => (int) $part['PartNumber'],
                    'etag' => $part['ETag'],
                    'size' => (int) $part['Size'],
                ];
            }

            $marker = $result['NextPartNumberMarker'] ?? null;
        } while (! empty($result['IsTruncated']) && $marker !== null);

        return $parts;
    }

    /**
     * @param  list<array{part_number: int, etag: string}>  $parts  sorted by part number
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): void
    {
        $this->client()->completeMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => array_map(fn (array $part) => [
                    'PartNumber' => $part['part_number'],
                    'ETag' => $part['etag'],
                ], $parts),
            ],
        ]);
    }

    /** Safe to call on an upload R2 already dropped. */
    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        try {
            $this->client()->abortMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'UploadId' => $uploadId,
            ]);
        } catch (AwsException $e) {
            if (! self::isNoSuchUpload($e)) {
                throw $e;
            }
        }
    }

    public function size(string $key): int
    {
        return (int) $this->client()->headObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
        ])['ContentLength'];
    }

    public function getText(string $key): string
    {
        return (string) $this->client()->getObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
        ])['Body'];
    }

    /** Streams the object to disk — a 2 GB original never sits in memory. */
    public function download(string $key, string $path): void
    {
        $this->client()->getObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'SaveAs' => $path,
        ]);
    }

    /**
     * Uploads local files in parallel.
     *
     * @param  array<string, array{0: string, 1: string}>  $files  key => [local path, content type]
     */
    public function uploadMany(array $files, int $concurrency = 10): void
    {
        $commands = (function () use ($files) {
            foreach ($files as $key => [$path, $contentType]) {
                yield $this->client()->getCommand('PutObject', [
                    'Bucket' => $this->bucket(),
                    'Key' => $key,
                    'SourceFile' => $path,
                    'ContentType' => $contentType,
                ]);
            }
        })();

        $failure = null;

        CommandPool::batch($this->client(), $commands, [
            'concurrency' => $concurrency,
            'rejected' => function (AwsException $reason) use (&$failure) {
                $failure ??= $reason;
            },
        ]);

        if ($failure) {
            throw $failure;
        }
    }

    public function delete(string $key): void
    {
        $this->client()->deleteObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
        ]);
    }

    /** Deletes every object under the prefix, 1000 per request. */
    public function deletePrefix(string $prefix): void
    {
        $token = null;

        do {
            $result = $this->client()->listObjectsV2(array_filter([
                'Bucket' => $this->bucket(),
                'Prefix' => $prefix,
                'ContinuationToken' => $token,
            ], fn ($value) => $value !== null));

            $objects = array_map(fn (array $object) => ['Key' => $object['Key']], $result['Contents'] ?? []);

            if ($objects !== []) {
                $this->client()->deleteObjects([
                    'Bucket' => $this->bucket(),
                    'Delete' => ['Objects' => $objects, 'Quiet' => true],
                ]);
            }

            $token = $result['NextContinuationToken'] ?? null;
        } while (! empty($result['IsTruncated']) && $token !== null);
    }

    /** Everything a video left in the bucket: original, HLS output, thumbnail. */
    public function deleteVideoMedia(Video $video): void
    {
        if ($video->original_key) {
            $this->delete($video->original_key);
        }

        if ($video->thumbnail_key) {
            $this->delete($video->thumbnail_key);
        }

        $this->deletePrefix($video->hlsPrefix());
    }

    /** A pre-signed GET; signing is local, no request is made. */
    public function temporaryUrl(string $key, int $ttlMinutes): string
    {
        $command = $this->client()->getCommand('GetObject', [
            'Bucket' => $this->bucket(),
            'Key' => $key,
        ]);

        return (string) $this->client()->createPresignedRequest($command, "+{$ttlMinutes} minutes")->getUri();
    }

    public static function isNoSuchUpload(AwsException $e): bool
    {
        return $e->getAwsErrorCode() === 'NoSuchUpload';
    }
}
