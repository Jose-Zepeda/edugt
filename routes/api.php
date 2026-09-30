<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LessonController;
use App\Http\Controllers\Api\V1\MissionController;
use App\Http\Controllers\Api\V1\ProgressController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — solo lectura pública + health
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'edugt-backend',
        'time' => now()->toIso8601String(),
    ]))->name('api.health');
});
