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
    public const PASSWORD_MIN_LENGTH = 8;

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
    public $expired_session = 0;

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
     * @var AuthContract|null
     */
    private static $instance = null;

    /**
     * @var UserModel
     */
    private $user_model;

    /**
     * @var SessionModel
     */
    private $session_model;

    /**
     * @var LoginAttemptsModel
     */
    private $login_attempts_model;

    /**
     * @var ActivationKeyService
     */
    private $activation_key_service;

    /**
     * @var SecurityNotificationService
     */
    private $security_notification_service;

    /**
     * Get singleton instance of Auth class
     * 
     * @return AuthContract
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
        $this->user_model = new UserModel();
        $this->session_model = new SessionModel();
        $this->login_attempts_model = new LoginAttemptsModel();
        $this->activation_key_service = new ActivationKeyService($this->user_model);
        $this->security_notification_service = new SecurityNotificationService();

        // Load anti-brute force configurations
        $this->max_attempts = Config::get('auth_max_attempts', 5);
        $this->attempts_window = Config::get('auth_attempts_window', 5);
        $this->system_lockdown_multiplier = Config::get('auth_system_lockdown_multiplier', 15);


        if (!Cli::isCli() && substr(strtolower($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 14) != 'milkhttpclient' && Config::get('version') !== null) {

            // Check if user already has an active session
            $phpsessid = session_id();
            $currentDateTime = new \DateTime();
            $currentDateTime->modify('-' . ($this->expired_session - 2) . ' minutes');
            $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
            $db = Get::db();
            if ($db && $db->checkConnection()) {
                $session = $this->session_model->findActiveByPhpSessionAndIp($phpsessid, $formattedDateTime, Get::clientIp());
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
        $session_ids_to_delete = [(string) ($this->session->phpsessid ?? '')];
        if (isset($this->session->old_phpsessid)) {
            $session_ids_to_delete[] = (string) $this->session->old_phpsessid;
        }
        $this->session_model->deleteByPhpSessionIds($session_ids_to_delete);

        $id = $this->session_model->insertSession($session_array);
        if ($id !== false) {
            $this->session->id = $id;
        } else {
            $this->last_error = 'Unable to create guest session: ' . $this->session_model->last_error;
            echo "<p>" . $this->session_model->last_error . "</p>";
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

        $user = $this->user_model->getUserById((int) $id);
        if (!is_object($user)) {
            $this->last_error = $this->user_model->last_error ?: 'User not found';
            return null;
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
        return $this->login_attempts_model->countRecentAttempts(
            (string) $identifier,
            (string) $type,
            (int) $this->attempts_window
        );
    }

    /**
     * Count all failed login attempts in the system
     * 
     * @return int Total number of system-wide failed attempts
     */
    private function countSystemFailedAttempts() {
        return $this->login_attempts_model->countSystemRecentAttempts((int) $this->attempts_window);
    }

    /**
     * Send email notification to all administrators about security issues
     */
    private function notifyAdministrators(int $attempts_count = 0): void {
        if ($attempts_count <= 0) {
            $attempts_count = $this->countSystemFailedAttempts();
        }
        $this->security_notification_service->notifySystemLockdown(
            $attempts_count,
            (int) $this->attempts_window
        );
    }

    /**
     * Log a failed login attempt
     * 
     * @param string $username Username used in attempt
     * @param string $ip_address IP address of the attempt
     * @param string $session_id Session ID of the attempt
     */
    private function logFailedAttempt($username, $ip_address, $session_id) {
        $this->login_attempts_model->logFailedAttempt(
            (string) $username,
            (string) $ip_address,
            (string) $session_id
        );
    }

    /**
     * Clear failed login attempts when login is successful
     * 
     * @param string $username Username
     * @param string $ip_address IP address
     * @param string $session_id Session ID
     */
    private function clearFailedAttempts($username, $ip_address, $session_id) {
        $this->login_attempts_model->clearFailedAttempts(
            (string) $username,
            (string) $ip_address,
            (string) $session_id
        );
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
        $user = $this->user_model->verifyActiveCredentials((string) $username, (string) $password);
        if (!is_object($user)) {
            $this->last_error = $this->user_model->last_error;
            return false;
        }
        return $user;
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
        $user = $this->user_model->findByUsername((string) $username, (bool) $include_inactive);
        if (!is_object($user)) {
            $this->last_error = $this->user_model->last_error;
            return false;
        }
        return $user;
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
        $username = $username_email;
       
        if ($username != '') {
          
            $ip_address = Get::clientIp();
            $session_id = (string) session_id();
          
            // First check if there are too many total attempts in the system
            $system_attempts = $this->countSystemFailedAttempts();
            $system_threshold = $this->max_attempts * $this->system_lockdown_multiplier;
         
            if ($system_attempts >= $system_threshold) {
                $this->last_error = 'Access temporarily blocked due to too many failed login attempts.';
                MessagesHandler::addError($this->last_error);
                // Send notification to administrators if not already sent recently
                $this->notifyAdministrators($system_attempts);
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
                        $this->user_model->updateLastLogin((int) ($this->current_user->id ?? 0));
                    }

                    // Handle "Remember Me" if enabled and checkbox selected
                    if (!empty($_POST['remember_me']) && RememberMeService::isAvailable()) {
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
                $this->logAccessLogin($this->current_user->id, (string) session_id(), $ip_address, $_SERVER['HTTP_USER_AGENT'] ?? '', $this->current_user->username ?? '');

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
        $is_guest = !is_object($this->current_user) || (($this->current_user->is_guest ?? 1) == 1);

        // Log logout before clearing session
        $current_session_id = (string) session_id();
        if (!$is_guest) {
            $this->logAccessLogout($current_session_id);
        }

        // Delete remember me cookie and tokens for CURRENT DEVICE ONLY if user was authenticated
        if (!$is_guest && Config::get('auth_remember_me_duration')) {
            try {
                $rememberMeService = new RememberMeService();

                // Delete cookie
                $rememberMeService->deleteCookie();

                // Delete tokens ONLY for current device (using fingerprint)
                $user_id = $this->current_user->id ?? null;
                if ($user_id !== null) {
                    $rememberMeService->deleteTokensForCurrentDevice($user_id);
                }

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
        return true;
    }

    /**
     * Create activation key for user (used for password reset, etc.)
     * 
     * @param int $user_id User ID to create key for
     * @return string Encrypted activation key
     */
    public function createActivationKey($user_id) {
        $result = $this->activation_key_service->createActivationKey((int) $user_id);
        $this->last_error = $this->activation_key_service->getLastError();
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
        $result = $this->activation_key_service->checkExpiresActivationKey(
            (string) $activation_key,
            (int) $time_min,
            (string) $op
        );
        $this->last_error = $this->activation_key_service->getLastError();
        return $result;
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
     * @param bool $allow_weak_password Allow short passwords (used only in controlled flows like first install)
     * @return int|bool User ID on success, false on failure
     */
    public function saveUser($id, $username, $email, $password = '', $status = 1, $is_admin = 0, $permissions = [], $timezone = 'UTC', $locale = '', $allow_weak_password = false) {
        $this->last_error = '';
        $this->last_insert_id = 0;
        $result = $this->user_model->saveUserData(
            $id,
            $username,
            $email,
            $password,
            $status,
            $is_admin,
            $permissions,
            $timezone,
            $locale,
            $allow_weak_password
        );

        if ($result === false) {
            $this->last_error = $this->user_model->last_error;
            return false;
        }

        if ((int) $id > 0) {
            $this->last_insert_id = (int) $id;
            return (bool) $result;
        }

        $this->last_insert_id = (int) $result;
        return $result;
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
        if (!$this->session_model) {
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
            $this->session_model->deleteByPhpSessionIds([$current_phpsessid]);

            $id = $this->session_model->insertSession($session_array);
            if ($id !== false) {
                $this->session->id = $id;
                $this->session->user_id = $user_id;
                $this->session->phpsessid = $current_phpsessid;
            } else {
                $this->last_error = 'Failed to create session: ' . $this->session_model->last_error;
            }
            return;
        }

        // Session exists, update it
        $udate_array = [
            'user_id' => $user_id,
            'phpsessid' => $current_phpsessid,
        ];
        $session_data = (array) $this->session;
        $session_data['user_id'] = $user_id;
        $session_phpsessid = (string) ($session_data['phpsessid'] ?? '');
        if ($session_phpsessid != $current_phpsessid) {
            $session_data['old_phpsessid'] = $session_phpsessid;
            $udate_array['old_phpsessid'] = $session_phpsessid;
        }
        // Also update phpsessid if it has changed
        $session_data['phpsessid'] = $current_phpsessid;
        $this->session = (object) $session_data;

        $result = $this->session_model->updateSessionById(_absint($session_data['id'] ?? 0), $udate_array);

        if ($result === false) {
            $this->last_error = 'Failed to update session: ' . $this->session_model->last_error;
        }
    }

    /**
     * Cancella tutte le sessioni scadute
     */
    private function cleanSessions() {
        if (!$this->session_model) {
            return;
        }
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . ( $this->expired_session * 2 ) . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
        $this->session_model->cleanOlderThan($formattedDateTime);
    }

    /**
     * Pulisce i vecchi tentativi di login
     */
    public function cleanLoginAttempts() {
        if (!$this->login_attempts_model) {
            return;
        }
        // Rimuovi i tentativi più vecchi del periodo di finestra
        $this->login_attempts_model->cleanOlderThanMinutes((int) ($this->attempts_window * 2));
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
        if (!RememberMeService::isAvailable()) {
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
            $this->user_model->updateLastLogin((int) ($this->current_user->id ?? 0));

            // Log access
                $this->logAccessLogin(
                    $this->current_user->id,
                    (string) session_id(),
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
