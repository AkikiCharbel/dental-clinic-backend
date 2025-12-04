<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\DefinesPermissions;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Finder\Finder;

/**
 * Seeds roles and permissions for the application.
 *
 * This seeder uses the dynamic permission system defined in models.
 * Permissions are discovered from models implementing DefinesPermissions.
 * Roles are created based on UserRole enum with default permissions assigned.
 */
class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Creating permissions from models...');

        // Discover and create permissions from models
        $models = $this->discoverModels();
        $allPermissions = [];

        foreach ($models as $modelClass) {
            $permissions = $modelClass::getPermissions();
            $allPermissions = array_merge($allPermissions, $permissions);

            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }

            $this->command->info("  - {$modelClass}: ".count($permissions).' permissions');
        }

        $this->command->newLine();
        $this->command->info('Creating roles and assigning permissions...');

        // Create roles and assign default permissions
        foreach (UserRole::cases() as $userRole) {
            $role = Role::firstOrCreate([
                'name' => $userRole->value,
                'guard_name' => 'web',
            ]);

            $rolePermissions = $userRole->defaultPermissions();

            // Skip wildcard permissions (handled by Gate)
            if ($rolePermissions === ['*']) {
                $this->command->info("  - {$userRole->label()}: Admin (all permissions via Gate)");
                continue;
            }

            // Filter to only existing permissions
            $validPermissions = array_filter($rolePermissions, function (string $permission) use ($allPermissions): bool {
                return in_array($permission, $allPermissions, true);
            });

            $role->syncPermissions($validPermissions);

            $this->command->info("  - {$userRole->label()}: ".count($validPermissions).' permissions');
        }

        $this->command->newLine();
        $this->command->info('Roles and permissions seeded successfully!');
    }

    /**
     * Discover all models implementing DefinesPermissions.
     *
     * @return array<int, class-string<DefinesPermissions>>
     */
    private function discoverModels(): array
    {
        $models = [];
        $modelsPath = app_path('Models');

        if (! File::isDirectory($modelsPath)) {
            return $models;
        }

        $finder = new Finder();
        $finder->files()->in($modelsPath)->name('*.php');

        foreach ($finder as $file) {
            $className = $this->getClassNameFromFile($file->getRealPath());

            if ($className === null) {
                continue;
            }

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            if ($reflection->implementsInterface(DefinesPermissions::class)) {
                /** @var class-string<DefinesPermissions> $className */
                $models[] = $className;
            }
        }

        sort($models);

        return $models;
    }

    /**
     * Get the fully qualified class name from a file path.
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $contents = File::get($filePath);

        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class name
        $className = null;
        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $className = trim($matches[1]);
        }

        if ($className === null) {
            return null;
        }

        return $namespace !== null
            ? "{$namespace}\\{$className}"
            : $className;
    }
}
