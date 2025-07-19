<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title API 
 * @category Framework
 * @order 10
 * @tags API, REST, JWT, token, authentication, login, verify, refresh, curl, Bearer, authorization, users, profile, list, create, update, delete, endpoints, MilkCoreTokenManager, automatic-token, token-refresh, error-handling, HTTP-methods, GET, POST, PUT, DELETE, JSON, pagination, search, CSRF, security, client, PHP-client, test-suite, browser-testing, command-line, 401, 403, 404, 422, 500, unauthorized, forbidden, not-found
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
    
    <p>Using the <code>MilkCoreTokenManager</code> class is highly recommended for PHP applications as it automatically handles all these aspects, providing a simple and robust interface for API interaction.</p>
</div>