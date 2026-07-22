<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ExamAttemptService;
use App\Services\ParentAppService;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function __construct(
        private readonly ParentAppService $parentAppService,
        private readonly ExamAttemptService $examAttemptService
    ) {
    }

    public function children(Request $request)
    {
        return $this->success($this->parentAppService->children($request->user()), 'تم جلب قائمة الأبناء بنجاح');
    }

    /**
     * A specific child's exam results — authorized by the `parent.student`
     * middleware (403 if student_id isn't linked to this parent).
     */
    public function examAttempts(Request $request, int $student_id)
    {
        $attempts = $this->examAttemptService->listForUser(
            User::findOrFail($student_id),
            $request->integer('exam_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($attempts, 'تم جلب نتائج الطالب بنجاح');
    }

    public function examAttempt(int $student_id, int $id)
    {
        $attempt = $this->examAttemptService->findForUser(User::findOrFail($student_id), $id);

        return $this->success($attempt, 'تم جلب تفاصيل النتيجة بنجاح');
    }
}
