<?php
namespace App;
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
 * // With Module
 * API::set('users/show', 'UserModule@show', ['auth' => true]);
 * ```
 *
 * @package     App
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
     * API documentation storage (maps endpoint => ApiDoc data)
     *
     * @var array
     */
    private static $documentation = [];

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
     * @param callable|string $handler The handler function or 'Module@method' string
     * @param array $options Additional options (auth, method, permissions, etc.)
     * @param string|null $description Optional API description (for documentation)
     * @param array|null $parameters Optional parameters structure (for documentation)
     * @param array|null $response Optional response structure (for documentation)
     * @return void
     *
     * @example
     * ```php
     * // Simple endpoint without documentation
     * API::set('users/list', function($request) {
     *     return ['users' => User::all()];
     * });
     *
     * // With authentication
     * API::set('users/create', 'UserModule@create', ['auth' => true, 'method' => 'POST']);
     *
     * // With documentation
     * API::set('posts/create', 'PostModule@create',
     *     ['auth' => true, 'method' => 'POST'],
     *     'Crea un nuovo post',
     *     ['body' => ['title' => 'string', 'content' => 'string']],
     *     ['id' => 'int', 'title' => 'string', 'created_at' => 'datetime']
     * );
     * ```
     */
    public static function set($page, $handler, $options = [], $description = null, $parameters = null, $response = null) {
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

        // Register documentation if provided
        if ($description !== null || $parameters !== null || $response !== null) {
            $doc = [];

            if ($description !== null) {
                $doc['description'] = $description;
            }

            if ($parameters !== null) {
                $doc['parameters'] = $parameters;
                // Generate flattened parameter paths
                $doc['parameter_paths'] = self::flattenArray($parameters);
            } else {
                $doc['parameters'] = [];
                $doc['parameter_paths'] = [];
            }

            if ($response !== null) {
                $doc['response'] = $response;
                // Generate flattened response paths
                $doc['response_paths'] = self::flattenArray($response);
            } else {
                $doc['response'] = [];
                $doc['response_paths'] = [];
            }

            self::setDocumentation($final_page, $doc);
        }
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
        $endpoint = self::findEndpoint($page);
        
        if (!$endpoint) {
            // Try OPTIONS for CORS preflight
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                self::handleCors();
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
                
                if ($required_method !== $current_method &&  $required_method != 'ANY') {
                    self::errorResponse("Method $current_method not allowed. Expected: $required_method", 405);
                    return true;
                }
            }
            
            // Check authentication if required
            if (self::requiresAuth($endpoint)) {
                $auth_result = self::checkAuth();
                if (!$auth_result['success']) {
                    self::errorResponse($auth_result['message'], 401);
                    return true;
                }
            }
            
            // Check permissions if specified
            if (isset($endpoint['options']['permissions'])) {
                // special permission simple token
                if (isset($endpoint['options']['permissions']) && $endpoint['options']['permissions'] == 'token') {
                    $api_token = Config::get('api_token', '');
                    $token = '';
                   
                    if (isset($_REQUEST['token'])) {
                        $token = $_REQUEST['token'];
                    } else {
                        $raw_body = file_get_contents('php://input');
                        
                        if (!empty($raw_body)) {
                            $body_data = json_decode($raw_body, true);
                            if (json_last_error() === JSON_ERROR_NONE && isset($body_data['token'])) {
                                $token = $body_data['token'];
                            } else {
                                // Se non è JSON, prova a fare il parse come query string
                                parse_str($raw_body, $parsed_body);
                                if (isset($parsed_body['token'])) {
                                    $token = $parsed_body['token'];
                                }
                            }
                        }
                    }
                    if ($api_token == '' || $api_token != $token) {
                        self::errorResponse('Insufficient permissions!!', 403);
                        return true;
                    }
                } elseif (!self::checkPermissions($endpoint['options']['permissions'])) {
                    self::errorResponse('Insufficient permissions', 403);
                    return true;
                }
            }
            
            // Prepare request data
            $request = self::prepareRequest($endpoint);
            self::$current_request = $request;
            
            // Execute handler (like Route::run)
            $response = self::executeHandler($endpoint['handler'], $request);
            
            $array_info['response'] = $response;
            Hooks::run('api_after_run', $array_info, $endpoint);
            // Send response
            self::jsonResponse($response);
            
        } catch (\Exception $e) {
            Logs::set('api', 'ERROR', 'API Error: ' . $e->getMessage());
            $array_info['error'] = $e->getMessage();
            Hooks::run('api_after_run', $array_info, $endpoint);
            self::errorResponse('Internal server error', 500);
        }
    }

    /**
     * Find matching endpoint
     * 
     * @param string $page Request page
     * @return array|null Matching endpoint or null
     */
    private static function findEndpoint($page) {
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
    private static function requiresAuth($endpoint) {
        return isset($endpoint['options']['auth']) && $endpoint['options']['auth'] === true;
    }

    /**
     * Check JWT authentication
     * 
     * @return array Authentication result with 'success' and 'message' keys
     */
    private static function checkAuth() {
        if (self::$auth_status !== null) {
            return self::$auth_status;
        }
        
        $token = Route::getBearerToken();
        
        if (!$token) {
            self::$auth_status = [
                'success' => false,
                'message' => 'No authentication token provided'
            ];
            return self::$auth_status;
        }
        
        $payload = Token::verifyJwt($token);
        
        if (!$payload) {
            self::$auth_status = [
                'success' => false,
                'message' => 'Invalid or expired token: ' . Token::$last_error
            ];
            return self::$auth_status;
        }
        
        // Set authenticated user
        if (isset($payload['user_id']) && Get::has('Auth')) {
            $auth = Get::make('Auth');
            $user = $auth->getUser($payload['user_id']);
            $auth->setAuthUser($payload['user_id']);
            if ($user && $user->status == 1) {
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
                'success' => false,
                'message' => 'User not found or inactive',
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
    private static function checkPermissions($permissions) {
        if (!Permissions::check($permissions)) {
            die (json_encode([
                'error' => true,
                'message' => 'Insufficient permissions',
                'permissions' => $permissions,
                'user_permissions' => Permissions::getUserPermissions()
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
    private static function prepareRequest($endpoint) {
        $request = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'page' => $endpoint['page'],
            'params' => [], // For URL parameters like id
            'query' => $_GET,
            'headers' => self::getRequestHeaders(),
            'body' => self::getRequestBody(),
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
    private static function getRequestHeaders() {
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
    private static function getRequestBody() {
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
    private static function executeHandler($handler, $request) {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            // Module@method format
            list($module, $method) = explode('@', $handler, 2);
            
            // Add namespace if needed
            if (strpos($module, '\\') === false) {
                $module = 'Modules\\' . $module;
            }
            
            if (!class_exists($module)) {
                throw new \Exception("Module class '$module' not found");
            }
            
            $instance = new $module();
            
            if (!method_exists($instance, $method)) {
                throw new \Exception("Method '$method' not found in module '$module'");
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
    public static function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        
        // Handle CORS if configured
        self::handleCors();
        
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
    public static function errorResponse($message, $status = 400) {
        self::jsonResponse([
            'error' => true,
            'message' => $message
        ], $status);
    }

    /**
     * Handle CORS headers
     * 
     * @return void
     */
    private static function handleCors() {
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
    public static function generateToken($user_id, $additional_data = []) {
        if (Get::has('Auth')) {
            $auth = Get::make('Auth');
            $user = $auth->getUser($user_id);
            
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
        
        $token = Token::generateJwt($user_id, $additional_data);
        
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
    public static function refreshToken() {
        $auth = self::checkAuth();
        
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
        
        return self::generateToken($user_id, $additional_data);
    }

    /**
     * List all registered endpoints (useful for debugging)
     *
     * @return array All registered endpoints
     */
    public static function listEndpoints() {
        $list = [];

        foreach (self::$endpoints as $page => $endpoint) {
            $endpointData = [
                'page' => $page,
                'method' => $endpoint['options']['method'] ?? 'ANY',
                'auth' => $endpoint['options']['auth'] ?? false,
                'permissions' => $endpoint['options']['permissions'] ?? null
            ];

            // Add documentation if available
            if (isset(self::$documentation[$page])) {
                $endpointData['documentation'] = self::$documentation[$page];
            }

            $list[] = $endpointData;
        }

        return $list;
    }

    /**
     * Set documentation for an API endpoint
     *
     * @param string $page The endpoint page
     * @param array $doc Documentation data (description, parameters, response)
     * @return void
     */
    public static function setDocumentation(string $page, array $doc): void {
        self::$documentation[$page] = $doc;
    }

    /**
     * Get documentation for a specific endpoint
     *
     * @param string $page The endpoint page
     * @return array|null Documentation data or null if not found
     */
    public static function getDocumentation(string $page): ?array {
        return self::$documentation[$page] ?? null;
    }

    /**
     * Check if an endpoint has documentation
     *
     * @param string $page The endpoint page
     * @return bool
     */
    public static function hasDocumentation(string $page): bool {
        return isset(self::$documentation[$page]);
    }

    /**
     * Get all documentation
     *
     * @return array All documentation data
     */
    public static function getAllDocumentation(): array {
        return self::$documentation;
    }

    /**
     * Flatten nested array to dot notation paths
     *
     * @param array $array Array to flatten
     * @param string $prefix Current path prefix
     * @return array Flattened paths with their types
     */
    private static function flattenArray(array $array, string $prefix = ''): array {
        $result = [];

        foreach ($array as $key => $value) {
            $path = $prefix ? "$prefix.$key" : $key;

            if (is_array($value)) {
                // Nested structure - recurse
                $result = array_merge($result, self::flattenArray($value, $path));
            } else {
                // Leaf node - this is a type definition
                $result[$path] = $value;
            }
        }

        return $result;
    }
}