<?php

declare(strict_types=1);

use App\Contracts\DefinesPermissions;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\HasModelPermissions;

describe('HasModelPermissions Trait', function (): void {
    describe('getPermissionActions()', function (): void {
        it('returns default CRUD actions', function (): void {
            $actions = Patient::getPermissionActions();

            expect($actions)->toBe(['view', 'create', 'update', 'delete']);
        });

        it('returns same actions for all models using the trait', function (): void {
            expect(Patient::getPermissionActions())->toBe(User::getPermissionActions());
            expect(User::getPermissionActions())->toBe(Tenant::getPermissionActions());
        });
    });

    describe('getPermissionPrefix()', function (): void {
        it('generates snake_case plural prefix from model name', function (): void {
            expect(Patient::getPermissionPrefix())->toBe('patients');
            expect(User::getPermissionPrefix())->toBe('users');
            expect(Tenant::getPermissionPrefix())->toBe('tenants');
        });
    });

    describe('getPermissions()', function (): void {
        it('generates permission names by combining prefix with actions', function (): void {
            $permissions = Patient::getPermissions();

            expect($permissions)->toBe([
                'view_patients',
                'create_patients',
                'update_patients',
                'delete_patients',
            ]);
        });

        it('generates permissions for User model', function (): void {
            $permissions = User::getPermissions();

            expect($permissions)->toBe([
                'view_users',
                'create_users',
                'update_users',
                'delete_users',
            ]);
        });

        it('generates permissions for Tenant model', function (): void {
            $permissions = Tenant::getPermissions();

            expect($permissions)->toBe([
                'view_tenants',
                'create_tenants',
                'update_tenants',
                'delete_tenants',
            ]);
        });
    });

    describe('Interface Implementation', function (): void {
        it('Patient implements DefinesPermissions', function (): void {
            expect(class_implements(Patient::class))->toContain(DefinesPermissions::class);
        });

        it('User implements DefinesPermissions', function (): void {
            expect(class_implements(User::class))->toContain(DefinesPermissions::class);
        });

        it('Tenant implements DefinesPermissions', function (): void {
            expect(class_implements(Tenant::class))->toContain(DefinesPermissions::class);
        });
    });

    describe('Custom Model Permissions', function (): void {
        it('can be overridden in a model', function (): void {
            // Create an anonymous class that overrides the default actions
            $customModel = new class
            {
                use HasModelPermissions;

                public static function getPermissionActions(): array
                {
                    return ['view', 'create', 'update', 'delete', 'export', 'import'];
                }

                public static function getPermissionPrefix(): string
                {
                    return 'custom_resources';
                }
            };

            expect($customModel::getPermissions())->toBe([
                'view_custom_resources',
                'create_custom_resources',
                'update_custom_resources',
                'delete_custom_resources',
                'export_custom_resources',
                'import_custom_resources',
            ]);
        });
    });
});
