<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
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

    // Public authentication routes
    Route::prefix('auth')->group(function (): void {
        // Future authentication routes will be added here
        // Route::post('/login', [AuthController::class, 'login']);
        // Route::post('/register', [AuthController::class, 'register']);
        // Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    });

    // Protected routes (require authentication)
    Route::middleware(['auth:sanctum'])->group(function (): void {
        // Future protected routes will be added here
        // Route::apiResource('patients', PatientController::class);
        // Route::apiResource('appointments', AppointmentController::class);
        // Route::apiResource('treatments', TreatmentController::class);
    });
});
