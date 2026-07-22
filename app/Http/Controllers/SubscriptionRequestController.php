<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOfferSubscriptionRequestRequest;
use App\Http\Requests\StoreSubscriptionRequestRequest;
use App\Services\SubscriptionRequestService;
use Illuminate\Http\Request;

class SubscriptionRequestController extends Controller
{
    public function __construct(private readonly SubscriptionRequestService $subscriptionRequestService)
    {
    }

    /**
     * The authenticated student's own subscription requests (direct and offer, mixed).
     */
    public function index(Request $request)
    {
        $requests = $this->subscriptionRequestService->listForStudent(
            $request->user(),
            $request->integer('per_page', 15)
        );

        return $this->paginate($requests, 'تم جلب طلبات الاشتراك بنجاح');
    }

    public function store(StoreSubscriptionRequestRequest $request)
    {
        $subscriptionRequest = $this->subscriptionRequestService->submitDirect($request->user(), $request->validated());

        return $this->success($subscriptionRequest, 'تم إرسال طلب الاشتراك بنجاح', 201);
    }

    public function storeOffer(StoreOfferSubscriptionRequestRequest $request)
    {
        $subscriptionRequest = $this->subscriptionRequestService->submitOffer($request->user(), $request->validated());

        return $this->success($subscriptionRequest, 'تم إرسال طلب الاشتراك بالعرض بنجاح', 201);
    }
}
