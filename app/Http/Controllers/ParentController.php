<?php

namespace App\Http\Controllers;

use App\Services\ParentAppService;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function __construct(private readonly ParentAppService $parentAppService)
    {
    }

    public function children(Request $request)
    {
        return $this->success($this->parentAppService->children($request->user()), 'تم جلب قائمة الأبناء بنجاح');
    }
}
