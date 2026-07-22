<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Reporting/notification only — flips is_active=false on subscriptions past
 * their expires_at. The actual access gate (App\Services\CourseAccess) never
 * reads this flag; it compares expires_at directly, so a missed or delayed
 * run of this command cannot accidentally grant or revoke access.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark subscriptions past their expiry as inactive (reporting/notifications only)';

    public function handle(): void
    {
        $expired = Subscription::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['is_active' => false]);

            // TODO: notify the student ("اشتراكك انتهى") — no notification
            // channel exists in this project yet, see the note in
            // Admin\ParentAccountRequestService::approve().
        }

        $this->info("Expired {$expired->count()} subscription(s).");
    }
}
