<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Services\Admin\OfferService;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offerService)
    {
    }

    /**
     * Active offers, for the student app to browse and purchase.
     */
    public function index(Request $request)
    {
        $offers = $this->offerService->list($request->integer('per_page', 15), activeOnly: true);

        return $this->paginate($offers, 'تم جلب العروض بنجاح');
    }

    public function show(Offer $offer)
    {
        return $this->success($offer->load('courses'), 'تم جلب تفاصيل العرض بنجاح');
    }
}
