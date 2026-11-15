<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\ParentModel;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    private function parent(): ParentModel
    {
        return ParentModel::create([
            'name' => 'Parent',
            'phone' => '+963911113333',
            'password' => 'password',
            'phone_verified_at' => now(),
        ]);
    }

    private function notify(User|ParentModel $account): void
    {
        UserNotification::create([
            'notifiable_type' => $account->getMorphClass(),
            'notifiable_id' => $account->getKey(),
            'title' => 'Title',
            'body' => 'Body',
        ]);
    }

    public function test_student_deletes_own_account_and_its_data(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $parent = $this->parent();
        $parent->students()->attach($student);

        $student->registerDevice('student-fcm');
        $other->registerDevice('other-fcm');
        $this->notify($student);
        $this->notify($other);
        $token = $student->createToken('access', ['access-api'])->plainTextToken;

        $this->withToken($token)->deleteJson('/api/auth/account')->assertOk();

        $this->assertModelMissing($student);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $student->id, 'tokenable_type' => $student->getMorphClass()]);
        $this->assertDatabaseMissing('devices', ['fcm_token' => 'student-fcm']);
        $this->assertDatabaseMissing('user_notifications', ['notifiable_type' => $student->getMorphClass(), 'notifiable_id' => $student->id]);
        $this->assertDatabaseMissing('parent_student', ['student_id' => $student->id]);

        // Nothing belonging to other accounts is touched.
        $this->assertModelExists($other);
        $this->assertModelExists($parent);
        $this->assertSame(1, Device::count());
        $this->assertSame(1, UserNotification::count());

        $this->app['auth']->forgetGuards();
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->withToken($token)->getJson('/api/auth/profile')->assertUnauthorized();
    }

    public function test_parent_deletes_own_account_and_its_data(): void
    {
        $parent = $this->parent();
        $student = User::factory()->create();
        $parent->students()->attach($student);
        $parent->registerDevice('parent-fcm');
        $this->notify($parent);

        Sanctum::actingAs($parent, ['access-api']);

        $this->deleteJson('/api/auth/account')->assertOk();

        $this->assertModelMissing($parent);
        $this->assertDatabaseMissing('devices', ['fcm_token' => 'parent-fcm']);
        $this->assertDatabaseMissing('user_notifications', ['notifiable_type' => $parent->getMorphClass(), 'notifiable_id' => $parent->id]);
        $this->assertDatabaseMissing('parent_student', ['parent_id' => $parent->id]);
        $this->assertModelExists($student);
    }

    public function test_admin_cannot_delete_own_account_from_the_app(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Sanctum::actingAs($admin, ['dashboard']);

        $this->deleteJson('/api/auth/account')->assertForbidden();

        $this->assertModelExists($admin);
    }

    public function test_guest_cannot_delete_an_account(): void
    {
        $this->deleteJson('/api/auth/account')->assertUnauthorized();
    }
}
