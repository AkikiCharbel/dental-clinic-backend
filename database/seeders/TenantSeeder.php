<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDemoTenant();
        $this->createTestTenants();
    }

    /**
     * Create the main demo tenant.
     */
    private function createDemoTenant(): void
    {
        Tenant::updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Healthcare Clinic',
                'email' => 'demo@dentalflow.com',
                'phone' => '+961 1 234567',
                'address' => '123 Medical Center Drive, Beirut, Lebanon',
                'subscription_status' => SubscriptionStatus::Active,
                'subscription_plan' => SubscriptionPlan::Professional,
                'subscription_ends_at' => now()->addYear(),
                'default_currency' => 'USD',
                'timezone' => 'Asia/Beirut',
                'locale' => 'en',
                'country_code' => 'LB',
                'settings' => [
                    'appointment_duration' => 30,
                    'working_hours_start' => '09:00',
                    'working_hours_end' => '18:00',
                    'working_days' => [1, 2, 3, 4, 5, 6], // Monday to Saturday
                    'appointment_reminder_hours' => 24,
                    'allow_online_booking' => true,
                ],
                'features' => null, // Use plan defaults
                'is_active' => true,
            ],
        );

        $this->command->info('Created demo tenant: demo@dentalflow.com');
    }

    /**
     * Create additional test tenants.
     */
    private function createTestTenants(): void
    {
        $testTenants = [
            [
                'name' => 'Smile Dental Center',
                'slug' => 'smile-dental',
                'email' => 'contact@smile-dental.example.com',
                'phone' => '+1 555 123 4567',
                'subscription_status' => SubscriptionStatus::Active,
                'subscription_plan' => SubscriptionPlan::Enterprise,
                'country_code' => 'US',
                'timezone' => 'America/New_York',
            ],
            [
                'name' => 'Family Dentistry',
                'slug' => 'family-dentistry',
                'email' => 'info@family-dentistry.example.com',
                'phone' => '+44 20 7946 0958',
                'subscription_status' => SubscriptionStatus::Trial,
                'subscription_plan' => SubscriptionPlan::Professional,
                'trial_ends_at' => now()->addDays(14),
                'country_code' => 'GB',
                'timezone' => 'Europe/London',
            ],
            [
                'name' => 'Premium Dental Care',
                'slug' => 'premium-dental',
                'email' => 'admin@premium-dental.example.com',
                'phone' => '+971 4 123 4567',
                'subscription_status' => SubscriptionStatus::Active,
                'subscription_plan' => SubscriptionPlan::Basic,
                'country_code' => 'AE',
                'timezone' => 'Asia/Dubai',
            ],
            [
                'name' => 'Inactive Clinic',
                'slug' => 'inactive-clinic',
                'email' => 'contact@inactive-clinic.example.com',
                'phone' => '+1 555 000 0000',
                'subscription_status' => SubscriptionStatus::Expired,
                'subscription_plan' => SubscriptionPlan::Basic,
                'subscription_ends_at' => now()->subMonth(),
                'is_active' => false,
                'country_code' => 'US',
                'timezone' => 'America/Los_Angeles',
            ],
        ];

        foreach ($testTenants as $tenantData) {
            Tenant::updateOrCreate(
                ['slug' => $tenantData['slug']],
                array_merge([
                    'address' => '123 Test Street',
                    'default_currency' => 'USD',
                    'locale' => 'en',
                    'settings' => [
                        'appointment_duration' => 30,
                        'working_hours_start' => '09:00',
                        'working_hours_end' => '17:00',
                        'working_days' => [1, 2, 3, 4, 5],
                    ],
                    'is_active' => true,
                ], $tenantData),
            );
        }

        $this->command->info('Created '.count($testTenants).' test tenants.');
    }
}
