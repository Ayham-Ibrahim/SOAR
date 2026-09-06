<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Choice;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Offer;
use App\Models\ParentModel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubCategory;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ExamScoringAndParentResultsTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'SubCategory', 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'Subject', 'order' => 1]);
        $teacher = Teacher::create(['name' => 'Teacher']);

        return Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Course',
            'description' => 'desc',
            'price' => 0,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);
    }

    public function test_mcq_score_is_weighted_by_question_points(): void
    {
        $exam = Exam::create(['course_id' => $this->course()->id, 'title' => 'MCQ Exam', 'type' => 'mcq']);
        $this->assertSame(100, $exam->total_score);

        $q1 = Question::create(['exam_id' => $exam->id, 'text' => 'Q1 worth 1 point', 'points' => 1]);
        $q1Correct = Choice::create(['question_id' => $q1->id, 'text' => 'right', 'is_correct' => true]);
        Choice::create(['question_id' => $q1->id, 'text' => 'wrong', 'is_correct' => false]);

        $q2 = Question::create(['exam_id' => $exam->id, 'text' => 'Q2 worth 3 points', 'points' => 3]);
        Choice::create(['question_id' => $q2->id, 'text' => 'right', 'is_correct' => true]);
        $q2Wrong = Choice::create(['question_id' => $q2->id, 'text' => 'wrong', 'is_correct' => false]);

        $student = User::factory()->create();
        Sanctum::actingAs($student, ['access-api']);

        // Answers correct on the 1-point question, wrong on the 3-point one:
        // a flat correct/total ratio would give 50%, weighted gives 1/4 = 25%.
        $response = $this->postJson('/api/exam-attempts', [
            'exam_id' => $exam->id,
            'answers' => [
                ['question_id' => $q1->id, 'choice_id' => $q1Correct->id],
                ['question_id' => $q2->id, 'choice_id' => $q2Wrong->id],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.total_points', 4);
        $response->assertJsonPath('data.earned_points', 1);
        $response->assertJsonPath('data.score', '25.00');
    }

    public function test_mcq_score_uses_the_configured_exam_total_score(): void
    {
        $exam = Exam::create([
            'course_id' => $this->course()->id,
            'title' => 'High Score Exam',
            'type' => 'mcq',
            'total_score' => 200,
        ]);

        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Q1', 'points' => 2]);
        $correctChoice = Choice::create(['question_id' => $question->id, 'text' => 'right', 'is_correct' => true]);

        Sanctum::actingAs(User::factory()->create(), ['access-api']);

        $response = $this->postJson('/api/exam-attempts', [
            'exam_id' => $exam->id,
            'answers' => [['question_id' => $question->id, 'choice_id' => $correctChoice->id]],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.score', '200.00')
            ->assertJsonPath('data.exam.total_score', 200);
    }

    public function test_student_submits_automated_exam_with_time_spent(): void
    {
        $exam = Exam::create([
            'course_id' => $this->course()->id,
            'title' => 'Timed Exam',
            'type' => 'mcq',
            'duration_minutes' => 30,
        ]);
        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Timed question', 'points' => 1]);
        $choice = Choice::create(['question_id' => $question->id, 'text' => 'right', 'is_correct' => true]);
        $student = User::factory()->create();
        Sanctum::actingAs($student, ['access-api']);

        $response = $this->postJson('/api/exam-attempts', [
            'exam_id' => $exam->id,
            'time_spent_seconds' => 420,
            'answers' => [['question_id' => $question->id, 'choice_id' => $choice->id]],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.time_spent_seconds', 420)
            ->assertJsonPath('data.exam.id', $exam->id);
    }

    public function test_exam_result_notification_is_sent_only_for_the_first_attempt(): void
    {
        $exam = Exam::create([
            'course_id' => $this->course()->id,
            'title' => 'Notification Exam',
            'type' => 'mcq',
        ]);
        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Question', 'points' => 1]);
        $choice = Choice::create(['question_id' => $question->id, 'text' => 'right', 'is_correct' => true]);
        $student = User::factory()->create();

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('notifyExamResult')->once();
        $this->app->instance(NotificationService::class, $notificationService);
        Sanctum::actingAs($student, ['access-api']);

        $payload = [
            'exam_id' => $exam->id,
            'time_spent_seconds' => 30,
            'answers' => [['question_id' => $question->id, 'choice_id' => $choice->id]],
        ];

        $this->postJson('/api/exam-attempts', $payload)->assertStatus(201);
        $this->postJson('/api/exam-attempts', $payload)->assertStatus(201);
    }

    public function test_admin_can_attach_an_image_or_pdf_to_an_exam(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        $courseId = $this->course()->id;

        $withImage = $this->postJson('/api/admin/exams', [
            'course_id' => $courseId,
            'title' => 'Written Exam',
            'type' => 'written',
            'total_score' => 150,
            'attachment' => UploadedFile::fake()->image('question.jpg'),
        ]);
        $withImage->assertStatus(201);
        $this->assertNotNull($withImage->json('data.attachment'));
        $withImage->assertJsonPath('data.total_score', 150);

        $withPdf = $this->postJson('/api/admin/exams', [
            'course_id' => $courseId,
            'title' => 'Written Exam 2',
            'type' => 'written',
            'attachment' => UploadedFile::fake()->create('question.pdf', 100, 'application/pdf'),
        ]);
        $withPdf->assertStatus(201);
        $this->assertNotNull($withPdf->json('data.attachment'));
    }

    public function test_written_exam_accepts_up_to_ten_solution_images_in_one_submission(): void
    {
        $exam = Exam::create([
            'course_id' => $this->course()->id,
            'title' => 'Written Images Exam',
            'type' => 'written',
        ]);
        $student = User::factory()->create();
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('notifyExamResult')->once();
        $this->app->instance(NotificationService::class, $notificationService);
        Sanctum::actingAs($student, ['access-api']);

        $images = array_map(
            fn (int $index) => UploadedFile::fake()->image("solution-{$index}.jpg"),
            range(1, 3)
        );

        $response = $this->post('/api/exam-attempts', [
            'exam_id' => $exam->id,
            'submission_files' => $images,
        ]);

        $response->assertStatus(201);
        $attempt = ExamAttempt::findOrFail($response->json('data.id'));
        $this->assertCount(3, $attempt->submission_files);

        $this->postJson('/api/exam-attempts', [
            'exam_id' => $exam->id,
            'submission_files' => array_fill(0, 11, 'image'),
        ])->assertStatus(422);
    }

    public function test_admin_attempt_details_include_student_exam_and_grading_information(): void
    {
        $exam = Exam::create([
            'course_id' => $this->course()->id,
            'title' => 'Detailed Written Exam',
            'type' => 'written',
        ]);
        $student = User::factory()->create(['name' => 'Detailed Student']);
        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'score' => 85,
            'submission_files' => ['/storage/exam-submissions/solution-1.jpg'],
            'feedback' => 'إجابة جيدة مع بعض الملاحظات.',
            'graded_at' => now(),
        ]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        $response = $this->getJson("/api/admin/exam-attempts/{$attempt->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.user.name', 'Detailed Student')
            ->assertJsonPath('data.exam.title', 'Detailed Written Exam')
            ->assertJsonPath('data.exam.course.subject.name', 'Subject')
            ->assertJsonPath('data.exam.course.subject.sub_category.name', 'SubCategory')
            ->assertJsonPath('data.score', '85.00')
            ->assertJsonPath('data.feedback', 'إجابة جيدة مع بعض الملاحظات.')
            ->assertJsonPath('data.submission_files.0', '/storage/exam-submissions/solution-1.jpg');
    }

    public function test_admin_can_view_exam_classification_and_unique_participants(): void
    {
        $course = $this->course();
        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => 'Classified Exam',
            'type' => 'mcq',
        ]);
        $firstStudent = User::factory()->create(['name' => 'First Student']);
        $secondStudent = User::factory()->create(['name' => 'Second Student']);

        ExamAttempt::create(['exam_id' => $exam->id, 'user_id' => $firstStudent->id, 'score' => 70, 'status' => 'graded']);
        ExamAttempt::create(['exam_id' => $exam->id, 'user_id' => $firstStudent->id, 'score' => 80, 'status' => 'graded']);
        ExamAttempt::create(['exam_id' => $exam->id, 'user_id' => $secondStudent->id, 'score' => 60, 'status' => 'graded']);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        $list = $this->getJson('/api/admin/exams');
        $list->assertStatus(200)
            ->assertJsonPath('data.0.course.subject.name', 'Subject')
            ->assertJsonPath('data.0.course.subject.sub_category.name', 'SubCategory')
            ->assertJsonPath('data.0.participants_count', 2);

        $participants = $this->getJson("/api/admin/exams/{$exam->id}/participants");
        $participants->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['score' => '80.00'])
            ->assertJsonFragment(['score' => '60.00']);
    }

    public function test_parent_can_view_a_linked_students_exam_attempts(): void
    {
        $exam = Exam::create(['course_id' => $this->course()->id, 'title' => 'MCQ Exam', 'type' => 'mcq']);
        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Q1', 'points' => 1]);
        $choice = Choice::create(['question_id' => $question->id, 'text' => 'right', 'is_correct' => true]);

        $student = User::factory()->create();
        Sanctum::actingAs($student, ['access-api']);
        $this->postJson('/api/exam-attempts', [
            'exam_id' => $exam->id,
            'answers' => [['question_id' => $question->id, 'choice_id' => $choice->id]],
        ])->assertStatus(201);

        $parent = ParentModel::create([
            'name' => 'Parent',
            'phone' => '+963911113333',
            'password' => Hash::make('password'),
            'phone_verified_at' => now(),
        ]);
        $parent->students()->attach($student->id);
        Sanctum::actingAs($parent, ['access-api']);

        $list = $this->getJson("/api/parent/students/{$student->id}/exam-attempts");
        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        $attemptId = $list->json('data.0.id');
        $detail = $this->getJson("/api/parent/students/{$student->id}/exam-attempts/{$attemptId}");
        $detail->assertStatus(200);
        $detail->assertJsonPath('data.score', '100.00');
    }

    public function test_parent_can_view_linked_students_subscriptions_and_offers(): void
    {
        $teacher = Teacher::create(['name' => 'Teacher A']);
        $course = Course::create([
            'subject_id' => Subject::create(['sub_category_id' => SubCategory::create([
                'category_id' => Category::create(['name' => 'Category', 'order' => 1])->id,
                'name' => 'SubCategory',
                'order' => 1,
            ])->id, 'name' => 'Subject', 'order' => 1])->id,
            'teacher_id' => $teacher->id,
            'title' => 'Math Course',
            'description' => 'desc',
            'price' => 120000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);

        $offer = Offer::create([
            'title' => 'Bundle Offer',
            'description' => 'desc',
            'price' => 300000,
            'offer_starts_at' => now()->subDay(),
            'offer_ends_at' => now()->addDays(30),
            'access_duration_days' => 60,
            'is_active' => true,
        ]);
        $offer->courses()->attach($course->id);

        $student = User::factory()->create();

        Subscription::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'source' => 'direct',
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
            'is_active' => true,
        ]);

        Subscription::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'source' => 'offer',
            'offer_id' => $offer->id,
            'starts_at' => now()->subDays(5),
            'expires_at' => now()->addDays(55),
            'is_active' => true,
        ]);

        $parent = ParentModel::create([
            'name' => 'Parent',
            'phone' => '+963911113334',
            'password' => Hash::make('password'),
            'phone_verified_at' => now(),
        ]);
        $parent->students()->attach($student->id);
        Sanctum::actingAs($parent, ['access-api']);

        $subscriptions = $this->getJson("/api/parent/students/{$student->id}/subscriptions");
        $subscriptions->assertStatus(200);
        $this->assertCount(2, $subscriptions->json('data'));
        $this->assertSame('direct', $subscriptions->json('data.0.source'));
        $this->assertSame('Teacher A', $subscriptions->json('data.0.teacher_name'));
        $this->assertSame(120000, (int) $subscriptions->json('data.0.price'));

        $offers = $this->getJson("/api/parent/students/{$student->id}/offers");
        $offers->assertStatus(200);
        $this->assertCount(1, $offers->json('data'));
        $this->assertSame($offer->id, $offers->json('data.0.id'));
        $this->assertSame('Bundle Offer', $offers->json('data.0.title'));
    }
}
