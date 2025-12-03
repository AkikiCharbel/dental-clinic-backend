<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get demo tenant
        $demoTenant = Tenant::where('slug', 'demo')->first();

        if (! $demoTenant) {
            $this->command->error('Demo tenant not found. Run TenantSeeder first.');

            return;
        }

        // Create demo users (idempotent)
        $demoUsers = [
            [
                'email' => 'admin@demo.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'factory_method' => 'admin',
            ],
            [
                'email' => 'dentist@demo.com',
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'title' => 'Dr.',
                'factory_method' => 'dentist',
            ],
            [
                'email' => 'receptionist@demo.com',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'factory_method' => 'receptionist',
            ],
        ];

        $this->command->info('');
        $this->command->info('=== Demo Tenant Users ===');
        $this->command->info("Tenant: {$demoTenant->name} (ID: {$demoTenant->id})");
        $this->command->info('');

        foreach ($demoUsers as $userData) {
            $user = User::withoutTenantScope()
                ->where('tenant_id', $demoTenant->id)
                ->where('email', $userData['email'])
                ->first();

            if (! $user) {
                $factory = User::factory()
                    ->forTenant($demoTenant)
                    ->{$userData['factory_method']}();

                $user = $factory->create([
                    'email' => $userData['email'],
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'title' => $userData['title'] ?? null,
                ]);
            }

            $this->command->info("  [{$user->primary_role->label()}] {$user->email} / password");
            Log::info('Demo user seeded', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_id' => $demoTenant->id,
            ]);
        }

        // Create users for other test tenants
        $otherTenants = Tenant::where('slug', '!=', 'demo')->get();

        foreach ($otherTenants as $tenant) {
            $this->command->info('');
            $this->command->info("=== {$tenant->name} Users ===");
            $this->command->info("Tenant ID: {$tenant->id}");
            $this->command->info('');

            // Create one admin
            $admin = $this->createUserIfNotExists($tenant, 'admin', "admin@{$tenant->slug}.test");
            if ($admin) {
                $this->command->info("  [Admin] {$admin->email} / password");
            }

            // Create two providers
            for ($i = 1; $i <= 2; $i++) {
                $dentist = $this->createUserIfNotExists($tenant, 'dentist', "dentist{$i}@{$tenant->slug}.test");
                if ($dentist) {
                    $this->command->info("  [Dentist] {$dentist->email} / password");
                }
            }

            // Create two staff
            $receptionist = $this->createUserIfNotExists($tenant, 'receptionist', "receptionist@{$tenant->slug}.test");
            if ($receptionist) {
                $this->command->info("  [Receptionist] {$receptionist->email} / password");
            }

            $assistant = $this->createUserIfNotExists($tenant, 'assistant', "assistant@{$tenant->slug}.test");
            if ($assistant) {
                $this->command->info("  [Assistant] {$assistant->email} / password");
            }
        }

        $this->command->info('');
        $this->command->info('All users use password: password');
        $this->command->info('');
    }

    /**
     * Create a user if they don't already exist.
     */
    private function createUserIfNotExists(Tenant $tenant, string $role, string $email): ?User
    {
        $existingUser = User::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($existingUser) {
            return $existingUser;
        }

        return User::factory()
            ->forTenant($tenant)
            ->{$role}()
            ->create(['email' => $email]);
    }
}
