<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\NotificationService;
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

    public function handle(NotificationService $notificationService): void
    {
        $expired = Subscription::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->with('student')
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['is_active' => false]);

            if ($subscription->student) {
                $notificationService->notifyStudentSubscriptionExpired($subscription->student, $subscription);
            }
        }

        $this->info("Expired {$expired->count()} subscription(s).");
    }
}
