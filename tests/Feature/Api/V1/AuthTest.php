<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

describe('Authentication API', function (): void {
    describe('POST /api/v1/auth/login', function (): void {
        it('successfully logs in with valid credentials', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => ['id', 'first_name', 'last_name', 'email', 'primary_role'],
                        'tenant' => ['id', 'name', 'slug'],
                        'token',
                    ],
                    'meta' => ['timestamp', 'request_id'],
                ]);

            expect($response->json('success'))->toBeTrue();
            expect($response->json('data.token'))->not->toBeEmpty();
        });

        it('returns error for invalid credentials', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertStatus(422);
            expect($response->json('success'))->toBeFalse();
            expect($response->json('error_code'))->toBe('INVALID_CREDENTIALS');
        });

        it('returns error for non-existent user', function (): void {
            $tenant = Tenant::factory()->create();

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'password123',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertStatus(422);
            expect($response->json('error_code'))->toBe('INVALID_CREDENTIALS');
        });

        it('returns error for inactive user', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->inactive()->create([
                'email' => 'inactive@example.com',
                'password' => bcrypt('password123'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'inactive@example.com',
                'password' => 'password123',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertStatus(403);
            expect($response->json('error_code'))->toBe('USER_INACTIVE');
        });

        it('returns error for inactive tenant', function (): void {
            $tenant = Tenant::factory()->inactive()->create();
            $user = User::factory()->forTenant($tenant)->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertStatus(403);
            expect($response->json('error_code'))->toBe('TENANT_INACTIVE');
        });

        it('returns error for wrong tenant_id', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant1)->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
                'tenant_id' => $tenant2->id,
            ]);

            $response->assertStatus(422);
            expect($response->json('error_code'))->toBe('INVALID_CREDENTIALS');
        });

        it('validates required fields', function (): void {
            $response = $this->postJson('/api/v1/auth/login', []);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['email', 'password', 'tenant_id']);
        });

        it('updates last login timestamp on successful login', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
                'last_login_at' => null,
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
                'tenant_id' => $tenant->id,
            ]);

            $response->assertOk();

            $user->refresh();
            expect($user->last_login_at)->not->toBeNull();
        });
    });

    describe('POST /api/v1/auth/logout', function (): void {
        it('successfully logs out authenticated user', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create();
            $token = $user->createToken('test-token');

            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$token->plainTextToken,
            ])->postJson('/api/v1/auth/logout');

            $response->assertOk();
            expect($response->json('success'))->toBeTrue();
            expect($response->json('message'))->toBe('Successfully logged out');

            // Token should be revoked
            $this->assertDatabaseMissing('personal_access_tokens', [
                'id' => $token->accessToken->id,
            ]);
        });

        it('requires authentication', function (): void {
            $response = $this->postJson('/api/v1/auth/logout');

            $response->assertUnauthorized();
        });
    });

    describe('GET /api/v1/auth/me', function (): void {
        it('returns authenticated user profile', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->dentist()->create();
            $token = $user->createToken('test-token');

            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$token->plainTextToken,
            ])->getJson('/api/v1/auth/me');

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'first_name',
                            'last_name',
                            'email',
                            'primary_role',
                            'is_admin',
                            'is_provider',
                        ],
                        'tenant' => [
                            'id',
                            'name',
                            'slug',
                        ],
                    ],
                ]);

            expect($response->json('data.user.id'))->toBe($user->id);
            expect($response->json('data.user.is_provider'))->toBeTrue();
        });

        it('requires authentication', function (): void {
            $response = $this->getJson('/api/v1/auth/me');

            $response->assertUnauthorized();
        });
    });

    describe('POST /api/v1/auth/refresh', function (): void {
        it('successfully refreshes token', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create();
            $oldToken = $user->createToken('test-token');
            $oldTokenId = $oldToken->accessToken->id;

            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$oldToken->plainTextToken,
            ])->postJson('/api/v1/auth/refresh');

            $response->assertOk();
            expect($response->json('success'))->toBeTrue();
            expect($response->json('data.token'))->not->toBeEmpty();

            // Old token should be deleted
            $this->assertDatabaseMissing('personal_access_tokens', [
                'id' => $oldTokenId,
            ]);
        });

        it('requires authentication', function (): void {
            $response = $this->postJson('/api/v1/auth/refresh');

            $response->assertUnauthorized();
        });
    });
});
