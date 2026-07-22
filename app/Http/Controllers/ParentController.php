<?php

namespace App\Http\Controllers;

use App\Services\ParentAppService;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function __construct(private readonly ParentAppService $parentAppService)
    {
    }

    public function children(Request $request)
    {
        return $this->success($this->parentAppService->children($request->user()), 'تم جلب قائمة الأبناء بنجاح');
    }

    /**
     * Reference implementation for the mandatory student_id authorization
     * pattern: every parent endpoint exposing data scoped to a specific
     * student (progress, results, watch history, downloads, notifications
     * — none of which exist in this codebase yet) must sit behind the
     * `parent.student` middleware like this route does. This particular
     * endpoint is a minimal stand-in, not a real feature.
     */
    public function studentSummary(Request $request, int $student_id)
    {
        $student = $request->user()->students()->findOrFail($student_id, ['users.id', 'users.name', 'users.phone']);

        return $this->success($student, 'تم جلب بيانات الطالب بنجاح');
    }
}
