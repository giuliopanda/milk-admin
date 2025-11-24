<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title API
 * @guide framework
 * @order 10
 * @tags API, REST, JWT, token, authentication, endpoints, success, error, response, request, Bearer, authorization, HTTP-methods, GET, POST, PUT, DELETE, JSON, CORS, AbstractApi, ApiEndpoint, ApiDoc, documentation, permissions
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>API</h1>
    <p class="text-muted">Revision: 2025-11-11</p>
    <p>RESTful API system with JWT authentication, automatic routing, and endpoint documentation.</p>

    <h2 class="mt-4">Response Format</h2>
    <p>All API responses follow a standardized JSON format:</p>

    <p><strong>Success responses:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": true,
    "data": { /* your data */ }
}</code></pre>

    <p><strong>Error responses:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": false,
    "message": "Error description"
}</code></pre>

    <h2 class="mt-4">Endpoint Registration</h2>

    <p><strong>In modules (extends AbstractApi or extended AbstactModule):</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyTestApi extends AbstractApi {
    #[ApiEndpoint('my-test/hello')]
    public function apiHello($request) {
        return $this->success(['message' => 'Hello World']);
    }
}</code></pre>

    <p><strong>With authentication and HTTP method:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class UsersApi extends AbstractApi {
    #[ApiEndpoint('users/create', 'POST', ['auth' => true])]
    public function apiCreate($request) {
        $data = $request['body'];
        return $this->success(['id' => 1, 'username' => $data['username']]);
    }
}</code></pre>

    <p><strong>Manual registration:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\API;

API::set('test/hello', function($request) {
    return ['message' => 'Hello World'];
});</code></pre>

    <h2 class="mt-4">Documenting APIs</h2>
    <p>Add documentation using the <code>#[ApiDoc]</code> attribute:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Attributes\{ApiEndpoint, ApiDoc};

class PostsApi extends AbstractApi {
    #[ApiEndpoint('posts/create', 'POST', ['auth' => true])]
    #[ApiDoc(
        'Create a new blog post',
        ['body' => ['title' => 'string', 'content' => 'string']],
        ['id' => 'int', 'title' => 'string', 'created_at' => 'datetime']
    )]
    public function createPost($request) {
        return $this->success(['id' => 1, 'title' => 'New Post']);
    }
}</code></pre>

    <p><strong>With API::set():</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">API::set('test/hello',
    function($request) {
        return ['message' => 'Hello World'];
    },
    [],                                          // Options
    'Returns a hello world message',             // Description
    [],                                          // Parameters
    ['message' => 'string']                      // Response structure
);</code></pre>

    <h2 class="mt-4">Exception Handling</h2>
    <p>Throw exceptions in your API handlers - the framework catches and formats them automatically:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Exceptions\{ApiException, ApiAuthException};

API::set('users/delete', function($request) {
    $id = $request['input']('id');

    if (!$id) {
        throw new ApiException("User ID required", 422);
    }

    $user = User::find($id);
    if (!$user) {
        throw new ApiException("User not found", 404);
    }

    if ($user->id !== API::user()->id) {
        throw new ApiAuthException("Cannot delete other users", 403);
    }

    $user->delete();
    return ['success' => true];
}, ['auth' => true, 'method' => 'DELETE']);</code></pre>

    <p><strong>Available exceptions:</strong></p>
    <ul>
        <li><code>ApiException</code> - General API errors (default 400)</li>
        <li><code>ApiAuthException</code> - Authentication/authorization errors (default 403)</li>
    </ul>

    <h2 class="mt-4">Methods</h2>

    <h4 class="text-primary mt-4">API::set(string $page, callable|string $handler, array $options = [], ?string $description = null, ?array $parameters = null, ?array $response = null) : void</h4>
    <p>Registers an API endpoint.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">API::set('users/list', function($request) {
    return ['users' => User::all()];
});</code></pre>

    <h4 class="text-primary mt-4">API::group(array $options, callable $callback) : void</h4>
    <p>Groups endpoints with common options.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">API::group(['prefix' => 'admin', 'auth' => true], function() {
    API::set('users', 'AdminModule@users');
    API::set('settings', 'AdminModule@settings');
});</code></pre>

    <h4 class="text-primary mt-4">API::run(string $page) : bool</h4>
    <p>Executes an API endpoint. Handles authentication and exceptions automatically.</p>

    <h4 class="text-primary mt-4">API::user() : ?object</h4>
    <p>Returns the currently authenticated user or null.</p>

    <h4 class="text-primary mt-4">API::payload() : ?array</h4>
    <p>Returns the current JWT payload or null.</p>

    <h4 class="text-primary mt-4">API::request() : ?array</h4>
    <p>Returns the current request data.</p>

    <h4 class="text-primary mt-4">API::generateToken(int $user_id, array $additional_data = []) : array</h4>
    <p>Generates a JWT token for a user.</p>

    <h4 class="text-primary mt-4">API::refreshToken() : array</h4>
    <p>Refreshes the current JWT token.</p>

    <h4 class="text-primary mt-4">API::successResponse(mixed $data) : void</h4>
    <p>Sends a success response.</p>

    <h4 class="text-primary mt-4">API::errorResponse(string $msg, int $status, ?array $debug_info = null) : void</h4>
    <p>Sends an error response.</p>

    <h4 class="text-primary mt-4">API::listEndpoints() : array</h4>
    <p>Returns all registered endpoints with their documentation.</p>

    <h4 class="text-primary mt-4">API::getDocumentation(string $page) : ?array</h4>
    <p>Returns documentation for a specific endpoint.</p>

    <h2 class="mt-4">Request Structure</h2>
    <p>Handlers receive a request array with structured data:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">function($request) {
    // HTTP method
    $method = $request['method'];

    // Endpoint page
    $page = $request['page'];

    // URL parameters (id, slug, etc.)
    $id = $request['params']['id'] ?? null;

    // Query parameters (?key=value)
    $search = $request['query']['search'] ?? '';

    // Request body (JSON or form data)
    $data = $request['body'];

    // Headers
    $contentType = $request['headers']['Content-Type'] ?? '';

    // Uploaded files
    $files = $request['files'];

    // Auth info (if authenticated)
    $user = $request['auth']['user'] ?? null;

    // Helper methods
    $value = $request['input']('key', 'default');
    $exists = $request['has']('key');
    $all = $request['all']();
}</code></pre>

    <h2 class="mt-4">Options</h2>

    <p><strong>Authentication:</strong></p>
    <ul>
        <li><code>['auth' => true]</code> - Require JWT authentication</li>
    </ul>

    <p><strong>Permissions:</strong></p>
    <ul>
        <li><code>['permissions' => 'permission.name']</code> - Require specific permission</li>
        <li><code>['permissions' => 'token']</code> - Require fixed API token from <code>Config::get('api_token')</code></li>
    </ul>

    <p><strong>HTTP Methods:</strong></p>
    <ul>
        <li><code>['method' => 'GET']</code> - Only allow GET requests</li>
        <li><code>['method' => 'POST']</code> - Only allow POST requests</li>
        <li><code>['method' => 'PUT']</code> - Only allow PUT requests</li>
        <li><code>['method' => 'DELETE']</code> - Only allow DELETE requests</li>
        <li><code>['method' => 'ANY']</code> - Allow any method (default)</li>
    </ul>

    <h2 class="mt-4">Authentication Endpoints</h2>

    <h4 class="text-primary mt-4">auth/login</h4>
    <p><span class="method-post">POST</span> - Obtain JWT token with credentials.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">curl -X POST "api.php?page=auth/login" \
-H "Content-Type: application/json" \
-d '{"username": "admin", "password": "admin"}'</code></pre>

    <div class="response-success">
        <strong>Success response:</strong>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": true,
    "result": {
        "user": {
            "id": 1,
            "username": "admin",
            "email": "admin@example.com"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
        "expires_at": 1641234567,
        "expires_in": 3600
    }
}</code></pre>
    </div>

    <h4 class="text-primary mt-4">auth/verify</h4>
    <p><span class="method-get">GET</span> - Verify token validity.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">curl -X GET "api.php?page=auth/verify" \
-H "Authorization: Bearer YOUR_TOKEN"</code></pre>

    <div class="response-success">
        <strong>Success response:</strong>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": true,
    "result": {
        "user_id": 1,
        "username": "admin",
        "expires_at": 1641234567
    }
}</code></pre>
    </div>

    <h4 class="text-primary mt-4">auth/refresh</h4>
    <p><span class="method-get">GET</span> - Renew token with existing valid token.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">curl -X GET "api.php?page=auth/refresh" \
-H "Authorization: Bearer YOUR_TOKEN"</code></pre>

    <div class="response-success">
        <strong>Success response:</strong>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": true,
    "result": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
        "expires_at": 1641237567,
        "expires_in": 3600
    }
}</code></pre>
    </div>

    <h2 class="mt-4">Error Responses</h2>
    <p>All errors return HTTP status codes and a standardized error format:</p>

    <p><strong>Authentication error (401):</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": false,
    "message": "No authentication token provided"
}</code></pre>

    <p><strong>Permission error (403):</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": false,
    "message": "Insufficient permissions"
}</code></pre>

    <p><strong>Not found (404):</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": false,
    "message": "Endpoint not found"
}</code></pre>

    <p><strong>Method not allowed (405):</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": false,
    "message": "Method POST not allowed. Expected: GET"
}</code></pre>

    <p><strong>Server error (500):</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": false,
    "message": "Internal server error"
}</code></pre>

    <h2 class="mt-4">Examples</h2>

    <h4 class="mt-4">Complete Authenticated API</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\API;
use App\Exceptions\ApiException;

API::set('posts/create',
    function($request) {
        $title = $request['body']['title'];
        if (empty($title)) {
            throw new ApiException("Title required", 422);
        }
        // Get authenticated user
        $user = API::user();
        $post = new PostsModel();
        $post->fill([
            'title' => $title,
            'content' => $request['body']['content'],
            'user_id' => $user->id
        ]);
        $post->save();

        return [
            'id' => $post->id,
            'title' => $post->title,
            'created_at' => $post->created_at
        ];
    },
    ['auth' => true, 'method' => 'POST'],
    'Create a new blog post',
    ['body' => ['title' => 'string', 'content' => 'string']],
    ['id' => 'int', 'title' => 'string', 'created_at' => 'datetime']
);</code></pre>

    <h4 class="mt-4">PHP Client Example</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\HttpClient;
use App\Route;

// Login
 $response = HttpClient::post(Route::url() . '/api.php?page=auth/login', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode(['username' => 'admin', 'password' => 'admin'])
]);

if ($response['status_code'] == 200 && $response['body']['success']) {
    print "<h1>Success</h1>";
    print "<div>TOKEN: " . $response['body']['data']['token'] . "</div>";
    $user = $response['body']['data']['user'];
    echo "Username: " . $user['username'];
} else {
    print "<h1>Error</h1>";
    echo "<div>ERROR: " . $response['body']['message'] . "</div>";
}</code></pre>

</div>
