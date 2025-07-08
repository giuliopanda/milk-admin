<?php
namespace Modules\Auth;
use MilkCore\Get;
use MilkCore\Config;

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
    public static function get_last_error() {
        return self::$last_error;
    }
    
    /**
     * Aggiorna la sessione corrente prolungandone la durata e cambiando l'ID
     * 
     * @return bool True se l'aggiornamento è riuscito, false altrimenti
     */
    public static function refresh_session() {
        self::$last_error = '';
        
        $auth = Get::make('auth');
        if (!$auth || !$auth->current_user) {
            self::$last_error = 'Nessuna sessione attiva da aggiornare';
            return false;
        }
        
        $session = $auth->session ?? null;
        if (!$session || !isset($session->id)) {
            self::$last_error = 'Sessione non valida';
            return false;
        }
        
        $db = Get::db();
        if (!$db || !$db->check_connection()) {
            self::$last_error = 'Connessione al database non disponibile';
            return false;
        }
        
        $old_session_id = $session->id;
        $current_time = date('Y-m-d H:i:s');
        
        // Step 1: Crea il nuovo record di sessione
        $insert_result = $db->update('#__sessions', [
            'phpsessid' => session_id(),
            'old_phpsessid' => $session->phpsessid,
            'user_id' => $session->user_id,
            'session_date' => $current_time,
            'ip_address' => Get::client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ], ['id' => $old_session_id]);
        
        if ($insert_result === false) {
            throw new \Exception('Errore durante la creazione della nuova sessione: ' . ($db->last_error ?? 'Errore sconosciuto'));
        }
        
        // Step 4: Aggiorna l'oggetto sessione in memoria
        $session->id = session_id();
        $session->session_date = $current_time;
        $auth->session = $session;
        // Conferma la transazione

        return true;
            
    }
    
    
    /**
     * Ottiene il tempo di scadenza delle sessioni in minuti
     * 
     * @return int Durata in minuti
     */
    public static function get_session_expiry() {
        return Config::get('auth_expires_session', 20); // Default 2 minuti
    }
    
    /**
     * Ottiene il tempo rimanente della sessione in minuti
     * 
     * @return int Minuti rimanenti, 0 se scaduta
     */
    public static function get_session_remaining_time() {
        $auth = Get::make('auth');
        if (!$auth || !$auth->session || !isset($auth->session->session_date)) {
            return 0;
        }
        
        $session_time = new \DateTime($auth->session->session_date);
        $current_time = new \DateTime();
        $expiry_minutes = self::get_session_expiry();
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
    public static function get_session_info() {
        $auth = Get::make('auth');
        
        if (!$auth || !$auth->session) {
            return [
                'active' => false,
                'remaining_minutes' => 0,
                'expires_at' => null
               // 'total_duration_minutes' => self::get_session_expiry(),
            ];
        }
        
        $remaining_minutes = self::get_session_remaining_time();
        $expires_at = null;
        $expiry_minutes = self::get_session_expiry();
        
        if (isset($auth->session->session_date)) {
            $session_time = new \DateTime($auth->session->session_date);
            $expires_at = clone $session_time;
            $expires_at->modify('+' . $expiry_minutes . ' minutes');
        }
        
        return [
            'active' => $auth->is_authenticated(),
            'remaining_minutes' => $remaining_minutes,
            'expires_at' => $expires_at ? $expires_at->format('Y-m-d H:i:s') : null
        //  'total_duration_minutes' => $expiry_minutes,
        ];
    }
}