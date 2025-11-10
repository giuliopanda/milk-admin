<?php
namespace App;
/**
 * Simple and versatile static HTTP client based on cURL
 * 
 * This class provides a simplified static interface for making HTTP requests
 * using PHP's cURL extension. It supports all main HTTP methods,
 * concurrent multiple requests, automatic JSON handling, and flexible
 * configuration options.
 * 
 * Main features:
 * - Support for GET, POST, PUT, DELETE, PATCH, HEAD
 * - Asynchronous multiple requests with curl_multi
 * - Automatic JSON response parsing
 * - Configurable timeout, redirect, and SSL handling
 * - Customizable headers and default options
 * - Comprehensive error handling
 * - Fully static interface
 * 
 * @package     App
 * 
 * @example
 * ```php
 * // Basic usage example
 * 
 * // Simple GET request
 * $response = HttpClient::get('https://api.example.com/users');
 * if ($response && $response['status_code'] === 200) {
 *     echo "Users found: " . count($response['body']);
 * }
 * 
 * // POST request with JSON data
 * $response = HttpClient::post('https://api.example.com/users', [
 *     'body' => ['name' => 'John Doe', 'email' => 'john@example.com'],
 *     'headers' => ['Authorization' => 'Bearer abc123']
 * ]);
 * 
 * // Configure default options
 * HttpClient::setDefaultOptions([
 *     'timeout' => 60,
 *     'headers' => ['Accept' => 'application/json']
 * ]);
 * 
 * // Concurrent multiple requests
 * $requests = [
 *     'users' => ['url' => 'https://api.example.com/users'],
 *     'posts' => ['url' => 'https://api.example.com/posts', 'options' => ['timeout' => 30]],
 *     'comments' => ['url' => 'https://api.example.com/comments', 'options' => ['headers' => ['X-Key' => 'value']]]
 * ];
 * $responses = HttpClient::executeMulti($requests);
 * ```
 */
class HttpClient
{
    /**
     * Default options for all requests
     *
     * @var array<string, mixed>
     */
    private static $default_options = [
        'timeout' => 30,
        'connect_timeout' => 10,
        'follow_redirects' => true,
        'max_redirects' => 5,
        'verify_ssl' => true,
        'user_agent' => 'MilkHttpClient/1.0',
        'headers' => [],
        'body' => null,
        'method' => 'GET',
    ];
    
    /**
     * Last occurred error
     *
     * @var string
     */
    private static $last_error = '';
    
    /**
     * Performs an HTTP GET request
     *
     * @param string $url     Target URL
     * @param array  $options Additional request options
     * @return array|false    Response array or false on error
     * 
     * @example
     * ```php
     * $response = HttpClient::get('https://api.example.com/data', [
     *     'headers' => ['Accept' => 'application/json'],
     *     'timeout' => 60
     * ]);
     * ```
     */
    public static function get($url, $options = [])
    {
        $options['method'] = 'GET';
        return self::request($url, $options);
    }
    
    /**
     * Performs an HTTP POST request
     *
     * @param string $url     Target URL
     * @param array  $options Additional request options
     * @return array|false    Response array or false on error
     * 
     * @example
     * ```php
     * $response = HttpClient::post('https://api.example.com/users', [
     *     'body' => ['name' => 'John', 'email' => 'john@test.com'],
     *     'headers' => ['Content-Type' => 'application/json']
     * ]);
     * ```
     */
    public static function post($url, $options = [])
    {
        $options['method'] = 'POST';
        return self::request($url, $options);
    }
    
    /**
     * Performs an HTTP PUT request
     *
     * @param string $url     Target URL
     * @param array  $options Additional request options
     * @return array|false    Response array or false on error
     */
    public static function put($url, $options = [])
    {
        $options['method'] = 'PUT';
        return self::request($url, $options);
    }
    
    /**
     * Performs an HTTP DELETE request
     *
     * @param string $url     Target URL
     * @param array  $options Additional request options
     * @return array|false    Response array or false on error
     */
    public static function delete($url, $options = [])
    {
        $options['method'] = 'DELETE';
        return self::request($url, $options);
    }
    
    /**
     * Performs an HTTP PATCH request
     *
     * @param string $url     Target URL
     * @param array  $options Additional request options
     * @return array|false    Response array or false on error
     */
    public static function patch($url, $options = [])
    {
        $options['method'] = 'PATCH';
        return self::request($url, $options);
    }
    
    /**
     * Performs an HTTP HEAD request
     *
     * Useful to get only response headers without the response body
     *
     * @param string $url     Target URL
     * @param array  $options Additional request options
     * @return array|false    Response array or false on error
     */
    public static function head($url, $options = [])
    {
        $options['method'] = 'HEAD';
        return self::request($url, $options);
    }
    
    /**
     * Main method for performing HTTP requests
     *
     * This method centralizes the logic for all HTTP requests,
     * handling cURL configuration, execution, and response parsing.
     *
     * @param string $url     Target URL
     * @param array  $options Request options that override default ones
     * @return array|false    Associative array with the response or false on error
     * 
     * The returned response has the following structure:
     * - status_code: HTTP status code
     * - headers: Associative array of response headers
     * - body: Response body (automatically decoded if JSON)
     * - info: Additional information from curl_getinfo()
     */
    public static function request($url, $options = [])
    {
        self::$last_error = '';
        
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            self::$last_error = "Invalid URL: {$url}";
            return false;
        }
        
        $options = array_merge(self::$default_options, $options);
        
        $ch = self::createCurlHandle($url, $options);
        if (!$ch) {
            return false;
        }
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            self::$last_error = "cURL error: {$error}";
            return false;
        }
        
        if ($response === false) {
            self::$last_error = "Unable to get response";
            return false;
        }
        
        return self::parseResponse($response, $info);
    }
    
    /**
     * Executes multiple HTTP requests in parallel
     *
     * Allows executing multiple HTTP requests simultaneously to improve
     * performance when data needs to be retrieved from multiple endpoints.
     *
     * @param array $requests Associative array of requests in the format:
     *                       ['key' => ['url' => 'https://...', 'options' => [...]]]
     * @return array|false   Associative array with responses (key => response) or false on error
     * 
     * @example
     * ```php
     * $requests = [
     *     'users' => [
     *         'url' => 'https://api.example.com/users',
     *         'options' => ['timeout' => 30]
     *     ],
     *     'posts' => [
     *         'url' => 'https://api.example.com/posts',
     *         'options' => ['headers' => ['Authorization' => 'Bearer token']]
     *     ],
     *     'comments' => [
     *         'url' => 'https://api.example.com/comments'
     *         // options is optional
     *     ]
     * ];
     * 
     * $responses = HttpClient::executeMulti($requests);
     * foreach ($responses as $key => $response) {
     *     if (isset($response['error'])) {
     *         echo "Error for {$key}: " . $response['message'] . "\n";
     *     } else {
     *         echo "Success for {$key}: " . $response['status_code'] . "\n";
     *     }
     * }
     * ```
     * 
     * Each response in the array can be:
     * - An array with status_code, headers, body, info (on success)
     * - An array with error=true and message (on error)
     */
    public static function executeMulti($requests)
    {
        self::$last_error = '';
        
        if (empty($requests) || !is_array($requests)) {
            self::$last_error = 'Empty or invalid requests array';
            return false;
        }
        
        $multi_handle = curl_multi_init();
        if (!$multi_handle) {
            self::$last_error = "Unable to initialize curl_multi";
            return false;
        }
        
        $curl_handles = [];
        
        // Prepare requests
        foreach ($requests as $key => $request) {
            // Request structure validation
            if (!isset($request['url'])) {
                self::$last_error = "Missing URL for request '{$key}'";
                self::cleanupMultiHandles($multi_handle, $curl_handles);
                return false;
            }
            
            $url = $request['url'];
            $options = isset($request['options']) && is_array($request['options']) 
                ? $request['options'] 
                : [];
            
            // URL validation
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                self::$last_error = "Invalid URL for '{$key}': {$url}";
                self::cleanupMultiHandles($multi_handle, $curl_handles);
                return false;
            }
            
            $options = array_merge(self::$default_options, $options);
            $ch = self::createCurlHandle($url, $options);
            
            if (!$ch) {
                self::cleanupMultiHandles($multi_handle, $curl_handles);
                return false;
            }
            
            curl_multi_add_handle($multi_handle, $ch);
            $curl_handles[$key] = $ch;
        }
        
        // Execute multiple requests
        $running = null;
        do {
            $status = curl_multi_exec($multi_handle, $running);
            if ($status !== CURLM_OK) {
                self::$last_error = "Error in multi cURL execution: {$status}";
                self::cleanupMultiHandles($multi_handle, $curl_handles);
                return false;
            }
            
            // Avoid busy waiting
            if ($running > 0) {
                curl_multi_select($multi_handle, 0.1);
            }
        } while ($running > 0);
        
        // Collect responses
        $responses = [];
        foreach ($curl_handles as $key => $ch) {
            $response = curl_multi_getcontent($ch);
            $info = curl_getinfo($ch);
            $error = curl_error($ch);
            
            if ($error || $response === false) {
                $responses[$key] = [
                    'error' => true,
                    'message' => $error ?: 'Response not available',
                ];
            } else {
                $responses[$key] = self::parseResponse($response, $info);
            }
            
            curl_multi_remove_handle($multi_handle, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($multi_handle);
        return $responses;
    }
    
    /**
     * Creates and configures a cURL handle with the specified options
     *
     * @param string $url     Target URL
     * @param array  $options Array of configuration options
     * @return \CurlHandle|resource|false Configured cURL handle or false on error
     */
    private static function createCurlHandle($url, $options)
    {
        $ch = curl_init();
        if (!$ch) {
            self::$last_error = "Unable to initialize cURL";
            return false;
        }
        
        // Basic configuration
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => (int)$options['timeout'],
            CURLOPT_CONNECTTIMEOUT => (int)$options['connect_timeout'],
            CURLOPT_USERAGENT => $options['user_agent'],
        ]);
        
        // HTTP method configuration
        self::configureHttpMethod($ch, $options);
        
        // Request body configuration
        self::configureBody($ch, $options);
        
        // Headers configuration
        self::configureHeaders($ch, $options);
        
        // Redirect configuration
        if ($options['follow_redirects']) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, (int)$options['max_redirects']);
        }
        
        // SSL configuration
        if (!$options['verify_ssl']) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        
        return $ch;
    }
    
    /**
     * Configures the HTTP method for the cURL handle
     *
     * @param \CurlHandle|resource $ch      cURL handle
     * @param array                $options Options array
     * @return void
     */
    private static function configureHttpMethod($ch, $options)
    {
        switch (strtoupper($options['method'])) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                break;
            case 'HEAD':
                curl_setopt($ch, CURLOPT_NOBODY, true);
                break;
            // GET is the default, no additional configuration needed
        }
    }
    
    /**
     * Configures the request body for the cURL handle
     *
     * @param \CurlHandle|resource $ch      cURL handle
     * @param array                $options Options array (passed by reference to modify headers)
     * @return void
     */
    private static function configureBody($ch, &$options)
    {
        if ($options['body'] !== null) {
            if (is_array($options['body']) || is_object($options['body'])) {
                $body = json_encode($options['body']);
                if ($body === false) {
                    self::$last_error = "Unable to encode body to JSON";
                    return;
                }
                // Automatically set Content-Type if not already present
                if (!isset($options['headers']['Content-Type'])) {
                    $options['headers']['Content-Type'] = 'application/json';
                }
            } else {
                $body = (string)$options['body'];
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }
    
    /**
     * Configures the headers for the cURL handle
     *
     * @param \CurlHandle|resource $ch      cURL handle
     * @param array                $options Options array
     * @return void
     */
    private static function configureHeaders($ch, $options)
    {
        if (!empty($options['headers']) && is_array($options['headers'])) {
            $headers = [];
            foreach ($options['headers'] as $key => $value) {
                $headers[] = "{$key}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    }
    
    /**
     * Parses the HTTP response and structures it into an array
     *
     * @param string $response Raw response from cURL
     * @param array  $info     Information from curl_getinfo()
     * @return array           Structured response array
     */
    private static function parseResponse($response, $info)
    {
        $header_size = $info['header_size'];
        $headers_raw = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        
        // More robust header parsing
        $headers = self::parseHeaders($headers_raw);
        
        // Attempt automatic JSON decoding
        $decoded_body = self::tryJsonDecode($body);
        
        return [
            'status_code' => (int)$info['http_code'],
            'headers' => $headers,
            'body' => $decoded_body !== null ? $decoded_body : $body,
            'info' => $info,
        ];
    }
    
    /**
     * Parses raw HTTP headers
     *
     * @param string $headers_raw Raw headers from response
     * @return array              Associative array of headers
     */
    private static function parseHeaders($headers_raw)
    {
        $headers = [];
        $header_lines = explode("\r\n", trim($headers_raw));
        
        foreach ($header_lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Skip HTTP status line
            if (strpos($line, 'HTTP/') === 0) continue;
            
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Handle multiple headers with the same name
                if (isset($headers[$key])) {
                    if (!is_array($headers[$key])) {
                        $headers[$key] = [$headers[$key]];
                    }
                    $headers[$key][] = $value;
                } else {
                    $headers[$key] = $value;
                }
            }
        }
        
        return $headers;
    }
    
    /**
     * Attempts to decode a JSON string
     *
     * @param string $body String to decode
     * @return mixed|null  Decoded data or null if not valid JSON
     */
    private static function tryJsonDecode($body)
    {
        if (empty($body)) {
            return null;
        }
        
        $decoded = json_decode($body, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }
    
    /**
     * Cleans up resources for multiple requests
     *
     * @param \CurlMultiHandle|resource                    $multi_handle Multi cURL handle
     * @param array<string, \CurlHandle|resource>          $curl_handles Array of cURL handles
     * @return void
     */
    private static function cleanupMultiHandles($multi_handle, $curl_handles)
    {
        foreach ($curl_handles as $ch) {
            curl_multi_remove_handle($multi_handle, $ch);
            curl_close($ch);
        }
        
        if ($multi_handle) {
            curl_multi_close($multi_handle);
        }
    }
    
    /**
     * Returns the last occurred error
     *
     * @return string Error message or empty string if no error
     */
    public static function getLastError()
    {
        return self::$last_error;
    }
    
    /**
     * Sets a single default option
     *
     * @param string $key   Option key
     * @param mixed  $value Option value
     * @return void
     * 
     * @example
     * ```php
     * HttpClient::setDefaultOption('timeout', 60);
     * HttpClient::setDefaultOption('user_agent', 'MyApp/1.0');
     * ```
     */
    public static function setDefaultOption($key, $value)
    {
        self::$default_options[$key] = $value;
    }
    
    /**
     * Sets multiple default options
     *
     * @param array $options Associative array of options
     * @return void
     * 
     * @example
     * ```php
     * HttpClient::setDefaultOptions([
     *     'timeout' => 60,
     *     'headers' => ['Accept' => 'application/json'],
     *     'verify_ssl' => false
     * ]);
     * ```
     */
    public static function setDefaultOptions($options)
    {
        if (is_array($options)) {
            self::$default_options = array_merge(self::$default_options, $options);
        }
    }
    
    /**
     * Returns the current default options
     *
     * @return array Array of default options
     */
    public static function getDefaultOptions()
    {
        return self::$default_options;
    }
    
    /**
     * Resets all options to their original default values
     *
     * @return void
     */
    public static function resetDefaultOptions()
    {
        self::$default_options = [
            'timeout' => 30,
            'connect_timeout' => 10,
            'follow_redirects' => true,
            'max_redirects' => 5,
            'verify_ssl' => true,
            'user_agent' => 'MilkHttpClient/1.0',
            'headers' => [],
            'body' => null,
            'method' => 'GET',
        ];
    }
   
} 
