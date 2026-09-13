<?php

namespace Tests\Feature;

use App\Models\ParentModel;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentSingleSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each call starts from a clean slate — no cached guard user, no leftover
     * bearer header — the way a separate device (or wiped app) would.
     */
    private function asNewDevice(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders();
    }

    private function login(User|ParentModel $account, string $type = 'user'): TestResponse
    {
        return $this->asNewDevice()->postJson('/api/auth/login', [
            'phone' => $account->phone,
            'password' => 'password',
            'type' => $type,
        ]);
    }

    private function profile(string $token): TestResponse
    {
        return $this->asNewDevice()->withToken($token)->getJson('/api/auth/profile');
    }

    public function test_second_login_is_blocked_while_the_first_device_is_signed_in(): void
    {
        $student = User::factory()->create();

        $token = $this->login($student)->assertOk()->json('data.access_token');

        // Same credentials from another phone, or the same phone after wiping app data.
        $this->login($student)
            ->assertForbidden()
            ->assertJsonPath('errors.code', AuthService::SESSION_ACTIVE);

        $this->profile($token)->assertOk();
        $this->assertSame(2, $student->tokens()->count());
    }

    public function test_student_cannot_log_out_so_the_account_stays_bound(): void
    {
        $student = User::factory()->create();
        $token = $this->login($student)->json('data.access_token');

        $this->asNewDevice()->withToken($token)->postJson('/api/auth/logout')
            ->assertForbidden()
            ->assertJsonPath('errors.code', AuthService::LOGOUT_NOT_ALLOWED);

        $this->profile($token)->assertOk();
        $this->login($student)->assertForbidden();
    }

    public function test_confirm_login_cannot_be_used_to_bypass_the_lock(): void
    {
        $student = User::factory()->create();
        $this->login($student)->assertOk();

        $this->asNewDevice()->postJson('/api/auth/confirm-login', [
            'phone' => $student->phone,
            'otp_code' => '1234',
            'type' => 'user',
        ])->assertForbidden()->assertJsonPath('errors.code', AuthService::SESSION_ACTIVE);

        $this->assertSame(2, $student->tokens()->count());
    }

    public function test_admin_reset_ends_the_old_session_and_allows_a_new_login(): void
    {
        $student = User::factory()->create();
        $oldToken = $this->login($student)->json('data.access_token');
        $student->registerDevice('old-phone-fcm-token');

        $this->asNewDevice();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        $this->getJson("/api/admin/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.has_active_session', true);

        $this->postJson("/api/admin/students/{$student->id}/reset-session")->assertOk();

        $this->profile($oldToken)->assertUnauthorized();
        $this->assertSame(0, $student->devices()->count());

        $newToken = $this->login($student)->assertOk()->json('data.access_token');
        $this->profile($newToken)->assertOk();
    }

    public function test_expired_session_does_not_block_login(): void
    {
        $student = User::factory()->create();
        $student->createToken('mobile-access', ['access-api'], now()->subDay());

        $this->login($student)->assertOk();
    }

    public function test_several_students_are_each_locked_independently(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->login($first)->assertOk();
        $this->login($second)->assertOk();

        $this->login($first)->assertForbidden();
        $this->login($second)->assertForbidden();
    }

    public function test_parents_are_not_bound_to_a_single_session(): void
    {
        $parent = ParentModel::create([
            'name' => 'Parent',
            'phone' => '+963900000001',
            'password' => 'password',
            'phone_verified_at' => now(),
        ]);

        $this->login($parent, 'parent')->assertOk();
        $token = $this->login($parent, 'parent')->assertOk()->json('data.access_token');

        $this->asNewDevice()->withToken($token)->postJson('/api/auth/logout')->assertOk();
    }
}
