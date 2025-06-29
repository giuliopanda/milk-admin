<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * @title Token
 * @category Framework
 * @order 
 * @tags 
 */
?>
<div class="bg-white p-4">
    <h1>Token Class</h1>

    <div class="alert alert-success">
        When you are a logged-in user, all POST data submissions both through forms and fetch are automatically protected against CSRF attacks, so there's no need to do anything. However, to protect a non-logged form submission you need to manually protect the submission by generating a token and verifying it on the PHP side.
    </div>

    <p>The <strong>Token</strong> class is used to generate and verify protection tokens. 
    It handles two main types of tokens: those for protection against <strong>CSRF</strong> attacks and those for <strong>API</strong> authentication through <strong>JWT</strong>.</p>

    <h4>CSRF Protection</h4>
    <p><strong>Cross-Site Request Forgery</strong> attacks are attacks where a malicious actor induces an authenticated user to perform unwanted actions on a web application, exploiting their active authentication to send unauthorized requests to the server without the user noticing.</p>

    <h4>JWT Authentication</h4>
    <p>The <strong>JWT</strong> (JSON Web Token) system authenticates API calls through a simple and effective process. Initially, the user's <strong>credentials</strong> are sent to the server, which generates a <strong>JWT token</strong> containing the necessary information and sends it to the user. From this moment, the user includes the <strong>token</strong> in every subsequent request, allowing the server to <strong>verify</strong> identity and permissions. When the token expires, it's possible to generate a <strong>new token</strong> without having to resend authentication credentials.</p>

    <h2 class="mt-4">CSRF Token Functions</h2>

    <h4 class="mt-4">config($secret_key, $token_key)</h4>
    <p>Configures the secret key and token key for CSRF protection.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Token::config('secret_key', 'token_key');</code></pre>

    <h4 class="mt-4">input($name)</h4>
    <p>Generates a hidden HTML input containing the CSRF token.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">echo Token::input('form_name');</code></pre>

    <p>Through the input it's possible to protect a form submission from CSRF attacks.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;form action="..." method="post"&gt;&lt;?php echo Token::input('form_name'); ?&gt;&lt;/form&gt;</code></pre>
    <p>In this case you use <code>check</code> to verify the token. Check also finds the input where the token is passed.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (Token::check('form_name')) { //... }</code></pre>

    <h4 class="mt-4">get($name)</h4>
    <p>Generates a CSRF token for browser call authentication.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$token = Token::get('form_name');</code></pre>
    <p>This is useful if we are protecting an ajax call since input generates an input whose name is random</p>
    <p>PHP</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$token = Token::get('form_name');</code></pre>
    <p>Javascript</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">fetch(milk_url, {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
}).then((response) => {
    return response.json();
}).then((data) => {
   console.log(data);
});</code></pre>
    <p>PHP-side verification</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (Token::check_value($_POST['token'], 'form_name')) { //... }</code></pre> 

    <h4 class="mt-4">get_token_key($name)</h4>
    <p>Returns the token key generated from the provided name.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$tokenKey = Token::get_token_key('form_name');</code></pre>

    <h4 class="mt-4">get_token_name($name)</h4>
    <p>Returns the name of the variable where the token is stored.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$tokenName = Token::get_token_name('form_name');</code></pre>

    <h4 class="mt-4">check($name)</h4>
    <p>Verifies if the CSRF token is correct, also finding the variable name.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$isValid = Token::check('form_name');</code></pre>

    <h4 class="mt-4">check_value($token, $name)</h4>
    <p>Verifies if the CSRF token value is correct.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$isValid = Token::check_value($token, 'form_name');</code></pre>

    <h2 class="mt-5">JWT Token Functions</h2>

    <h4 class="mt-4">config_jwt($private_key, $public_key, $expiration)</h4>
    <p>Configures keys and expiration for the JWT system. If parameters are not provided, global configurations are used.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Token::config_jwt($private_key, $public_key, 3600); // 1 hour</code></pre>

    <h4 class="mt-4">generate_key_pair()</h4>
    <p>Generates a public/private key pair for JWT authentication with 2048-bit RSA algorithm.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$keys = Token::generate_key_pair();
if ($keys) {
    $private_key = $keys['private_key'];
    $public_key = $keys['public_key'];
}</code></pre>

    <h4 class="mt-4">generate_jwt($user_id, $additional_data)</h4>
    <p>Generates a JWT token for a specific user. It's possible to add custom data to the token payload.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Base token with only user_id
$token = Token::generate_jwt(123);

// Token with additional data
$additional_data = [
    'role' => 'admin',
    'permissions' => ['read', 'write', 'delete']
];
$token = Token::generate_jwt(123, $additional_data);</code></pre>

    <h4 class="mt-4">verify_jwt($token)</h4>
    <p>Verifies a JWT token and returns its payload if valid. Automatically checks expiration and token integrity.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$payload = Token::verify_jwt($token);
if ($payload) {
    $user_id = $payload['user_id'];
    $role = $payload['role'] ?? null;
    echo "Authenticated user: " . $user_id;
} else {
    echo "Invalid token: " . Token::$last_error;
}</code></pre>

    <h4 class="mt-4">Property $last_error</h4>
    <p>Contains the last error that occurred during token operations. Useful for debugging and error handling.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (!Token::check('form_name')) {
    echo "Error: " . Token::$last_error;
}</code></pre>

    <h3 class="mt-4">Complete JWT Usage Example</h3>
    <p>Complete API authentication example using JWT tokens:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Initial configuration
Token::config_jwt($private_key, $public_key, 7200); // 2 hours

// Token generation after login
$user_id = 123;
$user_data = [
    'email' => 'user@example.com',
    'role' => 'user'
];
$jwt_token = Token::generate_jwt($user_id, $user_data);

// Send token to client
header('Content-Type: application/json');
echo json_encode(['token' => $jwt_token]);

// Token verification in subsequent requests
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    $token = $matches[1];
    $payload = Token::verify_jwt($token);
    
    if ($payload) {
        // Valid token, proceed with operation
        $current_user_id = $payload['user_id'];
        $user_email = $payload['email'];
    } else {
        // Invalid token
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
}</code></pre>
</div>