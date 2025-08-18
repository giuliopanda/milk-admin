<?php
namespace Modules\Auth;
use MilkCore\AbstractRouter;
use MilkCore\Hooks;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevents direct access

// Load access logs service
require_once(__DIR__ . '/access-logs.service.php');

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
    
    protected function action_profile() {
        AuthService::profile();
    }
    
    protected function action_update_profile() {
        AuthService::update_profile();
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

    /**
     * Access logs list page using ModelList
     * Displays access logs with start_date and user_id filters
     */
    protected function action_access_logs() {
        // Get access logs data from service
        $access_logs_data = AccessLogsService::get_access_logs_data();

        // Handle JSON response (AJAX)
        if (($_REQUEST['page-output'] ?? '') == 'json') {
            Get::response_json(['html' => $access_logs_data['table_html'], 'success' => true, 'msg' => '']);
        }
        
        // Render page with template
        Get::theme_page('default', __DIR__ . '/views/access-logs.php', $access_logs_data);
    }


    /**
     * Format page activity data for display in offcanvas
     * Returns formatted HTML for page activity details
     */
    protected function action_format_page_activity() {
        header('Content-Type: application/json');
        
        $pages_data = $_POST['pages_data'] ?? '';
        $result = AccessLogsService::format_page_activity($pages_data);
        
        echo json_encode($result);
    }
}