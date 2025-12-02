<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeSuccessful', function () {
    return $this->toHaveKey('success', true);
});

expect()->extend('toBeError', function () {
    return $this->toHaveKey('success', false);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a tenant for testing.
 *
 * @param  array<string, mixed>  $attributes
 */
function createTenant(array $attributes = []): Tenant
{
    return Tenant::factory()->create($attributes);
}

/**
 * Create a user for testing.
 *
 * @param  array<string, mixed>  $attributes
 */
function createUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/**
 * Create a user and authenticate as that user.
 *
 * @param  array<string, mixed>  $attributes
 */
function actingAsUser(array $attributes = []): User
{
    $user = createUser($attributes);
    test()->actingAs($user);

    return $user;
}

/**
 * Create a user with a tenant and authenticate.
 *
 * @param  array<string, mixed>  $userAttributes
 * @param  array<string, mixed>  $tenantAttributes
 *
 * @return array{user: User, tenant: Tenant}
 */
function actingAsTenant(array $userAttributes = [], array $tenantAttributes = []): array
{
    $tenant = createTenant($tenantAttributes);
    $user = createUser(array_merge(['tenant_id' => $tenant->id], $userAttributes));
    test()->actingAs($user);

    // Bind tenant to container
    app()->instance('currentTenant', $tenant);

    return ['user' => $user, 'tenant' => $tenant];
}

/**
 * Get standard API headers.
 *
 * @return array<string, string>
 */
function apiHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

/**
 * Get API headers with authentication token.
 *
 * @return array<string, string>
 */
function authenticatedHeaders(User $user): array
{
    $token = $user->createToken('test-token')->plainTextToken;

    return array_merge(apiHeaders(), [
        'Authorization' => 'Bearer '.$token,
    ]);
}
