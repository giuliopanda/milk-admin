<?php
namespace App\Attributes;

!defined('MILK_DIR') && die();

/**
 * AccessLevel Attribute
 *
 * Defines access level required for a specific controller method.
 * Takes precedence over module-level access configuration.
 *
 * Usage:
 * ```php
 * #[AccessLevel('public')]
 * #[RequestAction('my-action')]
 * protected function myAction() {
 *     // This method is accessible to everyone
 * }
 *
 * #[AccessLevel('admin')]
 * #[RequestAction('admin-action')]
 * protected function adminAction() {
 *     // This method is accessible only to admins
 * }
 * ```
 *
 * Available access levels:
 * - 'public': Anyone can access (no authentication required)
 * - 'registered': Only logged-in users can access
 * - 'admin': Only administrators can access
 * - 'authorized': Requires specific permission (format: 'page.permission_name')
 *
 * @package App\Attributes
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class AccessLevel {

    /**
     * @param string $level Access level: 'public', 'registered', 'admin', or 'authorized:permission'
     */
    public function __construct(
        public string $level
    ) {}

    /**
     * Get the base access level (removes permission suffix if present)
     *
     * @return string The base access level
     */
    public function getBaseLevel(): string {
        if (str_contains($this->level, ':')) {
            return explode(':', $this->level, 2)[0];
        }
        return $this->level;
    }

    /**
     * Get the permission name if access level is 'authorized'
     *
     * @return string|null The permission name or null if not authorized level
     */
    public function getPermission(): ?string {
        if (str_contains($this->level, ':')) {
            return explode(':', $this->level, 2)[1];
        }
        return null;
    }
}
