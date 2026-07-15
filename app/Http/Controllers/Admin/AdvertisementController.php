<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdvertisementRequest;
use App\Http\Requests\Admin\UpdateAdvertisementRequest;
use App\Models\Advertisement;
use App\Services\Admin\AdvertisementService;
use Illuminate\Http\Request;

class AdvertisementController extends Controller
{
    public function __construct(private readonly AdvertisementService $advertisementService)
    {
    }

    public function index(Request $request)
    {
        $advertisements = $this->advertisementService->list($request->integer('per_page', 15));

        return $this->paginate($advertisements, 'تم جلب الإعلانات بنجاح');
    }

    public function store(StoreAdvertisementRequest $request)
    {
        $advertisement = $this->advertisementService->create($request->validated());

        return $this->success($advertisement, 'تم إنشاء الإعلان بنجاح', 201);
    }

    public function show(Advertisement $advertisement)
    {
        return $this->success($advertisement, 'تم جلب بيانات الإعلان بنجاح');
    }

    public function update(UpdateAdvertisementRequest $request, Advertisement $advertisement)
    {
        $advertisement = $this->advertisementService->update($advertisement, $request->validated());

        return $this->success($advertisement, 'تم تحديث بيانات الإعلان بنجاح');
    }

    public function destroy(Advertisement $advertisement)
    {
        $this->advertisementService->delete($advertisement);

        return $this->success([], 'تم حذف الإعلان بنجاح');
    }

    public function getAdsUser(Request $request)
    {
        $advertisements = $this->advertisementService->getAdsForUser($request->integer('per_page', 15));

        return $this->paginate($advertisements, 'تم جلب الإعلانات بنجاح');
    }
}
