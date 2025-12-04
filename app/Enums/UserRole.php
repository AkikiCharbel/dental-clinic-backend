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
     * Check if this role is a provider (can perform clinical work).
     */
    public function isProvider(): bool
    {
        return in_array($this, [self::Dentist, self::Hygienist], true);
    }

    /**
     * Check if this role is administrative.
     */
    public function isAdministrative(): bool
    {
        return in_array($this, [self::Admin, self::Receptionist], true);
    }

    /**
     * Get a human-readable label for the role.
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
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Dentist => 'primary',
            self::Hygienist => 'info',
            self::Receptionist => 'success',
            self::Assistant => 'warning',
        };
    }

    /**
     * Get default permissions for this role.
     *
     * @return array<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => [
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'view_patients',
                'create_patients',
                'edit_patients',
                'delete_patients',
                'view_appointments',
                'create_appointments',
                'edit_appointments',
                'delete_appointments',
                'view_invoices',
                'create_invoices',
                'edit_invoices',
                'delete_invoices',
                'view_reports',
                'manage_settings',
            ],
            self::Dentist => [
                'view_patients',
                'create_patients',
                'edit_patients',
                'view_appointments',
                'create_appointments',
                'edit_appointments',
                'view_treatments',
                'create_treatments',
                'edit_treatments',
                'view_invoices',
                'create_invoices',
            ],
            self::Hygienist => [
                'view_patients',
                'edit_patients',
                'view_appointments',
                'edit_appointments',
                'view_treatments',
                'create_treatments',
            ],
            self::Receptionist => [
                'view_patients',
                'create_patients',
                'edit_patients',
                'view_appointments',
                'create_appointments',
                'edit_appointments',
                'view_invoices',
                'create_invoices',
                'edit_invoices',
            ],
            self::Assistant => [
                'view_patients',
                'view_appointments',
                'view_treatments',
            ],
        };
    }
}
