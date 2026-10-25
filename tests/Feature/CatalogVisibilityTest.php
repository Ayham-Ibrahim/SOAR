<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Offer;
use App\Models\Subject;
use App\Models\SubCategory;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.firebase.project_id' => null]);
        $this->teacher = Teacher::create(['name' => 'Mr. Teacher']);
    }

    /**
     * One full branch: stage → grade → subject → course → exam.
     *
     * @return array{category: Category, subCategory: SubCategory, subject: Subject, course: Course, exam: Exam}
     */
    private function branch(string $name): array
    {
        $category = Category::create(['name' => "Stage {$name}", 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => "Grade {$name}", 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => "Subject {$name}", 'order' => 1]);
        $course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => "Course {$name}",
            'price' => 1000,
        ]);
        $exam = Exam::create(['course_id' => $course->id, 'title' => "Exam {$name}", 'type' => 'mcq']);

        return compact('category', 'subCategory', 'subject', 'course', 'exam');
    }

    private function offer(array $courseIds): Offer
    {
        $offer = Offer::create([
            'title' => 'Winter offer',
            'price' => 5000,
            'offer_starts_at' => now()->subDay(),
            'offer_ends_at' => now()->addMonth(),
            'access_duration_days' => 30,
        ]);
        $offer->courses()->attach($courseIds);

        return $offer;
    }

    private function asAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);
    }

    private function ids(string $uri): array
    {
        return collect($this->getJson($uri)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_disabling_a_stage_hides_its_whole_branch_from_the_app(): void
    {
        $live = $this->branch('live');
        $off = $this->branch('off');
        $off['category']->update(['is_active' => false]);

        $this->assertSame([$live['category']->id], $this->ids('/api/categories'));
        $this->assertSame([$live['subCategory']->id], $this->ids('/api/sub-categories'));
        $this->assertSame([$live['subject']->id], $this->ids('/api/subjects'));
        $this->assertSame([$live['course']->id], $this->ids('/api/courses'));
        $this->assertSame([$live['exam']->id], $this->ids('/api/exams'));

        $this->getJson("/api/courses/{$off['course']->id}")->assertNotFound();
        $this->getJson("/api/subjects/{$off['subject']->id}")->assertNotFound();
        $this->getJson("/api/categories/{$off['category']->id}")->assertNotFound();
    }

    public function test_disabling_a_grade_or_subject_hides_what_is_under_it(): void
    {
        $grade = $this->branch('grade');
        $subject = $this->branch('subject');
        $grade['subCategory']->update(['is_active' => false]);
        $subject['subject']->update(['is_active' => false]);

        $this->assertSame([$grade['category']->id, $subject['category']->id], $this->ids('/api/categories'));
        $this->assertSame([$subject['subCategory']->id], $this->ids('/api/sub-categories'));
        $this->assertSame([], $this->ids('/api/subjects'));
        $this->assertSame([], $this->ids('/api/courses'));
        $this->assertSame([], $this->ids('/api/exams'));
    }

    public function test_dashboard_sees_everything_unless_it_asks_for_active_only(): void
    {
        $live = $this->branch('live');
        $off = $this->branch('off');
        $off['category']->update(['is_active' => false]);
        $this->asAdmin();

        $this->assertCount(2, $this->ids('/api/categories'));
        $this->assertCount(2, $this->ids('/api/courses'));
        $this->assertCount(2, $this->ids('/api/admin/exams'));
        $this->getJson("/api/courses/{$off['course']->id}")->assertOk();

        $this->assertSame([$live['category']->id], $this->ids('/api/categories?active_only=1'));
        $this->assertSame([$live['subCategory']->id], $this->ids('/api/sub-categories?active_only=1'));
        $this->assertSame([$live['subject']->id], $this->ids('/api/subjects?active_only=1'));
        $this->assertSame([$live['course']->id], $this->ids('/api/courses?active_only=1'));
        $this->assertSame([$live['exam']->id], $this->ids('/api/admin/exams?active_only=1'));

        $this->getJson('/api/admin/teachers?active_only=1')->assertOk()->assertJsonPath('data.0.courses_count', 1);
        $this->getJson('/api/admin/teachers')->assertOk()->assertJsonPath('data.0.courses_count', 2);
    }

    public function test_offers_list_counts_and_shows_courses_with_teacher_and_subject(): void
    {
        $live = $this->branch('live');
        $offer = $this->offer([$live['course']->id]);

        $this->getJson('/api/offers')
            ->assertOk()
            ->assertJsonPath('data.0.courses_count', 1)
            ->assertJsonPath('data.0.courses.0.teacher.name', 'Mr. Teacher')
            ->assertJsonPath('data.0.courses.0.subject.name', 'Subject live');

        $this->getJson("/api/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.courses_count', 1)
            ->assertJsonPath('data.courses.0.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.courses.0.teacher.name', 'Mr. Teacher')
            ->assertJsonPath('data.courses.0.subject.name', 'Subject live');

        $this->asAdmin();
        $this->getJson('/api/admin/offers')->assertOk()->assertJsonPath('data.0.courses_count', 1);
        $this->getJson("/api/admin/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.courses.0.teacher.name', 'Mr. Teacher');
    }

    public function test_offers_leave_out_courses_from_a_disabled_stage(): void
    {
        $live = $this->branch('live');
        $off = $this->branch('off');
        $mixed = $this->offer([$live['course']->id, $off['course']->id]);
        $dead = $this->offer([$off['course']->id]);
        $off['category']->update(['is_active' => false]);

        $this->assertSame([$mixed->id], $this->ids('/api/offers'));
        $this->getJson('/api/offers')->assertJsonPath('data.0.courses_count', 1)->assertJsonCount(1, 'data.0.courses');
        $this->getJson("/api/offers/{$mixed->id}")->assertJsonCount(1, 'data.courses');

        // Dashboard management keeps the whole offer; its dropdown doesn't.
        $this->asAdmin();
        $this->assertSame([$mixed->id, $dead->id], $this->ids('/api/admin/offers'));
        $this->assertSame([$mixed->id], $this->ids('/api/admin/offers?active_only=1'));
        $this->getJson("/api/admin/offers/{$mixed->id}")->assertJsonCount(2, 'data.courses');
    }

    public function test_a_course_outside_the_catalog_cannot_be_bought(): void
    {
        $off = $this->branch('off');
        $offer = $this->offer([$off['course']->id]);
        $off['subject']->update(['is_active' => false]);
        Sanctum::actingAs(User::factory()->create(), ['access-api']);

        $this->postJson('/api/subscription-requests', [
            'course_id' => $off['course']->id,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('course_id');

        $this->postJson('/api/offer-subscription-requests', [
            'offer_id' => $offer->id,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('offer_id');
    }

    public function test_a_subscribed_student_still_opens_a_course_whose_stage_was_disabled(): void
    {
        $off = $this->branch('off');
        $student = User::factory()->create();
        Subscription::create([
            'student_id' => $student->id,
            'course_id' => $off['course']->id,
            'source' => 'direct',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
        $off['category']->update(['is_active' => false]);

        Sanctum::actingAs($student, ['access-api']);

        $this->getJson("/api/courses/{$off['course']->id}")->assertOk()->assertJsonPath('data.has_access', true);
    }
}
