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
            'target_types' => $payload['target_types'],
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

        $normalizedTargets = array_values(array_intersect(
            array_unique(array_map('strval', $rawTargetTypes)),
            array_keys(Notification::getTargetTypes())
        ));

        if (empty($normalizedTargets)) {
            $normalizedTargets = [Notification::TARGET_STUDENTS];
        }

        // "all" already covers both sides; keeping another target beside it
        // would send a second copy to the same people.
        if (in_array(Notification::TARGET_ALL, $normalizedTargets, true)) {
            $normalizedTargets = [Notification::TARGET_ALL];
        }

        $finalRecipient = $normalizedTargets[0];
        $filters = [];

        if (filled(Arr::get($data, 'governorate_id'))) {
            $filters['governorate_id'] = (int) $data['governorate_id'];
        }

        if (filled(Arr::get($data, 'category_id'))) {
            $filters['category_id'] = (int) $data['category_id'];
        }

        if (filled(Arr::get($data, 'sub_category_id'))) {
            $filters['sub_category_id'] = (int) $data['sub_category_id'];
        }

        if (filled(Arr::get($data, 'study_type_id'))) {
            $filters['study_type_id'] = (int) $data['study_type_id'];
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

        if (in_array(Arr::get($data, 'notification_type'), ['news'], true)) {
            $filters['notification_type'] = Arr::get($data, 'notification_type');
        }

        return [
            'title' => (string) Arr::get($data, 'title', ''),
            'content' => (string) Arr::get($data, 'content', ''),
            'target_types' => $normalizedTargets,
            'recipient_type' => $finalRecipient, // kept for older callers; target_types is what's sent
            'filters' => $filters,
        ];
    }
}
