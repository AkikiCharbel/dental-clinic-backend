<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;
use App\Policies\PatientPolicy;

describe('PatientPolicy', function (): void {
    beforeEach(function (): void {
        $this->tenant = createTenant();
        app()->instance('currentTenant', $this->tenant);
        $this->policy = new PatientPolicy();
    });

    describe('viewAny()', function (): void {
        it('allows users with view_patients permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $user->givePermissionTo('view_patients');

            expect($this->policy->viewAny($user))->toBeTrue();
        });

        it('denies users without view_patients permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Assistant,
            ]);

            expect($this->policy->viewAny($user))->toBeFalse();
        });
    });

    describe('view()', function (): void {
        it('allows viewing patient in same tenant', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $user->givePermissionTo('view_patients');

            $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

            expect($this->policy->view($user, $patient))->toBeTrue();
        });

        it('denies viewing patient from different tenant', function (): void {
            $otherTenant = createTenant();
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $user->givePermissionTo('view_patients');

            $patient = Patient::factory()->create(['tenant_id' => $otherTenant->id]);

            expect($this->policy->view($user, $patient))->toBeFalse();
        });
    });

    describe('create()', function (): void {
        it('allows users with create_patients permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Receptionist,
            ]);
            $user->givePermissionTo('create_patients');

            expect($this->policy->create($user))->toBeTrue();
        });

        it('denies users without create_patients permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Assistant,
            ]);

            expect($this->policy->create($user))->toBeFalse();
        });
    });

    describe('update()', function (): void {
        it('allows updating patient in same tenant', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $user->givePermissionTo('update_patients');

            $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

            expect($this->policy->update($user, $patient))->toBeTrue();
        });

        it('denies updating patient from different tenant', function (): void {
            $otherTenant = createTenant();
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $user->givePermissionTo('update_patients');

            $patient = Patient::factory()->create(['tenant_id' => $otherTenant->id]);

            expect($this->policy->update($user, $patient))->toBeFalse();
        });
    });

    describe('delete()', function (): void {
        it('allows deleting patient in same tenant with permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $user->givePermissionTo('delete_patients');

            $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

            expect($this->policy->delete($user, $patient))->toBeTrue();
        });

        it('denies deleting patient from different tenant', function (): void {
            $otherTenant = createTenant();
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $user->givePermissionTo('delete_patients');

            $patient = Patient::factory()->create(['tenant_id' => $otherTenant->id]);

            expect($this->policy->delete($user, $patient))->toBeFalse();
        });
    });

    describe('restore()', function (): void {
        it('allows restoring patient in same tenant with delete permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $user->givePermissionTo('delete_patients');

            $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
            $patient->delete();

            expect($this->policy->restore($user, $patient))->toBeTrue();
        });
    });

    describe('forceDelete()', function (): void {
        it('only allows admin to force delete', function (): void {
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);

            $nonAdmin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $nonAdmin->givePermissionTo('delete_patients');

            $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

            expect($this->policy->forceDelete($admin, $patient))->toBeTrue();
            expect($this->policy->forceDelete($nonAdmin, $patient))->toBeFalse();
        });

        it('denies force delete for patient from different tenant', function (): void {
            $otherTenant = createTenant();
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);

            $patient = Patient::factory()->create(['tenant_id' => $otherTenant->id]);

            expect($this->policy->forceDelete($admin, $patient))->toBeFalse();
        });
    });
});
