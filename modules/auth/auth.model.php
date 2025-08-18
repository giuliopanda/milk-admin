<?php
namespace Modules\Auth;
use MilkCore\AbstractModel;

class UserModel extends AbstractModel {
    protected string $table = '#__users';
    protected string $primary_key = 'id';
    protected string $object_class = 'UserObject';
}

class SessionModel extends AbstractModel {
    protected string $table = '#__sessions';
    protected string $primary_key = 'id';
    protected string $object_class = 'SessionObject';
}

class LoginAttemptsModel extends AbstractModel {
    protected string $table = '#__login_attempts';
    protected string $primary_key = 'id';
    protected string $object_class = 'LoginAttemptsObject';
}

class AccessLogModel extends AbstractModel {
    protected string $table = '#__access_logs';
    protected string $primary_key = 'id';
    protected string $object_class = 'AccessLogObject';
    
    /**
     * Log user login
     */
    public function log_login($user_id, $session_id, $ip_address, $user_agent, $username = '') {
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
        
        return $this->save($data);
    }
    
    /**
     * Log user logout
     */
    public function log_logout($session_id) {
        $log = $this->where('session_id = ?', [$session_id])
                    ->where('logout_time IS NULL')
                    ->first();
        
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
            
            return $this->save($data, $log->id);
        }
        
        return false;
    }

    public function log_change_session($old_session_id, $session_id, $user_id) {
        $log = $this->where('session_id = ? AND user_id = ?', [$old_session_id, $user_id])
                    ->where('logout_time IS NULL')
                    ->order('id DESC')
                    ->first();
        
        if ($log) {
            $data = ['session_id' => $session_id];
            return $this->save($data, $log->id);
        }
        
        return false;
    }
    
    /**
     * Update last activity
     */
    public function update_last_activity($session_id) {
        $log = $this->where('session_id = ?', [$session_id])
                    ->where('logout_time IS NULL')
                    ->first();
        
        if ($log) {
            $data = ['last_activity' => date('Y-m-d H:i:s')];
            return $this->save($data, $log->id);
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
    public function log_page_activity($session_id, $page, $action = '') {
        $log = $this->where('session_id = ?', [$session_id])
                    ->where('logout_time IS NULL')
                    ->first();
        
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
        
        if (isset($pages_activity[$page_key])) {
            // Page already exists, update last_access and visit_count
            $pages_activity[$page_key]['last_access'] = $current_time;
            $pages_activity[$page_key]['visit_count'] = ($pages_activity[$page_key]['visit_count'] ?? 2) + 1;
        } else {
            // New page, create entry
            $pages_activity[$page_key] = [
                'first_access' => $current_time
            ];
        }
        
        // Update the log with new pages activity
        $data = [
            'pages_activity' => json_encode($pages_activity),
            'last_activity' => $current_time
        ];
        
        return $this->save($data, $log->id);
    }
}