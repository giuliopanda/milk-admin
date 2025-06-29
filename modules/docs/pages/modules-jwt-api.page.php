<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title JWT API 
 * @category Modules
 * @order 20
 * @tags API, curl, Token, JWT  
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
   <h1>JWT Authentication Documentation</h1>

   <div class="alert alert-info">Download the <strong><a href="<?php echo Route::url(); ?>/modules/api-registry/assets/api-token-manager.class.zip">api-token-manager.class.zip</a></strong> class for automatic JWT token management in your client project.</div>
   <p>This documentation provides a detailed overview of JWT (JSON Web Token) authentication and how to implement it in your application.</p>

   <h2>Introduction</h2>
   <p>JWT (JSON Web Token) is a compact, URL-safe means of representing claims to be transferred between two parties. In authentication systems, JWTs allow you to securely transmit information between client and server as a JSON object.</p>

   <h2>How JWT Authentication Works</h2>
   <p>The JWT authentication process follows a simple flow:</p>
   <ol>
       <li>Client sends credentials (username/password) to the server</li>
       <li>Server validates credentials and generates a JWT token</li>
       <li>Server returns the token to the client</li>
       <li>Client stores the token and sends it with subsequent requests</li>
       <li>Server validates the token and processes the request</li>
   </ol>

   <h2>Basic JWT Authentication Example</h2>
   <p>Below is a simple example demonstrating how to implement JWT authentication in your application.</p>

   <h3>Step 1: Authentication Request</h3>
   <p>First, the client sends a request with username and password to get a JWT token:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Client-side code: Request a token with username and password
$url = 'https://example.com/?page=jwt-test-api-v1&action=generate-token';

// Set up the request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

// Set Basic Authentication header
$username = 'admin';
$password = 'admin';
$auth_string = base64_encode("$username:$password");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
   'Content-Type: application/json',
   'Authorization: Basic ' . $auth_string
]);

// Execute the request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Parse the response
if ($http_code == 200) {
   $token_data = json_decode($response, true);
   echo "Token received: " . $token_data['token'] . "\n";
   echo "Expires at: " . date('Y-m-d H:i:s', $token_data['expires_at']) . "\n";
} else {
   echo "Authentication failed: $response\n";
}
   </code></pre>

   <h3>Step 2: Server-side Token Generation</h3>
   <p>The server authenticates the user and generates a JWT token:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Server-side code: Route handler for generating tokens
Route::set('jwt-test-api-v1', function() {
   if ($_REQUEST['action'] == 'generate-token') {
       // Extract username and password from Basic Auth header
       $credentials = Route::extract_credentials();
       
       // Validate credentials
       $login = Get::make('auth')->login($credentials['username'], $credentials['password'], false);
       if ($login == false) {
           Get::response_json([
               'error' => 'Invalid credentials'
           ]);
           return;
       }
       
       // Get authenticated user
       $user = Get::make('auth')->get_user();
       
       // Generate JWT token
       $token = Token::generate_jwt($user->id, [
           'username' => $user->username,
           'email' => $user->email,
           // Add other user data as needed
       ]);
       
       // Calculate expiration time (1 hour from now)
       $expires_at = time() + 3600;
       
       // Return token and expiration
       Get::response_json([
           'token' => $token,
           'expires_at' => $expires_at
       ]);
   }
});
   </code></pre>

   <h3>Step 3: Using the Token for API Requests</h3>
   <p>Once the client has the token, it can make authenticated API requests:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Client-side code: Using the JWT token for API requests
$url = 'https://example.com/?page=api-endpoint&action=get-data';
$token = $token_data['token']; // Token received from previous step

// Set up the request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
   'Content-Type: application/json',
   'Authorization: Bearer ' . $token
]);

// Execute the request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Process the response
if ($http_code == 200) {
   $data = json_decode($response, true);
   echo "API request successful\n";
   print_r($data);
} else {
   echo "API request failed: $response\n";
}
   </code></pre>

   <h3>Step 4: Server-side Token Verification</h3>
   <p>The server verifies the token for protected API endpoints:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Server-side code: Verifying JWT token on protected endpoints
Route::set('api-endpoint', function() {
   // Extract token from Authorization header
   $token = Route::get_bearer_token();
   if (!$token) {
       Get::response_json([
           'error' => 'Authentication required'
       ]);
       return;
   }
   
   // Verify token
   $user_id = Token::verify_jwt($token);
   if ($user_id === false) {
       Get::response_json([
           'error' => 'Invalid token: ' . Token::$last_error
       ]);
       return;
   }
   
   // Token is valid, proceed with the API request
   if ($_REQUEST['action'] == 'get-data') {
       // Get user information
       $user = Get::make('auth')->get_user($user_id);
       
       // Return data
       Get::response_json([
           'message' => 'Authentication successful',
           'user_id' => $user_id,
           'username' => $user->username,
           'data' => [
               // Your API response data here
           ]
       ]);
   }
});
   </code></pre>

   <h2>JWT Token Structure</h2>
   <p>A JWT token consists of three parts separated by dots:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c
   </code></pre>
   
   <p>These parts are:</p>
   <ol>
       <li><strong>Header</strong>: Contains the token type and signing algorithm</li>
       <li><strong>Payload</strong>: Contains the claims (user data and metadata)</li>
       <li><strong>Signature</strong>: Verifies the token hasn't been tampered with</li>
   </ol>

   <h2>Key Concepts</h2>
   
   <h4>1. Basic Authentication</h4>
   <p>Used only for the initial token request. The client sends the username and password encoded in the Authorization header:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>
Authorization: Basic YWRtaW46YWRtaW4=
   </code></pre>
   <p>Where <code>YWRtaW46YWRtaW4=</code> is the Base64 encoding of <code>admin:admin</code>.</p>
   
   <h4>2. Bearer Authentication</h4>
   <p>Used for all subsequent API requests after obtaining a token. The client includes the token in the Authorization header:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
   </code></pre>
   
   <h4>3. Token Expiration</h4>
   <p>JWT tokens have an expiration time for security. The server returns this as a Unix timestamp:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>
{
   "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
   "expires_at": 1620000000
}
   </code></pre>
   <p>Clients should refresh tokens before they expire to maintain the session.</p>
   
   <h4>4. Token Refresh</h4>
   <p>To refresh a token, the client sends the current token to a refresh endpoint, which issues a new token:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Client-side token refresh example
$url = 'https://example.com/?page=jwt-test-api-v1&action=refresh-token';
$current_token = $token_data['token'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
   'Content-Type: application/json',
   'Authorization: Bearer ' . $current_token
]);

$response = curl_exec($ch);
curl_close($ch);

$new_token_data = json_decode($response, true);
// Use the new token for subsequent requests
   </code></pre>

   <h2>Complete JWT Test Implementation</h2>
   <p>For a complete implementation example, you can check the JWT test files:</p>
   <ul>
       <li><strong>Server Controller</strong>: Handles token generation, verification and refreshing</li>
       <li><strong>JWT Test Client</strong>: Tests the JWT functionality with a complete authentication flow</li>
   </ul>
   <p>To run the test and see the complete flow in action, access <code>/jwt.test.php</code> in your browser. This will execute a series of tests demonstrating:</p>
   <ol>
       <li>Basic Authentication and token generation</li>
       <li>Token verification</li>
       <li>Token refresh</li>
       <li>Verification of the new token</li>
   </ol>

   <h2>Security Best Practices</h2>
   <ul>
       <li><strong>Always use HTTPS</strong> to prevent token interception</li>
       <li><strong>Set appropriate token expiration</strong> (typically 15-60 minutes)</li>
       <li><strong>Don't store sensitive data</strong> in the token payload</li>
       <li><strong>Implement proper error handling</strong> for authentication failures</li>
       <li><strong>Use secure algorithms</strong> for token signing (RS256 recommended for production)</li>
   </ul>

   <h2>Conclusion</h2>
   <p>JWT provides a simple yet powerful way to implement authentication in your API. By understanding the basic flow and implementing the provided examples, you can quickly add secure authentication to your application.</p>
</div>