<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');
        $this->command->newLine();

        // Seed in order of dependencies
        $this->call([
            RoleAndPermissionSeeder::class,  // Roles and permissions first
            TenantSeeder::class,              // Tenants before users
            UserSeeder::class,                // Users need tenants
            PatientSeeder::class,             // Patients need tenants and users
        ]);

        $this->command->newLine();
        $this->command->info('Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('Demo Tenant ID: '.\App\Models\Tenant::where('slug', 'demo')->first()?->id);
    }
}
