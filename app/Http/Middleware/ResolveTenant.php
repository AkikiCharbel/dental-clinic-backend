<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and binds the current tenant context.
 *
 * Tenant resolution strategies (in order of precedence):
 * 1. X-Tenant-ID header (for API clients)
 * 2. Subdomain extraction (for web clients)
 * 3. User's default tenant (for authenticated users)
 */
final class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            throw new ResourceNotFoundException(
                message: 'Tenant not found or inactive',
                errorCode: 'TENANT_NOT_FOUND',
            );
        }

        // Bind tenant to the container for global access
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }

    /**
     * Resolve the tenant using multiple strategies.
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        // Strategy 1: X-Tenant-ID header (highest priority for API clients)
        if ($tenantId = $request->header('X-Tenant-ID')) {
            return $this->findActiveTenant((int) $tenantId);
        }

        // Strategy 2: Subdomain extraction
        if ($tenant = $this->resolveFromSubdomain($request)) {
            return $tenant;
        }

        // Strategy 3: Authenticated user's tenant
        if ($user = $request->user()) {
            return $this->findActiveTenant($user->tenant_id);
        }

        return null;
    }

    /**
     * Resolve tenant from subdomain.
     */
    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        /** @var string $appDomain */
        $appDomain = config('app.domain', 'localhost');

        // Skip if accessing the main domain directly
        if ($host === $appDomain || $host === 'localhost' || str_starts_with($host, '127.')) {
            return null;
        }

        // Extract subdomain (e.g., 'clinic1.example.com' -> 'clinic1')
        $subdomain = str_replace('.'.$appDomain, '', $host);

        if ($subdomain === $host) {
            return null;
        }

        return Tenant::query()
            ->where('slug', $subdomain)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find an active tenant by ID.
     */
    private function findActiveTenant(?int $tenantId): ?Tenant
    {
        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()
            ->where('id', $tenantId)
            ->where('is_active', true)
            ->first();
    }
}
