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
        $categories = $this->categoryService->list($request->integer('per_page', 15));

        return $this->paginate($categories, 'تم جلب المراحل بنجاح');
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = $this->categoryService->create($request->validated());

        return $this->success($category, 'تم إنشاء المرحلة بنجاح', 201);
    }

    public function show(Category $category)
    {
        return $this->success($category, 'تم جلب بيانات المرحلة بنجاح');
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category = $this->categoryService->update($category, $request->validated());

        return $this->success($category, 'تم تحديث بيانات المرحلة بنجاح');
    }

    public function destroy(Category $category)
    {
        try {
            $this->categoryService->delete($category);
        } catch (QueryException) {
            return $this->error('لا يمكن حذف المرحلة لوجود فروع مرتبطة بها', 409);
        }

        return $this->success([], 'تم حذف المرحلة بنجاح');
    }
}
