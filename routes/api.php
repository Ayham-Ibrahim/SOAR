<?php

use App\Http\Controllers\Admin\ParentController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GovernorateController;
use App\Http\Controllers\SchoolController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('confirm-registration', [AuthController::class, 'confirmRegistration']);

    Route::post('login', [AuthController::class, 'login']);
    Route::post('confirm-login', [AuthController::class, 'confirmLogin']);

    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('confirm-forgot-password', [AuthController::class, 'confirmForgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::post('resend-otp', [AuthController::class, 'resendOTP']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::delete('account', [AuthController::class, 'deleteAccount']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Reference data: readable by any authenticated user (students browse schools/branches/categories).
    Route::get('governorates', [GovernorateController::class, 'index']);
    Route::apiResource('schools', SchoolController::class)->only(['index', 'show']);
    Route::apiResource('branches', BranchController::class)->only(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
});

Route::middleware(['auth:sanctum', CheckAbilities::class.':dashboard'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('students', StudentController::class);
        Route::apiResource('parents', ParentController::class);
    });

Route::middleware(['auth:sanctum', CheckAbilities::class.':dashboard'])->group(function () {
    Route::apiResource('schools', SchoolController::class)->except(['index', 'show']);
    Route::apiResource('branches', BranchController::class)->except(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
});
