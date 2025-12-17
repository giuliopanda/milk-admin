<?php
namespace App;

use App\Exceptions\HttpClientException;

!defined('MILK_DIR') && die(); // Avoid direct access

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
 * - Exception-based error handling
 * - Fully static interface
 *
 * @package App
 *
 * @example
 * ```php
 * // Basic usage example
 *
 * // Simple GET request
 * try {
 *     $response = HttpClient::get('https://api.example.com/users');
 *     if ($response['status_code'] === 200) {
 *         echo "Users found: " . count($response['body']);
 *     }
 * } catch (HttpClientException $e) {
 *     echo "Error: " . $e->getMessage();
 * }
 *
 * // POST request with JSON data
 * try {
 *     $response = HttpClient::post('https://api.example.com/users', [
 *         'body' => ['name' => 'John Doe', 'email' => 'john@example.com'],
 *         'headers' => ['Authorization' => 'Bearer abc123']
 *     ]);
 * } catch (HttpClientException $e) {
 *     echo "Request failed: " . $e->getMessage();
 * }
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
    private static array $default_options = [
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
     * Performs an HTTP GET request
     *
     * @param string $url Target URL
     * @param array<string, mixed> $options Additional request options
     * @return array<string, mixed> Response array with status_code, headers, body, info
     * @throws HttpClientException If URL is invalid or If request execution fails
     *
     * @example
     * ```php
     * $response = HttpClient::get('https://api.example.com/data', [
     *     'headers' => ['Accept' => 'application/json'],
     *     'timeout' => 60
     * ]);
     * ```
     */
    public static function get(string $url, array $options = []): array
    {
        $options['method'] = 'GET';
        return self::request($url, $options);
    }

    /**
     * Performs an HTTP POST request
     *
     * @param string $url Target URL
     * @param array<string, mixed> $options Additional request options
     * @return array<string, mixed> Response array with status_code, headers, body, info
     * @throws HttpClientException If URL is invalid or If request execution fails
     *
     * @example
     * ```php
     * $response = HttpClient::post('https://api.example.com/users', [
     *     'body' => ['name' => 'John', 'email' => 'john@test.com'],
     *     'headers' => ['Content-Type' => 'application/json']
     * ]);
     * ```
     */
    public static function post(string $url, array $options = []): array
    {
        $options['method'] = 'POST';
        return self::request($url, $options);
    }

    /**
     * Performs an HTTP PUT request
     *
     * @param string $url Target URL
     * @param array<string, mixed> $options Additional request options
     * @return array<string, mixed> Response array with status_code, headers, body, info
     * @throws HttpClientException If URL is invalid or If request execution fails
     */
    public static function put(string $url, array $options = []): array
    {
        $options['method'] = 'PUT';
        return self::request($url, $options);
    }

    /**
     * Performs an HTTP DELETE request
     *
     * @param string $url Target URL
     * @param array<string, mixed> $options Additional request options
     * @return array<string, mixed> Response array with status_code, headers, body, info
     * @throws HttpClientException If URL is invalid or If request execution fails
     */
    public static function delete(string $url, array $options = []): array
    {
        $options['method'] = 'DELETE';
        return self::request($url, $options);
    }

    /**
     * Performs an HTTP PATCH request
     *
     * @param string $url Target URL
     * @param array<string, mixed> $options Additional request options
     * @return array<string, mixed> Response array with status_code, headers, body, info
     * @throws HttpClientException If URL is invalid or If request execution fails
     */
    public static function patch(string $url, array $options = []): array
    {
        $options['method'] = 'PATCH';
        return self::request($url, $options);
    }

    /**
     * Performs an HTTP HEAD request
     *
     * Useful to get only response headers without the response body
     *
     * @param string $url Target URL
     * @param array<string, mixed> $options Additional request options
     * @return array<string, mixed> Response array with status_code, headers, body, info
     * @throws HttpClientException If URL is invalid or If request execution fails
     */
    public static function head(string $url, array $options = []): array
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
     * @param string $url Target URL
     * @param array<string, mixed> $options Request options that override default ones
     * @return array<string, mixed> Associative array with the response
     * @throws HttpClientException If URL is invalid or cURL initialization fails or If request execution fails
     *
     * The returned response has the following structure:
     * - status_code: HTTP status code
     * - headers: Associative array of response headers
     * - body: Response body (automatically decoded if JSON)
     * - info: Additional information from curl_getinfo()
     */
    public static function request(string $url, array $options = []): array
    {
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new HttpClientException("Invalid URL: {$url}");
        }

        $options = array_merge(self::$default_options, $options);

        $ch = self::createCurlHandle($url, $options);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);

        if ($error) {
            throw new HttpClientException("cURL error: {$error}");
        }

        if ($response === false) {
            throw new HttpClientException("Unable to get response from {$url}");
        }

        return self::parseResponse($response, $info);
    }

    /**
     * Executes multiple HTTP requests in parallel
     *
     * Allows executing multiple HTTP requests simultaneously to improve
     * performance when data needs to be retrieved from multiple endpoints.
     *
     * @param array<string, array<string, mixed>> $requests Associative array of requests in the format:
     *                       ['key' => ['url' => 'https://...', 'options' => [...]]]
     * @return array<string, array<string, mixed>> Associative array with responses (key => response)
     * @throws HttpClientException If requests array is invalid or URL validation fails or If multi-request execution fails
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
     * - An array with error=true and message (on individual request error)
     */
    public static function executeMulti(array $requests): array
    {
        if (empty($requests)) {
            throw new HttpClientException('Empty requests array');
        }

        $multi_handle = curl_multi_init();
        if (!$multi_handle) {
            throw new HttpClientException("Unable to initialize curl_multi");
        }

        $curl_handles = [];

        // Prepare requests
        foreach ($requests as $key => $request) {
            // Request structure validation
            if (!isset($request['url'])) {
                self::cleanupMultiHandles($multi_handle, $curl_handles);
                throw new HttpClientException("Missing URL for request '{$key}'");
            }

            $url = $request['url'];
            $options = isset($request['options']) && is_array($request['options'])
                ? $request['options']
                : [];

            // URL validation
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                self::cleanupMultiHandles($multi_handle, $curl_handles);
                throw new HttpClientException("Invalid URL for '{$key}': {$url}");
            }

            $options = array_merge(self::$default_options, $options);

            try {
                $ch = self::createCurlHandle($url, $options);
            } catch (HttpClientException $e) {
                self::cleanupMultiHandles($multi_handle, $curl_handles);
                throw $e;
            }

            curl_multi_add_handle($multi_handle, $ch);
            $curl_handles[$key] = $ch;
        }

        // Execute multiple requests
        $running = null;
        do {
            $status = curl_multi_exec($multi_handle, $running);
            if ($status !== CURLM_OK) {
                self::cleanupMultiHandles($multi_handle, $curl_handles);
                throw new HttpClientException("Error in multi cURL execution: {$status}");
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
        }

        curl_multi_close($multi_handle);
        return $responses;
    }

    /**
     * Creates and configures a cURL handle with the specified options
     *
     * @param string $url Target URL
     * @param array<string, mixed> $options Array of configuration options
     * @return \CurlHandle Configured cURL handle
     * @throws HttpClientException If cURL initialization fails or JSON encoding fails
     */
    private static function createCurlHandle(string $url, array $options): \CurlHandle
    {
        $ch = curl_init();
        if (!$ch) {
            throw new HttpClientException("Unable to initialize cURL");
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
     * @param \CurlHandle $ch cURL handle
     * @param array<string, mixed> $options Options array
     * @return void
     */
    private static function configureHttpMethod(\CurlHandle $ch, array $options): void
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
     * @param \CurlHandle $ch cURL handle
     * @param array<string, mixed> $options Options array (passed by reference to modify headers)
     * @return void
     * @throws HttpClientException If JSON encoding fails
     */
    private static function configureBody(\CurlHandle $ch, array &$options): void
    {
        if ($options['body'] !== null) {
            if (is_array($options['body']) || is_object($options['body'])) {
                $body = json_encode($options['body']);
                if ($body === false) {
                    throw new HttpClientException("Unable to encode body to JSON: " . json_last_error_msg());
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
     * @param \CurlHandle $ch cURL handle
     * @param array<string, mixed> $options Options array
     * @return void
     */
    private static function configureHeaders(\CurlHandle $ch, array $options): void
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
     * @param array<string, mixed> $info Information from curl_getinfo()
     * @return array<string, mixed> Structured response array
     */
    private static function parseResponse(string $response, array $info): array
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
     * @return array<string, string|array<string>> Associative array of headers
     */
    private static function parseHeaders(string $headers_raw): array
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
     * @return mixed|null Decoded data or null if not valid JSON
     */
    private static function tryJsonDecode(string $body): mixed
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
     * @param \CurlMultiHandle $multi_handle Multi cURL handle
     * @param array<string, \CurlHandle> $curl_handles Array of cURL handles
     * @return void
     */
    private static function cleanupMultiHandles(\CurlMultiHandle $multi_handle, array $curl_handles): void
    {
        foreach ($curl_handles as $ch) {
            curl_multi_remove_handle($multi_handle, $ch);
        }

        if ($multi_handle) {
            curl_multi_close($multi_handle);
        }
    }

    /**
     * Sets a single default option
     *
     * @param string $key Option key
     * @param mixed $value Option value
     * @return void
     *
     * @example
     * ```php
     * HttpClient::setDefaultOption('timeout', 60);
     * HttpClient::setDefaultOption('user_agent', 'MyApp/1.0');
     * ```
     */
    public static function setDefaultOption(string $key, mixed $value): void
    {
        self::$default_options[$key] = $value;
    }

    /**
     * Sets multiple default options
     *
     * @param array<string, mixed> $options Associative array of options
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
    public static function setDefaultOptions(array $options): void
    {
        self::$default_options = array_merge(self::$default_options, $options);
    }

    /**
     * Returns the current default options
     *
     * @return array<string, mixed> Array of default options
     */
    public static function getDefaultOptions(): array
    {
        return self::$default_options;
    }

    /**
     * Resets all options to their original default values
     *
     * @return void
     */
    public static function resetDefaultOptions(): void
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
