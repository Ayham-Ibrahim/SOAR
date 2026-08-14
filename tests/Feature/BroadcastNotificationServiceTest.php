<?php

namespace Tests\Feature;

use App\Services\Admin\BroadcastNotificationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BroadcastNotificationServiceTest extends TestCase
{
    #[Test]
    public function it_normalizes_notification_payload_for_targeted_broadcasts(): void
    {
        $service = new BroadcastNotificationService();

        $payload = [
            'title' => 'تنبيه جديد',
            'content' => 'هذا نص الإشعار',
            'recipient_type' => 'parents',
            'governorate_id' => 7,
            'gender' => 'female',
            'student_ids' => [1, 2, 2],
            'parent_ids' => [9],
        ];

        $normalized = $service->normalizePayload($payload);

        $this->assertSame('parents', $normalized['recipient_type']);
        $this->assertSame(7, $normalized['filters']['governorate_id']);
        $this->assertSame('female', $normalized['filters']['gender']);
        $this->assertSame([1, 2], $normalized['filters']['student_ids']);
        $this->assertSame([9], $normalized['filters']['parent_ids']);
    }
}
