<?php

namespace App\Services\Admin;

use App\Models\Teacher;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class TeacherService
{
    public function list(int $perPage = 15, bool $catalogOnly = false): LengthAwarePaginator
    {
        return Teacher::query()
            ->withCount(['courses' => fn ($query) => $query->when($catalogOnly, fn ($query) => $query->inCatalog())])
            ->when($catalogOnly, fn ($query) => $query->where('is_active', true))
            ->latest()
            ->paginate($perPage);
    }

    public function listِActive(int $perPage = 15): LengthAwarePaginator
    {
        return Teacher::query()->with(['courses' => fn ($query) => $query->inCatalog()])->where('is_active', true)->latest()->paginate($perPage);
    }
    
    public function create(array $data): Teacher
    {
        return Teacher::create([
            'name' => $data['name'],
            'bio' => $data['bio'] ?? null,
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'teachers', 'img') : null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Teacher $teacher, array $data): Teacher
    {
        $teacher->update([
            'name' => $data['name'] ?? $teacher->name,
            'bio' => $data['bio'] ?? $teacher->bio,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $teacher->image, 'teachers', 'img')
                : $teacher->image,
            'is_active' => $data['is_active'] ?? $teacher->is_active,
        ]);

        return $teacher->fresh();
    }

    public function delete(Teacher $teacher): void
    {
        $teacher->delete();
    }
}
