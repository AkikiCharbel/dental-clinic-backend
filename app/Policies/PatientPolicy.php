<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy for Patient model authorization.
 */
class PatientPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any patients.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_patients');
    }

    /**
     * Determine whether the user can view the patient.
     */
    public function view(User $user, Patient $patient): bool
    {
        // Ensure patient belongs to same tenant
        if ($user->tenant_id !== $patient->tenant_id) {
            return false;
        }

        return $user->can('view_patients');
    }

    /**
     * Determine whether the user can create patients.
     */
    public function create(User $user): bool
    {
        return $user->can('create_patients');
    }

    /**
     * Determine whether the user can update the patient.
     */
    public function update(User $user, Patient $patient): bool
    {
        // Ensure patient belongs to same tenant
        if ($user->tenant_id !== $patient->tenant_id) {
            return false;
        }

        return $user->can('update_patients');
    }

    /**
     * Determine whether the user can delete the patient.
     */
    public function delete(User $user, Patient $patient): bool
    {
        // Ensure patient belongs to same tenant
        if ($user->tenant_id !== $patient->tenant_id) {
            return false;
        }

        return $user->can('delete_patients');
    }

    /**
     * Determine whether the user can restore the patient.
     */
    public function restore(User $user, Patient $patient): bool
    {
        // Ensure patient belongs to same tenant
        if ($user->tenant_id !== $patient->tenant_id) {
            return false;
        }

        return $user->can('delete_patients');
    }

    /**
     * Determine whether the user can permanently delete the patient.
     */
    public function forceDelete(User $user, Patient $patient): bool
    {
        // Ensure patient belongs to same tenant
        if ($user->tenant_id !== $patient->tenant_id) {
            return false;
        }

        // Only admins can force delete
        return $user->isAdmin();
    }
}
