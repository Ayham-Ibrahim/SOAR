<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\News;
use App\Models\ParentModel;
use App\Models\Subject;
use App\Models\SubCategory;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppResultsAndBadgesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.firebase.project_id' => null]);
    }

    private function exam(string $type = 'mcq'): Exam
    {
        $category = Category::create(['name' => 'Stage', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'Grade', 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'علم الأحياء', 'order' => 1]);
        $course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::create(['name' => 'Teacher'])->id,
            'title' => 'Biology course',
            'price' => 1000,
        ]);

        return Exam::create(['course_id' => $course->id, 'title' => 'Exam', 'type' => $type]);
    }

    private function gradedAttempt(Exam $exam, User $student, string $gradedAt): ExamAttempt
    {
        return ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'score' => 80,
            'graded_at' => $gradedAt,
        ]);
    }

    private function parentOf(User $student): ParentModel
    {
        $parent = ParentModel::create([
            'name' => 'Parent',
            'phone' => '+963911113333',
            'password' => 'password',
            'phone_verified_at' => now(),
        ]);
        $parent->students()->attach($student->id);

        return $parent;
    }

    public function test_results_carry_course_title_and_subject_name_for_student_and_parent(): void
    {
        $exam = $this->exam();
        $student = User::factory()->create();
        $attempt = $this->gradedAttempt($exam, $student, now());

        Sanctum::actingAs($student, ['access-api']);
        $this->getJson('/api/exam-attempts')
            ->assertOk()
            ->assertJsonPath('data.0.exam.course.title', 'Biology course')
            ->assertJsonPath('data.0.exam.course.subject.name', 'علم الأحياء');
        $this->getJson("/api/exam-attempts/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('data.exam.course.subject.name', 'علم الأحياء');

        Sanctum::actingAs($this->parentOf($student), ['access-api']);
        $this->getJson("/api/parent/students/{$student->id}/exam-attempts")
            ->assertOk()
            ->assertJsonPath('data.0.exam.course.title', 'Biology course')
            ->assertJsonPath('data.0.exam.course.subject.name', 'علم الأحياء');
        $this->getJson("/api/parent/students/{$student->id}/exam-attempts/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('data.exam.course.title', 'Biology course');
    }

    public function test_news_and_results_are_new_until_seen(): void
    {
        Carbon::setTestNow('2026-10-01 10:00:00');
        $exam = $this->exam();
        $student = User::factory()->create();
        News::create(['title' => 'Before sign-up', 'body' => 'old', 'is_active' => true]);

        Carbon::setTestNow('2026-10-02 10:00:00');
        $student->forceFill(['created_at' => '2026-10-01 12:00:00'])->save();
        News::create(['title' => 'Fresh', 'body' => 'new', 'is_active' => true]);
        News::create(['title' => 'Hidden', 'body' => 'inactive', 'is_active' => false]);
        $this->gradedAttempt($exam, $student, '2026-10-02 09:00:00');

        Sanctum::actingAs($student, ['access-api']);

        $this->getJson('/api/news')->assertJsonPath('additional_data.new_count', 1)->assertJsonPath('additional_data.has_new', true);
        $this->getJson('/api/exam-attempts')->assertJsonPath('additional_data.new_count', 1);
        $this->getJson('/api/badges')
            ->assertOk()
            ->assertJsonPath('data.news.new_count', 1)
            ->assertJsonPath('data.results.new_count', 1);

        $this->postJson('/api/news/seen')->assertOk();
        $this->postJson('/api/exam-attempts/seen')->assertOk();

        $this->getJson('/api/badges')
            ->assertJsonPath('data.news.has_new', false)
            ->assertJsonPath('data.results.has_new', false);

        Carbon::setTestNow('2026-10-03 10:00:00');
        $this->gradedAttempt($exam, $student, now());
        $this->getJson('/api/exam-attempts')->assertJsonPath('additional_data.new_count', 1);
        Carbon::setTestNow();
    }

    public function test_parent_tracks_new_results_per_child(): void
    {
        Carbon::setTestNow('2026-10-01 10:00:00');
        $exam = $this->exam();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $parent = $this->parentOf($first);
        $parent->students()->attach($second->id);

        Carbon::setTestNow('2026-10-02 10:00:00');
        $this->gradedAttempt($exam, $first, now());
        $this->gradedAttempt($exam, $second, now());

        Sanctum::actingAs($parent, ['access-api']);

        $this->postJson("/api/parent/students/{$first->id}/exam-attempts/seen")->assertOk();

        $this->getJson("/api/parent/students/{$first->id}/exam-attempts")->assertJsonPath('additional_data.new_count', 0);
        $this->getJson("/api/parent/students/{$second->id}/exam-attempts")->assertJsonPath('additional_data.new_count', 1);

        $children = collect($this->getJson('/api/parent/children')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(0, $children[$first->id]['new_results_count']);
        $this->assertSame(1, $children[$second->id]['new_results_count']);

        $badges = collect($this->getJson('/api/badges')->assertOk()->json('data.children'))->keyBy('student_id');
        $this->assertFalse($badges[$first->id]['results']['has_new']);
        $this->assertTrue($badges[$second->id]['results']['has_new']);

        // The child's own marker is separate from the parent's.
        Sanctum::actingAs($first, ['access-api']);
        $this->getJson('/api/exam-attempts')->assertJsonPath('additional_data.new_count', 1);
        Carbon::setTestNow();
    }

    public function test_written_submission_notifies_admins_and_shows_in_the_pending_count(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $exam = $this->exam('written');
        $student = User::factory()->create();

        Sanctum::actingAs($student, ['access-api']);
        $this->post('/api/exam-attempts', [
            'exam_id' => $exam->id,
            'submission_files' => [UploadedFile::fake()->image('answer.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(1, UserNotification::query()
            ->where('notifiable_id', $admin->id)
            ->where('type', 'written_exam_submitted')
            ->count());

        Sanctum::actingAs($admin, ['dashboard']);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 1);
        $this->getJson('/api/admin/exam-attempts')->assertOk()->assertJsonPath('additional_data.pending_review_count', 1);
        $this->getJson('/api/admin/exams')->assertOk()->assertJsonPath('data.0.pending_review_count', 1);
    }

    public function test_parent_token_is_turned_away_from_subscribing(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($this->parentOf($student), ['access-api']);

        $this->postJson('/api/subscription-requests', ['course_id' => 1])
            ->assertForbidden()
            ->assertJsonPath('message', 'الاشتراك يتم من حساب الطالب');
        $this->postJson('/api/offer-subscription-requests', ['offer_id' => 1])
            ->assertForbidden()
            ->assertJsonPath('message', 'الاشتراك يتم من حساب الطالب');
        $this->getJson('/api/subscription-requests')->assertForbidden();
        $this->getJson('/api/exam-attempts')->assertForbidden();
    }
}
