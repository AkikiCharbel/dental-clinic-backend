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
     * The name of the factory's corresponding model.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

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
            'first_name' => $firstName,
            'last_name' => $lastName,
            'title' => null,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'primary_role' => UserRole::Receptionist,
            'phone' => fake()->e164PhoneNumber(),
            'license_number' => null,
            'specialization' => null,
            'is_active' => true,
            'last_login_at' => null,
            'last_login_ip' => null,
            'preferences' => [
                'notifications' => true,
                'theme' => 'light',
            ],
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
     * Set the user's role.
     */
    public function withRole(UserRole $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'primary_role' => $role,
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
        return $this->state(fn (array $attributes): array => [
            'title' => 'Dr.',
            'primary_role' => UserRole::Dentist,
            'license_number' => 'DDS-'.fake()->numerify('######'),
            'specialization' => fake()->randomElement([
                'General Dentistry',
                'Orthodontics',
                'Endodontics',
                'Periodontics',
                'Prosthodontics',
                'Oral Surgery',
                'Pediatric Dentistry',
            ]),
        ]);
    }

    /**
     * Create a hygienist user.
     */
    public function hygienist(): static
    {
        return $this->state(fn (array $attributes): array => [
            'primary_role' => UserRole::Hygienist,
            'license_number' => 'RDH-'.fake()->numerify('######'),
            'specialization' => 'Dental Hygiene',
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
     * Set specific email and name for demo users.
     */
    public function withCredentials(string $email, string $firstName, string $lastName, ?string $title = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'title' => $title,
        ]);
    }
}
