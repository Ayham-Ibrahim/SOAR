<?php

namespace App\Services\Admin;

use App\Models\Exam;
use App\Services\FileStorage;
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
            'attachment' => isset($data['attachment']) ? $this->storeAttachment($data['attachment']) : null,
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
            'attachment' => isset($data['attachment'])
                ? $this->storeAttachment($data['attachment'], $exam->attachment)
                : $exam->attachment,
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

    /**
     * The attachment may be a PDF or an image — FileStorage validates each
     * against a different allowed-type list, so route by the file's mime.
     */
    private function storeAttachment($file, ?string $old = null): string
    {
        $suffix = str_starts_with($file->getMimeType(), 'image/') ? 'img' : 'docs';

        return $old
            ? FileStorage::fileExists($file, $old, 'exams', $suffix)
            : FileStorage::storeFile($file, 'exams', $suffix);
    }
}
