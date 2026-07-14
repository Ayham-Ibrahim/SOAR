<?php

namespace App\Services\Admin;

use App\Models\SubCategory;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class SubCategoryService
{
    public function list(?int $categoryId = null, int $perPage = 15): LengthAwarePaginator
    {
        return SubCategory::query()
            ->with('category')
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->paginate($perPage);
    }

    public function create(array $data): SubCategory
    {
        return SubCategory::create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'sub-categories', 'img') : null,
            'order' => $data['order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(SubCategory $subCategory, array $data): SubCategory
    {
        $subCategory->update([
            'category_id' => $data['category_id'] ?? $subCategory->category_id,
            'name' => $data['name'] ?? $subCategory->name,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $subCategory->image, 'sub-categories', 'img')
                : $subCategory->image,
            'order' => $data['order'] ?? $subCategory->order,
            'is_active' => $data['is_active'] ?? $subCategory->is_active,
        ]);

        return $subCategory->fresh();
    }

    public function delete(SubCategory $subCategory): void
    {
        $subCategory->delete();
    }
}
