<?php
namespace Modules\docs;
/**
 * @title Http Client
 * @category Framework
 * @order 70
 * @tags HTTP, client, API, requests, GET, POST, PUT, DELETE, cURL, concurrent 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>HttpClient Class</h1>
    
    <p>The HttpClient class is a simple and versatile static HTTP client based on cURL. It provides a simplified static interface for making HTTP requests using PHP's cURL extension. It supports all major HTTP methods, concurrent multiple requests, automatic JSON handling, and flexible configuration options.</p>

    <h2 class="mt-4">Key Features</h2>
    <ul>
        <li>Support for GET, POST, PUT, DELETE, PATCH, HEAD methods</li>
        <li>Asynchronous multiple requests with curl_multi</li>
        <li>Automatic JSON response parsing</li>
        <li>Configurable timeout, redirects, and SSL settings</li>
        <li>Customizable headers and default options</li>
        <li>Complete error handling</li>
        <li>Fully static interface</li>
    </ul>

    <h2 class="mt-4">Basic HTTP Methods</h2>

    <h3 class="mt-4">get($url, $options = [])</h3>
    <p>Performs an HTTP GET request.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Simple GET request
$response = HttpClient::get('https://api.example.com/users');
if ($response && $response['status_code'] === 200) {
    echo "Found " . count($response['body']) . " users";
}

// GET with custom headers and timeout
$response = HttpClient::get('https://api.example.com/data', [
    'headers' => ['Authorization' => 'Bearer token123'],
    'timeout' => 60
]);</code></pre>

    <h3 class="mt-4">post($url, $options = [])</h3>
    <p>Performs an HTTP POST request.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// POST with JSON data
$response = HttpClient::post('https://api.example.com/users', [
    'body' => ['name' => 'John Doe', 'email' => 'john@example.com'],
    'headers' => ['Authorization' => 'Bearer token123']
]);

// POST with form data
$response = HttpClient::post('https://example.com/form', [
    'body' => 'name=John&email=john@example.com',
    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
]);</code></pre>

    <h3 class="mt-4">put($url, $options = [])</h3>
    <p>Performs an HTTP PUT request.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Update user data
$response = HttpClient::put('https://api.example.com/users/123', [
    'body' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
    'headers' => ['Authorization' => 'Bearer token123']
]);</code></pre>

    <h3 class="mt-4">delete($url, $options = [])</h3>
    <p>Performs an HTTP DELETE request.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Delete a user
$response = HttpClient::delete('https://api.example.com/users/123', [
    'headers' => ['Authorization' => 'Bearer token123']
]);</code></pre>

    <h3 class="mt-4">patch($url, $options = [])</h3>
    <p>Performs an HTTP PATCH request.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Partial update
$response = HttpClient::patch('https://api.example.com/users/123', [
    'body' => ['email' => 'newemail@example.com'],
    'headers' => ['Authorization' => 'Bearer token123']
]);</code></pre>

    <h3 class="mt-4">head($url, $options = [])</h3>
    <p>Performs an HTTP HEAD request. Useful for getting only headers without the response body.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Check if resource exists
$response = HttpClient::head('https://api.example.com/users/123');
if ($response['status_code'] === 200) {
    echo "User exists";
}</code></pre>

    <h2 class="mt-4">Response Structure</h2>
    <p>All HTTP methods return an associative array with the following structure, or false on error:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$response = [
    'status_code' => 200,                    // HTTP status code
    'headers' => [                           // Response headers
        'Content-Type' => 'application/json',
        'Server' => 'nginx/1.18.0'
    ],
    'body' => [...],                         // Response body (auto-decoded if JSON)
    'info' => [...]                          // Additional cURL info
];</code></pre>

    <h2 class="mt-4">Multiple Concurrent Requests</h2>

    <h3 class="mt-4">execute_multi($requests)</h3>
    <p>Executes multiple HTTP requests in parallel to improve performance when retrieving data from multiple endpoints.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$requests = [
    'users' => [
        'url' => 'https://api.example.com/users',
        'options' => ['timeout' => 30]
    ],
    'posts' => [
        'url' => 'https://api.example.com/posts',
        'options' => ['headers' => ['Authorization' => 'Bearer token']]
    ],
    'comments' => [
        'url' => 'https://api.example.com/comments'
        // options is optional
    ]
];

$responses = HttpClient::execute_multi($requests);
foreach ($responses as $key => $response) {
    if (isset($response['error'])) {
        echo "Error for {$key}: " . $response['message'] . "\n";
    } else {
        echo "Success for {$key}: " . $response['status_code'] . "\n";
    }
}</code></pre>

    <h2 class="mt-4">Configuration Methods</h2>

    <h3 class="mt-4">set_default_option($key, $value)</h3>
    <p>Sets a single default option that will be applied to all requests.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">HttpClient::set_default_option('timeout', 60);
HttpClient::set_default_option('user_agent', 'MyApp/1.0');</code></pre>

    <h3 class="mt-4">set_default_options($options)</h3>
    <p>Sets multiple default options at once.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">HttpClient::set_default_options([
    'timeout' => 60,
    'headers' => ['Accept' => 'application/json'],
    'verify_ssl' => false,
    'user_agent' => 'MyCustomApp/2.0'
]);</code></pre>

    <h3 class="mt-4">get_default_options()</h3>
    <p>Returns the current default options.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$current_options = HttpClient::get_default_options();
print_r($current_options);</code></pre>

    <h3 class="mt-4">reset_default_options()</h3>
    <p>Resets all options to their original default values.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">HttpClient::reset_default_options();</code></pre>

    <h2 class="mt-4">Error Handling</h2>

    <h3 class="mt-4">get_last_error()</h3>
    <p>Returns the last error message that occurred.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$response = HttpClient::get('https://invalid-url');
if (!$response) {
    echo "Error: " . HttpClient::get_last_error();
}</code></pre>

    <h3 class="mt-4">check_curl_availability()</h3>
    <p>Checks if the cURL extension is available. Throws a RuntimeException if cURL is not available.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">try {
    HttpClient::check_curl_availability();
    echo "cURL is available";
} catch (RuntimeException $e) {
    echo "cURL not available: " . $e->getMessage();
}</code></pre>

    <h2 class="mt-4">Available Options</h2>
    <p>The following options can be passed to any HTTP method or set as defaults:</p>
    <ul>
        <li><code>timeout</code>: Request timeout in seconds (default: 30)</li>
        <li><code>connect_timeout</code>: Connection timeout in seconds (default: 10)</li>
        <li><code>follow_redirects</code>: Whether to follow redirects (default: true)</li>
        <li><code>max_redirects</code>: Maximum number of redirects to follow (default: 5)</li>
        <li><code>verify_ssl</code>: Whether to verify SSL certificates (default: true)</li>
        <li><code>user_agent</code>: User agent string (default: 'MilkHttpClient/1.0')</li>
        <li><code>headers</code>: Array of custom headers (default: [])</li>
        <li><code>body</code>: Request body data (default: null)</li>
    </ul>

    <h2 class="mt-4">Practical Examples</h2>

    <h3 class="mt-4">API Authentication</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Set global authentication
HttpClient::set_default_options([
    'headers' => ['Authorization' => 'Bearer your-api-token']
]);

// Now all requests will include the authorization header
$users = HttpClient::get('https://api.example.com/users');
$posts = HttpClient::get('https://api.example.com/posts');</code></pre>

    <h3 class="mt-4">File Upload</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Upload a file
$response = HttpClient::post('https://api.example.com/upload', [
    'body' => [
        'file' => new CURLFile('/path/to/file.jpg', 'image/jpeg', 'photo.jpg'),
        'description' => 'My photo'
    ]
]);</code></pre>

    <h3 class="mt-4">REST API CRUD Operations</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Configure API base settings
HttpClient::set_default_options([
    'headers' => [
        'Authorization' => 'Bearer api-token',
        'Accept' => 'application/json'
    ],
    'timeout' => 30
]);

// Create a new user
$new_user = HttpClient::post('https://api.example.com/users', [
    'body' => ['name' => 'John Doe', 'email' => 'john@example.com']
]);

// Read user data
$user = HttpClient::get('https://api.example.com/users/123');

// Update user
$updated_user = HttpClient::put('https://api.example.com/users/123', [
    'body' => ['name' => 'John Smith']
]);

// Delete user
$result = HttpClient::delete('https://api.example.com/users/123');</code></pre>

    <h2 class="mt-4">Best Practices</h2>
    <ul>
        <li>Always check the response before using it: <code>if ($response && $response['status_code'] === 200)</code></li>
        <li>Use <code>get_last_error()</code> to handle errors gracefully</li>
        <li>Set appropriate timeouts based on expected response times</li>
        <li>Use <code>execute_multi()</code> for multiple concurrent requests to improve performance</li>
        <li>Configure default options once rather than repeating them in each request</li>
        <li>Enable SSL verification in production environments</li>
        <li>Use proper authentication headers for API access</li>
    </ul>

    <h2 class="mt-4">Important Notes</h2>
    <ul>
        <li>The class automatically handles JSON encoding/decoding</li>
        <li>Arrays and objects in the body are automatically JSON-encoded</li>
        <li>JSON responses are automatically decoded to PHP arrays</li>
        <li>All methods are static and stateless</li>
        <li>The class requires the cURL PHP extension</li>
        <li>Multiple requests using <code>execute_multi()</code> can significantly improve performance</li>
    </ul>
</div>