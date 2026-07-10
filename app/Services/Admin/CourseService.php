<?php

namespace App\Services\Admin;

use App\Models\Course;
use Illuminate\Pagination\LengthAwarePaginator;

class CourseService
{
    public function list(?int $subjectId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Course::query()
            ->with(['subject', 'teacher'])
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Course
    {
        return Course::create([
            'subject_id' => $data['subject_id'],
            'teacher_id' => $data['teacher_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'price' => $data['price'],
            'discount_price' => $data['discount_price'] ?? null,
            'subscription_days' => $data['subscription_days'],
            'free_videos_count' => $data['free_videos_count'] ?? 0,
            'allow_download' => $data['allow_download'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Course $course, array $data): Course
    {
        $course->update([
            'subject_id' => $data['subject_id'] ?? $course->subject_id,
            'teacher_id' => $data['teacher_id'] ?? $course->teacher_id,
            'title' => $data['title'] ?? $course->title,
            'description' => $data['description'] ?? $course->description,
            'price' => $data['price'] ?? $course->price,
            'discount_price' => $data['discount_price'] ?? $course->discount_price,
            'subscription_days' => $data['subscription_days'] ?? $course->subscription_days,
            'free_videos_count' => $data['free_videos_count'] ?? $course->free_videos_count,
            'allow_download' => $data['allow_download'] ?? $course->allow_download,
            'is_active' => $data['is_active'] ?? $course->is_active,
        ]);

        return $course->fresh();
    }

    public function delete(Course $course): void
    {
        $course->delete();
    }
}
