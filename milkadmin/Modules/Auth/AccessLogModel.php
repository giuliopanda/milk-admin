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
        
        if ($log) {
        
            if (is_a($log->login_time, \DateTime::class)) {
                $logout_time = new \DateTime();
                $logout_time_string = $logout_time->format('Y-m-d H:i:s');
                $duration = $logout_time->getTimestamp() - $log->login_time->getTimestamp();
            } else {
                $logout_time_string = '-';
                $duration = '-';
            }
            $data = [
                'logout_time' => $logout_time_string,
                'session_duration' => $duration
            ];
            
            return $this->store($data, $log->id);
        }
        
        return false;
    }

    public function logChangeSession($old_session_id, $session_id, $user_id) {
        $log = $this->where('session_id = ? AND user_id = ?', [$old_session_id, $user_id])
                    ->where('logout_time IS NULL')
                    ->order('id DESC')
                    ->getRow();
        
        if ($log) {
            $data = ['session_id' => $session_id];
            return $this->store($data, $log->id);
        }
        
        return false;
    }
    
    /**
     * Update last activity
     */
    public function updateLastActivity($session_id) {
        $log = $this->where('session_id = ?', [$session_id])
                    ->where('logout_time IS NULL')
                    ->getRow();
        
        if ($log) {
            $data = ['last_activity' => date('Y-m-d H:i:s')];
            return $this->store($data, $log->id);
        }
        
        return false;
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
       
        if (!$log) {
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
        $pages_activity = [];
        if (!empty($log->pages_activity)) {
            if (is_array($log->pages_activity)) {
                $pages_activity = $log->pages_activity;
            } else {
                $decoded = json_decode($log->pages_activity, true);
                if (is_array($decoded)) {
                    $pages_activity = $decoded;
                }
            }
        }
       
        // Check if we already have 500+ entries, stop logging if so
        if (count($pages_activity) >= 500) {
            return false;
        }

        $current_time = date('Y-m-d H:i:s');
        $current_time_minutes = date('Y-m-d H:i'); // Without seconds for comparison

        // Check if last_activity has changed (comparing at minute level, excluding seconds)
        $last_activity_minutes = '';
        if ($log->last_activity) {
            if (is_a($log->last_activity, \DateTime::class)) {
                $last_activity_minutes = $log->last_activity->format('Y-m-d H:i');
            } else {
                $last_activity_minutes = date('Y-m-d H:i', strtotime($log->last_activity));
            }
        }

        $minute_changed = $last_activity_minutes !== $current_time_minutes;
        $is_new_page = !isset($pages_activity[$page_key]);

        // Only update if it's a new page OR minute has changed
        if ($is_new_page || $minute_changed) {
            // Update pages_activity
            if ($is_new_page) {
                // New page, create entry
                $pages_activity[$page_key] = [
                    'first_access' => $current_time
                ];
            } else {
                // Page already exists and minute changed, update last_access and visit_count
                $pages_activity[$page_key]['last_access'] = $current_time;
                $pages_activity[$page_key]['visit_count'] = ($pages_activity[$page_key]['visit_count'] ?? 1) + 1;
            }

            // Update the log with new pages activity
            $log->pages_activity = json_encode($pages_activity);
            $log->last_activity = $current_time;

            // Use save(false, false) to avoid unnecessary reload after UPDATE
            // No cascade needed and no auto-generated fields in this table
            return $log->save(false, false);
        }

        // No update needed (same page within same minute)
        return true;
    }
}   