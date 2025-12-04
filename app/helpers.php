<?php

declare(strict_types=1);

use App\Models\Tenant;

if (! function_exists('tenant')) {
    /**
     * Get the current tenant instance.
     */
    function tenant(): ?Tenant
    {
        if (! app()->bound('currentTenant')) {
            return null;
        }

        $tenant = app('currentTenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }
}

if (! function_exists('tenant_id')) {
    /**
     * Get the current tenant ID (UUID string).
     */
    function tenant_id(): ?string
    {
        return tenant()?->id;
    }
}

if (! function_exists('is_tenant_context')) {
    /**
     * Check if we are currently in a tenant context.
     */
    function is_tenant_context(): bool
    {
        return tenant() !== null;
    }
}

if (! function_exists('format_currency')) {
    /**
     * Format a value as currency using tenant settings.
     */
    function format_currency(float|int|string $value, ?string $currency = null): string
    {
        if ($currency === null) {
            $tenant = tenant();
            $currency = $tenant !== null ? $tenant->default_currency : 'USD';
        }
        $value = (float) $value;

        return number_format($value, 2).' '.$currency;
    }
}
