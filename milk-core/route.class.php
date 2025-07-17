<?php 
namespace MilkCore;
/**
 * Page Routing and URL Management Class
 * 
 * This class manages page routing within the system, allowing you to register
 * and execute specific functions for pages, build URLs, and handle redirects.
 * It provides a simple routing mechanism for the framework.
 * 
 * See the documentation for the AbstractRoute class for advanced usage.
 * @see AbstractRoute
 * 
 * @example
 * ```php
 * // Register a route
 * Route::set('home', function() {
 *     echo 'Welcome to the homepage!';
 * });
 * 
 * // Register a route with permission check
 * Route::set('admin', function() {
 *     echo 'Admin panel';
 * }, 'auth.manage');
 * 
 * // Execute a route
 * Route::run('home');
 * 
 * // Generate a URL
 * $url = Route::url(['page' => 'products', 'id' => 123]);
 * // Result: https://example.com/?page=products&id=123
 * ```
 *
 * @package     MilkCore
 */

class Route 
{
    /**
     * Array of registered route functions
     * 
     * @var array
     */
    static private $functions = [];

    /**
     * Array of permissions associated with routes
     * 
     * @var array
     */
    static private $permissions = [];

    /**
     * Cached data for internal use
     * 
     * @var mixed
     */
    static private $cached_data = null;

    /**
     * Session data
     * 
     * @var array|null
     */
    static private $sessions = null;

    /**
     * Registers a function to be called when a page is requested
     * 
     * This method allows you to register a callback function that will be executed
     * when the specified route is run. It's the foundation of the routing system.
     * 
     * @example
     * ```php
     * // Register a simple route
     * Route::set('home', function() {
     *     echo 'Welcome to the homepage!';
     * });
     * 
     * // Register a route with permission check
     * Route::set('admin', function() {
     *     echo 'Admin panel';
     * }, 'auth.manage');
     * 
     * // Register a route with a controller method and permission
     * Route::set('products', [ProductController::class, 'index'], 'products.view');
     * ```
     *
     * @param string $name The route name/identifier
     * @param callable $function The function to execute when this route is run
     * @param string|null $permission The permission required to access this route (format: 'group.permission_name')
     * @return void
     */
    public static function set($name, $function, $permission = null) {
        self::$functions[$name] = $function;
        if ($permission !== null) {
            self::$permissions[$name] = $permission;
        }
    }

    /**
     * Executes the function registered for a specific route
     * 
     * This method runs the callback function associated with the specified route name.
     * It also triggers 'route_before_run' and 'route_after_run' hooks, allowing for
     * pre and post-processing of routes.
     * 
     * If a permission is associated with the route, it checks if the current user
     * has the required permission. If not, it redirects to the 'deny' route.
     * 
     * @example
     * ```php
     * // Run the 'home' route
     * if (Route::run('home')) {
     *     // Route was found and executed
     * } else {
     *     // Route not found, handle 404
     *     echo 'Page not found';
     * }
     * ```
     *
     * @param string $name The route name to execute (managed in index.php)
     * @return bool True if the route was found and executed, false otherwise
     */
    public static function run($name) {
        if (array_key_exists($name, self::$functions) && is_callable(self::$functions[$name])) {
            
            // Check permissions if a permission is set for this route
            if (isset(self::$permissions[$name])) {
                $required_permission = self::$permissions[$name];
                // Check if the user has the required permission
                if (!Permissions::check($required_permission)) {
                    // Permission denied - redirect to deny route
                    self::redirect_to_deny($name, $required_permission);
                    return true; // Return true because we handled the request (with redirect)
                }
            }
            
            $name = Hooks::run('route_before_run', $name);
            call_user_func(self::$functions[$name]);
            $name = Hooks::run('route_after_run', $name);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Handles permission denied scenarios by redirecting to the deny route
     * 
     * This method is called when a user doesn't have the required permission
     * to access a route. It redirects to the 'deny' route with error information.
     * 
     * @param string $route_name The name of the route that was denied
     * @param string $required_permission The permission that was required
     * @return void
     */
    private static function redirect_to_deny($route_name, $required_permission) {
        $error_message = "Access denied to route {$route_name}. Required permission: {$required_permission}";
        // Try to redirect to 'deny' route, if it exists
        if (array_key_exists('deny', self::$functions)) {
            self::redirect_error(['page' => 'deny'], $error_message, [
                'denied_route' => $route_name,
                'required_permission' => $required_permission
            ]);
        } else {
            // If no deny route exists, redirect to home with error
            die('Access denied');
        }
    }

    /**
     * Gets the permission required for a specific route
     * 
     * @param string $name The route name
     * @return string|null The required permission or null if no permission is set
     */
    public static function get_route_permission($name) {
        return self::$permissions[$name] ?? null;
    }

    /**
     * Checks if a route has a permission requirement
     * 
     * @param string $name The route name
     * @return bool True if the route has a permission requirement, false otherwise
     */
    public static function has_permission_requirement($name) {
        return isset(self::$permissions[$name]);
    }

    /**
     * Gets all routes with their permission requirements
     * 
     * @return array Array of routes with their permissions
     */
    public static function get_routes_with_permissions() {
        $routes = [];
        foreach (self::$functions as $route_name => $function) {
            $routes[$route_name] = [
                'function' => $function,
                'permission' => self::$permissions[$route_name] ?? null
            ];
        }
        return $routes;
    }

    /**
     * Returns the site URL with optional query parameters
     * 
     * This method generates a complete URL for the site, optionally including
     * query parameters. It can accept parameters as an array or as a query string.
     * 
     * @example
     * ```php
     * // Basic URL
     * $baseUrl = Route::url();
     * // Result: https://example.com/
     * 
     * // URL with query parameters as array
     * $url = Route::url(['page' => 'products', 'category' => 'electronics']);
     * // Result: https://example.com/?page=products&category=electronics
     * 
     * // URL with query string
     * $url = Route::url('?page=contact');
     * // Result: https://example.com/?page=contact
     * ```
     *
     * @param mixed $query Query parameters as array or string (default: '')
     * @return string The complete URL
     */
    public static function url($query = ''): string {
        
        $query_string = self::build_query($query);
        if ($query_string != '') { 
            $query_string = Hooks::run('route_url', $query_string);
        }
        $link_complete = '';
        if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_SCHEME'])) {
            $uri = explode('?', $_SERVER['REQUEST_URI']);
            $link_complete =   $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($uri[0]);
        }
        return (Config::get('base_url', $link_complete)).$query_string;
    }

    /**
     * Returns the current URL including query parameters
     * 
     * This method generates the complete URL for the current page,
     * including any query parameters that were passed in the request.
     * 
     * @example
     * ```php
     * // Get the current URL
     * $currentUrl = Route::current_url();
     * // If the current page is https://example.com/?page=products&id=123
     * // Result: https://example.com/?page=products&id=123
     * ```
     *
     * @return string The current URL with query parameters
     */
    public static function current_url() {
        $query = $_SERVER['QUERY_STRING'] ?? '';

        $query = ($query != '') ? '?'.$query : '';
        $link_complete = '';
        if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_SCHEME'])) {
            $uri = explode('?', $_SERVER['REQUEST_URI']);
            $link_complete =   $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($uri[0]);
        }
        return (Config::get('base_url', $link_complete)).$query;
    }

    /**
     * Performs a redirect with a success message
     * 
     * This method executes a redirect to the specified URL and passes
     * a success message that can be displayed on the destination page.
     * 
     * @param string|array $url URL of destination or query parameters array
     * @param string $message Success message to display
     * @param array $data Additional data to pass with the redirect
     * @return void
     */
    public static function redirect_success($url, $message = '', $data = []) {
        $data['alert-success'] = $message;
        self::redirect($url, $data);
    }

    /**
     * Performs a redirect with an error message
     * 
     * This method executes a redirect to the specified URL and passes
     * an error message that can be displayed on the destination page.
     * 
     * @param string|array $url URL of destination or query parameters array
     * @param string $message Error message to display
     * @param array $data Additional data to pass with the redirect
     * @return void
     */
    public static function redirect_error($url, $message = '', $data = []) {
        $data['alert-error'] = $message;
        self::redirect($url, $data);
    }

    /**
     * Performs a redirect with handler errors
     * 
     * This method executes a redirect and passes any errors from the MessagesHandler.
     * 
     * @param string|array $url URL of destination or query parameters array
     * @param array $data Additional data to pass with the redirect
     * @return void
     */
    public static function redirect_handler_errors($url, $data = []) {
        $data['message-handler'] = MessagesHandler::get_errors();
        self::redirect($url, $data);
    }

    /**
     * Performs a redirect with data in headers
     * 
     * This method handles redirects to other pages and can pass data
     * through session storage. If data is provided, it saves the data
     * in session and passes a session ID through cookies for retrieval
     * on the destination page.
     * 
     * @example
     * ```php
     * // Simple redirect
     * Route::redirect(['page' => 'home']);
     * 
     * // Redirect with data
     * Route::redirect(['page' => 'home'], ['user_id' => 123, 'message' => 'Hello']);
     * ```
     * 
     * @param string|array $url Destination URL or query parameters array
     * @param array $data Data to pass in the header/session
     * @return void
     */
    public static function redirect($url, $data = []) {
        if (headers_sent()) {
            Logs::set('route', 'ERROR', 'Cannot redirect, headers already sent');
            return;
        }
        if (is_array($url)) {
            $url = "?".http_build_query($url);
        }
      
        // If there's data to pass, save it in session
        // and generate an ID to pass in headers for retrieval
        if (!empty($data)) {
            $idSession = uniqid('data-', true);
            $_SESSION[$idSession] = $data;
            $encoded_value =  base64_encode($idSession);
            setcookie('X-Redirect-IDSession', $encoded_value, 0, '/');
        }

        Get::db()->close();
        Get::db2()->close();
        Settings::save();
        header('Location: ' . $url);
        exit();
    }

    /**
     * Retrieves session data passed through redirects
     * 
     * This method retrieves data that was passed during a redirect using the
     * session mechanism. It automatically cleans up the session data after retrieval.
     * 
     * @return array The session data or empty array if no data found
     */
    public static function get_session_data() {
        if (self::$sessions !== null) {
            return self::$sessions;
        }
        $data = Route::get_header_data();
        $idSession = '';
        if (!isset($data['IDSession'])) {
            return [];
        }
        $idSession = $data['IDSession'];
        if (isset($_SESSION[$idSession])) {
            $data = $_SESSION[$idSession];
            self::$sessions = $data;
            unset($_SESSION[$idSession]);
            setcookie('X-Redirect-IDSession', '', time() - 3600, '/');
            return $data;
        }
        return [];
    }

    /**
     * Retrieves data from headers
     * 
     * This method is used to define different groups of sessions.
     * The idea is that every time a redirect is made, a session identifier
     * is passed on which the data to be passed has been saved.
     * 
     * It looks for cookies with the prefix 'X-Redirect-' and decodes their values,
     * then cleans up the cookies after reading them.
     * 
     * @return array Retrieved data from headers/cookies
     */
    public static function get_header_data() {
       
        // If we have already read and stored the data, return it
        if (self::$cached_data !== null) {
            return self::$cached_data;
        }
        
        $data = [];
        
        // Check all cookies
        foreach ($_COOKIE as $key => $value) {
            // Look only for cookies that start with X-Redirect-
            if (strpos($key, 'X-Redirect-') === 0) {
                // Remove the X-Redirect- prefix
                $cleanKey = substr($key, 11);
                
                // Decode the value from base64
                $decoded_value = base64_decode($value);
                
                // Try to decode JSON if possible
                $decodedJson = json_decode($decoded_value, true);
                $data[$cleanKey] = ($decodedJson !== null) ? $decodedJson : $decoded_value;
                
                // Remove the cookie after reading it
                setcookie($key, '', time() - 3600, '/');
            }
        }
        
        // Store the data in the static variable
        self::$cached_data = $data;
        
        return $data;
    }

    /**
     * Builds the query string for the URL
     * 
     * This method can accept an array of parameters or a string and converts
     * it to a properly formatted query string starting with '?'.
     * 
     * @param mixed $query Query parameters as ['page' => 'home'] or '?page=home'
     * @return string Query string starting with '?' or empty string
     */
    static private function build_query($query = ''): string {
        $query_string = '';
        if (is_string($query)) {
            $query = trim($query);
            if ($query != '') {
                if (substr($query, 0, 1) == '?') {
                    $query = substr($query, 1);
                }
                $query_string = "?".$query;
            }
        } else {
            $query_string =  (!empty($query)) ? "?".http_build_query($query) : '';
        }
        return $query_string;
    }

    /**
     * Compares if parameters of query1 are included in query2
     * 
     * This method verifies that all parameters from query1 exist in query2
     * with the same values. Useful for checking if a URL matches certain criteria.
     * 
     * @example
     * ```php
     * $selected = (Route::compare_query_url('page=home') ? 'selected' : '');
     * ```
     * 
     * @param string|array $query1 First query to compare
     * @param string|array $query2 Second query to compare (default: current query)
     * @return bool True if query1 parameters are all present in query2
     */
    public static function compare_query_url($query1, $query2 = []) {
        if (is_string($query1)) {
            $query1 = self::parse_query_string($query1);
        }

        if (is_string($query2)) {
            $query2 = self::parse_query_string($query2);
        }
        if (count($query2) == 0) {
            // Take parameters from current query
            $query2 = self::parse_query_string(self::get_query_string()); 
        }
        foreach ($query1 as $key => $value) {
            if (!isset($query2[$key]) || $query2[$key] != $value) {
                return false;
            }
        }
        return true;
    }

    /**
     * Compares if the page parameter between query1 and query2 are equal
     * 
     * To check if the sidebar menu is active, it's sufficient to use this method.
     * This method only compares the 'page' parameter of the queries.
     * 
     * @example
     * ```php
     * $isActive = Route::compare_page_url(['page' => 'home']);
     * // or
     * $isActive = Route::compare_page_url('?page=home&action=foo');
     * ```
     * 
     * @param string|array $query1 First query to compare
     * @param string|array $query2 Second query to compare (default: current query)
     * @param bool $strict_check If true, all parameters of query1 must exist in query2 and be equal
     * @return bool True if both queries have the same 'page' parameter
     */
    public static function compare_page_url($query1, $query2 = [], $strict_check = false):bool {
        if (is_string($query1)) {
            $query1 = self::parse_query_string($query1);
        }
    
        if (is_string($query2)) {
            $query2 = self::parse_query_string($query2);
        }
        
        if (count($query2) == 0) {
            // Take parameters from current query
            $query2 = self::parse_query_string(self::get_query_string()); 
        }
        
        if ($strict_check) {
            // All parameters of query1 must exist in query2 and be equal
            // query2 can have additional parameters
            foreach ($query1 as $key => $value) {
                if (!isset($query2[$key]) || $query2[$key] != $value) {
                    return false;
                }
            }
            return true;
        } else {
            // query1 must have the same 'page' parameter as query2
            return $query1['page'] == $query2['page'];
        }
    }

    /**
     * Parses the query string and returns an associative array
     * 
     * This method converts a query string like "page=home&lang=it" into
     * an associative array ['page' => 'home', 'lang' => 'it'].
     * 
     * @example
     * ```php
     * $query = Route::parse_query_string('page=home&lang=it');
     * print_r($query);
     * // Output: ['page' => 'home', 'lang' => 'it']
     * ```
     * 
     * @param string $query_string The query string to parse
     * @return array Associative array of query parameters
     */
    public static function parse_query_string($query_string) {
        $query = [];
        if ($query_string != '') {
            $query_string = explode('?', $query_string);
            $query_string = array_pop($query_string);
            $query_string = str_replace('&amp;', '&', $query_string);
            $query_array = explode('&', $query_string);
            foreach ($query_array as $q) {
                $q = explode('=', $q);
                $query[$q[0]] = $q[1] ?? '';
            }
        }
        return $query;
    }
  
    /**
     * Gets the current query string
     * 
     * This method returns the current page's query string, ensuring it starts with '?'.
     * 
     * @return string The current query string starting with '?'
     */
    public static function get_query_string() {
        $query_string = $_SERVER['QUERY_STRING'] ?? '';
        if (substr($query_string, 0, 1) != '?') {
            $query_string = '?'.$query_string;
        }
        return $query_string;
    }

    /**
     * Encodes a string in URL-safe Base64 format
     * 
     * This method encodes a string in Base64 and makes it URL-safe by replacing
     * characters that have special meaning in URLs.
     * 
     * @param string $input The string to encode
     * @return string The URL-safe Base64 encoded string
     */
    public static function urlsafeB64Encode(string $input): string {
        if ($input == '') {
            return '';
        }
        return \str_replace('=', '', \strtr(\base64_encode($input), '+/', '-_'));
    }

    /**
     * Decodes a URL-safe Base64 string
     * 
     * This method decodes a URL-safe Base64 encoded string back to its original form.
     * 
     * @param string $input The URL-safe Base64 string to decode
     * @return string The decoded string
     */
    public static function urlsafeB64Decode(string $input): string {
        return \base64_decode(\str_pad(\strtr($input, '-_', '+/'), \strlen($input) % 4, '=', \STR_PAD_RIGHT));
    }

    /**
     * Retrieves a bearer token from the Authorization header
     * 
     * This method searches for a Bearer token in various authorization headers
     * and returns it if found. It checks multiple possible header locations
     * for maximum compatibility.
     * 
     * @example
     * ```php
     * $token = Route::get_bearer_token();
     * if ($token !== false) {
     *     echo "Bearer token found: " . $token;
     * } else {
     *     echo "No Bearer token found";
     * }
     * ```
     * 
     * @return string|false Bearer token or false if not found
     */
    public static function get_bearer_token() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $request_headers = apache_request_headers();
            $request_headers = array_combine(
                array_map('ucwords', array_keys($request_headers)),
                array_values($request_headers)
            );
            
            if (isset($request_headers['Authorization'])) {
                $headers = trim($request_headers['Authorization']);
            }
        }
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }

    /**
     * Extracts username and password from HTTP request
     * 
     * This method handles different authentication methods: Basic Auth, POST, and JSON body.
     * It attempts to extract credentials from various sources in order of preference:
     * 1. HTTP Basic Authentication
     * 2. Authorization header with Basic auth
     * 3. POST parameters
     * 4. JSON request body
     * 
     * @example
     * ```php
     * $credentials = Route::extract_credentials();
     * print_r($credentials);
     * 
     * // With custom keys
     * $credentials = Route::extract_credentials('user', 'pass');
     * print_r($credentials);
     * ```
     * 
     * @param string $username_key Optional custom key for username field (default: 'username')
     * @param string $password_key Optional custom key for password field (default: 'password')
     * @return array Associative array with 'username' and 'password', or empty values if not found
     */
    public static function extract_credentials($username_key = 'username', $password_key = 'password')
    {
        $credentials = [
            'username' => '',
            'password' => ''
        ];
        
        // Case 1: HTTP Basic Authentication
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $credentials['username'] = $_SERVER['PHP_AUTH_USER'];
            $credentials['password'] = $_SERVER['PHP_AUTH_PW'];
            return $credentials;
        }
        // Case 2: Authorization header (may contain Basic auth in different format)
        if (isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? 
                        $_SERVER['HTTP_AUTHORIZATION'] : 
                        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            
            // If header contains "Basic"
            if (strpos($auth_header, 'Basic') === 0) {
                $auth_value = trim(substr($auth_header, 6));
                $decoded = base64_decode($auth_value);
                
                if ($decoded && strpos($decoded, ':') !== false) {
                    list($username, $password) = explode(':', $decoded, 2);
                    $credentials['username'] = $username;
                    $credentials['password'] = $password;
                    return $credentials;
                }
            }
        }
        
        // Case 3: POST parameters - using provided keys
        if (isset($_POST[$username_key]) && isset($_POST[$password_key])) {
            $credentials['username'] = $_POST[$username_key];
            $credentials['password'] = $_POST[$password_key];
            return $credentials;
        }
        
        // Case 4: JSON in request body - using provided keys
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $json_data = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Check if the provided keys exist in JSON data
                if (isset($json_data[$username_key]) && isset($json_data[$password_key])) {
                    $credentials['username'] = is_string($json_data[$username_key]) ? 
                                            $json_data[$username_key] : 
                                            '';
                    $credentials['password'] = is_string($json_data[$password_key]) ? 
                                            $json_data[$password_key] : 
                                            '';
                    
                    // If both fields have values, return
                    if (!empty($credentials['username'])) {
                        return $credentials;
                    }
                }
            }
        }
        
        // Return empty credentials if no method worked
        return $credentials;
    }

}