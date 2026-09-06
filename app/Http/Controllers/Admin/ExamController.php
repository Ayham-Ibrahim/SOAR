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
            $this->curriculumFilters($request),
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
        $exam->load(['course.subject.subCategory.category', 'questions.choices']);
        $exam->loadCount('questions');
        $exam->participants_count = $exam->attempts()->distinct('user_id')->count('user_id');

        return $this->success($exam, 'تم جلب بيانات الامتحان بنجاح');
    }

    public function participants(Request $request, Exam $exam)
    {
        $participants = $this->examService->participants(
            $exam,
            $request->integer('per_page', 15)
        );

        $participants->getCollection()->transform(function ($attempt) {
            return [
                'student' => $attempt->user,
                'score' => $attempt->score,
                'status' => $attempt->status,
                'submitted_at' => $attempt->created_at?->toDateTimeString(),
                'graded_at' => $attempt->graded_at?->toDateTimeString(),
            ];
        });

        return $this->paginate($participants, 'تم جلب الطلاب المتقدمين للامتحان بنجاح');
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
