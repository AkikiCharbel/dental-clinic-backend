<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy for User model authorization.
 */
class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_users');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can always view themselves
        if ($user->id === $model->id) {
            return true;
        }

        // Ensure user belongs to same tenant
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        return $user->can('view_users');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('create_users');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can always update themselves (profile)
        if ($user->id === $model->id) {
            return true;
        }

        // Ensure user belongs to same tenant
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        return $user->can('update_users');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete yourself
        if ($user->id === $model->id) {
            return false;
        }

        // Ensure user belongs to same tenant
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        return $user->can('delete_users');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // Ensure user belongs to same tenant
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        return $user->can('delete_users');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Cannot force delete yourself
        if ($user->id === $model->id) {
            return false;
        }

        // Ensure user belongs to same tenant
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        // Only admins can force delete
        return $user->isAdmin();
    }
}
