<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveSubscriptionRequestRequest;
use App\Http\Requests\Admin\RejectSubscriptionRequestRequest;
use App\Models\SubscriptionRequest;
use App\Services\Admin\SubscriptionRequestService;
use Illuminate\Http\Request;

class SubscriptionRequestController extends Controller
{
    public function __construct(private readonly SubscriptionRequestService $subscriptionRequestService)
    {
    }

    public function index(Request $request)
    {
        $requests = $this->subscriptionRequestService->list(
            $request->string('status')->value() ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($requests, 'تم جلب طلبات الاشتراك بنجاح');
    }

    public function show(SubscriptionRequest $subscription_request)
    {
        return $this->success(
            $subscription_request->load(['student:id,name,phone', 'course', 'offer', 'reviewer:id,name']),
            'تم جلب تفاصيل الطلب بنجاح'
        );
    }

    public function approve(ApproveSubscriptionRequestRequest $request, SubscriptionRequest $subscription_request)
    {
        $approved = $this->subscriptionRequestService->approve(
            $subscription_request,
            $request->validated('access_ends_at'),
            $request->user()
        );

        return $this->success($approved, 'تم قبول الطلب وتفعيل الاشتراك بنجاح');
    }

    public function reject(RejectSubscriptionRequestRequest $request, SubscriptionRequest $subscription_request)
    {
        $rejected = $this->subscriptionRequestService->reject(
            $subscription_request,
            $request->validated('rejection_reason'),
            $request->user()
        );

        return $this->success($rejected, 'تم رفض الطلب بنجاح');
    }
}
