<?php

namespace App\Http\Middleware;

use App\Models\ParentModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a student/admin (User) token from hitting parent-only endpoints.
 * Both User and ParentModel authenticate through the same auth:sanctum
 * guard, so the token alone doesn't tell you which one it is — this checks
 * the resolved model.
 */
class EnsureParentAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof ParentModel) {
            return response()->json([
                'status' => 'error',
                'message' => 'هذا الإجراء متاح لحسابات أولياء الأمور فقط',
            ], 403);
        }

        return $next($request);
    }
}
