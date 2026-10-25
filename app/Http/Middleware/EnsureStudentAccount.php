<?php

namespace App\Http\Middleware;

use App\Models\ParentModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The counterpart of EnsureParentAccount: blocks a parent token from
 * student-only endpoints (exam attempts, subscriptions, parent requests),
 * which act on the signed-in account as the student. Pass "subscription"
 * as the parameter for the purchase endpoints' own message.
 */
class EnsureStudentAccount
{
    private const MESSAGES = [
        'subscription' => 'الاشتراك يتم من حساب الطالب',
    ];

    public function handle(Request $request, Closure $next, ?string $action = null): Response
    {
        if ($request->user() instanceof ParentModel) {
            return response()->json([
                'status' => 'error',
                'message' => self::MESSAGES[$action] ?? 'هذا الإجراء متاح لحسابات الطلاب فقط',
            ], 403);
        }

        return $next($request);
    }
}
