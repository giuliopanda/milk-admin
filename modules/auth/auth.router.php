<?php
namespace Modules\Auth;
use MilkCore\AbstractRouter;
use MilkCore\Hooks;

!defined('MILK_DIR') && die(); // Prevents direct access

/**
 * The router for auth module that manages login, user management and permissions
 */
class AuthRouter extends AbstractRouter
{
    protected function action_home() {
        AuthService::login();
    }

    protected function action_forgot_password() {
        AuthService::forgot_password();
    }
    
    protected function action_logout() {
        AuthService::logout();
    }
    
    protected function action_new_password() {
        AuthService::choose_new_password();
    }
    
    protected function action_user_list() {
        AuthService::user_list();
    }
    
    protected function action_edit_form() {
        AuthService::edit_form();
    }
    
    protected function action_save_user() {
        Hooks::run('active_custom_user_permissions');
        AuthService::save_user();
    }
    
    protected function action_delete_user() {
        $id = _absint($_REQUEST['id'] ?? 0);
        AuthService::edit_delete_user($id);
    }
    
    protected function action_login() {
        AuthService::login();
    }

    // === GESTIONE SESSIONI ===

    /**
     * Rinnova la sessione corrente
     */
    protected function action_refresh_session() {
        header('Content-Type: application/json');
    
        $success = SessionsService::refresh_session();
        
        if ($success) {
            $session_info = SessionsService::get_session_info();
            echo json_encode([
                'success' => true,
                'message' => 'Session updated successfully',
                'session_info' => $session_info
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => SessionsService::get_last_error() ?: 'Errore durante il rinnovo della sessione'
            ]);
        }
    }

    /**
     * Ottiene informazioni sulla sessione corrente
     */
    protected function action_session_info() {
        header('Content-Type: application/json');
    
        $session_info = SessionsService::get_session_info();
        
        echo json_encode([
            'success' => true,
            'session_info' => $session_info,
            'is_authenticated' => $session_info['active']
        ]);
    }
}