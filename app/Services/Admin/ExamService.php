<?php

namespace App\Services\Admin;

use App\Models\Exam;
use Illuminate\Pagination\LengthAwarePaginator;

class ExamService
{
    public function list(?int $courseId = null, int $perPage = 15, bool $activeOnly = false): LengthAwarePaginator
    {
        return Exam::query()
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Exam
    {
        return Exam::create([
            'course_id' => $data['course_id'],
            'title' => $data['title'],
            'type' => $data['type'] ?? 'mcq',
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'passing_score' => $data['passing_score'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Exam $exam, array $data): Exam
    {
        $exam->update([
            'course_id' => $data['course_id'] ?? $exam->course_id,
            'title' => $data['title'] ?? $exam->title,
            'type' => $data['type'] ?? $exam->type,
            'description' => $data['description'] ?? $exam->description,
            'duration_minutes' => $data['duration_minutes'] ?? $exam->duration_minutes,
            'passing_score' => $data['passing_score'] ?? $exam->passing_score,
            'is_active' => $data['is_active'] ?? $exam->is_active,
        ]);

        return $exam->fresh();
    }

    public function delete(Exam $exam): void
    {
        $exam->delete();
    }
}
