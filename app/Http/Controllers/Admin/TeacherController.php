<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Services\Admin\TeacherService;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(private readonly TeacherService $teacherService)
    {
    }

    public function index(Request $request)
    {
        $teachers = $this->teacherService->list($request->integer('per_page', 15));

        return $this->paginate($teachers, 'تم جلب المدرّسين بنجاح');
    }

    public function store(StoreTeacherRequest $request)
    {
        $teacher = $this->teacherService->create($request->validated());

        return $this->success($teacher, 'تم إنشاء المدرّس بنجاح', 201);
    }

    public function show(Teacher $teacher)
    {
        return $this->success($teacher, 'تم جلب بيانات المدرّس بنجاح');
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher)
    {
        $teacher = $this->teacherService->update($teacher, $request->validated());

        return $this->success($teacher, 'تم تحديث بيانات المدرّس بنجاح');
    }

    public function destroy(Teacher $teacher)
    {
        $this->teacherService->delete($teacher);

        return $this->success([], 'تم حذف المدرّس بنجاح');
    }
}
