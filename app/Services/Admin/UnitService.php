<?php

namespace App\Services\Admin;

use App\Models\Unit;
use Illuminate\Pagination\LengthAwarePaginator;

class UnitService
{
    public function list(?int $subjectId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Unit::query()
            ->with('subject')
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->paginate($perPage);
    }

    public function create(array $data): Unit
    {
        return Unit::create([
            'subject_id' => $data['subject_id'],
            'title' => $data['title'],
            'order' => $data['order'] ?? 0,
        ]);
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update([
            'subject_id' => $data['subject_id'] ?? $unit->subject_id,
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
