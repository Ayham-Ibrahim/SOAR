<?php

namespace App\Services\Admin;

use App\Models\Course;
use App\Models\Offer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Admin-side management of granted subscriptions (as opposed to
 * SubscriptionRequestService, which reviews the requests that create them).
 */
class SubscriptionService
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * Every subscription the student has had — active, expired and revoked.
     */
    public function forStudent(User $student, int $perPage = 15): LengthAwarePaginator
    {
        return $student->subscriptions()
            ->with(['course:id,title', 'offer:id,title', 'revoker:id,name'])
            ->latest('starts_at')
            ->paginate($perPage);
    }

    /**
     * Students with a live grant from this package — one row per student.
     */
    public function offerSubscribers(Offer $offer, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->select(['id', 'name', 'phone', 'avatar'])
            ->whereHas('subscriptions', fn ($query) => $query->where('offer_id', $offer->id)->active())
            ->paginate($perPage);
    }

    /**
     * Ends the student's access to one course: every live grant for it,
     * whether bought directly or as part of a package — the package's other
     * courses stay open.
     */
    public function revokeCourse(User $student, Course $course, ?string $reason, User $admin): Collection
    {
        return $this->revoke(
            $student->subscriptions()->where('course_id', $course->id),
            $student,
            $course->title,
            $reason,
            $admin
        );
    }

    /**
     * Ends the whole package: every live grant the student got from it.
     */
    public function revokeOffer(User $student, Offer $offer, ?string $reason, User $admin): Collection
    {
        return $this->revoke(
            $student->subscriptions()->where('offer_id', $offer->id),
            $student,
            $offer->title,
            $reason,
            $admin
        );
    }

    /**
     * Marks the live grants revoked (who, when, why) instead of deleting
     * them, so the record stays for audit. Empty when there was nothing live.
     */
    private function revoke(HasMany $grants, User $student, string $label, ?string $reason, User $admin): Collection
    {
        $revoked = DB::transaction(function () use ($grants, $reason, $admin) {
            $ids = $grants->active()->lockForUpdate()->pluck('id');

            Subscription::whereKey($ids)->update([
                'revoked_at' => now(),
                'revoked_by' => $admin->id,
                'revocation_reason' => $reason,
                'is_active' => false,
            ]);

            return Subscription::whereKey($ids)->with(['course:id,title', 'offer:id,title'])->get();
        });

        if ($revoked->isNotEmpty()) {
            $this->notificationService->notifyStudentSubscriptionRevoked($student, $label, $reason);
        }

        return $revoked;
    }
}
