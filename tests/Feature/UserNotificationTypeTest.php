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

    public function test_news_and_exam_result_types_are_stored_and_other_types_are_null(): void
    {
        putenv('FIREBASE_CREDENTIALS_FILE=missing-test-credentials.json');

        $user = User::factory()->create();
        Device::create([
            'deviceable_id' => $user->id,
            'deviceable_type' => $user->getMorphClass(),
            'fcm_token' => 'type-test-token',
        ]);
        $fcmService = app(FcmService::class);

        $fcmService->sendToToken('type-test-token', 'News', 'News body', ['type' => 'news']);
        $fcmService->sendToToken('type-test-token', 'Exam', 'Exam body', ['type' => 'exam_result']);
        $fcmService->sendToToken('type-test-token', 'Other', 'Other body', ['type' => 'broadcast']);

        $this->assertSame('news', UserNotification::where('title', 'News')->value('type'));
        $this->assertSame('exam_result', UserNotification::where('title', 'Exam')->value('type'));
        $this->assertNull(UserNotification::where('title', 'Other')->value('type'));
    }
}
