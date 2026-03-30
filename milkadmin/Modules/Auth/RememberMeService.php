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
     * Remember-me is available only when configured and served over HTTPS.
     */
    public static function isAvailable(): bool
    {
        if (!Config::get('auth_remember_me_duration')) {
            return false;
        }

        return self::isHttpsRequest();
    }

    private static function isHttpsRequest(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        $requestScheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
        if ($requestScheme === 'https') {
            return true;
        }

        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto !== '') {
            $first = trim(explode(',', $forwardedProto)[0]);
            if ($first === 'https') {
                return true;
            }
        }

        $baseUrlScheme = strtolower((string) parse_url((string) Config::get('base_url', ''), PHP_URL_SCHEME));
        return $baseUrlScheme === 'https';
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
        if (!self::isAvailable()) {
            return false;
        }
        $duration = Config::get('auth_remember_me_duration');

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
     * Validate and rotate a remember me token.
     *
     * Rotation strategy:
     * - Keep selector stable
     * - Rotate validator at every successful remember-me login
     * - If an old validator is replayed for the same selector, treat it as compromise
     *
     * @param string $selector Public token identifier
     * @param string $validator Secret token to verify
     * @return array|false ['user_id' => int, 'selector' => string, 'validator' => string] or false
     */
    public function validateToken(string $selector, string $validator)
    {
        // Find token by selector
        $query = "SELECT * FROM `#__remember_tokens` WHERE selector = ? LIMIT 1";
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
            // Old/invalid validator for known selector => probable token theft.
            // Invalidate all persistent logins and active sessions for that user.
            $this->invalidateUserPersistentAuth((int) ($token->user_id ?? 0), (int) ($token->id ?? 0));
            return false;
        }

        // Token is valid: rotate validator immediately.
        $rotated_validator = bin2hex(random_bytes(self::TOKEN_BYTES));
        $rotated_hash = password_hash($rotated_validator, PASSWORD_DEFAULT, ['cost' => 12]);
        if (!is_string($rotated_hash) || $rotated_hash === '') {
            $this->revokeToken((int) ($token->id ?? 0));
            return false;
        }

        $rotated = $this->rotateTokenValidator((int) ($token->id ?? 0), $rotated_hash);
        if (!$rotated) {
            // Fail closed: token can no longer be reused if rotation failed.
            $this->revokeToken((int) ($token->id ?? 0));
            return false;
        }

        return [
            'user_id' => (int) ($token->user_id ?? 0),
            'selector' => (string) ($token->selector ?? ''),
            'validator' => $rotated_validator
        ];
    }

    /**
     * Update last_used_at timestamp for a token
     *
     * @param int $token_id Token ID
     * @return bool Success
     */
    private function updateLastUsed(int $token_id): bool
    {
        $query = "UPDATE `#__remember_tokens`
                  SET last_used_at = ?
                  WHERE id = ?";
        return Get::db()->query($query, [date('Y-m-d H:i:s'), $token_id]);
    }

    /**
     * Rotate validator hash and refresh device metadata for a token.
     */
    private function rotateTokenValidator(int $token_id, string $token_hash): bool
    {
        $query = "UPDATE `#__remember_tokens`
                  SET token_hash = ?,
                      last_used_at = ?,
                      ip_address = ?,
                      user_agent = ?,
                      device_fingerprint = ?
                  WHERE id = ? AND is_revoked = 0";

        return Get::db()->query($query, [
            $token_hash,
            date('Y-m-d H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $this->generateDeviceFingerprint(),
            $token_id
        ]) !== false;
    }

    /**
     * Invalidate all persistent auth artifacts for a user after suspected token replay.
     */
    private function invalidateUserPersistentAuth(int $user_id, int $fallback_token_id): void
    {
        if ($user_id > 0) {
            $this->deleteAllUserTokens($user_id);
            Get::db()->delete('#__sessions', ['user_id' => $user_id]);
            return;
        }

        if ($fallback_token_id > 0) {
            $this->revokeToken($fallback_token_id);
        }
    }

    /**
     * Set remember me cookie
     *
     * @param string $selector
     * @param string $validator
     */
    public function setCookie(string $selector, string $validator): void
    {
        if (!self::isAvailable()) {
            return;
        }
        $duration = Config::get('auth_remember_me_duration');

        $cookieValue = $selector . ':' . $validator;
        $expires = time() + ($duration * 86400);
        $cookieName = $this->getCookieName();

        setcookie($cookieName, $cookieValue, [
            'expires' => $expires,
            'path' => $this->getCookiePath(),
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
        $cookieName = $this->getCookieName();
        if (!isset($_COOKIE[$cookieName])) {
            return false;
        }

        $parts = explode(':', $_COOKIE[$cookieName], 2);

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
        $cookieName = $this->getCookieName();
        setcookie($cookieName, '', [
            'expires' => time() - 3600,
            'path' => $this->getCookiePath(),
            'httponly' => true,
            'secure' => true,
            'samesite' => 'Strict'
        ]);
    }

    private function getCookieName(): string
    {
        $baseUrl = (string) Config::get('base_url', '');
        $defaultName = self::COOKIE_NAME . '_' . substr(hash('sha256', ($baseUrl !== '' ? $baseUrl : __DIR__)), 0, 8);
        $configured = (string) Config::get('auth_remember_me_cookie_name', $defaultName);
        $cookieName = preg_replace('/[^A-Za-z0-9_]/', '', $configured);
        return $cookieName !== '' ? $cookieName : $defaultName;
    }

    private function getCookiePath(): string
    {
        $baseUrl = (string) Config::get('base_url', '');
        $defaultPath = (string) parse_url($baseUrl, PHP_URL_PATH);
        if ($defaultPath === '') {
            $defaultPath = '/';
        }

        $cookiePath = (string) Config::get('auth_cookie_path', $defaultPath);
        $cookiePath = '/' . ltrim(trim($cookiePath), '/');
        if ($cookiePath !== '/') {
            $cookiePath = rtrim($cookiePath, '/') . '/';
        }

        return $cookiePath;
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
        $query = "UPDATE `#__remember_tokens`
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
        $query = "DELETE FROM `#__remember_tokens`
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
        $query = "DELETE FROM `#__remember_tokens`
                  WHERE expires_at < ? OR is_revoked = 1";
        $db = Get::db();
        $result = $db->query($query, [$now]);
        if ($result === false) {
            return 0;
        }
        return $db->affectedRows();

    }

    /**
     * Enforce max devices limit for a user
     *
     * @param int $user_id User ID
     */
    private function enforceMaxDevices(int $user_id): void
    {
        // Count active tokens for this user
        $query = "SELECT * FROM `#__remember_tokens`
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
        $query = "UPDATE `#__remember_tokens`
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

        $query = "DELETE FROM `#__remember_tokens`
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
        $query = "DELETE FROM `#__remember_tokens`
                  WHERE user_id = ?";

        return Get::db()->query($query, [$user_id]) !== false;
    }
}
