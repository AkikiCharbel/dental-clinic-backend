<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Tenant>
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinicTypes = ['Dental', 'Family Dental', 'Orthodontic', 'Pediatric Dental', 'Cosmetic Dental'];
        $name = fake()->randomElement($clinicTypes).' '.fake()->randomElement(['Clinic', 'Center', 'Practice', 'Care']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'address' => fake()->address(),
            'country_code' => fake()->countryCode(),
            'subscription_status' => SubscriptionStatus::Active,
            'subscription_plan' => SubscriptionPlan::Professional,
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addYear(),
            'default_currency' => 'USD',
            'timezone' => fake()->timezone(),
            'locale' => 'en',
            'features' => [
                'appointments' => true,
                'billing' => true,
                'reports' => true,
                'sms_notifications' => false,
            ],
            'settings' => [
                'working_hours' => [
                    'start' => '09:00',
                    'end' => '17:00',
                ],
                'appointment_duration' => 30,
                'reminder_hours' => 24,
            ],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tenant is on trial.
     */
    public function trial(int $daysRemaining = 14): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::Trial,
            'subscription_plan' => SubscriptionPlan::Professional,
            'trial_ends_at' => now()->addDays($daysRemaining),
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the tenant's trial has expired.
     */
    public function expiredTrial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::Expired,
            'subscription_plan' => SubscriptionPlan::Basic,
            'trial_ends_at' => now()->subDays(7),
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * Set specific subscription plan.
     */
    public function withPlan(SubscriptionPlan $plan): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_plan' => $plan,
        ]);
    }

    /**
     * Configure with basic plan.
     */
    public function basic(): static
    {
        return $this->withPlan(SubscriptionPlan::Basic);
    }

    /**
     * Configure with professional plan.
     */
    public function professional(): static
    {
        return $this->withPlan(SubscriptionPlan::Professional);
    }

    /**
     * Configure with enterprise plan.
     */
    public function enterprise(): static
    {
        return $this->withPlan(SubscriptionPlan::Enterprise);
    }

    /**
     * Indicate subscription is past due.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::PastDue,
            'subscription_ends_at' => now()->subDays(7),
        ]);
    }

    /**
     * Indicate subscription is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::Cancelled,
            'subscription_ends_at' => now()->subDays(30),
        ]);
    }

    /**
     * Create demo tenant with specific slug.
     */
    public function demo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Demo Healthcare Clinic',
            'slug' => 'demo',
            'email' => 'demo@dentalflow.com',
            'phone' => '+961 1 234567',
            'country_code' => 'LB',
            'subscription_status' => SubscriptionStatus::Active,
            'subscription_plan' => SubscriptionPlan::Professional,
            'default_currency' => 'USD',
            'timezone' => 'Asia/Beirut',
            'locale' => 'en',
        ]);
    }
}
