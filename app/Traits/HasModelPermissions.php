<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Trait for models to define their permissions.
 *
 * Provides default implementation for DefinesPermissions contract.
 * Models can override any method to customize behavior.
 */
trait HasModelPermissions
{
    /**
     * Get the permission actions available for this model.
     *
     * Override this in your model to customize available actions.
     *
     * @return array<int, string>
     */
    public static function getPermissionActions(): array
    {
        return ['view', 'create', 'update', 'delete'];
    }

    /**
     * Get the permission prefix for this model.
     *
     * Default: snake_case plural of the class name.
     * Override in model to customize.
     */
    public static function getPermissionPrefix(): string
    {
        $className = class_basename(static::class);

        return Str::snake(Str::pluralStudly($className));
    }

    /**
     * Get all permission names for this model.
     *
     * Combines prefix with actions to create full permission names.
     *
     * @return array<int, string>
     */
    public static function getPermissions(): array
    {
        $prefix = static::getPermissionPrefix();

        return array_map(
            fn (string $action): string => "{$action}_{$prefix}",
            static::getPermissionActions(),
        );
    }
}
