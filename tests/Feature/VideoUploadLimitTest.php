<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\StoreVideoRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class VideoUploadLimitTest extends TestCase
{
    public function test_server_upload_limits_are_large_enough_for_2gb_video_uploads(): void
    {
        $requiredBytes = 2 * 1024 * 1024 * 1024;

        $uploadLimit = $this->toBytes(ini_get('upload_max_filesize'));
        $postLimit = $this->toBytes(ini_get('post_max_size'));

        $this->assertGreaterThanOrEqual($requiredBytes, $uploadLimit, 'upload_max_filesize أقل من 2GB.');
        $this->assertGreaterThanOrEqual($requiredBytes, $postLimit, 'post_max_size أقل من 2GB.');
    }

    public function test_store_video_request_accepts_a_valid_video_file(): void
    {
        $file = UploadedFile::fake()->create('sample-video.mp4', 10, 'video/mp4');

        $validator = Validator::make([
            'lesson_id' => 1,
            'title' => 'Test Video',
            'video' => $file,
        ], (new StoreVideoRequest())->rules());

        $this->assertFalse($validator->fails(), 'The request should accept a valid MP4 file under the app limit.');
    }

    private function toBytes(string $value): int
    {
        if ($value === '-1') {
            return PHP_INT_MAX;
        }

        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) substr($value, 0, -1);

        switch ($unit) {
            case 'g':
                return $number * 1024 * 1024 * 1024;
            case 'm':
                return $number * 1024 * 1024;
            case 'k':
                return $number * 1024;
            default:
                return (int) $value;
        }
    }
}
