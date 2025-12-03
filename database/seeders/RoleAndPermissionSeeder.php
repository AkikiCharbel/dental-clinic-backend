<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createPermissions();
        $this->createRoles();
    }

    /**
     * Create all application permissions.
     */
    private function createPermissions(): void
    {
        $permissions = [
            // User management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // Patient management
            'view_patients',
            'create_patients',
            'edit_patients',
            'delete_patients',

            // Appointment management
            'view_appointments',
            'create_appointments',
            'edit_appointments',
            'delete_appointments',

            // Treatment management
            'view_treatments',
            'create_treatments',
            'edit_treatments',
            'delete_treatments',

            // Invoice management
            'view_invoices',
            'create_invoices',
            'edit_invoices',
            'delete_invoices',

            // Reports
            'view_reports',
            'export_reports',

            // Settings
            'manage_settings',
            'manage_tenant_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->command->info('Created '.count($permissions).' permissions.');
    }

    /**
     * Create roles with their default permissions.
     */
    private function createRoles(): void
    {
        foreach (UserRole::cases() as $userRole) {
            $role = Role::firstOrCreate([
                'name' => $userRole->value,
                'guard_name' => 'web',
            ]);

            $permissions = $userRole->defaultPermissions();
            $role->syncPermissions($permissions);

            $this->command->info("Created role '{$userRole->label()}' with ".count($permissions).' permissions.');
        }
    }
}
