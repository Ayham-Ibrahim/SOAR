<?php

namespace App\Services\Admin;

use App\Models\Category;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()->paginate($perPage);
    }

    public function create(array $data): Category
    {
        return Category::create([
            'name' => $data['name'],
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'categories', 'img') : null,
            'order' => $data['order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update([
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
