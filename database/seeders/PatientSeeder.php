<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedDemoPatients();
        $this->seedTestPatients();
    }

    /**
     * Create sample patients for the demo tenant.
     */
    private function seedDemoPatients(): void
    {
        $demoTenant = Tenant::where('slug', 'demo')->first();

        if (! $demoTenant) {
            $this->command->warn('Demo tenant not found. Run TenantSeeder first.');

            return;
        }

        // Get a dentist for preferred_dentist_id
        $dentist = User::withoutTenantScope()
            ->where('tenant_id', $demoTenant->id)
            ->where('primary_role', UserRole::Dentist->value)
            ->first();

        // Create 20 patients for demo tenant
        Patient::factory()
            ->count(20)
            ->forTenant($demoTenant)
            ->when($dentist, fn ($factory) => $factory->withPreferredDentist($dentist))
            ->create();

        // Create some specific scenarios
        Patient::factory()
            ->forTenant($demoTenant)
            ->withBalance(500.00)
            ->withAllergies(['Penicillin', 'Latex'])
            ->create([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+961 71 123 456',
            ]);

        Patient::factory()
            ->forTenant($demoTenant)
            ->minor()
            ->noBalance()
            ->create([
                'first_name' => 'Emma',
                'last_name' => 'Wilson',
                'email' => 'emma.wilson@example.com',
            ]);

        Patient::factory()
            ->forTenant($demoTenant)
            ->inactive()
            ->create([
                'first_name' => 'Inactive',
                'last_name' => 'Patient',
                'email' => 'inactive.patient@example.com',
            ]);

        $this->command->info('Created 23 patients for demo tenant.');
    }

    /**
     * Create random patients for test tenants.
     */
    private function seedTestPatients(): void
    {
        $testTenants = Tenant::where('slug', '!=', 'demo')
            ->where('is_active', true)
            ->get();

        foreach ($testTenants as $tenant) {
            Patient::factory()
                ->count(10)
                ->forTenant($tenant)
                ->create();
        }

        $this->command->info('Created patients for '.$testTenants->count().' test tenants.');
    }
}
