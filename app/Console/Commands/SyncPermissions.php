<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\DefinesPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Finder\Finder;

/**
 * Syncs permissions defined in models to the database.
 *
 * This command discovers all models implementing DefinesPermissions
 * and creates/updates their permissions in the database.
 */
class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync
                            {--dry-run : Show what would be synced without making changes}
                            {--clean : Remove orphaned permissions not defined in any model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync model-defined permissions to the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $clean = (bool) $this->option('clean');

        $this->info('Discovering model permissions...');
        $this->newLine();

        $models = $this->discoverModels();
        $allPermissions = [];
        $created = 0;
        $existing = 0;

        foreach ($models as $modelClass) {
            $permissions = $modelClass::getPermissions();
            $prefix = $modelClass::getPermissionPrefix();

            $this->components->twoColumnDetail(
                "<fg=yellow>{$modelClass}</>",
                sprintf('%d permissions', count($permissions))
            );

            foreach ($permissions as $permission) {
                $allPermissions[] = $permission;

                if ($dryRun) {
                    $this->line("    <fg=gray>→</> {$permission}");
                    continue;
                }

                $exists = Permission::where('name', $permission)
                    ->where('guard_name', 'web')
                    ->exists();

                if ($exists) {
                    $existing++;
                    $this->line("    <fg=gray>→</> {$permission} <fg=gray>(exists)</>");
                } else {
                    Permission::create([
                        'name' => $permission,
                        'guard_name' => 'web',
                    ]);
                    $created++;
                    $this->line("    <fg=green>+</> {$permission} <fg=green>(created)</>");
                }
            }
        }

        $this->newLine();

        if ($clean && ! $dryRun) {
            $orphaned = $this->cleanOrphanedPermissions($allPermissions);
            if ($orphaned > 0) {
                $this->components->warn("Removed {$orphaned} orphaned permission(s).");
            }
        } elseif ($clean && $dryRun) {
            /** @var \Illuminate\Support\Collection<int, string> $orphanedPermissions */
            $orphanedPermissions = Permission::whereNotIn('name', $allPermissions)
                ->where('guard_name', 'web')
                ->pluck('name');

            if ($orphanedPermissions->isNotEmpty()) {
                $this->components->warn('Would remove orphaned permissions:');
                foreach ($orphanedPermissions as $permission) {
                    $this->line('    <fg=red>-</> '.$permission);
                }
            }
        }

        // Clear permission cache
        if (! $dryRun) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->newLine();

        if ($dryRun) {
            $this->components->info('Dry run complete. No changes were made.');
            $this->components->twoColumnDetail('Total permissions', (string) count($allPermissions));
        } else {
            $this->components->info('Permission sync complete!');
            $this->components->twoColumnDetail('Created', (string) $created);
            $this->components->twoColumnDetail('Already existing', (string) $existing);
            $this->components->twoColumnDetail('Total', (string) count($allPermissions));
        }

        return self::SUCCESS;
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

    /**
     * Remove permissions that are not defined in any model.
     *
     * @param  array<int, string>  $definedPermissions
     */
    private function cleanOrphanedPermissions(array $definedPermissions): int
    {
        /** @var int $deleted */
        $deleted = Permission::whereNotIn('name', $definedPermissions)
            ->where('guard_name', 'web')
            ->delete();

        return $deleted;
    }
}
