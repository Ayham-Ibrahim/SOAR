<?php

namespace App\Services\Admin;

use App\Models\Branch;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class BranchService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Branch::query()->paginate($perPage);
    }

    public function create(array $data): Branch
    {
        return Branch::create([
            'name' => $data['name'],
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'branches', 'img') : null,
            'order' => $data['order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->update([
            'name' => $data['name'] ?? $branch->name,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $branch->image, 'branches', 'img')
                : $branch->image,
            'order' => $data['order'] ?? $branch->order,
            'is_active' => $data['is_active'] ?? $branch->is_active,
        ]);

        return $branch->fresh();
    }

    public function delete(Branch $branch): void
    {
        $branch->delete();
    }
}
