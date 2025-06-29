<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * API Routing and Management Class
 * 
 * Simplified API system that works like the Route class but for API endpoints.
 * Uses only the "page" parameter, supports slashes in page names, and provides
 * authentication and HTTP method validation.
 * 
 * @example
 * ```php
 * // Register simple endpoints
 * API::set('users/list', function($request) {
 *     return ['users' => User::all()];
 * });
 * 
 * // With authentication and specific HTTP method
 * API::set('users/create', function($request) {
 *     $data = $request['body'];
 *     return ['user' => User::create($data)];
 * }, ['auth' => true, 'method' => 'POST']);
 * 
 * // With controller
 * API::set('users/show', 'UserController@show', ['auth' => true]);
 * ```
 *
 * @package     MilkCore
 */
class API 
{
    /**
     * Registered API endpoints
     * 
     * @var array
     */
    private static $endpoints = [];

    /**
     * Current group settings for nested registrations
     * 
     * @var array
     */
    private static $group_stack = [];

    /**
     * Current request data
     * 
     * @var array|null
     */
    private static $current_request = null;

    /**
     * JWT authentication status for the current request
     * 
     * @var array|null
     */
    private static $auth_status = null;

    /**
     * Register an API endpoint
     * 
     * @param string $page The endpoint page (e.g., 'users/list', 'auth/login')
     * @param callable|string $handler The handler function or 'Controller@method' string
     * @param array $options Additional options (auth, method, permissions, etc.)
     * @return void
     */
    public static function set($page, $handler, $options = []) {
        // Apply group options
        $final_options = $options;
        $final_page = $page;
        
        foreach (self::$group_stack as $group) {
            // Apply prefix to page
            if (isset($group['prefix'])) {
                $prefix = trim($group['prefix'], '/');
                $final_page = $prefix . '/' . ltrim($final_page, '/');
            }
            
            // Merge options (specific options override group options)
            $final_options = array_merge($group, $final_options);
        }
        
        // Store the endpoint
        self::$endpoints[$final_page] = [
            'page' => $final_page,
            'handler' => $handler,
            'options' => $final_options
        ];
    }

    /**
     * Group endpoints with common options
     * 
     * @param array $options Group options (prefix, auth, permissions, etc.)
     * @param callable $callback Function containing endpoint registrations
     * @return void
     */
    public static function group($options, $callback) {
        self::$group_stack[] = $options;
        call_user_func($callback);
        array_pop(self::$group_stack);
    }

    /**
     * Execute an API endpoint (like Route::run)
     * 
     * @param string $page The requested page
     * @return bool True if endpoint was found and executed, false otherwise
     */
    public static function run($page) {
        // Find matching endpoint
        $endpoint = self::find_endpoint($page);
        
        if (!$endpoint) {
            // Try OPTIONS for CORS preflight
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                self::handle_cors();
                return true;
            }
            return false;
        }
        
        $array_info = ['continue'=>true];
        // Run before hooks
        $array_info = Hooks::run('api_before_run', $array_info, $endpoint);
        if (!$array_info['continue']) {
            return true;
        }
        
        try {
            // Check HTTP method if specified
            if (isset($endpoint['options']['method'])) {
                $required_method = strtoupper($endpoint['options']['method']);
                $current_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                
                if ($required_method !== $current_method) {
                    self::error_response("Method $current_method not allowed. Expected: $required_method", 405);
                    return true;
                }
            }
            
            // Check authentication if required
            if (self::requires_auth($endpoint)) {
                $auth_result = self::check_auth();
                if (!$auth_result['success']) {
                    self::error_response($auth_result['message'], 401);
                    return true;
                }
            }
            
            // Check permissions if specified
            if (isset($endpoint['options']['permissions'])) {
                if (!self::check_permissions($endpoint['options']['permissions'])) {
                    self::error_response('Insufficient permissions', 403);
                    return true;
                }
            }
            
            // Prepare request data
            $request = self::prepare_request($endpoint);
            self::$current_request = $request;
            
            // Execute handler (like Route::run)
            $response = self::execute_handler($endpoint['handler'], $request);
            
            $array_info['response'] = $response;
            Hooks::run('api_after_run', $array_info, $endpoint);
            // Send response
            self::json_response($response);
            
        } catch (\Exception $e) {
            Logs::set('api', 'ERROR', 'API Error: ' . $e->getMessage());
            $array_info['error'] = $e->getMessage();
            Hooks::run('api_after_run', $array_info, $endpoint);
            self::error_response('Internal server error', 500);
        }
    }

    /**
     * Find matching endpoint
     * 
     * @param string $page Request page
     * @return array|null Matching endpoint or null
     */
    private static function find_endpoint($page) {
        // Direct match
        if (isset(self::$endpoints[$page])) {
            return self::$endpoints[$page];
        }
        
        return null;
    }

    /**
     * Check if endpoint requires authentication
     * 
     * @param array $endpoint Endpoint configuration
     * @return bool
     */
    private static function requires_auth($endpoint) {
        return isset($endpoint['options']['auth']) && $endpoint['options']['auth'] === true;
    }

    /**
     * Check JWT authentication
     * 
     * @return array Authentication result with 'success' and 'message' keys
     */
    private static function check_auth() {
        if (self::$auth_status !== null) {
            return self::$auth_status;
        }
        
        $token = Route::get_bearer_token();
        
        if (!$token) {
            self::$auth_status = [
                'success' => false,
                'message' => 'No authentication token provided'
            ];
            return self::$auth_status;
        }
        
        $payload = Token::verify_jwt($token);
        
        if (!$payload) {
            self::$auth_status = [
                'success' => false,
                'message' => 'Invalid or expired token: ' . Token::$last_error
            ];
            return self::$auth_status;
        }
        
        // Set authenticated user
        if (isset($payload['user_id']) && Get::has('auth')) {
            $auth = Get::make('auth');
            $user = $auth->get_user($payload['user_id']);
            if ($user && $user->status == 1) {
                // Set user permissions based on JWT payload
                if (isset($payload['is_admin']) && $payload['is_admin'] == 1) {
                    // Set admin permissions
                    Permissions::set_user_permissions('_user', [
                        'is_admin' => true,
                        'is_guest' => false
                    ]);
                    
                    // Set permissions for user management
                    Permissions::set_user_permissions('users', [
                        'list' => true,
                        'create' => true,
                        'update' => true,
                        'delete' => true
                    ]);
                } else {
                    // Set regular user permissions
                    Permissions::set_user_permissions('_user', [
                        'is_admin' => false,
                        'is_guest' => false
                    ]);
                }
                
                // Set additional permissions from the payload if available
                if (isset($payload['role'])) {
                    // Here you can set role-specific permissions
                    // based on the role value in the JWT payload
                }
                
                self::$auth_status = [
                    'success' => true,
                    'message' => 'Authenticated',
                    'user' => $user,
                    'payload' => $payload
                ];
            } else {
                self::$auth_status = [
                    'success' => false,
                    'message' => 'User not found or inactive'
                ];
            }
        } else {
            self::$auth_status = [
                'success' => true,
                'message' => 'Authenticated',
                'payload' => $payload
            ];
        }
        
        return self::$auth_status;
    }

    /**
     * Check if user has required permissions
     * 
     * @param array|string $permissions Required permissions
     * @return bool
     */
    private static function check_permissions($permissions) {
        if (!Permissions::check($permissions)) {
            die (json_encode([
                'error' => true,
                'message' => 'Insufficient permissions',
                'permissions' => $permissions,
                'user_permissions' => Permissions::get_user_permissions()
            ]));
        }
        return Permissions::check($permissions);
    }

    /**
     * Prepare request data for handler
     * 
     * @param array $endpoint Endpoint configuration
     * @return array Request data
     */
    private static function prepare_request($endpoint) {
        $request = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'page' => $endpoint['page'],
            'params' => [], // For URL parameters like id
            'query' => $_GET,
            'headers' => self::get_request_headers(),
            'body' => self::get_request_body(),
            'files' => $_FILES,
            'auth' => self::$auth_status
        ];
        
        // Extract common parameters from URL
        $common_params = ['id', 'slug', 'category', 'type', 'status'];
        foreach ($common_params as $param) {
            if (isset($_REQUEST[$param])) {
                $request['params'][$param] = $_REQUEST[$param];
            }
        }
        
        // Add helper methods
        $request['input'] = function($key, $default = null) use ($request) {
            // Check in body first, then query, then params
            if (isset($request['body'][$key])) {
                return $request['body'][$key];
            }
            if (isset($request['query'][$key])) {
                return $request['query'][$key];
            }
            if (isset($request['params'][$key])) {
                return $request['params'][$key];
            }
            return $default;
        };
        
        $request['has'] = function($key) use ($request) {
            return isset($request['body'][$key]) || 
                   isset($request['query'][$key]) || 
                   isset($request['params'][$key]);
        };
        
        $request['all'] = function() use ($request) {
            return array_merge($request['query'], $request['body'], $request['params']);
        };
        
        return $request;
    }

    /**
     * Get request headers
     * 
     * @return array Request headers
     */
    private static function get_request_headers() {
        $headers = [];
        
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        // Fallback
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $header = implode('-', array_map('ucfirst', explode('-', strtolower($header))));
                $headers[$header] = $value;
            }
        }
        
        // Special headers
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        return $headers;
    }

    /**
     * Get request body
     * 
     * @return array Request body data
     */
    private static function get_request_body() {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Handle JSON content for any HTTP method
        if (strpos($content_type, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            return is_array($data) ? $data : [];
        }
        
        // Handle form data for POST requests
        if ($method === 'POST') {
            return $_POST;
        }
        
        // Handle other methods with form data
        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            // For non-JSON content, try to parse it
            if (!empty($input = file_get_contents('php://input'))) {
                if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
                    parse_str($input, $data);
                    return $data;
                }
                
                // As a fallback, try to parse as JSON even if content-type is not explicitly set
                $data = json_decode($input, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }
        
        return [];
    }

    /**
     * Execute the endpoint handler
     * 
     * @param callable|string $handler The handler to execute
     * @param array $request Request data
     * @return mixed Handler response
     */
    private static function execute_handler($handler, $request) {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            // Controller@method format
            list($controller, $method) = explode('@', $handler, 2);
            
            // Add namespace if needed
            if (strpos($controller, '\\') === false) {
                $controller = 'Modules\\' . $controller;
            }
            
            if (!class_exists($controller)) {
                throw new \Exception("Controller class '$controller' not found");
            }
            
            $instance = new $controller();
            
            if (!method_exists($instance, $method)) {
                throw new \Exception("Method '$method' not found in controller '$controller'");
            }
            
            return call_user_func([$instance, $method], $request);
        }
        
        // Direct callable
        return call_user_func($handler, $request);
    }

    /**
     * Send JSON response
     * 
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return void
     */
    public static function json_response($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        
        // Handle CORS if configured
        self::handle_cors();
        
        echo json_encode($data);
        
        // Clean up
        if (Get::db() !== null) {
            Get::db()->close();
        }
        if (Get::db2() !== null) {
            Get::db2()->close();
        }
        Settings::save();
        exit;
    }

    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $status HTTP status code
     * @return void
     */
    public static function error_response($message, $status = 400) {
        self::json_response([
            'error' => true,
            'message' => $message
        ], $status);
    }

    /**
     * Handle CORS headers
     * 
     * @return void
     */
    private static function handle_cors() {
        $allowed_origins = Config::get('api_cors_origins', '*');
        $allowed_methods = Config::get('api_cors_methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $allowed_headers = Config::get('api_cors_headers', 'Content-Type, Authorization');
        
        if ($allowed_origins === '*') {
            header('Access-Control-Allow-Origin: *');
        } elseif (is_array($allowed_origins)) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if (in_array($origin, $allowed_origins)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Credentials: true');
            }
        }
        
        header('Access-Control-Allow-Methods: ' . $allowed_methods);
        header('Access-Control-Allow-Headers: ' . $allowed_headers);
        header('Access-Control-Max-Age: 86400');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Get the current authenticated user
     * 
     * @return object|null The authenticated user or null
     */
    public static function user() {
        if (self::$auth_status && self::$auth_status['success']) {
            return self::$auth_status['user'] ?? null;
        }
        return null;
    }

    /**
     * Get the current JWT payload
     * 
     * @return array|null The JWT payload or null
     */
    public static function payload() {
        if (self::$auth_status && self::$auth_status['success']) {
            return self::$auth_status['payload'] ?? null;
        }
        return null;
    }

    /**
     * Get the current request data
     * 
     * @return array|null The current request or null
     */
    public static function request() {
        return self::$current_request;
    }

    /**
     * Generate a new JWT token for a user
     * 
     * @param int $user_id User ID
     * @param array $additional_data Additional data to include in the token
     * @return array Token response
     */
    public static function generate_token($user_id, $additional_data = []) {
        if (Get::has('auth')) {
            $auth = Get::make('auth');
            $user = $auth->get_user($user_id);
            
            if (!$user || $user->status != 1) {
                return [
                    'error' => true,
                    'message' => 'User not found or inactive'
                ];
            }
            
            $additional_data = array_merge($additional_data, [
                'username' => $user->username,
                'email' => $user->email,
                'is_admin' => $user->is_admin
            ]);
        }
        
        $token = Token::generate_jwt($user_id, $additional_data);
        
        if (!$token) {
            return [
                'error' => true,
                'message' => Token::$last_error
            ];
        }
        
        $expiration = Config::get('jwt_expiration', 3600);
        
        return [
            'token' => $token,
            'expires_at' => time() + $expiration,
            'expires_in' => $expiration
        ];
    }

    /**
     * Refresh a JWT token
     * 
     * @return array Token response
     */
    public static function refresh_token() {
        $auth = self::check_auth();
        
        if (!$auth['success']) {
            return [
                'error' => true,
                'message' => $auth['message']
            ];
        }
        
        $payload = $auth['payload'];
        $user_id = $payload['user_id'] ?? 0;
        
        if (!$user_id) {
            return [
                'error' => true,
                'message' => 'Invalid token payload'
            ];
        }
        
        $additional_data = $payload;
        unset($additional_data['iat'], $additional_data['exp'], $additional_data['jti'], 
              $additional_data['iss'], $additional_data['user_id']);
        
        return self::generate_token($user_id, $additional_data);
    }

    /**
     * List all registered endpoints (useful for debugging)
     * 
     * @return array All registered endpoints
     */
    public static function list_endpoints() {
        $list = [];
        
        foreach (self::$endpoints as $page => $endpoint) {
            $list[] = [
                'page' => $page,
                'method' => $endpoint['options']['method'] ?? 'ANY',
                'auth' => $endpoint['options']['auth'] ?? false,
                'permissions' => $endpoint['options']['permissions'] ?? null
            ];
        }
        
        return $list;
    }
}