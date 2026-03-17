<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Generates a token that can be used to authenticate a user and protect forms from CSRF attacks.
 * In addition to the token, it also creates the token name.
 * 
 * Cross-Site Request Forgery (CSRF) attacks occur when a malicious actor induces an authenticated user
 * to perform unwanted actions on a web application where they're authenticated, without their knowledge.
 * The attacker exploits the user's active authentication to send unauthorized requests to a server.
 * 
 * 
 *
 * Includes JWT support for microservices/API authentication. Can generate and validate JWT tokens
 * with RS256 signature verification without database storage.
 *
 * @package     App
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @version     1.0.0
 */

class Token {

    /**
     * Last error that occurred during operations
     * 
     * @var string
     */
    public static $last_error = '';
    
    /**
     * Secret key used for encryption
     * 
     * @var string
     */
    static private $secret_key;
    
    /**
     * Key for token generation
     * 
     * @var string 
     */
    static private $token_key;
    
    /**
     * Private key content for JWT signing
     * 
     * @var string
     */
    static private $private_key;
    
    /**
     * Public key content for JWT verification
     * 
     * @var string
     */
    static private $public_key;
    
    /**
     * JWT token expiration time in seconds (default: 1 hour)
     * 
     * @var int
     */
    static private $jwt_expiration = 3600;
    private const CSRF_SESSION_STORE = '__milk_csrf_tokens';
    private const CSRF_DEFAULT_CONTEXT = '__csrf_global';
    private const CSRF_TTL_SECONDS = 43200; // 12 hours
    /**
     * Configure keys for CSRF token generation
     * 
     * @param string $secret_key Secret key for encryption
     * @param string $token_key Key for token generation
     * @return void
     */
    public static function config($secret_key, $token_key) {
        self::$secret_key = $secret_key;
        self::$token_key = $token_key;
    }

    public static function getConfiguredTokenKey(): ?string
    {
        return self::$token_key;
    }
    
    /**
     * Configure JWT settings
     *
     * Sets the keys and expiration for JWT functionality
     *
     * @param string|null $private_key Private key content (null to use Config)
     * @param string|null $public_key Public key content (null to use Config)
     * @param int|null $expiration Token duration in seconds (null to use Config)
     * @return void
     */
    /**
     * Configure JWT settings
     *
     * Sets the keys and expiration for JWT functionality
     *
     * @param string|null $private_key Private key content (null to use Config)
     * @param string|null $public_key Public key content (null to use Config)
     * @param int|null $expiration Token duration in seconds (null to use Config)
     * @return void
     */
    public static function configJwt($private_key = null, $public_key = null, $expiration = null) {
       
        // Get values from Config if not provided
        if ($private_key === null) {
            if (is_file(STORAGE_DIR ."/". Config::get('jwt_private_key'))) {
               
                $private_key = file_get_contents(STORAGE_DIR ."/". Config::get('jwt_private_key'));
            }
        }
        if ($public_key === null) {
            if (is_file(STORAGE_DIR ."/". Config::get('jwt_public_key'))) {
                $public_key = file_get_contents(STORAGE_DIR ."/". Config::get('jwt_public_key'));
            }
        }
    
        self::$jwt_expiration = $expiration ?? Config::get('jwt_expiration', 3600);
        
        // Process keys to ensure correct format
        if ($private_key) {
            // Replace literal \n with actual newlines if needed
            if (strpos($private_key, "\\n") !== false) {
                $private_key = str_replace("\\n", "\n", $private_key);
            }
            self::$private_key = $private_key;
        }
        
        if ($public_key) {
            // Replace literal \n with actual newlines if needed
            if (strpos($public_key, "\\n") !== false) {
               $public_key = str_replace("\\n", "\n", $public_key);
            }
            self::$public_key = $public_key;
        }
    }

    /**
     * Generate a hidden input element for the CSRF token.
     *
     * @param string|null $name Optional token context
     * @return string HTML input element
     */
    public static function input($name = null) {
        return '<input type="hidden" name="'.self::getTokenName($name).'" value="'.self::get($name).'">';
    }

    /**
     * Forces generation of a fresh CSRF token for the given context.
     *
     * @param string|null $name Optional token context
     * @return string Token value
     */
    public static function generate($name = null): string
    {
        self::$last_error = '';
        $context = self::getTokenKey($name);

        if (!self::ensureCsrfStore()) {
            throw new \RuntimeException('CSRF session store is not available.');
        }

        $token = self::createRandomToken();
        $_SESSION[self::CSRF_SESSION_STORE][$context] = [
            'token' => $token,
            'issued_at' => time(),
            'user_id' => self::getCurrentUserId(),
        ];

        return $token;
    }

    /**
     * Returns the CSRF token for the given context, generating it when missing/expired.
     *
     * @param string|null $name Optional token context
     * @return string Token value
     */
    public static function get($name = null) {
        self::$last_error = '';
        $context = self::getTokenKey($name);

        if (!self::ensureCsrfStore()) {
            throw new \RuntimeException('CSRF session store is not available.');
        }

        $entry = $_SESSION[self::CSRF_SESSION_STORE][$context] ?? null;
        if (!is_array($entry)) {
            return self::generate($name);
        }

        $token = (string) ($entry['token'] ?? '');
        if ($token === '') {
            return self::generate($name);
        }
        if (!self::isValidTokenFormat($token)) {
            return self::generate($name);
        }

        if (self::isCsrfEntryExpired($entry)) {
            return self::generate($name);
        }

        if (!self::isCsrfUserContextValid($entry)) {
            return self::generate($name);
        }

        return $token;
    }

    /**
     * Returns a deterministic context key for a token.
     *
     * @param string|null $name Optional token context
     * @return string Token context key
     */
    public static function getTokenKey($name = null) {
        if (!is_string($name) || trim($name) === '') {
            return self::CSRF_DEFAULT_CONTEXT;
        }
        return $name;
    }

    /**
     * Returns the request variable name used to carry the token.
     *
     * @param string|null $name Optional token context
     * @return string Generated token variable name
     */
    public static function getTokenName($name = null) {
        $context = self::getTokenKey($name);
        if ($context === self::CSRF_DEFAULT_CONTEXT) {
            return 'csrf_token';
        }
        $salt = trim((string) self::$token_key);
        if ($salt === '') {
            $salt = trim((string) self::$secret_key);
        }
        if ($salt === '') {
            $salt = 'csrf_token';
        }
        return 'csrf_' . substr(hash_hmac('sha256', $context, $salt), 0, 12);
    }

    /**
     * Verifies if the CSRF token is correct by finding the variable name in the request
     * and checking its value.
     *
     * @param string|null $name Optional token context
     * @return bool True if the token is valid, false otherwise
     */
    public static function check($name = null) {
        self::$last_error = '';
        $input_name = self::getTokenName($name);
        if (!isset($_REQUEST[$input_name])) {
            self::$last_error = 'input_not_found';
            return false;
        }
        $input_value = $_REQUEST[$input_name];
        return self::checkValue($input_value, $name);
    }

    /**
     * Verifies if the CSRF token value is correct.
     * Useful for AJAX requests where the token is passed directly.
     *
     * @param string|null $token Token value to verify
     * @param string|null $name Optional token context
     * @return bool True if the token is valid, false otherwise
     */
    public static function checkValue($token, $name = null) {
        self::$last_error = '';
        $context = self::getTokenKey($name);
        $token = is_string($token) ? trim($token) : '';

        if ($token === '') {
            self::$last_error = 'invalid_token';
            return false;
        }
        if (!self::isValidTokenFormat($token)) {
            self::$last_error = 'invalid_token_format';
            return false;
        }
        if (!self::ensureCsrfStore()) {
            return false;
        }

        $entry = $_SESSION[self::CSRF_SESSION_STORE][$context] ?? null;
        if (is_array($entry)) {
            if (self::isCsrfEntryExpired($entry)) {
                unset($_SESSION[self::CSRF_SESSION_STORE][$context]);
                self::$last_error = 'expired_token';
                return false;
            }

            if (!self::isCsrfUserContextValid($entry)) {
                self::$last_error = 'invalid_user_context';
                return false;
            }

            $expected = (string) ($entry['token'] ?? '');
            if ($expected !== '' && self::isValidTokenFormat($expected) && hash_equals($expected, $token)) {
                return true;
            }
        }

        self::$last_error = 'invalid_token';
        return false;
    }

    /**
     * Get current user id for token context binding.
     * Returns 0 for guest users or when user resolution is unavailable.
     */
    private static function getCurrentUserId(): int {
        try {
            $user = Get::user();
            if (is_object($user) && isset($user->id) && is_numeric($user->id)) {
                return (int) $user->id;
            }
        } catch (\Throwable) {
            // Fallback to guest user context.
        }

        return 0;
    }

    /**
     * Ensures the CSRF token store exists in session.
     */
    private static function ensureCsrfStore(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::$last_error = 'session_not_active';
            return false;
        }

        if (!isset($_SESSION[self::CSRF_SESSION_STORE]) || !is_array($_SESSION[self::CSRF_SESSION_STORE])) {
            $_SESSION[self::CSRF_SESSION_STORE] = [];
        }

        return true;
    }

    /**
     * Returns true when the stored CSRF entry is expired.
     */
    private static function isCsrfEntryExpired(array $entry): bool
    {
        $issuedAt = isset($entry['issued_at']) && is_numeric($entry['issued_at'])
            ? (int) $entry['issued_at']
            : 0;

        if ($issuedAt <= 0) {
            return true;
        }

        return ($issuedAt + self::CSRF_TTL_SECONDS) < time();
    }

    /**
     * Validates token user context against current user.
     */
    private static function isCsrfUserContextValid(array $entry): bool
    {
        if (!array_key_exists('user_id', $entry) || !is_numeric($entry['user_id'])) {
            return false;
        }

        return (int) $entry['user_id'] === self::getCurrentUserId();
    }

    /**
     * Create a cryptographically secure random token.
     */
    private static function createRandomToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate CSRF token format (64 hex chars).
     */
    private static function isValidTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Generates a public/private key pair for JWT
     * 
     * @return array|false Array with the keys or false on error
     */
    public static function generateKeyPair() {
        self::$last_error = '';
        
        try {
            // Configuration for key generation
            $config = [
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ];
            
            // Create the key resource
            $res = openssl_pkey_new($config);
            if (!$res) {
                self::$last_error = "Error generating keys: " . openssl_error_string();
                Logs::set('TOKEN', self::$last_error, 'ERROR');
                return false;
            }
            
            // Extract the private key
            openssl_pkey_export($res, $private_key);
            
            // Extract the public key
            $key_details = openssl_pkey_get_details($res);
            $public_key = $key_details["key"];
            
            // Store the keys in the class for immediate use
            self::$private_key = $private_key;
            self::$public_key = $public_key;
            
            // Return the keys
            return [
                'private_key' => $private_key,
                'public_key' => $public_key
            ];
        } catch (\Exception $e) {
            self::$last_error = $e->getMessage();
            Logs::set('TOKEN', "Error generating keys: " . self::$last_error, 'ERROR');
            return false;
        }
    }
    
    /**
     * Generates a JWT token for a user
     * 
     * @param int $user_id User ID
     * @param array $additional_data Additional data to include in the token
     * @return string|false JWT token or false on error
     */
    public static function generateJwt($user_id, $additional_data = []) {
        self::$last_error = '';
        
        try {
            // Prepare the JWT payload
            $issuedAt = time();
            $expiresAt = $issuedAt + self::$jwt_expiration;
            
            $payload = [
                'iat' => $issuedAt,          // Issued At: timestamp of issuance
                'exp' => $expiresAt,         // Expires At: expiration timestamp
                'jti' => bin2hex(random_bytes(16)), // JWT ID: unique identifier for the token
                'iss' => Config::get('base_url', $_SERVER['SERVER_NAME'] ?? 'milk_api'), // Issuer: token issuer
                'user_id' => $user_id,       // User ID
            ];
            
            // Add any additional data
            if (!empty($additional_data) && is_array($additional_data)) {
                foreach ($additional_data as $key => $value) {
                    if (!isset($payload[$key])) {
                        $payload[$key] = $value;
                    }
                }
            }
            
            // Sign the token with the private key
            return self::signJwt($payload);
        } catch (\Exception $e) {
            self::$last_error = $e->getMessage();
            Logs::set('TOKEN', "Error generating token: " . self::$last_error, 'ERROR');
            return false;
        }
    }
    
  /**
     * Signs a JWT payload with a private key
     * 
     * @param array $payload Data to include in the token
     * @return string|false Signed JWT token or false on error
     */
    private static function signJwt($payload) {
        self::$last_error = '';
        
        try {
            if (self::$private_key === null) {
               self::configJwt();
            }
            // Create the header
            $header = [
                'typ' => 'JWT',
                'alg' => 'RS256'
            ];
            
            // Encode header and payload
            $header_encoded = self::base64urlEncode(json_encode($header));
            $payload_encoded = self::base64urlEncode(json_encode($payload));
            
            // Create the signature
            $signature = '';
            $data = $header_encoded . '.' . $payload_encoded;
            
            // Handle private key resource creation
            $pk = openssl_pkey_get_private(self::$private_key);
            if ($pk === false) {
                self::$last_error = "Invalid private key: " . openssl_error_string();
                Logs::set('TOKEN', "Invalid private key: " . self::$last_error, 'ERROR');
                return false;
            }
            
            // Sign the data
            $sign_result = openssl_sign($data, $signature, $pk, OPENSSL_ALGO_SHA256);
            
            if ($sign_result === false) {
                self::$last_error = "Signing failed: " . openssl_error_string();
                Logs::set('TOKEN', "Signing failed: " . self::$last_error, 'ERROR');
                return false;
            }
            
            $signature_encoded = self::base64urlEncode($signature);
            
            // Return the complete token
            return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
        } catch (\Exception $e) {
            self::$last_error = "Error signing JWT: " . $e->getMessage();
            Logs::set('TOKEN', "Error signing JWT: " . self::$last_error, 'ERROR');
            return false;
        }
    }
    
    /**
     * Verifies a JWT token
     * 
     * @param string $token JWT token to verify
     * @return array|false Decoded payload or false if the token is invalid
     */
    public static function verifyJwt($token) {
        self::$last_error = '';
        
        try {
            // Split the token into its parts
            $token_parts = explode('.', $token);
            if (count($token_parts) != 3) {
                self::$last_error = "Invalid token format";
                return false;
            }
            
            list($header_encoded, $payload_encoded, $signature_encoded) = $token_parts;
            
            // Decode the header and payload
            $header = json_decode(self::base64urlDecode($header_encoded), true);
            $payload = json_decode(self::base64urlDecode($payload_encoded), true);
            
            if (!$header || !$payload) {
                self::$last_error = "Unable to decode header or payload";
                return false;
            }
            
            // Verify that the algorithm is supported
            if ($header['alg'] !== 'RS256') {
                self::$last_error = "Unsupported algorithm: " . $header['alg'];
                return false;
            }
            
            // Verify that the token is not expired
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                self::$last_error = "Token expired";
                return false;
            }
            
            // If no public key is provided, use the configured one
            if (self::$public_key === null) {
                self::configJwt();
            }
            
            // Verify the signature
            $data = $header_encoded . '.' . $payload_encoded;
            $signature = self::base64urlDecode($signature_encoded);
            $result = openssl_verify($data, $signature, self::$public_key, OPENSSL_ALGO_SHA256);
            
            if ($result !== 1) {
                self::$last_error = "Invalid signature";
                return false;
            }
            
            // Valid token, return the payload
            return $payload;
        } catch (\Exception $e) {
            self::$last_error = $e->getMessage();
            Logs::set('TOKEN', "Error verifying token: " . self::$last_error, 'ERROR');
            return false;
        }
    }

    
    /**
     * Decodes base64url to string
     * 
     * @param string $data Base64url encoded data
     * @return string Decoded data
     */
    private static function base64urlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
    
    /**
     * Encodes a string in base64url
     * 
     * @param string $data Data to encode
     * @return string Base64url encoded data
     */
    private static function base64urlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
