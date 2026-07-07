<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\Admin\BranchService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(private readonly BranchService $branchService)
    {
    }

    public function index(Request $request)
    {
        $branches = $this->branchService->list($request->integer('per_page', 15));

        return $this->paginate($branches, 'تم جلب الفروع بنجاح');
    }

    public function store(StoreBranchRequest $request)
    {
        $branch = $this->branchService->create($request->validated());

        return $this->success($branch, 'تم إنشاء الفرع بنجاح', 201);
    }

    public function show(Branch $branch)
    {
        return $this->success($branch, 'تم جلب بيانات الفرع بنجاح');
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $branch = $this->branchService->update($branch, $request->validated());

        return $this->success($branch, 'تم تحديث بيانات الفرع بنجاح');
    }

    public function destroy(Branch $branch)
    {
        try {
            $this->branchService->delete($branch);
        } catch (QueryException $e) {
            return $this->error('لا يمكن حذف الفرع لوجود فئات مرتبطة به', 409);
        }

        return $this->success([], 'تم حذف الفرع بنجاح');
    }
}
