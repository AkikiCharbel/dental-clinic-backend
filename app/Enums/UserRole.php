<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Dentist = 'dentist';
    case Hygienist = 'hygienist';
    case Receptionist = 'receptionist';
    case Assistant = 'assistant';

    /**
     * Get human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Dentist => 'Dentist',
            self::Hygienist => 'Dental Hygienist',
            self::Receptionist => 'Receptionist',
            self::Assistant => 'Dental Assistant',
        };
    }

    /**
     * Check if role is a clinical provider.
     */
    public function isProvider(): bool
    {
        return in_array($this, [self::Dentist, self::Hygienist], true);
    }

    /**
     * Check if role has administrative privileges.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Get all roles as options for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $role) => $role->label(), self::cases()),
        );
    }

    /**
     * Get all provider roles.
     *
     * @return array<self>
     */
    public static function providers(): array
    {
        return [self::Dentist, self::Hygienist];
    }

    /**
     * Get all staff roles (non-provider).
     *
     * @return array<self>
     */
    public static function staff(): array
    {
        return [self::Receptionist, self::Assistant];
    }

    /**
     * Get default permissions for this role.
     *
     * Returns an array of permission names that should be assigned to this role.
     *
     * @return array<int, string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => ['*'], // Admins get all permissions via gate
            self::Dentist => [
                'view_patients',
                'create_patients',
                'update_patients',
                'view_users',
                'view_tenants',
            ],
            self::Hygienist => [
                'view_patients',
                'update_patients',
                'view_users',
            ],
            self::Receptionist => [
                'view_patients',
                'create_patients',
                'update_patients',
                'view_users',
            ],
            self::Assistant => [
                'view_patients',
                'view_users',
            ],
        };
    }

    /**
     * Get all permissions for all roles as a flat array.
     *
     * @return array<int, string>
     */
    public static function allDefaultPermissions(): array
    {
        $permissions = [];

        foreach (self::cases() as $role) {
            $rolePermissions = $role->defaultPermissions();
            // Skip wildcard '*' as it's handled by Gate
            if ($rolePermissions !== ['*']) {
                $permissions = array_merge($permissions, $rolePermissions);
            }
        }

        return array_values(array_unique($permissions));
    }
}
