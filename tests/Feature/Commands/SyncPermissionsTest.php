<?php

declare(strict_types=1);

use Spatie\Permission\Models\Permission;

describe('SyncPermissions Command', function (): void {
    beforeEach(function (): void {
        // Clear existing permissions
        Permission::query()->delete();
    });

    describe('permissions:sync', function (): void {
        it('creates permissions for all models implementing DefinesPermissions', function (): void {
            $this->artisan('permissions:sync')
                ->assertSuccessful();

            // Check Patient permissions
            expect(Permission::where('name', 'view_patients')->exists())->toBeTrue();
            expect(Permission::where('name', 'create_patients')->exists())->toBeTrue();
            expect(Permission::where('name', 'update_patients')->exists())->toBeTrue();
            expect(Permission::where('name', 'delete_patients')->exists())->toBeTrue();

            // Check User permissions
            expect(Permission::where('name', 'view_users')->exists())->toBeTrue();
            expect(Permission::where('name', 'create_users')->exists())->toBeTrue();
            expect(Permission::where('name', 'update_users')->exists())->toBeTrue();
            expect(Permission::where('name', 'delete_users')->exists())->toBeTrue();

            // Check Tenant permissions
            expect(Permission::where('name', 'view_tenants')->exists())->toBeTrue();
            expect(Permission::where('name', 'create_tenants')->exists())->toBeTrue();
            expect(Permission::where('name', 'update_tenants')->exists())->toBeTrue();
            expect(Permission::where('name', 'delete_tenants')->exists())->toBeTrue();
        });

        it('uses web guard for all permissions', function (): void {
            $this->artisan('permissions:sync')
                ->assertSuccessful();

            $permissions = Permission::all();

            foreach ($permissions as $permission) {
                expect($permission->guard_name)->toBe('web');
            }
        });

        it('does not duplicate existing permissions', function (): void {
            // Create a permission manually
            Permission::create(['name' => 'view_patients', 'guard_name' => 'web']);

            $this->artisan('permissions:sync')
                ->assertSuccessful();

            $count = Permission::where('name', 'view_patients')->count();
            expect($count)->toBe(1);
        });

        it('shows dry run without making changes', function (): void {
            $this->artisan('permissions:sync', ['--dry-run' => true])
                ->assertSuccessful();

            expect(Permission::count())->toBe(0);
        });

        it('removes orphaned permissions with --clean option', function (): void {
            // Create an orphaned permission (not defined in any model)
            Permission::create(['name' => 'orphaned_permission', 'guard_name' => 'web']);

            $this->artisan('permissions:sync', ['--clean' => true])
                ->assertSuccessful();

            expect(Permission::where('name', 'orphaned_permission')->exists())->toBeFalse();
        });

        it('dry run shows what would be cleaned without changes', function (): void {
            // Create an orphaned permission
            Permission::create(['name' => 'orphaned_permission', 'guard_name' => 'web']);

            $this->artisan('permissions:sync', ['--dry-run' => true, '--clean' => true])
                ->assertSuccessful();

            // Permission should still exist after dry run
            expect(Permission::where('name', 'orphaned_permission')->exists())->toBeTrue();
        });
    });

    describe('output', function (): void {
        it('displays model count and permissions', function (): void {
            $this->artisan('permissions:sync')
                ->expectsOutputToContain('Discovering model permissions')
                ->expectsOutputToContain('Permission sync complete')
                ->assertSuccessful();
        });

        it('shows created count after sync', function (): void {
            $this->artisan('permissions:sync')
                ->expectsOutputToContain('Created')
                ->expectsOutputToContain('Total')
                ->assertSuccessful();
        });
    });
});
