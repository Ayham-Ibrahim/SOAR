<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Question;
use App\Services\Admin\QuestionService;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function __construct(private readonly QuestionService $questionService)
    {
    }

    public function index(Request $request)
    {
        $questions = $this->questionService->list(
            $request->integer('exam_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($questions, 'تم جلب الأسئلة بنجاح');
    }

    public function store(StoreQuestionRequest $request)
    {
        $question = $this->questionService->create($request->validated());

        return $this->success($question, 'تم إنشاء السؤال بنجاح', 201);
    }

    public function show(Question $question)
    {
        return $this->success($question->load('choices'), 'تم جلب بيانات السؤال بنجاح');
    }

    public function update(UpdateQuestionRequest $request, Question $question)
    {
        $question = $this->questionService->update($question, $request->validated());

        return $this->success($question, 'تم تحديث بيانات السؤال بنجاح');
    }

    public function destroy(Question $question)
    {
        $this->questionService->delete($question);

        return $this->success([], 'تم حذف السؤال بنجاح (وكل خياراته)');
    }
}
