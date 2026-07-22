<?php

use App\Http\Controllers\Admin\AdvertisementController;
use App\Http\Controllers\Admin\ChoiceController;
use App\Http\Controllers\Admin\ExamAttemptController as AdminExamAttemptController;
use App\Http\Controllers\Admin\ExamController as AdminExamController;
use App\Http\Controllers\Admin\FileController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\OfferController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\ParentAccountRequestController as AdminParentAccountRequestController;
use App\Http\Controllers\Admin\ParentController as AdminParentController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamAttemptController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\GovernorateController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\ParentAccountRequestController;
use App\Http\Controllers\ParentController as ParentAppController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\SubjectController;
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
    // Reference data & content tree: readable by ANY authenticated user, with
    // NO filtering by student attribute. The platform is open — every student
    // can browse every category/sub-category/subject/course.
    Route::apiResource('governorates', GovernorateController::class)->only(['index']);
    Route::apiResource('schools', SchoolController::class)->only(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('sub-categories', SubCategoryController::class)->only(['index', 'show']);
    Route::apiResource('subjects', SubjectController::class)->only(['index', 'show']);
    Route::apiResource('courses', CourseController::class)->only(['index', 'show']);
    Route::apiResource('news', NewsController::class)->only(['index', 'show']);
    Route::apiResource('exams', ExamController::class)->only(['index', 'show']);
    Route::apiResource('advertisements', AdvertisementController::class)->only(['show']);
    Route::get('advertisements', [AdvertisementController::class,'getAdsUser']);

    // Exam taking & results: scoped to the authenticated student.
    Route::apiResource('exam-attempts', ExamAttemptController::class)->only(['index', 'store', 'show']);

    // A student requesting a parent account be created for them. Reviewed by
    // an admin (see admin/parent-requests below).
    Route::apiResource('student/parent-requests', ParentAccountRequestController::class)->only(['index', 'store']);
});

Route::middleware(['auth:sanctum', 'parent'])->prefix('parent')->group(function () {
    Route::get('children', [ParentAppController::class, 'children']);

    // Reference implementation for the mandatory student_id authorization
    // pattern — see the docblock on ParentController::studentSummary().
    Route::middleware('parent.student')->get('students/{student_id}/summary', [ParentAppController::class, 'studentSummary']);
});

Route::middleware(['auth:sanctum', CheckAbilities::class.':dashboard'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('students', StudentController::class);
        Route::apiResource('parents', AdminParentController::class);
        Route::post('parents/{parent}/students', [AdminParentController::class, 'addStudents']);
        Route::delete('parents/{parent}/students/{studentId}', [AdminParentController::class, 'removeStudent']);
        Route::apiResource('teachers', TeacherController::class);
        Route::apiResource('units', UnitController::class);
        Route::apiResource('lessons', LessonController::class);
        Route::apiResource('videos', VideoController::class);
        Route::apiResource('files', FileController::class);
        Route::apiResource('exams', AdminExamController::class);
        Route::apiResource('questions', QuestionController::class);
        Route::apiResource('choices', ChoiceController::class);
        Route::apiResource('news', AdminNewsController::class);
        Route::apiResource('advertisements', AdvertisementController::class);
        Route::apiResource('packages', PackageController::class);
        Route::apiResource('offers', OfferController::class);

        Route::apiResource('exam-attempts', AdminExamAttemptController::class)->only(['index', 'show']);
        Route::patch('exam-attempts/{exam_attempt}/grade', [AdminExamAttemptController::class, 'grade']);

        Route::apiResource('parent-requests', AdminParentAccountRequestController::class)
            ->parameters(['parent-requests' => 'parent_account_request'])
            ->only(['index', 'show']);
        Route::post('parent-requests/{parent_account_request}/approve', [AdminParentAccountRequestController::class, 'approve']);
        Route::post('parent-requests/{parent_account_request}/reject', [AdminParentAccountRequestController::class, 'reject']);
    });

Route::middleware(['auth:sanctum', CheckAbilities::class.':dashboard'])->group(function () {
    Route::apiResource('schools', SchoolController::class)->except(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
    Route::apiResource('sub-categories', SubCategoryController::class)->except(['index', 'show']);
    Route::apiResource('subjects', SubjectController::class)->except(['index', 'show']);
    Route::apiResource('courses', CourseController::class)->except(['index', 'show']);
});
