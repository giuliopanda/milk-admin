<?php
namespace Modules\Auth;
use App\Permissions;
use App\Abstracts\AbstractModel;
use App\Attributes\ToDatabaseValue;


class UserModel extends AbstractModel {
    public const PASSWORD_MIN_LENGTH = 8;

    protected string $table = '#__users';
    protected string $primary_key = 'id';

    protected function configure($rule): void {
        $languages = \App\Config::get('available_locales', []);
        $default_language = \App\Config::get('locale', 'en_US');
        $rule->table($this->table)
            ->id('id')
            ->string('username', 255)->required()->label('Username')
            ->string('email', 255)->required()->label('Email')->formType('email')
            ->string('password', 255)->required()->label('Password')->formType('password')
            ->datetime('registered')->nullable()->label('Registration Date')
            ->datetime('last_login')->nullable()->label('Last Login')
            ->string('activation_key', 255)->default('')->label('Activation Key')
            ->int('status')->default(0)->label('Status')
                ->formType('list')
                ->formParams(['options' => [
                    0 => 'Inactive',
                    1 => 'Active'
                ]])
            ->boolean('is_admin')->default(0)->label('Administrator')
            ->text('permissions')->default('{}')->label('Permissions')
            ->string('timezone', 64)->default('UTC')->label('Timezone')
             ->string('locale', 10)->options($languages)->formType('select')->default($default_language)->label('Language');
    }

    #[ToDatabaseValue('password')]
    public function sqlPassword($current_record_obj) {
        $password = trim((string)($current_record_obj->password ?? ''));
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            throw new \InvalidArgumentException('Password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters long');
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }

    #[ToDatabaseValue('permissions')]
    public function sqlPermissions($current_record_obj) {
        $permissions = $current_record_obj->permissions ?? [];
        return json_encode($this->normalizePermissionsPayload($permissions));
    }

    /**
     * Get user by ID with normalized permissions.
     */
    public function getUserById(int $id): ?object {
        $this->last_error = '';
        if ($id <= 0) {
            $this->last_error = 'Invalid user ID';
            return null;
        }
        if (!$this->db) {
            $this->last_error = 'Database connection unavailable';
            return null;
        }

        $user = $this->db->getRow('SELECT * FROM `#__users` WHERE id = ?', [$id]);
        if (!is_object($user)) {
            $this->last_error = 'User not found';
            return null;
        }

        return $this->hydratePermissions($user);
    }

    /**
     * Find user by username with optional inactive users.
     */
    public function findByUsername(string $username, bool $include_inactive = false): ?object {
        $this->last_error = '';
        if ($username === '') {
            $this->last_error = 'Username is required';
            return null;
        }
        if (!$this->db) {
            $this->last_error = 'Database connection unavailable';
            return null;
        }

        $status_condition = $include_inactive ? '' : ' AND status = 1';
        $query = 'SELECT * FROM ' . $this->db->qn($this->table) . ' WHERE username = ?' . $status_condition;
        $user_db = $this->db->getRow($query, [$username]);
        if (!is_object($user_db)) {
            $this->last_error = $include_inactive ? 'User not found' : 'User not found or inactive';
            return null;
        }

        return $this->hydratePermissions($user_db);
    }

    /**
     * Verify active user credentials and return hydrated user.
     */
    public function verifyActiveCredentials(string $username, string $password): ?object {
        $this->last_error = '';
        if ($username === '' || $password === '') {
            $this->last_error = 'Username/email and password are required';
            return null;
        }
        if (!$this->db) {
            $this->last_error = 'Database connection unavailable';
            return null;
        }

        $query = 'SELECT * FROM ' . $this->db->qn($this->table) . ' WHERE username = ? AND status = 1';
        $user_db = $this->db->getRow($query, [$username]);

        if (!is_object($user_db)) {
            // Keep timing similar when the user does not exist.
            if (password_verify($password, '$2y$10$dummy.hash.to.prevent.timing.attacks')) {
                // Intentionally ignored.
            }
            $this->last_error = 'User not found or inactive';
            return null;
        }

        if (!password_verify($password, (string) ($user_db->password ?? ''))) {
            $this->last_error = 'Invalid password';
            return null;
        }

        return $this->hydratePermissions($user_db);
    }

    /**
     * Update user's last login timestamp.
     */
    public function updateLastLogin(int $user_id): bool {
        $this->last_error = '';
        if ($user_id <= 0) {
            $this->last_error = 'Invalid user ID';
            return false;
        }
        if (!$this->db) {
            $this->last_error = 'Database connection unavailable';
            return false;
        }

        $result = $this->db->update($this->table, [
            'last_login' => date('Y-m-d H:i:s')
        ], ['id' => $user_id]);

        if ($result === false) {
            $this->last_error = 'Failed to update last login: ' . $this->db->last_error;
            return false;
        }

        return true;
    }

    /**
     * Update activation key for the user.
     */
    public function updateActivationKey(int $user_id, string $activation_key): bool {
        $this->last_error = '';
        if ($user_id <= 0) {
            $this->last_error = 'Invalid user ID';
            return false;
        }
        if (!$this->db) {
            $this->last_error = 'Database connection unavailable';
            return false;
        }

        $result = $this->db->update($this->table, [
            'activation_key' => $activation_key
        ], ['id' => $user_id]);

        if ($result === false) {
            $this->last_error = 'Failed to update activation key: ' . $this->db->last_error;
            return false;
        }

        return true;
    }

    /**
     * Save user data to database.
     *
     * @return int|bool Insert ID on create, bool on update, false on failure.
     */
    public function saveUserData($id, $username, $email, $password = '', $status = 1, $is_admin = 0, $permissions = [], $timezone = 'UTC', $locale = '', $allow_weak_password = false) {
        $this->last_error = '';
        if (!$this->db) {
            $this->last_error = 'Database connection unavailable';
            return false;
        }

        $password = trim((string) $password);
        if (!$allow_weak_password && $password !== '' && strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $this->last_error = 'Password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters long';
            return false;
        }

        $save_permissions = $this->normalizePermissionsPayload($permissions);
        if ($locale == '') {
            $locale = \App\Config::get('locale', 'en_US');
        }

        $permissions_json = json_encode($save_permissions);
        if (!is_string($permissions_json)) {
            $permissions_json = '{}';
        }

        $data = [
            'username' => $username,
            'email' => $email,
            'status' => $status,
            'is_admin' => _absint($is_admin),
            'permissions' => $permissions_json,
            'registered' => date('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'locale' => $locale
        ];

        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ((int) $id > 0) {
            $result = $this->db->update($this->table, $data, ['id' => _absint($id)]);
            if ($result === false) {
                $this->last_error = 'Failed to update user: ' . $this->db->last_error;
            }
            return $result;
        }

        $result = $this->db->insert($this->table, $data);
        if ($result === false) {
            $this->last_error = 'Failed to create user: ' . $this->db->last_error;
        }

        return $result;
    }

    /**
     * Check if a username already exists (optionally excluding an ID).
     */
    public function usernameExists(string $username, int $exclude_id = 0): bool {
        $this->last_error = '';
        if ($username === '') {
            return false;
        }
        if (!$this->db) {
            $this->last_error = 'Database connection unavailable';
            return false;
        }

        if ($exclude_id > 0) {
            $row = $this->db->getRow(
                'SELECT id FROM `#__users` WHERE username = ? AND id != ? LIMIT 1',
                [$username, $exclude_id]
            );
        } else {
            $row = $this->db->getRow(
                'SELECT id FROM `#__users` WHERE username = ? LIMIT 1',
                [$username]
            );
        }

        return is_object($row);
    }

    /**
     * Update user status by ID.
     */
    public function updateStatusById(int $id, int $status): bool {
        $this->last_error = '';
        if ($id <= 0) {
            $this->last_error = 'Invalid user ID';
            return false;
        }
        if (!$this->db) {
            $this->last_error = 'Database connection unavailable';
            return false;
        }

        $result = $this->db->update($this->table, ['status' => $status], ['id' => $id]);
        if ($result === false) {
            $this->last_error = 'Failed to update user status: ' . $this->db->last_error;
            return false;
        }

        return true;
    }

    /**
     * Permanently delete user by ID.
     */
    public function hardDeleteById(int $id): bool {
        $this->last_error = '';
        if ($id <= 0) {
            $this->last_error = 'Invalid user ID';
            return false;
        }

        $result = $this->delete($id);
        if ($result === false) {
            return false;
        }

        return true;
    }

    /**
     * Normalize persisted permissions payload.
     *
     * @param mixed $permissions
     * @return array<string, array<string, int>>
     */
    private function normalizePermissionsPayload($permissions): array {
        $save_permissions = [];
        $permissions_groups = Permissions::getGroups();

        foreach ($permissions_groups as $group => $_) {
            $list_of_permissions = Permissions::get($group);
            foreach ($list_of_permissions as $permission_name => $_) {
                if (!is_array($permissions) || !isset($permissions[$group][$permission_name])) {
                    $save_permissions[$group][$permission_name] = 0;
                } else {
                    $save_permissions[$group][$permission_name] = ($permissions[$group][$permission_name] == 1) ? 1 : 0;
                }
            }
        }

        if (is_array($permissions)) {
            foreach ($permissions as $group => $permissions_group) {
                if (!isset($save_permissions[$group])) {
                    $save_permissions[$group] = [];
                }
                if (!is_array($permissions_group)) {
                    continue;
                }
                foreach ($permissions_group as $permission_name => $permission_value) {
                    $save_permissions[$group][$permission_name] = (int) $permission_value;
                }
            }
        }

        return $save_permissions;
    }

    /**
     * Hydrate permission booleans and user flags.
     */
    private function hydratePermissions(object $user): object {
        $user->is_guest = 0;
        $temp_perm = json_decode((string) ($user->permissions ?? ''), true);
        if (!is_array($temp_perm)) {
            $temp_perm = [];
        }

        $user->permissions = [];
        $user->permissions['_user'] = [
            'is_admin' => ((int) ($user->is_admin ?? 0) === 1),
            'is_guest' => false
        ];

        foreach ($temp_perm as $group => $permission_group) {
            if (!is_array($permission_group)) {
                continue;
            }
            if (!array_key_exists($group, $user->permissions)) {
                $user->permissions[$group] = $permission_group;
            }

            foreach ($permission_group as $permission_name => $value) {
                if ((int) ($user->status ?? 0) !== 1) {
                    $user->permissions[$group][$permission_name] = false;
                    continue;
                }
                if ((int) ($user->is_admin ?? 0) === 1) {
                    $user->permissions[$group][$permission_name] = true;
                    continue;
                }
                $user->permissions[$group][$permission_name] = ($value === 1 || $value === true || $value === '1' || $value === 'true' || $value === 't');
            }
        }

        return $user;
    }
}
