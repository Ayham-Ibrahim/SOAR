<?php

namespace App\Services\Admin;

use App\Models\File as LessonFile;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class FileService
{
    public function list(?int $lessonId = null, int $perPage = 15): LengthAwarePaginator
    {
        return LessonFile::query()
            ->when($lessonId, fn ($query) => $query->where('lesson_id', $lessonId))
            ->paginate($perPage);
    }

    public function create(array $data): LessonFile
    {
        return LessonFile::create([
            'lesson_id' => $data['lesson_id'],
            'title' => $data['title'],
            'path' => FileStorage::storeFile($data['file'], 'lesson-files', 'docs'),
            'type' => $data['type'],
        ]);
    }

    public function update(LessonFile $file, array $data): LessonFile
    {
        $file->update([
            'lesson_id' => $data['lesson_id'] ?? $file->lesson_id,
            'title' => $data['title'] ?? $file->title,
            'path' => isset($data['file'])
                ? FileStorage::fileExists($data['file'], $file->path, 'lesson-files', 'docs')
                : $file->path,
            'type' => $data['type'] ?? $file->type,
        ]);

        return $file->fresh();
    }

    public function delete(LessonFile $file): void
    {
        $file->delete();
    }
}
