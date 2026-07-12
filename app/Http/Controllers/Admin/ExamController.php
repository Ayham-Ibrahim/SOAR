<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamRequest;
use App\Http\Requests\Admin\UpdateExamRequest;
use App\Models\Exam;
use App\Services\Admin\ExamService;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function __construct(private readonly ExamService $examService)
    {
    }

    public function index(Request $request)
    {
        $exams = $this->examService->list(
            $request->integer('course_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($exams, 'تم جلب الامتحانات بنجاح');
    }

    public function store(StoreExamRequest $request)
    {
        $exam = $this->examService->create($request->validated());

        return $this->success($exam, 'تم إنشاء الامتحان بنجاح', 201);
    }

    public function show(Exam $exam)
    {
        return $this->success($exam->load('questions.choices'), 'تم جلب بيانات الامتحان بنجاح');
    }

    public function update(UpdateExamRequest $request, Exam $exam)
    {
        $exam = $this->examService->update($exam, $request->validated());

        return $this->success($exam, 'تم تحديث بيانات الامتحان بنجاح');
    }

    public function destroy(Exam $exam)
    {
        $this->examService->delete($exam);

        return $this->success([], 'تم حذف الامتحان بنجاح (وكل أسئلته وخياراته)');
    }
}
