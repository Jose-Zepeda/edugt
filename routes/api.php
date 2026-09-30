<?php

use App\Http\Controllers\Api\V1\AuthController;
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
    });
});
