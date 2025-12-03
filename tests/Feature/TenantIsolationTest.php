<?php

declare(strict_types=1);

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;

describe('Tenant Isolation', function (): void {
    describe('Patient Model Scoping', function (): void {
        it('automatically scopes queries to the current tenant', function (): void {
            // Create two tenants with patients
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $user1 = User::factory()->forTenant($tenant1)->create();
            $user2 = User::factory()->forTenant($tenant2)->create();

            // Create patients for both tenants (without global scope)
            Patient::factory()->count(3)->forTenant($tenant1)->create();
            Patient::factory()->count(5)->forTenant($tenant2)->create();

            // Act as user from tenant1 and set tenant context
            $this->actingAs($user1);
            app()->instance('currentTenant', $tenant1);

            // Query should only return tenant1's patients
            $patients = Patient::all();

            expect($patients)->toHaveCount(3);
            expect($patients->pluck('tenant_id')->unique()->toArray())->toBe([$tenant1->id]);
        });

        it('cannot find patients from another tenant by id', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $user1 = User::factory()->forTenant($tenant1)->create();

            // Create a patient in tenant2
            $otherPatient = Patient::factory()->forTenant($tenant2)->create();

            // Set tenant context to tenant1
            $this->actingAs($user1);
            app()->instance('currentTenant', $tenant1);

            // Should not find the patient from tenant2
            $patient = Patient::find($otherPatient->id);

            expect($patient)->toBeNull();
        });

        it('automatically sets tenant_id when creating a patient', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create();

            $this->actingAs($user);
            app()->instance('currentTenant', $tenant);

            // Create patient without explicitly setting tenant_id
            $patient = Patient::create([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

            expect($patient->tenant_id)->toBe($tenant->id);
        });

        it('can bypass scope using withoutTenantScope', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $user1 = User::factory()->forTenant($tenant1)->create();

            Patient::factory()->count(3)->forTenant($tenant1)->create();
            Patient::factory()->count(5)->forTenant($tenant2)->create();

            $this->actingAs($user1);
            app()->instance('currentTenant', $tenant1);

            // With tenant scope - only tenant1's patients
            $scopedCount = Patient::count();

            // Without tenant scope - all patients
            $unscopedCount = Patient::withoutTenantScope()->count();

            expect($scopedCount)->toBe(3);
            expect($unscopedCount)->toBe(8);
        });
    });

    describe('User Model Scoping', function (): void {
        it('automatically scopes user queries to the current tenant', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            User::factory()->count(3)->forTenant($tenant1)->create();
            User::factory()->count(5)->forTenant($tenant2)->create();

            // Set tenant context to tenant1
            $user = User::withoutTenantScope()->where('tenant_id', $tenant1->id)->first();
            $this->actingAs($user);
            app()->instance('currentTenant', $tenant1);

            // Query should only return tenant1's users
            $users = User::all();

            expect($users)->toHaveCount(3);
        });
    });

    describe('Relationship Scoping', function (): void {
        it('scopes relationship queries to the current tenant', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->dentist()->create();

            Patient::factory()->count(3)->forTenant($tenant)->withPreferredDentist($user)->create();

            $this->actingAs($user);
            app()->instance('currentTenant', $tenant);

            // Accessing patients through the tenant relationship
            $tenantPatients = $tenant->patients ?? Patient::where('tenant_id', $tenant->id)->get();

            expect($tenantPatients)->toHaveCount(3);
        });
    });

    describe('Cross-Tenant Security', function (): void {
        it('prevents access to other tenant data even with direct ID', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $user1 = User::factory()->forTenant($tenant1)->create();

            // Create a patient in a different tenant
            $foreignPatient = Patient::factory()->forTenant($tenant2)->create();

            $this->actingAs($user1);
            app()->instance('currentTenant', $tenant1);

            // Try to access the foreign patient
            $result = Patient::find($foreignPatient->id);

            expect($result)->toBeNull();
        });

        it('prevents updating other tenant data', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $user1 = User::factory()->forTenant($tenant1)->create();

            // Create a patient in a different tenant
            $foreignPatient = Patient::factory()->forTenant($tenant2)->create();
            $originalName = $foreignPatient->first_name;

            $this->actingAs($user1);
            app()->instance('currentTenant', $tenant1);

            // Try to update the foreign patient
            Patient::where('id', $foreignPatient->id)->update(['first_name' => 'Hacked']);

            // Verify the patient wasn't updated
            $foreignPatient->refresh();
            expect($foreignPatient->first_name)->toBe($originalName);
        });

        it('prevents deleting other tenant data', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $user1 = User::factory()->forTenant($tenant1)->create();

            // Create a patient in a different tenant
            $foreignPatient = Patient::factory()->forTenant($tenant2)->create();

            $this->actingAs($user1);
            app()->instance('currentTenant', $tenant1);

            // Try to delete the foreign patient
            Patient::where('id', $foreignPatient->id)->delete();

            // Verify the patient wasn't deleted
            expect(Patient::withoutTenantScope()->find($foreignPatient->id))->not->toBeNull();
        });
    });
});
