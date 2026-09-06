<?php

namespace App\Services\Admin;

use App\Models\StudyType;
use Illuminate\Database\Eloquent\Collection;

class StudyTypeService
{
    public function list(): Collection
    {
        return StudyType::query()->orderBy('name')->get();
    }

    public function create(array $data): StudyType
    {
        return StudyType::create($data);
    }

    public function update(StudyType $studyType, array $data): StudyType
    {
        $studyType->update($data);

        return $studyType->fresh();
    }

    public function delete(StudyType $studyType): void
    {
        $studyType->delete();
    }
}