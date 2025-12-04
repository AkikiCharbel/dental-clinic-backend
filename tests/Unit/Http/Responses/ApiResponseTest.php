<?php

declare(strict_types=1);

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

describe('ApiResponse', function (): void {
    describe('success()', function (): void {
        it('returns success response with data', function (): void {
            $data = ['name' => 'Test', 'value' => 123];

            $response = ApiResponse::success($data, 'Operation completed');

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(Response::HTTP_OK);

            $content = $response->getData(true);
            expect($content['success'])->toBeTrue();
            expect($content['message'])->toBe('Operation completed');
            expect($content['data'])->toBe($data);
            expect($content['meta'])->toHaveKeys(['timestamp', 'request_id']);
        });

        it('returns success response without data', function (): void {
            $response = ApiResponse::success(null, 'Success');

            $content = $response->getData(true);
            expect($content['success'])->toBeTrue();
            expect($content['data'])->toBeNull();
        });

        it('accepts custom status code', function (): void {
            $response = ApiResponse::success(['test' => true], 'Created', Response::HTTP_ACCEPTED);

            expect($response->getStatusCode())->toBe(Response::HTTP_ACCEPTED);
        });

        it('includes timestamp in ISO8601 format', function (): void {
            $response = ApiResponse::success();

            $content = $response->getData(true);
            expect($content['meta']['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
        });
    });

    describe('created()', function (): void {
        it('returns 201 status code', function (): void {
            $response = ApiResponse::created(['id' => 1]);

            expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);
        });

        it('has default created message', function (): void {
            $response = ApiResponse::created();

            $content = $response->getData(true);
            expect($content['message'])->toBe('Resource created successfully');
        });

        it('accepts custom message', function (): void {
            $response = ApiResponse::created(['id' => 1], 'User created');

            $content = $response->getData(true);
            expect($content['message'])->toBe('User created');
        });
    });

    describe('error()', function (): void {
        it('returns error response with message and code', function (): void {
            $response = ApiResponse::error('Something went wrong', 'GENERIC_ERROR');

            expect($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);

            $content = $response->getData(true);
            expect($content['success'])->toBeFalse();
            expect($content['message'])->toBe('Something went wrong');
            expect($content['error_code'])->toBe('GENERIC_ERROR');
        });

        it('includes errors array when provided', function (): void {
            $errors = ['field' => ['Field is required']];

            $response = ApiResponse::error('Validation failed', 'VALIDATION', $errors);

            $content = $response->getData(true);
            expect($content['errors'])->toBe($errors);
        });

        it('excludes errors key when empty', function (): void {
            $response = ApiResponse::error('Error', 'CODE');

            $content = $response->getData(true);
            expect(array_key_exists('errors', $content))->toBeFalse();
        });

        it('accepts custom status code', function (): void {
            $response = ApiResponse::error('Not found', 'NOT_FOUND', [], Response::HTTP_NOT_FOUND);

            expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
        });
    });

    describe('notFound()', function (): void {
        it('returns 404 status code', function (): void {
            $response = ApiResponse::notFound();

            expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
        });

        it('has default not found message', function (): void {
            $response = ApiResponse::notFound();

            $content = $response->getData(true);
            expect($content['message'])->toBe('Resource not found');
            expect($content['error_code'])->toBe('RESOURCE_NOT_FOUND');
        });

        it('accepts custom message and error code', function (): void {
            $response = ApiResponse::notFound('User not found', 'USER_NOT_FOUND');

            $content = $response->getData(true);
            expect($content['message'])->toBe('User not found');
            expect($content['error_code'])->toBe('USER_NOT_FOUND');
        });
    });

    describe('unauthorized()', function (): void {
        it('returns 401 status code', function (): void {
            $response = ApiResponse::unauthorized();

            expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
        });

        it('has correct error code', function (): void {
            $response = ApiResponse::unauthorized();

            $content = $response->getData(true);
            expect($content['error_code'])->toBe('UNAUTHORIZED');
        });
    });

    describe('forbidden()', function (): void {
        it('returns 403 status code', function (): void {
            $response = ApiResponse::forbidden();

            expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
        });

        it('has correct error code', function (): void {
            $response = ApiResponse::forbidden();

            $content = $response->getData(true);
            expect($content['error_code'])->toBe('FORBIDDEN');
        });
    });

    describe('validationError()', function (): void {
        it('returns 422 status code', function (): void {
            $errors = ['email' => ['Invalid email format']];

            $response = ApiResponse::validationError($errors);

            expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        it('includes validation errors', function (): void {
            $errors = [
                'email' => ['Invalid email format'],
                'name' => ['Name is required'],
            ];

            $response = ApiResponse::validationError($errors);

            $content = $response->getData(true);
            expect($content['errors'])->toBe($errors);
            expect($content['error_code'])->toBe('VALIDATION_ERROR');
        });
    });

    describe('serverError()', function (): void {
        it('returns 500 status code', function (): void {
            $response = ApiResponse::serverError();

            expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR);
        });

        it('has correct default message', function (): void {
            $response = ApiResponse::serverError();

            $content = $response->getData(true);
            expect($content['message'])->toBe('Internal server error');
            expect($content['error_code'])->toBe('SERVER_ERROR');
        });
    });

    describe('noContent()', function (): void {
        it('returns 204 status code', function (): void {
            $response = ApiResponse::noContent();

            expect($response->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);
        });

        it('has empty or null body content', function (): void {
            $response = ApiResponse::noContent();

            // For 204 No Content, the body should be empty or null
            $content = $response->getContent();
            expect($content === '' || $content === 'null' || $content === '{}')->toBeTrue();
        });
    });

    describe('paginated()', function (): void {
        it('returns paginated response structure', function (): void {
            $items = collect([
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ]);

            $paginator = new LengthAwarePaginator(
                $items,
                total: 10,
                perPage: 2,
                currentPage: 1,
            );

            $response = ApiResponse::paginated($paginator);

            expect($response->getStatusCode())->toBe(Response::HTTP_OK);

            $content = $response->getData(true);
            expect($content['success'])->toBeTrue();
            expect($content['data'])->toHaveKeys(['items', 'pagination', 'links']);
        });

        it('includes correct pagination metadata', function (): void {
            $items = collect([['id' => 1], ['id' => 2]]);

            $paginator = new LengthAwarePaginator(
                $items,
                total: 50,
                perPage: 10,
                currentPage: 2,
            );

            $response = ApiResponse::paginated($paginator);

            $content = $response->getData(true);
            $pagination = $content['data']['pagination'];

            expect($pagination['current_page'])->toBe(2);
            expect($pagination['per_page'])->toBe(10);
            expect($pagination['total'])->toBe(50);
            expect($pagination['last_page'])->toBe(5);
        });

        it('includes pagination links', function (): void {
            $items = collect([['id' => 1]]);

            $paginator = new LengthAwarePaginator(
                $items,
                total: 30,
                perPage: 10,
                currentPage: 2,
            );

            $response = ApiResponse::paginated($paginator);

            $content = $response->getData(true);
            $links = $content['data']['links'];

            expect($links)->toHaveKeys(['first', 'last', 'prev', 'next']);
        });
    });

    describe('Response Structure Consistency', function (): void {
        it('all success responses have same base structure', function (): void {
            $responses = [
                ApiResponse::success(['data' => true]),
                ApiResponse::created(['id' => 1]),
            ];

            foreach ($responses as $response) {
                $content = $response->getData(true);
                expect($content)->toHaveKeys(['success', 'message', 'data', 'meta']);
                expect($content['meta'])->toHaveKeys(['timestamp', 'request_id']);
            }
        });

        it('all error responses have same base structure', function (): void {
            $responses = [
                ApiResponse::error('Error', 'CODE'),
                ApiResponse::notFound(),
                ApiResponse::unauthorized(),
                ApiResponse::forbidden(),
                ApiResponse::serverError(),
            ];

            foreach ($responses as $response) {
                $content = $response->getData(true);
                expect($content)->toHaveKeys(['success', 'message', 'error_code', 'meta']);
                expect($content['success'])->toBeFalse();
            }
        });
    });
});
