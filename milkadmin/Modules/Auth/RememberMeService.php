<?php

namespace Modules\Auth;

use App\{Config, Get};

/**
 * RememberMe Service
 *
 * Handles secure "remember me" token generation, validation, and rotation.
 *
 * Security features:
 * - Cryptographically secure random tokens
 * - bcrypt hashing (never stores plain tokens)
 * - Device fingerprinting
 * - Automatic cleanup of expired tokens
 */
class RememberMeService
{
    private RememberTokenModel $model;

    // Hardcoded constants
    private const COOKIE_NAME = 'mk_persist';
    private const MAX_DEVICES = 5;
    private const TOKEN_BYTES = 32; // 64 hex chars

    public function __construct()
    {
        $this->model = new RememberTokenModel();
    }

    /**
     * Generate a new remember me token
     *
     * @return array ['selector' => string, 'validator' => string]
     */
    public function generateToken(): array
    {
        return [
            'selector' => bin2hex(random_bytes(self::TOKEN_BYTES)),
            'validator' => bin2hex(random_bytes(self::TOKEN_BYTES))
        ];
    }

    /**
     * Store a new remember me token
     *
     * @param int $user_id User ID
     * @param string $selector Public token identifier
     * @param string $validator Secret token (will be hashed)
     * @return bool Success
     */
    public function storeToken(int $user_id, string $selector, string $validator): bool
    {
        $duration = Config::get('auth_remember_me_duration');
        if (!$duration) {
            return false;
        }

        // Enforce max devices limit
        $this->enforceMaxDevices($user_id);

        // Hash the validator with bcrypt
        $token_hash = password_hash($validator, PASSWORD_DEFAULT, ['cost' => 12]);

        $data = [
            'user_id' => $user_id,
            'token_hash' => $token_hash,
            'selector' => $selector,
            'device_fingerprint' => $this->generateDeviceFingerprint(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + ($duration * 86400)),
            'last_used_at' => date('Y-m-d H:i:s'),
            'is_revoked' => 0
        ];

        return $this->model->store($data) !== false;
    }

    /**
     * Validate a remember me token (NO rotation, just update last_used_at)
     *
     * @param string $selector Public token identifier
     * @param string $validator Secret token to verify
     * @return int|false User ID if valid, false otherwise
     */
    public function validateToken(string $selector, string $validator)
    {
        // Find token by selector using SQL query
        $query = "SELECT * FROM milk_remember_tokens WHERE selector = ? LIMIT 1";
        $token = Get::db()->getRow($query, [$selector]);

        if (!$token) {
            return false;
        }

        // Check if token is revoked
        if ($token->is_revoked) {
            return false;
        }

        // Check if token is expired
        if (strtotime($token->expires_at) < time()) {
            $this->deleteToken($token->id);
            return false;
        }

        // Verify validator against hash
        if (!password_verify($validator, $token->token_hash)) {
            // Invalid token - possible attack, revoke it
            $this->revokeToken($token->id);
            return false;
        }

        // Token is valid - just update last_used_at (NO rotation)
        $this->updateLastUsed($token->id);

        return (int)$token->user_id;
    }

    /**
     * Update last_used_at timestamp for a token
     *
     * @param int $token_id Token ID
     * @return bool Success
     */
    private function updateLastUsed(int $token_id): bool
    {
        $query = "UPDATE milk_remember_tokens
                  SET last_used_at = ?
                  WHERE id = ?";
        return Get::db()->query($query, [date('Y-m-d H:i:s'), $token_id]);
    }

    /**
     * Set remember me cookie
     *
     * @param string $selector
     * @param string $validator
     */
    public function setCookie(string $selector, string $validator): void
    {
        $duration = Config::get('auth_remember_me_duration');
        if (!$duration) {
            return;
        }

        $cookieValue = $selector . ':' . $validator;
        $expires = time() + ($duration * 86400);

        setcookie(self::COOKIE_NAME, $cookieValue, [
            'expires' => $expires,
            'path' => '/',
            'httponly' => true,
            'secure' => true,
            'samesite' => 'Strict'
        ]);
    }

    /**
     * Get cookie value
     *
     * @return array|false ['selector' => string, 'validator' => string] or false
     */
    public function getCookie()
    {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }

        $parts = explode(':', $_COOKIE[self::COOKIE_NAME], 2);

        if (count($parts) !== 2) {
            return false;
        }

        return [
            'selector' => $parts[0],
            'validator' => $parts[1]
        ];
    }

    /**
     * Delete remember me cookie
     */
    public function deleteCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => true,
            'samesite' => 'Strict'
        ]);
    }

    /**
     * Generate device fingerprint
     *
     * @return string SHA-256 hash of IP + User-Agent
     */
    public function generateDeviceFingerprint(): string
    {
        $data = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') .
                ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        return hash('sha256', $data);
    }

    /**
     * Revoke a specific token
     *
     * @param int $token_id Token ID
     * @return bool Success
     */
    public function revokeToken(int $token_id): bool
    {
        $query = "UPDATE milk_remember_tokens
                  SET is_revoked = 1
                  WHERE id = ?";
        return Get::db()->query($query, [$token_id]);
    }

    /**
     * Delete a specific token
     *
     * @param int $token_id Token ID
     * @return bool Success
     */
    private function deleteToken(int $token_id): bool
    {
        $query = "DELETE FROM milk_remember_tokens
                  WHERE id = ?";
        return Get::db()->query($query, [$token_id]);
    }

    /**
     * Clean up expired and revoked tokens
     *
     * @return int Number of deleted tokens
     */
    public function cleanExpiredTokens(): int
    {
        $now = date('Y-m-d H:i:s');

        // Delete expired tokens using direct database access
        $query = "DELETE FROM milk_remember_tokens
                  WHERE expires_at < ? OR is_revoked = 1";

       return Get::db()->query($query, [$now]);

    }

    /**
     * Enforce max devices limit for a user
     *
     * @param int $user_id User ID
     */
    private function enforceMaxDevices(int $user_id): void
    {
        // Count active tokens for this user
        $query = "SELECT * FROM milk_remember_tokens
                  WHERE user_id = ? AND is_revoked = 0
                  ORDER BY created_at DESC";
        $tokens = Get::db()->getResults($query, [$user_id]);

        if (count($tokens) >= self::MAX_DEVICES) {
            // Delete oldest tokens
            $tokensToDelete = array_slice($tokens, self::MAX_DEVICES - 1);

            foreach ($tokensToDelete as $token) {
                $this->deleteToken($token->id);
            }
        }
    }

    /**
     * Revoke all tokens for a user
     *
     * @param int $user_id User ID
     * @return bool Success
     */
    public function revokeAllUserTokens(int $user_id): bool
    {
        $query = "UPDATE milk_remember_tokens
                  SET is_revoked = 1
                  WHERE user_id = ?";

        return Get::db()->query($query, [$user_id]) !== false;
    }

    /**
     * Delete tokens for a specific user and device (based on fingerprint)
     * Useful for logout on current device only
     *
     * @param int $user_id User ID
     * @return bool Success
     */
    public function deleteTokensForCurrentDevice(int $user_id): bool
    {
        $fingerprint = $this->generateDeviceFingerprint();

        $query = "DELETE FROM milk_remember_tokens
                  WHERE user_id = ? AND device_fingerprint = ?";

        return Get::db()->query($query, [$user_id, $fingerprint]) !== false;
    }

    /**
     * Delete ALL tokens for a user (logout from all devices)
     *
     * @param int $user_id User ID
     * @return bool Success
     */
    public function deleteAllUserTokens(int $user_id): bool
    {
        $query = "DELETE FROM milk_remember_tokens
                  WHERE user_id = ?";

        return Get::db()->query($query, [$user_id]) !== false;
    }
}
