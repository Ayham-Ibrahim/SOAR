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
        $offers = $this->offerService->list($request->integer('per_page', 15), catalogOnly: true);

        return $this->paginate($offers, 'تم جلب العروض بنجاح');
    }

    /**
     * Courses come with their teacher and subject. Ones taken out of the
     * catalog are left out — the offer no longer sells them.
     */
    public function show(Offer $offer)
    {
        return $this->success($this->offerService->details($offer, catalogOnly: true), 'تم جلب تفاصيل العرض بنجاح');
    }
}
