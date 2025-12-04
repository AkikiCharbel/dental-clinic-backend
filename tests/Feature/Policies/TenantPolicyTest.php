<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Policies\TenantPolicy;

describe('TenantPolicy', function (): void {
    beforeEach(function (): void {
        $this->tenant = createTenant();
        app()->instance('currentTenant', $this->tenant);
        $this->policy = new TenantPolicy();
    });

    describe('viewAny()', function (): void {
        it('allows users with view_tenants permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $user->givePermissionTo('view_tenants');

            expect($this->policy->viewAny($user))->toBeTrue();
        });

        it('denies users without view_tenants permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Assistant,
            ]);

            expect($this->policy->viewAny($user))->toBeFalse();
        });
    });

    describe('view()', function (): void {
        it('allows users to view their own tenant', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $user->givePermissionTo('view_tenants');

            expect($this->policy->view($user, $this->tenant))->toBeTrue();
        });

        it('denies viewing other tenants', function (): void {
            $otherTenant = createTenant();
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $user->givePermissionTo('view_tenants');

            expect($this->policy->view($user, $otherTenant))->toBeFalse();
        });
    });

    describe('create()', function (): void {
        it('allows super admins (no tenant) with permission to create tenants', function (): void {
            $superAdmin = createUser([
                'tenant_id' => null,
                'primary_role' => UserRole::Admin,
            ]);
            $superAdmin->givePermissionTo('create_tenants');

            expect($this->policy->create($superAdmin))->toBeTrue();
        });

        it('denies tenant-bound admins from creating tenants', function (): void {
            $tenantAdmin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $tenantAdmin->givePermissionTo('create_tenants');

            expect($this->policy->create($tenantAdmin))->toBeFalse();
        });
    });

    describe('update()', function (): void {
        it('allows users to update their own tenant', function (): void {
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $admin->givePermissionTo('update_tenants');

            expect($this->policy->update($admin, $this->tenant))->toBeTrue();
        });

        it('denies updating other tenants', function (): void {
            $otherTenant = createTenant();
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $admin->givePermissionTo('update_tenants');

            expect($this->policy->update($admin, $otherTenant))->toBeFalse();
        });

        it('denies non-admins from updating tenant', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Receptionist,
            ]);

            expect($this->policy->update($user, $this->tenant))->toBeFalse();
        });
    });

    describe('delete()', function (): void {
        it('allows super admins (no tenant) with permission to delete tenants', function (): void {
            $superAdmin = createUser([
                'tenant_id' => null,
                'primary_role' => UserRole::Admin,
            ]);
            $superAdmin->givePermissionTo('delete_tenants');

            expect($this->policy->delete($superAdmin, $this->tenant))->toBeTrue();
        });

        it('denies tenant-bound admins from deleting tenants', function (): void {
            $tenantAdmin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $tenantAdmin->givePermissionTo('delete_tenants');

            expect($this->policy->delete($tenantAdmin, $this->tenant))->toBeFalse();
        });
    });

    describe('restore()', function (): void {
        it('allows super admins (no tenant) to restore tenants', function (): void {
            $superAdmin = createUser([
                'tenant_id' => null,
                'primary_role' => UserRole::Admin,
            ]);
            $superAdmin->givePermissionTo('delete_tenants');

            expect($this->policy->restore($superAdmin, $this->tenant))->toBeTrue();
        });
    });

    describe('forceDelete()', function (): void {
        it('only allows super admin to force delete tenants', function (): void {
            $superAdmin = createUser([
                'tenant_id' => null,
                'primary_role' => UserRole::Admin,
            ]);

            expect($this->policy->forceDelete($superAdmin, $this->tenant))->toBeTrue();
        });

        it('denies tenant-bound admin from force deleting', function (): void {
            $tenantAdmin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);

            expect($this->policy->forceDelete($tenantAdmin, $this->tenant))->toBeFalse();
        });
    });
});
