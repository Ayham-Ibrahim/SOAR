<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\Unit;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoStorage;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\S3\S3Client;
use Laravel\Sanctum\Sanctum;

/**
 * A lesson in a course, an R2 client whose responses each test queues up
 * (Aws\MockHandler — nothing leaves the machine), and a token secret.
 */
trait InteractsWithVideoPipeline
{
    protected const TOKEN_SECRET = 'test-video-token-secret';

    protected MockHandler $r2;

    /** @var list<CommandInterface> */
    protected array $r2Commands = [];

    protected Course $course;

    protected Lesson $lesson;

    protected function setUpVideoPipeline(): void
    {
        config([
            'video.token_secret' => self::TOKEN_SECRET,
            'video.media_base' => 'https://media.soaar.tech',
            'video.part_size' => 25 * 1024 * 1024,
        ]);

        $this->r2 = new MockHandler;

        $client = new S3Client([
            'region' => 'auto',
            'version' => 'latest',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            'handler' => $this->r2,
        ]);

        $this->app->instance(VideoStorage::class, new VideoStorage($client, 'soar-video'));

        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'SubCategory', 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'Subject', 'order' => 1]);
        $unit = Unit::create(['subject_id' => $subject->id, 'title' => 'Unit', 'order' => 1]);

        $this->course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::create(['name' => 'Teacher'])->id,
            'title' => 'Physics',
            'description' => 'desc',
            'price' => 50000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);
        $this->lesson = Lesson::create(['unit_id' => $unit->id, 'title' => 'Lesson']);
        $this->lesson->courses()->attach($this->course->id);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin, ['dashboard']);

        return $admin;
    }

    protected function subscribedStudent(array $attributes = []): User
    {
        $student = User::factory()->create($attributes);
        Subscription::create([
            'student_id' => $student->id,
            'course_id' => $this->course->id,
            'source' => 'direct',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(20),
            'is_active' => true,
        ]);

        return $student;
    }

    protected function hlsVideo(array $attributes = []): Video
    {
        $video = Video::create($attributes + [
            'lesson_id' => $this->lesson->id,
            'title' => 'HLS lesson',
            'source' => Video::SOURCE_HLS,
            'processing_status' => Video::READY,
            'original_filename' => 'lesson.mp4',
            'size' => 60 * 1024 * 1024,
            'content_type' => 'video/mp4',
            'part_size' => 25 * 1024 * 1024,
            'total_parts' => 3,
            'encryption_key' => base64_encode(str_repeat("\x01", 16)),
        ]);

        if (! array_key_exists('original_key', $attributes)) {
            $video->update(['original_key' => "originals/{$video->id}/source.mp4"]);
        }

        return $video;
    }

    protected function uploadingVideo(): Video
    {
        return $this->hlsVideo([
            'processing_status' => Video::UPLOADING,
            'upload_id' => 'upload-123',
            'encryption_key' => null,
        ]);
    }

    /**
     * Queues R2's answers, in order: an Aws\Result, an exception, or a
     * callable receiving the command. A request beyond the queue fails the
     * test ("Mock queue is empty").
     */
    protected function r2Returns(mixed ...$outcomes): void
    {
        foreach ($outcomes as $outcome) {
            $this->r2->append(function (CommandInterface $command) use ($outcome) {
                $this->r2Commands[] = $command;

                return is_callable($outcome) ? $outcome($command) : $outcome;
            });
        }
    }

    /** @return list<string> */
    protected function r2CommandNames(): array
    {
        return array_map(fn (CommandInterface $command) => $command->getName(), $this->r2Commands);
    }
}
