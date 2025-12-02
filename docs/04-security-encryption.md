# Security & Encryption Guide

## Overview

As a healthcare SaaS application, the Dental Clinic Backend must comply with strict security requirements for handling Protected Health Information (PHI) and Personally Identifiable Information (PII).

---

## Security Configuration

### Environment Settings

```bash
# .env

# Strong password hashing
BCRYPT_ROUNDS=12

# Session security
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true        # Production only

# Database
DB_CONNECTION=pgsql               # Use PostgreSQL with SSL

# API Authentication
SANCTUM_STATEFUL_DOMAINS=app.dentalclinic.com,admin.dentalclinic.com
```

---

## Encryption Helper

### Location: `app/Support/Encryption.php`

The `Encryption` class provides utilities for handling sensitive data:

```php
use App\Support\Encryption;

// Encrypt sensitive data
$encrypted = Encryption::encrypt($ssn);

// Decrypt when needed
$decrypted = Encryption::decrypt($encrypted);

// Check if value is encrypted
$isEncrypted = Encryption::isEncrypted($value);

// Create searchable hash
$hash = Encryption::searchHash($email);

// Mask for display
$masked = Encryption::mask($ssn, visibleStart: 0, visibleEnd: 4);
// "***-**-1234"

// Mask email
$maskedEmail = Encryption::maskEmail('john.doe@example.com');
// "j******e@example.com"

// Mask phone
$maskedPhone = Encryption::maskPhone('+1-555-123-4567');
// "***-***-4567"
```

---

## PII Field Encryption

### Model Implementation

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Encryption;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'date_of_birth',
        'ssn_encrypted',           // Encrypted field
        'ssn_hash',                // Searchable hash
        'insurance_number_encrypted',
        'insurance_number_hash',
    ];

    protected $hidden = [
        'ssn_encrypted',
        'ssn_hash',
        'insurance_number_encrypted',
        'insurance_number_hash',
    ];

    // Accessor for decrypted SSN
    public function getSsnAttribute(): ?string
    {
        return Encryption::decrypt($this->ssn_encrypted);
    }

    // Mutator for SSN encryption
    public function setSsnAttribute(?string $value): void
    {
        $this->attributes['ssn_encrypted'] = Encryption::encrypt($value);
        $this->attributes['ssn_hash'] = Encryption::searchHash($value);
    }

    // Masked SSN for display
    public function getMaskedSsnAttribute(): ?string
    {
        $ssn = $this->ssn;
        return $ssn ? Encryption::mask($ssn, 0, 4) : null;
    }

    // Search by SSN
    public static function findBySsn(string $ssn): ?self
    {
        $hash = Encryption::searchHash($ssn);
        return static::where('ssn_hash', $hash)->first();
    }
}
```

### Migration for Encrypted Fields

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
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Regular fields
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();

            // Encrypted PII fields
            $table->text('ssn_encrypted')->nullable();      // Encrypted value
            $table->string('ssn_hash', 64)->nullable();     // SHA-256 hash

            $table->text('insurance_number_encrypted')->nullable();
            $table->string('insurance_number_hash', 64)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index hash columns for searching
            $table->index(['tenant_id', 'ssn_hash']);
            $table->index(['tenant_id', 'insurance_number_hash']);
        });
    }
};
```

---

## Rate Limiting

### Configuration

Location: `app/Providers/AppServiceProvider.php`

```php
private function configureRateLimiting(): void
{
    // Default API: 60/min unauthenticated, 120/min authenticated
    RateLimiter::for('api', function (Request $request) {
        return $request->user()
            ? Limit::perMinute(120)->by($request->user()->id)
            : Limit::perMinute(60)->by($request->ip());
    });

    // Auth endpoints: 5/min (prevent brute force)
    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });

    // File uploads: 10/min
    RateLimiter::for('uploads', function (Request $request) {
        return Limit::perMinute(10)->by(
            $request->user()?->id ?: $request->ip()
        );
    });

    // Exports: 20/hour
    RateLimiter::for('exports', function (Request $request) {
        return Limit::perHour(20)->by(
            $request->user()?->id ?: $request->ip()
        );
    });

    // Per-tenant: 1000/min
    RateLimiter::for('tenant', function (Request $request) {
        $tenantId = tenant_id() ?? $request->header('X-Tenant-ID');
        return $tenantId
            ? Limit::perMinute(1000)->by('tenant:'.$tenantId)
            : Limit::perMinute(60)->by($request->ip());
    });
}
```

### Applying Rate Limits

```php
// routes/api.php

// Auth routes with strict limiting
Route::prefix('v1/auth')
    ->middleware('throttle:auth')
    ->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    });

// Export routes
Route::prefix('v1/reports')
    ->middleware(['auth:sanctum', 'throttle:exports'])
    ->group(function () {
        Route::get('/patients/export', [ReportController::class, 'exportPatients']);
    });
```

---

## CORS Configuration

### Location: `config/cors.php`

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),

    'allowed_origins_patterns' => [
        '/^https?:\/\/.*\.'.preg_quote(env('APP_DOMAIN', 'localhost'), '/').'$/',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-Request-ID',
        'X-Tenant-ID',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [
        'X-Request-ID',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],

    'max_age' => 86400,  // 24 hours

    'supports_credentials' => true,
];
```

### Environment Configuration

```bash
# .env

# Comma-separated allowed origins
CORS_ALLOWED_ORIGINS=https://app.dentalclinic.com,https://admin.dentalclinic.com

# Domain for subdomain pattern matching
APP_DOMAIN=dentalclinic.com
```

---

## Authentication with Sanctum

### Token-Based API Authentication

```php
// Login and receive token
public function login(LoginRequest $request): JsonResponse
{
    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw new AuthenticationException('Invalid credentials');
    }

    // Create token with abilities
    $token = $user->createToken(
        name: 'api-token',
        abilities: $this->getAbilitiesForUser($user),
        expiresAt: now()->addDays(7),
    );

    return ApiResponse::success([
        'user' => $user,
        'token' => $token->plainTextToken,
        'expires_at' => $token->accessToken->expires_at,
    ]);
}

private function getAbilitiesForUser(User $user): array
{
    $abilities = ['read'];

    if ($user->hasRole('admin')) {
        $abilities = ['*'];  // All abilities
    } elseif ($user->hasRole('dentist')) {
        $abilities = ['read', 'write', 'delete-own'];
    }

    return $abilities;
}
```

### Protecting Routes

```php
// routes/api.php

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {
        // All authenticated users
        Route::get('/profile', [ProfileController::class, 'show']);

        // Check specific ability
        Route::middleware('ability:write')->group(function () {
            Route::post('/patients', [PatientController::class, 'store']);
        });

        // Check role
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/users', [AdminController::class, 'users']);
        });
    });
```

---

## Audit Logging

### Model Configuration

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Patient extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'email',
                'phone',
                'date_of_birth',
                // Don't log encrypted fields directly
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => "Patient was {$event}");
    }
}
```

### Custom Activity Logging

```php
use Spatie\Activitylog\Facades\Activity;

// Log sensitive action
Activity::causedBy($user)
    ->performedOn($patient)
    ->withProperties([
        'action' => 'viewed_ssn',
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ])
    ->log('Viewed patient SSN');

// Log export
Activity::causedBy($user)
    ->withProperties([
        'export_type' => 'patient_list',
        'record_count' => $count,
        'filters' => $request->filters,
    ])
    ->log('Exported patient data');
```

### Querying Audit Logs

```php
use Spatie\Activitylog\Models\Activity;

// Get all activities for a patient
$activities = Activity::forSubject($patient)->get();

// Get all activities by a user
$activities = Activity::causedBy($user)->get();

// Get recent sensitive access
$sensitiveAccess = Activity::where('description', 'like', '%SSN%')
    ->where('created_at', '>=', now()->subDay())
    ->get();
```

---

## Database Security

### Transaction Handling

Location: `app/Support/Database.php`

```php
use App\Support\Database;

// Safe transaction with retries
$result = Database::transaction(function () {
    $patient = Patient::create([...]);
    $appointment = Appointment::create([...]);
    return $patient;
}, attempts: 3);

// Transaction with result wrapper
$result = Database::safeTransaction(function () {
    return Patient::create([...]);
});

if ($result['success']) {
    $patient = $result['data'];
} else {
    $error = $result['error'];
}

// Row-level locking
Database::lockForUpdate(Patient::class, $patientId, function ($patient) {
    $patient->update(['balance' => $patient->balance - $amount]);
});
```

### Slow Query Detection

```php
// Configured in AppServiceProvider
// Logs queries taking > 100ms in non-production

// Example log output:
// [warning] Slow query detected
// {
//     "sql": "SELECT * FROM patients WHERE ...",
//     "bindings": [...],
//     "time": "150.23ms"
// }
```

---

## Security Best Practices

### DO

```php
// Validate tenant ownership
public function view(User $user, Patient $patient): bool
{
    return $user->tenant_id === $patient->tenant_id;
}

// Use parameterized queries (automatic with Eloquent)
Patient::where('email', $email)->first();

// Hash passwords properly
Hash::make($password);

// Encrypt sensitive data
Encryption::encrypt($ssn);

// Log sensitive access
Activity::log('Accessed patient medical records');
```

### DON'T

```php
// Don't trust client-provided tenant_id
$patient = Patient::create([
    'tenant_id' => $request->tenant_id,  // DANGEROUS
]);

// Don't expose sensitive data in logs
Log::info('User data', ['password' => $password]);  // BAD

// Don't store unencrypted PII
$patient->ssn = $request->ssn;  // BAD - use accessor/mutator

// Don't use raw queries with user input
DB::select("SELECT * FROM patients WHERE email = '{$email}'");  // SQL injection
```

---

## Security Headers

Add to your web server (Nginx/Apache) or middleware:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: default-src 'self'
Referrer-Policy: strict-origin-when-cross-origin
```

---

## Regular Security Tasks

### Key Rotation

```bash
# Rotate application key (will invalidate existing sessions/tokens)
php artisan key:generate

# Rotate Sanctum tokens periodically
# Implement token refresh mechanism in your auth flow
```

### Security Audits

```bash
# Check for known vulnerabilities
composer audit

# Update dependencies regularly
composer update --with-all-dependencies
```

### Access Review

```php
// Periodically review user access
User::query()
    ->whereNull('last_login_at')
    ->where('created_at', '<', now()->subMonths(3))
    ->get();

// Review admin users
User::role('admin')->get();

// Check token usage
PersonalAccessToken::where('last_used_at', '<', now()->subDays(90))->delete();
```
