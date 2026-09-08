<?php

namespace Tests\Feature;

use App\Jobs\SendBroadcastNotification;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NewsNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_news_queues_notification_for_all_students_and_parents(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        $response = $this->postJson('/api/admin/news', [
            'title' => 'خبر جديد',
            'body' => 'محتوى الخبر الجديد',
        ]);

        $response->assertStatus(201);

        $notification = Notification::query()->latest('id')->first();
        $this->assertNotNull($notification);
        $this->assertSame('خبر جديد', $notification->title);
        $this->assertSame('محتوى الخبر الجديد', $notification->content);
        $this->assertSame(['all'], $notification->target_types);

        Queue::assertPushed(SendBroadcastNotification::class, function (SendBroadcastNotification $job) use ($notification) {
            return $job->notification->is($notification);
        });
    }
}
