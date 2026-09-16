<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevokeSubscriptionRequest;
use App\Models\Course;
use App\Models\Offer;
use App\Models\User;
use App\Services\Admin\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptionService)
    {
    }

    public function forStudent(Request $request, User $student)
    {
        return $this->paginate(
            $this->subscriptionService->forStudent($student, $request->integer('per_page', 15)),
            'تم جلب اشتراكات الطالب بنجاح'
        );
    }

    public function offerSubscribers(Request $request, Offer $offer)
    {
        return $this->paginate(
            $this->subscriptionService->offerSubscribers($offer, $request->integer('per_page', 15)),
            'تم جلب الطلاب المشتركين بالباقة بنجاح'
        );
    }

    public function revokeCourse(RevokeSubscriptionRequest $request, Course $course, User $student)
    {
        $revoked = $this->subscriptionService->revokeCourse(
            $student,
            $course,
            $request->validated('reason'),
            $request->user()
        );

        return $revoked->isEmpty()
            ? $this->error('لا يوجد اشتراك فعّال لهذا الطالب في هذه الدورة', 404)
            : $this->success($revoked, 'تم سحب اشتراك الطالب في الدورة بنجاح');
    }

    public function revokeOffer(RevokeSubscriptionRequest $request, Offer $offer, User $student)
    {
        $revoked = $this->subscriptionService->revokeOffer(
            $student,
            $offer,
            $request->validated('reason'),
            $request->user()
        );

        return $revoked->isEmpty()
            ? $this->error('لا يوجد اشتراك فعّال لهذا الطالب في هذه الباقة', 404)
            : $this->success($revoked, 'تم سحب اشتراك الطالب في الباقة بنجاح');
    }
}
