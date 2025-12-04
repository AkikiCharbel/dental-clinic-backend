<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
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

        // Check if patients already exist for demo tenant
        $existingCount = Patient::withoutTenantScope()
            ->where('tenant_id', $demoTenant->id)
            ->count();

        if ($existingCount > 0) {
            $this->command->info("Demo tenant already has {$existingCount} patients. Skipping...");

            return;
        }

        $this->command->info('Creating patients for demo tenant...');

        // Create a mix of patients
        // Adults
        Patient::factory()
            ->count(15)
            ->forTenant($demoTenant)
            ->adult()
            ->create();

        // Children
        Patient::factory()
            ->count(5)
            ->forTenant($demoTenant)
            ->child()
            ->create();

        // Seniors
        Patient::factory()
            ->count(5)
            ->forTenant($demoTenant)
            ->senior()
            ->create();

        // Patients with medical alerts
        Patient::factory()
            ->count(3)
            ->forTenant($demoTenant)
            ->withMedicalAlerts(['Penicillin allergy', 'Latex sensitivity'])
            ->create();

        // Patients with outstanding balance
        Patient::factory()
            ->count(3)
            ->forTenant($demoTenant)
            ->withBalance(250.00)
            ->create();

        // Inactive patients
        Patient::factory()
            ->count(2)
            ->forTenant($demoTenant)
            ->inactive()
            ->create();

        $totalCreated = Patient::withoutTenantScope()
            ->where('tenant_id', $demoTenant->id)
            ->count();

        $this->command->info("Created {$totalCreated} patients for demo tenant.");

        // Create patients for other tenants
        $otherTenants = Tenant::where('slug', '!=', 'demo')->get();

        foreach ($otherTenants as $tenant) {
            $existingCount = Patient::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->count();

            if ($existingCount > 0) {
                $this->command->info("{$tenant->name} already has {$existingCount} patients. Skipping...");

                continue;
            }

            // Create fewer patients for test tenants
            Patient::factory()
                ->count(10)
                ->forTenant($tenant)
                ->create();

            $this->command->info("Created 10 patients for {$tenant->name}");
        }
    }
}
