<?php

namespace App\Services\Admin;

use App\Models\Category;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryService
{
    public function list(?int $branchId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()
            ->with('branch')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->paginate($perPage);
    }

    public function create(array $data): Category
    {
        return Category::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'categories', 'img') : null,
            'order' => $data['order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update([
            'branch_id' => $data['branch_id'] ?? $category->branch_id,
            'name' => $data['name'] ?? $category->name,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $category->image, 'categories', 'img')
                : $category->image,
            'order' => $data['order'] ?? $category->order,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);

        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
