<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveParentAccountRequestRequest;
use App\Http\Requests\Admin\RejectParentAccountRequestRequest;
use App\Models\ParentAccountRequest;
use App\Services\Admin\ParentAccountRequestService;
use Illuminate\Http\Request;

class ParentAccountRequestController extends Controller
{
    public function __construct(private readonly ParentAccountRequestService $parentAccountRequestService)
    {
    }

    public function index(Request $request)
    {
        $requests = $this->parentAccountRequestService->list(
            $request->string('status')->value() ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($requests, 'تم جلب طلبات إنشاء حسابات أولياء الأمور بنجاح');
    }

    public function show(ParentAccountRequest $parent_account_request)
    {
        return $this->success(
            $parent_account_request->load('student:id,name,phone', 'reviewer:id,name'),
            'تم جلب تفاصيل الطلب بنجاح'
        );
    }

    public function approve(ApproveParentAccountRequestRequest $request, ParentAccountRequest $parent_account_request)
    {
        $parent = $this->parentAccountRequestService->approve(
            $parent_account_request,
            $request->validated('student_ids'),
            $request->user()
        );

        return $this->success($parent, 'تم قبول الطلب وإنشاء حساب ولي الأمر بنجاح');
    }

    public function reject(RejectParentAccountRequestRequest $request, ParentAccountRequest $parent_account_request)
    {
        $rejected = $this->parentAccountRequestService->reject(
            $parent_account_request,
            $request->validated('rejection_reason'),
            $request->user()
        );

        return $this->success($rejected, 'تم رفض الطلب بنجاح');
    }
}
