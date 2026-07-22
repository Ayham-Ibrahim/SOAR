<?php

namespace Tests\Feature;

use App\Models\ParentAccountRequest;
use App\Models\ParentModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentAccountRequestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_student_submits_request_password_hashed_and_never_in_response(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student, ['access-api']);

        $response = $this->postJson('/api/student/parent-requests', [
            'parent_name' => 'Parent Name',
            'parent_phone' => '+963911119999',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonMissing(['password']);
        $this->assertStringNotContainsString('secret123', $response->getContent());

        $stored = ParentAccountRequest::first();
        $this->assertNotNull($stored);
        $this->assertNotEquals('secret123', $stored->password);
        $this->assertTrue(Hash::check('secret123', $stored->password));
    }

    public function test_duplicate_pending_request_is_rejected(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student, ['access-api']);

        ParentAccountRequest::create([
            'requested_by_student_id' => $student->id,
            'parent_name' => 'First Attempt',
            'parent_phone' => '+963911118888',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/student/parent-requests', [
            'parent_name' => 'Second Attempt',
            'parent_phone' => '+963911117777',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_approve_creates_parent_and_links_all_students_including_requester(): void
    {
        $requester = User::factory()->create();
        $otherOne = User::factory()->create();
        $otherTwo = User::factory()->create();

        $request = ParentAccountRequest::create([
            'requested_by_student_id' => $requester->id,
            'parent_name' => 'New Parent',
            'parent_phone' => '+963911116666',
            'password' => Hash::make('originalPass1'),
        ]);

        Sanctum::actingAs($this->admin(), ['dashboard']);

        // Requester deliberately omitted — must be auto-included.
        $response = $this->postJson("/api/admin/parent-requests/{$request->id}/approve", [
            'student_ids' => [$otherOne->id, $otherTwo->id],
        ]);

        $response->assertStatus(200);

        $parent = ParentModel::where('phone', '+963911116666')->firstOrFail();
        $this->assertDatabaseCount('parent_student', 3);
        $this->assertTrue($parent->students()->where('users.id', $requester->id)->exists());
        $this->assertTrue($parent->students()->where('users.id', $otherOne->id)->exists());
        $this->assertTrue($parent->students()->where('users.id', $otherTwo->id)->exists());

        $this->assertDatabaseHas('parent_account_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'created_parent_id' => $parent->id,
        ]);

        // Approving an already-reviewed request is idempotent-guarded.
        $again = $this->postJson("/api/admin/parent-requests/{$request->id}/approve", [
            'student_ids' => [$otherOne->id],
        ]);
        $again->assertStatus(422);
    }

    public function test_parent_logs_in_with_the_original_plain_password_after_approval(): void
    {
        $requester = User::factory()->create();

        $request = ParentAccountRequest::create([
            'requested_by_student_id' => $requester->id,
            'parent_name' => 'Login Test Parent',
            'parent_phone' => '+963911115555',
            'password' => Hash::make('originalPass1'),
        ]);

        Sanctum::actingAs($this->admin(), ['dashboard']);
        $this->postJson("/api/admin/parent-requests/{$request->id}/approve", [
            'student_ids' => [$requester->id],
        ])->assertStatus(200);

        $login = $this->postJson('/api/auth/login', [
            'phone' => '+963911115555',
            'password' => 'originalPass1',
            'type' => 'parent',
        ]);

        $login->assertStatus(200);
        $this->assertNotEmpty($login->json('data.access_token'));
    }

    public function test_parent_forbidden_from_non_linked_student_data(): void
    {
        $linkedStudent = User::factory()->create();
        $otherStudent = User::factory()->create();

        $parent = ParentModel::create([
            'name' => 'Guard Test Parent',
            'phone' => '+963911114444',
            'password' => Hash::make('password'),
            'phone_verified_at' => now(),
        ]);
        $parent->students()->attach($linkedStudent->id);

        Sanctum::actingAs($parent, ['access-api']);

        $this->getJson("/api/parent/students/{$linkedStudent->id}/exam-attempts")->assertStatus(200);
        $this->getJson("/api/parent/students/{$otherStudent->id}/exam-attempts")->assertStatus(403);
    }
}
