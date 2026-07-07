<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreSchoolRequest;
use App\Http\Requests\Admin\UpdateSchoolRequest;
use App\Models\School;
use App\Services\Admin\SchoolService;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __construct(private readonly SchoolService $schoolService)
    {
    }

    public function index(Request $request)
    {
        $schools = $this->schoolService->list(
            $request->integer('governorate_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($schools, 'تم جلب المدارس بنجاح');
    }

    public function store(StoreSchoolRequest $request)
    {
        $school = $this->schoolService->create($request->validated());

        return $this->success($school, 'تم إنشاء المدرسة بنجاح', 201);
    }

    public function show(School $school)
    {
        return $this->success($school->load('governorate'), 'تم جلب بيانات المدرسة بنجاح');
    }

    public function update(UpdateSchoolRequest $request, School $school)
    {
        $school = $this->schoolService->update($school, $request->validated());

        return $this->success($school, 'تم تحديث بيانات المدرسة بنجاح');
    }

    public function destroy(School $school)
    {
        $this->schoolService->delete($school);

        return $this->success([], 'تم حذف المدرسة بنجاح');
    }
}
