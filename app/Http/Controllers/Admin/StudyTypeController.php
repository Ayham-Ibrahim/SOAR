<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudyTypeRequest;
use App\Http\Requests\Admin\UpdateStudyTypeRequest;
use App\Models\StudyType;
use App\Services\Admin\StudyTypeService;
use Illuminate\Http\Request;

class StudyTypeController extends Controller
{
    public function __construct(private readonly StudyTypeService $studyTypeService)
    {
    }

    public function index(Request $request)
    {
        return $this->success($this->studyTypeService->list(), 'تم جلب أنواع الدراسة بنجاح');
    }

    public function store(StoreStudyTypeRequest $request)
    {
        return $this->success(
            $this->studyTypeService->create($request->validated()),
            'تم إنشاء نوع الدراسة بنجاح',
            201
        );
    }

    public function show(StudyType $studyType)
    {
        return $this->success($studyType, 'تم جلب نوع الدراسة بنجاح');
    }

    public function update(UpdateStudyTypeRequest $request, StudyType $studyType)
    {
        return $this->success(
            $this->studyTypeService->update($studyType, $request->validated()),
            'تم تحديث نوع الدراسة بنجاح'
        );
    }

    public function destroy(StudyType $studyType)
    {
        $this->studyTypeService->delete($studyType);

        return $this->success([], 'تم حذف نوع الدراسة بنجاح');
    }
}