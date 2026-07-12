<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOfferRequest;
use App\Http\Requests\Admin\UpdateOfferRequest;
use App\Models\Offer;
use App\Services\Admin\OfferService;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offerService)
    {
    }

    public function index(Request $request)
    {
        $offers = $this->offerService->list(
            $request->integer('package_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($offers, 'تم جلب العروض بنجاح');
    }

    public function store(StoreOfferRequest $request)
    {
        $offer = $this->offerService->create($request->validated());

        return $this->success($offer, 'تم إنشاء العرض بنجاح', 201);
    }

    public function show(Offer $offer)
    {
        return $this->success($offer->load('package'), 'تم جلب بيانات العرض بنجاح');
    }

    public function update(UpdateOfferRequest $request, Offer $offer)
    {
        $offer = $this->offerService->update($offer, $request->validated());

        return $this->success($offer, 'تم تحديث بيانات العرض بنجاح');
    }

    public function destroy(Offer $offer)
    {
        $this->offerService->delete($offer);

        return $this->success([], 'تم حذف العرض بنجاح');
    }
}
