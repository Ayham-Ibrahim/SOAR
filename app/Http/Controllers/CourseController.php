<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Models\Course;
use App\Services\Admin\CourseService;
use App\Services\CourseAccess;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
        private readonly CourseAccess $courseAccess
    ) {
    }

    public function index(Request $request)
    {
        $courses = $this->courseService->list(
            $request->integer('subject_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($courses, 'تم جلب الدورات بنجاح');
    }

    public function store(StoreCourseRequest $request)
    {
        $course = $this->courseService->create($request->validated());

        return $this->success($course, 'تم إنشاء الدورة بنجاح', 201);
    }

    /**
     * Course Details: full curriculum tree (subject, teacher, lessons → unit +
     * videos/files). Video/file URLs are hidden unless the student has paid
     * access (CourseAccess) or the video is marked free — the lesson/video
     * titles themselves stay visible as a catalog preview either way.
     */
    public function show(Request $request, Course $course)
    {
        $course->load([
            'subject.subCategory.category',
            'teacher',
            'lessons.unit',
            'lessons.videos',
            'lessons.files',
        ]);

        $hasAccess = $this->courseAccess->hasAccess($request->user(), $course);

        $course->lessons->each(function ($lesson) use ($hasAccess) {
            $lesson->videos->each(function ($video) use ($hasAccess) {
                if (! $hasAccess && ! $video->is_free) {
                    $video->makeHidden('url');
                }
            });

            if (! $hasAccess) {
                $lesson->files->each->makeHidden('path');
            }
        });

        $course->has_access = $hasAccess;

        return $this->success($course, 'تم جلب تفاصيل الدورة بنجاح');
    }

    public function update(UpdateCourseRequest $request, Course $course)
    {
        $course = $this->courseService->update($course, $request->validated());

        return $this->success($course, 'تم تحديث بيانات الدورة بنجاح');
    }

    public function destroy(Course $course)
    {
        $this->courseService->delete($course);

        return $this->success([], 'تم حذف الدورة بنجاح');
    }
}
