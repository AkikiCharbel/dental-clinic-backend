<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Policies\UserPolicy;

describe('UserPolicy', function (): void {
    beforeEach(function (): void {
        $this->tenant = createTenant();
        app()->instance('currentTenant', $this->tenant);
        $this->policy = new UserPolicy;
    });

    describe('viewAny()', function (): void {
        it('allows users with view_users permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $user->givePermissionTo('view_users');

            expect($this->policy->viewAny($user))->toBeTrue();
        });

        it('denies users without view_users permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Assistant,
            ]);

            expect($this->policy->viewAny($user))->toBeFalse();
        });
    });

    describe('view()', function (): void {
        it('allows users to view themselves', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Assistant,
            ]);

            expect($this->policy->view($user, $user))->toBeTrue();
        });

        it('allows viewing other users in same tenant with permission', function (): void {
            $viewer = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $viewer->givePermissionTo('view_users');

            $targetUser = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Receptionist,
            ]);

            expect($this->policy->view($viewer, $targetUser))->toBeTrue();
        });

        it('denies viewing users from different tenant', function (): void {
            $otherTenant = createTenant();
            $viewer = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $viewer->givePermissionTo('view_users');

            $otherUser = createUser(['tenant_id' => $otherTenant->id]);

            expect($this->policy->view($viewer, $otherUser))->toBeFalse();
        });
    });

    describe('create()', function (): void {
        it('allows users with create_users permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $user->givePermissionTo('create_users');

            expect($this->policy->create($user))->toBeTrue();
        });

        it('denies users without create_users permission', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Receptionist,
            ]);

            expect($this->policy->create($user))->toBeFalse();
        });
    });

    describe('update()', function (): void {
        it('allows users to update themselves', function (): void {
            $user = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Assistant,
            ]);

            expect($this->policy->update($user, $user))->toBeTrue();
        });

        it('allows updating other users with permission', function (): void {
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $admin->givePermissionTo('update_users');

            $targetUser = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Receptionist,
            ]);

            expect($this->policy->update($admin, $targetUser))->toBeTrue();
        });

        it('denies updating users from different tenant', function (): void {
            $otherTenant = createTenant();
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $admin->givePermissionTo('update_users');

            $otherUser = createUser(['tenant_id' => $otherTenant->id]);

            expect($this->policy->update($admin, $otherUser))->toBeFalse();
        });
    });

    describe('delete()', function (): void {
        it('denies users from deleting themselves', function (): void {
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $admin->givePermissionTo('delete_users');

            expect($this->policy->delete($admin, $admin))->toBeFalse();
        });

        it('allows deleting other users in same tenant', function (): void {
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $admin->givePermissionTo('delete_users');

            $targetUser = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Receptionist,
            ]);

            expect($this->policy->delete($admin, $targetUser))->toBeTrue();
        });

        it('denies deleting users from different tenant', function (): void {
            $otherTenant = createTenant();
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);
            $admin->givePermissionTo('delete_users');

            $otherUser = createUser(['tenant_id' => $otherTenant->id]);

            expect($this->policy->delete($admin, $otherUser))->toBeFalse();
        });
    });

    describe('forceDelete()', function (): void {
        it('denies force deleting yourself', function (): void {
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);

            expect($this->policy->forceDelete($admin, $admin))->toBeFalse();
        });

        it('only allows admin to force delete', function (): void {
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);

            $nonAdmin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $nonAdmin->givePermissionTo('delete_users');

            $targetUser = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Receptionist,
            ]);

            expect($this->policy->forceDelete($admin, $targetUser))->toBeTrue();
            expect($this->policy->forceDelete($nonAdmin, $targetUser))->toBeFalse();
        });
    });
});
