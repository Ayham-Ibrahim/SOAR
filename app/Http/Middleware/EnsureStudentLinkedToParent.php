<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mandatory on every parent-app endpoint that exposes data for a specific
 * student: confirms the `student_id` being requested (route param, query
 * string, or body — checked in that order) is actually linked to the
 * authenticated parent via the parent_student pivot. Assumes
 * EnsureParentAccount already ran, so $request->user() is a ParentModel.
 */
class EnsureStudentLinkedToParent
{
    public function handle(Request $request, Closure $next): Response
    {
        $studentId = $request->route('student_id') ?? $request->input('student_id');

        if (! $studentId || ! $request->user()->students()->where('users.id', $studentId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'هذا الطالب غير مرتبط بحسابك',
            ], 403);
        }

        return $next($request);
    }
}
