<?php

namespace App\Http\Controllers;

use App\Models\SeenMarker;
use App\Models\User;
use App\Services\ExamAttemptService;
use App\Services\NewContentService;
use App\Services\ParentAppService;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function __construct(
        private readonly ParentAppService $parentAppService,
        private readonly ExamAttemptService $examAttemptService,
        private readonly NewContentService $newContent
    ) {
    }

    /**
     * Each child carries new_results_count: results graded since this
     * parent last opened that child's results.
     */
    public function children(Request $request)
    {
        $children = $this->parentAppService->children(
            $request->user(),
            $request->string('search')->value() ?: null
        );

        $children->each(fn (User $child) => $child->setAttribute(
            'new_results_count',
            $this->newContent->newResultsCount($request->user(), $child)
        ));

        return $this->success($children, 'تم جلب قائمة الأبناء بنجاح');
    }

    /**
     * A specific child's exam results — authorized by the `parent.student`
     * middleware (403 if student_id isn't linked to this parent).
     */
    public function examAttempts(Request $request, int $student_id)
    {
        $student = User::findOrFail($student_id);
        $attempts = $this->examAttemptService->listForUser(
            $student,
            $request->integer('exam_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginateWithData(
            $attempts,
            $this->newContent->summary($this->newContent->newResultsCount($request->user(), $student)),
            'تم جلب نتائج الطالب بنجاح'
        );
    }

    public function seenExamAttempts(Request $request, int $student_id)
    {
        $this->newContent->markSeen($request->user(), SeenMarker::RESULTS, User::findOrFail($student_id));

        return $this->success($this->newContent->summary(0), 'تم تعليم نتائج الطالب كمشاهدة');
    }

    public function examAttempt(int $student_id, int $id)
    {
        $attempt = $this->examAttemptService->findForUser(User::findOrFail($student_id), $id);

        return $this->success($attempt, 'تم جلب تفاصيل النتيجة بنجاح');
    }

    public function subscriptions(Request $request, int $student_id)
    {
        $student = User::findOrFail($student_id);

        return $this->success(
            $this->parentAppService->subscriptionsForStudent($request->user(), $student),
            'تم جلب اشتراكات الطالب بنجاح'
        );
    }

    public function offers(Request $request, int $student_id)
    {
        $student = User::findOrFail($student_id);

        return $this->success(
            $this->parentAppService->offersForStudent($request->user(), $student),
            'تم جلب العروض المشترك بها الطالب بنجاح'
        );
    }

    public function academicDetails(Request $request, int $student_id)
    {
        return $this->success(
            $this->parentAppService->academicDetailsForStudent(
                $request->user(),
                User::findOrFail($student_id)
            ),
            'تم جلب التفاصيل الأكاديمية للطالب بنجاح'
        );
    }
}
