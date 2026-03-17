<?php
namespace Modules\Auth;

use App\{Config, Get};

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Servizio semplificato per la gestione delle sessioni utente
 */
class SessionsService {
    
    /**
     * Ultima errore verificatosi
     */
    private static $last_error = '';
    
    /**
     * Ottiene l'ultimo errore
     */
    public static function getLastError() {
        return self::$last_error;
    }
    
    /**
     * Aggiorna la sessione corrente prolungandone la durata.
     *
     * The browser keeps the same PHP session cookie here. Rotating the PHP session id
     * via AJAX would also require rotating the CSRF meta tokens exposed in the page.
     * 
     * @return bool True se l'aggiornamento è riuscito, false altrimenti
     */
    public static function refreshSession() {
        self::$last_error = '';
        
        $auth = Get::make('Auth');
        if (!$auth || !$auth->isAuthenticated()) {
            self::$last_error = 'Nessuna sessione attiva da aggiornare';
            return false;
        }
        
        $session = $auth->session ?? null;
        if (!$session || !isset($session->id)) {
            self::$last_error = 'Sessione non valida';
            return false;
        }
        $session_data = (array) $session;
        if (!isset($session_data['id'])) {
            self::$last_error = 'Sessione non valida';
            return false;
        }

        $session_row_id = _absint($session_data['id'] ?? 0);
        if ($session_row_id <= 0) {
            self::$last_error = 'Identificativo sessione non valido';
            return false;
        }
        
        $db = Get::db();
        if (!$db || !$db->checkConnection()) {
            self::$last_error = 'Connessione al database non disponibile';
            return false;
        }

        $session_model = new SessionModel();
        $current_phpsessid = (string) session_id();
        $previous_phpsessid = (string) ($session_data['phpsessid'] ?? '');
        $ip_address = Get::clientIp();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $current_time = date('Y-m-d H:i:s');

        $update_data = [
            'user_id' => _absint($session_data['user_id'] ?? 0),
            'session_date' => $current_time,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent
        ];

        if ($previous_phpsessid === '') {
            $update_data['phpsessid'] = $current_phpsessid;
        } elseif ($previous_phpsessid !== $current_phpsessid) {
            $update_data['phpsessid'] = $current_phpsessid;
            $update_data['old_phpsessid'] = $previous_phpsessid;
        }

        $update_result = $session_model->updateSessionById($session_row_id, $update_data);
        if ($update_result === false) {
            self::$last_error = 'Errore durante l\'aggiornamento della sessione: ' . ($db->last_error ?? 'Errore sconosciuto');
            return false;
        }

        $session_data['id'] = $session_row_id;
        $session_data['session_date'] = $current_time;
        $session_data['ip_address'] = $ip_address;
        $session_data['user_agent'] = $user_agent;
        $session_data['user_id'] = _absint($session_data['user_id'] ?? 0);
        if ($previous_phpsessid === '' || $previous_phpsessid !== $current_phpsessid) {
            if ($previous_phpsessid !== '') {
                $session_data['old_phpsessid'] = $previous_phpsessid;
            }
            $session_data['phpsessid'] = $current_phpsessid;
        }
        $auth->session = (object) $session_data;

        return true;
            
    }

    /**
     * Ottiene il tempo di scadenza delle sessioni in minuti
     * 
     * @return int Durata in minuti
     */
    public static function getSessionExpiry() {
        return Config::get('auth_expires_session', 20); // Default 2 minuti
    }
    
    /**
     * Ottiene il tempo rimanente della sessione in minuti
     * 
     * @return int Minuti rimanenti, 0 se scaduta
     */
    public static function getSessionRemainingTime() {
        $auth = Get::make('Auth');
        if (!$auth || !$auth->session || !isset($auth->session->session_date)) {
            return 0;
        }
        
        $session_time = new \DateTime($auth->session->session_date);
        $current_time = new \DateTime();
        $expiry_minutes = self::getSessionExpiry();
        $expiry_time = clone $session_time;
        $expiry_time->modify('+' . $expiry_minutes . ' minutes');
        
        if ($current_time >= $expiry_time) {
            return 0;
        }
        
        $diff = $current_time->diff($expiry_time);
        return ($diff->h * 60) + $diff->i;
    }
    
    /**
     * Ottiene informazioni di base sulla sessione corrente
     * 
     * @return array Array con informazioni sulla sessione
     */
    public static function getSessionInfo() {
        $auth = Get::make('Auth');
        
        if (!$auth || !$auth->session) {
            return [
                'active' => false,
                'remaining_minutes' => 0,
                'expires_at' => null
               // 'total_duration_minutes' => self::getSessionExpiry(),
            ];
        }
        
        $remaining_minutes = self::getSessionRemainingTime();
        $expires_at = null;
        $expiry_minutes = self::getSessionExpiry();
        
        if (isset($auth->session->session_date)) {
            $session_time = new \DateTime($auth->session->session_date);
            $expires_at = clone $session_time;
            $expires_at->modify('+' . $expiry_minutes . ' minutes');
        }
        
        return [
            'active' => $auth->isAuthenticated(),
            'remaining_minutes' => $remaining_minutes,
            'expires_at' => $expires_at ? $expires_at->format('Y-m-d H:i:s') : null
        //  'total_duration_minutes' => $expiry_minutes,
        ];
    }
}
