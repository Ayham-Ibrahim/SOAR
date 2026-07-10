<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLessonRequest;
use App\Http\Requests\Admin\UpdateLessonRequest;
use App\Models\Lesson;
use App\Services\Admin\LessonService;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function __construct(private readonly LessonService $lessonService)
    {
    }

    public function index(Request $request)
    {
        $lessons = $this->lessonService->list(
            $request->integer('unit_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($lessons, 'تم جلب الدروس بنجاح');
    }

    public function store(StoreLessonRequest $request)
    {
        $lesson = $this->lessonService->create($request->validated());

        return $this->success($lesson, 'تم إنشاء الدرس بنجاح', 201);
    }

    public function show(Lesson $lesson)
    {
        return $this->success($lesson->load(['videos', 'files']), 'تم جلب بيانات الدرس بنجاح');
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson)
    {
        $lesson = $this->lessonService->update($lesson, $request->validated());

        return $this->success($lesson, 'تم تحديث بيانات الدرس بنجاح');
    }

    public function destroy(Lesson $lesson)
    {
        $this->lessonService->delete($lesson);

        return $this->success([], 'تم حذف الدرس بنجاح (وكل الفيديوهات والملفات التابعة له)');
    }
}
