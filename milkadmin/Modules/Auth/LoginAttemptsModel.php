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
        $params = [$identifier, $formattedDateTime];

        // Username/session counters are reset by the last successful login.
        // IP counter stays untouched and naturally decays only by time window.
        if ($type === 'username' || $type === 'session') {
            $last_success_time = $this->getLastSuccessfulLoginTime($identifier, $type);
            if ($last_success_time !== null) {
                $query .= ' AND attempt_time > ?';
                $params[] = $last_success_time;
            }
        }

        $result = $this->db->getRow($query, $params);
        return (int) ($result->count ?? 0);
    }

    /**
     * Get last successful login time for username/session if available.
     */
    private function getLastSuccessfulLoginTime(string $identifier, string $type): ?string {
        if (!$this->db || $identifier === '') {
            return null;
        }

        if ($type === 'username') {
            $row = $this->db->getRow(
                'SELECT MAX(login_time) as last_login FROM `#__access_logs` WHERE username = ?',
                [$identifier]
            );
        } else if ($type === 'session') {
            $row = $this->db->getRow(
                'SELECT MAX(login_time) as last_login FROM `#__access_logs` WHERE session_id = ?',
                [$identifier]
            );
        } else {
            return null;
        }

        $last_login = $row->last_login ?? null;
        if (!is_string($last_login) || $last_login === '') {
            return null;
        }

        return $last_login;
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
