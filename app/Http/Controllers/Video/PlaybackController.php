<?php

namespace App\Http\Controllers\Video;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\VideoPlaybackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaybackController extends Controller
{
    public function __construct(private readonly VideoPlaybackService $playback)
    {
    }

    /**
     * A personal, short-lived HLS link for the lesson's video. Needs the
     * X-Device-Id header (the app's stable device id) for the device limit.
     */
    public function store(Request $request, Lesson $lesson): JsonResponse
    {
        $deviceId = trim((string) $request->header('X-Device-Id'));

        if ($deviceId === '' || mb_strlen($deviceId) > 191) {
            return $this->error('معرّف الجهاز (X-Device-Id) مطلوب.', 422);
        }

        $validated = $request->validate([
            'video_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->playback->start(
            $request->user(),
            $lesson,
            $deviceId,
            $request->ip(),
            $request->userAgent(),
            isset($validated['video_id']) ? (int) $validated['video_id'] : null,
        ));
    }
}
