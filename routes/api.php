<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassroomController;
use App\Http\Controllers\Api\V1\CurriculumController;
use App\Http\Controllers\Api\V1\DiagnosticController;
use App\Http\Controllers\Api\V1\GamificationController;
use App\Http\Controllers\Api\V1\QuizController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — EduGT (§7 del plan de arquitectura backend)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'edugt-backend',
        'time' => now()->toIso8601String(),
    ]))->name('api.health');

    // ---- Auth (RF-1, RF-2) ----
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:register')  // named limiter: 1/min por IP
            ->name('auth.register');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')    // named limiter: 5/min por email+IP (§10.3)
            ->name('auth.login');

        Route::get('/google/redirect', [AuthController::class, 'googleRedirect'])
            ->name('auth.google.redirect');

        Route::get('/google/callback', [AuthController::class, 'googleCallback'])
            ->name('auth.google.callback');

        Route::post('/google/complete', [AuthController::class, 'googleComplete'])
            ->middleware('throttle:register')
            ->name('auth.google.complete');
    });

    // ---- Autenticado (Sanctum) ----
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // ---- Curriculum (RF-8, RF-9, RF-10) ----
        Route::get('/me/missions', [CurriculumController::class, 'missions'])->name('curriculum.missions');
        Route::get('/lessons/{id}', [CurriculumController::class, 'showLesson'])->name('curriculum.lessons.show');
        Route::post('/lessons/{id}/complete', [GamificationController::class, 'completeLesson'])
            ->name('curriculum.lessons.complete');

        // ---- Gamificación (RF-17) ----
        Route::get('/me/xp', [GamificationController::class, 'xp'])->name('gamification.xp');
        Route::get('/me/streak', [GamificationController::class, 'streak'])->name('gamification.streak');
        Route::get('/me/badges', [GamificationController::class, 'badges'])->name('gamification.badges');
        Route::get('/rankings', [GamificationController::class, 'rankings'])->name('gamification.rankings');

        // ---- Diagnostic (RF-6, RF-7, RF-28) ----
        Route::get('/diagnostic/tests/{kind}', [DiagnosticController::class, 'show'])
            ->where('kind', 'pre|post')->name('diagnostic.show');
        Route::post('/diagnostic/tests/{kind}/answers', [DiagnosticController::class, 'answer'])
            ->where('kind', 'pre|post')->name('diagnostic.answer');
        Route::post('/diagnostic/tests/{kind}/submit', [DiagnosticController::class, 'submit'])
            ->where('kind', 'pre|post')->name('diagnostic.submit');

        // ---- Quizzes (RF-11..RF-16) ----
        Route::get('/quizzes/{id}', [QuizController::class, 'show'])->name('quiz.show');
        Route::post('/quizzes/{id}/attempts', [QuizController::class, 'start'])->name('quiz.start');
        Route::post('/quizzes/{id}/attempts/{aid}/answers', [QuizController::class, 'answer'])->name('quiz.answer');
        Route::post('/quizzes/{id}/attempts/{aid}/submit', [QuizController::class, 'submit'])->name('quiz.submit');
        Route::get('/quizzes/{id}/attempts', [QuizController::class, 'history'])->name('quiz.history');

        // ---- Aulas (RF-19) ----
        Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classroom.store');
        Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classroom.index');
        Route::post('/classrooms/join', [ClassroomController::class, 'join'])->name('classroom.join');
        Route::get('/classrooms/{id}/students', [ClassroomController::class, 'students'])->name('classroom.students');
        Route::post('/classrooms/{id}/students/{studentId}/state', [ClassroomController::class, 'setStudentState'])
            ->name('classroom.student.state');
        Route::post('/classrooms/{id}/codes/regenerate', [ClassroomController::class, 'regenerateCode'])
            ->name('classroom.codes.regenerate');

        // ---- Analítica y reportes (RF-20, RF-21, RF-22) ----
        Route::get('/classrooms/{id}/analytics', [ReportController::class, 'classroomAnalytics'])
            ->name('reports.analytics');
        Route::post('/reports', [ReportController::class, 'generate'])->name('reports.generate');

        // ---- Admin (RF-4, RB-02, RB-09) ----
        Route::prefix('admin')->group(function (): void {
            Route::get('/teachers', [AdminController::class, 'pendingTeachers'])
                ->name('admin.teachers.pending');
            Route::post('/teachers/{id}/approve', [AdminController::class, 'approveTeacher'])
                ->name('admin.teachers.approve');
            Route::post('/teachers/{id}/reject', [AdminController::class, 'rejectTeacher'])
                ->name('admin.teachers.reject');
        });
    });
});
