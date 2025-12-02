<?php

declare(strict_types=1);

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\AddRequestId;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
        // Add request ID to all requests
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
        ]);
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
    })->create();
