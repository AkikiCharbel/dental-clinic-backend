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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Dental Clinic';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'subscription_status' => SubscriptionStatus::Active,
            'subscription_plan' => SubscriptionPlan::Professional,
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addYear(),
            'default_currency' => 'USD',
            'timezone' => fake()->randomElement(['UTC', 'America/New_York', 'Europe/London', 'Asia/Beirut']),
            'locale' => 'en',
            'country_code' => fake()->randomElement(['US', 'GB', 'LB', 'AE']),
            'settings' => [
                'appointment_duration' => 30,
                'working_hours_start' => '09:00',
                'working_hours_end' => '17:00',
                'working_days' => [1, 2, 3, 4, 5], // Monday to Friday
            ],
            'features' => null,
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
            'subscription_status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->subDays(7),
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the tenant has the basic plan.
     */
    public function basicPlan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_plan' => SubscriptionPlan::Basic,
        ]);
    }

    /**
     * Indicate that the tenant has the professional plan.
     */
    public function professionalPlan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_plan' => SubscriptionPlan::Professional,
        ]);
    }

    /**
     * Indicate that the tenant has the enterprise plan.
     */
    public function enterprisePlan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_plan' => SubscriptionPlan::Enterprise,
        ]);
    }

    /**
     * Indicate that the subscription is past due.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::PastDue,
            'subscription_ends_at' => now()->subDays(15),
        ]);
    }

    /**
     * Indicate that the subscription is canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::Canceled,
            'subscription_ends_at' => now()->addDays(30), // Grace period
        ]);
    }

    /**
     * Indicate that the subscription is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::Expired,
            'subscription_ends_at' => now()->subMonth(),
        ]);
    }

    /**
     * Set specific localization settings.
     */
    public function withLocale(string $currency, string $timezone, string $locale, string $countryCode): static
    {
        return $this->state(fn (array $attributes): array => [
            'default_currency' => $currency,
            'timezone' => $timezone,
            'locale' => $locale,
            'country_code' => $countryCode,
        ]);
    }
}
