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
     * @return bool
     */
    public function deleteByUserIdExceptCurrent(int $user_id): void {
        $current_session = session_id();
        $sessions = $this->where('user_id = ? AND phpsessid != ?', [$user_id, $current_session])->getResults();
        foreach ($sessions as $session) {
            $this->delete($session->id);
        }
        
    }
}
