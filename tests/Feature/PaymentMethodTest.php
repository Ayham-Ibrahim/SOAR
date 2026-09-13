<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Offer;
use App\Models\PaymentMethod;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\SubscriptionRequest;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);
    }

    private function actingAsStudent(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access-api']);
    }

    private function method(array $overrides = []): PaymentMethod
    {
        return PaymentMethod::create([
            'option_name' => 'حوالة شام كاش',
            'person_name' => 'أحمد محمد',
            'person_phone' => '0999999999',
            ...$overrides,
        ]);
    }

    private function course(): Course
    {
        $category = Category::create(['name' => 'Category', 'order' => 1]);
        $subCategory = SubCategory::create(['category_id' => $category->id, 'name' => 'SubCategory', 'order' => 1]);
        $subject = Subject::create(['sub_category_id' => $subCategory->id, 'name' => 'Subject', 'order' => 1]);

        return Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::create(['name' => 'Teacher'])->id,
            'title' => 'Course',
            'description' => 'desc',
            'price' => 50000,
            'subscription_days' => 30,
            'free_videos_count' => 0,
            'allow_download' => false,
        ]);
    }

    public function test_admin_can_create_payment_method_and_user_can_view_active_options(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/api/admin/payment-methods', [
            'option_name' => 'تحويل بنكي',
            'person_name' => 'أحمد محمد',
            'person_phone' => '0999999999',
            'qr_code' => UploadedFile::fake()->image('qr.png'),
            'location' => 'دمشق - المزة',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.option_name', 'تحويل بنكي')
            ->assertJsonPath('data.person_name', 'أحمد محمد')
            ->assertJsonPath('data.location', 'دمشق - المزة')
            ->assertJsonPath('data.is_active', true);

        $inactive = $this->method(['option_name' => 'غير متاح', 'is_active' => false]);

        $this->actingAsStudent();

        $this->getJson('/api/payment-methods')
            ->assertStatus(200)
            ->assertJsonFragment(['option_name' => 'تحويل بنكي'])
            ->assertJsonMissing(['option_name' => $inactive->option_name]);
    }

    public function test_admin_manages_several_methods_and_the_qr_code_is_optional(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/payment-methods', [
            'option_name' => 'حوالة شام كاش',
            'person_name' => 'أحمد محمد',
            'person_phone' => '0999999999',
        ])->assertCreated()->assertJsonPath('data.qr_code', null);

        $id = $this->postJson('/api/admin/payment-methods', [
            'option_name' => 'حوالة الهرم',
            'person_name' => 'سامر علي',
            'person_phone' => '0988888888',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/admin/payment-methods/{$id}", ['person_phone' => '0977777777', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.person_phone', '0977777777')
            ->assertJsonPath('data.is_active', false);

        // The dashboard lists inactive methods too.
        $this->getJson('/api/admin/payment-methods')->assertOk()->assertJsonCount(2, 'data');

        $this->deleteJson("/api/admin/payment-methods/{$id}")->assertOk();
        $this->assertDatabaseMissing('payment_methods', ['id' => $id]);
    }

    public function test_person_name_and_phone_are_required(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/payment-methods', ['option_name' => 'حوالة شام كاش'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['person_name', 'person_phone']);
    }

    public function test_student_request_keeps_the_chosen_method_and_the_admin_sees_it(): void
    {
        $shamCash = $this->method();
        $this->method(['option_name' => 'حوالة الهرم', 'person_name' => 'سامر علي', 'person_phone' => '0988888888']);
        $course = $this->course();

        $this->actingAsStudent();
        $requestId = $this->postJson('/api/subscription-requests', [
            'course_id' => $course->id,
            'payment_method_id' => $shamCash->id,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertCreated()->json('data.id');

        // The admin changes the account number afterwards: the request keeps
        // the one the student actually transferred to.
        $shamCash->update(['person_phone' => '0911111111']);

        $this->actingAsAdmin();

        $this->getJson("/api/admin/subscription-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.payment_method_id', $shamCash->id)
            ->assertJsonPath('data.payment_details.option_name', 'حوالة شام كاش')
            ->assertJsonPath('data.payment_details.person_phone', '0999999999')
            ->assertJsonPath('data.payment_method.person_phone', '0911111111');

        $this->getJson('/api/admin/subscription-requests')
            ->assertOk()
            ->assertJsonPath('data.0.payment_details.person_name', 'أحمد محمد');

        // Deleting the method doesn't erase where the money went.
        $this->deleteJson("/api/admin/payment-methods/{$shamCash->id}")->assertOk();
        $this->assertSame('0999999999', SubscriptionRequest::find($requestId)->payment_details['person_phone']);
    }

    public function test_offer_request_also_keeps_the_chosen_method(): void
    {
        $method = $this->method();
        $offer = Offer::create([
            'title' => 'Bundle',
            'description' => 'desc',
            'price' => 300000,
            'offer_starts_at' => now()->subDay(),
            'offer_ends_at' => now()->addDays(30),
            'access_duration_days' => 60,
            'is_active' => true,
        ]);
        $offer->courses()->attach($this->course()->id);

        $this->actingAsStudent();

        $this->postJson('/api/offer-subscription-requests', [
            'offer_id' => $offer->id,
            'payment_method_id' => $method->id,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method_id', $method->id)
            ->assertJsonPath('data.payment_details.person_name', 'أحمد محمد');
    }

    public function test_an_inactive_or_unknown_payment_method_is_rejected(): void
    {
        $inactive = $this->method(['is_active' => false]);
        $course = $this->course();
        $this->actingAsStudent();

        foreach ([$inactive->id, 999] as $paymentMethodId) {
            $this->postJson('/api/subscription-requests', [
                'course_id' => $course->id,
                'payment_method_id' => $paymentMethodId,
                'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            ])->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');
        }

        $this->assertSame(0, SubscriptionRequest::count());
    }

    /**
     * Transition period: app versions from before payment methods don't send
     * one, and must still be able to subscribe.
     */
    public function test_request_without_a_method_still_works_for_older_app_versions(): void
    {
        $this->method();
        $course = $this->course();
        $this->actingAsStudent();

        $this->postJson('/api/subscription-requests', [
            'course_id' => $course->id,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method_id', null)
            ->assertJsonPath('data.payment_details', null);
    }
}
