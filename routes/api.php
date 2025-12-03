<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// API Version 1
Route::prefix('v1')->group(function (): void {
    // Health check endpoints (public)
    Route::prefix('health')->group(function (): void {
        Route::get('/', [HealthController::class, 'index'])->name('api.v1.health');
        Route::get('/database', [HealthController::class, 'database'])->name('api.v1.health.database');
        Route::get('/redis', [HealthController::class, 'redis'])->name('api.v1.health.redis');
        Route::get('/cache', [HealthController::class, 'cache'])->name('api.v1.health.cache');
        Route::get('/full', [HealthController::class, 'full'])->name('api.v1.health.full');
    });

    // Public authentication routes (with rate limiting)
    Route::prefix('auth')->middleware('throttle:auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    });

    // Authenticated routes (require auth, but no tenant context yet)
    Route::middleware(['auth:sanctum'])->group(function (): void {
        // Auth routes that require authentication
        Route::prefix('auth')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::get('/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });

    // Protected tenant-scoped routes (require authentication AND tenant context)
    Route::middleware(['auth:sanctum', ResolveTenant::class])->group(function (): void {
        // Future tenant-scoped routes will be added here
        // Route::apiResource('patients', PatientController::class);
        // Route::apiResource('appointments', AppointmentController::class);
        // Route::apiResource('treatments', TreatmentController::class);
    });
});
