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
        $this->command->info('');
        $this->command->info('====================================');
        $this->command->info('  Dental Clinic Database Seeder');
        $this->command->info('====================================');
        $this->command->info('');

        $this->call([
            RoleAndPermissionSeeder::class,
            TenantSeeder::class,
            UserSeeder::class,
            PatientSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('====================================');
        $this->command->info('  Seeding Complete!');
        $this->command->info('====================================');
        $this->command->info('');
        $this->command->info('Demo Credentials:');
        $this->command->info('  Tenant: demo');
        $this->command->info('  Admin: admin@demo.com / password');
        $this->command->info('  Dentist: dentist@demo.com / password');
        $this->command->info('  Receptionist: receptionist@demo.com / password');
        $this->command->info('');
    }
}
