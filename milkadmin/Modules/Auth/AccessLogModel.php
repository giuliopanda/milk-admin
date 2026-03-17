<?php
namespace Modules\Auth;

use App\Abstracts\AbstractModel;

class AccessLogModel extends AbstractModel {

    protected function configure($rule): void {
        $rule->table('#__access_logs')
            ->id()
            ->int('user_id')->nullable(false)->label('User ID')
            ->string('username', 128)->nullable(false)->label('Username')
            ->string('session_id', 128)->nullable(false)->label('Session ID')
            ->datetime('login_time')->nullable(false)->label('Login')
            ->datetime('logout_time')->nullable()->label('Logout')
            ->string('ip_address', 64)->nullable(false)->label('IP Address')
            ->string('user_agent', 512)->nullable()->label('User Agent')
            ->array('pages_activity')->nullable()->label('Pages activity')
            ->datetime('last_activity')->nullable()->label('Last activity')
            ->int('session_duration')->nullable()->label('Session Duration (seconds)')->hide();
    }
    
    /**
     * Log user login
     */
    public function logLogin($user_id, $session_id, $ip_address, $user_agent, $username = '') {
        $data = [
            'user_id' => $user_id,
            'username' => $username,
            'session_id' => $session_id,
            'login_time' => date('Y-m-d H:i:s'),
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'last_activity' => date('Y-m-d H:i:s'),
            'pages_activity' => '{}'
        ];
        
        return $this->store($data);
    }
    
    /**
     * Log user logout
     */
    public function logLogout($session_id) {
        $log = $this->where('session_id = ?', [$session_id])
                    ->where('logout_time IS NULL')
                    ->getRow();

        if (!$log || !is_object($log)) {
            return false;
        }

        $log_id = (int) ($log['id'] ?? 0);
        if ($log_id <= 0) {
            return false;
        }

        $login_time = $log['login_time'] ?? null;
        if ($login_time instanceof \DateTimeInterface) {
            $logout_time = new \DateTime();
            $logout_time_string = $logout_time->format('Y-m-d H:i:s');
            $duration = $logout_time->getTimestamp() - $login_time->getTimestamp();
        } else {
            $logout_time_string = '-';
            $duration = '-';
        }
        $data = [
            'logout_time' => $logout_time_string,
            'session_duration' => $duration
        ];

        return $this->store($data, $log_id);
    }

    public function logChangeSession($old_session_id, $session_id, $user_id) {
        $log = $this->where('session_id = ? AND user_id = ?', [$old_session_id, $user_id])
                    ->where('logout_time IS NULL')
                    ->order('id DESC')
                    ->getRow();

        if (!$log || !is_object($log)) {
            return false;
        }
        $log_id = (int) ($log['id'] ?? 0);
        if ($log_id <= 0) {
            return false;
        }

        $data = ['session_id' => $session_id];
        return $this->store($data, $log_id);
    }
    
    /**
     * Update last activity
     */
    public function updateLastActivity($session_id) {
        $log = $this->where('session_id = ?', [$session_id])
                    ->where('logout_time IS NULL')
                    ->getRow();

        if (!$log || !is_object($log)) {
            return false;
        }
        $log_id = (int) ($log['id'] ?? 0);
        if ($log_id <= 0) {
            return false;
        }

        $data = ['last_activity' => date('Y-m-d H:i:s')];
        return $this->store($data, $log_id);
    }

    /**
     * Log page activity for current session
     * 
     * @param string $session_id PHP session ID
     * @param string $page Page name (will be trimmed and lowercased)
     * @param string $action Action name (optional, will be trimmed and lowercased)
     * @return bool True if logged successfully, false otherwise
     */
    public function logPageActivity($session_id, $page, $action = '') {
        $log = $this->where('session_id = ?', [$session_id])
                    ->where('logout_time IS NULL')
                    ->getRow();

        if (!$log || !is_object($log)) {
            return false;
        }
        $log_id = (int) ($log->id ?? 0);
        if ($log_id <= 0) {
            return false;
        }
        
        // Clean and prepare page/action
        $page = strtolower(trim($page));
        $action = strtolower(trim($action));
        
        if (empty($page)) {
            return false;
        }
         
    
        // Create page key
        $page_key = trim($page . ($action ? '/' . $action : ''));
        
        // Get current pages activity (decode from JSON)
        /** @var array<string, mixed> $pages_activity */
        $pages_activity = is_array($log->pages_activity ?? null) ? $log->pages_activity : [];
       
        // Check if we already have 500+ entries, stop logging if so
        if (count($pages_activity) >= 500) {
            return false;
        }

        $current_time = date('Y-m-d H:i:s');
        $current_time_minutes = date('Y-m-d H:i'); // Without seconds for comparison

        // Check if last_activity has changed (comparing at minute level, excluding seconds)
        $last_activity_minutes = '';
        $last_activity = $log->last_activity ?? null;
        if ($last_activity) {
            if ($last_activity instanceof \DateTimeInterface) {
                $last_activity_minutes = $last_activity->format('Y-m-d H:i');
            } elseif (is_string($last_activity)) {
                $last_activity_minutes = date('Y-m-d H:i', strtotime((string) $last_activity));
            }
        }

        $minute_changed = $last_activity_minutes !== $current_time_minutes;
        $is_new_page = !array_key_exists($page_key, $pages_activity);

        // Only update if it's a new page OR minute has changed
        if ($is_new_page || $minute_changed) {
            // Update pages_activity
            if ($is_new_page) {
                // New page, create entry
                $pages_activity[$page_key] = [
                    'first_access' => $current_time,
                    'visit_count' => 1,
                ];
            } else {
                // Page already exists and minute changed, update last_access and visit_count
                $page_activity = $pages_activity[$page_key];
                if (!is_array($page_activity)) {
                    $page_activity = [];
                }
                $page_activity['last_access'] = $current_time;
                $page_activity['visit_count'] = ((int) ($page_activity['visit_count'] ?? 1)) + 1;
                $pages_activity[$page_key] = $page_activity;
            }

            // Update the log with new pages activity
            return $this->store([
                'pages_activity' => json_encode($pages_activity),
                'last_activity' => $current_time
            ], $log_id);
        }

        // No update needed (same page within same minute)
        return true;
    }
}   
