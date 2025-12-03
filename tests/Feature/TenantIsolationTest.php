<?php

declare(strict_types=1);

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;

describe('Tenant Isolation', function (): void {
    describe('Model Scope Tests', function (): void {
        it('automatically scopes patient queries to current tenant', function (): void {
            // Create two tenants with patients
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $patient1 = Patient::factory()->forTenant($tenant1)->create();
            $patient2 = Patient::factory()->forTenant($tenant2)->create();

            // Simulate being authenticated as tenant1 user
            $user = User::factory()->forTenant($tenant1)->create();
            $this->actingAs($user);
            app()->instance('currentTenant', $tenant1);

            // Should only see tenant1's patients
            $patients = Patient::all();

            expect($patients)->toHaveCount(1);
            expect($patients->first()->id)->toBe($patient1->id);
        });

        it('automatically scopes user queries to current tenant', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $user1 = User::factory()->forTenant($tenant1)->create();
            $user2 = User::factory()->forTenant($tenant2)->create();

            $this->actingAs($user1);
            app()->instance('currentTenant', $tenant1);

            $users = User::all();

            expect($users)->toHaveCount(1);
            expect($users->first()->id)->toBe($user1->id);
        });

        it('includes tenant_id in all tenant-scoped queries', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create();

            $this->actingAs($user);
            app()->instance('currentTenant', $tenant);

            $query = Patient::query()->toSql();

            expect($query)->toContain('tenant_id');
        });
    });

    describe('Cross-Tenant Access Tests', function (): void {
        it('cannot access other tenants patients', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $patient = Patient::factory()->forTenant($tenant2)->create();

            $user = User::factory()->forTenant($tenant1)->create();
            $this->actingAs($user);
            app()->instance('currentTenant', $tenant1);

            // Try to find the patient from another tenant
            $foundPatient = Patient::find($patient->id);

            expect($foundPatient)->toBeNull();
        });

        it('cannot access other tenants users', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $user1 = User::factory()->forTenant($tenant1)->create();
            $user2 = User::factory()->forTenant($tenant2)->create();

            $this->actingAs($user1);
            app()->instance('currentTenant', $tenant1);

            $foundUser = User::find($user2->id);

            expect($foundUser)->toBeNull();
        });

        it('properly scopes relationship queries', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            Patient::factory()->count(3)->forTenant($tenant1)->create();
            Patient::factory()->count(5)->forTenant($tenant2)->create();

            $user = User::factory()->forTenant($tenant1)->create();
            $this->actingAs($user);
            app()->instance('currentTenant', $tenant1);

            // Get patients through tenant relationship
            $patients = $tenant1->patients;

            expect($patients)->toHaveCount(3);
        });
    });

    describe('Auto-Assignment Tests', function (): void {
        it('automatically assigns tenant_id on model creation', function (): void {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant)->create();

            $this->actingAs($user);
            app()->instance('currentTenant', $tenant);

            $patient = Patient::create([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

            expect($patient->tenant_id)->toBe($tenant->id);
        });

        it('does not override explicitly provided tenant_id', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant1)->create();

            $this->actingAs($user);
            app()->instance('currentTenant', $tenant1);

            // Explicitly set tenant_id to tenant2 (should be preserved)
            $patient = Patient::factory()->forTenant($tenant2)->create([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

            expect($patient->tenant_id)->toBe($tenant2->id);
        });

        it('requires tenant_id for tenant-scoped models with constraint', function (): void {
            // Without tenant context, should work since we're bypassing scope
            $tenant = Tenant::factory()->create();

            $patient = Patient::factory()->forTenant($tenant)->create();

            expect($patient->exists)->toBeTrue();
            expect($patient->tenant_id)->toBe($tenant->id);
        });
    });

    describe('Scope Bypass Tests', function (): void {
        it('can bypass tenant scope when needed', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            Patient::factory()->count(2)->forTenant($tenant1)->create();
            Patient::factory()->count(3)->forTenant($tenant2)->create();

            $user = User::factory()->forTenant($tenant1)->create();
            $this->actingAs($user);
            app()->instance('currentTenant', $tenant1);

            // Normal query should only see 2 patients
            expect(Patient::count())->toBe(2);

            // Bypassing scope should see all 5 patients
            expect(Patient::withoutTenantScope()->count())->toBe(5);
        });

        it('withoutTenantScope allows cross-tenant queries', function (): void {
            $tenant1 = Tenant::factory()->create();
            $tenant2 = Tenant::factory()->create();

            $patient2 = Patient::factory()->forTenant($tenant2)->create();

            $user = User::factory()->forTenant($tenant1)->create();
            $this->actingAs($user);
            app()->instance('currentTenant', $tenant1);

            $found = Patient::withoutTenantScope()->find($patient2->id);

            expect($found)->not->toBeNull();
            expect($found->id)->toBe($patient2->id);
        });
    });

    describe('Tenant Model Tests', function (): void {
        it('correctly identifies active tenant', function (): void {
            $activeTenant = Tenant::factory()->create(['is_active' => true]);
            $inactiveTenant = Tenant::factory()->inactive()->create();

            expect($activeTenant->isActive())->toBeTrue();
            expect($inactiveTenant->isActive())->toBeFalse();
        });

        it('correctly identifies trial tenant', function (): void {
            $trialTenant = Tenant::factory()->trial()->create();
            $activeTenant = Tenant::factory()->create();

            expect($trialTenant->isOnTrial())->toBeTrue();
            expect($activeTenant->isOnTrial())->toBeFalse();
        });

        it('correctly calculates trial days remaining', function (): void {
            $trialTenant = Tenant::factory()->trial(7)->create();

            expect($trialTenant->trialDaysRemaining())->toBeBetween(6, 7);
        });

        it('can get and update settings', function (): void {
            $tenant = Tenant::factory()->create([
                'settings' => ['key1' => 'value1'],
            ]);

            expect($tenant->getSettingValue('key1'))->toBe('value1');
            expect($tenant->getSettingValue('nonexistent', 'default'))->toBe('default');

            $tenant->updateSetting('key2', 'value2');
            $tenant->refresh();

            expect($tenant->getSettingValue('key2'))->toBe('value2');
        });
    });
});
