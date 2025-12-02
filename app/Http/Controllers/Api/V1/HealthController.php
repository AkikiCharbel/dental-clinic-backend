<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends ApiController
{
    /**
     * Basic health check endpoint.
     */
    public function index(): JsonResponse
    {
        return $this->success(
            data: [
                'status' => 'healthy',
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
            ],
            message: 'Application is healthy',
        );
    }

    /**
     * Database health check endpoint.
     */
    public function database(): JsonResponse
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return $this->success(
                data: [
                    'status' => 'connected',
                    'driver' => config('database.default'),
                    'latency_ms' => $latency,
                ],
                message: 'Database connection is healthy',
            );
        } catch (Exception $e) {
            return $this->error(
                message: 'Database connection failed',
                statusCode: 503,
                errors: ['database' => $e->getMessage()],
            );
        }
    }

    /**
     * Redis health check endpoint.
     */
    public function redis(): JsonResponse
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return $this->success(
                data: [
                    'status' => 'connected',
                    'client' => config('database.redis.client'),
                    'latency_ms' => $latency,
                ],
                message: 'Redis connection is healthy',
            );
        } catch (Exception $e) {
            return $this->error(
                message: 'Redis connection failed',
                statusCode: 503,
                errors: ['redis' => $e->getMessage()],
            );
        }
    }

    /**
     * Cache health check endpoint.
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
                return $this->error(
                    message: 'Cache read/write test failed',
                    statusCode: 503,
                );
            }

            return $this->success(
                data: [
                    'status' => 'operational',
                    'driver' => config('cache.default'),
                ],
                message: 'Cache is operational',
            );
        } catch (Exception $e) {
            return $this->error(
                message: 'Cache test failed',
                statusCode: 503,
                errors: ['cache' => $e->getMessage()],
            );
        }
    }

    /**
     * Full system health check endpoint.
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

        return $this->success(
            data: [
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
            message: $message,
            statusCode: $statusCode,
        );
    }
}
