<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access

use App\Exceptions\ApiException;
use App\Exceptions\ApiAuthException;
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
     * Buffered response data
     *
     * @var array|null
     */
    private static $response_buffer = null;

    /**
     * Buffered HTTP status code
     *
     * @var int
     */
    private static $status_code = 200;

    /**
     * Buffered headers
     *
     * @var array
     */
    private static $headers_buffer = [];

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
    public static function set(string $page, callable|string $handler, array $options = [], ?string $description = null, ?array $parameters = null, ?array $response = null): void {
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
    public static function group(array $options, callable $callback): void {
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
    public static function run(string $page): bool {
        // Find matching endpoint
        $endpoint = self::findEndpoint($page);

        if (!$endpoint) {
            // Try OPTIONS for CORS preflight
            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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
                    throw new ApiException("Method $current_method not allowed. Expected: $required_method", 405);
                }
            }

            // Check authentication if required
            if (self::requiresAuth($endpoint)) {
                $auth_result = self::checkAuth();
                if (!$auth_result['success']) {
                    throw new ApiAuthException($auth_result['message'], 401);
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
                        throw new ApiAuthException('Invalid or missing API token.', 403);
                    }
                } else {
                    // Lancia eccezione se i permessi non sono sufficienti
                    self::checkPermissions($endpoint['options']['permissions']);
                }
            }

            // Prepare request data
            $request = self::prepareRequest($endpoint);
            self::$current_request = $request;

            // Execute handler
            $response = self::executeHandler($endpoint['handler'], $request);

            $array_info['response'] = $response;
            Hooks::run('api_after_run', $array_info, $endpoint);

            // Send success response
            self::successResponse($response);

        } catch (ApiAuthException $e) {
            // Expected authentication errors
            Logs::set('API', $e->getMessage());
            $array_info['error'] = $e->getMessage();
            Hooks::run('api_after_run', $array_info, $endpoint);
            self::errorResponse($e->getMessage(), $e->getCode() ?: 403);

        } catch (ApiException $e) {
            // Expected API errors
            Logs::set('API', $e->getMessage(), 'ERROR');
            $array_info['error'] = $e->getMessage();
            Hooks::run('api_after_run', $array_info, $endpoint);
            self::errorResponse($e->getMessage(), $e->getCode() ?: 400);

        } catch (\Throwable $e) {
            // Unexpected PHP errors
            Logs::set('API', $e->getMessage() , 'ERROR');
            $array_info['error'] = $e->getMessage();
            Hooks::run('api_after_run', $array_info, $endpoint);

            // Check debug mode
            if (Config::get('debug', false)) {
                // Detailed error for debug mode
                self::errorResponse(
                    "Internal server error: " . $e->getMessage(),
                    500,
                    [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]
                );
            } else {
                // Generic error for production
                self::errorResponse("Internal server error.", 500);
            }
        }

        return true;
    }

    /**
     * Find matching endpoint
     *
     * @param string $page Request page
     * @return array|null Matching endpoint or null
     */
    private static function findEndpoint(string $page): ?array {
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
    private static function requiresAuth(array $endpoint): bool {
        return isset($endpoint['options']['auth']) && $endpoint['options']['auth'] === true;
    }

    /**
     * Check JWT authentication
     *
     * @return array Authentication result with 'success' and 'message' keys
     */
    private static function checkAuth(): array {
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
     * @return void
     * @throws ApiAuthException
     */
    private static function checkPermissions(array|string $permissions): void {
        // Se i permessi sono disattivati, tutto ok
        if (!Settings::get("permissions_enabled")) {
            return;
        }

        // Caso: autenticazione disattivata per la route
        // (Questo check potrebbe non essere necessario qui, dipende dalla tua logica)

        // Ottieni i permessi dell'utente
        $user_permissions = Permissions::getUserPermissions();

        // No permissions found for user
        if (empty($user_permissions)) {
            throw new ApiAuthException("Missing permissions: user has no assigned authorizations.", 403);
        }

        // Get user information
        $user = self::user();

        // No user information available
        if (empty($user)) {
            throw new ApiAuthException("Unknown user: unable to verify authorizations.", 401);
        }

        // Check permissions: difference between required and possessed
        if (!$user->is_core && !Permissions::check($permissions)) {
            throw new ApiAuthException("Insufficient permissions: user does not have required authorizations.", 403);
        }
    }

    /**
     * Prepare request data for handler
     *
     * @param array $endpoint Endpoint configuration
     * @return array Request data
     */
    private static function prepareRequest(array $endpoint): array {
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
    private static function getRequestHeaders(): array {
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
    private static function getRequestBody(): array {
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
     * @throws ApiException
     */
    private static function executeHandler(callable|string $handler, array $request): mixed {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            // Module@method format
            list($module, $method) = explode('@', $handler, 2);

            // Add namespace if needed
            if (strpos($module, '\\') === false) {
                $module = 'Modules\\' . $module;
            }

            if (!class_exists($module)) {
                throw new ApiException("Modulo '$module' non trovato: impossibile eseguire l'endpoint.", 500);
            }

            $instance = new $module();

            if (!method_exists($instance, $method)) {
                throw new ApiException("Metodo '$method' mancante nel modulo '$module'.", 500);
            }

            return call_user_func([$instance, $method], $request);
        }

        // Direct callable
        return call_user_func($handler, $request);
    }

    /**
     * Buffer JSON response (low-level method)
     *
     * @param array $payload Response payload
     * @param int $status HTTP status code
     * @return void
     */
    public static function jsonResponse(array $payload, int $status = 200): void {
        // Close session to avoid locking
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Buffer the response instead of sending it
        self::$response_buffer = $payload;
        self::$status_code = $status;

        // Buffer CORS headers
        self::bufferCorsHeaders();

        // Buffer Content-Type header
        self::$headers_buffer['Content-Type'] = 'application/json; charset=utf-8';
    }

    /**
     * Send success response
     *
     * @param mixed $data Response data
     * @return void
     */
    public static function successResponse(mixed $data): void {
        // Se i dati sono già un array con struttura di risposta (success/f), passali direttamente
        if (is_array($data) && (isset($data['success']) || isset($data['error']))) {
            self::jsonResponse($data, 200);
            return;
        }

        // Altrimenti wrappa in formato standard
        self::jsonResponse([
            'success' => true,
            'data'  => $data
        ], 200);
    }

    /**
     * Send error response
     *
     * @param string $msg Error message
     * @param int $status HTTP status code
     * @param array|null $debug_info Optional debug information (only shown in debug mode)
     * @return void
     */
    public static function errorResponse(string $msg, int $status, ?array $debug_info = null): void {
        $response = [
            'success' => false,
            'message' => $msg
        ];

        // Add debug info if provided
        if ($debug_info !== null && !empty($debug_info)) {
            $response['debug'] = $debug_info;
        }

        self::jsonResponse($response, $status);
    }

    /**
     * Handle CORS headers (legacy method for OPTIONS preflight)
     *
     * @return void
     */
    private static function handleCors(): void {
        self::bufferCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::$status_code = 204;
            self::$response_buffer = [];
        }
    }

    /**
     * Buffer CORS headers
     *
     * @return void
     */
    private static function bufferCorsHeaders(): void {
        $allowed_origins = Config::get('api_cors_origins', '*');
        $allowed_methods = Config::get('api_cors_methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $allowed_headers = Config::get('api_cors_headers', 'Content-Type, Authorization');

        if ($allowed_origins === '*') {
            self::$headers_buffer['Access-Control-Allow-Origin'] = '*';
        } elseif (is_array($allowed_origins)) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if (in_array($origin, $allowed_origins)) {
                self::$headers_buffer['Access-Control-Allow-Origin'] = $origin;
                self::$headers_buffer['Access-Control-Allow-Credentials'] = 'true';
            }
        }

        self::$headers_buffer['Access-Control-Allow-Methods'] = $allowed_methods;
        self::$headers_buffer['Access-Control-Allow-Headers'] = $allowed_headers;
        self::$headers_buffer['Access-Control-Max-Age'] = '86400';
    }

    /**
     * Get the current authenticated user
     *
     * @return object|null The authenticated user or null
     */
    public static function user(): ?object {
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
    public static function payload(): ?array {
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
    public static function request(): ?array {
        return self::$current_request;
    }

    /**
     * Generate a new JWT token for a user
     *
     * @param int $user_id User ID
     * @param array $additional_data Additional data to include in the token
     * @return array Token response
     */
    public static function generateToken(int $user_id, array $additional_data = []): array {
        if (Get::has('Auth')) {
            $auth = Get::make('Auth');
            $user = $auth->getUser($user_id);
            
            if (!$user || $user->status != 1) {
                return [
                    'success' => false,
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
                'success' => false,
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
    public static function refreshToken(): array {
        $auth = self::checkAuth();
        
        if (!$auth['success']) {
            return [
                'success' => false,
                'message' => $auth['message']
            ];
        }
        
        $payload = $auth['payload'];
        $user_id = $payload['user_id'] ?? 0;
        
        if (!$user_id) {
            return [
                'success' => false,
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
    public static function listEndpoints(): array {
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

    /**
     * Get the buffered response data
     *
     * @return array|null The buffered response or null
     */
    public static function getResponseBuffer(): ?array {
        return self::$response_buffer;
    }

    /**
     * Get the buffered HTTP status code
     *
     * @return int The buffered status code
     */
    public static function getStatusCode(): int {
        return self::$status_code;
    }

    /**
     * Get the buffered headers
     *
     * @return array The buffered headers
     */
    public static function getHeadersBuffer(): array {
        return self::$headers_buffer;
    }

    /**
     * Output the buffered response with headers and JSON
     *
     * @return void
     */
    public static function outputResponse(): void {
        // Set HTTP status code
        http_response_code(self::$status_code);

        // Send all buffered headers
        foreach (self::$headers_buffer as $name => $value) {
            header("$name: $value");
        }

        // Output JSON response
        if (self::$response_buffer !== null) {
            echo json_encode(self::$response_buffer);
        }
    }

    /**
     * Check if a response has been buffered
     *
     * @return bool True if a response is buffered
     */
    public static function hasBufferedResponse(): bool {
        return self::$response_buffer !== null;
    }

    /**
     * Clear the response buffer
     *
     * @return void
     */
    public static function clearResponseBuffer(): void {
        self::$response_buffer = null;
        self::$status_code = 200;
        self::$headers_buffer = [];
    }
}