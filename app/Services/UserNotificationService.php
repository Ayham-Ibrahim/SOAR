<?php

namespace App\Services;

use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserNotificationService
{
    public function listFor(Model $recipient): Collection
    {
        return $this->inbox($recipient)
            ->latest()
            ->orderByDesc('id')
            ->get();
    }

    public function unreadCount(Model $recipient): int
    {
        return $this->inbox($recipient)->unread()->count();
    }

    public function markRead(Model $recipient, int $id): UserNotification
    {
        $notification = $this->inbox($recipient)->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }

    /**
     * @return int how many were unread
     */
    public function markAllRead(Model $recipient): int
    {
        return $this->inbox($recipient)->unread()->update(['read_at' => now(), 'updated_at' => now()]);
    }

    private function inbox(Model $recipient): Builder
    {
        return UserNotification::query()
            ->where('notifiable_type', $recipient->getMorphClass())
            ->where('notifiable_id', $recipient->getKey());
    }
}
