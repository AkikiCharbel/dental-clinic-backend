<?php

declare(strict_types=1);

use function Pest\Laravel\getJson;

describe('Health Check Endpoints', function (): void {
    it('returns healthy status on basic health check', function (): void {
        $response = getJson('/api/v1/health');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Application is healthy',
                'data' => [
                    'status' => 'healthy',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['status', 'version', 'environment'],
                'meta' => ['request_id', 'timestamp'],
            ]);
    });

    it('includes request ID in response header', function (): void {
        $response = getJson('/api/v1/health');

        $response->assertOk()
            ->assertHeader('X-Request-ID');
    });

    it('accepts custom request ID from header', function (): void {
        $customRequestId = 'custom-request-id-12345';

        $response = getJson('/api/v1/health', ['X-Request-ID' => $customRequestId]);

        $response->assertOk()
            ->assertHeader('X-Request-ID', $customRequestId);

        $responseData = $response->json();
        expect($responseData['meta']['request_id'])->toBe($customRequestId);
    });
});
