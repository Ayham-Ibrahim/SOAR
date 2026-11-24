<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\ParentModel;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\CourseAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReviewAccountTest extends TestCase
{
    use RefreshDatabase;

    private const STUDENT_PHONE = '+963933335555';

    private const PARENT_PHONE = '+963933336666';

    private function asNewDevice(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders();
    }

    private function login(string $phone, string $type): TestResponse
    {
        return $this->asNewDevice()->postJson('/api/auth/login', [
            'phone' => $phone,
            'password' => 'Review@2026',
            'type' => $type,
        ]);
    }

    private function course(): Course
    {
        $category = Category::create(['name' => 'Stage', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'Grade', 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'Math', 'order' => 1]);

        return Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::create(['name' => 'Teacher'])->id,
            'title' => 'Math course',
            'price' => 1000,
        ]);
    }

    public function test_command_creates_linked_accounts_with_every_course_open(): void
    {
        $course = $this->course();

        $this->artisan('review:account')->assertSuccessful();

        $student = User::where('phone', self::STUDENT_PHONE)->firstOrFail();
        $parent = ParentModel::where('phone', self::PARENT_PHONE)->firstOrFail();

        $this->assertTrue($student->is_reviewer);
        $this->assertTrue($parent->is_reviewer);
        $this->assertTrue($parent->students()->whereKey($student->id)->exists());
        $this->assertTrue(app(CourseAccess::class)->hasAccess($student, $course));

        // Re-running refreshes instead of duplicating.
        $this->artisan('review:account')->assertSuccessful();
        $this->assertSame(1, $student->subscriptions()->count());
    }

    public function test_review_student_signs_in_from_several_devices_at_once(): void
    {
        $this->artisan('review:account')->assertSuccessful();

        $first = $this->login(self::STUDENT_PHONE, 'user')->assertOk()->json('data.access_token');
        $second = $this->login(self::STUDENT_PHONE, 'user')->assertOk()->json('data.access_token');

        $this->asNewDevice()->withToken($first)->getJson('/api/auth/profile')->assertOk();
        $this->asNewDevice()->withToken($second)->getJson('/api/auth/profile')->assertOk();

        $this->asNewDevice()->withToken($first)->postJson('/api/auth/logout')->assertOk();
        $this->asNewDevice()->withToken($second)->getJson('/api/auth/profile')->assertOk();
    }

    public function test_review_parent_keeps_every_device_signed_in(): void
    {
        $this->artisan('review:account')->assertSuccessful();

        $first = $this->login(self::PARENT_PHONE, 'parent')->assertOk()->json('data.access_token');
        $this->login(self::PARENT_PHONE, 'parent')->assertOk();

        $this->asNewDevice()->withToken($first)->getJson('/api/parent/children')
            ->assertOk()
            ->assertJsonFragment(['phone' => self::STUDENT_PHONE]);
    }
}
