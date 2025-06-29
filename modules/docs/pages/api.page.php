<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title API 
 * @category Framework
 * @order 10
 * @tags API, curl, Token, JWT  
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Complete MilkCore API Documentation</h1>
    
    <div class="alert alert-info">Download the <strong><a href="<?php echo Route::url(); ?>/modules/api-registry/assets/api-token-manager.class.zip">api-token-manager.class.zip</a></strong> class for automatic JWT token management in your client project.</div>
    
    <p>To use the class, initialize it as follows:</p>
    
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
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X POST "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=auth/login" \
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
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X GET "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=auth/verify" \
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
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X GET "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=auth/refresh" \
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

    <h2 class="mt-4">User Management</h2>

    <h4 class="mt-4">1. Current User Profile</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>users/profile</code><br>
        <strong>Method:</strong> <span class="method-get">GET</span><br>
        <strong>Authentication:</strong> <span class="auth-required">Required</span>
    </div>
    
    <p>Gets the profile of the currently authenticated user.</p>
    
    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X GET "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=users/profile" \
-H "Authorization: Bearer YOUR_TOKEN"</code></pre>

    <h4 class="mt-4">2. User List</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>users/list</code><br>
        <strong>Method:</strong> <span class="method-get">GET</span><br>
        <strong>Authentication:</strong> <span class="auth-required">Required</span>
    </div>
    
    <p>Gets the list of all system users with support for pagination and filters.</p>
    
    <p><strong>Optional parameters:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>page=1          # Page number (default: 1)
limit=50        # Items per page (default: 50)
search=term     # Search term for username/email</code></pre>

    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X GET "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=users/list&limit=10" \
-H "Authorization: Bearer YOUR_TOKEN"</code></pre>

    <h4 class="mt-4">3. View Specific User</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>users/show</code><br>
        <strong>Method:</strong> <span class="method-get">GET</span><br>
        <strong>Authentication:</strong> <span class="auth-required">Required</span>
    </div>
    
    <p>Gets the complete details of a specific user by their ID.</p>
    
    <p><strong>Required parameters:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>id=1    # ID of the user to view</code></pre>

    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X GET "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=users/show&id=1" \
-H "Authorization: Bearer YOUR_TOKEN"</code></pre>

    <h4 class="mt-4">4. Create New User</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>users/create</code><br>
        <strong>Method:</strong> <span class="method-post">POST</span><br>
        <strong>Authentication:</strong> <span class="auth-required">Required</span>
    </div>
    
    <p>Creates a new user in the system with the provided data.</p>
    
    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X POST "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=users/create" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer YOUR_TOKEN" \
-d '{
"username": "new_user",
"email": "new@example.com",
"password": "password123"
}'</code></pre>

    <div class="response-success">
        <strong>Success response:</strong>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code>{
"success": true,
"message": "User created successfully",
"data": {
"user": {
    "id": 5,
    "username": "new_user",
    "email": "new@example.com",
    "created_at": "2025-01-01 10:00:00"
}
}
}</code></pre>
    </div>

    <h4 class="mt-4">5. Update Existing User</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>users/update</code><br>
        <strong>Method:</strong> <span class="method-put">PUT</span><br>
        <strong>Authentication:</strong> <span class="auth-required">Required</span>
    </div>
    
    <p>Updates the data of an existing user. Only the specified fields can be updated.</p>
    
    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X PUT "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=users/update" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer YOUR_TOKEN" \
-d '{
"id": 5,
"email": "new_email@example.com"
}'</code></pre>

    <h4 class="mt-4">6. Delete User</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>users/delete</code><br>
        <strong>Method:</strong> <span class="method-delete">DELETE</span><br>
        <strong>Authentication:</strong> <span class="auth-required">Required</span>
    </div>
    
    <p>Permanently deletes a user from the system.</p>
    
    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X DELETE "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=users/delete" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer YOUR_TOKEN" \
-d '{
"id": 5
}'</code></pre>


    <h2 class="mt-4">Utility Endpoints</h2>

    <h4 class="mt-4">1. API Information</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>test/info</code><br>
        <strong>Method:</strong> <span class="method-get">GET</span><br>
        <strong>Authentication:</strong> <span class="auth-not-required">Not required</span>
    </div>
    
    <p>Test endpoint to verify API functionality and get basic information.</p>
    
    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X GET "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=test/info"</code></pre>

    <div class="response-success">
        <strong>Success response:</strong>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code>{
"success": true,
"message": "API working",
"data": {
"version": "1.0.0",
"timestamp": 1641234567,
"method": "GET",
"headers": {...},
"query": {...}
}
}</code></pre>
    </div>

    <h4 class="mt-4">2. Available Endpoints List</h4>
    <div class="endpoint-box">
        <strong>Endpoint:</strong> <code>endpoints</code><br>
        <strong>Method:</strong> <span class="method-get">GET</span><br>
        <strong>Authentication:</strong> <span class="auth-not-required">Not required</span>
    </div>
    
    <p>Returns the complete list of all available endpoints in the system.</p>
    
    <p><strong>Request example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>curl -X GET "http://localhost/progetto-redcap-statistiche/new_version_250500/api.php?page=endpoints"</code></pre>

    <h2 class="mt-4">MilkCoreTokenManager Class</h2>
    
    <p>The <code>MilkCoreTokenManager</code> class provides a PHP interface to automatically manage the entire JWT token lifecycle, including automatic login, token refresh, and error handling.</p>

    <h4 class="mt-4">Initialization</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code><?php echo htmlentities("<?php"); ?>
require_once 'api-token-manager.class.php';

// Configuration
$api = new MilkCoreTokenManager(
    'http://localhost/progetto-redcap-statistiche/new_version_250500/api.php',
    'admin',      // username
    'admin',      // password
    [
        'debug' => true,
        'refresh_margin' => 300,  // refresh 5 minutes before expiration
        'timeout' => 30
    ]
);
?></code></pre>

    <h4 class="mt-4">Main Methods</h4>
    
    <p><strong>API calls with automatic token management:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>// GET request
$response = $api->get('users/profile');

// POST request
$response = $api->post('users/create', [
    'username' => 'new_user',
    'email' => 'new@example.com',
    'password' => 'password123'
]);

// PUT request
$response = $api->put('users/update', [
    'id' => 1,
    'email' => 'new_email@example.com'
]);

// DELETE request
$response = $api->delete('users/delete', ['id' => 1]);</code></pre>

    <p><strong>Manual token management:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>// Manual login
if ($api->login()) {
    echo "Login successful";
}

// Current token information
$token_info = $api->get_token_info();
if ($token_info) {
    echo "Token expires in: " . $token_info['expires_in'] . " seconds";
}

// Check if token should be renewed
if ($api->should_refresh()) {
    $api->refresh_token();
}

// Get last error
if (!$response) {
    echo "Error: " . $api->get_last_error();
}</code></pre>

    <h4 class="mt-4">Automatic Management</h4>
    
    <p>The Token Manager automatically handles:</p>
    <ul>
        <li><strong>Automatic login:</strong> If there's no token, it automatically performs login</li>
        <li><strong>Automatic refresh:</strong> Renews the token when it's about to expire (configurable margin)</li>
        <li><strong>Automatic retry:</strong> If a call fails due to expired token, it gets a new token and retries</li>
        <li><strong>Error handling:</strong> Provides detailed error messages through <code>get_last_error()</code></li>
    </ul>

    <h2 class="mt-4">Test Suite</h2>
    
    <p>The system includes complete test scripts to verify the functionality of all APIs.</p>

    <h4 class="mt-4">Command line testing</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code># Run all tests
php test-api-client.php

# Test single endpoint
php test-api-client.php auth/login POST

# Test with Token Manager
php test-token-manager.php</code></pre>

    <h4 class="mt-4">Browser testing</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code># All tests
http://localhost/progetto-redcap-statistiche/new_version_250500/test-api-client.php

# Single endpoint test
http://localhost/progetto-redcap-statistiche/new_version_250500/test-api-client.php?endpoint=users/list&method=GET</code></pre>

    <h2 class="mt-4">Error Handling</h2>
    
    <p>All APIs return responses in JSON format with a consistent structure. In case of error, the response will always include the "error" field set to true and a descriptive message.</p>

    <h4 class="mt-4">Error Response Structure</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>{
"error": true,
"message": "Error description",
"code": "ERROR_CODE",
"data": null
}</code></pre>

    <h4 class="mt-4">Common Error Codes</h4>
    <ul>
        <li><strong>401 Unauthorized:</strong> Missing, expired, or invalid token</li>
        <li><strong>403 Forbidden:</strong> Insufficient permissions for the operation</li>
        <li><strong>404 Not Found:</strong> Endpoint or resource not found</li>
        <li><strong>405 Method Not Allowed:</strong> HTTP method not allowed for the endpoint</li>
        <li><strong>422 Unprocessable Entity:</strong> Invalid input data</li>
        <li><strong>500 Internal Server Error:</strong> Internal server error</li>
    </ul>

    <h2 class="mt-4">Important Notes</h2>
    
    <p>When using these APIs, it's important to keep in mind some fundamental aspects to ensure security and proper system functioning.</p>
    
    <p>The token obtained through login must be included in all subsequent requests that require authentication, using the Authorization header with the format "Bearer TOKEN". The token duration is time-limited for security reasons, so it's advisable to implement automatic refresh logic when necessary.</p>
    
    <p>It's essential to store the token securely on the client side and not expose it in logs or URLs. In case of expired or invalid token, the system will return an appropriate error that should be handled by the client application.</p>
    
    <p>Using the <code>MilkCoreTokenManager</code> class is highly recommended for PHP applications as it automatically handles all these aspects, providing a simple and robust interface for API interaction.</p>
</div>