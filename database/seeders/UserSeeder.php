<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDemoUsers();
        $this->createTestUsersForOtherTenants();
    }

    /**
     * Create demo users for the demo tenant.
     */
    private function createDemoUsers(): void
    {
        $demoTenant = Tenant::where('slug', 'demo')->first();

        if (! $demoTenant) {
            $this->command->warn('Demo tenant not found. Run TenantSeeder first.');

            return;
        }

        $demoUsers = [
            [
                'name' => 'Admin User',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@demo.com',
                'primary_role' => UserRole::Admin,
                'title' => null,
                'phone' => '+961 1 111 1111',
            ],
            [
                'name' => 'Dr. Sarah Johnson',
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'dentist@demo.com',
                'primary_role' => UserRole::Dentist,
                'title' => 'Dr.',
                'phone' => '+961 1 222 2222',
                'license_number' => 'DDS-123456',
                'specialization' => 'General Dentistry',
            ],
            [
                'name' => 'Dr. Michael Chen',
                'first_name' => 'Michael',
                'last_name' => 'Chen',
                'email' => 'michael.chen@demo.com',
                'primary_role' => UserRole::Dentist,
                'title' => 'Dr.',
                'phone' => '+961 1 333 3333',
                'license_number' => 'DDS-234567',
                'specialization' => 'Orthodontics',
            ],
            [
                'name' => 'Emily Davis',
                'first_name' => 'Emily',
                'last_name' => 'Davis',
                'email' => 'hygienist@demo.com',
                'primary_role' => UserRole::Hygienist,
                'title' => null,
                'phone' => '+961 1 444 4444',
                'license_number' => 'RDH-345678',
            ],
            [
                'name' => 'Jane Smith',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'receptionist@demo.com',
                'primary_role' => UserRole::Receptionist,
                'title' => null,
                'phone' => '+961 1 555 5555',
            ],
            [
                'name' => 'Tom Wilson',
                'first_name' => 'Tom',
                'last_name' => 'Wilson',
                'email' => 'assistant@demo.com',
                'primary_role' => UserRole::Assistant,
                'title' => null,
                'phone' => '+961 1 666 6666',
            ],
        ];

        foreach ($demoUsers as $userData) {
            $user = User::withoutTenantScope()->updateOrCreate(
                [
                    'email' => $userData['email'],
                    'tenant_id' => $demoTenant->id,
                ],
                array_merge($userData, [
                    'tenant_id' => $demoTenant->id,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'preferences' => [
                        'theme' => 'light',
                        'notifications' => true,
                        'language' => 'en',
                    ],
                ]),
            );

            // Assign role using Spatie Permission
            $user->syncRoles([$userData['primary_role']->value]);
        }

        $this->command->info('Created '.count($demoUsers).' demo users for the demo tenant.');
        $this->command->newLine();
        $this->command->info('Demo User Credentials:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', 'admin@demo.com', 'password'],
                ['Dentist', 'dentist@demo.com', 'password'],
                ['Hygienist', 'hygienist@demo.com', 'password'],
                ['Receptionist', 'receptionist@demo.com', 'password'],
                ['Assistant', 'assistant@demo.com', 'password'],
            ],
        );
    }

    /**
     * Create test users for other test tenants.
     */
    private function createTestUsersForOtherTenants(): void
    {
        $testTenants = Tenant::where('slug', '!=', 'demo')
            ->where('is_active', true)
            ->get();

        foreach ($testTenants as $tenant) {
            // Create one admin for each tenant
            $admin = User::withoutTenantScope()->updateOrCreate(
                [
                    'email' => 'admin@'.$tenant->slug.'.test',
                    'tenant_id' => $tenant->id,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Admin '.$tenant->name,
                    'first_name' => 'Admin',
                    'last_name' => $tenant->name,
                    'primary_role' => UserRole::Admin,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
            $admin->syncRoles([UserRole::Admin->value]);

            // Create two providers using factory
            User::factory()
                ->count(2)
                ->forTenant($tenant)
                ->dentist()
                ->create()
                ->each(fn (User $user) => $user->syncRoles([UserRole::Dentist->value]));

            // Create two staff using factory
            User::factory()
                ->count(2)
                ->forTenant($tenant)
                ->receptionist()
                ->create()
                ->each(fn (User $user) => $user->syncRoles([UserRole::Receptionist->value]));
        }

        $this->command->info('Created test users for '.$testTenants->count().' test tenants.');
    }
}
