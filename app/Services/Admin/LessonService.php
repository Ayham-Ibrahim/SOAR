<?php

namespace App\Services\Admin;

use App\Models\Lesson;
use Illuminate\Pagination\LengthAwarePaginator;

class LessonService
{
    public function list(?int $unitId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Lesson::query()
            ->with('unit')
            ->when($unitId, fn ($query) => $query->where('unit_id', $unitId))
            ->paginate($perPage);
    }

    public function create(array $data): Lesson
    {
        return Lesson::create([
            'unit_id' => $data['unit_id'],
            'title' => $data['title'],
            'order' => $data['order'] ?? 0,
            'is_free' => $data['is_free'] ?? false,
        ]);
    }

    public function update(Lesson $lesson, array $data): Lesson
    {
        $lesson->update([
            'unit_id' => $data['unit_id'] ?? $lesson->unit_id,
            'title' => $data['title'] ?? $lesson->title,
            'order' => $data['order'] ?? $lesson->order,
            'is_free' => $data['is_free'] ?? $lesson->is_free,
        ]);

        return $lesson->fresh();
    }

    public function delete(Lesson $lesson): void
    {
        $lesson->delete();
    }
}
