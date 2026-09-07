<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_payment_method_and_user_can_view_active_options(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

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
            ->assertJsonPath('data.location', 'دمشق - المزة');

        $inactive = PaymentMethod::create([
            'option_name' => 'غير متاح',
            'person_name' => 'شخص آخر',
            'person_phone' => '0988888888',
            'location' => 'حلب',
            'is_active' => false,
        ]);

        Sanctum::actingAs(User::factory()->create(), ['access-api']);

        $this->getJson('/api/payment-methods')
            ->assertStatus(200)
            ->assertJsonFragment(['option_name' => 'تحويل بنكي'])
            ->assertJsonMissing(['option_name' => $inactive->option_name]);
    }
}
