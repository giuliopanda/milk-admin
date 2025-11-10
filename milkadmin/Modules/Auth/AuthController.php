<?php
namespace Modules\Auth;

use App\Abstracts\AbstractController;
use App\{Hooks, Response,  Permissions};
use App\Attributes\RequestAction;

!defined('MILK_DIR') && die(); // Prevents direct access


/**
 * The router for auth module that manages login, user management and permissions
 */
class AuthController extends AbstractController
{
    #[RequestAction('home')]
    protected function actionHome() {
        AuthService::login();
    }

    #[RequestAction('forgot-password')]
    protected function actionForgotPassword() {
        AuthService::forgotPassword();
    }

    #[RequestAction('logout')]
    protected function actionLogout() {
        AuthService::logout();
    }
    
    #[RequestAction('new-password')]
    protected function actionNewPassword() {
        AuthService::chooseNewPassword();
    }
    
    #[RequestAction('user-list')]
    protected function actionUserList() {
        AuthService::userList();
    }
    
    #[RequestAction('edit-user')]
    protected function actionEditForm() {
        AuthService::editForm();
    }
    
    #[RequestAction('save-user')]
    protected function actionSaveUser() {
        Hooks::run('active_custom_user_permissions');
        AuthService::saveUser();
    }
    
    #[RequestAction('delete-user')]
    protected function actionDeleteUser() {
        $id = _absint($_REQUEST['id'] ?? 0);
        AuthService::editDeleteUser($id);
    }
    
    #[RequestAction('login')]
    protected function actionLogin() {
        AuthService::login();
    }
    
    #[RequestAction('profile')]
    protected function actionProfile() {
        AuthService::profile();
    }

    #[RequestAction('update-profile')]
    protected function actionUpdateProfile() {
        AuthService::updateProfile();
    }

    // === GESTIONE SESSIONI ===

    /**
     * Rinnova la sessione corrente
     */
    #[RequestAction('refresh-session')]
    protected function actionRefreshSession() {
        header('Content-Type: application/json');
    
        $success = SessionsService::refreshSession();
        
        if ($success) {
            $session_info = SessionsService::getSessionInfo();
            echo json_encode([
                'success' => true,
                'message' => 'Session updated successfully',
                'session_info' => $session_info
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => SessionsService::getLastError() ?: 'Errore durante il rinnovo della sessione'
            ]);
        }
    }

    /**
     * Ottiene informazioni sulla sessione corrente
     */
    #[RequestAction('session-info')]
    protected function actionSessionInfo() {
        header('Content-Type: application/json');
    
        $session_info = SessionsService::getSessionInfo();
        
        echo json_encode([
            'success' => true,
            'session_info' => $session_info,
            'is_authenticated' => $session_info['active']
        ]);
    }

    /**
     * Access logs list page using TableBuilder and SearchBuilder
     * Displays access logs with start_date, end_date and user_id filters
     */
    #[RequestAction('access-logs')]
    protected function actionAccessLogs() {
        // Check permissions first
        if (!Permissions::check('auth.manage')) Response::denyAccess();
    

        // Get users for filter dropdown
        $users_options = AccessLogsService::getUsersOptions();

        // Create table builder
        $tableBuilder = AccessLogsService::getAccessLogsTableBuilder();
        $table_id = $tableBuilder->getTableId();

        // Create search builder
        $searchBuilder = AccessLogsService::getSearchBuilder($table_id, $users_options);
        $search_html = $searchBuilder->render([], true);

        // Get response from table builder
        $response = $tableBuilder->getResponse();
        $response['table_id'] = $table_id;

        // Handle AJAX requests
        if (($_REQUEST['page-output'] ?? '') == 'json') {
            Response::json($response);
            return;
        }

        // Create title builder with search on the right
        $titleBuilder = AccessLogsService::getTitleBuilder($search_html);
        $response['title_html'] = $titleBuilder->render();

        // Get active users data for the summary table
        $response['active_users_data'] = AccessLogsService::getActiveUsersData();

        // Render page with template
        Response::themePage('default', __DIR__ . '/Views/access-logs.php', $response);
    }

    /**
     * Logout user from all devices (revoke all tokens and delete all sessions)
     * Returns JSON response for AJAX calls with data-fetch
     */
    #[RequestAction('logout-all-devices')]
    protected function actionLogoutAllDevices() {
        header('Content-Type: application/json');

        // Check permissions first
        if (!\App\Permissions::check('auth.manage')) {
            echo json_encode([
                'success' => false,
                'msg' => 'Permission denied'
            ]);
            return;
        }

        // Get user_id from REQUEST (works with both GET and POST from data-fetch)
        $user_id = _absint($_REQUEST['user_id'] ?? 0);

        if (empty($user_id)) {
            echo json_encode([
                'success' => false,
                'msg' => 'Invalid user ID'
            ]);
            return;
        }

        try {
            // Delete all sessions for the user using direct database query
            $deleted_sessions = \App\Get::db()->delete('#__sessions', ['user_id' => $user_id]);

            // Revoke and delete all remember me tokens for the user
            $rememberService = new RememberMeService();
            $rememberService->deleteAllUserTokens($user_id);

            echo json_encode([
                'success' => true,
                'msg' => 'User logged out from all devices successfully',
                'element' => [
                    'selector' => '#user-row-' . $user_id,
                    'action' => 'remove'
                ]
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'msg' => 'Error logging out user: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Format page activity data for display in offcanvas
     * Returns formatted HTML for page activity details
     */
     #[RequestAction('format-page-activity')]
    protected function actionFormatPageActivity() {
        $model = new AccessLogModel();
        $id = _absint($_POST['id'] ?? 0);
       
        $row = $model->getById($id);
        if ($row->isEmpty()) {
            echo json_encode(['success'=>false, 'msg' => 'Log not found']);
            return;
        }
        $pages_data = $row->pages_activity;
        
        $ris = AccessLogsService::formatPageActivity($pages_data);
        $ris = ["offcanvas_end" => [         // 'xl', 'l', or default
            "action" => "show",
            "title" => "Edit Item",
            "body" => $ris['html']
        ]];
        if (isset($ris['html'])) {
            unset($ris['html']);
        }

        Response::json($ris);
    }
}