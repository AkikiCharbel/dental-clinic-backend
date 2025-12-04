<?php

declare(strict_types=1);

use Illuminate\Support\Str;

describe('AddRequestId Middleware', function (): void {
    it('generates a new request ID when none provided', function (): void {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $requestId = $response->headers->get('X-Request-ID');

        expect($requestId)->not->toBeNull();
        expect(Str::isUuid($requestId))->toBeTrue();
    });

    it('uses client-provided request ID when valid UUID', function (): void {
        $clientRequestId = Str::uuid()->toString();

        $response = $this->withHeaders([
            'X-Request-ID' => $clientRequestId,
        ])->getJson('/api/v1/health');

        $response->assertOk();
        expect($response->headers->get('X-Request-ID'))->toBe($clientRequestId);
    });

    it('generates new request ID when client provides invalid UUID', function (): void {
        $response = $this->withHeaders([
            'X-Request-ID' => 'not-a-valid-uuid',
        ])->getJson('/api/v1/health');

        $response->assertOk();
        $requestId = $response->headers->get('X-Request-ID');

        expect($requestId)->not->toBe('not-a-valid-uuid');
        expect(Str::isUuid($requestId))->toBeTrue();
    });

    it('includes request ID in response meta data', function (): void {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $headerRequestId = $response->headers->get('X-Request-ID');
        $bodyRequestId = $response->json('meta.request_id');

        expect($headerRequestId)->toBe($bodyRequestId);
    });

    it('maintains same request ID throughout request lifecycle', function (): void {
        $clientRequestId = Str::uuid()->toString();

        $response = $this->withHeaders([
            'X-Request-ID' => $clientRequestId,
        ])->getJson('/api/v1/health');

        $response->assertOk();

        // Both header and body should have the same ID
        expect($response->headers->get('X-Request-ID'))->toBe($clientRequestId);
        expect($response->json('meta.request_id'))->toBe($clientRequestId);
    });
});
