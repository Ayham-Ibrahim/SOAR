<?php

namespace App\Services\Admin;

use App\Models\Lesson;
use Illuminate\Pagination\LengthAwarePaginator;

class LessonService
{
    public function list(?int $courseId = null, ?int $unitId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Lesson::query()
            ->with(['course', 'unit'])
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when($unitId, fn ($query) => $query->where('unit_id', $unitId))
            ->paginate($perPage);
    }

    public function create(array $data): Lesson
    {
        return Lesson::create([
            'course_id' => $data['course_id'],
            'unit_id' => $data['unit_id'],
            'title' => $data['title'],
            'order' => $data['order'] ?? 0,
            'is_free' => $data['is_free'] ?? false,
        ]);
    }

    public function update(Lesson $lesson, array $data): Lesson
    {
        $lesson->update([
            'course_id' => $data['course_id'] ?? $lesson->course_id,
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
