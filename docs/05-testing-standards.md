# Testing Standards

## Overview

The application uses **Pest PHP** for testing with a focus on feature tests for API endpoints and unit tests for business logic. All tests must pass PHPStan level 10 static analysis.

---

## Test Structure

```
tests/
├── Feature/
│   └── Api/
│       └── V1/
│           ├── AuthTest.php
│           ├── HealthTest.php
│           ├── PatientTest.php
│           └── AppointmentTest.php
├── Unit/
│   ├── Models/
│   │   └── PatientTest.php
│   ├── Services/
│   │   └── AppointmentServiceTest.php
│   └── Support/
│       └── EncryptionTest.php
├── Traits/
│   └── ValidatesOpenApiSpec.php
├── Pest.php
└── TestCase.php
```

---

## Running Tests

```bash
# Run all tests
php artisan test

# Run with parallel execution
php artisan test --parallel

# Run specific test file
php artisan test tests/Feature/Api/V1/PatientTest.php

# Run specific test
php artisan test --filter="it creates a patient"

# Run with coverage
php artisan test --coverage --min=80

# Run quality suite (pint + phpstan + tests)
composer quality
```

---

## Pest Configuration

### Location: `tests/Pest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Use RefreshDatabase for feature tests
uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

// Custom Expectations
expect()->extend('toBeSuccessful', function () {
    return $this->toBeTrue();
});

expect()->extend('toBeError', function () {
    return $this->toBeFalse();
});

// Helper Functions
function createTenant(array $attributes = []): Tenant
{
    return Tenant::factory()->create($attributes);
}

function createUser(array $attributes = [], ?Tenant $tenant = null): User
{
    $tenant ??= createTenant();

    return User::factory()
        ->for($tenant)
        ->create($attributes);
}

function actingAsUser(?User $user = null): User
{
    $user ??= createUser();
    test()->actingAs($user);
    app()->instance('currentTenant', $user->tenant);

    return $user;
}

function actingAsTenant(Tenant $tenant): Tenant
{
    app()->instance('currentTenant', $tenant);

    return $tenant;
}

function apiHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function authenticatedHeaders(User $user): array
{
    $token = $user->createToken('test-token')->plainTextToken;

    return [
        ...apiHeaders(),
        'Authorization' => "Bearer {$token}",
    ];
}
```

---

## Feature Test Patterns

### API Endpoint Test

```php
<?php

declare(strict_types=1);

use App\Models\Patient;

describe('Patient API', function () {
    beforeEach(function () {
        $this->user = createUser();
        $this->actingAs($this->user);
        actingAsTenant($this->user->tenant);
    });

    describe('GET /api/v1/patients', function () {
        it('returns paginated patients for current tenant', function () {
            // Arrange
            Patient::factory()
                ->for($this->user->tenant)
                ->count(5)
                ->create();

            // Act
            $response = $this->getJson('/api/v1/patients');

            // Assert
            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'items',
                        'pagination' => [
                            'current_page',
                            'per_page',
                            'total',
                        ],
                    ],
                    'meta',
                ])
                ->assertJsonPath('data.pagination.total', 5);
        });

        it('does not return patients from other tenants', function () {
            // Arrange: Create patient in different tenant
            $otherTenant = createTenant();
            Patient::factory()->for($otherTenant)->create();

            // Arrange: Create patient in current tenant
            $myPatient = Patient::factory()
                ->for($this->user->tenant)
                ->create();

            // Act
            $response = $this->getJson('/api/v1/patients');

            // Assert
            $response->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.items.0.id', $myPatient->id);
        });
    });

    describe('POST /api/v1/patients', function () {
        it('creates a patient', function () {
            $response = $this->postJson('/api/v1/patients', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '+1-555-123-4567',
            ]);

            $response->assertCreated()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.name', 'John Doe');

            $this->assertDatabaseHas('patients', [
                'email' => 'john@example.com',
                'tenant_id' => $this->user->tenant_id,
            ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/patients', []);

            $response->assertUnprocessable()
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'VALIDATION_ERROR')
                ->assertJsonValidationErrors(['name', 'email']);
        });

        it('prevents duplicate email within tenant', function () {
            Patient::factory()
                ->for($this->user->tenant)
                ->create(['email' => 'john@example.com']);

            $response = $this->postJson('/api/v1/patients', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['email']);
        });
    });

    describe('GET /api/v1/patients/{patient}', function () {
        it('returns patient details', function () {
            $patient = Patient::factory()
                ->for($this->user->tenant)
                ->create();

            $response = $this->getJson("/api/v1/patients/{$patient->id}");

            $response->assertOk()
                ->assertJsonPath('data.id', $patient->id);
        });

        it('returns 404 for non-existent patient', function () {
            $response = $this->getJson('/api/v1/patients/99999');

            $response->assertNotFound()
                ->assertJsonPath('error_code', 'PATIENT_NOT_FOUND');
        });

        it('returns 404 for patient in different tenant', function () {
            $otherPatient = Patient::factory()
                ->for(createTenant())
                ->create();

            $response = $this->getJson("/api/v1/patients/{$otherPatient->id}");

            $response->assertNotFound();
        });
    });

    describe('DELETE /api/v1/patients/{patient}', function () {
        it('soft deletes patient', function () {
            $patient = Patient::factory()
                ->for($this->user->tenant)
                ->create();

            $response = $this->deleteJson("/api/v1/patients/{$patient->id}");

            $response->assertNoContent();

            $this->assertSoftDeleted('patients', ['id' => $patient->id]);
        });
    });
});
```

---

## Unit Test Patterns

### Service Test

```php
<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleViolationException;
use App\Exceptions\Domain\ResourceConflictException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentService;

describe('AppointmentService', function () {
    beforeEach(function () {
        $this->tenant = createTenant();
        actingAsTenant($this->tenant);

        $this->service = app(AppointmentService::class);
    });

    describe('scheduleAppointment', function () {
        it('creates appointment for valid patient', function () {
            $patient = Patient::factory()
                ->for($this->tenant)
                ->create();

            $appointment = $this->service->scheduleAppointment(
                patientId: $patient->id,
                startTime: now()->addDay()->setHour(10),
                durationMinutes: 30,
            );

            expect($appointment)
                ->toBeInstanceOf(Appointment::class)
                ->patient_id->toBe($patient->id)
                ->duration_minutes->toBe(30);
        });

        it('throws exception for non-existent patient', function () {
            expect(fn () => $this->service->scheduleAppointment(
                patientId: 99999,
                startTime: now()->addDay(),
                durationMinutes: 30,
            ))->toThrow(ResourceNotFoundException::class);
        });

        it('throws exception for past appointment', function () {
            $patient = Patient::factory()
                ->for($this->tenant)
                ->create();

            expect(fn () => $this->service->scheduleAppointment(
                patientId: $patient->id,
                startTime: now()->subHour(),
                durationMinutes: 30,
            ))->toThrow(BusinessRuleViolationException::class);
        });

        it('throws exception for conflicting time slot', function () {
            $patient = Patient::factory()
                ->for($this->tenant)
                ->create();

            // Create existing appointment
            Appointment::factory()
                ->for($patient)
                ->for($this->tenant)
                ->create([
                    'start_time' => now()->addDay()->setHour(10),
                    'end_time' => now()->addDay()->setHour(11),
                ]);

            expect(fn () => $this->service->scheduleAppointment(
                patientId: $patient->id,
                startTime: now()->addDay()->setHour(10)->addMinutes(30),
                durationMinutes: 30,
            ))->toThrow(ResourceConflictException::class);
        });
    });
});
```

### Model Test

```php
<?php

declare(strict_types=1);

use App\Models\Patient;

describe('Patient Model', function () {
    describe('tenant scoping', function () {
        it('automatically sets tenant_id on creation', function () {
            $tenant = createTenant();
            actingAsTenant($tenant);

            $patient = Patient::create([
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);

            expect($patient->tenant_id)->toBe($tenant->id);
        });

        it('filters queries by tenant', function () {
            $tenant1 = createTenant();
            $tenant2 = createTenant();

            Patient::factory()->for($tenant1)->count(3)->create();
            Patient::factory()->for($tenant2)->count(2)->create();

            actingAsTenant($tenant1);

            expect(Patient::count())->toBe(3);
        });
    });

    describe('encryption', function () {
        it('encrypts SSN on save', function () {
            $tenant = createTenant();
            actingAsTenant($tenant);

            $patient = Patient::create([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'ssn' => '123-45-6789',
            ]);

            // Raw value should be encrypted
            expect($patient->ssn_encrypted)
                ->not->toBe('123-45-6789')
                ->toStartWith('eyJ');

            // Accessor should return decrypted value
            expect($patient->ssn)->toBe('123-45-6789');
        });

        it('can search by SSN hash', function () {
            $tenant = createTenant();
            actingAsTenant($tenant);

            Patient::create([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'ssn' => '123-45-6789',
            ]);

            $found = Patient::findBySsn('123-45-6789');

            expect($found)->not->toBeNull()
                ->name->toBe('John Doe');
        });
    });
});
```

---

## OpenAPI Validation

### Location: `tests/Traits/ValidatesOpenApiSpec.php`

Tests can validate responses against the OpenAPI specification:

```php
<?php

declare(strict_types=1);

describe('API Contract', function () {
    beforeEach(function () {
        $this->user = createUser();
        $this->actingAs($this->user);
    });

    it('GET /patients matches OpenAPI spec', function () {
        $response = $this->getJson('/api/v1/patients');

        // Uses ValidatesOpenApiSpec trait
        $this->assertResponseMatchesOpenApiSpec(
            response: $response,
            path: '/api/v1/patients',
            method: 'get',
        );
    });

    it('POST /patients matches OpenAPI spec', function () {
        $response = $this->postJson('/api/v1/patients', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertResponseMatchesOpenApiSpec(
            response: $response,
            path: '/api/v1/patients',
            method: 'post',
        );
    });
});
```

---

## Test Database

### Configuration

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="dental_clinic_test"/>
```

### Using Transactions

```php
// Feature tests use RefreshDatabase trait
uses(RefreshDatabase::class)->in('Feature');

// This:
// 1. Runs migrations once per test suite
// 2. Wraps each test in a transaction
// 3. Rolls back after each test
```

---

## Factories

### Patient Factory Example

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->date('Y-m-d', '-18 years'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withAppointments(int $count = 3): static
    {
        return $this->has(
            Appointment::factory()->count($count),
            'appointments'
        );
    }
}
```

---

## Test Best Practices

### DO

```php
// Use descriptive test names
it('prevents scheduling appointments on holidays', function () {});

// Arrange-Act-Assert pattern
it('creates patient', function () {
    // Arrange
    $data = ['name' => 'John', 'email' => 'john@example.com'];

    // Act
    $response = $this->postJson('/api/v1/patients', $data);

    // Assert
    $response->assertCreated();
});

// Test edge cases
it('handles empty search results', function () {});
it('handles maximum pagination limit', function () {});

// Test error scenarios
it('returns validation errors for invalid input', function () {});
it('returns 404 for non-existent resources', function () {});
```

### DON'T

```php
// Don't test implementation details
it('calls repository method', function () {}); // BAD

// Don't test Laravel framework
it('validates required fields exist', function () {}); // Too low-level

// Don't skip tenant context
Patient::create([...]); // BAD - no tenant context

// Don't use production data
$patient = Patient::find(1); // BAD - use factories
```

---

## Coverage Requirements

- **Minimum**: 80% code coverage
- **Target**: 90%+ for business logic
- **Required**: 100% for security-related code

```bash
# Generate coverage report
php artisan test --coverage --min=80

# HTML report
php artisan test --coverage-html=coverage
```
