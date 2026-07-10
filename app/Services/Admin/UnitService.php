<?php

namespace App\Services\Admin;

use App\Models\Unit;
use Illuminate\Pagination\LengthAwarePaginator;

class UnitService
{
    public function list(?int $courseId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Unit::query()
            ->with('course')
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->paginate($perPage);
    }

    public function create(array $data): Unit
    {
        return Unit::create([
            'course_id' => $data['course_id'],
            'title' => $data['title'],
            'order' => $data['order'] ?? 0,
        ]);
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update([
            'course_id' => $data['course_id'] ?? $unit->course_id,
            'title' => $data['title'] ?? $unit->title,
            'order' => $data['order'] ?? $unit->order,
        ]);

        return $unit->fresh();
    }

    public function delete(Unit $unit): void
    {
        $unit->delete();
    }
}
