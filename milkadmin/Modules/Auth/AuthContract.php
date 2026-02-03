<?php
namespace Modules\Auth;

use App\{AuthContractInterface, Cli, Config, Get, MessagesHandler, Permissions, Token};

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Authentication and user management class
 * 
 * Handles user authentication, session management, and security features
 * including brute force protection and user permissions.
 */
class AuthContract implements AuthContractInterface
{
    /**
     * Current authenticated user object
     * 
     * @var object|null User object with properties: username, folder, pwd, secret_code, session_date
     */
    public $current_user = null;

    /**
     * Session expiration time in minutes
     * 
     * @var int
     */
    public $expired_session = null;

    /**
     * Current session object
     * 
     * @var object|null
     */
    public $session = null;

    /**
     * Last error message
     * 
     * @var string
     */
    public $last_error = '';

    /**
     * Last insert id (when save user)
     * 
     * @var int
     */
    public $last_insert_id = 0;

    // Anti-brute force configuration
    /**
     * Maximum allowed login attempts before blocking
     * 
     * @var int
     */
    private $max_attempts = 5;

    /**
     * Lockout time in minutes after max attempts reached
     * 
     * @var int
     */
    private $lockout_time = 30;

    /**
     * Time window in minutes for counting attempts
     * 
     * @var int
     */
    private $attempts_window = 15;

    /**
     * System lockdown multiplier (blocks entire system with 10x normal attempts)
     * 
     * @var int
     */
    private $system_lockdown_multiplier = 10;

    /**
     * Singleton instance
     * 
     * @var Auth|null
     */
    private static $instance = null;

    /**
     * Get singleton instance of Auth class
     * 
     * @return Auth
     */
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new AuthContract();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize session and user authentication
     */
    public function __construct() {

        $this->last_error = '';
        $this->session = new \stdClass();
        $this->expired_session = Config::get('auth_expires_session', 15);

        // Load anti-brute force configurations
        $this->max_attempts = Config::get('auth_max_attempts', 5);
        $this->lockout_time = Config::get('auth_lockout_time', 15);
        $this->attempts_window = Config::get('auth_attempts_window', 5);
        $this->system_lockdown_multiplier = Config::get('auth_system_lockdown_multiplier', 15);


        if (!Cli::isCli() && substr(strtolower($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 14) != 'milkhttpclient' && Config::get('version') !== null) {

            // Check if user already has an active session
            $phpsessid = session_id();
            $currentDateTime = new \DateTime();
            $currentDateTime->modify('-' . ($this->expired_session - 2) . ' minutes');
            $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
            if (Get::db() && Get::db()->checkConnection()) {
                /*
                $session = Get::db()->getRow(
                    'SELECT * FROM `#__sessions` WHERE (phpsessid = ? OR old_phpsessid = ?) AND `session_date` > ? AND ip_address = ? AND user_agent = ?',
                    [$phpsessid, $phpsessid, $formattedDateTime, Get::clientIp(), $_SERVER['HTTP_USER_AGENT'] ?? '']
                );
                */
                $session = Get::db()->getRow(
                    'SELECT * FROM `#__sessions` WHERE (phpsessid = ? OR old_phpsessid = ?) AND `session_date` > ? AND ip_address = ? ',
                    [$phpsessid, $phpsessid, $formattedDateTime, Get::clientIp()]
                );
                if ($session) {
                    $this->session = $session;
                    // Use global secret key for CSRF tokens (not session-specific)
                    Token::config(Config::get('secret_key'), Config::get('token_key'));

                    if ($session->user_id > 0) {
                        $this->current_user = $this->getUser($session->user_id);
                    } else {
                        $this->setGuestUser($session);
                    }

                } else {
                    // No active session - check for remember me cookie
                    $rememberMeSuccess = $this->attemptRememberMeLogin();

                    if (!$rememberMeSuccess) {
                        $this->setGuestUser();
                        $this->saveSession();
                    }
                }
            } else {
                $this->setGuestUser();
            }
            $this->setCurrentUserPermissions();
        } else {
            $this->setGuestUser();
            $this->setCurrentUserPermissions();
        }
    }

    /**
     * Get the last error that occurred
     * 
     * @return string The last error message
     */
    public function getLastError() {
        return $this->last_error;
    }

    /**
     * Set authenticated user
     * 
     * @param int $user_id User ID to set as authenticated
     */
    public function setAuthUser($user_id) {
        $this->current_user = $this->getUser($user_id);
        $this->setCurrentUserPermissions();
    }

    /**
     * Set current user as guest
     * 
     * @param object|null $session Existing session object or null to create new
     */
    private function setGuestUser($session = null) {
        $this->last_error = '';
        $phpsessid = session_id();
        
        if (($this->session->id ?? 0) > 0) {
            $this->updateUserSession(0);
        }
        
        $this->current_user = (object)[
            'id' => 0, 
            'username' => 'Guest', 
            'email' => '', 
            'password' => '', 
            'registered' => date('Y-m-d H:i:s'), 
            'status' => 1, 
            'is_admin' => 0, 
            'permissions' => [], 
            'is_guest' => 1
        ];

        if ($session == null) {
            $this->session = (object)[
                'user_id' => 0, 
                'phpsessid' => $phpsessid, 
                'username' => 'Guest', 
                'email' => '', 
                'status' => 1, 
                'is_admin' => 0, 
                'permissions' => [], 
                'is_guest' => 1, 
                'ip_address' => Get::clientIp(), 
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'session_date' => date('Y-m-d H:i:s'), 
                'secret_key' => ''
            ];
        } else {
            $this->session = $session;
        }
    }

    /**
     * Save current session to database
     */
    private function saveSession() {
        $this->last_error = '';
        $session_array = [];
        $session_fields = ['id', 'phpsessid', 'ip_address', 'user_agent', 'session_date', 'user_id', 'secret_key'];
        
        foreach ($session_fields as $field) {
            if (isset($this->session->$field)) {
                $session_array[$field] = $this->session->$field;
            }
        }
        
        $session_array['secret_key'] = bin2hex(random_bytes(32));
        $this->session->secret_key = $session_array['secret_key'];
        // Use global secret key for CSRF tokens (not session-specific)
        Token::config(Config::get('secret_key'), Config::get('token_key'));

        // Remove any duplicate sessions
        Get::db()->delete('#__sessions', ['phpsessid' => $this->session->phpsessid]);
        if (isset($this->session->old_phpsessid)) {
            Get::db()->delete('#__sessions', ['phpsessid' => $this->session->old_phpsessid]);
        }
        
        $id = Get::db()->insert('#__sessions', $session_array);
        if ($id !== false) {
            $this->session->id = $id;
        } else {
            $this->last_error = 'Unable to create guest session: ' . Get::db()->last_error;
            echo "<p>" . Get::db()->last_error . "</p>";
            die('Unable to create guest session');
        }
    }

    /**
     * Get current user or specific user by ID
     * 
     * @param int $id User ID to fetch, 0 for current user
     * @return object|null User object with permissions or null if not found
     */
    public function getUser($id = 0) {
        $this->last_error = '';
        
        if ($id == 0) {
            return $this->current_user;
        }
        
        $user = Get::db()->getRow('SELECT * FROM `#__users` WHERE id = ?', [$id]);
        if (!$user) {
            $this->last_error = 'User not found';
            return null;
        } else {
            $user->is_guest = 0;
        }
        
        $temp_perm = json_decode($user->permissions, true);
        if (!is_array($temp_perm)) {
            $temp_perm = [];
        }
        
        $user->permissions = [];
        $user->permissions['_user'] = [
            'is_admin' => ($user->is_admin == 1), 
            'is_guest' => ($user->is_guest == 1)
        ];
        
        foreach ($temp_perm as $group => $permission) {
            if (!array_key_exists($group, $user->permissions)) {
                $user->permissions[$group] = $permission;
            }
            
            foreach ($permission as $permission_name => $value) {
                if ($user->status != 1) {
                    $user->permissions[$group][$permission_name] = false;
                    continue;
                }
                if ($user->is_admin == 1) {
                    $user->permissions[$group][$permission_name] = true;
                    continue;
                }
                $user->permissions[$group][$permission_name] = ($value === 1 || $value === true || $value === '1' || $value === 'true' || $value === 't');
            }
        }

        return $user;
    }

    /**
     * Set permissions for current user
     */
    private function setCurrentUserPermissions() {
        $user = $this->current_user;
        if ($this->current_user == null) {
            return;
        }
        
        if ($user->is_admin == 1) {
            $user->permissions['_user'] = ['is_admin' => true, 'is_guest' => false];
        } else if ($user->is_guest == 1) {
            $user->permissions['_user'] = ['is_admin' => false, 'is_guest' => true];
        }
        
        foreach ($user->permissions as $group => $user_permissions) {
            Permissions::setUserPermissions($group, $user_permissions);
        }
    }

    /**
     * Check if an IP, username, or session is blocked based on recent failed attempts
     * 
     * @param string $identifier IP address, username/email, or session_id
     * @param string $type Type of identifier: 'ip', 'username', or 'session'
     * @return bool True if blocked, false otherwise
     */
    private function isBlocked($identifier, $type = 'ip') {
        $attempts = $this->countFailedAttempts($identifier, $type);
        return $attempts >= $this->max_attempts;
    }

    /**
     * Count recent failed login attempts
     * 
     * @param string $identifier IP address, username/email, or session_id
     * @param string $type Type of identifier: 'ip', 'username', or 'session'
     * @return int Number of failed attempts
     */
    private function countFailedAttempts($identifier, $type = 'ip') {
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . $this->attempts_window . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
        
        if ($type === 'ip') {
            $query = 'SELECT COUNT(*) as count FROM `#__login_attempts` WHERE ip_address = ? AND attempt_time > ?';
            $params = [$identifier, $formattedDateTime];
        } else if ($type === 'session') {
            $query = 'SELECT COUNT(*) as count FROM `#__login_attempts` WHERE session_id = ? AND attempt_time > ?';
            $params = [$identifier, $formattedDateTime];
        } else {
            $query = 'SELECT COUNT(*) as count FROM `#__login_attempts` WHERE username_email = ? AND attempt_time > ?';
            $params = [$identifier, $formattedDateTime];
        }
        
        $result = Get::db()->getRow($query, $params);
        return $result ? $result->count : 0;
    }

    /**
     * Count all failed login attempts in the system
     * 
     * @return int Total number of system-wide failed attempts
     */
    private function countSystemFailedAttempts() {
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . $this->attempts_window . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
        
        $result = Get::db()->getRow(
            'SELECT COUNT(*) as count FROM `#__login_attempts` WHERE attempt_time > ?',
            [$formattedDateTime]
        );
        
        return $result ? $result->count : 0;
    }

    /**
     * Send email notification to all administrators about security issues
     */
    private function notifyAdministrators() {
        /*
        // Avoid sending too many notifications - maximum one every 15 minutes
        $cache_key = 'last_system_lockdown_notification';
        $last_notification = Config::get_cache($cache_key, 0);
        
        if (time() - $last_notification < 900) { // 900 seconds = 15 minutes
            return;
        }
        
        // Save timestamp of last notification
        Config::set_cache($cache_key, time());

        // Get all administrators
        $admins = Get::db()->getResults('SELECT * FROM `#__users` WHERE is_admin = 1 AND status = 1');
        
        if (!$admins) {
            return;
        }
        
        $attempts_count = $this->countSystemFailedAttempts();
        $subject = '[SECURITY ALERT] Login system under attack';
        $message = sprintf(
            "The login system is experiencing a possible brute force attack.\n\n" .
            "Detected %d failed login attempts in the last %d minutes.\n" .
            "Date and time: %s\n" .
            "Most active IPs:\n%s\n\n" .
            "The system has automatically blocked all login attempts.\n" .
            "Access will be restored automatically when attempts decrease.",
            $attempts_count,
            $this->attempts_window,
            date('Y-m-d H:i:s'),
            $this->get_top_attacking_ips()
        );
        
        foreach ($admins as $admin) {
            if ($admin->email) {
                // Use mail() function or framework's email system
                mail($admin->email, $subject, $message, "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n");
            }
        }
        */
    }

    /**
     * Log a failed login attempt
     * 
     * @param string $username Username used in attempt
     * @param string $ip_address IP address of the attempt
     * @param string $session_id Session ID of the attempt
     */
    private function logFailedAttempt($username, $ip_address, $session_id) {
        $data = [
            'username_email' => $username,
            'ip_address' => $ip_address,
            'session_id' => $session_id,
            'attempt_time' => date('Y-m-d H:i:s')
        ];
        
        Get::db()->insert('#__login_attempts', $data);
    }

    /**
     * Clear failed login attempts when login is successful
     * 
     * @param string $username Username
     * @param string $ip_address IP address
     * @param string $session_id Session ID
     */
    private function clearFailedAttempts($username, $ip_address, $session_id) {
        // Remove failed attempts for this username, IP and session
        Get::db()->delete('#__login_attempts', ['username_email' => $username]);
        Get::db()->delete('#__login_attempts', ['ip_address' => $ip_address]);
        Get::db()->delete('#__login_attempts', ['session_id' => $session_id]);
    }

    /**
     * Verify user credentials without performing login
     * 
     * @param string $username Username or email address
     * @param string $password Plain text password
     * @return object|false Returns user object if credentials are correct, false otherwise
     */
    public function verifyCredentials($username, $password) {
        $this->last_error = '';
        
        if (empty($username) || empty($password)) {
            $this->last_error = 'Username/email and password are required';
            return false;
        }
        
        // First try to find user by username
        $user_db = Get::db()->getRow("SELECT * FROM " . Get::db()->qn('#__users') . " WHERE username = ? AND status = 1", [$username]);

        if (!is_object($user_db)) {
            password_verify($password, '$2y$10$dummy.hash.to.prevent.timing.attacks');
            $this->last_error = 'User not found or inactive';
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $user_db->password)) {
            $this->last_error = 'Invalid password';
            return false;
        }
        
        // Return the complete user object with permissions
        return $this->getUser($user_db->id);
    }

    /**
     * Find user by username 
     * 
     * @param string $username Username 
     * @param bool $include_inactive Whether to include inactive users in search (default: false)
     * @return object|false Returns user object if found, false otherwise
     */
    public function findUser($username, $include_inactive = false) {
        $this->last_error = '';
        
        if (empty($username)) {
            $this->last_error = 'Username is required';
            return false;
        }
        
        $status_condition = $include_inactive ? '' : ' AND status = 1;';
        
        // First try to find user by username
        $user_db = Get::db()->getRow("SELECT * FROM " . Get::db()->qn('#__users') . " WHERE username = ?" . $status_condition, [$username]);

        if (!is_object($user_db)) {
            $this->last_error = $include_inactive ? 'User not found' : 'User not found or inactive';
            return false;
        }
        
        // Return the complete user object with permissions
        return $this->getUser($user_db->id);
    }

    /**
     * Verify credentials and login user if correct
     * 
     * @param string $username
     * @param string $password Password
     * @param bool $save_sessions Whether to save session data (default: true)
     * @param bool $save_user_last_login Whether to save user last login (default: true)
     * @return bool True if login successful, false otherwise
     */
    public function login($username = '', $password = '', $save_sessions = true, $save_user_last_login = true) {
        $this->last_error = '';
       
        if ($username != '') {
          
            $ip_address = Get::clientIp();
            $session_id = session_id();
          
            // First check if there are too many total attempts in the system
            $system_attempts = $this->countSystemFailedAttempts();
            $system_threshold = $this->max_attempts * $this->system_lockdown_multiplier;
         
            if ($system_attempts >= $system_threshold) {
                $this->last_error = 'Access temporarily blocked due to too many failed login attempts.';
                MessagesHandler::addError($this->last_error);
                // Send notification to administrators if not already sent recently
                $this->notifyAdministrators();
                return false;
            }
          
            // Check if IP is blocked
            if ($this->isBlocked($ip_address, 'ip')) {
                $this->last_error = 'Access temporarily blocked due to too many failed login attempts.';
                MessagesHandler::addError($this->last_error);
                return false;
            }
           
            // Check if session is blocked
            if ($this->isBlocked($session_id, 'session')) {
                $this->last_error = 'Access temporarily blocked due to too many failed login attempts.';
                MessagesHandler::addError($this->last_error);
                return false;
            }
            
            // Check if username/email is blocked
            if ($this->isBlocked($username, 'username')) {
                $this->last_error = 'Access temporarily blocked due to too many failed login attempts.';
                MessagesHandler::addError($this->last_error);
                return false;
            }
    
            // Use verify_credentials to check login
            $user = $this->verifyCredentials($username, $password);
          
            if ($user !== false) {
                if ($save_sessions) {
                    // Regenerate session ID only if we're saving the session
                    $this->session->old_phpsessid = session_id();
                    session_regenerate_id(true);
                    $this->current_user = $user;
                    // Use global secret key for CSRF tokens (not session-specific)
                    Token::config(Config::get('secret_key'), Config::get('token_key'));
                    $this->setCurrentUserPermissions();
                    $this->updateUserSession($this->current_user->id);
                    if ($save_user_last_login) {
                        Get::db()->update('#__users', [
                            'last_login' => date('Y-m-d H:i:s')
                        ], ['id' => $this->current_user->id]);
                    }

                    // Handle "Remember Me" if enabled and checkbox selected
                    if (!empty($_POST['remember_me']) && Config::get('auth_remember_me_duration')) {
                        $this->createRememberMeToken($this->current_user->id);
                    }
                } else {
                    // For login without session, just set current user
                    $this->current_user = $user;
                    $this->setCurrentUserPermissions();
                }

                // Clear failed attempts after successful login
                $this->clearFailedAttempts($username, $ip_address, $session_id);

                // Log successful login to access logs
                $this->logAccessLogin($this->current_user->id, session_id(), $ip_address, $_SERVER['HTTP_USER_AGENT'] ?? '', $this->current_user->username ?? '');

                return true;
            } else {
                // Login failed - log the attempt
                $this->logFailedAttempt($username, $ip_address, $session_id);
            }
        }
        
        if ($save_sessions) {
            $this->cleanSessions();
        }
        return $this->isAuthenticated();
    }

    /**
     * Check if user is currently logged in
     * 
     * @return bool True if logged in, false if guest
     */
    public function isAuthenticated() {
        if ($this->current_user == null) {
            return false;
        } else {
            return ($this->current_user->is_guest != 1);
        }
    }

    /**
     * Logout current user and set as guest
     */
    public function logout() {
        $this->last_error = '';

        // Log logout before clearing session
        $current_session_id = session_id();
        if (!$this->current_user->is_guest) {
            $this->logAccessLogout($current_session_id);
        }

        // Delete remember me cookie and tokens for CURRENT DEVICE ONLY if user was authenticated
        if (!$this->current_user->is_guest && Config::get('auth_remember_me_duration')) {
            try {
                $rememberMeService = new RememberMeService();

                // Delete cookie
                $rememberMeService->deleteCookie();

                // Delete tokens ONLY for current device (using fingerprint)
                $rememberMeService->deleteTokensForCurrentDevice($this->current_user->id);

            } catch (\Exception $e) {
                error_log('Remember me cleanup error during logout: ' . $e->getMessage());
            }
        }

        // Set session to guest
        $this->setGuestUser();
        $this->session = (object)[
            'user_id' => 0,
            'phpsessid' => session_id(),
            'username' => 'Guest',
            'email' => '',
            'status' => 1,
            'is_admin' => 0,
            'permissions' => [],
            'is_guest' => 1
        ];
    }

    /**
     * Create activation key for user (used for password reset, etc.)
     * 
     * @param int $user_id User ID to create key for
     * @return string Encrypted activation key
     */
    public function createActivationKey($user_id) {
        $this->last_error = '';
        $secret_key = Config::get('secret_key');
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        
        $randomBytes = random_bytes(256) . uniqid(rand(), true);
        $hash = substr(md5($randomBytes), 0, 8);
        $string = json_encode([$user_id, $hash, time()]);
    
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($string, $cipher, $secret_key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $secret_key, true);
        $result = $iv . $hmac . $ciphertext_raw;
        $result = str_replace('=', '', strtr(base64_encode($result), '+/', '-_'));

        $update_result = Get::db()->update('#__users', ['activation_key' => $result], ['id' => $user_id]);
        if ($update_result === false) {
            $this->last_error = 'Failed to update activation key: ' . Get::db()->last_error;
        }
        
        return $result;
    }

    /**
     * Check if activation key has expired based on time constraints
     * 
     * @param string $activation_key The activation key to check
     * @param int $time_min Time in minutes for comparison
     * @param string $op Comparison operator: '<' or '>'
     * @return bool True if key meets time criteria, false otherwise
     */
    public function checkExpiresActivationKey($activation_key, $time_min, $op = '<') {
        $this->last_error = '';
        $decrypted = $this->decryptActivationKey($activation_key);
        
        if (is_array($decrypted) && count($decrypted) >= 3) {
            // Key creation time
            $created_time = new \DateTime();
            $created_time->setTimestamp($decrypted[2]);
            
            // Current time
            $now = new \DateTime();
            
            // Calculate total minutes elapsed since creation
            $diff_minutes = ($now->getTimestamp() - $created_time->getTimestamp()) / 60;
            
            // Check based on operator
            if ($op == '<' && $diff_minutes < $time_min) {
                return true;  // Key was created less than $time_min minutes ago
            } else if ($op == '>' && $diff_minutes > $time_min) {
                return true;  // Key was created more than $time_min minutes ago
            }
        } else {
            $this->last_error = 'Invalid activation key';
        }
        
        return false;
    }

    /**
     * Generate password hash
     * 
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Save user data to database
     *
     * @param int $id User ID (0 for new user)
     * @param string $username Username
     * @param string $email Email address
     * @param string $password Password (empty to keep existing)
     * @param int $status User status (1 = active, 0 = inactive)
     * @param int $is_admin Admin flag (1 = admin, 0 = regular user)
     * @param array $permissions User permissions array
     * @param string $timezone User timezone (default: UTC)
     * @param string $locale User locale (default: en_US)
     * @return int|bool User ID on success, false on failure
     */
    public function saveUser($id, $username, $email, $password = '', $status = 1, $is_admin = 0, $permissions = [], $timezone = 'UTC', $locale = '') {
        $this->last_error = '';
        $this->last_insert_id = 0;
        $save_permissions = [];
        $password = trim($password);

        $permissions_groups = Permissions::getGroups();
        foreach ($permissions_groups as $group => $_) {
            $list_of_permissions = Permissions::get($group);
            foreach ($list_of_permissions as $permission_name => $_) {
                if (!isset($permissions[$group][$permission_name])) {
                    $save_permissions[$group][$permission_name] = 0;
                } else {
                    $save_permissions[$group][$permission_name] = ($permissions[$group][$permission_name] == 1) ? 1 : 0;
                }
            }
        }
        // add any $permissions not present in $permissions_groups
        foreach ($permissions as $group => $permissions_group) {
            if (!isset($save_permissions[$group])) {
                $save_permissions[$group] = [];
            }
            foreach ($permissions_group as $permission_name => $permission_value) {
                $save_permissions[$group][$permission_name] = (int)$permission_value;
            }
        }
        if ($locale == '') {
            $locale = Config::get('locale', 'en_US');
        }

        $data = [
            'username' => $username,
            'email' => $email,
            'status' => $status,
            'is_admin' => _absint($is_admin),
            'permissions' => json_encode($save_permissions),
            'registered' => date('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'locale' => $locale
        ];

        if ($password != '') {
            $data['password'] = $this->hashPassword($password);
        }

        if ($id > 0) {
            $result = Get::db()->update('#__users', $data, ['id' => _absint($id)]);
            if ($result === false) {
                $this->last_error = 'Failed to update user: ' . Get::db()->last_error;
            }
            $this->last_insert_id = $id;
            return $result;
        } else {
            $result = Get::db()->insert('#__users', $data);

            if ($result === false) {
                $this->last_error = 'Failed to create user: ' . Get::db()->last_error;
            } else {
                $this->last_insert_id = Get::db()->insertId();
            }
            return $result;
        }
    }

    public function getLastInsertId() {
        return $this->last_insert_id;
    }
    
    /**
     * Update user session in database
     *
     * @param int $user_id User ID to associate with session (0 for guest)
     */
    private function updateUserSession($user_id = 0) {
        if (!Get::db() || !Get::db()->checkConnection()) {
            return;
        }

        $current_phpsessid = session_id();

        // If session doesn't have an ID, create new session
        if (!isset($this->session->id) || ($this->session->id ?? 0) == 0) {
            // Create new session
            $session_array = [
                'user_id' => $user_id,
                'phpsessid' => $current_phpsessid,
                'ip_address' => Get::clientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'session_date' => date('Y-m-d H:i:s'),
                'secret_key' => bin2hex(random_bytes(32))
            ];

            // Remove any duplicate sessions
            Get::db()->delete('#__sessions', ['phpsessid' => $current_phpsessid]);

            $id = Get::db()->insert('#__sessions', $session_array);
            if ($id !== false) {
                $this->session->id = $id;
                $this->session->user_id = $user_id;
                $this->session->phpsessid = $current_phpsessid;
            } else {
                $this->last_error = 'Failed to create session: ' . Get::db()->last_error;
            }
            return;
        }

        // Session exists, update it
        $udate_array = [
            'user_id' => $user_id,
            'phpsessid' => $current_phpsessid,
        ];
        $this->session->user_id = $user_id;
        if ($this->session->phpsessid != $current_phpsessid) {
            $this->session->old_phpsessid = $this->session->phpsessid;
            $udate_array['old_phpsessid'] = $this->session->phpsessid;
        }
        // Also update phpsessid if it has changed
        $this->session->phpsessid = $current_phpsessid;

        $result = Get::db()->update('#__sessions', $udate_array, ['id' => $this->session->id]);

        if ($result === false) {
            $this->last_error = 'Failed to update session: ' . Get::db()->last_error;
        }
    }

    /**
     * Decrypt activation key - used for password reset and internal data
     * Returns array with user id [0] and timestamp [2]
     * 
     * @param string $activation_key Encrypted activation key
     * @return array|null Decrypted data array or null on failure
     */
    private function decryptActivationKey($activation_key) {
    
        $secret_key = Config::get('secret_key');
        $c = \strtr($activation_key, '-_', '+/');
        $c = \base64_decode($c);
        $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len=32);
        $ciphertext_raw = substr($c, $ivlen+$sha2len);
        $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $secret_key, OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $secret_key, true);
        if (hash_equals($hmac, $calcmac)) {
            $data = json_decode($original_plaintext);
            if (is_array($data) && count($data) == 3) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Cancella tutte le sessioni scadute
     */
    private function cleanSessions() {
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . ( $this->expired_session * 2 ) . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
        $sql = 'DELETE FROM `#__sessions` WHERE session_date < "'.$formattedDateTime.'"';
        Get::db()->query($sql);
    }

    /**
     * Pulisce i vecchi tentativi di login
     */
    public function cleanLoginAttempts() {
        // Rimuovi i tentativi più vecchi del periodo di finestra
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . ($this->attempts_window * 2) . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
        
        Get::db()->query('DELETE FROM `#__login_attempts` WHERE attempt_time < ?', [$formattedDateTime]);
    }

    /**
     * Log successful login to access logs
     * 
     * @param int $user_id User ID who logged in
     * @param string $session_id PHP session ID
     * @param string $ip_address IP address of the user
     * @param string $user_agent User agent string
     * @param string $username Username of the user
     */
    private function logAccessLogin($user_id, $session_id, $ip_address, $user_agent, $username = '') {
        try {
            $accessLogModel = new AccessLogModel();
            $accessLogModel->logLogin($user_id, $session_id, $ip_address, $user_agent, $username);
        } catch (\Exception $e) {
            // Log error but don't interrupt login process
            error_log('Access log error during login: ' . $e->getMessage());
        }
    }

    /**
     * Log successful logout from access logs
     *
     * @param string $session_id PHP session ID
     */
    private function logAccessLogout($session_id) {
        try {
            $accessLogModel = new AccessLogModel();
            $accessLogModel->logLogout($session_id);
        } catch (\Exception $e) {
            // Log error but don't interrupt logout process
            error_log('Access log error during logout: ' . $e->getMessage());
        }
    }

    /**
     * Attempt to login user via remember me cookie
     *
     * @return bool True if login successful via remember me, false otherwise
     */
    private function attemptRememberMeLogin(): bool
    {
        // Check if remember me is enabled
        if (!Config::get('auth_remember_me_duration')) {
            return false;
        }

        try {
            $rememberMeService = new RememberMeService();

            // Get cookie data
            $cookieData = $rememberMeService->getCookie();
            if (!$cookieData) {
                return false;
            }

            // Validate token and get user_id
            $user_id = $rememberMeService->validateToken($cookieData['selector'], $cookieData['validator']);

            if (!$user_id) {
                // Invalid token - delete cookie
                $rememberMeService->deleteCookie();
                return false;
            }

            // Get user and create session
            $user = $this->getUser($user_id);
            if (!$user || $user->status != 1) {
                // User not found or inactive - delete cookie
                $rememberMeService->deleteCookie();
                return false;
            }

            // Login successful - set user and create session
            $this->current_user = $user;
            Token::config(Config::get('secret_key'), Config::get('token_key'));
            $this->setCurrentUserPermissions();
            $this->updateUserSession($this->current_user->id);

            // Update last login
            Get::db()->update('#__users', [
                'last_login' => date('Y-m-d H:i:s')
            ], ['id' => $this->current_user->id]);

            // Log access
            $this->logAccessLogin(
                $this->current_user->id,
                session_id(),
                Get::clientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $this->current_user->username ?? ''
            );

            return true;

        } catch (\Exception $e) {
            // Log error but don't interrupt normal flow
            error_log('Remember me error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create remember me token after successful login
     *
     * @param int $user_id User ID
     * @return bool Success
     */
    private function createRememberMeToken(int $user_id): bool
    {
        try {
            $rememberMeService = new RememberMeService();

            // Generate token
            $token = $rememberMeService->generateToken();

            // Store token in database
            $stored = $rememberMeService->storeToken($user_id, $token['selector'], $token['validator']);

            if ($stored) {
                // Set cookie
                $rememberMeService->setCookie($token['selector'], $token['validator']);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            // Log error but don't interrupt login process
            error_log('Remember me token creation error: ' . $e->getMessage());
            return false;
        }
    }

}