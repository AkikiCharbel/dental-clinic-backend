# Development Conventions

## Overview

This document defines the coding standards, patterns, and conventions for the Dental Clinic Backend. Adherence to these standards ensures consistency, maintainability, and code quality.

---

## Code Style

### PHP Standards

- **PHP Version**: 8.4+
- **Type Declarations**: Strict types required
- **Formatting**: Laravel Pint with custom rules

### Strict Types

Every PHP file must start with:

```php
<?php

declare(strict_types=1);
```

### Pint Configuration

Location: `pint.json`

Key rules enforced:
- `declare_strict_types`
- `ordered_class_elements` (traits → properties → methods)
- `trailing_comma_in_multiline`
- `no_unused_imports`
- `native_function_casing`

### Running Code Style

```bash
# Fix code style
./vendor/bin/pint

# Check without fixing
./vendor/bin/pint --test
```

---

## Static Analysis

### PHPStan Level 10

Configuration: `phpstan.neon`

```yaml
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 10
    paths:
        - app
    checkModelProperties: true
    checkPhpDocMissingReturn: true
```

### Running Analysis

```bash
./vendor/bin/phpstan analyse --memory-limit=512M
```

### Type Annotations

```php
// Document array contents
/** @var array<string, mixed> $data */
$data = request()->all();

// Document collection types
/** @return Collection<int, Patient> */
public function getPatients(): Collection

// Document closures
/** @param Closure(Patient): void $callback */
public function eachPatient(Closure $callback): void
```

---

## Naming Conventions

### Classes

| Type | Convention | Example |
|------|------------|---------|
| Controller | `{Resource}Controller` | `PatientController` |
| Model | Singular PascalCase | `Patient`, `Appointment` |
| Service | `{Domain}Service` | `AppointmentService` |
| Action | `{Verb}{Noun}Action` | `ScheduleAppointmentAction` |
| Request | `{Verb}{Resource}Request` | `CreatePatientRequest` |
| Resource | `{Model}Resource` | `PatientResource` |
| Data/DTO | `{Model}Data` | `PatientData` |
| Exception | `{Description}Exception` | `AppointmentConflictException` |
| Policy | `{Model}Policy` | `PatientPolicy` |
| Job | `{Verb}{Noun}Job` | `SendReminderEmailJob` |
| Event | `{Model}{Verb}Event` | `AppointmentScheduledEvent` |
| Listener | `{Action}Listener` | `SendConfirmationListener` |

### Methods

```php
// Controllers: RESTful methods
public function index(): JsonResponse      // GET /resources
public function show(Resource $r): JsonResponse   // GET /resources/{id}
public function store(Request $r): JsonResponse   // POST /resources
public function update(Request $r, Resource $r): JsonResponse  // PUT /resources/{id}
public function destroy(Resource $r): JsonResponse // DELETE /resources/{id}

// Services: verb + noun
public function scheduleAppointment(): Appointment
public function cancelAppointment(): void
public function getUpcomingAppointments(): Collection

// Boolean methods: is/has/can/should
public function isActive(): bool
public function hasAppointments(): bool
public function canSchedule(): bool

// Private methods: descriptive
private function validateBusinessHours(): void
private function calculateEndTime(): Carbon
```

### Variables

```php
// Collections: plural
$patients = Patient::all();
$appointments = $patient->appointments;

// Single items: singular
$patient = Patient::find($id);
$appointment = $appointments->first();

// Booleans: is/has/should prefix
$isActive = $patient->is_active;
$hasInsurance = $patient->insurance_number !== null;

// IDs: {model}Id
$patientId = $request->integer('patient_id');
$appointmentId = $appointment->id;
```

---

## File Organization

### Controller Structure

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Http\JsonResponse;

/**
 * @group Patients
 *
 * APIs for managing patients
 */
class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
    ) {}

    /**
     * List all patients
     */
    public function index(): JsonResponse
    {
        $patients = $this->patientService->list();

        return ApiResponse::paginated($patients);
    }

    /**
     * Create a new patient
     */
    public function store(CreatePatientRequest $request): JsonResponse
    {
        $patient = $this->patientService->create($request->validated());

        return ApiResponse::created($patient->toArray());
    }

    /**
     * Show a patient
     */
    public function show(Patient $patient): JsonResponse
    {
        return ApiResponse::success($patient->toArray());
    }

    /**
     * Update a patient
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $patient = $this->patientService->update($patient, $request->validated());

        return ApiResponse::success($patient->toArray());
    }

    /**
     * Delete a patient
     */
    public function destroy(Patient $patient): JsonResponse
    {
        $this->patientService->delete($patient);

        return ApiResponse::noContent();
    }
}
```

### Service Structure

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\Patient;
use App\Support\Database;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PatientService
{
    /**
     * @return LengthAwarePaginator<int, Patient>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Patient::query()
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Patient
    {
        return Database::transaction(fn () => Patient::create($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Patient $patient, array $data): Patient
    {
        return Database::transaction(function () use ($patient, $data) {
            $patient->update($data);
            return $patient->fresh();
        });
    }

    public function delete(Patient $patient): void
    {
        $patient->delete();
    }

    public function findOrFail(int $id): Patient
    {
        $patient = Patient::find($id);

        if ($patient === null) {
            throw new ResourceNotFoundException(
                message: 'Patient not found',
                errorCode: 'PATIENT_NOT_FOUND',
            );
        }

        return $patient;
    }
}
```

### Form Request Structure

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CreatePatientRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->tenantRules(),

            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('patients')->where('tenant_id', $this->currentTenantId()),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'A patient with this email already exists.',
        ];
    }
}
```

---

## Database Conventions

### Migrations

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
        Schema::create('appointments', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign keys first
            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('dentist_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Regular columns
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();

            // Flags
            $table->boolean('is_confirmed')->default(false);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes (tenant-first for multi-tenancy)
            $table->index(['tenant_id', 'start_time']);
            $table->index(['tenant_id', 'patient_id']);
            $table->index(['tenant_id', 'dentist_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
```

### Model Definition

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $patient_id
 * @property int|null $dentist_id
 * @property \Carbon\Carbon $start_time
 * @property \Carbon\Carbon $end_time
 * @property string $status
 * @property string|null $notes
 * @property bool $is_confirmed
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read Tenant $tenant
 * @property-read Patient $patient
 * @property-read User|null $dentist
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTenant;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'dentist_id',
        'start_time',
        'end_time',
        'status',
        'notes',
        'is_confirmed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'is_confirmed' => 'boolean',
        ];
    }

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id');
    }

    // Scopes
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_time', '>=', now());
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('start_time', $date);
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'start_time', 'end_time', 'is_confirmed'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

---

## API Routes

### Structure

```php
// routes/api.php

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// API Version 1
Route::prefix('v1')->group(function () {

    // Public routes
    Route::prefix('health')->group(function () {
        Route::get('/', [HealthController::class, 'index']);
        Route::get('/database', [HealthController::class, 'database']);
        Route::get('/redis', [HealthController::class, 'redis']);
    });

    // Authentication (rate limited)
    Route::prefix('auth')
        ->middleware('throttle:auth')
        ->group(function () {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        });

    // Protected routes
    Route::middleware(['auth:sanctum', 'tenant'])
        ->group(function () {
            // Profile
            Route::get('/me', [ProfileController::class, 'show']);
            Route::put('/me', [ProfileController::class, 'update']);
            Route::post('/logout', [AuthController::class, 'logout']);

            // Resources
            Route::apiResource('patients', PatientController::class);
            Route::apiResource('appointments', AppointmentController::class);
            Route::apiResource('treatments', TreatmentController::class);

            // Nested resources
            Route::apiResource('patients.appointments', PatientAppointmentController::class)
                ->shallow();
        });

    // Admin routes
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:admin'])
        ->group(function () {
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/audit-log', [AdminController::class, 'auditLog']);
        });
});
```

---

## Git Workflow

### Branch Naming

```
feature/add-patient-search
bugfix/fix-appointment-overlap
hotfix/security-patch
refactor/optimize-queries
docs/update-api-documentation
```

### Commit Messages

```
feat(patients): add search by name and email
fix(appointments): prevent double booking
refactor(services): extract appointment validation
docs(api): update endpoint documentation
test(patients): add validation error tests
chore(deps): update Laravel to 12.1
```

### Pre-commit Checklist

1. Run `./vendor/bin/pint`
2. Run `./vendor/bin/phpstan analyse`
3. Run `php artisan test`
4. Or simply: `composer quality`

---

## Documentation

### PHPDoc Standards

```php
/**
 * Schedule a new appointment for a patient.
 *
 * @param int $patientId The patient's ID
 * @param \DateTimeInterface $startTime Appointment start time
 * @param int $durationMinutes Duration in minutes (15-240)
 *
 * @throws ResourceNotFoundException When patient doesn't exist
 * @throws BusinessRuleViolationException When scheduling rules are violated
 * @throws ResourceConflictException When time slot is already booked
 */
public function scheduleAppointment(
    int $patientId,
    \DateTimeInterface $startTime,
    int $durationMinutes,
): Appointment;
```

### Scribe Annotations

```php
/**
 * @group Appointments
 *
 * @authenticated
 *
 * @bodyParam patient_id integer required The patient ID. Example: 1
 * @bodyParam start_time datetime required Appointment start time. Example: 2025-01-15 10:00:00
 * @bodyParam duration integer required Duration in minutes. Example: 30
 *
 * @response 201 scenario="success" {"success": true, "data": {"id": 1}}
 * @response 422 scenario="validation error" {"success": false, "error_code": "VALIDATION_ERROR"}
 * @response 409 scenario="slot taken" {"success": false, "error_code": "SLOT_UNAVAILABLE"}
 */
public function store(ScheduleAppointmentRequest $request): JsonResponse
```

---

## Quality Commands

```bash
# Full quality check
composer quality

# Individual checks
composer pint        # Fix code style
composer pint:test   # Check code style
composer stan        # Static analysis
composer test        # Run tests

# Generate API docs
php artisan scribe:generate
```
