# Architecture Overview

## Project Summary

**Dental Clinic Backend** is an enterprise-grade multi-tenant SaaS API built with Laravel 12 and PHP 8.4. Designed for 10+ years of maintainability, it provides a robust foundation for dental clinic management systems.

---

## Technology Stack

| Component | Technology | Version | Purpose |
|-----------|------------|---------|---------|
| Framework | Laravel | 12.x | Core application framework |
| Language | PHP | 8.4+ | Server-side language |
| Database | PostgreSQL | 16+ | Primary data store |
| Cache | Redis | 7+ | Caching, sessions, queues |
| Queue | Laravel Horizon | 5.x | Queue monitoring and management |
| API Auth | Laravel Sanctum | 4.x | API token authentication |
| Permissions | Spatie Permission | 6.x | Role-based access control |
| DTOs | Spatie Laravel Data | 4.x | Data Transfer Objects |
| Logging | Spatie Activity Log | 4.x | Audit trail |
| API Docs | Scribe | 5.x | OpenAPI documentation |
| Testing | Pest | 4.x | Testing framework |
| Static Analysis | PHPStan | Level 10 | Type safety |
| Code Style | Laravel Pint | 1.x | Code formatting |

---

## Directory Structure

```
app/
├── Actions/                    # Single-purpose action classes
├── Data/                       # Spatie Data DTOs
├── Enums/                      # PHP 8.1+ enums
├── Exceptions/
│   └── Domain/                 # Domain-specific exceptions
│       ├── DomainException.php (abstract base)
│       ├── ResourceNotFoundException.php
│       ├── ResourceConflictException.php
│       ├── UnauthorizedActionException.php
│       ├── BusinessRuleViolationException.php
│       ├── InvalidArgumentException.php
│       ├── ValidationException.php
│       └── OperationFailedException.php
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/            # Versioned API controllers
│   ├── Middleware/
│   │   ├── AddRequestId.php   # Request tracing
│   │   └── ResolveTenant.php  # Tenant resolution
│   ├── Requests/
│   │   └── ApiRequest.php     # Base form request
│   └── Responses/
│       └── ApiResponse.php    # Standardized responses
├── Models/                     # Eloquent models
├── Policies/                   # Authorization policies
├── Providers/
│   ├── AppServiceProvider.php # Core configuration
│   └── HorizonServiceProvider.php
├── Queries/                    # Query builder classes
├── Services/                   # Business logic services
├── Support/
│   ├── Database.php           # Transaction helpers
│   └── Encryption.php         # PII encryption
└── Traits/
    └── BelongsToTenant.php    # Multi-tenancy trait

config/
├── cors.php                   # CORS configuration
├── horizon.php                # Queue dashboard
├── permission.php             # Spatie permissions
├── sanctum.php                # API authentication
└── scribe.php                 # API documentation

database/
├── factories/                 # Model factories
├── migrations/                # Schema migrations
└── seeders/                   # Data seeders

docs/                          # Project documentation
├── 00-architecture-overview.md
├── 01-api-response-standards.md
├── 02-multi-tenancy-guide.md
├── 03-exception-handling.md
├── 04-security-encryption.md
├── 05-testing-standards.md
└── 06-development-conventions.md

tests/
├── Feature/
│   └── Api/V1/               # API endpoint tests
├── Unit/                      # Unit tests
└── Traits/
    └── ValidatesOpenApiSpec.php
```

---

## Core Design Principles

### 1. Multi-Tenancy First
Every data model is tenant-scoped by default. The `BelongsToTenant` trait automatically:
- Filters queries by `tenant_id`
- Sets `tenant_id` on model creation
- Prevents cross-tenant data access

### 2. API-First Design
- All responses follow a consistent JSON structure
- OpenAPI 3.1 specification auto-generated
- Versioned endpoints (`/api/v1/`, `/api/v2/`)
- Request ID tracing for debugging

### 3. Type Safety
- PHPStan level 10 (maximum strictness)
- Strict type declarations in all files
- Generics for collections and builders
- Eloquent model property checking

### 4. Domain-Driven Exceptions
- Custom exception hierarchy for business logic
- HTTP status codes mapped to exception types
- Consistent error response format
- Production-safe error messages

### 5. Security by Default
- Rate limiting on all endpoints
- CORS properly configured
- PII encryption helpers
- Audit logging on sensitive models

---

## Request/Response Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              REQUEST FLOW                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Client Request                                                             │
│       │                                                                     │
│       ▼                                                                     │
│  ┌─────────────────┐                                                        │
│  │  AddRequestId   │  ← Generates/uses X-Request-ID for tracing            │
│  └────────┬────────┘                                                        │
│           │                                                                 │
│           ▼                                                                 │
│  ┌─────────────────┐                                                        │
│  │  Rate Limiting  │  ← api, auth, uploads, exports, tenant limiters       │
│  └────────┬────────┘                                                        │
│           │                                                                 │
│           ▼                                                                 │
│  ┌─────────────────┐                                                        │
│  │ ResolveTenant   │  ← Header, subdomain, or user-based resolution        │
│  └────────┬────────┘                                                        │
│           │                                                                 │
│           ▼                                                                 │
│  ┌─────────────────┐                                                        │
│  │   Sanctum Auth  │  ← Token validation for protected routes              │
│  └────────┬────────┘                                                        │
│           │                                                                 │
│           ▼                                                                 │
│  ┌─────────────────┐                                                        │
│  │   Controller    │  ← Business logic execution                           │
│  └────────┬────────┘                                                        │
│           │                                                                 │
│           ▼                                                                 │
│  ┌─────────────────┐                                                        │
│  │  ApiResponse    │  ← Standardized JSON response                         │
│  └─────────────────┘                                                        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Environment Configuration

### Required Environment Variables

```bash
# Application
APP_NAME="Dental Clinic"
APP_ENV=local|staging|production
APP_KEY=base64:...
APP_DEBUG=true|false
APP_URL=https://api.example.com
APP_DOMAIN=example.com  # For subdomain tenant resolution

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dental_clinic
DB_USERNAME=postgres
DB_PASSWORD=secret

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Cache & Sessions
CACHE_STORE=redis
SESSION_DRIVER=redis

# Queue
QUEUE_CONNECTION=redis
HORIZON_PREFIX=dental_clinic_horizon:

# Security
BCRYPT_ROUNDS=12
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173

# Documentation
VALIDATE_OPENAPI=true
SCRIBE_AUTH_KEY=
```

---

## Quick Start Commands

```bash
# Install dependencies
composer install

# Start Docker services
docker-compose up -d

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Generate API documentation
php artisan scribe:generate

# Run quality checks
composer quality  # Runs pint:test, stan, and test

# Start queue worker
php artisan horizon
```

---

## Related Documentation

- [01-api-response-standards.md](01-api-response-standards.md) - Response format specification
- [02-multi-tenancy-guide.md](02-multi-tenancy-guide.md) - Tenant isolation implementation
- [03-exception-handling.md](03-exception-handling.md) - Exception hierarchy and usage
- [04-security-encryption.md](04-security-encryption.md) - PII handling and security
- [05-testing-standards.md](05-testing-standards.md) - Testing conventions
- [06-development-conventions.md](06-development-conventions.md) - Coding standards
