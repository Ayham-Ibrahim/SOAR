<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GradeExamAttemptRequest;
use App\Models\ExamAttempt;
use App\Services\ExamAttemptService;
use Illuminate\Http\Request;

class ExamAttemptController extends Controller
{
    public function __construct(private readonly ExamAttemptService $examAttemptService)
    {
    }

    /**
     * Review queue: all student submissions, filterable by exam and status
     * (e.g. ?status=pending_review to find written exams awaiting grading).
     */
    public function index(Request $request)
    {
        $attempts = ExamAttempt::query()
            ->with(['exam', 'user'])
            ->when($request->integer('exam_id'), fn ($query, $examId) => $query->where('exam_id', $examId))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginate($attempts, 'تم جلب محاولات الامتحانات بنجاح');
    }

    public function show(ExamAttempt $exam_attempt)
    {
        return $this->success(
            $exam_attempt->load(['exam', 'user', 'answers.question', 'answers.choice']),
            'تم جلب بيانات المحاولة بنجاح'
        );
    }

    /**
     * Grade a written exam submission (sets score + optional feedback).
     */
    public function grade(GradeExamAttemptRequest $request, ExamAttempt $exam_attempt)
    {
        $exam_attempt = $this->examAttemptService->grade($exam_attempt, $request->validated());

        return $this->success($exam_attempt, 'تم تصحيح المحاولة بنجاح');
    }
}
