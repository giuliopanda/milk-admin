<?php
namespace Modules\Auth;

use App\Abstracts\AbstractModel;

/**
 * Stores forgot-password attempts for throttling.
 */
class PasswordResetAttemptsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__password_reset_attempts')
            ->id()
            ->string('username', 255)->nullable(false)->label('Username')
            ->string('ip_address', 64)->nullable(false)->label('IP Address')
            ->datetime('attempt_time')->nullable(false)->label('Attempt Time');
    }

    /**
     * Count recent attempts for an IP.
     */
    public function countRecentAttemptsByIp(string $ip_address, int $window_minutes): int
    {
        if (!$this->db || $ip_address === '') {
            return 0;
        }

        $result = $this->db->getRow(
            'SELECT COUNT(*) AS count FROM `#__password_reset_attempts` WHERE ip_address = ? AND attempt_time > ?',
            [$ip_address, $this->windowStart($window_minutes)]
        );

        return (int) ($result->count ?? 0);
    }

    /**
     * Count recent attempts for a username.
     */
    public function countRecentAttemptsByUsername(string $username, int $window_minutes): int
    {
        if (!$this->db || $username === '') {
            return 0;
        }

        $username = strtolower(trim($username));
        $result = $this->db->getRow(
            'SELECT COUNT(*) AS count FROM `#__password_reset_attempts` WHERE username = ? AND attempt_time > ?',
            [$username, $this->windowStart($window_minutes)]
        );

        return (int) ($result->count ?? 0);
    }

    /**
     * Log one forgot-password attempt.
     */
    public function logAttempt(string $username, string $ip_address): bool
    {
        if (!$this->db || $ip_address === '') {
            return false;
        }

        $data = [
            'username' => strtolower(trim($username)),
            'ip_address' => $ip_address,
            'attempt_time' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert($this->table, $data) !== false;
    }

    /**
     * Delete attempts older than given minutes.
     */
    public function cleanOlderThanMinutes(int $minutes): bool
    {
        if (!$this->db) {
            return false;
        }

        return $this->db->query(
            'DELETE FROM `#__password_reset_attempts` WHERE attempt_time < ?',
            [$this->windowStart(max(1, $minutes))]
        ) !== false;
    }

    private function windowStart(int $window_minutes): string
    {
        $window_minutes = max(1, $window_minutes);
        $current = new \DateTime();
        $current->modify('-' . $window_minutes . ' minutes');
        return $current->format('Y-m-d H:i:s');
    }
}
