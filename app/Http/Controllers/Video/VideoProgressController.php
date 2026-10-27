<?php

namespace App\Http\Controllers\Video;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\VideoProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoProgressController extends Controller
{
    public function __construct(private readonly VideoProgressService $progress)
    {
    }

    /**
     * The player's current position, sent every ~15 seconds while playing
     * and on pause/close; also after an offline viewing once back online.
     */
    public function store(Request $request, Video $video): JsonResponse
    {
        $validated = $request->validate([
            'position_seconds' => ['required', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
        ]);

        $progress = $this->progress->record(
            $request->user(),
            $video,
            (int) $validated['position_seconds'],
            isset($validated['duration_seconds']) ? (int) $validated['duration_seconds'] : null,
        );

        return $this->success([
            'video_id' => $video->id,
            'watched_seconds' => $progress->watched_seconds,
            'last_position_seconds' => $progress->last_position_seconds,
            'watch_percentage' => VideoProgressService::percentage($video, $progress),
        ], 'تم حفظ تقدم المشاهدة');
    }
}
