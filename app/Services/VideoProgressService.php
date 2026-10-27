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

    public function record(User $user, Video $video, int $positionSeconds, ?int $durationSeconds): VideoProgress
    {
        if (! $this->playback->canWatch($user, $video->lesson, $video)) {
            throw new VideoPipelineException('ليس لديك صلاحية مشاهدة هذا الدرس.', 403);
        }

        $progress = VideoProgress::firstOrNew(['user_id' => $user->id, 'video_id' => $video->id]);

        $progress->fill([
            'watched_seconds' => max($progress->watched_seconds ?? 0, $positionSeconds),
            'last_position_seconds' => $positionSeconds,
            'duration_seconds' => $durationSeconds ?? $progress->duration_seconds,
        ])->save();

        return $progress;
    }

    /**
     * 0–100, against the video's own duration, else the one the player
     * reported. Null when neither is known.
     */
    public static function percentage(Video $video, ?VideoProgress $progress): ?int
    {
        $duration = $video->duration_seconds ?: $progress?->duration_seconds;

        if (! $duration) {
            return $progress ? null : 0;
        }

        return (int) min(100, round(($progress?->watched_seconds ?? 0) / $duration * 100));
    }
}
