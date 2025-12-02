# Multi-Tenancy Guide

## Overview

The Dental Clinic Backend implements **row-level multi-tenancy** where all tenant data is stored in shared tables with a `tenant_id` foreign key. This approach provides:

- Simple database management (single schema)
- Easy cross-tenant queries for admin operations
- Efficient resource utilization
- Straightforward backups and migrations

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         TENANT ISOLATION MODEL                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   Clinic A (tenant_id: 1)          Clinic B (tenant_id: 2)                 │
│   ┌───────────────────────┐        ┌───────────────────────┐               │
│   │ Patients              │        │ Patients              │               │
│   │ Appointments          │        │ Appointments          │               │
│   │ Treatments            │        │ Treatments            │               │
│   │ Invoices              │        │ Invoices              │               │
│   │ Staff                 │        │ Staff                 │               │
│   └───────────────────────┘        └───────────────────────┘               │
│              │                                │                             │
│              └────────────────┬───────────────┘                             │
│                               │                                             │
│                               ▼                                             │
│                    ┌─────────────────────┐                                  │
│                    │   Shared Database   │                                  │
│                    │   (PostgreSQL)      │                                  │
│                    │                     │                                  │
│                    │ WHERE tenant_id = ? │                                  │
│                    └─────────────────────┘                                  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Tenant Resolution

### Middleware: `ResolveTenant`

Location: `app/Http/Middleware/ResolveTenant.php`

The middleware resolves tenants using three strategies (in priority order):

#### 1. X-Tenant-ID Header (API Clients)

```http
GET /api/v1/patients HTTP/1.1
X-Tenant-ID: 123
Authorization: Bearer {token}
```

Best for: API integrations, mobile apps, third-party systems

#### 2. Subdomain Extraction (Web Clients)

```
https://clinicname.dentalclinic.com/api/v1/patients
        └──────┬─────┘
           subdomain = tenant slug
```

Configuration required:
```bash
# .env
APP_DOMAIN=dentalclinic.com
```

Best for: White-label solutions, branded URLs

#### 3. User's Default Tenant (Authenticated Users)

```php
// Falls back to authenticated user's tenant_id
$user->tenant_id
```

Best for: Users who belong to a single tenant

---

## Configuration

### Environment Variables

```bash
# .env

# Main application domain for subdomain extraction
APP_DOMAIN=dentalclinic.com

# Stateful domains for Sanctum (include tenant subdomains)
SANCTUM_STATEFUL_DOMAINS=*.dentalclinic.com,localhost
```

### Route Setup

```php
// routes/api.php

// Public routes (no tenant required)
Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'index']);
});

// Tenant-scoped routes
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'tenant'])
    ->group(function () {
        Route::apiResource('patients', PatientController::class);
        Route::apiResource('appointments', AppointmentController::class);
    });
```

---

## BelongsToTenant Trait

### Location: `app/Traits/BelongsToTenant.php`

### Usage

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'email',
        'phone',
        // Note: tenant_id is NOT in fillable (auto-set)
    ];
}
```

### Features

#### Automatic Scoping

```php
// All queries automatically filtered by tenant
Patient::all();  // WHERE tenant_id = {current_tenant_id}

Patient::where('status', 'active')->get();
// WHERE tenant_id = {current_tenant_id} AND status = 'active'
```

#### Auto-Set tenant_id

```php
// tenant_id automatically set on creation
$patient = Patient::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
// $patient->tenant_id is automatically set to current tenant
```

#### Bypass Scoping (Admin Operations)

```php
// For cross-tenant operations (admin dashboards, reporting)
Patient::withoutTenantScope()->get();
// Returns ALL patients across ALL tenants

Patient::withoutTenantScope()
    ->where('created_at', '>=', now()->subMonth())
    ->count();
// Count all patients created this month (all tenants)
```

---

## Database Schema

### Migration Pattern

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            // ALWAYS include tenant_id
            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Model fields
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // ALWAYS index tenant_id with commonly queried columns
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'created_at']);

            // Unique constraints should include tenant_id
            $table->unique(['tenant_id', 'email']);
        });
    }
};
```

### Index Strategy

```php
// Good: Tenant-first composite indexes
$table->index(['tenant_id', 'status']);
$table->index(['tenant_id', 'created_at']);
$table->index(['tenant_id', 'appointment_date']);

// Good: Unique constraints within tenant
$table->unique(['tenant_id', 'email']);
$table->unique(['tenant_id', 'invoice_number']);

// Bad: Indexes without tenant_id (rarely useful)
$table->index(['status']);  // Will scan all tenants
```

---

## Helper Functions

### Location: `app/helpers.php`

```php
// Get current tenant instance
$tenant = tenant();
if ($tenant) {
    echo $tenant->name;
}

// Get current tenant ID
$tenantId = tenant_id();
if ($tenantId) {
    // Use in queries or logging
}
```

### Safe Usage

```php
// Always check for null in non-tenant contexts
public function handle(): void
{
    $tenant = tenant();

    if ($tenant === null) {
        // Handle no-tenant context (admin, CLI, etc.)
        return;
    }

    // Proceed with tenant-scoped operation
}
```

---

## Testing with Tenants

### Test Helpers

Location: `tests/Pest.php`

```php
// Create a tenant for testing
$tenant = createTenant();
$tenant = createTenant(['name' => 'Test Clinic']);

// Create a user with tenant
$user = createUser();  // Creates user with new tenant
$user = createUser(['name' => 'Dr. Smith'], $tenant);  // Specific tenant

// Act as user (sets tenant context)
actingAsUser($user);

// Act as tenant directly
actingAsTenant($tenant);
```

### Test Example

```php
<?php

declare(strict_types=1);

use App\Models\Patient;

describe('Patient Management', function () {
    beforeEach(function () {
        $this->user = createUser();
        actingAsUser($this->user);
    });

    it('creates a patient for current tenant', function () {
        $response = $this->postJson('/api/v1/patients', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $response->assertCreated();

        $patient = Patient::first();
        expect($patient->tenant_id)->toBe($this->user->tenant_id);
    });

    it('cannot access another tenant\'s patients', function () {
        // Create patient in different tenant
        $otherTenant = createTenant();
        $otherPatient = Patient::factory()
            ->for($otherTenant)
            ->create();

        // Try to access it
        $response = $this->getJson("/api/v1/patients/{$otherPatient->id}");

        $response->assertNotFound();
    });
});
```

---

## Common Patterns

### Service Layer with Tenant Context

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Patient;
use App\Models\Tenant;

final class PatientService
{
    public function __construct(
        private readonly ?Tenant $tenant = null,
    ) {
        $this->tenant = $tenant ?? tenant();
    }

    public function listPatients(): Collection
    {
        // Tenant scoping happens automatically
        return Patient::query()
            ->orderBy('name')
            ->get();
    }

    public function createPatient(array $data): Patient
    {
        // tenant_id set automatically by BelongsToTenant trait
        return Patient::create($data);
    }
}
```

### Cross-Tenant Admin Operations

```php
<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Patient;

final class AdminReportService
{
    public function getAllPatientsCount(): int
    {
        return Patient::withoutTenantScope()->count();
    }

    public function getPatientsByTenant(): Collection
    {
        return Patient::withoutTenantScope()
            ->selectRaw('tenant_id, COUNT(*) as patient_count')
            ->groupBy('tenant_id')
            ->get();
    }
}
```

### Queue Jobs with Tenant Context

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessPatientReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $tenantId,
        private readonly int $patientId,
    ) {}

    public function handle(): void
    {
        // Manually set tenant context for queue jobs
        $tenant = Tenant::findOrFail($this->tenantId);
        app()->instance('currentTenant', $tenant);

        // Now BelongsToTenant queries will work correctly
        $patient = Patient::findOrFail($this->patientId);

        // Process the report...
    }
}

// Dispatch with tenant context
ProcessPatientReportJob::dispatch(
    tenantId: tenant_id(),
    patientId: $patient->id,
);
```

---

## Security Considerations

### Never Trust Client Input for Tenant

```php
// BAD: Never accept tenant_id from request
$patient = Patient::create([
    'tenant_id' => $request->input('tenant_id'),  // DANGEROUS!
    'name' => $request->input('name'),
]);

// GOOD: Let BelongsToTenant handle it
$patient = Patient::create([
    'name' => $request->input('name'),
]);
```

### Validate Tenant Access in Policies

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        // Extra safety check (BelongsToTenant already filters)
        return $user->tenant_id === $patient->tenant_id;
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->tenant_id === $patient->tenant_id
            && $user->hasPermissionTo('edit patients');
    }
}
```

### Form Request Validation

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

class UpdatePatientRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // NEVER allow tenant_id in requests
            'tenant_id' => ['prohibited'],

            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
        ];
    }
}
```

---

## Rate Limiting by Tenant

The application includes tenant-level rate limiting to prevent any single tenant from overwhelming the system:

```php
// In AppServiceProvider

RateLimiter::for('tenant', function (Request $request) {
    $tenantId = tenant_id() ?? $request->header('X-Tenant-ID');

    if ($tenantId) {
        return Limit::perMinute(1000)->by('tenant:'.$tenantId);
    }

    return Limit::perMinute(60)->by($request->ip());
});
```

Apply to routes:

```php
Route::middleware(['auth:sanctum', 'tenant', 'throttle:tenant'])
    ->group(function () {
        // High-volume endpoints
    });
```
