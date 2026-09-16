<?php

namespace App\Services\Admin;

use App\Models\Video;
use App\Services\FileStorage;
use App\Support\YouTubeVideo;
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
        $youtubeId = isset($data['youtube_url']) ? YouTubeVideo::idFrom($data['youtube_url']) : null;

        return Video::create([
            'lesson_id' => $data['lesson_id'],
            'title' => $data['title'],
            'source' => $youtubeId ? Video::SOURCE_YOUTUBE : Video::SOURCE_UPLOAD,
            'url' => $youtubeId ? null : FileStorage::storeFile($data['video'], 'videos', 'vid'),
            'youtube_video_id' => $youtubeId,
            'thumbnail' => isset($data['thumbnail']) ? FileStorage::storeFile($data['thumbnail'], 'videos', 'img') : null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'order' => $data['order'] ?? 0,
            'is_free' => $data['is_free'] ?? false,
            // A YouTube video is played through YouTube's own player; offline
            // download doesn't apply to it (and their terms forbid it).
            'is_downloadable' => $youtubeId ? false : ($data['is_downloadable'] ?? false),
            'status' => $data['status'] ?? 'incomplete',
        ]);
    }

    public function update(Video $video, array $data): Video
    {
        $updates = [
            'lesson_id' => $data['lesson_id'] ?? $video->lesson_id,
            'title' => $data['title'] ?? $video->title,
            'thumbnail' => isset($data['thumbnail'])
                ? FileStorage::fileExists($data['thumbnail'], $video->getRawOriginal('thumbnail'), 'videos', 'img')
                : $video->getRawOriginal('thumbnail'),
            'duration_seconds' => $data['duration_seconds'] ?? $video->duration_seconds,
            'order' => $data['order'] ?? $video->order,
            'is_free' => $data['is_free'] ?? $video->is_free,
            'status' => $data['status'] ?? $video->status,
        ];

        $video->update($updates + $this->sourceUpdates($video, $data));

        return $video->fresh();
    }

    /**
     * A new link (or a new file, once uploads are back on) replaces the other
     * source, so a video never carries both.
     */
    private function sourceUpdates(Video $video, array $data): array
    {
        if (isset($data['youtube_url'])) {
            return [
                'source' => Video::SOURCE_YOUTUBE,
                'youtube_video_id' => YouTubeVideo::idFrom($data['youtube_url']),
                'url' => null,
                'is_downloadable' => false,
            ];
        }

        if (isset($data['video'])) {
            return [
                'source' => Video::SOURCE_UPLOAD,
                'url' => FileStorage::fileExists($data['video'], $video->url, 'videos', 'vid'),
                'youtube_video_id' => null,
                'is_downloadable' => $data['is_downloadable'] ?? $video->is_downloadable,
            ];
        }

        return [
            'is_downloadable' => $video->source === Video::SOURCE_YOUTUBE
                ? false
                : ($data['is_downloadable'] ?? $video->is_downloadable),
        ];
    }

    public function delete(Video $video): void
    {
        $video->delete();
    }
}
