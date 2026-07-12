<?php

namespace App\Http\Controllers;

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
            $request->integer('per_page', 15),
            activeOnly: true,
        );

        return $this->paginate($exams, 'تم جلب الامتحانات بنجاح');
    }

    /**
     * Exam questions & choices for taking the exam — correct-answer flags are
     * stripped so students can't read them off the API response.
     */
    public function show(Exam $exam)
    {
        $exam->load('questions.choices');

        $exam->questions->each(function ($question) {
            $question->choices->each->makeHidden('is_correct');
        });

        return $this->success($exam, 'تم جلب بيانات الامتحان بنجاح');
    }
}
