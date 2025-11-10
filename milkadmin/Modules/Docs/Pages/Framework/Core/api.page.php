<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title API 
 * @guide framework
 * @order 10
 * @tags API, REST, JWT, token, authentication, login, verify, refresh, curl, Bearer, authorization, users, profile, list, create, update, delete, endpoints, MilkCoreTokenManager, automatic-token, token-refresh, error-handling, HTTP-methods, GET, POST, PUT, DELETE, JSON, pagination, search, CSRF, security, client, PHP-client, test-suite, browser-testing, command-line, 401, 403, 404, 422, 500, unauthorized, forbidden, not-found
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Complete MilkCore API Documentation</h1>
    <p>EndPoints are registered within modules in the module or in a {FileName}Api.php file within the module.</p>

    <p>To register an EndPoint, use the following attributes:
    <h3>#[ApiEndpoint($endpoint, $method, $options)].</h3>
    <ul>
    <li>$endpoint: The endpoint string</li>
    <li>$method: The HTTP method (GET, POST, PUT, DELETE, ANY). Default is ANY</li>
    <li>$options: The authentication array. For example: ['auth' => true] to require a user authenticated via JWT. [permission => 'auth.manage'] to verify a specific permission level. ['permissions'=>'token'] is a special handling for a call that is not authenticated but receives a fixed token configured in $config['api_token'];</li>
    </ul>

    <p>The code structure will look something like this:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
class MyTestApi extends AbstractApi {
    #[ApiEndpoint('my-test/hello')]
    public function apiHello($request) {
    return $this->success(['message' => 'Hello World']);
}
}</code></pre>

<p>You can find more examples at <a href="<?php echo Route::url(); ?>?page=docs&action=Developer/GettingStarted/getting-started-api">Make your first API</a></p>

<h2>Documenting your APIs (Recommended)</h2>

<p><strong>It is highly recommended to add documentation to your APIs</strong> using either the <code>#[ApiDoc]</code> attribute or the documentation parameters in <code>API::set()</code>.</p>

<h3>Documentation with #[ApiDoc] Attribute</h3>
<p>When using attributes, add <code>#[ApiDoc]</code> right after <code>#[ApiEndpoint]</code>:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Attributes\{ApiEndpoint, ApiDoc};

class MyTestApi extends AbstractApi {
    #[ApiEndpoint('my-test/create-post', 'POST', ['auth' => true])]
    #[ApiDoc(
        'Create a new blog post',
        ['body' => ['title' => 'string', 'content' => 'string', 'tags' => 'array']],
        ['id' => 'int', 'title' => 'string', 'created_at' => 'datetime']
    )]
    public function createPost($request) {
        return $this->success(['id' => 1, 'title' => 'New Post']);
    }
}</code></pre>

<h3>Documentation with API::set()</h3>
<p>When registering APIs without a class, pass documentation parameters to <code>API::set()</code>:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\API;

API::set('test/hello',
    function($request) {
        return ['message' => 'Hello World'];
    },
    ['auth' => true],
    'Returns a hello world message',                    // Description
    ['query' => ['name' => 'string']],                   // Parameters
    ['message' => 'string', 'timestamp' => 'datetime']   // Response
);
</code></pre>

<p><strong>Documentation structure:</strong></p>
<ul>
<li><strong>Description</strong>: Brief explanation of what the API does</li>
<li><strong>Parameters</strong>: Input parameters structure (supports nesting)</li>
<li><strong>Response</strong>: Expected response structure (supports nesting)</li>
</ul>

<p>Access documentation programmatically:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get specific endpoint documentation
$doc = API::getDocumentation('test/hello');

// Get all endpoints with documentation
$endpoints = API::listEndpoints();
</code></pre>

<h2>To register EndPoint APIs without using a class that extends AbstractModule</h2>

<p>To register an API, open milkadmin_local/functions.php and add the following code:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\API;
API::set('test/hello', function($request) {
return 'Hello World';
});
</code></pre>

<p><strong>With documentation (recommended):</strong></p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\API;

API::set('test/hello',
    function($request) {
        return ['message' => 'Hello World', 'timestamp' => time()];
    },
    [],                                          // Options (empty for public endpoint)
    'Returns a hello world message',             // Description
    [],                                          // Parameters (empty for no params)
    ['message' => 'string', 'timestamp' => 'int'] // Response structure
);
</code></pre>

<p>To call the API open your browser and go to http://localhost/api.php/?page=test/hello</p>

<p>To call the api from Milk Admin you can write:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\API;
$response = HttpClient::get('<?php echo Route::url(); ?>/api.php?page=test/hello'); 
if ($response['status_code'] == 200) {
$article = $response['body'];
}
</code></pre>

<p>If you want to handle a call with a user's authorization, you can write:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\API;

!defined('MILK_DIR') && die(); // Avoid direct access
API::set('test/hello', function($request) {
return 'Hello World';
}, '_user.is_authenticated');
</code></pre>

<p><strong>With authentication and documentation:</strong></p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\API;

API::set('users/profile',
    function($request) {
        $user = API::user();
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email
        ];
    },
    ['auth' => true, 'method' => 'GET'],           // Options with auth
    'Get current authenticated user profile',       // Description
    [],                                             // No parameters needed
    ['id' => 'int', 'username' => 'string', 'email' => 'string'] // Response
);
</code></pre>

<p>This is a minimal example with authentication:</p>
<p>Create a new file in the root directory called test-api.php. (In production do not create new files in the root directory, this is just for testing).</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace MilkCore;

define('MILK_DIR', __DIR__);
require __DIR__ . '/App/autoload.php';

!defined('MILK_DIR') && die(); // Avoid direct access
// user and password
$response = HttpClient::post(Route::url() . '/api.php?page=auth/login', 
['headers' => ['Authorization' => 'Basic' . base64_encode( 'admin:admin' )]]);
if (@$response['status_code'] == 200 && ($response['body']['success'] ?? false)) { 
    $token = $response['body']['data']['token'];
    print "TOKEN: ".$token."\n";

    $response = HttpClient::post(Route::url() . '/api.php?page=test/hello',
    ['headers' => ['Authorization' => 'Bearer ' . $token]]);
    var_dump ($response['body']);

} else {
    print "<pre>";
    var_dump($response);
    print "</pre>";
    die();
}

</code></pre>

    <p>MilkCore APIs provide a complete RESTful interface for user management, JWT authentication, and CSRF protection. The system also includes a PHP class for automatic token management.</p>

    <h2 class="mt-4">Authentication APIs</h2>

    <h4 class="mt-4">1. Login - Get access token</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>auth/login</code><br>
        <strong>Method:</strong> <span class="method-post">POST</span><br>
        <strong>Authentication:</strong> <span class="auth-not-required">Not required (public)</span>
    </div>
    
    <p>This endpoint allows you to obtain a JWT token by providing valid login credentials.</p>
    
    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X POST "api.php?page=auth/login" \
-H "Content-Type: application/json" \
-d '{
"username": "admin", 
"password": "admin"
}'</code></pre>

    <div class="response-success">
        <strong>Success response:</strong>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code>{
"success": true,
"message": "Login successful",
"data": {
"user": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "is_admin": true
},
"token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
"expires_at": 1641234567,
"expires_in": 3600
}
}</code></pre>
    </div>

    <h4 class="mt-4">2. Token Verification - Check token validity</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>auth/verify</code><br>
        <strong>Method:</strong> <span class="method-get">GET</span><br>
        <strong>Authentication:</strong> <span class="auth-required">Required (token in header)</span>
    </div>
    
    <p>This endpoint allows you to verify if a JWT token is still valid and has not expired.</p>
    
    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X GET "api.php?page=auth/verify" \
-H "Authorization: Bearer YOUR_TOKEN_HERE"</code></pre>

    <div class="response-success">
        <strong>Success response:</strong>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code>{
"success": true,
"message": "Valid token",
"data": {
    "user_id": 1,
    "username": "admin",
    "expires_at": 1641234567
}
}</code></pre>
    </div>

    <h4 class="mt-4">3. Refresh Token - Renew an existing token</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>auth/refresh</code><br>
        <strong>Method:</strong> <span class="method-get">GET</span><br>
        <strong>Authentication:</strong> <span class="auth-required">Required (token in header)</span>
    </div>
    
    <p>This endpoint allows you to obtain a new JWT token using an existing valid token, thus extending the user's session.</p>
    
    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X GET "api.php?page=auth/refresh" \
-H "Authorization: Bearer YOUR_CURRENT_TOKEN"</code></pre>

    <div class="response-success">
        <strong>Success response:</strong>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code>{
"success": true,
"message": "Token renewed",
"token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
"expires_at": 1641237567,
"expires_in": 3600
}</code></pre>
    </div>

    <h2 class="mt-4">Important Notes</h2>
    
    <p>When using these APIs, it's important to keep in mind some fundamental aspects to ensure security and proper system functioning.</p>
    
    <p>The token obtained through login must be included in all subsequent requests that require authentication, using the Authorization header with the format "Bearer TOKEN". The token duration is time-limited for security reasons, so it's advisable to implement automatic refresh logic when necessary.</p>
    
    <p>It's essential to store the token securely on the client side and not expose it in logs or URLs. In case of expired or invalid token, the system will return an appropriate error that should be handled by the client application.</p>
    
</div>