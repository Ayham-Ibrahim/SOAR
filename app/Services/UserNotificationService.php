<?php

namespace App\Services;

use App\Models\UserNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

class UserNotificationService
{
    public function listFor(Authenticatable $recipient): Collection
    {
        return UserNotification::query()
            ->where('notifiable_type', $recipient::class)
            ->where('notifiable_id', $recipient->getAuthIdentifier())
            ->get();
    }
}