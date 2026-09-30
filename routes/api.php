<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CurriculumController;
use App\Http\Controllers\Api\V1\DiagnosticController;
use App\Http\Controllers\Api\V1\GamificationController;
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

        // ---- Diagnostic (RF-6, RF-7, RF-28) ----
        Route::get('/diagnostic/tests/{kind}', [DiagnosticController::class, 'show'])
            ->where('kind', 'pre|post')->name('diagnostic.show');
        Route::post('/diagnostic/tests/{kind}/answers', [DiagnosticController::class, 'answer'])
            ->where('kind', 'pre|post')->name('diagnostic.answer');
        Route::post('/diagnostic/tests/{kind}/submit', [DiagnosticController::class, 'submit'])
            ->where('kind', 'pre|post')->name('diagnostic.submit');

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
