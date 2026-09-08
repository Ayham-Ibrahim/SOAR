<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_types_are_stored_for_all_notification_kinds(): void
    {
        putenv('FIREBASE_CREDENTIALS_FILE=missing-test-credentials.json');

        $user = User::factory()->create();
        Device::create([
            'deviceable_id' => $user->id,
            'deviceable_type' => $user->getMorphClass(),
            'fcm_token' => 'type-test-token',
        ]);
        $fcmService = app(FcmService::class);

        $fcmService->sendToUser($user, 'News', 'News body', ['type' => 'news']);
        $fcmService->sendToUser($user, 'Exam', 'Exam body', ['type' => 'exam_result']);
        $fcmService->sendToUser($user, 'Other', 'Other body', ['type' => 'broadcast']);

        $this->assertSame('news', UserNotification::where('title', 'News')->value('type'));
        $this->assertSame('exam_result', UserNotification::where('title', 'Exam')->value('type'));
        $this->assertSame('broadcast', UserNotification::where('title', 'Other')->value('type'));
    }

    public function test_notification_is_stored_for_user_without_a_device(): void
    {
        putenv('FIREBASE_CREDENTIALS_FILE=missing-test-credentials.json');

        $user = User::factory()->create();

        $sent = app(FcmService::class)->sendToUser(
            $user,
            'No device',
            'Stored in inbox',
            ['type' => 'subscription_approved']
        );

        $this->assertSame(0, $sent);
        $this->assertDatabaseHas('user_notifications', [
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'title' => 'No device',
            'type' => 'subscription_approved',
        ]);
    }
}
