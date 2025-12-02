# API Response Standards

## Overview

All API responses follow a consistent JSON structure for predictable client-side handling. The `ApiResponse` helper class (`app/Http/Responses/ApiResponse.php`) provides static methods for generating standardized responses.

---

## Response Structure

### Success Response

```json
{
    "success": true,
    "message": "Resource created successfully",
    "data": {
        "id": 1,
        "name": "Example"
    },
    "meta": {
        "timestamp": "2025-01-15T10:30:00+00:00",
        "request_id": "550e8400-e29b-41d4-a716-446655440000"
    }
}
```

### Error Response

```json
{
    "success": false,
    "message": "Validation failed",
    "error_code": "VALIDATION_ERROR",
    "errors": {
        "email": ["The email field is required."],
        "name": ["The name must be at least 2 characters."]
    },
    "meta": {
        "timestamp": "2025-01-15T10:30:00+00:00",
        "request_id": "550e8400-e29b-41d4-a716-446655440000"
    }
}
```

### Paginated Response

```json
{
    "success": true,
    "message": "Data retrieved successfully",
    "data": {
        "items": [
            {"id": 1, "name": "Item 1"},
            {"id": 2, "name": "Item 2"}
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 15,
            "total": 50,
            "last_page": 4,
            "from": 1,
            "to": 15
        },
        "links": {
            "first": "https://api.example.com/patients?page=1",
            "last": "https://api.example.com/patients?page=4",
            "prev": null,
            "next": "https://api.example.com/patients?page=2"
        }
    },
    "meta": {
        "timestamp": "2025-01-15T10:30:00+00:00",
        "request_id": "550e8400-e29b-41d4-a716-446655440000"
    }
}
```

---

## ApiResponse Helper Methods

### Location: `app/Http/Responses/ApiResponse.php`

```php
use App\Http\Responses\ApiResponse;

// Success responses
ApiResponse::success($data, $message, $status);     // 200 OK
ApiResponse::created($data, $message);              // 201 Created
ApiResponse::noContent();                           // 204 No Content

// Error responses
ApiResponse::error($message, $errorCode, $errors, $status);  // Custom error
ApiResponse::notFound($message, $errorCode);                  // 404
ApiResponse::unauthorized($message, $errorCode);              // 401
ApiResponse::forbidden($message, $errorCode);                 // 403
ApiResponse::validationError($errors, $message);              // 422
ApiResponse::serverError($message, $errorCode);               // 500

// Paginated response
ApiResponse::paginated($paginator, $message, $dataClass);
```

---

## Usage Examples

### Controller Usage

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;

class PatientController extends Controller
{
    public function index(): JsonResponse
    {
        $patients = Patient::query()
            ->orderBy('name')
            ->paginate(15);

        return ApiResponse::paginated(
            paginator: $patients,
            message: 'Patients retrieved successfully',
        );
    }

    public function show(Patient $patient): JsonResponse
    {
        return ApiResponse::success(
            data: $patient->toArray(),
            message: 'Patient retrieved successfully',
        );
    }

    public function store(CreatePatientRequest $request): JsonResponse
    {
        $patient = Patient::create($request->validated());

        return ApiResponse::created(
            data: $patient->toArray(),
            message: 'Patient created successfully',
        );
    }

    public function destroy(Patient $patient): JsonResponse
    {
        $patient->delete();

        return ApiResponse::noContent();
    }
}
```

### With Spatie Data DTOs

```php
use App\Data\PatientData;
use App\Http\Responses\ApiResponse;

public function show(Patient $patient): JsonResponse
{
    return ApiResponse::success(
        data: PatientData::from($patient),
        message: 'Patient retrieved successfully',
    );
}

public function index(): JsonResponse
{
    $patients = Patient::paginate(15);

    return ApiResponse::paginated(
        paginator: $patients,
        message: 'Patients retrieved successfully',
        dataClass: PatientData::class,  // Auto-transforms each item
    );
}
```

---

## HTTP Status Codes

| Code | Constant | Usage |
|------|----------|-------|
| 200 | `HTTP_OK` | Successful GET, PUT, PATCH |
| 201 | `HTTP_CREATED` | Successful POST (resource created) |
| 204 | `HTTP_NO_CONTENT` | Successful DELETE |
| 400 | `HTTP_BAD_REQUEST` | Invalid request/parameters |
| 401 | `HTTP_UNAUTHORIZED` | Missing/invalid authentication |
| 403 | `HTTP_FORBIDDEN` | Authenticated but not authorized |
| 404 | `HTTP_NOT_FOUND` | Resource not found |
| 409 | `HTTP_CONFLICT` | Resource conflict (duplicate) |
| 422 | `HTTP_UNPROCESSABLE_ENTITY` | Validation failed |
| 429 | `HTTP_TOO_MANY_REQUESTS` | Rate limit exceeded |
| 500 | `HTTP_INTERNAL_SERVER_ERROR` | Server error |
| 503 | `HTTP_SERVICE_UNAVAILABLE` | Database/service unavailable |

---

## Error Codes

Standard error codes used throughout the application:

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| `VALIDATION_ERROR` | 422 | Request validation failed |
| `RESOURCE_NOT_FOUND` | 404 | Resource doesn't exist |
| `{MODEL}_NOT_FOUND` | 404 | Specific model not found (e.g., `PATIENT_NOT_FOUND`) |
| `UNAUTHENTICATED` | 401 | No valid authentication token |
| `ACCESS_DENIED` | 403 | Not authorized for this action |
| `FORBIDDEN` | 403 | Action forbidden for this user |
| `TENANT_NOT_FOUND` | 404 | Tenant resolution failed |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |
| `DATABASE_ERROR` | 503 | Database connection/query failed |
| `INTERNAL_ERROR` | 500 | Unexpected server error |
| `DOMAIN_ERROR` | 400 | Generic domain exception |
| `BUSINESS_RULE_VIOLATION` | 422 | Business logic constraint |
| `RESOURCE_CONFLICT` | 409 | Duplicate/conflict error |
| `UNAUTHORIZED_ACTION` | 403 | Action not permitted |
| `INVALID_ARGUMENT` | 400 | Invalid parameter value |
| `OPERATION_FAILED` | 503 | External operation failed |

---

## Request Headers

### Required Headers

```http
Accept: application/json
Content-Type: application/json
```

### Optional Headers

```http
Authorization: Bearer {token}
X-Request-ID: {uuid}           # Client-provided request ID
X-Tenant-ID: {tenant_id}       # Explicit tenant selection
```

### Response Headers

```http
X-Request-ID: {uuid}           # Request tracing ID
X-RateLimit-Limit: 60          # Rate limit ceiling
X-RateLimit-Remaining: 58      # Remaining requests
X-RateLimit-Reset: 1705312200  # Reset timestamp
```

---

## Request ID Tracing

Every request is assigned a unique ID for debugging and log correlation:

1. **Client-provided**: Pass `X-Request-ID` header with your UUID
2. **Auto-generated**: Server generates UUID if not provided
3. **Response inclusion**: Same ID returned in response header and `meta.request_id`

### Log Correlation

```php
// In your logs, filter by request_id
Log::info('Processing payment', [
    'request_id' => request()->header('X-Request-ID'),
    'patient_id' => $patient->id,
]);
```

---

## Best Practices

### DO

```php
// Use meaningful messages
return ApiResponse::success(
    data: $patient,
    message: 'Patient profile updated successfully',
);

// Include relevant error codes
return ApiResponse::error(
    message: 'Cannot schedule appointment on a holiday',
    errorCode: 'APPOINTMENT_HOLIDAY_CONFLICT',
    status: 422,
);

// Use specific not found messages
return ApiResponse::notFound(
    message: 'Treatment plan not found',
    errorCode: 'TREATMENT_PLAN_NOT_FOUND',
);
```

### DON'T

```php
// Don't expose internal details in production
return ApiResponse::error(
    message: $exception->getMessage(),  // May contain SQL
    errorCode: 'ERROR',
);

// Don't use generic messages
return ApiResponse::success(
    data: $patient,
    message: 'Success',  // Not helpful
);

// Don't return raw arrays
return response()->json($data);  // Use ApiResponse instead
```

---

## OpenAPI Documentation

API documentation is auto-generated using Scribe. Add annotations to controllers:

```php
/**
 * @group Patients
 *
 * APIs for managing patients
 */
class PatientController extends Controller
{
    /**
     * List all patients
     *
     * Get a paginated list of patients for the current tenant.
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 15
     * @queryParam search string Search by name or email. Example: john
     *
     * @response 200 scenario="success" {
     *   "success": true,
     *   "message": "Patients retrieved successfully",
     *   "data": {
     *     "items": [{"id": 1, "name": "John Doe"}],
     *     "pagination": {"current_page": 1, "total": 50}
     *   }
     * }
     */
    public function index(): JsonResponse
    {
        // ...
    }
}
```

Generate documentation:

```bash
php artisan scribe:generate
```

Access at: `http://localhost:8000/docs`
