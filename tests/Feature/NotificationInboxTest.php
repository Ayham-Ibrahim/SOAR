<?php

namespace Tests\Feature;

use App\Models\ParentModel;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.firebase.project_id' => null]);
    }

    private function notify($recipient, string $title): void
    {
        $fcm = app(FcmService::class);

        $recipient instanceof ParentModel
            ? $fcm->sendToParent($recipient, $title, 'body', ['type' => 'broadcast'])
            : $fcm->sendToUser($recipient, $title, 'body', ['type' => 'broadcast']);
    }

    private function parent(): ParentModel
    {
        return ParentModel::create([
            'name' => 'Parent',
            'phone' => '+963911113333',
            'password' => 'password',
            'phone_verified_at' => now(),
        ]);
    }

    public function test_inbox_lists_newest_first_with_id_timestamps_and_unread_count(): void
    {
        $student = User::factory()->create();

        Carbon::setTestNow('2026-10-01 10:00:00');
        $this->notify($student, 'Older');
        Carbon::setTestNow('2026-10-02 10:00:00');
        $this->notify($student, 'Newer');
        $this->notify(User::factory()->create(), 'Someone else');
        Carbon::setTestNow();

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Newer')
            ->assertJsonPath('data.1.title', 'Older')
            ->assertJsonPath('data.0.read_at', null);

        $this->assertIsInt($response->json('data.0.id'));
        $this->assertNotNull($response->json('data.0.created_at'));
    }

    public function test_marking_one_and_all_as_read(): void
    {
        $student = User::factory()->create();
        $this->notify($student, 'First');
        $this->notify($student, 'Second');
        $this->notify($student, 'Third');
        $first = UserNotification::where('title', 'First')->first();

        Sanctum::actingAs($student, ['access-api']);

        $this->postJson("/api/notifications/{$first->id}/read")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);
        $this->assertNotNull($first->fresh()->read_at);

        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 2);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked_count', 2)
            ->assertJsonPath('data.unread_count', 0);
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_cannot_mark_someone_elses_notification(): void
    {
        $owner = User::factory()->create();
        $this->notify($owner, 'Private');
        $notification = UserNotification::first();

        Sanctum::actingAs(User::factory()->create(), ['access-api']);

        $this->postJson("/api/notifications/{$notification->id}/read")->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_parent_inbox_is_separate_from_a_student_with_the_same_id(): void
    {
        $parent = $this->parent();
        $student = User::factory()->create(['id' => $parent->id]);
        $this->notify($parent, 'For parent');
        $this->notify($student, 'For student');

        Sanctum::actingAs($parent, ['access-api']);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'For parent');
    }
}
