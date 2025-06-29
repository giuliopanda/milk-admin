<?php 
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Permission Management System
 * 
 * This class manages all permissions used throughout the system. It sets up
 * permission groups and handles user permission checks. By default, users have
 * access to everything unless explicitly restricted.
 * 
 * The permission system works by first blocking all access and then enabling only
 * the specific permissions granted to the user.
 * 
 * Special permission groups (see autoload.php):
 * - _user: A conventional group for managing administrators and guests outside the Auth module
 * - _user.is_admin: Administrator user flag
 * - _user.is_guest: Guest user flag (if false, the user is logged in)
 * - _user.is_authenticated: Logged in user flag (alias of !is_guest, returns true if user is logged in)
 *
 * @example
 * ```php
 * // Check if user is a guest
 * if (Permissions::check('_user.is_guest')) {
 *     // Handle guest user
 * }
 * 
 * // Check if user is logged in (Method 1)
 * if (!Permissions::check('_user.is_guest')) {
 *     // Handle logged-in user
 * }
 * 
 * // Check if user is logged in (Method 2 - more intuitive)
 * if (Permissions::check('_user.is_authenticated')) {
 *     // Handle logged-in user
 * }
 * 
 * // Check if user is an administrator
 * if (Permissions::check('_user.is_admin')) {
 *     // Handle administrator
 * }
 * ```
 *
 * @package     MilkCore
 */

class Permissions
{
    /**
     * List of all permission definitions in the system
     * 
     * This array stores all defined permissions organized by group.
     * Format: ['group_name' => ['permission_name' => 'Permission Title']]
     * 
     * @var array
     */
    public static $permissions = [];
    /**
     * Titles for permission groups
     * 
     * This array stores display titles for permission groups.
     * If a group doesn't have a title, it won't appear in the Auth module's permission settings.
     * 
     * @var array
     */
    public static $group_title = [];
    
    /**
     * Current user's active permissions
     * 
     * This array stores the current user's permission states (true/false).
     * Format: ['group_name' => ['permission_name' => true|false]]
     * 
     * @var array
     */
    public static $user_permissions = [];
    
    /**
     * Groups with exclusive permissions
     * 
     * In exclusive groups, only one permission can be active at a time.
     * Format: ['group_name' => true]
     * 
     * @var array
     */
    public static $exclusive_groups = [];

    /**
     * Sets permissions for a group
     * Does not set user permissions!
     * 
     * This method defines permissions for a specific group. When a permission is set,
     * the current user's permission is initially set to false. The user management
     * system will later activate the permissions granted to the user.
     *
     * @example
     * ```php
     * // Define permissions for the 'auth' group
     * Permissions::set('auth', [
     *     'manage' => 'Manage Users',
     *     'delete' => 'Delete Users'
     * ], 'Authentication', false);
     * ```
     *
     * @param string $group The permission group name
     * @param array $permissions List of permissions as ['permission_name' => 'Title']
     * @param string|bool $group_name Optional title for the permission group
     * @param bool $exclusive Whether the group is exclusive (only one permission active at a time)
     * @return void
     */
    public static function set($group, $permissions, $group_name = false, $exclusive = false) {
        if (!array_key_exists($group, self::$permissions)) {
            self::$permissions[$group] = [];
        }
        if (!array_key_exists($group, self::$user_permissions)) {
            self::$user_permissions[$group] = [];
        }
        
        foreach ($permissions as $permission_name => $title) {
            self::$permissions[$group][$permission_name] = $title;
            if (!array_key_exists($permission_name, self::$user_permissions[$group])) {
                self::$user_permissions[$group][$permission_name] = false;
            }
        }
        if ($group_name) {
            self::$group_title[$group] = $group_name;
        }
        if ($exclusive) {
            self::set_exclusive_group($group, true);
        }
    }

    /**
     * Gets permissions for a group or all permissions if no group is specified
     * Does not return user permissions!
     * 
     * This method returns the permissions for a specific group or all permissions
     * if no group is specified.
     *
     * @example
     * ```php
     * // Get all 
     * 
     * $all_permissions = Permissions::get();
     * // Get permissions for the 'auth' group
     * $auth_permissions = Permissions::get('auth');
     * ```
     *
     * @param string $group Optional group name
     * @return array List of permissions
     */
    public static function get($group = '') {
        if ($group == '') {
            return self::$permissions;
        }
        if (array_key_exists($group, self::$permissions)) {
            return self::$permissions[$group];
        }
        return [];
    }

    /**
     * Sets the title for a permission group
     * 
     * If a group title is not set, it won't appear in the Auth module's permission settings.
     * The title can also be set directly using the third parameter of set().
     *
     * @example
     * ```php
     * Permissions::set_group_title('auth', 'Authentication');
     * ```
     *
     * @param string $group The permission group name
     * @param string $title The display title for the group
     * @return void
     */
    public static function set_group_title($group, $title) {
        self::$group_title[$group] = $title;
    }

    /**
     * Gets the title for a permission group
     * 
     * Returns the display title for a permission group or an empty string if not set.
     *
     * @param string $group The permission group name
     * @return string The group title or empty string if not set
     */
    public static function get_group_title($group) {
        if (array_key_exists($group, self::$group_title)) {
            return self::$group_title[$group];
        }
        return '';
    }

    /**
     * Sets a group as exclusive or non-exclusive
     * 
     * In exclusive groups, only one permission can be active at a time.
     * In non-exclusive groups, multiple permissions can be active simultaneously.
     * When a group is set as exclusive, this method ensures only one permission remains active.
     *
     * @param string $group The permission group name
     * @param bool $exclusive True if the group should be exclusive, false otherwise
     * @return void
     */
    public static function set_exclusive_group($group, $exclusive = true) {
        if ($exclusive) {
            self::$exclusive_groups[$group] = true;
            
            // Se il gruppo è già definito con permessi, assicuriamo che solo uno sia attivo
            if (isset(self::$user_permissions[$group]) && count(self::$user_permissions[$group]) > 0) {
                $found_active = false;
                foreach (self::$user_permissions[$group] as $perm => $value) {
                    if ($value && !$found_active) {
                        $found_active = true;
                    } else {
                        self::$user_permissions[$group][$perm] = false;
                    }
                }
            }
        } else {
            unset(self::$exclusive_groups[$group]);
        }
    }

    /**
     * Checks if a group is exclusive
     * 
     * Determines whether a permission group is exclusive (only one permission can be active at a time).
     *
     * @param string $group The permission group name
     * @return bool True if the group is exclusive, false otherwise
     */
    public static function is_exclusive_group($group) {
        return isset(self::$exclusive_groups[$group]) && self::$exclusive_groups[$group];
    }

    /**
     * Gets the list of all permission groups with titles
     * 
     * Returns an array of all permission groups that have titles set.
     *
     * @return array Array of group titles indexed by group name
     */
    public static function get_groups() {
        return self::$group_title;
    }

    /**
     * Checks if the current user has permission for a specific action
     * 
     * This is the main method for permission verification throughout the application.
     * Permission format is 'group.permission_name' (e.g., 'auth.manage').
     * 
     * Special cases:
     * - If the user has '_user.is_admin' permission, all checks return true
     * - '_user.is_guest' can be checked to determine if the user is a guest
     * - '_user.is_authenticated' can be checked to determine if the user is logged in (alias of !is_guest)
     *
     * @example
     * ```php
     * // Check if user can manage users
     * if (Permissions::check('auth.manage')) {
     *     // User can manage users
     * }
     * 
     * // Check if user is an administrator
     * if (Permissions::check('_user.is_admin')) {
     *     // User is an administrator
     * }
     * 
     * // Check if user is logged in
     * if (Permissions::check('_user.is_authenticated')) {
     *     // User is logged in
     * }
     * ```
     *
     * @param string $permission Permission in format 'group.permission_name'
     * @return bool True if the user has the permission, false otherwise
     */
    public static function check($permission)  {
        if ((self::$user_permissions['_user']['is_admin'] ?? false) && ($permission != '_user.is_guest')) {
            return true;
        }
        $permission = explode('.', $permission);
        if (count($permission) == 2) {
            $group = $permission[0];
            $permission_name = $permission[1];
            if ($group == '_user') {
                if ($permission_name == 'is_admin') {
                    return (self::$user_permissions['_user']['is_admin'] ?? false);
                } else if ($permission_name == 'is_guest') {
                    return (self::$user_permissions['_user']['is_guest'] ?? false);
                } else if ($permission_name == 'is_authenticated') {
                    // is_authenticated is the opposite of is_guest
                    return !(self::$user_permissions['_user']['is_guest'] ?? false);
                }
            } else if (self::$user_permissions['_user']['is_admin'] ?? false) {
                return true;
            }
      
            if (array_key_exists($group, self::$user_permissions)) {
                if (array_key_exists($permission_name, self::$user_permissions[$group])) {
                    return self::$user_permissions[$group][$permission_name];
                }
            }
        }
        return false;
    }

    /**
     * Generate a standardized JSON error response for permission denied
     * 
     * @param string $custom_message Optional custom message to customizations default
     * @return void Outputs JSON response and exits
     */
    public static function check_json( $permission, $custom_message = ''): void {
        if (!self::check($permission)) { 
            http_response_code(403); // Set HTTP 403 Forbidden
            $action = $_REQUEST['action'] ? "You don't have permission for "._r($_REQUEST['action'])." action" : "You don't have permission for "._r($permission);
            $message = $custom_message ?: $action;
            
            $response = [
                'success' => false,
                'msg' => $message,
                'permission_denied' => true,
                'code' => 403
            ];
            Get::response_json($response);
        }
    }

    /**
     * Sets permissions for the current user
     * 
     * This method assigns permission values to the current user.
     * For exclusive groups, only one permission can be active at a time.
     *
     * @example
     * ```php
     * // Set user permissions for the 'auth' group
     * Permissions::set_user_permissions('auth', [
     *     'manage' => true,
     *     'delete' => false
     * ]);
     * ```
     *
     * @param string $group The permission group name
     * @param array $permissions Array of permissions as ['permission_name' => true|false]
     * @return void
     */
    public static function set_user_permissions($group, $permissions) {
        if (!array_key_exists($group, self::$user_permissions)) {
            self::$user_permissions[$group] = [];
        }
        
        // Se il gruppo è esclusivo, prima disattiviamo tutti i permessi
        if (self::is_exclusive_group($group)) {
            foreach (self::$user_permissions[$group] as $perm => $value) {
                self::$user_permissions[$group][$perm] = false;
            }
            
            // Poi attiviamo solo il primo permesso che trova a true
            $found_active = false;
            foreach ($permissions as $permission_name => $value) {
                if ( (bool)$value && !$found_active) {
                    self::$user_permissions[$group][$permission_name] = true;
                    $found_active = true;
                } else {
                    self::$user_permissions[$group][$permission_name] = false;
                }
            }
        } else {
            // Per gruppi non esclusivi, settiamo normalmente

            foreach ($permissions as $permission_name => $value) {
                self::$user_permissions[$group][$permission_name] = (bool)$value;
            }
        }
    }

    /**
     * Gets user permissions
     * 
     * @return array User permissions
     */
    public static function get_user_permissions() {
        return self::$user_permissions;
    }
}