<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

describe('Authentication API', function (): void {
    beforeEach(function (): void {
        // Disable rate limiting for authentication tests
        $this->withoutMiddleware([
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
        ]);

        // Clear rate limiter for clean test state
        RateLimiter::clear('auth');
    });

    describe('POST /api/v1/auth/login', function (): void {
        it('logs in a user with valid credentials', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => ['id', 'email', 'name', 'primary_role'],
                        'tenant' => ['id', 'name', 'slug'],
                        'token',
                    ],
                    'message',
                ]);

            expect($response->json('success'))->toBeTrue();
            expect($response->json('data.token'))->not->toBeNull();
        });

        it('returns error for invalid email', function (): void {
            $tenant = Tenant::factory()->create();

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'password',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertStatus(403);
            expect($response->json('error_code'))->toBe('AUTH_INVALID_CREDENTIALS');
        });

        it('returns error for invalid password', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create([
                'email' => 'test@example.com',
                'password' => Hash::make('correctpassword'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertStatus(403);
            expect($response->json('error_code'))->toBe('AUTH_INVALID_CREDENTIALS');
        });

        it('returns error for wrong tenant', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant1)->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
            ]);

            // Try to login with different tenant
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
                'tenant_id' => $tenant2->id,
            ]);

            $response->assertStatus(403);
            expect($response->json('error_code'))->toBe('AUTH_INVALID_CREDENTIALS');
        });

        it('returns error for inactive user', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->inactive()->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertStatus(403);
            expect($response->json('error_code'))->toBe('AUTH_USER_INACTIVE');
        });

        it('returns error for inactive tenant', function (): void {
            $tenant = Tenant::factory()->inactive()->create();
            $user = User::factory()->forTenant($tenant)->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertStatus(403);
        });

        it('returns error for tenant with expired subscription', function (): void {
            $tenant = Tenant::factory()->expired()->create();
            $user = User::factory()->forTenant($tenant)->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertStatus(403);
            expect($response->json('error_code'))->toBe('AUTH_TENANT_INACTIVE');
        });

        it('updates last login timestamp on successful login', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'last_login_at' => null,
            ]);

            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
                'tenant_id' => $tenant->id,
            ]);

            $user->refresh();
            expect($user->last_login_at)->not->toBeNull();
        });

        it('requires all fields', function (): void {
            $response = $this->postJson('/api/v1/auth/login', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password', 'tenant_id']);
        });
    });

    describe('POST /api/v1/auth/logout', function (): void {
        it('logs out an authenticated user', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create();
            $token = $user->createToken('api-token')->plainTextToken;

            $response = $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/auth/logout');

            $response->assertOk();
            expect($response->json('success'))->toBeTrue();

            // Token should be revoked
            expect($user->tokens()->count())->toBe(0);
        });

        it('returns error for unauthenticated request', function (): void {
            $response = $this->postJson('/api/v1/auth/logout');

            $response->assertStatus(401);
        });
    });

    describe('GET /api/v1/auth/me', function (): void {
        it('returns the authenticated user', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->dentist()->create();
            $token = $user->createToken('api-token')->plainTextToken;

            $response = $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/v1/auth/me');

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'email',
                            'name',
                            'first_name',
                            'last_name',
                            'primary_role',
                            'is_admin',
                            'is_provider',
                        ],
                        'tenant' => [
                            'id',
                            'name',
                            'slug',
                            'subscription_status',
                        ],
                    ],
                ]);

            expect($response->json('data.user.id'))->toBe($user->id);
            expect($response->json('data.user.is_provider'))->toBeTrue();
            expect($response->json('data.tenant.id'))->toBe($tenant->id);
        });

        it('returns error for unauthenticated request', function (): void {
            $response = $this->getJson('/api/v1/auth/me');

            $response->assertStatus(401);
        });
    });
});
