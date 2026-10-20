<?php

namespace App\Http\Controllers\Video;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\VideoDownload;
use App\Services\VideoDownloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Offline downloads for the student app. Like playback, every call needs
 * the X-Device-Id header: a download only plays on the device it was made
 * for.
 */
class VideoDownloadController extends Controller
{
    public function __construct(private readonly VideoDownloadService $downloads)
    {
    }

    public function store(Request $request, Lesson $lesson): JsonResponse
    {
        $deviceId = $this->deviceId($request);
        if ($deviceId === null) {
            return $this->error('معرّف الجهاز (X-Device-Id) مطلوب.', 422);
        }

        $validated = $request->validate([
            'video_id' => ['nullable', 'integer'],
            'height' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->downloads->start(
            $request->user(),
            $lesson,
            $deviceId,
            $request->ip(),
            $request->userAgent(),
            isset($validated['video_id']) ? (int) $validated['video_id'] : null,
            isset($validated['height']) ? (int) $validated['height'] : null,
        ));
    }

    /**
     * Called whenever the app is online: renews the offline copies still
     * allowed; the app deletes the ones that come back invalid.
     */
    public function sync(Request $request): JsonResponse
    {
        $deviceId = $this->deviceId($request);
        if ($deviceId === null) {
            return $this->error('معرّف الجهاز (X-Device-Id) مطلوب.', 422);
        }

        $validated = $request->validate([
            'download_ids' => ['present', 'array', 'max:500'],
            'download_ids.*' => ['string', 'max:64'],
        ]);

        return response()->json([
            'downloads' => $this->downloads->sync($request->user(), $deviceId, $validated['download_ids']),
        ]);
    }

    public function destroy(Request $request, VideoDownload $download): Response
    {
        $this->downloads->remove($request->user(), $download);

        return response()->noContent();
    }

    private function deviceId(Request $request): ?string
    {
        $deviceId = trim((string) $request->header('X-Device-Id'));

        return $deviceId === '' || mb_strlen($deviceId) > 191 ? null : $deviceId;
    }
}
