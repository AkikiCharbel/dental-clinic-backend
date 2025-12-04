<?php

declare(strict_types=1);

use App\Enums\ContactMethod;
use App\Enums\Gender;
use App\Enums\PatientStatus;
use App\Models\Patient;
use App\Models\User;

describe('Patient Model', function (): void {
    describe('Factory and Creation', function (): void {
        it('creates a patient with required attributes', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

            expect($patient->id)->not->toBeNull();
            expect($patient->tenant_id)->toBe($tenant->id);
            expect($patient->first_name)->not->toBeNull();
            expect($patient->last_name)->not->toBeNull();
        });

        it('auto-assigns tenant_id from current tenant', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create();

            expect($patient->tenant_id)->toBe($tenant->id);
        });

        it('uses UUID as primary key', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

            expect(strlen($patient->id))->toBe(36);
            expect($patient->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });
    });

    describe('Attributes and Accessors', function (): void {
        it('returns full name with middle name', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'John',
                'middle_name' => 'William',
                'last_name' => 'Doe',
            ]);

            expect($patient->full_name)->toBe('John William Doe');
        });

        it('returns full name without middle name', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'Jane',
                'middle_name' => null,
                'last_name' => 'Smith',
            ]);

            expect($patient->full_name)->toBe('Jane Smith');
        });

        it('returns preferred name when set', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'William',
                'last_name' => 'Johnson',
                'preferred_name' => 'Bill',
            ]);

            expect($patient->name)->toBe('Bill');
        });

        it('calculates age from date of birth', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'date_of_birth' => now()->subYears(30),
            ]);

            expect($patient->age)->toBe(30);
        });

        it('returns null age when no date of birth', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'date_of_birth' => null,
            ]);

            expect($patient->age)->toBeNull();
        });
    });

    describe('Enum Casting', function (): void {
        it('casts status to PatientStatus enum', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'status' => 'active',
            ]);

            expect($patient->status)->toBeInstanceOf(PatientStatus::class);
            expect($patient->status)->toBe(PatientStatus::Active);
        });

        it('casts gender to Gender enum', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'gender' => 'female',
            ]);

            expect($patient->gender)->toBeInstanceOf(Gender::class);
            expect($patient->gender)->toBe(Gender::Female);
        });

        it('casts preferred_contact_method to ContactMethod enum', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'preferred_contact_method' => 'email',
            ]);

            expect($patient->preferred_contact_method)->toBeInstanceOf(ContactMethod::class);
            expect($patient->preferred_contact_method)->toBe(ContactMethod::Email);
        });
    });

    describe('JSON Fields', function (): void {
        it('stores address as JSON', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $address = [
                'street' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'CA',
                'postal_code' => '12345',
                'country' => 'USA',
            ];

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'address' => $address,
            ]);

            $patient->refresh();

            expect($patient->address)->toBe($address);
        });

        it('stores medical_alerts as JSON', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $alerts = [
                ['type' => 'allergy', 'description' => 'Penicillin'],
                ['type' => 'condition', 'description' => 'Diabetes'],
            ];

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'medical_alerts' => $alerts,
            ]);

            $patient->refresh();

            expect($patient->medical_alerts)->toBe($alerts);
        });

        it('stores insurance_info as JSON', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $insurance = [
                'provider' => 'Blue Cross',
                'policy_number' => 'BC123456',
                'group_number' => 'GRP001',
            ];

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'insurance_info' => $insurance,
            ]);

            $patient->refresh();

            expect($patient->insurance_info)->toBe($insurance);
        });
    });

    describe('Helper Methods', function (): void {
        it('correctly identifies active patient', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $activePatient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'status' => PatientStatus::Active,
            ]);

            $inactivePatient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'status' => PatientStatus::Inactive,
            ]);

            expect($activePatient->isActive())->toBeTrue();
            expect($inactivePatient->isActive())->toBeFalse();
        });

        it('correctly identifies patients with medical alerts', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patientWithAlerts = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'medical_alerts' => [['type' => 'allergy', 'description' => 'Latex']],
            ]);

            $patientWithoutAlerts = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'medical_alerts' => null,
            ]);

            expect($patientWithAlerts->hasMedicalAlerts())->toBeTrue();
            expect($patientWithoutAlerts->hasMedicalAlerts())->toBeFalse();
        });

        it('correctly identifies patients with outstanding balance', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patientWithBalance = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'outstanding_balance' => 150.00,
            ]);

            $patientNoBalance = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'outstanding_balance' => 0,
            ]);

            expect($patientWithBalance->hasOutstandingBalance())->toBeTrue();
            expect($patientNoBalance->hasOutstandingBalance())->toBeFalse();
        });

        it('formats address correctly', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'Los Angeles',
                    'state' => 'CA',
                    'postal_code' => '90001',
                ],
            ]);

            expect($patient->getFormattedAddress())->toBe('123 Main St, Los Angeles, CA, 90001');
        });

        it('returns null for empty address', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'address' => null,
            ]);

            expect($patient->getFormattedAddress())->toBeNull();
        });
    });

    describe('Scopes', function (): void {
        it('filters active patients with scope', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            Patient::factory()->count(3)->create([
                'tenant_id' => $tenant->id,
                'status' => PatientStatus::Active,
            ]);

            Patient::factory()->count(2)->create([
                'tenant_id' => $tenant->id,
                'status' => PatientStatus::Inactive,
            ]);

            expect(Patient::active()->count())->toBe(3);
        });

        it('filters patients with outstanding balance', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            Patient::factory()->count(2)->create([
                'tenant_id' => $tenant->id,
                'outstanding_balance' => 100.00,
            ]);

            Patient::factory()->count(3)->create([
                'tenant_id' => $tenant->id,
                'outstanding_balance' => 0,
            ]);

            expect(Patient::withOutstandingBalance()->count())->toBe(2);
        });

        it('searches patients by name', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'John',
                'last_name' => 'Smith',
            ]);

            Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
            ]);

            expect(Patient::search('John')->count())->toBe(1);
            expect(Patient::search('Smith')->count())->toBe(1);
            expect(Patient::search('Jane')->count())->toBe(1);
        });
    });

    describe('Relationships', function (): void {
        it('belongs to a tenant', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

            expect($patient->tenant->id)->toBe($tenant->id);
        });

        it('can have a preferred dentist', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $dentist = User::factory()->dentist()->create(['tenant_id' => $tenant->id]);
            $patient = Patient::factory()->create([
                'tenant_id' => $tenant->id,
                'preferred_dentist_id' => $dentist->id,
            ]);

            expect($patient->preferredDentist->id)->toBe($dentist->id);
        });
    });

    describe('Soft Deletes', function (): void {
        it('soft deletes patient', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
            $patientId = $patient->id;

            $patient->delete();

            expect(Patient::find($patientId))->toBeNull();
            expect(Patient::withTrashed()->find($patientId))->not->toBeNull();
        });

        it('can restore soft deleted patient', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
            $patientId = $patient->id;

            $patient->delete();
            $patient->restore();

            expect(Patient::find($patientId))->not->toBeNull();
        });
    });
});
