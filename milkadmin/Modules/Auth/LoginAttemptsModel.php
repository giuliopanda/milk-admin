<?php
namespace Modules\Auth;

use App\Abstracts\AbstractModel;

class LoginAttemptsModel extends AbstractModel {

    protected function configure($rule): void {
        $rule->table('#__login_attempts')
            ->id()
            ->string('username_email', 255)->nullable(false)->label('Username/Email')
            ->string('ip_address', 64)->nullable(false)->label('IP Address')
            ->string('session_id', 128)->nullable(false)->label('Session ID')
            ->datetime('attempt_time')->nullable(false)->label('Attempt Time');
    }

    /**
     * Count attempts for identifier in recent minutes.
     */
    public function countRecentAttempts(string $identifier, string $type, int $window_minutes): int {
        if (!$this->db) {
            return 0;
        }

        $window_minutes = max(1, $window_minutes);
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . $window_minutes . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');

        $field = match ($type) {
            'ip' => 'ip_address',
            'session' => 'session_id',
            default => 'username_email',
        };

        $query = 'SELECT COUNT(*) as count FROM `#__login_attempts` WHERE ' . $field . ' = ? AND attempt_time > ?';
        $result = $this->db->getRow($query, [$identifier, $formattedDateTime]);
        return (int) ($result->count ?? 0);
    }

    /**
     * Count all recent attempts in system in recent minutes.
     */
    public function countSystemRecentAttempts(int $window_minutes): int {
        if (!$this->db) {
            return 0;
        }

        $window_minutes = max(1, $window_minutes);
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . $window_minutes . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');

        $result = $this->db->getRow(
            'SELECT COUNT(*) as count FROM `#__login_attempts` WHERE attempt_time > ?',
            [$formattedDateTime]
        );
        return (int) ($result->count ?? 0);
    }

    /**
     * Log failed attempt.
     */
    public function logFailedAttempt(string $username, string $ip_address, string $session_id): bool {
        if (!$this->db) {
            return false;
        }

        $data = [
            'username_email' => $username,
            'ip_address' => $ip_address,
            'session_id' => $session_id,
            'attempt_time' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert($this->table, $data) !== false;
    }

    /**
     * Clear attempts for username, ip and session.
     */
    public function clearFailedAttempts(string $username, string $ip_address, string $session_id): void {
        if (!$this->db) {
            return;
        }

        $this->db->delete($this->table, ['username_email' => $username]);
        $this->db->delete($this->table, ['ip_address' => $ip_address]);
        $this->db->delete($this->table, ['session_id' => $session_id]);
    }

    /**
     * Delete attempts older than given minutes.
     */
    public function cleanOlderThanMinutes(int $minutes): bool {
        if (!$this->db) {
            return false;
        }

        $minutes = max(1, $minutes);
        $currentDateTime = new \DateTime();
        $currentDateTime->modify('-' . $minutes . ' minutes');
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');

        return $this->db->query(
            'DELETE FROM `#__login_attempts` WHERE attempt_time < ?',
            [$formattedDateTime]
        ) !== false;
    }
}
