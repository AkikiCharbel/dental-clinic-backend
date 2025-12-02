<?php

declare(strict_types=1);

use App\Http\Middleware\AddRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add request ID to all requests
        $middleware->prepend(AddRequestId::class);

        // API middleware configuration
        $middleware->api(prepend: [
            Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Alias middleware for use in routes
        $middleware->alias([
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render API exceptions as JSON
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                    'meta' => [
                        'request_id' => $request->header('X-Request-ID'),
                        'timestamp' => now()->toIso8601String(),
                    ],
                ], 404);
            }

            return null;
        });
    })->create();
