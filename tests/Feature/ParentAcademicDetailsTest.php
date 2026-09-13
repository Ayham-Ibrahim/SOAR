<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ParentModel;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentAcademicDetailsTest extends TestCase
{
    use RefreshDatabase;

    private SubCategory $subCategory;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $this->subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'Grade 12', 'order' => 1]);
        $this->teacher = Teacher::create(['name' => 'Teacher']);
    }

    private function subject(string $name, int $order): Subject
    {
        return Subject::create(['sub_category_id' => $this->subCategory->id, 'name' => $name, 'order' => $order]);
    }

    private function course(Subject $subject, string $title): Course
    {
        return Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => $title,
            'description' => 'desc',
            'price' => 50000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);
    }

    private function subscribe(User $student, Course $course, bool $active = true): void
    {
        Subscription::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'source' => 'direct',
            'starts_at' => now()->subDays(40),
            'expires_at' => $active ? now()->addDays(20) : now()->subDays(10),
            'is_active' => $active,
        ]);
    }

    private function exam(Course $course, string $title, bool $active = true): Exam
    {
        return Exam::create([
            'course_id' => $course->id,
            'title' => $title,
            'type' => 'mcq',
            'total_score' => 100,
            'passing_score' => 50,
            'is_active' => $active,
        ]);
    }

    private function attempt(User $student, Exam $exam, float $score): ExamAttempt
    {
        return ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'score' => $score,
            'time_spent_seconds' => 600,
            'graded_at' => now(),
        ]);
    }

    private function parentOf(User ...$students): ParentModel
    {
        $parent = ParentModel::create([
            'name' => 'Parent',
            'phone' => '+963911113333',
            'password' => 'password',
            'phone_verified_at' => now(),
        ]);
        $parent->students()->attach(collect($students)->pluck('id'));

        return $parent;
    }

    private function academicDetails(ParentModel $parent, User $student): TestResponse
    {
        Sanctum::actingAs($parent, ['access-api']);

        return $this->getJson("/api/parent/students/{$student->id}/academic-details");
    }

    public function test_record_is_grouped_by_subject_with_only_the_exams_that_concern_the_student(): void
    {
        $student = User::factory()->create();

        // Live subscription: exams taken, exams still open to take; an inactive
        // exam never taken is hidden, one taken before it was deactivated stays.
        $algebra = $this->course($this->subject('Mathematics', 1), 'Algebra');
        $this->subscribe($student, $algebra);
        $this->attempt($student, $this->exam($algebra, 'Algebra Midterm'), 80);
        $this->exam($algebra, 'Algebra Final');
        $this->exam($algebra, 'Algebra Draft', active: false);
        $this->attempt($student, $this->exam($algebra, 'Algebra Retired', active: false), 70);

        // Expired subscription: the course and its result stay, exams no longer open to them don't.
        $mechanics = $this->course($this->subject('Physics', 2), 'Mechanics');
        $this->subscribe($student, $mechanics, active: false);
        $this->attempt($student, $this->exam($mechanics, 'Mechanics Quiz'), 40);
        $this->exam($mechanics, 'Mechanics Final');

        // Never subscribed, but sat one exam.
        $organic = $this->course($this->subject('Chemistry', 3), 'Organic');
        $this->attempt($student, $this->exam($organic, 'Organic Quiz'), 65);
        $this->exam($organic, 'Organic Final');

        // Nothing to do with the student at all.
        $this->exam($this->course($this->subject('Biology', 4), 'Genetics'), 'Genetics Quiz');

        $data = $this->academicDetails($this->parentOf($student), $student)->assertOk()->json('data');

        $this->assertSame(['Mathematics', 'Physics', 'Chemistry'], array_column(array_column($data, 'subject'), 'name'));

        $algebraData = $data[0]['courses'][0];
        $this->assertSame('Algebra', $algebraData['title']);
        $this->assertSame('Teacher', $algebraData['teacher_name']);
        $this->assertTrue($algebraData['is_subscription_active']);
        $this->assertSame(
            ['Algebra Midterm', 'Algebra Final', 'Algebra Retired'],
            array_column($algebraData['exams'], 'title')
        );

        $result = $algebraData['exams'][0]['attempts'][0];
        $this->assertSame('80.00', $result['score']);
        $this->assertTrue($result['passed']);
        $this->assertSame(600, $result['time_spent_seconds']);
        $this->assertNotNull($result['submitted_at']);
        $this->assertSame([], $algebraData['exams'][1]['attempts']); // open, not taken yet

        $mechanicsData = $data[1]['courses'][0];
        $this->assertFalse($mechanicsData['is_subscription_active']);
        $this->assertSame(['Mechanics Quiz'], array_column($mechanicsData['exams'], 'title'));
        $this->assertFalse($mechanicsData['exams'][0]['attempts'][0]['passed']);

        $this->assertSame(['Organic Quiz'], array_column($data[2]['courses'][0]['exams'], 'title'));
    }

    public function test_each_child_sees_only_their_own_courses_and_results(): void
    {
        $math = $this->subject('Mathematics', 1);
        $student = User::factory()->create();
        $sibling = User::factory()->create();
        $stranger = User::factory()->create();

        $algebra = $this->course($math, 'Algebra');
        $geometry = $this->course($math, 'Geometry');
        $this->subscribe($student, $algebra);
        $this->subscribe($sibling, $geometry);
        $this->subscribe($stranger, $geometry);

        $midterm = $this->exam($algebra, 'Algebra Midterm');
        $this->attempt($student, $midterm, 90);
        $this->attempt($sibling, $midterm, 30);
        $this->attempt($stranger, $midterm, 10);

        $parent = $this->parentOf($student, $sibling);

        $mine = $this->academicDetails($parent, $student)->assertOk()->json('data');
        $this->assertSame(['Algebra'], array_column($mine[0]['courses'], 'title'));
        $this->assertSame(['90.00'], array_column($mine[0]['courses'][0]['exams'][0]['attempts'], 'score'));

        $theirs = $this->academicDetails($parent, $sibling)->assertOk()->json('data');
        $this->assertSame(['Algebra', 'Geometry'], array_column($theirs[0]['courses'], 'title'));
        $this->assertSame(['30.00'], array_column($theirs[0]['courses'][0]['exams'][0]['attempts'], 'score'));
    }

    public function test_parent_cannot_read_a_student_who_is_not_their_child(): void
    {
        $child = User::factory()->create();
        $stranger = User::factory()->create();
        $this->subscribe($stranger, $this->course($this->subject('Mathematics', 1), 'Algebra'));

        $this->academicDetails($this->parentOf($child), $stranger)->assertForbidden();
    }

    public function test_child_with_no_activity_has_an_empty_record(): void
    {
        $child = User::factory()->create();
        $this->course($this->subject('Mathematics', 1), 'Algebra');

        $this->academicDetails($this->parentOf($child), $child)->assertOk()->assertExactJson([
            'status' => 'success',
            'message' => 'تم جلب التفاصيل الأكاديمية للطالب بنجاح',
            'data' => [],
        ]);
    }
}
