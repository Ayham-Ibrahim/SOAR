<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoYouTubeSourceTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO_ID = 'dQw4w9WgXcQ';

    private Course $course;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'SubCategory', 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'Subject', 'order' => 1]);
        $unit = Unit::create(['subject_id' => $subject->id, 'title' => 'Unit', 'order' => 1]);

        $this->course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::create(['name' => 'Teacher'])->id,
            'title' => 'Algebra',
            'description' => 'desc',
            'price' => 50000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);
        $this->lesson = Lesson::create(['unit_id' => $unit->id, 'title' => 'Lesson']);
        $this->lesson->courses()->attach($this->course->id);
    }

    private function asAdmin(): static
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        return $this;
    }

    private function subscribedStudent(): User
    {
        $student = User::factory()->create();
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

    private function youtubeVideo(bool $isFree = false): Video
    {
        return Video::create([
            'lesson_id' => $this->lesson->id,
            'title' => 'YouTube lesson',
            'source' => Video::SOURCE_YOUTUBE,
            'youtube_video_id' => self::VIDEO_ID,
            'is_free' => $isFree,
        ]);
    }

    public function test_admin_adds_a_video_by_youtube_link(): void
    {
        $this->asAdmin();

        $response = $this->postJson('/api/admin/videos', [
            'lesson_id' => $this->lesson->id,
            'title' => 'الدرس الأول',
            'youtube_url' => 'https://www.youtube.com/watch?v='.self::VIDEO_ID.'&t=30s',
            'duration_seconds' => 610,
            'is_downloadable' => true, // ignored: YouTube videos are streamed
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.source', 'youtube')
            ->assertJsonPath('data.youtube_video_id', self::VIDEO_ID)
            ->assertJsonPath('data.youtube_url', 'https://www.youtube.com/watch?v='.self::VIDEO_ID)
            ->assertJsonPath('data.url', null)
            ->assertJsonPath('data.duration_seconds', 610)
            ->assertJsonPath('data.is_downloadable', false)
            // No thumbnail uploaded, so YouTube's own is used.
            ->assertJsonPath('data.thumbnail', 'https://img.youtube.com/vi/'.self::VIDEO_ID.'/hqdefault.jpg');
    }

    public function test_every_youtube_link_shape_is_accepted(): void
    {
        $this->asAdmin();

        $links = [
            'https://youtu.be/'.self::VIDEO_ID,
            'https://www.youtube.com/shorts/'.self::VIDEO_ID,
            'https://m.youtube.com/watch?v='.self::VIDEO_ID,
            'https://www.youtube.com/embed/'.self::VIDEO_ID,
            'youtube.com/watch?v='.self::VIDEO_ID,
            self::VIDEO_ID,
        ];

        foreach ($links as $link) {
            $this->postJson('/api/admin/videos', [
                'lesson_id' => $this->lesson->id,
                'title' => 'درس',
                'youtube_url' => $link,
            ])->assertCreated()->assertJsonPath('data.youtube_video_id', self::VIDEO_ID);
        }
    }

    public function test_an_invalid_link_or_a_missing_one_is_rejected(): void
    {
        $this->asAdmin();

        $this->postJson('/api/admin/videos', [
            'lesson_id' => $this->lesson->id,
            'title' => 'درس',
            'youtube_url' => 'https://vimeo.com/123456789',
        ])->assertUnprocessable()->assertJsonValidationErrors('youtube_url');

        $this->postJson('/api/admin/videos', [
            'lesson_id' => $this->lesson->id,
            'title' => 'درس',
        ])->assertUnprocessable()->assertJsonValidationErrors('youtube_url');

        $this->assertSame(0, Video::count());
    }

    public function test_uploading_a_file_is_refused_while_uploads_are_switched_off(): void
    {
        $this->asAdmin();

        $this->postJson('/api/admin/videos', [
            'lesson_id' => $this->lesson->id,
            'title' => 'درس',
            'video' => UploadedFile::fake()->create('lesson.mp4', 10, 'video/mp4'),
        ])->assertUnprocessable()->assertJsonValidationErrors('video');

        $this->assertSame(0, Video::count());
    }

    /**
     * The old path is only switched off, not removed: flipping the flag back
     * accepts video files again.
     */
    public function test_turning_uploads_back_on_accepts_a_video_file(): void
    {
        config(['video.uploads_enabled' => true]);
        $this->asAdmin();

        $this->postJson('/api/admin/videos', [
            'lesson_id' => $this->lesson->id,
            'title' => 'درس مرفوع',
            'video' => UploadedFile::fake()->create('lesson.mp4', 10, 'video/mp4'),
            'is_downloadable' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'upload')
            ->assertJsonPath('data.youtube_video_id', null)
            ->assertJsonPath('data.is_downloadable', true);

        $this->assertNotNull(Video::first()->url);
    }

    public function test_updating_replaces_the_link_and_switches_an_uploaded_video_over(): void
    {
        $uploaded = Video::create([
            'lesson_id' => $this->lesson->id,
            'title' => 'درس قديم',
            'source' => Video::SOURCE_UPLOAD,
            'url' => '/storage/videos/old-lesson.mp4',
            'is_downloadable' => true,
        ]);

        $this->asAdmin()
            ->putJson("/api/admin/videos/{$uploaded->id}", ['youtube_url' => 'https://youtu.be/'.self::VIDEO_ID])
            ->assertOk()
            ->assertJsonPath('data.source', 'youtube')
            ->assertJsonPath('data.youtube_video_id', self::VIDEO_ID)
            ->assertJsonPath('data.url', null)
            ->assertJsonPath('data.is_downloadable', false);
    }

    public function test_old_uploaded_videos_keep_working_for_subscribers(): void
    {
        Video::create([
            'lesson_id' => $this->lesson->id,
            'title' => 'درس قديم',
            'source' => Video::SOURCE_UPLOAD,
            'url' => '/storage/videos/old-lesson.mp4',
        ]);

        Sanctum::actingAs($this->subscribedStudent(), ['access-api']);

        $this->getJson("/api/courses/{$this->course->id}")
            ->assertOk()
            ->assertJsonPath('data.has_access', true)
            ->assertJsonPath('data.lessons.0.videos.0.url', '/storage/videos/old-lesson.mp4');
    }

    public function test_the_youtube_link_is_hidden_from_everyone_without_access(): void
    {
        $this->youtubeVideo();

        // Guest browsing the catalog.
        $video = $this->getJson("/api/courses/{$this->course->id}")
            ->assertOk()
            ->json('data.lessons.0.videos.0');

        $this->assertSame('YouTube lesson', $video['title']); // still a catalog preview
        $this->assertArrayNotHasKey('youtube_url', $video);
        $this->assertArrayNotHasKey('youtube_video_id', $video);
        $this->assertArrayNotHasKey('url', $video);

        // Signed-in student without a subscription.
        Sanctum::actingAs(User::factory()->create(), ['access-api']);
        $this->getJson("/api/courses/{$this->course->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.lessons.0.videos.0.youtube_url');

        // Subscriber gets the link.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->subscribedStudent(), ['access-api']);
        $this->getJson("/api/courses/{$this->course->id}")
            ->assertOk()
            ->assertJsonPath('data.lessons.0.videos.0.youtube_url', 'https://www.youtube.com/watch?v='.self::VIDEO_ID);
    }

    public function test_a_free_youtube_video_is_visible_without_a_subscription(): void
    {
        $this->youtubeVideo(isFree: true);

        $this->getJson("/api/courses/{$this->course->id}")
            ->assertOk()
            ->assertJsonPath('data.has_access', false)
            ->assertJsonPath('data.lessons.0.videos.0.youtube_url', 'https://www.youtube.com/watch?v='.self::VIDEO_ID);
    }
}
