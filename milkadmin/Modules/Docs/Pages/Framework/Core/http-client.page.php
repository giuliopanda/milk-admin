<?php
namespace Modules\Docs\Pages;
/**
 * @title Http Client
 * @guide framework
 * @order 70
 * @tags HTTP, client, API, requests, GET, POST, PUT, DELETE, cURL, concurrent, exceptions
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>HttpClient</h1>
    <p class="text-muted">Revision: 2025-11-11</p>
    <p>Static HTTP client based on cURL with support for all major HTTP methods, concurrent requests, automatic JSON handling, and exception-based error handling.</p>

    <h2 class="mt-4">Usage</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\HttpClient;
use App\Exceptions\HttpClientException;

// Simple GET request
try {
    $response = HttpClient::get('https://api.example.com/users');
    echo "Status: {$response['status_code']}";
} catch (HttpClientException $e) {
    // Invalid URL or configuration error
    echo "Error: " . $e->getMessage();
} </code></pre>

    <h2 class="mt-4">Exception Handling</h2>

    <p><strong>Available exceptions:</strong></p>
    <ul>
        <li><code>HttpClientException</code> - Invalid URL, empty requests array, JSON encoding errors, cURL errors, connection failures, timeout errors</li>
    </ul>

    <h2 class="mt-4">Methods</h2>

    <h4 class="text-primary mt-4">get(string $url, array $options = []) : array</h4>
    <p>Performs an HTTP GET request.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$response = HttpClient::get('https://api.example.com/users', [
    'headers' => ['Authorization' => 'Bearer token'],
    'timeout' => 60
]);</code></pre>

    <h4 class="text-primary mt-4">post(string $url, array $options = []) : array</h4>
    <p>Performs an HTTP POST request.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$response = HttpClient::post('https://api.example.com/users', [
    'body' => ['name' => 'John', 'email' => 'john@example.com']
]);</code></pre>

    <h4 class="text-primary mt-4">put(string $url, array $options = []) : array</h4>
    <p>Performs an HTTP PUT request.</p>

    <h4 class="text-primary mt-4">delete(string $url, array $options = []) : array</h4>
    <p>Performs an HTTP DELETE request.</p>

    <h4 class="text-primary mt-4">patch(string $url, array $options = []) : array</h4>
    <p>Performs an HTTP PATCH request.</p>

    <h4 class="text-primary mt-4">head(string $url, array $options = []) : array</h4>
    <p>Performs an HTTP HEAD request. Returns only headers without body.</p>

    <h4 class="text-primary mt-4">executeMulti(array $requests) : array</h4>
    <p>Executes multiple HTTP requests in parallel.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$requests = [
    'users' => ['url' => 'https://api.example.com/users'],
    'posts' => [
        'url' => 'https://api.example.com/posts',
        'options' => ['timeout' => 30]
    ]
];

try {
    $responses = HttpClient::executeMulti($requests);
    foreach ($responses as $key => $response) {
        if (isset($response['error'])) {
            echo "Error for {$key}: {$response['message']}\n";
        } else {
            echo "Success: {$response['status_code']}\n";
        }
    }
} catch (HttpClientException $e) {
    echo "Multi request failed: " . $e->getMessage();
}</code></pre>

    <h4 class="text-primary mt-4">setDefaultOption(string $key, mixed $value) : void</h4>
    <p>Sets a single default option for all requests.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">HttpClient::setDefaultOption('timeout', 60);</code></pre>

    <h4 class="text-primary mt-4">setDefaultOptions(array $options) : void</h4>
    <p>Sets multiple default options at once.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">HttpClient::setDefaultOptions([
    'timeout' => 60,
    'headers' => ['Accept' => 'application/json'],
    'user_agent' => 'MyApp/1.0'
]);</code></pre>

    <h4 class="text-primary mt-4">getDefaultOptions() : array</h4>
    <p>Returns current default options.</p>

    <h4 class="text-primary mt-4">resetDefaultOptions() : void</h4>
    <p>Resets all options to framework defaults.</p>

    <h2 class="mt-4">Response Structure</h2>
    <p>All methods return an associative array:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$response = [
    'status_code' => 200,
    'headers' => ['Content-Type' => 'application/json'],
    'body' => [...],  // Auto-decoded if JSON
    'info' => [...]   // cURL metadata
];</code></pre>

    <h2 class="mt-4">Available Options</h2>
    <ul>
        <li><code>timeout</code> - Request timeout in seconds (default: 30)</li>
        <li><code>connect_timeout</code> - Connection timeout in seconds (default: 10)</li>
        <li><code>follow_redirects</code> - Follow redirects (default: true)</li>
        <li><code>max_redirects</code> - Max redirects to follow (default: 5)</li>
        <li><code>verify_ssl</code> - Verify SSL certificates (default: true)</li>
        <li><code>user_agent</code> - User agent string (default: 'MilkHttpClient/1.0')</li>
        <li><code>headers</code> - Custom headers array (default: [])</li>
        <li><code>body</code> - Request body (arrays/objects auto-encoded to JSON)</li>
    </ul>

    <h2 class="mt-4">Examples</h2>

    <h4 class="mt-4">Complete REST API Client</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">try {
        $response = HttpClient::get('https://www.milkadmin.org/ma32r4c2aa/api.php?page=home/get', ['timeout' => 2]);
        if ($response['status_code'] == 200) {
            echo $response['body'];
        }
    } catch (\App\Exceptions\HttpClientException $e) {
        // Fallback to local welcome page if HTTP request fails
        echo "Error: " . $e->getMessage();
    }
</code></pre>
</div>
