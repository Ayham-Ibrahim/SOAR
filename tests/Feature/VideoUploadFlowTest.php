<?php

namespace Tests\Feature;

use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use Aws\Command;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithVideoPipeline;
use Tests\TestCase;

class VideoUploadFlowTest extends TestCase
{
    use InteractsWithVideoPipeline, RefreshDatabase;

    private const MB = 1024 * 1024;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpVideoPipeline();
    }

    private function noSuchUpload(string $operation): S3Exception
    {
        return new S3Exception('The specified multipart upload does not exist.', new Command($operation), [
            'code' => 'NoSuchUpload',
        ]);
    }

    public function test_starting_an_upload_creates_the_video_and_the_multipart_upload(): void
    {
        $admin = $this->admin();
        $this->r2Returns(function (CommandInterface $command) {
            $this->assertSame('CreateMultipartUpload', $command->getName());
            $this->assertSame('soar-video', $command['Bucket']);
            $this->assertSame('video/mp4', $command['ContentType']);
            $this->assertMatchesRegularExpression('#^originals/\d+/[0-9a-f-]{36}\.mp4$#', $command['Key']);

            return new Result(['UploadId' => 'upload-abc']);
        });

        $response = $this->postJson('/api/videos/uploads', [
            'filename' => 'محاضرة 1.mp4',
            'size' => 60 * self::MB,
            'content_type' => 'video/mp4',
            'lesson_id' => $this->lesson->id,
        ]);

        $video = Video::firstOrFail();

        $response->assertCreated()->assertExactJson([
            'video_id' => $video->id,
            'upload_id' => 'upload-abc',
            'part_size' => 25 * self::MB,
            'total_parts' => 3,
        ]);

        $this->assertSame(Video::SOURCE_HLS, $video->source);
        $this->assertSame(Video::UPLOADING, $video->processing_status);
        $this->assertSame('محاضرة 1', $video->title);
        $this->assertSame($admin->id, $video->uploaded_by);
        $this->assertStringStartsWith("originals/{$video->id}/", $video->original_key);
    }

    public function test_starting_an_upload_is_validated(): void
    {
        $this->admin();

        $this->postJson('/api/videos/uploads', [
            'filename' => 'big.mp4',
            'size' => 2147483648 + 1,
            'content_type' => 'video/avi',
            'lesson_id' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['size', 'content_type', 'lesson_id']);

        $this->assertSame(0, Video::count());
        $this->assertSame([], $this->r2CommandNames());
    }

    public function test_a_2gb_upload_is_accepted(): void
    {
        $this->admin();
        $this->r2Returns(new Result(['UploadId' => 'u']));

        $this->postJson('/api/videos/uploads', [
            'filename' => 'long.mov',
            'size' => 2147483648,
            'content_type' => 'video/quicktime',
            'lesson_id' => $this->lesson->id,
        ])->assertCreated()->assertJsonPath('total_parts', 82);

        $this->assertStringEndsWith('.mov', Video::first()->original_key);
    }

    public function test_upload_endpoints_are_for_the_dashboard_only(): void
    {
        $video = $this->uploadingVideo();
        Sanctum::actingAs($this->subscribedStudent(), ['access-api']);

        $this->postJson('/api/videos/uploads', [])->assertForbidden();
        $this->postJson("/api/videos/{$video->id}/uploads/parts", ['part_numbers' => [1]])->assertForbidden();
        $this->getJson("/api/videos/{$video->id}/uploads/parts")->assertForbidden();
        $this->postJson("/api/videos/{$video->id}/uploads/complete", [])->assertForbidden();
        $this->deleteJson("/api/videos/{$video->id}/uploads")->assertForbidden();
        $this->getJson("/api/videos/{$video->id}")->assertForbidden();
    }

    public function test_part_urls_are_presigned_for_the_requested_parts(): void
    {
        $this->admin();
        $video = $this->uploadingVideo();

        $response = $this->postJson("/api/videos/{$video->id}/uploads/parts", ['part_numbers' => [3, 1]])
            ->assertOk()
            ->assertJsonCount(2, 'parts')
            ->assertJsonPath('parts.0.part_number', 3)
            ->assertJsonPath('parts.1.part_number', 1);

        $url = $response->json('parts.0.url');
        $this->assertStringContainsString("/soar-video/originals/{$video->id}/source.mp4", $url);
        $this->assertStringContainsString('partNumber=3', $url);
        $this->assertStringContainsString('uploadId=upload-123', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
        $this->assertStringContainsString('X-Amz-Expires=3600', $url);
        $this->assertNotNull($response->json('expires_at'));

        // Signing is local: nothing was sent to R2.
        $this->assertSame([], $this->r2CommandNames());
    }

    public function test_part_requests_are_validated(): void
    {
        $this->admin();
        $video = $this->uploadingVideo();

        $this->postJson("/api/videos/{$video->id}/uploads/parts", ['part_numbers' => range(1, 21)])
            ->assertUnprocessable()->assertJsonValidationErrors('part_numbers');
        $this->postJson("/api/videos/{$video->id}/uploads/parts", ['part_numbers' => [4]])
            ->assertUnprocessable()->assertJsonValidationErrors('part_numbers.0');
        $this->postJson("/api/videos/{$video->id}/uploads/parts", ['part_numbers' => [1, 1]])
            ->assertUnprocessable();
        $this->postJson("/api/videos/{$video->id}/uploads/parts", ['part_numbers' => []])
            ->assertUnprocessable();
    }

    public function test_part_urls_are_refused_once_the_upload_is_finished(): void
    {
        $this->admin();
        $video = $this->hlsVideo(['processing_status' => Video::PROCESSING]);

        $this->postJson("/api/videos/{$video->id}/uploads/parts", ['part_numbers' => [1]])->assertStatus(409);
    }

    public function test_uploaded_parts_are_listed_across_pages_for_resuming(): void
    {
        $this->admin();
        $video = $this->uploadingVideo();

        $this->r2Returns(
            function (CommandInterface $command) {
                $this->assertArrayNotHasKey('PartNumberMarker', $command->toArray());

                return new Result([
                    'IsTruncated' => true,
                    'NextPartNumberMarker' => 1,
                    'Parts' => [['PartNumber' => 1, 'ETag' => '"e1"', 'Size' => 25 * self::MB]],
                ]);
            },
            function (CommandInterface $command) {
                $this->assertSame(1, $command['PartNumberMarker']);

                return new Result([
                    'IsTruncated' => false,
                    'Parts' => [['PartNumber' => 3, 'ETag' => '"e3"', 'Size' => 10 * self::MB]],
                ]);
            },
        );

        $this->getJson("/api/videos/{$video->id}/uploads/parts")
            ->assertOk()
            ->assertExactJson([
                'part_size' => 25 * self::MB,
                'total_parts' => 3,
                'uploaded' => [
                    ['part_number' => 1, 'etag' => '"e1"', 'size' => 25 * self::MB],
                    ['part_number' => 3, 'etag' => '"e3"', 'size' => 10 * self::MB],
                ],
            ]);
    }

    public function test_an_upload_r2_no_longer_has_is_marked_failed(): void
    {
        $this->admin();
        $video = $this->uploadingVideo();
        $this->r2Returns($this->noSuchUpload('ListParts'));

        $this->getJson("/api/videos/{$video->id}/uploads/parts")->assertNotFound();

        $video->refresh();
        $this->assertSame(Video::FAILED, $video->processing_status);
        $this->assertNull($video->upload_id);
        $this->assertNotNull($video->error);
    }

    public function test_completing_assembles_the_parts_and_queues_processing(): void
    {
        Queue::fake();
        $this->admin();
        $video = $this->uploadingVideo();

        $this->r2Returns(
            function (CommandInterface $command) {
                $this->assertSame('CompleteMultipartUpload', $command->getName());
                $this->assertSame('upload-123', $command['UploadId']);
                $this->assertSame([
                    ['PartNumber' => 1, 'ETag' => '"e1"'],
                    ['PartNumber' => 2, 'ETag' => '"e2"'],
                    ['PartNumber' => 3, 'ETag' => '"e3"'],
                ], $command['MultipartUpload']['Parts']);

                return new Result([]);
            },
            new Result(['ContentLength' => 60 * self::MB]),
        );

        $this->postJson("/api/videos/{$video->id}/uploads/complete", [
            'parts' => [
                ['part_number' => 3, 'etag' => '"e3"'],
                ['part_number' => 1, 'etag' => '"e1"'],
                ['part_number' => 2, 'etag' => '"e2"'],
            ],
        ])->assertOk()->assertExactJson(['video_id' => $video->id, 'status' => 'processing']);

        $this->assertSame(['CompleteMultipartUpload', 'HeadObject'], $this->r2CommandNames());

        $video->refresh();
        $this->assertSame(Video::PROCESSING, $video->processing_status);
        $this->assertNull($video->upload_id);

        Queue::assertPushedOn('video', ProcessVideoJob::class, fn (ProcessVideoJob $job) => $job->videoId === $video->id);
    }

    public function test_completing_needs_every_part_exactly_once(): void
    {
        $this->admin();
        $video = $this->uploadingVideo();

        $this->postJson("/api/videos/{$video->id}/uploads/complete", [
            'parts' => [['part_number' => 1, 'etag' => 'a'], ['part_number' => 2, 'etag' => 'b']],
        ])->assertUnprocessable()->assertJsonValidationErrors('parts');

        $this->postJson("/api/videos/{$video->id}/uploads/complete", [
            'parts' => [
                ['part_number' => 1, 'etag' => 'a'],
                ['part_number' => 1, 'etag' => 'b'],
                ['part_number' => 2, 'etag' => 'c'],
            ],
        ])->assertUnprocessable();

        $this->postJson("/api/videos/{$video->id}/uploads/complete", [
            'parts' => [
                ['part_number' => 1, 'etag' => 'a'],
                ['part_number' => 2, 'etag' => 'b'],
                ['part_number' => 4, 'etag' => 'c'],
            ],
        ])->assertUnprocessable();

        $this->assertSame([], $this->r2CommandNames());
    }

    public function test_a_size_mismatch_after_completing_fails_the_video(): void
    {
        Queue::fake();
        $this->admin();
        $video = $this->uploadingVideo();

        $this->r2Returns(new Result([]), new Result(['ContentLength' => 59 * self::MB]), new Result([]));

        $this->postJson("/api/videos/{$video->id}/uploads/complete", [
            'parts' => [
                ['part_number' => 1, 'etag' => 'a'],
                ['part_number' => 2, 'etag' => 'b'],
                ['part_number' => 3, 'etag' => 'c'],
            ],
        ])->assertUnprocessable();

        $this->assertSame(['CompleteMultipartUpload', 'HeadObject', 'DeleteObject'], $this->r2CommandNames());
        $this->assertSame(Video::FAILED, $video->refresh()->processing_status);
        Queue::assertNothingPushed();
    }

    public function test_aborting_cancels_the_upload_and_removes_the_video(): void
    {
        $this->admin();
        $video = $this->uploadingVideo();

        // R2 already dropped it: still fine.
        $this->r2Returns(
            $this->noSuchUpload('AbortMultipartUpload'),
            new Result([]),                     // DeleteObject (original)
            new Result(['Contents' => []]),     // ListObjectsV2 hls/{id}/
        );

        $this->deleteJson("/api/videos/{$video->id}/uploads")->assertNoContent();

        $this->assertModelMissing($video);
        $this->assertSame('AbortMultipartUpload', $this->r2CommandNames()[0]);
    }

    public function test_a_ready_video_cannot_be_aborted(): void
    {
        $this->admin();
        $video = $this->hlsVideo();

        $this->deleteJson("/api/videos/{$video->id}/uploads")->assertStatus(409);
        $this->assertModelExists($video);
    }

    public function test_status_is_reported_without_storage_internals(): void
    {
        $this->admin();
        $video = $this->hlsVideo(['duration_seconds' => 610, 'thumbnail_key' => 'thumbs/1.jpg']);

        $response = $this->getJson("/api/videos/{$video->id}")
            ->assertOk()
            ->assertJsonPath('id', $video->id)
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('duration', 610)
            ->assertJsonPath('error', null);

        $this->assertStringContainsString('X-Amz-Expires=86400', $response->json('thumbnail_url'));
        $this->assertStringNotContainsString('originals/', $response->getContent());
    }

    public function test_storage_errors_are_reported_without_leaking_details(): void
    {
        $this->admin();
        $this->r2Returns(new S3Exception('Access Denied for https://account.r2.cloudflarestorage.com/soar-video', new Command('CreateMultipartUpload'), [
            'code' => 'AccessDenied',
        ]));

        $response = $this->postJson('/api/videos/uploads', [
            'filename' => 'a.mp4',
            'size' => self::MB,
            'content_type' => 'video/mp4',
            'lesson_id' => $this->lesson->id,
        ])->assertStatus(502);

        $this->assertStringNotContainsString('r2.cloudflarestorage.com', $response->getContent());
        $this->assertStringNotContainsString('soar-video', $response->getContent());
        // The transaction rolled back: no half-created video.
        $this->assertSame(0, Video::count());
    }

    public function test_internal_fields_never_appear_in_the_admin_video_api(): void
    {
        $this->admin();
        $video = $this->hlsVideo(['upload_id' => 'u-1', 'thumbnail_key' => 'thumbs/x.jpg']);

        $body = $this->getJson("/api/admin/videos/{$video->id}")->assertOk()->getContent();

        foreach (['original_key', 'upload_id', 'encryption_key', 'thumbnail_key', 'originals/'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_deleting_an_hls_video_from_the_dashboard_cleans_up_r2(): void
    {
        $this->admin();
        $video = $this->hlsVideo(['thumbnail_key' => 'thumbs/9.jpg']);

        $this->r2Returns(
            new Result([]),
            new Result([]),
            new Result(['Contents' => [['Key' => "hls/{$video->id}/master.m3u8"]]]),
            new Result([]),
        );

        $this->deleteJson("/api/admin/videos/{$video->id}")->assertOk();

        $this->assertSame(['DeleteObject', 'DeleteObject', 'ListObjectsV2', 'DeleteObjects'], $this->r2CommandNames());
        $this->assertModelMissing($video);
    }

    public function test_guests_get_401(): void
    {
        $this->postJson('/api/videos/uploads', [])->assertUnauthorized();
    }
}
