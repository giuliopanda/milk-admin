<?php
namespace Modules\Docs\Pages;
/**
 * @title Route
 * @guide framework
 * @order 
 * @tags routing, URL-management, page-routing, redirects, query-strings, route-registration, navigation, URL-building, request-handling, page-handlers, URL-parameters, route-functions
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

    <h4>currentUrl()</h4>
    <p>Returns the current URL.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$current_url = Route::currentUrl();
echo $current_url;
    </code></pre>

    <h4>redirect($query = '', $data = [])</h4>
    <p>Performs a redirect to another page.</p>
    <p>$data is an associative array that can contain data to pass to the destination page.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Route::redirect(['page' => 'home']);
    </code></pre>

    <h4 class="mt-4">getHeaderData()</h4>
    <p>Retrieves the data passed in the data.</p>

    <h4 class="mt-4">redirectSuccess($url, $message = '')</h4>
    <p>Performs a redirect with a success message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Route::redirectSuccess('home', 'Operation completed successfully!');</code></pre>

    <h4 class="mt-4">redirectError($url, $message = '')</h4>
    <p>Performs a redirect with an error message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Route::redirectError('home', 'An error occurred!');</code></pre>

    <h4>compareQueryUrl($query1, $query2 = []) : bool</h4>
    <p>Compares the current URL's query string with the specified one.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$selected = (Route::compareQueryUrl('page=home') ? 'selected' : '');
    </code></pre>

    <h4>parseQueryString($query_string)</h4>
    <p>Parses the current query string and returns an associative array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query = Route::parseQueryString('page=home&lang=it');
print_r($query);
    </code></pre>

    <h4>getQueryString()</h4>
    <p>Returns the current query string.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query_string = Route::getQueryString();
echo $query_string;
    </code></pre>

    <h4>comparePageUrl($query1, $query2 = [])</h4>
    <p>Verifies that the page query between query1 and query2 are equal.</p>
    <p>To check if the sidebar menu is active, simply use this method.</p>
    <p>$query1 and $query2 can be strings or arrays. If $query2 is not specified, the current query is used.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$isActive = Route::comparePageUrl(['page' => 'home']);
// or
$isActive = Route::comparePageUrl('?page=home&action=foo');
    </code></pre>

    <h4>buildQuery($query = '')</h4>
    <p>Builds the query string for the URL. Can be an array or a string.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query_string = Route::buildQuery(['page' => 'home']);
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

    <h4>redirectSuccess($url, $message = '')</h4>
    <p>Performs a redirect with a success message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Route::redirectSuccess('home', 'Operation completed successfully!');
    </code></pre>

    <h4>redirectError($url, $message = '')</h4>
    <p>Performs a redirect with an error message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Route::redirectError('home', 'An error occurred!');
    </code></pre>

    <h4>getHeaderData()</h4>
    <p>Retrieves data from headers.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$data = Route::getHeaderData();
print_r($data);
    </code></pre>

    <h4>extractCredentials($username_key = 'username', $password_key = 'password')</h4>
    <p>Extracts authentication credentials from the HTTP request. Handles different authentication methods: Basic Auth, POST, and JSON body.</p>
    <p>Parameters:</p>
    <ul>
        <li><strong>$username_key</strong>: Custom key for the username field (default: 'username')</li>
        <li><strong>$password_key</strong>: Custom key for the password field (default: 'password')</li>
    </ul>
    <p>Returns an associative array with 'username' and 'password', or empty values if not found.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$credentials = Route::extractCredentials();
print_r($credentials);

// With custom keys
$credentials = Route::extractCredentials('user', 'pass');
print_r($credentials);
    </code></pre>

    <h4>getBearerToken()</h4>
    <p>Retrieves the Bearer token from the Authorization header.</p>
    <p>Searches for the Bearer token in the following headers:</p>
    <ul>
        <li>Authorization</li>
        <li>HTTP_AUTHORIZATION</li>
        <li>apache_request_headers() (if available)</li>
    </ul>
    <p>Returns the Bearer token if found, otherwise false.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$token = Route::getBearerToken();
if ($token !== false) {
    echo "Bearer token found: " . $token;
} else {
    echo "No Bearer token found";
}
    </code></pre>

    <h4>replaceUrlPlaceholders($url, $values = [])</h4>
    <p>Replaces placeholders in URL query parameters with actual values. Placeholders use the format %placeholder_name%.</p>
    <p>Parameters with unmatched placeholders are removed from the query string.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$url = Route::replaceUrlPlaceholders("?page=view&id=%id%", ['id' => 12]);
// Result: "?page=view&id=12"

$url = Route::replaceUrlPlaceholders("?page=view&id=%id%", []);
// Result: "?page=view" (unmatched placeholder removed)
    </code></pre>
</div>