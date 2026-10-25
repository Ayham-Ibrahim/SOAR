<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExamAttemptRequest;
use App\Models\SeenMarker;
use App\Services\ExamAttemptService;
use App\Services\NewContentService;
use Illuminate\Http\Request;

class ExamAttemptController extends Controller
{
    public function __construct(
        private readonly ExamAttemptService $examAttemptService,
        private readonly NewContentService $newContent
    ) {
    }

    /**
     * "مشاهدة النتائج" — the authenticated student's own exam attempt history.
     * additional_data: results graded since they last opened this page
     * (POST /exam-attempts/seen).
     */
    public function index(Request $request)
    {
        $attempts = $this->examAttemptService->listForUser(
            $request->user(),
            $request->integer('exam_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginateWithData(
            $attempts,
            $this->newContent->summary($this->newContent->newResultsCount($request->user(), $request->user())),
            'تم جلب نتائجك بنجاح'
        );
    }

    public function seen(Request $request)
    {
        $this->newContent->markSeen($request->user(), SeenMarker::RESULTS);

        return $this->success($this->newContent->summary(0), 'تم تعليم النتائج كمشاهدة');
    }

    /**
     * "أداء الاختبارات" / "رفع حلول الاختبارات التقليدية" — submit MCQ answers
     * (auto-graded immediately) or a solution file for a written exam
     * (pending manual review).
     */
    public function store(StoreExamAttemptRequest $request)
    {
        $attempt = $this->examAttemptService->submit($request->user(), $request->validated());

        return $this->success($attempt, 'تم تسليم الامتحان بنجاح', 201);
    }

    public function show(Request $request, int $id)
    {
        $attempt = $this->examAttemptService->findForUser($request->user(), $id);

        return $this->success($attempt, 'تم جلب نتيجة الامتحان بنجاح');
    }
}
