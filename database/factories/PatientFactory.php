<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactMethod;
use App\Enums\Gender;
use App\Enums\PatientStatus;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Patient>
     */
    protected $model = Patient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'tenant_id' => Tenant::factory(),
            'first_name' => fake()->firstName($gender === Gender::Male ? 'male' : 'female'),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->boolean(30) ? fake()->firstName() : null,
            'preferred_name' => fake()->boolean(20) ? fake()->firstName() : null,
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-1 year'),
            'gender' => $gender,
            'phone' => fake()->e164PhoneNumber(),
            'phone_secondary' => fake()->boolean(30) ? fake()->e164PhoneNumber() : null,
            'email' => fake()->boolean(80) ? fake()->unique()->safeEmail() : null,
            'preferred_contact_method' => fake()->randomElement(ContactMethod::cases()),
            'contact_consent' => true,
            'marketing_consent' => fake()->boolean(40),
            'address' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => fake()->country(),
            ],
            'preferred_location_id' => null,
            'preferred_dentist_id' => null,
            'status' => PatientStatus::Active,
            'outstanding_balance' => fake()->boolean(20) ? fake()->randomFloat(2, 50, 500) : 0,
            'outstanding_balance_currency' => 'USD',
            'medical_alerts' => fake()->boolean(15) ? [
                fake()->randomElement([
                    'Penicillin allergy',
                    'Latex allergy',
                    'Heart condition',
                    'Diabetes',
                    'Blood thinners',
                ]),
            ] : null,
            'insurance_info' => fake()->boolean(70) ? [
                'provider' => fake()->company(),
                'policy_number' => fake()->numerify('POL-########'),
                'group_number' => fake()->numerify('GRP-######'),
            ] : null,
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }

    /**
     * Assign the patient to a tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Create an inactive patient.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PatientStatus::Inactive,
        ]);
    }

    /**
     * Create a deceased patient.
     */
    public function deceased(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PatientStatus::Deceased,
        ]);
    }

    /**
     * Create a patient with outstanding balance.
     */
    public function withBalance(float $amount = 150.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'outstanding_balance' => $amount,
        ]);
    }

    /**
     * Create a patient with medical alerts.
     */
    public function withMedicalAlerts(array $alerts = ['Penicillin allergy']): static
    {
        return $this->state(fn (array $attributes): array => [
            'medical_alerts' => $alerts,
        ]);
    }

    /**
     * Create a child patient.
     */
    public function child(): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_of_birth' => fake()->dateTimeBetween('-17 years', '-1 year'),
        ]);
    }

    /**
     * Create an adult patient.
     */
    public function adult(): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_of_birth' => fake()->dateTimeBetween('-65 years', '-18 years'),
        ]);
    }

    /**
     * Create a senior patient.
     */
    public function senior(): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_of_birth' => fake()->dateTimeBetween('-90 years', '-65 years'),
        ]);
    }
}
