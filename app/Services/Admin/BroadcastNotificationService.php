<?php

namespace App\Services\Admin;

use App\Jobs\SendBroadcastNotification;
use App\Models\Notification;
use Illuminate\Support\Arr;

/**
 * Broadcast Notification Service
 *
 * Handles creation and management of broadcast notifications for this project's
 * students and parents, with optional governorate/gender targeting.
 */
class BroadcastNotificationService
{
    /**
     * Create and send a broadcast notification.
     */
    public function createAndSend(array $data): Notification
    {
        $payload = $this->normalizePayload($data);

        $notification = Notification::create([
            'title' => $payload['title'],
            'content' => $payload['content'],
            'target_types' => [$payload['recipient_type']],
            'status' => Notification::STATUS_PENDING,
            'sent_count' => 0,
            'filters' => $payload['filters'],
        ]);

        SendBroadcastNotification::dispatch($notification);

        return $notification;
    }

    /**
     * Get all notifications (paginated).
     */
    public function getAllNotifications(int $perPage = 15)
    {
        return Notification::latest()->paginate($perPage);
    }

    /**
     * Get notification by ID.
     */
    public function getNotificationById(int $id): Notification
    {
        $notification = Notification::find($id);

        if (! $notification) {
            abort(404, 'الإشعار غير موجود');
        }

        return $notification;
    }

    /**
     * Delete notification.
     */
    public function deleteNotification(int $id): void
    {
        $notification = $this->getNotificationById($id);

        if (in_array($notification->status, [Notification::STATUS_PENDING, Notification::STATUS_SENDING], true)) {
            abort(400, 'لا يمكن حذف الإشعار أثناء الإرسال');
        }

        $notification->delete();
    }

    /**
     * Get available target types with their labels.
     */
    public function getTargetTypes(): array
    {
        return Notification::getTargetTypes();
    }

    /**
     * Normalize and sanitize the payload for this project's notification rules.
     */
    public function normalizePayload(array $data): array
    {
        $rawTargetTypes = Arr::wrap(Arr::get($data, 'target_types', []));
        $legacyRecipient = Arr::get($data, 'recipient_type');

        if (empty($rawTargetTypes) && ! empty($legacyRecipient)) {
            $rawTargetTypes = [$legacyRecipient];
        }

        $normalizedTargets = array_values(array_unique(array_map('strval', $rawTargetTypes)));
        $finalRecipient = ! empty($normalizedTargets) ? $normalizedTargets[0] : 'students';
        $filters = [];

        if (filled(Arr::get($data, 'governorate_id'))) {
            $filters['governorate_id'] = (int) $data['governorate_id'];
        }

        if (filled(Arr::get($data, 'gender'))) {
            $filters['gender'] = (string) $data['gender'];
        }

        if (! empty(Arr::get($data, 'student_ids'))) {
            $filters['student_ids'] = array_values(array_unique(array_map('intval', Arr::wrap($data['student_ids']))));
        }

        if (! empty(Arr::get($data, 'parent_ids'))) {
            $filters['parent_ids'] = array_values(array_unique(array_map('intval', Arr::wrap($data['parent_ids']))));
        }

        return [
            'title' => (string) Arr::get($data, 'title', ''),
            'content' => (string) Arr::get($data, 'content', ''),
            'recipient_type' => in_array($finalRecipient, ['students', 'parents', 'all'], true) ? $finalRecipient : 'students',
            'filters' => $filters,
        ];
    }
}
