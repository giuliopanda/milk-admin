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
     * Generate a hidden input element for the CSRF token
     * 
     * @param string $name Form name
     * @return string HTML input element
     */
    public static function input($name) {
        return '<input type="hidden" name="'.self::getTokenName($name).'" value="'.self::get($name).'">';
    }

    /**
     * Generates a CSRF token for authenticating browser calls.
     * The token is formed by encrypting a JSON array containing [timestamp, formname].
     * 
     * @param string $name Form name used to generate the token
     * @return string Encrypted token
     */
    public static function get($name) {
        return self::encrypt(json_encode([time(), self::getTokenKey($name)]));
    }

    /**
     * Returns a token key based on the form name
     * 
     * @param string $name Form name
     * @return string Token key
     */
    public static function getTokenKey($name) {
        return $name;
    }

    /**
     * Returns the name of the variable in which the token is stored.
     * Generates a unique name based on the user agent and form name.
     * 
     * @param string $name Form name
     * @return string Generated token variable name
     */
    public static function getTokenName($name) {
        $md5Name = md5($name);
        $user_agent = substr(md5(($_SERVER['HTTP_USER_AGENT'] ?? '') . $md5Name), 0, 6);
        // If the first character is a number, add a letter
        if (is_numeric(substr($user_agent, 0, 1))) {
            $user_agent = 't' . $user_agent;
        }
        return $user_agent;
    }

    /**
     * Verifies if the CSRF token is correct by finding the variable name in the request
     * and checking its value.
     * 
     * @param string $name Form name
     * @return boolean True if the token is valid, false otherwise
     */
    public static function check($name) {
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
     * @param string $token Token value to verify
     * @param string $name Form name
     * @return boolean True if the token is valid, false otherwise
     */
    public static function checkValue($token, $name) {
        self::$last_error = '';
        $time_to_expire = 86400 / 2; // 12 hours
    
        if ($token === null) {
            self::$last_error = 'invalid_token';
            return false;
        }
        $token_array = self::decrypt($token);
        if (is_array($token_array) && count($token_array) > 0) {
            if (self::getTokenKey($name) !== $token_array[1]) {
                self::$last_error = 'invalid_token';
                return false;
            }
            $time = $token_array[0];
            if ($time + $time_to_expire < time()) {
                self::$last_error = 'expired_token';
                return false;
            }
            return true;
        }
        self::$last_error = 'invalid_token';
        return false;
    }

    /**
     * Encrypts a string using AES-128-CBC cipher with HMAC authentication.
     * 
     * @param string $string String to encrypt
     * @return string Encrypted string, URL-safe base64 encoded
     */
    private static function encrypt($string) {
        if (!self::$secret_key) {
            self::$secret_key = 'DFca324';
        }
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($string, $cipher, self::$secret_key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, self::$secret_key, true);
        return Route::urlsafeB64Encode($iv . $hmac . $ciphertext_raw);
    }

    /**
     * Decrypts a string previously encrypted with the encrypt method.
     * Includes HMAC verification to ensure data integrity.
     * 
     * @param string $string Encrypted string to decrypt
     * @return array|false Decrypted data as an array, or false on failure
     */
    private static function decrypt($string) {
        self::$last_error = '';
        if (!self::$secret_key) {
            self::$secret_key = 'DFca324';
        }
        $c = Route::urlsafeB64Decode($string);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($c, $ivlen + $sha2len);
        if (strlen($iv) !== $ivlen || strlen($hmac) !== $sha2len) {
            self::$last_error = 'invalid_string';
            return false;
        }
        $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, self::$secret_key, OPENSSL_RAW_DATA, $iv);
      
        $calcmac = hash_hmac('sha256', $ciphertext_raw, self::$secret_key, true);
        if (!hash_equals($hmac, $calcmac)) { // timing attack safe comparison
            $original_plaintext = false;
        }
        if ($original_plaintext !== false) {
            $original_plaintext = json_decode($original_plaintext);
        }
        return $original_plaintext;
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
                Logs::set('token', 'ERROR', self::$last_error);
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
            Logs::set('token', 'ERROR', "Error generating keys: " . self::$last_error);
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
            Logs::set('System', 'ERROR', "Error generating token: " . self::$last_error);
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
                Logs::set('token', 'ERROR', self::$last_error);
                return false;
            }
            
            // Sign the data
            $sign_result = openssl_sign($data, $signature, $pk, OPENSSL_ALGO_SHA256);
            
            if ($sign_result === false) {
                self::$last_error = "Signing failed: " . openssl_error_string();
                Logs::set('token', 'ERROR', self::$last_error);
                return false;
            }
            
            $signature_encoded = self::base64urlEncode($signature);
            
            // Return the complete token
            return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
        } catch (\Exception $e) {
            self::$last_error = "Error signing JWT: " . $e->getMessage();
            Logs::set('token', 'ERROR', self::$last_error);
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
            Logs::set('token', 'ERROR', "Error verifying token: " . self::$last_error);
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