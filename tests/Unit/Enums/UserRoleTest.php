<?php

declare(strict_types=1);

use App\Enums\UserRole;

describe('UserRole Enum', function (): void {
    describe('defaultPermissions()', function (): void {
        it('returns wildcard for admin', function (): void {
            expect(UserRole::Admin->defaultPermissions())->toBe(['*']);
        });

        it('returns correct permissions for dentist', function (): void {
            $permissions = UserRole::Dentist->defaultPermissions();

            expect($permissions)->toContain('view_patients');
            expect($permissions)->toContain('create_patients');
            expect($permissions)->toContain('update_patients');
            expect($permissions)->toContain('view_users');
            expect($permissions)->toContain('view_tenants');
            expect($permissions)->not->toContain('delete_patients');
        });

        it('returns correct permissions for hygienist', function (): void {
            $permissions = UserRole::Hygienist->defaultPermissions();

            expect($permissions)->toContain('view_patients');
            expect($permissions)->toContain('update_patients');
            expect($permissions)->toContain('view_users');
            expect($permissions)->not->toContain('create_patients');
            expect($permissions)->not->toContain('delete_patients');
        });

        it('returns correct permissions for receptionist', function (): void {
            $permissions = UserRole::Receptionist->defaultPermissions();

            expect($permissions)->toContain('view_patients');
            expect($permissions)->toContain('create_patients');
            expect($permissions)->toContain('update_patients');
            expect($permissions)->toContain('view_users');
            expect($permissions)->not->toContain('delete_patients');
        });

        it('returns correct permissions for assistant', function (): void {
            $permissions = UserRole::Assistant->defaultPermissions();

            expect($permissions)->toContain('view_patients');
            expect($permissions)->toContain('view_users');
            expect($permissions)->not->toContain('create_patients');
            expect($permissions)->not->toContain('update_patients');
            expect($permissions)->not->toContain('delete_patients');
        });
    });

    describe('allDefaultPermissions()', function (): void {
        it('returns unique permissions from all non-admin roles', function (): void {
            $allPermissions = UserRole::allDefaultPermissions();

            expect($allPermissions)->toContain('view_patients');
            expect($allPermissions)->toContain('create_patients');
            expect($allPermissions)->toContain('update_patients');
            expect($allPermissions)->toContain('view_users');
            expect($allPermissions)->toContain('view_tenants');
        });

        it('does not include wildcard permission', function (): void {
            $allPermissions = UserRole::allDefaultPermissions();

            expect($allPermissions)->not->toContain('*');
        });

        it('returns unique values', function (): void {
            $allPermissions = UserRole::allDefaultPermissions();
            $uniquePermissions = array_unique($allPermissions);

            expect(count($allPermissions))->toBe(count($uniquePermissions));
        });
    });

    describe('existing methods', function (): void {
        it('label() returns human-readable names', function (): void {
            expect(UserRole::Admin->label())->toBe('Administrator');
            expect(UserRole::Dentist->label())->toBe('Dentist');
            expect(UserRole::Hygienist->label())->toBe('Dental Hygienist');
            expect(UserRole::Receptionist->label())->toBe('Receptionist');
            expect(UserRole::Assistant->label())->toBe('Dental Assistant');
        });

        it('isProvider() returns true for clinical roles', function (): void {
            expect(UserRole::Dentist->isProvider())->toBeTrue();
            expect(UserRole::Hygienist->isProvider())->toBeTrue();
            expect(UserRole::Admin->isProvider())->toBeFalse();
            expect(UserRole::Receptionist->isProvider())->toBeFalse();
            expect(UserRole::Assistant->isProvider())->toBeFalse();
        });

        it('isAdmin() returns true only for admin', function (): void {
            expect(UserRole::Admin->isAdmin())->toBeTrue();
            expect(UserRole::Dentist->isAdmin())->toBeFalse();
            expect(UserRole::Hygienist->isAdmin())->toBeFalse();
            expect(UserRole::Receptionist->isAdmin())->toBeFalse();
            expect(UserRole::Assistant->isAdmin())->toBeFalse();
        });

        it('providers() returns clinical roles', function (): void {
            $providers = UserRole::providers();

            expect($providers)->toBe([UserRole::Dentist, UserRole::Hygienist]);
        });

        it('staff() returns non-clinical roles', function (): void {
            $staff = UserRole::staff();

            expect($staff)->toBe([UserRole::Receptionist, UserRole::Assistant]);
        });

        it('options() returns all roles as key-value pairs', function (): void {
            $options = UserRole::options();

            expect($options)->toHaveCount(5);
            expect($options['admin'])->toBe('Administrator');
            expect($options['dentist'])->toBe('Dentist');
        });
    });
});
