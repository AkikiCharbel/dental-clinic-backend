<?php

declare(strict_types=1);

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\ResolveTenant;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add request ID to all requests (first in chain for tracing)
        $middleware->prepend(AddRequestId::class);

        // API middleware configuration
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // Alias middleware for use in routes
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'tenant' => ResolveTenant::class,
        ]);

        // Configure rate limiting throttle
        $middleware->throttleWithRedis();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle Domain Exceptions
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return ApiResponse::error(
                    message: $e->getMessage(),
                    errorCode: $e->getErrorCode(),
                    status: $e->getHttpStatus(),
                );
            }

            return null;
        });

        // Handle Validation Exceptions
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return ApiResponse::validationError(
                    errors: $e->errors(),
                    message: $e->getMessage(),
                );
            }

            return null;
        });

        // Handle Authentication Exceptions
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return ApiResponse::unauthorized(
                    message: $e->getMessage() ?: 'Unauthenticated',
                    errorCode: 'UNAUTHENTICATED',
                );
            }

            return null;
        });

        // Handle Access Denied Exceptions
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return ApiResponse::forbidden(
                    message: $e->getMessage() ?: 'Access denied',
                    errorCode: 'ACCESS_DENIED',
                );
            }

            return null;
        });

        // Handle Model Not Found Exceptions
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $model = class_basename($e->getModel());

                return ApiResponse::notFound(
                    message: "{$model} not found",
                    errorCode: strtoupper($model).'_NOT_FOUND',
                );
            }

            return null;
        });

        // Handle Not Found HTTP Exceptions
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return ApiResponse::notFound(
                    message: $e->getMessage() ?: 'Resource not found',
                    errorCode: 'RESOURCE_NOT_FOUND',
                );
            }

            return null;
        });

        // Handle Too Many Requests Exceptions
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return ApiResponse::error(
                    message: 'Too many requests. Please try again later.',
                    errorCode: 'RATE_LIMIT_EXCEEDED',
                    status: 429,
                );
            }

            return null;
        });

        // Handle Database Query Exceptions (connection failures, deadlocks, etc.)
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                Log::error('Database error', [
                    'request_id' => $request->header('X-Request-ID'),
                    'message' => $e->getMessage(),
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                    'code' => $e->getCode(),
                ]);

                // Don't expose SQL details in production
                $message = app()->isProduction()
                    ? 'A database error occurred. Please try again later.'
                    : $e->getMessage();

                return ApiResponse::error(
                    message: $message,
                    errorCode: 'DATABASE_ERROR',
                    status: 503,
                );
            }

            return null;
        });

        // Handle generic HTTP exceptions
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return ApiResponse::error(
                    message: $e->getMessage() ?: 'An error occurred',
                    errorCode: 'HTTP_ERROR',
                    status: $e->getStatusCode(),
                );
            }

            return null;
        });

        // Handle all other exceptions (catch-all with logging)
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                // Log unexpected exceptions for debugging
                Log::error('Unhandled exception', [
                    'request_id' => $request->header('X-Request-ID'),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => $request->user()?->id,
                ]);

                // Don't expose internal details in production
                $message = app()->isProduction()
                    ? 'An unexpected error occurred. Please try again later.'
                    : $e->getMessage();

                return ApiResponse::serverError(
                    message: $message,
                    errorCode: 'INTERNAL_ERROR',
                );
            }

            return null;
        });
    })->create();
