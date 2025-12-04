<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy for Tenant model authorization.
 */
class TenantPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any tenants.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_tenants');
    }

    /**
     * Determine whether the user can view the tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        // Users can only view their own tenant
        if ($user->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->can('view_tenants');
    }

    /**
     * Determine whether the user can create tenants.
     */
    public function create(User $user): bool
    {
        // Only super admins (no tenant) can create tenants
        return $user->can('create_tenants') && $user->tenant_id === null;
    }

    /**
     * Determine whether the user can update the tenant.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        // Users can only update their own tenant
        if ($user->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->can('update_tenants');
    }

    /**
     * Determine whether the user can delete the tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        // Only super admins (no tenant) can delete tenants
        return $user->can('delete_tenants') && $user->tenant_id === null;
    }

    /**
     * Determine whether the user can restore the tenant.
     */
    public function restore(User $user, Tenant $tenant): bool
    {
        // Only super admins (no tenant) can restore tenants
        return $user->can('delete_tenants') && $user->tenant_id === null;
    }

    /**
     * Determine whether the user can permanently delete the tenant.
     */
    public function forceDelete(User $user, Tenant $tenant): bool
    {
        // Only super admins (no tenant) can force delete tenants
        return $user->isAdmin() && $user->tenant_id === null;
    }
}
