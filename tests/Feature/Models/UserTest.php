<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

describe('User Model', function (): void {
    describe('Factory and Creation', function (): void {
        it('creates a user with required attributes', function (): void {
            $tenant = createTenant();

            $user = User::factory()->create(['tenant_id' => $tenant->id]);

            expect($user->id)->not->toBeNull();
            expect($user->first_name)->not->toBeNull();
            expect($user->last_name)->not->toBeNull();
            expect($user->email)->not->toBeNull();
        });

        it('uses UUID as primary key', function (): void {
            $tenant = createTenant();

            $user = User::factory()->create(['tenant_id' => $tenant->id]);

            expect(strlen($user->id))->toBe(36);
            expect($user->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });

        it('hashes password automatically', function (): void {
            $tenant = createTenant();

            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'password' => 'plain-text-password',
            ]);

            expect($user->password)->not->toBe('plain-text-password');
            expect(password_verify('plain-text-password', $user->password))->toBeTrue();
        });
    });

    describe('Attributes and Accessors', function (): void {
        it('returns name (first + last)', function (): void {
            $tenant = createTenant();

            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

            expect($user->name)->toBe('John Doe');
        });

        it('returns full name with title', function (): void {
            $tenant = createTenant();

            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'title' => 'Dr.',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
            ]);

            expect($user->full_name)->toBe('Dr. Jane Smith');
        });

        it('returns full name without title when not set', function (): void {
            $tenant = createTenant();

            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'title' => null,
                'first_name' => 'Bob',
                'last_name' => 'Wilson',
            ]);

            expect($user->full_name)->toBe('Bob Wilson');
        });
    });

    describe('Role Methods', function (): void {
        it('correctly identifies admin user', function (): void {
            $tenant = createTenant();

            $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
            $dentist = User::factory()->dentist()->create(['tenant_id' => $tenant->id]);

            expect($admin->isAdmin())->toBeTrue();
            expect($dentist->isAdmin())->toBeFalse();
        });

        it('correctly identifies provider users (dentist/hygienist)', function (): void {
            $tenant = createTenant();

            $dentist = User::factory()->dentist()->create(['tenant_id' => $tenant->id]);
            $hygienist = User::factory()->hygienist()->create(['tenant_id' => $tenant->id]);
            $receptionist = User::factory()->receptionist()->create(['tenant_id' => $tenant->id]);

            expect($dentist->isProvider())->toBeTrue();
            expect($hygienist->isProvider())->toBeTrue();
            expect($receptionist->isProvider())->toBeFalse();
        });

        it('correctly identifies dentist user', function (): void {
            $tenant = createTenant();

            $dentist = User::factory()->dentist()->create(['tenant_id' => $tenant->id]);
            $hygienist = User::factory()->hygienist()->create(['tenant_id' => $tenant->id]);

            expect($dentist->isDentist())->toBeTrue();
            expect($hygienist->isDentist())->toBeFalse();
        });

        it('correctly checks tenant membership', function (): void {
            $tenant1 = createTenant();
            $tenant2 = createTenant();

            $user = User::factory()->create(['tenant_id' => $tenant1->id]);

            expect($user->belongsToTenant($tenant1->id))->toBeTrue();
            expect($user->belongsToTenant($tenant2->id))->toBeFalse();
        });
    });

    describe('Enum Casting', function (): void {
        it('casts primary_role to UserRole enum', function (): void {
            $tenant = createTenant();

            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'primary_role' => 'dentist',
            ]);

            expect($user->primary_role)->toBeInstanceOf(UserRole::class);
            expect($user->primary_role)->toBe(UserRole::Dentist);
        });

        it('handles all UserRole enum values', function (): void {
            $tenant = createTenant();

            foreach (UserRole::cases() as $role) {
                $user = User::factory()->create([
                    'tenant_id' => $tenant->id,
                    'primary_role' => $role->value,
                ]);

                expect($user->primary_role)->toBe($role);
            }
        });
    });

    describe('Login Tracking', function (): void {
        it('updates last login timestamp and IP', function (): void {
            $tenant = createTenant();
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'last_login_at' => null,
                'last_login_ip' => null,
            ]);

            $user->updateLastLogin('192.168.1.1');
            $user->refresh();

            expect($user->last_login_at)->not->toBeNull();
            expect($user->last_login_ip)->toBe('192.168.1.1');
        });

        it('updates last login without IP', function (): void {
            $tenant = createTenant();
            $user = User::factory()->create(['tenant_id' => $tenant->id]);

            $user->updateLastLogin(null);
            $user->refresh();

            expect($user->last_login_at)->not->toBeNull();
            expect($user->last_login_ip)->toBeNull();
        });
    });

    describe('Preferences', function (): void {
        it('gets preference value', function (): void {
            $tenant = createTenant();
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => ['email' => true, 'sms' => false],
                ],
            ]);

            expect($user->getPreference('theme'))->toBe('dark');
            expect($user->getPreference('notifications.email'))->toBeTrue();
            expect($user->getPreference('notifications.sms'))->toBeFalse();
        });

        it('returns default for non-existent preference', function (): void {
            $tenant = createTenant();
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'preferences' => ['theme' => 'light'],
            ]);

            expect($user->getPreference('language', 'en'))->toBe('en');
            expect($user->getPreference('missing'))->toBeNull();
        });

        it('updates single preference', function (): void {
            $tenant = createTenant();
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'preferences' => ['theme' => 'light'],
            ]);

            $user->updatePreference('theme', 'dark');
            $user->refresh();

            expect($user->getPreference('theme'))->toBe('dark');
        });

        it('adds new preference without overwriting others', function (): void {
            $tenant = createTenant();
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'preferences' => ['theme' => 'light'],
            ]);

            $user->updatePreference('language', 'es');
            $user->refresh();

            expect($user->getPreference('theme'))->toBe('light');
            expect($user->getPreference('language'))->toBe('es');
        });
    });

    describe('Scopes', function (): void {
        it('filters active users', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            User::factory()->count(3)->create([
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);

            User::factory()->count(2)->create([
                'tenant_id' => $tenant->id,
                'is_active' => false,
            ]);

            expect(User::active()->count())->toBe(3);
        });

        it('filters providers only', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            User::factory()->dentist()->create(['tenant_id' => $tenant->id]);
            User::factory()->hygienist()->create(['tenant_id' => $tenant->id]);
            User::factory()->receptionist()->create(['tenant_id' => $tenant->id]);
            User::factory()->admin()->create(['tenant_id' => $tenant->id]);

            expect(User::providers()->count())->toBe(2);
        });

        it('filters by specific role', function (): void {
            $tenant = createTenant();
            app()->instance('currentTenant', $tenant);

            User::factory()->dentist()->count(2)->create(['tenant_id' => $tenant->id]);
            User::factory()->hygienist()->count(3)->create(['tenant_id' => $tenant->id]);

            expect(User::role(UserRole::Dentist)->count())->toBe(2);
            expect(User::role(UserRole::Hygienist)->count())->toBe(3);
        });
    });

    describe('Relationships', function (): void {
        it('belongs to a tenant', function (): void {
            $tenant = createTenant();

            $user = User::factory()->create(['tenant_id' => $tenant->id]);

            expect($user->tenant->id)->toBe($tenant->id);
        });

        it('can exist without tenant (super admin)', function (): void {
            $user = User::factory()->create(['tenant_id' => null]);

            expect($user->tenant)->toBeNull();
        });
    });

    describe('Soft Deletes', function (): void {
        it('soft deletes user', function (): void {
            $tenant = createTenant();
            $user = User::factory()->create(['tenant_id' => $tenant->id]);
            $userId = $user->id;

            $user->delete();

            expect(User::find($userId))->toBeNull();
            expect(User::withTrashed()->find($userId))->not->toBeNull();
        });
    });

    describe('Activity Logging', function (): void {
        it('logs activity on user changes', function (): void {
            $tenant = createTenant();
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'Original',
            ]);

            $user->update(['first_name' => 'Updated']);

            // Verify activity was logged
            $activities = $user->activities;
            expect($activities->count())->toBeGreaterThanOrEqual(1);
        });

        it('excludes password from activity log', function (): void {
            $tenant = createTenant();
            $user = User::factory()->create(['tenant_id' => $tenant->id]);

            $user->update(['password' => 'new-password']);

            $lastActivity = $user->activities->last();

            // Password should not be in the logged properties
            if ($lastActivity && $lastActivity->properties) {
                expect($lastActivity->properties->get('attributes.password'))->toBeNull();
            }
        });
    });
});
