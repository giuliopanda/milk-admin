<?php
namespace App\Abstracts\Services\AbstractModule;

use App\Permissions;

!defined('MILK_DIR') && die();

class ModuleAccessService
{
    /**
     * Register permission groups and permission keys for a module during bootstrap.
     * Supports both local keys (e.g. "access") and cross-group keys (e.g. "blog.edit").
     */
    public static function registerModulePermissions($page, $title, $permissions, $access): void
    {
        Permissions::setGroupTitle($page, $title);

        if (is_array($permissions) && $access == 'authorized') {
            foreach ($permissions as $key => $desc) {
                $permissionGroup = $page;
                $permissionKey = $key;

                if (is_string($key) && strpos($key, '.') !== false) {
                    $parts = explode('.', $key, 3);
                    if (count($parts) == 2) {
                        [$permissionGroup, $permissionKey] = $parts;
                    }
                }

                if ($permissionGroup !== $page && Permissions::getGroupTitle($permissionGroup) === '') {
                    Permissions::setGroupTitle($permissionGroup, ucfirst($permissionGroup));
                }

                Permissions::set($permissionGroup, [$permissionKey => $desc]);
            }

            return;
        }

        if ($access == 'authorized') {
            Permissions::set($page, $permissions);
        }
    }

    /**
     * Evaluate module visibility against the current user permissions.
     * Mirrors the same access policy used by AbstractModule before the refactor.
     */
    public static function canAccess($page, $access, $permissions): bool
    {
        $hook = $page != null ? $page : null;
        $permission = false;

        switch ($access) {
            case 'public':
                $permission = true;
                break;
            case 'registered':
                $permission = (Permissions::check('_user.is_guest', $hook) == false);
                break;
            case 'authorized':
                $permissionKey = self::getPermissionName($permissions);
                $permissionGroup = $page;

                if (is_string($permissionKey) && strpos($permissionKey, '.') !== false) {
                    $parts = explode('.', $permissionKey, 3);
                    if (count($parts) == 2) {
                        [$permissionGroup, $permissionKey] = $parts;
                    }
                }

                $permission = Permissions::check($permissionGroup . '.' . $permissionKey, $hook);
                break;
            case 'admin':
                $permission = Permissions::check('_user.is_admin', $hook);
                break;
        }

        return $permission;
    }

    /**
     * Return the primary permission key for "authorized" modules.
     * Falls back to "access" when no explicit permission array is configured.
     */
    public static function getPermissionName($permissions)
    {
        return (is_array($permissions) && count($permissions) > 0)
            ? array_key_first($permissions)
            : 'access';
    }
}
