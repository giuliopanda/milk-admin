<?php
namespace Modules\Auth;

use App\Abstracts\AbstractModel;

class SessionModel extends AbstractModel {

    protected function configure($rule): void {
        $rule->table('#__sessions')
            ->id()
            ->string('phpsessid', 64)->nullable(false)->label('PHP Session ID')
            ->string('old_phpsessid', 64)->nullable()->label('Old PHP Session ID')
            ->string('ip_address', 64)->nullable(false)->label('IP Address')
            ->string('user_agent', 255)->nullable()->label('User Agent')
            ->datetime('session_date')->nullable(false)->label('Session Date')
            ->int('user_id')->nullable(false)->label('User ID')
            ->string('secret_key', 64)->nullable(false)->label('Secret Key')

            // Virtual fields (not stored in database)
            ->string('username', 255)->excludeFromDatabase()->hideFromEdit()->label('Username')
            ->string('email', 255)->excludeFromDatabase()->hideFromEdit()->label('Email')
            ->int('status')->excludeFromDatabase()->hideFromEdit()->label('Status')
            ->int('is_admin')->excludeFromDatabase()->hideFromEdit()->label('Is Admin')
            ->array('permissions')->excludeFromDatabase()->hideFromEdit()->label('Permissions')
            ->int('is_guest')->excludeFromDatabase()->hideFromEdit()->label('Is Guest');
    }

    /**
     * Delete session by PHP session ID
     * @param string $phpsessid
     * @return bool
     */
    public function deleteByPhpSessionId(string $phpsessid): bool {
        return $this->db->delete($this->table, ['phpsessid' => $phpsessid]);
    }

    /**
     * Delete sessions by user ID except current session
     * @param int $user_id
     * @return void
     */
    public function deleteByUserIdExceptCurrent(int $user_id): void {
        $current_session = session_id();
        $sessions = $this->where('user_id = ? AND phpsessid != ?', [$user_id, $current_session])->getResults();
        foreach ($sessions as $session) {
            $this->delete($session->id);
        }
        
    }

    /**
     * Find active session by current or old PHP session id and IP.
     */
    public function findActiveByPhpSessionAndIp(string $phpsessid, string $formattedDateTime, string $ip_address): ?object {
        if (!$this->db) {
            return null;
        }

        $query = 'SELECT * FROM `#__sessions` WHERE (phpsessid = ? OR old_phpsessid = ?) AND `session_date` > ? AND ip_address = ?';
        $session = $this->db->getRow($query, [$phpsessid, $phpsessid, $formattedDateTime, $ip_address]);

        return is_object($session) ? $session : null;
    }

    /**
     * Delete sessions by list of PHP session ids.
     *
     * @param array<int, string> $session_ids
     */
    public function deleteByPhpSessionIds(array $session_ids): void {
        if (!$this->db) {
            return;
        }

        foreach (array_unique($session_ids) as $session_id) {
            if (!is_string($session_id) || $session_id === '') {
                continue;
            }
            $this->db->delete($this->table, ['phpsessid' => $session_id]);
        }
    }

    /**
     * Insert a session row.
     *
     * @param array<string, mixed> $session_data
     * @return int|false
     */
    public function insertSession(array $session_data) {
        if (!$this->db) {
            return false;
        }
        return $this->db->insert($this->table, $session_data);
    }

    /**
     * Update session by primary id.
     *
     * @param array<string, mixed> $session_data
     */
    public function updateSessionById(int $id, array $session_data): bool {
        if (!$this->db || $id <= 0) {
            return false;
        }
        return $this->db->update($this->table, $session_data, ['id' => $id]);
    }

    /**
     * Delete sessions older than given datetime.
     */
    public function cleanOlderThan(string $formattedDateTime): bool {
        if (!$this->db) {
            return false;
        }
        return $this->db->query(
            'DELETE FROM `#__sessions` WHERE session_date < ?',
            [$formattedDateTime]
        ) !== false;
    }
}
