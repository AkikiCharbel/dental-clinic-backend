<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Domain\ResourceNotFoundException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
                message: 'Tenant not found or could not be identified',
                errorCode: 'TENANT_NOT_FOUND',
            );
        }

        // Verify tenant is active and subscription allows access
        if (! $tenant->isActive()) {
            throw new UnauthorizedActionException(
                message: 'Tenant account is inactive or subscription has expired',
                errorCode: 'TENANT_INACTIVE',
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
            return $this->findTenantById($tenantId);
        }

        // Strategy 2: Subdomain extraction
        if ($tenant = $this->resolveFromSubdomain($request)) {
            return $tenant;
        }

        // Strategy 3: Authenticated user's tenant
        if ($user = $request->user()) {
            return $user->tenant;
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
            ->first();
    }

    /**
     * Find a tenant by ID (UUID).
     */
    private function findTenantById(string $tenantId): ?Tenant
    {
        // Validate UUID format
        if (! Str::isUuid($tenantId)) {
            return null;
        }

        return Tenant::query()
            ->where('id', $tenantId)
            ->first();
    }
}
