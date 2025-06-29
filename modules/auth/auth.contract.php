<?php
namespace Modules\Auth;
use MilkCore\Get;
use MilkCore\Permissions;
use MilkCore\Config;
use MilkCore\Token;
use MilkCore\MessagesHandler;
use MilkCore\AuthContract;
use MilkCore\Cli;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Authentication and user management class
 * 
 * Handles user authentication, session management, and security features
 * including brute force protection and user permissions.
 */
class Auth implements AuthContract
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
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new Auth();
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
    

        if (!Cli::is_cli() && substr(strtolower($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 14) != 'milkhttpclient') {
            // Check if user already has an active session
            $phpsessid = session_id();
            $currentDateTime = new \DateTime();
            $currentDateTime->modify('-' . ($this->expired_session - 2) . ' minutes');
            $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
            if (Get::db() && Get::db()->check_connection()) {
                $session = Get::db()->get_row(
                    'SELECT * FROM `#__sessions` WHERE (phpsessid = ? OR old_phpsessid = ?) AND `session_date` > ? AND ip_address = ? AND user_agent = ?', 
                    [$phpsessid, $phpsessid, $formattedDateTime, Get::client_ip(), $_SERVER['HTTP_USER_AGENT'] ?? '']
                );
                if ($session) {
                    $this->session = $session;
                    $secret_key = $this->session->secret_key ?? Config::get('secret_key');
                    Token::config($secret_key, Config::get('token_key'));
                    
                    if ($session->user_id > 0) {
                        $this->current_user = $this->get_user($session->user_id);
                    } else {
                        $this->set_guest_user($session);
                    }
                
                } else {
                    $this->set_guest_user();
                    $this->save_session();  
                }
            } else {
                $this->set_guest_user();
            }
            $this->set_current_user_permissions();
        }
    }

    /**
     * Get the last error that occurred
     * 
     * @return string The last error message
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Set current user as guest
     * 
     * @param object|null $session Existing session object or null to create new
     */
    private function set_guest_user($session = null) {
        $this->last_error = '';
        $phpsessid = session_id();
        
        if (($this->session->id ?? 0) > 0) {
            $this->update_user_session(0);
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
                'ip_address' => Get::client_ip(), 
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
    private function save_session() {
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
        $secret_key = $this->session->secret_key ?? Config::get('secret_key');
        Token::config($secret_key, Config::get('token_key'));

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
    public function get_user($id = 0) {
        $this->last_error = '';
        
        if ($id == 0) {
            return $this->current_user;
        }
        
        $user = Get::db()->get_row('SELECT * FROM `#__users` WHERE id = ?', [$id]);
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
     * Set all permissions to false
     * 
     * @deprecated This method is deprecated
     */
    private function set_permissions_false() {
        $permissions = Permissions::get();
        foreach ($permissions as $group => $permission) {
            foreach ($permission as $permission_name => $_) {
                Permissions::set_user_permissions($group, [$permission_name => false]);
            }
        }

        $user_permissions = Permissions::get_user_permissions();
        foreach ($user_permissions as $group => $permission) {
            foreach ($permission as $permission_name => $_) {
                Permissions::set_user_permissions($group, [$permission_name => false]);
            }
        }
    }

    /**
     * Set permissions for current user
     */
    private function set_current_user_permissions() {
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
            Permissions::set_user_permissions($group, $user_permissions);
        }
    }

    /**
     * Check if an IP, username, or session is blocked based on recent failed attempts
     * 
     * @param string $identifier IP address, username/email, or session_id
     * @param string $type Type of identifier: 'ip', 'username', or 'session'
     * @return bool True if blocked, false otherwise
     */
    private function is_blocked($identifier, $type = 'ip') {
        $attempts = $this->count_failed_attempts($identifier, $type);
        return $attempts >= $this->max_attempts;
    }

    /**
     * Count recent failed login attempts
     * 
     * @param string $identifier IP address, username/email, or session_id
     * @param string $type Type of identifier: 'ip', 'username', or 'session'
     * @return int Number of failed attempts
     */
    private function count_failed_attempts($identifier, $type = 'ip') {
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
        
        $result = Get::db()->get_row($query, $params);
        return $result ? $result->count : 0;
    }

    /**
     * Count all failed login attempts in the system
     * 
     * @return int Total number of system-wide failed attempts
     */
    private function count_system_failed_attempts() {
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . $this->attempts_window . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
        
        $result = Get::db()->get_row(
            'SELECT COUNT(*) as count FROM `#__login_attempts` WHERE attempt_time > ?',
            [$formattedDateTime]
        );
        
        return $result ? $result->count : 0;
    }

    /**
     * Send email notification to all administrators about security issues
     */
    private function notify_administrators() {
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
        $admins = Get::db()->get_results('SELECT * FROM `#__users` WHERE is_admin = 1 AND status = 1');
        
        if (!$admins) {
            return;
        }
        
        $attempts_count = $this->count_system_failed_attempts();
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
     * @param string $username_email Username or email used in attempt
     * @param string $ip_address IP address of the attempt
     * @param string $session_id Session ID of the attempt
     */
    private function log_failed_attempt($username_email, $ip_address, $session_id) {
        $data = [
            'username_email' => $username_email,
            'ip_address' => $ip_address,
            'session_id' => $session_id,
            'attempt_time' => date('Y-m-d H:i:s')
        ];
        
        Get::db()->insert('#__login_attempts', $data);
    }

    /**
     * Clear failed login attempts when login is successful
     * 
     * @param string $username_email Username or email
     * @param string $ip_address IP address
     * @param string $session_id Session ID
     */
    private function clear_failed_attempts($username_email, $ip_address, $session_id) {
        // Remove failed attempts for this username, IP and session
        Get::db()->delete('#__login_attempts', ['username_email' => $username_email]);
        Get::db()->delete('#__login_attempts', ['ip_address' => $ip_address]);
        Get::db()->delete('#__login_attempts', ['session_id' => $session_id]);
    }

    /**
     * Verify user credentials without performing login
     * 
     * @param string $username_email Username or email address
     * @param string $password Plain text password
     * @return object|false Returns user object if credentials are correct, false otherwise
     */
    public function verify_credentials($username_email, $password) {
        $this->last_error = '';
        
        if (empty($username_email) || empty($password)) {
            $this->last_error = 'Username/email and password are required';
            return false;
        }
        
        // First try to find user by username
        $user_db = Get::db()->get_row("SELECT * FROM " . Get::db()->qn('#__users') . " WHERE username = ? AND status = 1", [$username_email]);
        
        // If not found by username, try by email
        if (!is_object($user_db)) {
            $user_db = Get::db()->get_row("SELECT * FROM " . Get::db()->qn('#__users') . " WHERE email = ? AND status = 1", [$username_email]);
        }
        
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
        return $this->get_user($user_db->id);
    }

    /**
     * Find user by username or email
     * 
     * @param string $username_email Username or email address to search for
     * @param bool $include_inactive Whether to include inactive users in search (default: false)
     * @return object|false Returns user object if found, false otherwise
     */
    public function find_user($username_email, $include_inactive = false) {
        $this->last_error = '';
        
        if (empty($username_email)) {
            $this->last_error = 'Username or email is required';
            return false;
        }
        
        $status_condition = $include_inactive ? '' : ' AND status = 1;';
        
        // First try to find user by username
        $user_db = Get::db()->get_row("SELECT * FROM " . Get::db()->qn('#__users') . " WHERE username = ?" . $status_condition, [$username_email]);
        
        // If not found by username, try by email
        if (!is_object($user_db)) {
            $user_db = Get::db()->get_row("SELECT * FROM " . Get::db()->qn('#__users') . " WHERE email = ?" . $status_condition, [$username_email]);
        }
        
        if (!is_object($user_db)) {
            $this->last_error = $include_inactive ? 'User not found' : 'User not found or inactive';
            return false;
        }
        
        // Return the complete user object with permissions
        return $this->get_user($user_db->id);
    }

    /**
     * Verify credentials and login user if correct
     * 
     * @param string $username_email Username or email
     * @param string $password Password
     * @param bool $save_sessions Whether to save session data (default: true)
     * @param bool $save_user_last_login Whether to save user last login (default: true)
     * @return bool True if login successful, false otherwise
     */
    public function login($username_email = '', $password = '', $save_sessions = true, $save_user_last_login = true) {
        $this->last_error = '';
       
        if ($username_email != '') {
          
            $ip_address = Get::client_ip();
            $session_id = session_id();
          
            // First check if there are too many total attempts in the system
            $system_attempts = $this->count_system_failed_attempts();
            $system_threshold = $this->max_attempts * $this->system_lockdown_multiplier;
         
            if ($system_attempts >= $system_threshold) {
                $this->last_error = 'Access temporarily blocked due to too many failed login attempts.';
                MessagesHandler::add_error($this->last_error);
                // Send notification to administrators if not already sent recently
                $this->notify_administrators();
                return false;
            }
          
            // Check if IP is blocked
            if ($this->is_blocked($ip_address, 'ip')) {
                $this->last_error = 'Access temporarily blocked due to too many failed login attempts.';
                MessagesHandler::add_error($this->last_error);
                return false;
            }
           
            // Check if session is blocked
            if ($this->is_blocked($session_id, 'session')) {
                $this->last_error = 'Access temporarily blocked due to too many failed login attempts.';
                MessagesHandler::add_error($this->last_error);
                return false;
            }
            
            // Check if username/email is blocked
            if ($this->is_blocked($username_email, 'username')) {
                $this->last_error = 'Access temporarily blocked due to too many failed login attempts.';
                MessagesHandler::add_error($this->last_error);
                return false;
            }
    
            // Use verify_credentials to check login
            $user = $this->verify_credentials($username_email, $password);
          
            if ($user !== false) {
                if ($save_sessions) {
                    // Regenerate session ID only if we're saving the session
                    $this->session->old_phpsessid = session_id();
                    session_regenerate_id(true);
                    $this->current_user = $user;
                    $secret_key = $this->session->secret_key ?? Config::get('secret_key');
                    Token::config($secret_key, Config::get('token_key'));
                    $this->set_current_user_permissions();
                    $this->update_user_session($this->current_user->id);
                    if ($save_user_last_login) {
                        Get::db()->update('#__users', [
                            'last_login' => date('Y-m-d H:i:s')
                        ], ['id' => $this->current_user->id]);
                    }
                } else {
                    // For login without session, just set current user
                    $this->current_user = $user;
                    $this->set_current_user_permissions();
                }
                
                // Clear failed attempts after successful login
                $this->clear_failed_attempts($username_email, $ip_address, $session_id);
                
                return true;
            } else {
                // Login failed - log the attempt
                $this->log_failed_attempt($username_email, $ip_address, $session_id);
            }
        }
        
        if ($save_sessions) {
            $this->clean_sessions();
        }
        return $this->is_authenticated();
    }

    /**
     * Check if user is currently logged in
     * 
     * @return bool True if logged in, false if guest
     */
    public function is_authenticated() {
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
        // Set session to guest
        $this->set_guest_user();
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
    public function create_activation_key($user_id) {
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
    public function check_expires_activation_key($activation_key, $time_min, $op = '<') {
        $this->last_error = '';
        $decrypted = $this->decrypt_activation_key($activation_key);
        
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
    public function hash_password($password) {
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
     * @return int|bool User ID on success, false on failure
     */
    public function save_user($id, $username, $email, $password = '', $status = 1, $is_admin = 0, $permissions = []) {
        $this->last_error = '';
        $this->last_insert_id = 0;
        $save_permissions = [];
        $password = trim($password);
        $permissions_groups = Permissions::get_groups();
        
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
        
        $data = [
            'username' => $username,
            'email' => $email,
            'status' => $status,
            'is_admin' => _absint($is_admin),
            'permissions' => json_encode($save_permissions),
            'registered' => date('Y-m-d H:i:s')
        ];
        
        if ($password != '') {
            $data['password'] = $this->hash_password($password);
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
                $this->last_insert_id = Get::db()->insert_id();
            }
            return $result;
        }
    }

    public function get_last_insert_id() {
        return $this->last_insert_id;
    }
    
    /**
     * Update user session in database
     * 
     * @param int $user_id User ID to associate with session (0 for guest)
     */
    private function update_user_session($user_id = 0) {
        if (!Get::db() || !Get::db()->check_connection()) {
            return;
        }
        $udate_array = [
            'user_id' => $user_id,
            'phpsessid' =>session_id(),
        ];
        $this->session->user_id = $user_id;
        if ($this->session->phpsessid != session_id()) {
            $this->session->old_phpsessid = $this->session->phpsessid;
            $udate_array['old_phpsessid'] = $this->session->phpsessid;
        } 
        // Also update phpsessid if it has changed
        $this->session->phpsessid = session_id();
        
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
    private function decrypt_activation_key($activation_key) {
    
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
    private function clean_sessions() {
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . ( $this->expired_session * 2 ) . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
        $sql = 'DELETE FROM `#__sessions` WHERE session_date < "'.$formattedDateTime.'"';
        Get::db()->query($sql);
    }

    /**
     * Pulisce i vecchi tentativi di login
     */
    public function clean_login_attempts() {
        // Rimuovi i tentativi più vecchi del periodo di finestra
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . ($this->attempts_window * 2) . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
        
        Get::db()->query('DELETE FROM `#__login_attempts` WHERE attempt_time < ?', [$formattedDateTime]);
    }

}