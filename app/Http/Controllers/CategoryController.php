<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Admin\CategoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }

    public function index(Request $request)
    {
        $categories = $this->categoryService->list(
            $request->integer('branch_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($categories, 'تم جلب الفئات بنجاح');
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = $this->categoryService->create($request->validated());

        return $this->success($category, 'تم إنشاء الفئة بنجاح', 201);
    }

    public function show(Category $category)
    {
        return $this->success($category->load('branch'), 'تم جلب بيانات الفئة بنجاح');
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category = $this->categoryService->update($category, $request->validated());

        return $this->success($category, 'تم تحديث بيانات الفئة بنجاح');
    }

    public function destroy(Category $category)
    {
        try {
            $this->categoryService->delete($category);
        } catch (QueryException $e) {
            return $this->error('لا يمكن حذف الفئة لوجود مواد مرتبطة بها', 409);
        }

        return $this->success([], 'تم حذف الفئة بنجاح');
    }
}
