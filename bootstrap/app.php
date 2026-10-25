<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'parent' => \App\Http\Middleware\EnsureParentAccount::class,
            'parent.student' => \App\Http\Middleware\EnsureStudentLinkedToParent::class,
            'student' => \App\Http\Middleware\EnsureStudentAccount::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // R2 errors carry bucket paths and endpoints: log them, never show them.
        $exceptions->render(function (\Aws\Exception\AwsException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'تعذر الاتصال بخدمة تخزين الفيديو. يرجى المحاولة مرة أخرى.',
                ], 502);
            }
        });
    })->create();
