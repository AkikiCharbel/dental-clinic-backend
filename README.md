# Dental Clinic Backend

[![CI](https://github.com/AkikiCharbel/dental-clinic-backend/actions/workflows/ci.yml/badge.svg)](https://github.com/AkikiCharbel/dental-clinic-backend/actions/workflows/ci.yml)

A comprehensive dental clinic management system backend built with Laravel 12.

## Requirements

- PHP 8.4+
- PostgreSQL 16+
- Redis 7+
- Composer 2.x
- Node.js 20+ (for frontend assets)

## Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd dental-clinic-backend
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Start Services (Docker)

```bash
docker-compose up -d
```

This will start:
- PostgreSQL on port 5432
- Redis on port 6379

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Start Development Server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`.

## Configuration

### Database

Update your `.env` file with PostgreSQL credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dental_clinic
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### Redis

Redis is used for caching, sessions, and queues:

```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

## Development

### Code Quality

Run all quality checks:

```bash
composer quality
```

Individual checks:

```bash
# Code formatting check
composer pint:test

# Fix code formatting
composer pint

# Static analysis
composer stan

# Run tests
composer test
```

### Testing

```bash
# Run all tests
php artisan test

# Run tests in parallel
php artisan test --parallel

# Run specific test file
php artisan test tests/Feature/Api/V1/HealthTest.php

# Run with coverage
composer test:coverage
```

### IDE Helper

Generate IDE helper files for better autocompletion:

```bash
composer ide-helper
```

This generates:
- `_ide_helper.php` - Facade method signatures
- `_ide_helper_models.php` - Model property/relation hints
- `.phpstorm.meta.php` - PhpStorm meta hints

### Queue Worker (Horizon)

Start the queue worker:

```bash
php artisan horizon
```

Access the Horizon dashboard at `/horizon` (requires authentication in production).

## API Documentation

### Base URL

```
http://localhost:8000/api/v1
```

### Health Check Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Basic health check |
| GET | `/health/database` | Database connection check |
| GET | `/health/redis` | Redis connection check |
| GET | `/health/cache` | Cache system check |
| GET | `/health/full` | Full system health check |

### Response Format

All API responses follow this structure:

**Success Response:**
```json
{
    "success": true,
    "message": "Success message",
    "data": { },
    "meta": {
        "request_id": "uuid",
        "timestamp": "2024-01-01T00:00:00.000000Z"
    }
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Error message",
    "errors": { },
    "meta": {
        "request_id": "uuid",
        "timestamp": "2024-01-01T00:00:00.000000Z"
    }
}
```

## Project Structure

```
app/
├── Actions/              # Single-purpose action classes
├── Data/                 # DTOs (spatie/laravel-data)
├── Enums/               # PHP enums
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Middleware/
│   └── Requests/Api/V1/
├── Models/
├── Policies/
├── Queries/             # Complex query builders
├── Services/            # External service integrations
└── Traits/              # Reusable model traits
```

## Installed Packages

### Production
- **laravel/sanctum** - API authentication
- **spatie/laravel-permission** - Roles & permissions
- **spatie/laravel-query-builder** - Advanced API filtering
- **spatie/laravel-data** - DTOs
- **spatie/laravel-activitylog** - Audit logging
- **laravel/horizon** - Queue monitoring
- **spatie/laravel-backup** - Automated database backups
- **spatie/laravel-health** - Application health monitoring
- **spatie/laravel-medialibrary** - Media/file management
- **intervention/image-laravel** - Image processing
- **barryvdh/laravel-dompdf** - PDF generation

### Development
- **laravel/pint** - Code formatting
- **larastan/larastan** - Static analysis (Level 10)
- **pestphp/pest** - Testing framework
- **barryvdh/laravel-ide-helper** - IDE autocompletion support

## Deployment

### Production Setup

1. Copy `.env.production.example` to `.env` and configure values
2. Configure your web server (Nginx recommended)
3. Set up SSL certificates (Let's Encrypt recommended)
4. Configure Supervisor for Horizon queue workers

### Deploy Script

For zero-downtime deployments (Laravel Forge compatible):

```bash
./deploy.sh
```

This script handles:
- Maintenance mode
- Dependency installation
- Database migrations
- Cache optimization
- Queue worker restart

### Infrastructure Requirements

- **Server:** 2GB+ RAM, 2+ vCPUs
- **Database:** Managed PostgreSQL 16+
- **Cache:** Managed Redis 7+
- **Storage:** DigitalOcean Spaces (or S3-compatible)

## Git Workflow

### Commit Message Convention

```
<type>(<scope>): <description>

Types:
- feat: New feature
- fix: Bug fix
- refactor: Code refactoring
- test: Adding tests
- docs: Documentation
- chore: Maintenance tasks
```

Examples:
```
feat(auth): implement login endpoint
fix(tenancy): resolve cross-tenant data leak
test(appointments): add validation tests
```

## License

MIT License
