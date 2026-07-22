<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Choice;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ParentModel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubCategory;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
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

    public function test_admin_can_attach_an_image_or_pdf_to_an_exam(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        $courseId = $this->course()->id;

        $withImage = $this->postJson('/api/admin/exams', [
            'course_id' => $courseId,
            'title' => 'Written Exam',
            'type' => 'written',
            'attachment' => UploadedFile::fake()->image('question.jpg'),
        ]);
        $withImage->assertStatus(201);
        $this->assertNotNull($withImage->json('data.attachment'));

        $withPdf = $this->postJson('/api/admin/exams', [
            'course_id' => $courseId,
            'title' => 'Written Exam 2',
            'type' => 'written',
            'attachment' => UploadedFile::fake()->create('question.pdf', 100, 'application/pdf'),
        ]);
        $withPdf->assertStatus(201);
        $this->assertNotNull($withPdf->json('data.attachment'));
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
}
