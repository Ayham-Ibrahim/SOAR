<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\User;
use App\Services\Admin\StudentService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(private readonly StudentService $studentService)
    {
    }

    public function index(Request $request)
    {
        $students = $this->studentService->list(
            $request->integer('per_page', 15),
            $request->string('search')->value() ?: null
        );

        return $this->paginate($students, 'تم جلب الطلاب بنجاح');
    }

    public function store(StoreStudentRequest $request)
    {
        $student = $this->studentService->create($request->validated());

        return $this->success($student, 'تم إنشاء الطالب بنجاح', 201);
    }

    public function show(User $student)
    {
        return $this->success($student, 'تم جلب بيانات الطالب بنجاح');
    }

    public function update(UpdateStudentRequest $request, User $student)
    {
        $student = $this->studentService->update($student, $request->validated());

        return $this->success($student, 'تم تحديث بيانات الطالب بنجاح');
    }

    public function destroy(User $student)
    {
        $this->studentService->delete($student);

        return $this->success([], 'تم حذف الطالب بنجاح');
    }
}
