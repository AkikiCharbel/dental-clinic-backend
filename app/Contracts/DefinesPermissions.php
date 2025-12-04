<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contract for models that define their own permissions.
 *
 * Models implementing this interface will have their permissions
 * automatically discovered and synced by the permissions:sync command.
 */
interface DefinesPermissions
{
    /**
     * Get the permission actions available for this model.
     *
     * Common actions: view, create, update, delete, restore, force_delete
     *
     * @return array<int, string>
     */
    public static function getPermissionActions(): array;

    /**
     * Get the permission prefix for this model.
     *
     * By default, this should be the snake_case plural of the model name.
     * e.g., Patient -> patients, User -> users
     *
     * @return string
     */
    public static function getPermissionPrefix(): string;

    /**
     * Get all permission names for this model.
     *
     * Returns fully qualified permission names like:
     * ['view_patients', 'create_patients', 'update_patients', 'delete_patients']
     *
     * @return array<int, string>
     */
    public static function getPermissions(): array;
}
