<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Offer;
use App\Models\ParentModel;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Services\CourseAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionRevocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Subject $subject;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'SubCategory', 'order' => 1]);
        $this->subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'Mathematics', 'order' => 1]);
        $this->teacher = Teacher::create(['name' => 'Teacher']);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function course(string $title): Course
    {
        return Course::create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => $title,
            'description' => 'desc',
            'price' => 50000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);
    }

    private function offer(Course ...$courses): Offer
    {
        $offer = Offer::create([
            'title' => 'Bundle',
            'description' => 'desc',
            'price' => 90000,
            'offer_starts_at' => now()->subDay(),
            'offer_ends_at' => now()->addDays(30),
            'access_duration_days' => 60,
            'is_active' => true,
        ]);
        $offer->courses()->attach(collect($courses)->pluck('id'));

        return $offer;
    }

    private function grant(User $student, Course $course, ?Offer $offer = null, bool $expired = false): Subscription
    {
        return Subscription::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'source' => $offer ? 'offer' : 'direct',
            'offer_id' => $offer?->id,
            'starts_at' => now()->subDays(10),
            'expires_at' => $expired ? now()->subDay() : now()->addDays(20),
            'is_active' => ! $expired,
        ]);
    }

    private function asAdmin(): static
    {
        Sanctum::actingAs($this->admin, ['dashboard']);

        return $this;
    }

    private function hasAccess(User $student, Course $course): bool
    {
        return app(CourseAccess::class)->hasAccess($student->fresh(), $course);
    }

    public function test_revoking_a_course_ends_access_but_keeps_the_record_and_the_student(): void
    {
        $student = User::factory()->create();
        $course = $this->course('Algebra');
        $subscription = $this->grant($student, $course);

        $this->asAdmin()
            ->postJson("/api/admin/courses/{$course->id}/students/{$student->id}/revoke", ['reason' => 'مشاركة الحساب'])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'revoked');

        $this->assertFalse($this->hasAccess($student, $course));

        $subscription->refresh();
        $this->assertSame('revoked', $subscription->status);
        $this->assertSame($this->admin->id, $subscription->revoked_by);
        $this->assertSame('مشاركة الحساب', $subscription->revocation_reason);
        $this->assertFalse($subscription->is_active);
        $this->assertModelExists($student);

        // Student app: paid content is locked again.
        Sanctum::actingAs($student, ['access-api']);
        $this->getJson("/api/courses/{$course->id}")->assertOk()->assertJsonPath('data.has_access', false);

        // Dashboard: no longer listed or counted as a subscriber.
        $this->asAdmin()->getJson("/api/courses/{$course->id}/students")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/courses/{$course->id}")->assertJsonPath('data.active_subscribers_count', 0);
    }

    /**
     * Course details is a public route (guest browsing), so it must resolve
     * the student from the bearer token itself. Sanctum::actingAs would hide
     * a mistake there, hence a real token.
     */
    public function test_student_with_a_real_token_loses_paid_content_once_revoked(): void
    {
        $student = User::factory()->create();
        $course = $this->course('Algebra');
        $this->grant($student, $course);
        $token = $student->createToken('mobile-access', ['access-api'])->plainTextToken;

        $this->withToken($token)->getJson("/api/courses/{$course->id}")->assertJsonPath('data.has_access', true);

        $this->asAdmin()->postJson("/api/admin/courses/{$course->id}/students/{$student->id}/revoke")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/courses/{$course->id}")->assertJsonPath('data.has_access', false);
    }

    public function test_revoking_a_package_ends_access_to_all_its_courses(): void
    {
        $student = User::factory()->create();
        $algebra = $this->course('Algebra');
        $geometry = $this->course('Geometry');
        $offer = $this->offer($algebra, $geometry);
        $this->grant($student, $algebra, $offer);
        $this->grant($student, $geometry, $offer);

        $this->asAdmin()->getJson("/api/admin/offers/{$offer->id}/students")
            ->assertOk()
            ->assertJsonPath('data.0.id', $student->id);

        $this->postJson("/api/admin/offers/{$offer->id}/students/{$student->id}/revoke")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertFalse($this->hasAccess($student, $algebra));
        $this->assertFalse($this->hasAccess($student, $geometry));
        $this->getJson("/api/admin/offers/{$offer->id}/students")->assertJsonCount(0, 'data');
    }

    public function test_revoking_one_course_closes_every_grant_for_it_and_nothing_else(): void
    {
        $student = User::factory()->create();
        $algebra = $this->course('Algebra');
        $geometry = $this->course('Geometry');
        $offer = $this->offer($algebra, $geometry);
        $this->grant($student, $algebra);          // bought directly
        $this->grant($student, $algebra, $offer);  // and again through the package
        $this->grant($student, $geometry, $offer);

        $this->asAdmin()
            ->postJson("/api/admin/courses/{$algebra->id}/students/{$student->id}/revoke")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertFalse($this->hasAccess($student, $algebra));
        $this->assertTrue($this->hasAccess($student, $geometry));
    }

    public function test_admin_sees_every_subscription_of_a_student_with_its_status(): void
    {
        $student = User::factory()->create();
        $this->grant($student, $this->course('Algebra'));
        $this->grant($student, $this->course('Physics'), expired: true);
        $chemistry = $this->course('Chemistry');
        $this->grant($student, $chemistry);

        $this->asAdmin()->postJson("/api/admin/courses/{$chemistry->id}/students/{$student->id}/revoke")->assertOk();

        $rows = collect($this->getJson("/api/admin/students/{$student->id}/subscriptions")->assertOk()->json('data'));

        $this->assertEquals(
            ['Algebra' => 'active', 'Physics' => 'expired', 'Chemistry' => 'revoked'],
            $rows->mapWithKeys(fn ($row) => [$row['course']['title'] => $row['status']])->all()
        );
        $this->assertSame($this->admin->name, $rows->firstWhere('status', 'revoked')['revoker']['name']);
    }

    public function test_revoking_without_a_live_subscription_is_rejected(): void
    {
        $student = User::factory()->create();
        $course = $this->course('Algebra');
        $this->grant($student, $course, expired: true);

        $this->asAdmin()
            ->postJson("/api/admin/courses/{$course->id}/students/{$student->id}/revoke")
            ->assertNotFound();

        $this->assertNull(Subscription::first()->revoked_at);
    }

    public function test_only_admins_can_revoke(): void
    {
        $student = User::factory()->create();
        $course = $this->course('Algebra');
        $this->grant($student, $course);

        Sanctum::actingAs($student, ['access-api']);
        $this->postJson("/api/admin/courses/{$course->id}/students/{$student->id}/revoke")->assertForbidden();

        $this->assertTrue($this->hasAccess($student, $course));
    }

    public function test_parent_app_and_student_requests_reflect_the_revocation(): void
    {
        $student = User::factory()->create();
        $course = $this->course('Algebra');
        $request = SubscriptionRequest::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'receipt_image' => 'receipts/r.jpg',
            'status' => 'approved',
        ]);
        $this->grant($student, $course)->update(['subscription_request_id' => $request->id]);

        $parent = ParentModel::create([
            'name' => 'Parent',
            'phone' => '+963911113333',
            'password' => 'password',
            'phone_verified_at' => now(),
        ]);
        $parent->students()->attach($student->id);

        $this->asAdmin()->postJson("/api/admin/courses/{$course->id}/students/{$student->id}/revoke")->assertOk();

        Sanctum::actingAs($parent, ['access-api']);
        $this->getJson("/api/parent/students/{$student->id}/subscriptions")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/parent/students/{$student->id}/academic-details")
            ->assertOk()
            ->assertJsonPath('data.0.courses.0.is_subscription_active', false)
            ->assertJsonPath('data.0.courses.0.subscription_status', 'revoked');

        // The request stays approved (it was); its grant now reads revoked.
        Sanctum::actingAs($student, ['access-api']);
        $this->getJson("/api/subscription-requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.subscriptions.0.status', 'revoked');
    }
}
