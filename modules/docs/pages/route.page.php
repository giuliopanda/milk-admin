<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Route
 * @category Framework
 * @order 
 * @tags 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>


<div class="container">
    <h1>Route Class Documentation</h1>
    <p>This documentation provides a detailed overview of the Route class and its functions.</p>

    <h2>Introduction</h2>
    <p>The Route class manages page routing within the system. It allows registering and executing specific functions for pages, building URLs, and handling redirects.</p>

    <h2>Static Variables</h2>
    <ul>
        <li><strong>$functions</strong>: Array of registered functions for page routing.</li>
    </ul>

    <h2>Functions</h2>

    <h4>set($name, $function, $permission = null)</h4>
    <p>Registers a function to be called when the page starts.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Route::set('home', function() {
echo 'Welcome to the homepage!';
}, '_user.is_authenticated');
    </code></pre>

    <h4>run($name)</h4>
    <p>Executes the registered function for the page.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Route::run('home');
    </code></pre>

    <h4>url($query = '')</h4>
    <p>Returns the site URL with the specified query string.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$url = Route::url(['page' => 'home']);
echo $url;
    </code></pre>

    <h4>current_url()</h4>
    <p>Returns the current URL.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$current_url = Route::current_url();
echo $current_url;
    </code></pre>

    <h4>redirect($query = '', $data = [])</h4>
    <p>Performs a redirect to another page.</p>
    <p>$data is an associative array that can contain data to pass to the destination page.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Route::redirect(['page' => 'home']);
    </code></pre>

    <h4 class="mt-4">get_header_data()</h4>
    <p>Retrieves the data passed in the data.</p>

    <h4 class="mt-4">redirect_success($url, $message = '')</h4>
    <p>Performs a redirect with a success message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Route::redirect_success('home', 'Operation completed successfully!');</code></pre>

    <h4 class="mt-4">redirect_error($url, $message = '')</h4>
    <p>Performs a redirect with an error message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Route::redirect_error('home', 'An error occurred!');</code></pre>

    <h4>compare_query_url($query1, $query2 = []) : bool</h4>
    <p>Compares the current URL's query string with the specified one.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$selected = (Route::compare_query_url('page=home') ? 'selected' : '');
    </code></pre>

    <h4>parse_query_string($query_string)</h4>
    <p>Parses the current query string and returns an associative array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query = Route::parse_query_string('page=home&lang=it');
print_r($query);
    </code></pre>

    <h4>get_query_string()</h4>
    <p>Returns the current query string.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query_string = Route::get_query_string();
echo $query_string;
    </code></pre>

    <h4>compare_page_url($query1, $query2 = [])</h4>
    <p>Verifies that the page query between query1 and query2 are equal.</p>
    <p>To check if the sidebar menu is active, simply use this method.</p>
    <p>$query1 and $query2 can be strings or arrays. If $query2 is not specified, the current query is used.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$isActive = Route::compare_page_url(['page' => 'home']);
// or
$isActive = Route::compare_page_url('?page=home&action=foo');
    </code></pre>

    <h4>build_query($query = '')</h4>
    <p>Builds the query string for the URL. Can be an array or a string.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query_string = Route::build_query(['page' => 'home']);
echo $query_string;
    </code></pre>

    <h4>urlsafeB64Encode(string $input)</h4>
    <p>Encodes a string in Base64 in a URL-safe manner.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$encoded = Route::urlsafeB64Encode('test string');
echo $encoded;
    </code></pre>

    <h4>urlsafeB64Decode(string $input)</h4>
    <p>Decodes a URL-safe Base64 string.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$decoded = Route::urlsafeB64Decode('dGVzdCBzdHJpbmc');
echo $decoded;
    </code></pre>

    <h4>redirect_success($url, $message = '')</h4>
    <p>Performs a redirect with a success message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Route::redirect_success('home', 'Operation completed successfully!');
    </code></pre>

    <h4>redirect_error($url, $message = '')</h4>
    <p>Performs a redirect with an error message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Route::redirect_error('home', 'An error occurred!');
    </code></pre>

    <h4>get_header_data()</h4>
    <p>Retrieves data from headers.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$data = Route::get_header_data();
print_r($data);
    </code></pre>

    <h4>extract_credentials($username_key = 'username', $password_key = 'password')</h4>
    <p>Extracts authentication credentials from the HTTP request. Handles different authentication methods: Basic Auth, POST, and JSON body.</p>
    <p>Parameters:</p>
    <ul>
        <li><strong>$username_key</strong>: Custom key for the username field (default: 'username')</li>
        <li><strong>$password_key</strong>: Custom key for the password field (default: 'password')</li>
    </ul>
    <p>Returns an associative array with 'username' and 'password', or empty values if not found.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$credentials = Route::extract_credentials();
print_r($credentials);

// With custom keys
$credentials = Route::extract_credentials('user', 'pass');
print_r($credentials);
    </code></pre>

    <h4>get_bearer_token()</h4>
    <p>Retrieves the Bearer token from the Authorization header.</p>
    <p>Searches for the Bearer token in the following headers:</p>
    <ul>
        <li>Authorization</li>
        <li>HTTP_AUTHORIZATION</li>
        <li>apache_request_headers() (if available)</li>
    </ul>
    <p>Returns the Bearer token if found, otherwise false.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$token = Route::get_bearer_token();
if ($token !== false) {
    echo "Bearer token found: " . $token;
} else {
    echo "No Bearer token found";
}
    </code></pre>
</div>