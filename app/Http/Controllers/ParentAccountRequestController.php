<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParentAccountRequestRequest;
use App\Services\ParentAccountRequestService;
use Illuminate\Http\Request;

class ParentAccountRequestController extends Controller
{
    public function __construct(private readonly ParentAccountRequestService $parentAccountRequestService)
    {
    }

    /**
     * The authenticated student's own request(s) and status only — never
     * the password (hidden on the model regardless).
     */
    public function index(Request $request)
    {
        $requests = $this->parentAccountRequestService->listForStudent(
            $request->user(),
            $request->integer('per_page', 15)
        );

        return $this->paginate($requests, 'تم جلب طلباتك بنجاح');
    }

    public function store(StoreParentAccountRequestRequest $request)
    {
        $parentRequest = $this->parentAccountRequestService->submit($request->user(), $request->validated());

        return $this->success($parentRequest, 'تم إرسال طلب إنشاء حساب ولي الأمر بنجاح', 201);
    }
}
