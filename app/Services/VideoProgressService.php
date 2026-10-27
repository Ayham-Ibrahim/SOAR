<?php

namespace App\Services;

use App\Exceptions\VideoPipelineException;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProgress;

/**
 * Watch progress, reported by the student app's player every few seconds
 * and on pause/close. Progress is the furthest point reached, so seeking
 * back to rewatch never lowers it.
 */
class VideoProgressService
{
    public function __construct(private readonly VideoPlaybackService $playback)
    {
    }

    public function record(User $user, Video $video, int $positionSeconds): VideoProgress
    {
        if (! $this->playback->canWatch($user, $video->lesson, $video)) {
            throw new VideoPipelineException('ليس لديك صلاحية مشاهدة هذا الدرس.', 403);
        }

        $progress = VideoProgress::firstOrNew(['user_id' => $user->id, 'video_id' => $video->id]);

        $progress->fill([
            'watched_seconds' => max($progress->watched_seconds ?? 0, $positionSeconds),
            'last_position_seconds' => $positionSeconds,
        ])->save();

        return $progress;
    }

    /**
     * 0–100, against the video's duration (set when it is processed). Null
     * for a watched video whose duration isn't known yet.
     */
    public static function percentage(Video $video, ?VideoProgress $progress): ?int
    {
        if (! $video->duration_seconds) {
            return $progress ? null : 0;
        }

        return (int) min(100, round(($progress?->watched_seconds ?? 0) / $video->duration_seconds * 100));
    }
}
