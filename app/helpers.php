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
     * Get the current tenant ID.
     */
    function tenant_id(): ?int
    {
        return tenant()?->id;
    }
}
