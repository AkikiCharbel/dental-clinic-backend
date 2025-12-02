<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    /**
     * Return a success response with data.
     *
     * @param  array<string, mixed>|JsonResource|ResourceCollection|null  $data
     * @param  array<string, mixed>  $meta
     */
    protected function success(
        array|JsonResource|ResourceCollection|null $data = null,
        string $message = 'Success',
        int $statusCode = Response::HTTP_OK,
        array $meta = [],
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        $response['meta']['request_id'] = request()->header('X-Request-ID');
        $response['meta']['timestamp'] = now()->toIso8601String();

        return response()->json($response, $statusCode);
    }

    /**
     * Return a success response for created resources.
     *
     * @param  array<string, mixed>|JsonResource|null  $data
     */
    protected function created(
        array|JsonResource|null $data = null,
        string $message = 'Resource created successfully',
    ): JsonResponse {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return a success response with no content.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Return an error response.
     *
     * @param  array<string, mixed>|null  $errors
     */
    protected function error(
        string $message,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        ?array $errors = null,
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'meta' => [
                'request_id' => request()->header('X-Request-ID'),
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a not found error response.
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return an unauthorized error response.
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden error response.
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a validation error response.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function validationError(
        array $errors,
        string $message = 'Validation failed',
    ): JsonResponse {
        return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Return a server error response.
     */
    protected function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return $this->error($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
