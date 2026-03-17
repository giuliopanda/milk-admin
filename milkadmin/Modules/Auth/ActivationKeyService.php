<?php
namespace Modules\Auth;

use App\Config;

/**
 * Handles activation key creation/validation logic.
 */
class ActivationKeyService
{
    private UserModel $user_model;
    private string $last_error = '';

    public function __construct(?UserModel $user_model = null)
    {
        $this->user_model = $user_model ?? new UserModel();
    }

    public function getLastError(): string
    {
        return $this->last_error;
    }

    /**
     * Create and persist activation key for a user.
     */
    public function createActivationKey(int $user_id): string
    {
        $this->last_error = '';
        $secret_key = Config::get('secret_key');

        $randomBytes = random_bytes(256) . uniqid((string) rand(), true);
        $hash = substr(md5($randomBytes), 0, 8);
        $string = json_encode([$user_id, $hash, time()]);
        if (!is_string($string)) {
            $this->last_error = 'Unable to encode activation key payload';
            return '';
        }

        $ivlen = openssl_cipher_iv_length($cipher = 'AES-128-CBC');
        if (!is_int($ivlen) || $ivlen <= 0) {
            $this->last_error = 'Unable to initialize cipher IV length';
            return '';
        }

        try {
            $iv = random_bytes($ivlen);
        } catch (\Throwable $e) {
            $this->last_error = 'Unable to generate cipher IV';
            return '';
        }

        $ciphertext_raw = openssl_encrypt($string, $cipher, $secret_key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext_raw === false) {
            $this->last_error = 'Unable to encrypt activation key';
            return '';
        }

        $hmac = hash_hmac('sha256', $ciphertext_raw, $secret_key, true);
        $result = $iv . $hmac . $ciphertext_raw;
        $result = str_replace('=', '', strtr(base64_encode($result), '+/', '-_'));

        $update_result = $this->user_model->updateActivationKey($user_id, $result);
        if ($update_result === false) {
            $this->last_error = $this->user_model->last_error;
        }

        return $result;
    }

    /**
     * Check activation key age in minutes.
     */
    public function checkExpiresActivationKey(string $activation_key, int $time_min, string $op = '<'): bool
    {
        $this->last_error = '';
        $decrypted = $this->decryptActivationKey($activation_key);

        if (is_array($decrypted) && count($decrypted) >= 3) {
            $created_time = new \DateTime();
            $created_time->setTimestamp((int) $decrypted[2]);
            $now = new \DateTime();
            $diff_minutes = ($now->getTimestamp() - $created_time->getTimestamp()) / 60;

            if ($op === '<' && $diff_minutes < $time_min) {
                return true;
            }

            if ($op === '>' && $diff_minutes > $time_min) {
                return true;
            }

            return false;
        }

        $this->last_error = 'Invalid activation key';
        return false;
    }

    /**
     * Decrypt activation key payload.
     *
     * @return array<int, mixed>|null
     */
    private function decryptActivationKey(string $activation_key): ?array
    {
        $secret_key = Config::get('secret_key');
        $decoded = \strtr($activation_key, '-_', '+/');
        $decoded = \base64_decode($decoded);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $ivlen = openssl_cipher_iv_length($cipher = 'AES-128-CBC');
        if (!is_int($ivlen) || $ivlen <= 0) {
            return null;
        }

        $iv = substr($decoded, 0, $ivlen);
        $hmac = substr($decoded, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($decoded, $ivlen + $sha2len);
        if ($iv === '' || $hmac === '' || $ciphertext_raw === '') {
            return null;
        }

        $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $secret_key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($original_plaintext)) {
            return null;
        }

        $calcmac = hash_hmac('sha256', $ciphertext_raw, $secret_key, true);
        if (!hash_equals($hmac, $calcmac)) {
            return null;
        }

        $data = json_decode($original_plaintext);
        if (is_array($data) && count($data) === 3) {
            return $data;
        }

        return null;
    }
}
