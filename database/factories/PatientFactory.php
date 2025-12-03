<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactMethod;
use App\Enums\Gender;
use App\Enums\PatientStatus;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);

        return [
            'tenant_id' => Tenant::factory(),
            'first_name' => $gender === Gender::Male ? fake()->firstNameMale() : fake()->firstNameFemale(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->optional(0.3)->firstName(),
            'preferred_name' => fake()->optional(0.2)->firstName(),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-1 year'),
            'gender' => $gender,
            'phone' => fake()->phoneNumber(),
            'phone_secondary' => fake()->optional(0.3)->phoneNumber(),
            'email' => fake()->optional(0.8)->safeEmail(),
            'preferred_contact_method' => fake()->randomElement(ContactMethod::cases()),
            'contact_consent' => true,
            'marketing_consent' => fake()->boolean(30),
            'address' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => 'US',
            ],
            'preferred_location_id' => null,
            'preferred_dentist_id' => null,
            'status' => PatientStatus::Active,
            'outstanding_balance' => fake()->randomFloat(2, 0, 500),
            'outstanding_balance_currency' => 'USD',
            'medical_notes' => fake()->optional(0.4)->paragraph(),
            'allergies' => fake()->optional(0.2)->randomElements(
                ['Penicillin', 'Latex', 'Lidocaine', 'Aspirin', 'Ibuprofen', 'Codeine'],
                fake()->numberBetween(1, 3),
            ),
            'medications' => fake()->optional(0.3)->randomElements(
                ['Lisinopril', 'Metformin', 'Atorvastatin', 'Levothyroxine', 'Omeprazole', 'Amlodipine'],
                fake()->numberBetween(1, 4),
            ),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'emergency_contact_relationship' => fake()->randomElement(['Spouse', 'Parent', 'Child', 'Sibling', 'Friend']),
            'insurance_provider' => fake()->optional(0.7)->company().' Insurance',
            'insurance_policy_number' => fake()->optional(0.7)->numerify('POL-######'),
            'insurance_group_number' => fake()->optional(0.5)->numerify('GRP-####'),
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    /**
     * Assign the patient to a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Assign a preferred dentist to the patient.
     */
    public function withPreferredDentist(User $dentist): static
    {
        return $this->state(fn (array $attributes): array => [
            'preferred_dentist_id' => $dentist->id,
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
     * Create a minor patient (under 18).
     */
    public function minor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_of_birth' => fake()->dateTimeBetween('-17 years', '-1 year'),
        ]);
    }

    /**
     * Create a patient with outstanding balance.
     */
    public function withBalance(float $amount = 250.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'outstanding_balance' => $amount,
        ]);
    }

    /**
     * Create a patient with no outstanding balance.
     */
    public function noBalance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'outstanding_balance' => 0,
        ]);
    }

    /**
     * Create a patient with allergies.
     *
     * @param  array<string>  $allergies
     */
    public function withAllergies(array $allergies): static
    {
        return $this->state(fn (array $attributes): array => [
            'allergies' => $allergies,
        ]);
    }

    /**
     * Create a patient with insurance.
     */
    public function withInsurance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'insurance_provider' => fake()->company().' Insurance',
            'insurance_policy_number' => 'POL-'.fake()->numerify('######'),
            'insurance_group_number' => 'GRP-'.fake()->numerify('####'),
        ]);
    }

    /**
     * Create a patient without insurance.
     */
    public function withoutInsurance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'insurance_provider' => null,
            'insurance_policy_number' => null,
            'insurance_group_number' => null,
        ]);
    }

    /**
     * Create a male patient.
     */
    public function male(): static
    {
        return $this->state(fn (array $attributes): array => [
            'first_name' => fake()->firstNameMale(),
            'gender' => Gender::Male,
        ]);
    }

    /**
     * Create a female patient.
     */
    public function female(): static
    {
        return $this->state(fn (array $attributes): array => [
            'first_name' => fake()->firstNameFemale(),
            'gender' => Gender::Female,
        ]);
    }
}
