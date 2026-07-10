<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Models\Course;
use App\Services\Admin\CourseService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(private readonly CourseService $courseService)
    {
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
     * Course Details: full curriculum tree (subject/branch, teacher, units → lessons → videos/files).
     */
    public function show(Course $course)
    {
        $course->load([
            'subject.category.branch',
            'teacher',
            'units.lessons.videos',
            'units.lessons.files',
        ]);

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
