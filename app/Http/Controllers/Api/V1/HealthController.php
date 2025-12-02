<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Health check endpoints for monitoring application status.
 *
 * @tags Health
 */
class HealthController extends Controller
{
    /**
     * Basic health check
     *
     * Returns the application's basic health status, version, and environment.
     *
     * @response 200 scenario="success" {
     *   "success": true,
     *   "message": "Application is healthy",
     *   "data": {
     *     "status": "healthy",
     *     "version": "1.0.0",
     *     "environment": "local"
     *   },
     *   "meta": {
     *     "timestamp": "2024-01-01T00:00:00.000000Z",
     *     "request_id": "uuid"
     *   }
     * }
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'status' => 'healthy',
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
            ],
            message: 'Application is healthy',
        );
    }

    /**
     * Database health check
     *
     * Tests the database connection and returns connection status with latency.
     *
     * @response 200 scenario="success" {
     *   "success": true,
     *   "message": "Database connection is healthy",
     *   "data": {
     *     "status": "connected",
     *     "driver": "pgsql",
     *     "latency_ms": 1.5
     *   },
     *   "meta": {
     *     "timestamp": "2024-01-01T00:00:00.000000Z",
     *     "request_id": "uuid"
     *   }
     * }
     * @response 503 scenario="database_error" {
     *   "success": false,
     *   "message": "Database connection failed",
     *   "error_code": "DATABASE_ERROR",
     *   "errors": {
     *     "database": "Connection refused"
     *   },
     *   "meta": {
     *     "timestamp": "2024-01-01T00:00:00.000000Z",
     *     "request_id": "uuid"
     *   }
     * }
     */
    public function database(): JsonResponse
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ApiResponse::success(
                data: [
                    'status' => 'connected',
                    'driver' => config('database.default'),
                    'latency_ms' => $latency,
                ],
                message: 'Database connection is healthy',
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                message: 'Database connection failed',
                errorCode: 'DATABASE_ERROR',
                errors: ['database' => $e->getMessage()],
                status: 503,
            );
        }
    }

    /**
     * Redis health check
     *
     * Tests the Redis connection and returns connection status with latency.
     *
     * @response 200 scenario="success" {
     *   "success": true,
     *   "message": "Redis connection is healthy",
     *   "data": {
     *     "status": "connected",
     *     "client": "phpredis",
     *     "latency_ms": 0.5
     *   },
     *   "meta": {
     *     "timestamp": "2024-01-01T00:00:00.000000Z",
     *     "request_id": "uuid"
     *   }
     * }
     * @response 503 scenario="redis_error" {
     *   "success": false,
     *   "message": "Redis connection failed",
     *   "error_code": "REDIS_ERROR",
     *   "errors": {
     *     "redis": "Connection refused"
     *   },
     *   "meta": {
     *     "timestamp": "2024-01-01T00:00:00.000000Z",
     *     "request_id": "uuid"
     *   }
     * }
     */
    public function redis(): JsonResponse
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ApiResponse::success(
                data: [
                    'status' => 'connected',
                    'client' => config('database.redis.client'),
                    'latency_ms' => $latency,
                ],
                message: 'Redis connection is healthy',
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                message: 'Redis connection failed',
                errorCode: 'REDIS_ERROR',
                errors: ['redis' => $e->getMessage()],
                status: 503,
            );
        }
    }

    /**
     * Cache health check
     *
     * Tests the cache system by performing a write/read/delete operation.
     *
     * @response 200 scenario="success" {
     *   "success": true,
     *   "message": "Cache is operational",
     *   "data": {
     *     "status": "operational",
     *     "driver": "redis"
     *   },
     *   "meta": {
     *     "timestamp": "2024-01-01T00:00:00.000000Z",
     *     "request_id": "uuid"
     *   }
     * }
     * @response 503 scenario="cache_error" {
     *   "success": false,
     *   "message": "Cache test failed",
     *   "error_code": "CACHE_ERROR",
     *   "errors": {
     *     "cache": "Connection refused"
     *   },
     *   "meta": {
     *     "timestamp": "2024-01-01T00:00:00.000000Z",
     *     "request_id": "uuid"
     *   }
     * }
     */
    public function cache(): JsonResponse
    {
        try {
            $key = 'health_check_'.time();
            $value = 'test_value';

            Cache::put($key, $value, 10);
            $retrieved = Cache::get($key);
            Cache::forget($key);

            if ($retrieved !== $value) {
                return ApiResponse::error(
                    message: 'Cache read/write test failed',
                    errorCode: 'CACHE_ERROR',
                    status: 503,
                );
            }

            return ApiResponse::success(
                data: [
                    'status' => 'operational',
                    'driver' => config('cache.default'),
                ],
                message: 'Cache is operational',
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                message: 'Cache test failed',
                errorCode: 'CACHE_ERROR',
                errors: ['cache' => $e->getMessage()],
                status: 503,
            );
        }
    }

    /**
     * Full system health check
     *
     * Performs health checks on all system components (database, Redis, cache)
     * and returns a comprehensive status report.
     *
     * @response 200 scenario="all_healthy" {
     *   "success": true,
     *   "message": "All systems operational",
     *   "data": {
     *     "status": "healthy",
     *     "checks": {
     *       "database": {"status": "healthy"},
     *       "redis": {"status": "healthy"},
     *       "cache": {"status": "healthy"}
     *     },
     *     "timestamp": "2024-01-01T00:00:00.000000Z"
     *   },
     *   "meta": {
     *     "timestamp": "2024-01-01T00:00:00.000000Z",
     *     "request_id": "uuid"
     *   }
     * }
     * @response 503 scenario="partial_failure" {
     *   "success": true,
     *   "message": "Some systems are unhealthy",
     *   "data": {
     *     "status": "unhealthy",
     *     "checks": {
     *       "database": {"status": "healthy"},
     *       "redis": {"status": "unhealthy", "error": "Connection refused"},
     *       "cache": {"status": "unhealthy", "error": "Connection refused"}
     *     },
     *     "timestamp": "2024-01-01T00:00:00.000000Z"
     *   },
     *   "meta": {
     *     "timestamp": "2024-01-01T00:00:00.000000Z",
     *     "request_id": "uuid"
     *   }
     * }
     */
    public function full(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // Check database
        try {
            DB::select('SELECT 1');
            $checks['database'] = ['status' => 'healthy'];
        } catch (Exception $e) {
            $checks['database'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
            $allHealthy = false;
        }

        // Check Redis
        try {
            Redis::ping();
            $checks['redis'] = ['status' => 'healthy'];
        } catch (Exception $e) {
            $checks['redis'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
            $allHealthy = false;
        }

        // Check Cache
        try {
            $key = 'health_check_full_'.time();
            Cache::put($key, 'test', 10);
            Cache::forget($key);
            $checks['cache'] = ['status' => 'healthy'];
        } catch (Exception $e) {
            $checks['cache'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
            $allHealthy = false;
        }

        $statusCode = $allHealthy ? 200 : 503;
        $message = $allHealthy ? 'All systems operational' : 'Some systems are unhealthy';

        return ApiResponse::success(
            data: [
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
            message: $message,
            status: $statusCode,
        );
    }
}
