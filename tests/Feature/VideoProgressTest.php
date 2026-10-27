<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\ParentModel;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\Unit;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoProgressTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'Grade 12', 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'Arabic', 'order' => 1]);
        $this->unit = Unit::create(['subject_id' => $subject->id, 'title' => 'Unit', 'order' => 1]);
        $this->course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::create(['name' => 'Teacher'])->id,
            'title' => 'Arabic Complete',
            'description' => 'desc',
            'price' => 50000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);
    }

    private function video(string $title, ?int $duration, int $lessonOrder = 1, bool $free = false): Video
    {
        $lesson = Lesson::create(['unit_id' => $this->unit->id, 'title' => "Lesson {$title}", 'order' => $lessonOrder]);
        $lesson->courses()->attach($this->course->id);

        return Video::create([
            'lesson_id' => $lesson->id,
            'title' => $title,
            'url' => "videos/{$title}.mp4",
            'duration_seconds' => $duration,
            'is_free' => $free,
        ]);
    }

    private function subscribedStudent(): User
    {
        $student = User::factory()->create();
        Subscription::create([
            'student_id' => $student->id,
            'course_id' => $this->course->id,
            'source' => 'direct',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        return $student;
    }

    private function report(User $student, Video $video, int $position, ?int $duration = null): TestResponse
    {
        Sanctum::actingAs($student, ['access-api']);

        return $this->postJson("/api/videos/{$video->id}/progress", array_filter([
            'position_seconds' => $position,
            'duration_seconds' => $duration,
        ], fn ($value) => $value !== null));
    }

    public function test_progress_keeps_the_furthest_point_reached(): void
    {
        $student = $this->subscribedStudent();
        $video = $this->video('Grammar', 600);

        $this->report($student, $video, 300)->assertOk()->assertJsonPath('data.watch_percentage', 50);

        // Seeking back to rewatch moves the resume point, not the progress.
        $this->report($student, $video, 60)->assertOk()
            ->assertJsonPath('data.watched_seconds', 300)
            ->assertJsonPath('data.last_position_seconds', 60)
            ->assertJsonPath('data.watch_percentage', 50);

        $this->report($student, $video, 700)->assertOk()->assertJsonPath('data.watch_percentage', 100);
    }

    public function test_player_duration_is_used_when_the_video_has_none(): void
    {
        $video = $this->video('YouTube', null);

        $this->report($this->subscribedStudent(), $video, 45, 180)->assertOk()->assertJsonPath('data.watch_percentage', 25);
    }

    public function test_only_students_who_can_watch_the_video_can_report_progress(): void
    {
        $paid = $this->video('Paid', 600);

        $this->report(User::factory()->create(), $paid, 30)->assertForbidden();
        $this->report(User::factory()->create(), $this->video('Free', 600, free: true), 30)->assertOk();

        $parent = ParentModel::create(['name' => 'Parent', 'phone' => '+963911113333', 'password' => 'password', 'phone_verified_at' => now()]);
        Sanctum::actingAs($parent, ['access-api']);
        $this->postJson("/api/videos/{$paid->id}/progress", ['position_seconds' => 30])->assertForbidden();

        $this->assertDatabaseCount('video_progress', 1);
    }

    public function test_parent_sees_each_course_video_with_the_childs_watch_percentage(): void
    {
        $student = $this->subscribedStudent();
        $grammar = $this->video('Grammar', 600, lessonOrder: 1);
        $syntax = $this->video('Syntax', 1200, lessonOrder: 2);
        $this->video('Spelling', 900, lessonOrder: 3);

        $this->report($student, $grammar, 540);
        $this->report($student, $syntax, 300);

        // Another student's progress never leaks in.
        $this->report($this->subscribedStudent(), $this->course->lessons()->first()->videos()->first(), 600);

        $parent = ParentModel::create(['name' => 'Parent', 'phone' => '+963911113333', 'password' => 'password', 'phone_verified_at' => now()]);
        $parent->students()->attach($student->id);
        Sanctum::actingAs($parent, ['access-api']);

        $course = $this->getJson("/api/parent/students/{$student->id}/academic-details")
            ->assertOk()
            ->json('data.0.courses.0');

        $this->assertSame(3, $course['videos_count']);
        $this->assertSame(2, $course['watched_videos_count']);
        $this->assertSame(['Grammar', 'Syntax', 'Spelling'], array_column($course['videos'], 'title'));
        $this->assertSame([90, 25, 0], array_column($course['videos'], 'watch_percentage'));
        $this->assertSame('Lesson Grammar', $course['videos'][0]['lesson_title']);
        $this->assertNull($course['videos'][2]['last_watched_at']);
    }
}
