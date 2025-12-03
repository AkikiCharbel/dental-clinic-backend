<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'tenant_id' => null,
            'name' => $firstName.' '.$lastName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'title' => null,
            'primary_role' => UserRole::Receptionist,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'license_number' => null,
            'specialization' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'preferences' => [
                'theme' => 'light',
                'notifications' => true,
                'language' => 'en',
            ],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Assign the user to a tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Create a user with a new tenant.
     */
    public function withTenant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => Tenant::factory(),
        ]);
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'primary_role' => UserRole::Admin,
        ]);
    }

    /**
     * Create a dentist user.
     */
    public function dentist(): static
    {
        return $this->state(function (array $attributes): array {
            $specializations = ['General Dentistry', 'Orthodontics', 'Periodontics', 'Endodontics', 'Oral Surgery'];

            return [
                'title' => 'Dr.',
                'primary_role' => UserRole::Dentist,
                'license_number' => 'DDS-'.fake()->unique()->numerify('######'),
                'specialization' => fake()->randomElement($specializations),
            ];
        });
    }

    /**
     * Create a hygienist user.
     */
    public function hygienist(): static
    {
        return $this->state(fn (array $attributes): array => [
            'primary_role' => UserRole::Hygienist,
            'license_number' => 'RDH-'.fake()->unique()->numerify('######'),
        ]);
    }

    /**
     * Create a receptionist user.
     */
    public function receptionist(): static
    {
        return $this->state(fn (array $attributes): array => [
            'primary_role' => UserRole::Receptionist,
        ]);
    }

    /**
     * Create an assistant user.
     */
    public function assistant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'primary_role' => UserRole::Assistant,
        ]);
    }

    /**
     * Set a specific role for the user.
     */
    public function withRole(UserRole $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'primary_role' => $role,
        ]);
    }
}
