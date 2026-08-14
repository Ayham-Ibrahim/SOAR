<?php

namespace App\Services\Admin;

use App\Models\Video;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class VideoService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Video::query()
            ->curriculumFilter($filters)
            ->paginate($perPage);
    }

    public function create(array $data): Video
    {
        return Video::create([
            'lesson_id' => $data['lesson_id'],
            'title' => $data['title'],
            'url' => FileStorage::storeFile($data['video'], 'videos', 'vid'),
            'thumbnail' => isset($data['thumbnail']) ? FileStorage::storeFile($data['thumbnail'], 'videos', 'img') : null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'order' => $data['order'] ?? 0,
            'is_free' => $data['is_free'] ?? false,
            'is_downloadable' => $data['is_downloadable'] ?? false,
            'status' => $data['status'] ?? 'incomplete',
        ]);
    }

    public function update(Video $video, array $data): Video
    {
        $video->update([
            'lesson_id' => $data['lesson_id'] ?? $video->lesson_id,
            'title' => $data['title'] ?? $video->title,
            'url' => isset($data['video'])
                ? FileStorage::fileExists($data['video'], $video->url, 'videos', 'vid')
                : $video->url,
            'thumbnail' => isset($data['thumbnail'])
                ? FileStorage::fileExists($data['thumbnail'], $video->thumbnail, 'videos', 'img')
                : $video->thumbnail,
            'duration_seconds' => $data['duration_seconds'] ?? $video->duration_seconds,
            'order' => $data['order'] ?? $video->order,
            'is_free' => $data['is_free'] ?? $video->is_free,
            'is_downloadable' => $data['is_downloadable'] ?? $video->is_downloadable,
            'status' => $data['status'] ?? $video->status,
        ]);

        return $video->fresh();
    }

    public function delete(Video $video): void
    {
        $video->delete();
    }
}
