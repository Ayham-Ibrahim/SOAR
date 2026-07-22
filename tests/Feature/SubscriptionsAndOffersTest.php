<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Offer;
use App\Models\Subject;
use App\Models\SubCategory;
use App\Models\Teacher;
use App\Models\User;
use App\Services\CourseAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionsAndOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function course(string $title = 'Course'): Course
    {
        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'SubCategory', 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'Subject '.$title, 'order' => 1]);
        $teacher = Teacher::create(['name' => 'Teacher']);

        return Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => $title,
            'description' => 'desc',
            'price' => 50000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_direct_subscription_grants_access_until_expiry(): void
    {
        $course = $this->course();
        $student = User::factory()->create();
        Sanctum::actingAs($student, ['access-api']);

        $store = $this->postJson('/api/subscription-requests', [
            'course_id' => $course->id,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
        $store->assertStatus(201);
        $requestId = $store->json('data.id');

        Sanctum::actingAs($this->admin(), ['dashboard']);
        $approve = $this->postJson("/api/admin/subscription-requests/{$requestId}/approve", [
            'access_ends_at' => now()->addDays(10)->toDateString(),
        ]);
        $approve->assertStatus(200);

        $access = app(CourseAccess::class);
        $this->assertTrue($access->hasAccess($student->fresh(), $course));

        Carbon::setTestNow(now()->addDays(11));
        $this->assertFalse($access->hasAccess($student->fresh(), $course));
    }

    /**
     * The exact worked example from the spec: offer window 1/7-10/7,
     * access_duration_days=365, approved on 5/7 -> every bundled course's
     * expires_at is 5/7 NEXT YEAR, never 10/7.
     */
    public function test_offer_access_duration_is_independent_of_offer_window(): void
    {
        $courseA = $this->course('Physics');
        $courseB = $this->course('Chemistry');

        $offer = Offer::create([
            'title' => 'Bundle',
            'price' => 100000,
            'offer_starts_at' => '2026-07-01 00:00:00',
            'offer_ends_at' => '2026-07-10 23:59:59',
            'access_duration_days' => 365,
        ]);
        $offer->courses()->attach([$courseA->id, $courseB->id]);

        Carbon::setTestNow('2026-07-05 12:00:00');

        $student = User::factory()->create();
        Sanctum::actingAs($student, ['access-api']);

        $store = $this->postJson('/api/offer-subscription-requests', [
            'offer_id' => $offer->id,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
        $store->assertStatus(201);
        $requestId = $store->json('data.id');

        Sanctum::actingAs($this->admin(), ['dashboard']);
        $approve = $this->postJson("/api/admin/subscription-requests/{$requestId}/approve", []);
        $approve->assertStatus(200);

        $expectedExpiry = Carbon::parse('2026-07-05 12:00:00')->addDays(365);

        foreach ([$courseA, $courseB] as $course) {
            $subscription = $student->subscriptions()->where('course_id', $course->id)->firstOrFail();
            $this->assertTrue($subscription->expires_at->equalTo($expectedExpiry));
            // The critical negative assertion: NOT anywhere near offer_ends_at.
            $this->assertFalse($subscription->expires_at->isSameDay(Carbon::parse('2026-07-10')));
        }
    }

    public function test_subscribing_to_an_offer_after_its_window_closes_is_rejected(): void
    {
        $course = $this->course();
        $offer = Offer::create([
            'title' => 'Closed Bundle',
            'price' => 50000,
            'offer_starts_at' => '2026-07-01 00:00:00',
            'offer_ends_at' => '2026-07-10 23:59:59',
            'access_duration_days' => 365,
        ]);
        $offer->courses()->attach($course->id);

        Carbon::setTestNow('2026-07-11 00:00:01');

        $student = User::factory()->create();
        Sanctum::actingAs($student, ['access-api']);

        $response = $this->postJson('/api/offer-subscription-requests', [
            'offer_id' => $offer->id,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertStatus(422);
    }

    public function test_access_works_day_before_expiry_and_fails_day_after(): void
    {
        $course = $this->course();
        $student = User::factory()->create();
        Sanctum::actingAs($student, ['access-api']);

        $store = $this->postJson('/api/subscription-requests', [
            'course_id' => $course->id,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
        $requestId = $store->json('data.id');

        Sanctum::actingAs($this->admin(), ['dashboard']);
        $this->postJson("/api/admin/subscription-requests/{$requestId}/approve", [
            'access_ends_at' => '2026-08-15',
        ])->assertStatus(200);

        $access = app(CourseAccess::class);

        Carbon::setTestNow('2026-08-14 23:00:00');
        $this->assertTrue($access->hasAccess($student->fresh(), $course));

        Carbon::setTestNow('2026-08-16 00:00:01');
        $this->assertFalse($access->hasAccess($student->fresh(), $course));
    }
}
