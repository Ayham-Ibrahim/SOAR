<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Unit;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseVideoStatsTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'SubCategory', 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'Subject', 'order' => 1]);
        $this->teacher = Teacher::create(['name' => 'Teacher']);
        $this->unit = Unit::create(['subject_id' => $subject->id, 'title' => 'Unit', 'order' => 1]);
    }

    private function course(string $title): Course
    {
        return Course::create([
            'subject_id' => $this->unit->subject_id,
            'teacher_id' => $this->teacher->id,
            'title' => $title,
            'description' => 'desc',
            'price' => 50000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);
    }

    /**
     * @param  list<Course>  $courses  courses the lesson is attached to
     * @param  list<bool>  $freeFlags  one video per entry, true = free
     */
    private function lessonWithVideos(array $courses, array $freeFlags, bool $lessonIsFree = false): Lesson
    {
        $lesson = Lesson::create(['unit_id' => $this->unit->id, 'title' => 'Lesson', 'is_free' => $lessonIsFree]);
        $lesson->courses()->attach(collect($courses)->pluck('id'));

        foreach ($freeFlags as $i => $isFree) {
            Video::create([
                'lesson_id' => $lesson->id,
                'title' => "Video {$i}",
                'url' => "videos/{$lesson->id}-{$i}.mp4",
                'is_free' => $isFree,
            ]);
        }

        return $lesson;
    }

    private function courseDetails(Course $course): TestResponse
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        return $this->getJson("/api/courses/{$course->id}")->assertOk();
    }

    public function test_free_videos_are_counted_separately_and_also_inside_the_total(): void
    {
        $course = $this->course('Physics');
        $this->lessonWithVideos([$course], [true, false, false]);
        $this->lessonWithVideos([$course], [true, false]);

        $this->courseDetails($course)
            ->assertJsonPath('data.videos_count', 5)
            ->assertJsonPath('data.actual_free_videos_count', 2);
    }

    public function test_each_course_counts_only_its_own_videos(): void
    {
        $physics = $this->course('Physics');
        $chemistry = $this->course('Chemistry');

        $this->lessonWithVideos([$physics], [true, false]);
        $this->lessonWithVideos([$physics, $chemistry], [true]); // shared lesson counts in both
        $this->lessonWithVideos([$chemistry], [true, true, false]);

        $this->courseDetails($physics)
            ->assertJsonPath('data.videos_count', 3)
            ->assertJsonPath('data.actual_free_videos_count', 2);

        $this->courseDetails($chemistry)
            ->assertJsonPath('data.videos_count', 4)
            ->assertJsonPath('data.actual_free_videos_count', 3);
    }

    public function test_course_without_videos_reports_zero(): void
    {
        $this->courseDetails($this->course('Empty'))
            ->assertJsonPath('data.videos_count', 0)
            ->assertJsonPath('data.actual_free_videos_count', 0);
    }

    public function test_free_count_follows_the_video_flag_only(): void
    {
        $course = $this->course('Physics');
        $course->update(['free_videos_count' => 10]);

        // A free lesson doesn't unlock its videos — only video.is_free does — so they stay paid.
        $this->lessonWithVideos([$course], [false, false], lessonIsFree: true);
        $this->lessonWithVideos([$course], [true]);

        $this->courseDetails($course)
            ->assertJsonPath('data.videos_count', 3)
            ->assertJsonPath('data.actual_free_videos_count', 1)
            ->assertJsonPath('data.free_videos_count', 10); // admin-entered column, left untouched
    }
}
