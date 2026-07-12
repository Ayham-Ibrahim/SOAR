<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVideoRequest;
use App\Http\Requests\Admin\UpdateVideoRequest;
use App\Models\Video;
use App\Services\Admin\VideoService;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function __construct(private readonly VideoService $videoService)
    {
    }

    public function index(Request $request)
    {
        $videos = $this->videoService->list(
            $request->integer('lesson_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($videos, 'تم جلب الفيديوهات بنجاح');
    }

    public function store(StoreVideoRequest $request)
    {
        $video = $this->videoService->create($request->validated());

        return $this->success($video, 'تم إنشاء الفيديو بنجاح', 201);
    }

    public function show(Video $video)
    {
        return $this->success($video, 'تم جلب بيانات الفيديو بنجاح');
    }

    public function update(UpdateVideoRequest $request, Video $video)
    {
        $video = $this->videoService->update($video, $request->validated());

        return $this->success($video, 'تم تحديث بيانات الفيديو بنجاح');
    }

    public function destroy(Video $video)
    {
        $this->videoService->delete($video);

        return $this->success([], 'تم حذف الفيديو بنجاح');
    }
}
