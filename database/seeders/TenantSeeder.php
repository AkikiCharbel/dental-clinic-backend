<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo tenant (idempotent)
        $demoTenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            Tenant::factory()->demo()->make()->toArray(),
        );

        $this->command->info("Demo tenant created/found: {$demoTenant->name} (ID: {$demoTenant->id})");
        Log::info('Demo tenant seeded', ['tenant_id' => $demoTenant->id]);

        // Create additional test tenants if they don't exist
        $testTenants = [
            [
                'slug' => 'test-clinic-1',
                'name' => 'Sunrise Dental Center',
            ],
            [
                'slug' => 'test-clinic-2',
                'name' => 'Mountain View Dental',
            ],
            [
                'slug' => 'test-clinic-3',
                'name' => 'City Smile Clinic',
            ],
        ];

        foreach ($testTenants as $tenantData) {
            $tenant = Tenant::firstOrCreate(
                ['slug' => $tenantData['slug']],
                array_merge(
                    Tenant::factory()->make()->toArray(),
                    $tenantData,
                ),
            );

            $this->command->info("Test tenant created/found: {$tenant->name} (ID: {$tenant->id})");
        }

        // Create a trial tenant
        $trialTenant = Tenant::firstOrCreate(
            ['slug' => 'trial-clinic'],
            array_merge(
                Tenant::factory()->trial()->make()->toArray(),
                ['name' => 'Trial Dental Practice', 'slug' => 'trial-clinic'],
            ),
        );

        $this->command->info("Trial tenant created/found: {$trialTenant->name} (ID: {$trialTenant->id})");
    }
}
