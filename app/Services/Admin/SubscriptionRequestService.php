<?php

namespace App\Services\Admin;

use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SubscriptionRequestService
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function list(?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return SubscriptionRequest::query()
            ->with(['student', 'course', 'offer'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Direct request: one subscription row, expires_at = the admin-chosen
     * access_ends_at. Offer request: one subscription row per bundled
     * course, all expiring access_duration_days from NOW — never from the
     * offer's own purchase window (offer_ends_at plays no part here).
     */
    public function approve(SubscriptionRequest $request, ?string $accessEndsAt, User $admin): SubscriptionRequest
    {
        return DB::transaction(function () use ($request, $accessEndsAt, $admin) {
            if ($request->course_id) {
                Subscription::create([
                    'student_id' => $request->student_id,
                    'course_id' => $request->course_id,
                    'source' => 'direct',
                    'subscription_request_id' => $request->id,
                    'starts_at' => now(),
                    'expires_at' => $accessEndsAt,
                ]);
            } else {
                $grantStart = now();
                $grantEnd = now()->addDays($request->offer->access_duration_days);

                foreach ($request->offer->courses as $course) {
                    Subscription::create([
                        'student_id' => $request->student_id,
                        'course_id' => $course->id,
                        'source' => 'offer',
                        'offer_id' => $request->offer_id,
                        'subscription_request_id' => $request->id,
                        'starts_at' => $grantStart,
                        'expires_at' => $grantEnd,
                    ]);
                }
            }

            $request->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if ($request->student) {
                $this->notificationService->notifyStudentSubscriptionApproved($request->student, $request);
            }

            return $request->fresh('subscriptions');
        });
    }

    public function reject(SubscriptionRequest $request, string $reason, User $admin): SubscriptionRequest
    {
        $request->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        if ($request->student) {
            $this->notificationService->notifyStudentSubscriptionRejected($request->student, $reason);
        }

        return $request->fresh();
    }
}
