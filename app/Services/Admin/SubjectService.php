<?php

namespace App\Services\Admin;

use App\Models\Subject;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class SubjectService
{
    public function list(?int $subCategoryId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Subject::query()
            ->with('subCategory')
            ->withCount('courses')
            ->when($subCategoryId, fn ($query) => $query->where('sub_category_id', $subCategoryId))
            ->paginate($perPage);
    }

    public function create(array $data): Subject
    {
        return Subject::create([
            'sub_category_id' => $data['sub_category_id'],
            'name' => $data['name'],
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'subjects', 'img') : null,
            'order' => $data['order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Subject $subject, array $data): Subject
    {
        $subject->update([
            'sub_category_id' => $data['sub_category_id'] ?? $subject->sub_category_id,
            'name' => $data['name'] ?? $subject->name,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $subject->image, 'subjects', 'img')
                : $subject->image,
            'order' => $data['order'] ?? $subject->order,
            'is_active' => $data['is_active'] ?? $subject->is_active,
        ]);

        return $subject->fresh();
    }

    public function delete(Subject $subject): void
    {
        $subject->delete();
    }
}
