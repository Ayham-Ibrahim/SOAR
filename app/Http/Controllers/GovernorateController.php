<?php

namespace App\Http\Controllers;

use App\Services\Admin\GovernorateService;

class GovernorateController extends Controller
{
    public function __construct(private readonly GovernorateService $governorateService)
    {
    }

    public function index()
    {
        return $this->success($this->governorateService->list(), 'تم جلب المحافظات بنجاح');
    }
}
