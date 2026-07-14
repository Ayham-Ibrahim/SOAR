<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreSubCategoryRequest;
use App\Http\Requests\Admin\UpdateSubCategoryRequest;
use App\Models\SubCategory;
use App\Services\Admin\SubCategoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class SubCategoryController extends Controller
{
    public function __construct(private readonly SubCategoryService $subCategoryService)
    {
    }

    public function index(Request $request)
    {
        $subCategories = $this->subCategoryService->list(
            $request->integer('category_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($subCategories, 'تم جلب الفروع بنجاح');
    }

    public function store(StoreSubCategoryRequest $request)
    {
        $subCategory = $this->subCategoryService->create($request->validated());

        return $this->success($subCategory, 'تم إنشاء الفرع بنجاح', 201);
    }

    public function show(SubCategory $sub_category)
    {
        return $this->success($sub_category->load('category'), 'تم جلب بيانات الفرع بنجاح');
    }

    public function update(UpdateSubCategoryRequest $request, SubCategory $sub_category)
    {
        $subCategory = $this->subCategoryService->update($sub_category, $request->validated());

        return $this->success($subCategory, 'تم تحديث بيانات الفرع بنجاح');
    }

    public function destroy(SubCategory $sub_category)
    {
        try {
            $this->subCategoryService->delete($sub_category);
        } catch (QueryException) {
            return $this->error('لا يمكن حذف الفرع لوجود مواد مرتبطة به', 409);
        }

        return $this->success([], 'تم حذف الفرع بنجاح');
    }
}
