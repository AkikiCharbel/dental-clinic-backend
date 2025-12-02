# Exception Handling Guide

## Overview

The application uses a structured exception hierarchy for consistent error handling. All domain exceptions extend `DomainException` and are automatically converted to standardized API responses.

---

## Exception Hierarchy

```
Throwable
└── Exception
    └── DomainException (abstract)
        ├── ResourceNotFoundException      (404)
        ├── ResourceConflictException      (409)
        ├── UnauthorizedActionException    (403)
        ├── BusinessRuleViolationException (422)
        ├── InvalidArgumentException       (400)
        ├── ValidationException            (422)
        └── OperationFailedException       (503)
```

---

## Domain Exceptions

### Location: `app/Exceptions/Domain/`

### DomainException (Base Class)

```php
abstract class DomainException extends Exception
{
    protected string $errorCode = 'DOMAIN_ERROR';
    protected int $httpStatus = Response::HTTP_BAD_REQUEST;

    public function __construct(
        string $message = '',
        ?string $errorCode = null,
        ?int $httpStatus = null,
    );

    public function getErrorCode(): string;
    public function getHttpStatus(): int;
}
```

---

## Exception Types and Usage

### ResourceNotFoundException (404)

Use when a requested resource doesn't exist.

```php
use App\Exceptions\Domain\ResourceNotFoundException;

// Basic usage
throw new ResourceNotFoundException('Patient not found');

// With custom error code
throw new ResourceNotFoundException(
    message: 'Treatment plan not found',
    errorCode: 'TREATMENT_PLAN_NOT_FOUND',
);
```

**API Response:**
```json
{
    "success": false,
    "message": "Patient not found",
    "error_code": "RESOURCE_NOT_FOUND",
    "meta": { "timestamp": "...", "request_id": "..." }
}
```

---

### ResourceConflictException (409)

Use when a resource already exists or there's a conflict.

```php
use App\Exceptions\Domain\ResourceConflictException;

// Duplicate resource
throw new ResourceConflictException('A patient with this email already exists');

// Conflict with existing state
throw new ResourceConflictException(
    message: 'Appointment slot is already booked',
    errorCode: 'APPOINTMENT_SLOT_TAKEN',
);
```

**API Response:**
```json
{
    "success": false,
    "message": "Appointment slot is already booked",
    "error_code": "APPOINTMENT_SLOT_TAKEN",
    "meta": { "timestamp": "...", "request_id": "..." }
}
```

---

### UnauthorizedActionException (403)

Use when the user lacks permission for an action.

```php
use App\Exceptions\Domain\UnauthorizedActionException;

// Permission denied
throw new UnauthorizedActionException('You cannot delete this patient');

// Role-based restriction
throw new UnauthorizedActionException(
    message: 'Only dentists can prescribe medications',
    errorCode: 'PRESCRIPTION_NOT_ALLOWED',
);
```

**API Response:**
```json
{
    "success": false,
    "message": "Only dentists can prescribe medications",
    "error_code": "PRESCRIPTION_NOT_ALLOWED",
    "meta": { "timestamp": "...", "request_id": "..." }
}
```

---

### BusinessRuleViolationException (422)

Use when a business rule is violated.

```php
use App\Exceptions\Domain\BusinessRuleViolationException;

// Business logic constraint
throw new BusinessRuleViolationException(
    'Cannot schedule appointment in the past'
);

// Complex rule violation
throw new BusinessRuleViolationException(
    message: 'Patient must complete intake form before scheduling',
    errorCode: 'INTAKE_REQUIRED',
);
```

**API Response:**
```json
{
    "success": false,
    "message": "Patient must complete intake form before scheduling",
    "error_code": "INTAKE_REQUIRED",
    "meta": { "timestamp": "...", "request_id": "..." }
}
```

---

### InvalidArgumentException (400)

Use when arguments or parameters are invalid.

```php
use App\Exceptions\Domain\InvalidArgumentException;

// Invalid parameter
throw new InvalidArgumentException('Duration must be positive');

// Missing required data
throw new InvalidArgumentException(
    message: 'At least one treatment must be selected',
    errorCode: 'NO_TREATMENTS_SELECTED',
);
```

**API Response:**
```json
{
    "success": false,
    "message": "At least one treatment must be selected",
    "error_code": "NO_TREATMENTS_SELECTED",
    "meta": { "timestamp": "...", "request_id": "..." }
}
```

---

### ValidationException (Domain) (422)

Use for domain-level validation (not form validation).

```php
use App\Exceptions\Domain\ValidationException;

// Single validation error
throw new ValidationException('Invalid appointment configuration');

// With field-level errors
throw new ValidationException(
    message: 'Appointment validation failed',
    errors: [
        'start_time' => ['Start time must be during business hours'],
        'duration' => ['Duration cannot exceed 4 hours'],
    ],
);
```

**API Response:**
```json
{
    "success": false,
    "message": "Appointment validation failed",
    "error_code": "DOMAIN_VALIDATION_FAILED",
    "errors": {
        "start_time": ["Start time must be during business hours"],
        "duration": ["Duration cannot exceed 4 hours"]
    },
    "meta": { "timestamp": "...", "request_id": "..." }
}
```

---

### OperationFailedException (503)

Use when an external operation fails.

```php
use App\Exceptions\Domain\OperationFailedException;

// External service failure
throw new OperationFailedException('Payment gateway unavailable');

// Retryable operation
throw new OperationFailedException(
    message: 'Email service temporarily unavailable',
    retryable: true,
);

// Non-retryable
throw new OperationFailedException(
    message: 'File storage quota exceeded',
    retryable: false,
);
```

**API Response:**
```json
{
    "success": false,
    "message": "Payment gateway unavailable",
    "error_code": "OPERATION_FAILED",
    "meta": { "timestamp": "...", "request_id": "..." }
}
```

---

## Exception Handler Configuration

### Location: `bootstrap/app.php`

The application handles exceptions in the following order:

1. **DomainException** → Domain-specific error response
2. **ValidationException** (Laravel) → Form validation error response
3. **AuthenticationException** → 401 Unauthorized
4. **AccessDeniedHttpException** → 403 Forbidden
5. **ModelNotFoundException** → 404 Not Found
6. **NotFoundHttpException** → 404 Not Found
7. **TooManyRequestsHttpException** → 429 Rate Limit
8. **QueryException** → 503 Database Error (sanitized)
9. **HttpException** → Generic HTTP error
10. **Throwable** → 500 Internal Error (catch-all)

---

## Production vs Development

### Development Mode

```json
{
    "success": false,
    "message": "SQLSTATE[23505]: Unique violation: duplicate key value",
    "error_code": "DATABASE_ERROR",
    "meta": { "timestamp": "...", "request_id": "..." }
}
```

### Production Mode

```json
{
    "success": false,
    "message": "A database error occurred. Please try again later.",
    "error_code": "DATABASE_ERROR",
    "meta": { "timestamp": "...", "request_id": "..." }
}
```

The actual error is logged server-side with full details:

```php
Log::error('Database error', [
    'request_id' => $request->header('X-Request-ID'),
    'message' => $e->getMessage(),
    'sql' => $e->getSql(),
    'bindings' => $e->getBindings(),
]);
```

---

## Usage in Services

### Service Pattern with Exceptions

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessRuleViolationException;
use App\Exceptions\Domain\ResourceConflictException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\Appointment;
use App\Models\Patient;

final class AppointmentService
{
    public function scheduleAppointment(
        int $patientId,
        DateTimeInterface $startTime,
        int $durationMinutes,
    ): Appointment {
        // Check patient exists
        $patient = Patient::find($patientId);
        if ($patient === null) {
            throw new ResourceNotFoundException(
                message: 'Patient not found',
                errorCode: 'PATIENT_NOT_FOUND',
            );
        }

        // Business rule: no past appointments
        if ($startTime < now()) {
            throw new BusinessRuleViolationException(
                message: 'Cannot schedule appointments in the past',
                errorCode: 'PAST_APPOINTMENT',
            );
        }

        // Check for conflicts
        $conflict = Appointment::query()
            ->where('start_time', '<', $startTime->add($durationMinutes, 'minutes'))
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($conflict) {
            throw new ResourceConflictException(
                message: 'Time slot is not available',
                errorCode: 'SLOT_UNAVAILABLE',
            );
        }

        // Create appointment
        return Appointment::create([
            'patient_id' => $patientId,
            'start_time' => $startTime,
            'duration_minutes' => $durationMinutes,
        ]);
    }
}
```

### Controller Usage

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleAppointmentRequest;
use App\Http\Responses\ApiResponse;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    public function store(ScheduleAppointmentRequest $request): JsonResponse
    {
        // Exceptions are automatically caught and converted to API responses
        $appointment = $this->appointmentService->scheduleAppointment(
            patientId: $request->integer('patient_id'),
            startTime: $request->date('start_time'),
            durationMinutes: $request->integer('duration'),
        );

        return ApiResponse::created(
            data: $appointment->toArray(),
            message: 'Appointment scheduled successfully',
        );
    }
}
```

---

## Creating Custom Exceptions

### Step 1: Create Exception Class

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

class AppointmentConflictException extends DomainException
{
    protected string $errorCode = 'APPOINTMENT_CONFLICT';
    protected int $httpStatus = Response::HTTP_CONFLICT;

    public function __construct(
        string $message = 'Appointment time conflicts with existing booking',
        public readonly ?int $conflictingAppointmentId = null,
    ) {
        parent::__construct($message);
    }
}
```

### Step 2: Use in Service

```php
throw new AppointmentConflictException(
    message: 'Dr. Smith is already booked at this time',
    conflictingAppointmentId: $existingAppointment->id,
);
```

---

## Best Practices

### DO

```php
// Use specific exception types
throw new ResourceNotFoundException('Patient not found');

// Include helpful error codes
throw new BusinessRuleViolationException(
    message: 'Insurance verification required',
    errorCode: 'INSURANCE_NOT_VERIFIED',
);

// Provide context in messages
throw new ResourceConflictException(
    "Email '{$email}' is already registered",
);
```

### DON'T

```php
// Don't use generic exceptions for domain logic
throw new \Exception('Patient not found');  // BAD

// Don't expose internal details
throw new BusinessRuleViolationException(
    "Query failed: SELECT * FROM...",  // BAD - exposes SQL
);

// Don't use wrong exception types
throw new ResourceNotFoundException('Invalid email');  // BAD - use validation
```

---

## Testing Exceptions

```php
<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleViolationException;
use App\Services\AppointmentService;

describe('AppointmentService', function () {
    it('throws exception for past appointments', function () {
        $service = app(AppointmentService::class);

        expect(fn () => $service->scheduleAppointment(
            patientId: 1,
            startTime: now()->subDay(),
            durationMinutes: 30,
        ))->toThrow(BusinessRuleViolationException::class);
    });

    it('returns correct error response for conflicts', function () {
        // Create existing appointment
        $existing = Appointment::factory()->create([
            'start_time' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/v1/appointments', [
            'patient_id' => 1,
            'start_time' => now()->addHour()->format('Y-m-d H:i:s'),
            'duration' => 30,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'SLOT_UNAVAILABLE',
            ]);
    });
});
```
