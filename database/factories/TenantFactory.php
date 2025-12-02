<?php

declare(strict_types=1);

namespace Database\Factories;

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
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'settings' => [
                'timezone' => 'UTC',
                'language' => 'en',
                'currency' => 'USD',
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
}
