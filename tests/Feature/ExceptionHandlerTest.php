<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleViolationException;
use App\Exceptions\Domain\InvalidArgumentException;
use App\Exceptions\Domain\OperationFailedException;
use App\Exceptions\Domain\ResourceConflictException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Exceptions\Domain\ValidationException;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // Register test routes that throw different exceptions
    Route::get('/test/not-found', fn () => throw new ResourceNotFoundException('Test resource not found', 'TEST_NOT_FOUND'))
        ->middleware('api');

    Route::get('/test/unauthorized', fn () => throw new UnauthorizedActionException('Test unauthorized', 'TEST_UNAUTHORIZED'))
        ->middleware('api');

    Route::get('/test/conflict', fn () => throw new ResourceConflictException('Test conflict', 'TEST_CONFLICT'))
        ->middleware('api');

    Route::get('/test/business-rule', fn () => throw new BusinessRuleViolationException('Test business rule violation', 'TEST_BUSINESS_RULE'))
        ->middleware('api');

    Route::get('/test/invalid-argument', fn () => throw new InvalidArgumentException('Test invalid argument', 'TEST_INVALID_ARG'))
        ->middleware('api');

    Route::get('/test/validation', fn () => throw new ValidationException('Test validation failed', 'TEST_VALIDATION'))
        ->middleware('api');

    Route::get('/test/operation-failed', fn () => throw new OperationFailedException('Test operation failed', 'TEST_OPERATION'))
        ->middleware('api');
});

describe('Exception Handler', function (): void {
    describe('Domain Exceptions', function (): void {
        it('handles ResourceNotFoundException with 404 status', function (): void {
            $response = $this->getJson('/test/not-found');

            $response->assertNotFound()
                ->assertJson([
                    'success' => false,
                    'message' => 'Test resource not found',
                    'error_code' => 'TEST_NOT_FOUND',
                ]);
        });

        it('handles UnauthorizedActionException with 403 status', function (): void {
            $response = $this->getJson('/test/unauthorized');

            $response->assertForbidden()
                ->assertJson([
                    'success' => false,
                    'message' => 'Test unauthorized',
                    'error_code' => 'TEST_UNAUTHORIZED',
                ]);
        });

        it('handles ResourceConflictException with 409 status', function (): void {
            $response = $this->getJson('/test/conflict');

            $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'Test conflict',
                    'error_code' => 'TEST_CONFLICT',
                ]);
        });

        it('handles BusinessRuleViolationException with 422 status', function (): void {
            $response = $this->getJson('/test/business-rule');

            $response->assertUnprocessable()
                ->assertJson([
                    'success' => false,
                    'message' => 'Test business rule violation',
                    'error_code' => 'TEST_BUSINESS_RULE',
                ]);
        });

        it('handles InvalidArgumentException with 400 status', function (): void {
            $response = $this->getJson('/test/invalid-argument');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'message' => 'Test invalid argument',
                    'error_code' => 'TEST_INVALID_ARG',
                ]);
        });

        it('handles ValidationException with 422 status', function (): void {
            $response = $this->getJson('/test/validation');

            $response->assertUnprocessable()
                ->assertJson([
                    'success' => false,
                    'message' => 'Test validation failed',
                    'error_code' => 'TEST_VALIDATION',
                ]);
        });

        it('handles OperationFailedException with 500 status', function (): void {
            $response = $this->getJson('/test/operation-failed');

            $response->assertServerError()
                ->assertJson([
                    'success' => false,
                    'message' => 'Test operation failed',
                    'error_code' => 'TEST_OPERATION',
                ]);
        });
    });

    describe('Response Format', function (): void {
        it('includes request ID in error responses', function (): void {
            $response = $this->getJson('/test/not-found');

            $response->assertNotFound();
            expect($response->json('meta.request_id'))->not->toBeNull();
        });

        it('includes timestamp in error responses', function (): void {
            $response = $this->getJson('/test/not-found');

            $response->assertNotFound();
            expect($response->json('meta.timestamp'))->not->toBeNull();
        });

        it('has consistent error response structure', function (): void {
            $response = $this->getJson('/test/not-found');

            $response->assertNotFound()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'error_code',
                    'meta' => [
                        'timestamp',
                        'request_id',
                    ],
                ]);
        });
    });

    describe('Authentication Exceptions', function (): void {
        it('returns 401 for unauthenticated requests to protected routes', function (): void {
            $response = $this->getJson('/api/v1/auth/me');

            $response->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'error_code' => 'UNAUTHENTICATED',
                ]);
        });
    });

    describe('Model Not Found Exceptions', function (): void {
        it('returns 404 when model is not found', function (): void {
            $tenant = createTenant();
            $user = createUser(['tenant_id' => $tenant->id]);
            $fakeUuid = '00000000-0000-0000-0000-000000000001';

            // Try to access non-existent resource via header
            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
                'X-Tenant-ID' => $fakeUuid,
            ])->getJson('/api/v1/auth/me');

            $response->assertNotFound();
        });
    });
});
