<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\Data;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponse
{
    /**
     * Return a success response.
     *
     * @param  array<string, mixed>|Data|null  $data
     */
    public static function success(
        array|Data|null $data = null,
        string $message = 'Success',
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        $responseData = $data instanceof Data ? $data->toArray() : $data;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $responseData,
            'meta' => self::getMeta(),
        ], $status);
    }

    /**
     * Return a created response.
     *
     * @param  array<string, mixed>|Data|null  $data
     */
    public static function created(
        array|Data|null $data = null,
        string $message = 'Resource created successfully',
    ): JsonResponse {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return an error response.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function error(
        string $message,
        string $errorCode = 'ERROR',
        array $errors = [],
        int $status = Response::HTTP_BAD_REQUEST,
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            'meta' => self::getMeta(),
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a paginated response.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  class-string<Data>|null  $dataClass
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'Data retrieved successfully',
        ?string $dataClass = null,
    ): JsonResponse {
        $items = $paginator->items();

        // Transform items using Data class if provided
        if ($dataClass !== null) {
            $items = array_map(
                fn ($item) => $dataClass::from($item)->toArray(),
                $items,
            );
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'meta' => self::getMeta(),
        ], Response::HTTP_OK);
    }

    /**
     * Return a not found error response.
     */
    public static function notFound(
        string $message = 'Resource not found',
        string $errorCode = 'RESOURCE_NOT_FOUND',
    ): JsonResponse {
        return self::error($message, $errorCode, [], Response::HTTP_NOT_FOUND);
    }

    /**
     * Return an unauthorized error response.
     */
    public static function unauthorized(
        string $message = 'Unauthorized',
        string $errorCode = 'UNAUTHORIZED',
    ): JsonResponse {
        return self::error($message, $errorCode, [], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden error response.
     */
    public static function forbidden(
        string $message = 'Forbidden',
        string $errorCode = 'FORBIDDEN',
    ): JsonResponse {
        return self::error($message, $errorCode, [], Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a validation error response.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed',
    ): JsonResponse {
        return self::error($message, 'VALIDATION_ERROR', $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Return a server error response.
     */
    public static function serverError(
        string $message = 'Internal server error',
        string $errorCode = 'SERVER_ERROR',
    ): JsonResponse {
        return self::error($message, $errorCode, [], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Return no content response.
     */
    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get meta information for the response.
     *
     * @return array<string, string|null>
     */
    private static function getMeta(): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
            'request_id' => request()->header('X-Request-ID'),
        ];
    }
}
