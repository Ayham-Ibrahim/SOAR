<?php

namespace App\Services\Admin;

use App\Models\School;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class SchoolService
{
    public function list(?int $governorateId = null, int $perPage = 15): LengthAwarePaginator
    {
        return School::query()
            ->with('governorate')
            ->when($governorateId, fn ($query) => $query->where('governorate_id', $governorateId))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): School
    {
        return School::create([
            'governorate_id' => $data['governorate_id'],
            'name' => $data['name'],
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'schools', 'img') : null,
        ]);
    }

    public function update(School $school, array $data): School
    {
        $school->update([
            'governorate_id' => $data['governorate_id'] ?? $school->governorate_id,
            'name' => $data['name'] ?? $school->name,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $school->image, 'schools', 'img')
                : $school->image,
        ]);

        return $school->fresh();
    }

    public function delete(School $school): void
    {
        $school->delete();
    }
}
