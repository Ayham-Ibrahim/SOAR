<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Models\Subject;
use App\Services\Admin\SubjectService;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function __construct(private readonly SubjectService $subjectService)
    {
    }

    public function index(Request $request)
    {
        $subjects = $this->subjectService->list(
            $request->integer('category_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($subjects, 'تم جلب المواد بنجاح');
    }

    public function store(StoreSubjectRequest $request)
    {
        $subject = $this->subjectService->create($request->validated());

        return $this->success($subject, 'تم إنشاء المادة بنجاح', 201);
    }

    public function show(Subject $subject)
    {
        return $this->success($subject->load('category.branch'), 'تم جلب بيانات المادة بنجاح');
    }

    public function update(UpdateSubjectRequest $request, Subject $subject)
    {
        $subject = $this->subjectService->update($subject, $request->validated());

        return $this->success($subject, 'تم تحديث بيانات المادة بنجاح');
    }

    public function destroy(Subject $subject)
    {
        $this->subjectService->delete($subject);

        return $this->success([], 'تم حذف المادة بنجاح');
    }
}
