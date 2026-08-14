<?php

namespace App\Services\Admin;

use App\Models\Lesson;
use Illuminate\Pagination\LengthAwarePaginator;

class LessonService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Lesson::query()
            ->with(['courses', 'unit'])
            ->curriculumFilter($filters)
            ->paginate($perPage);
    }

    public function create(array $data): Lesson
    {
        $courseIds = $data['course_ids'] ?? ($data['course_id'] ? [$data['course_id']] : []);

        $lesson = Lesson::create([
            'unit_id' => $data['unit_id'],
            'title' => $data['title'],
            'order' => $data['order'] ?? 0,
            'is_free' => $data['is_free'] ?? false,
        ]);

        if ($courseIds) {
            $lesson->courses()->sync($courseIds);
        }

        return $lesson->fresh(['courses', 'unit', 'videos', 'files']);
    }

    public function update(Lesson $lesson, array $data): Lesson
    {
        $courseIds = $data['course_ids'] ?? ($data['course_id'] ? [$data['course_id']] : null);

        $lesson->update([
            'unit_id' => $data['unit_id'] ?? $lesson->unit_id,
            'title' => $data['title'] ?? $lesson->title,
            'order' => $data['order'] ?? $lesson->order,
            'is_free' => $data['is_free'] ?? $lesson->is_free,
        ]);

        if ($courseIds !== null) {
            $lesson->courses()->sync($courseIds);
        }

        return $lesson->fresh(['courses', 'unit', 'videos', 'files']);
    }

    public function delete(Lesson $lesson): void
    {
        $lesson->delete();
    }
}
