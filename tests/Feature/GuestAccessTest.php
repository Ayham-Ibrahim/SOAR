<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_courses_but_cannot_submit_a_subscription_request(): void
    {
        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $subCategory = SubCategory::create([
            'category_id' => $category->id,
            'name' => 'SubCategory',
            'order' => 1,
        ]);
        $subject = Subject::create([
            'sub_category_id' => $subCategory->id,
            'name' => 'Subject',
            'order' => 1,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::create(['name' => 'Teacher'])->id,
            'title' => 'Public Course',
            'description' => 'desc',
            'price' => 50000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);

        $this->getJson('/api/courses')->assertOk();
        $this->getJson("/api/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.has_access', false);

        $this->postJson('/api/subscription-requests', ['course_id' => $course->id])
            ->assertUnauthorized();
    }
}