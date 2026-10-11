<?php

namespace App\Http\Controllers\Video;

use App\Http\Controllers\Controller;
use App\Http\Requests\Video\CompleteVideoUploadRequest;
use App\Http\Requests\Video\SignVideoPartsRequest;
use App\Http\Requests\Video\StartVideoUploadRequest;
use App\Models\Video;
use App\Services\VideoUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Dashboard upload flow (see soaar-video-frontend.md). Responses are the
 * bare objects that contract defines, not the usual {status, data} envelope.
 */
class VideoUploadController extends Controller
{
    public function __construct(private readonly VideoUploadService $uploads)
    {
    }

    public function store(StartVideoUploadRequest $request): JsonResponse
    {
        $video = $this->uploads->start($request->user(), $request->validated());

        return response()->json([
            'video_id' => $video->id,
            'upload_id' => $video->upload_id,
            'part_size' => $video->part_size,
            'total_parts' => $video->total_parts,
        ], 201);
    }

    public function signParts(SignVideoPartsRequest $request, Video $video): JsonResponse
    {
        return response()->json(
            $this->uploads->signParts($video, array_map('intval', $request->validated('part_numbers')))
        );
    }

    public function parts(Video $video): JsonResponse
    {
        return response()->json($this->uploads->uploadedParts($video));
    }

    public function complete(CompleteVideoUploadRequest $request, Video $video): JsonResponse
    {
        $video = $this->uploads->complete($video, $request->parts());

        return response()->json([
            'video_id' => $video->id,
            'status' => $video->processing_status,
        ]);
    }

    public function destroy(Video $video): Response
    {
        $this->uploads->abort($video);

        return response()->noContent();
    }

    public function show(Video $video): JsonResponse
    {
        return response()->json([
            'id' => $video->id,
            'status' => $video->processing_status,
            'duration' => $video->duration_seconds,
            'thumbnail_url' => $video->thumbnailUrl(),
            'error' => $video->error,
        ]);
    }
}
