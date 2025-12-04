<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Support\Facades\Gate;

describe('Admin Gate Bypass', function (): void {
    beforeEach(function (): void {
        $this->tenant = createTenant();
        app()->instance('currentTenant', $this->tenant);
    });

    describe('Gate::before callback', function (): void {
        it('admin users pass all ability checks', function (): void {
            $admin = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Admin,
            ]);

            $this->actingAs($admin);

            expect(Gate::allows('view_patients'))->toBeTrue();
            expect(Gate::allows('create_patients'))->toBeTrue();
            expect(Gate::allows('update_patients'))->toBeTrue();
            expect(Gate::allows('delete_patients'))->toBeTrue();
            expect(Gate::allows('view_users'))->toBeTrue();
            expect(Gate::allows('create_users'))->toBeTrue();
            expect(Gate::allows('update_users'))->toBeTrue();
            expect(Gate::allows('delete_users'))->toBeTrue();
            expect(Gate::allows('any_permission'))->toBeTrue();
        });

        it('non-admin users require specific permissions', function (): void {
            $dentist = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);

            $this->actingAs($dentist);

            // Without any permissions assigned
            expect(Gate::allows('view_patients'))->toBeFalse();
            expect(Gate::allows('create_patients'))->toBeFalse();
        });

        it('non-admin users with permissions pass specific checks', function (): void {
            $dentist = createUser([
                'tenant_id' => $this->tenant->id,
                'primary_role' => UserRole::Dentist,
            ]);
            $dentist->givePermissionTo('view_patients');

            $this->actingAs($dentist);

            expect(Gate::allows('view_patients'))->toBeTrue();
            expect(Gate::allows('create_patients'))->toBeFalse();
        });
    });

    describe('role-based access', function (): void {
        it('all role types are denied without explicit permissions', function (): void {
            $roles = [
                UserRole::Dentist,
                UserRole::Hygienist,
                UserRole::Receptionist,
                UserRole::Assistant,
            ];

            foreach ($roles as $role) {
                $user = createUser([
                    'tenant_id' => $this->tenant->id,
                    'primary_role' => $role,
                ]);

                $this->actingAs($user);

                expect(Gate::allows('delete_patients'))
                    ->toBeFalse("Expected {$role->value} to be denied delete_patients");
            }
        });
    });
});
