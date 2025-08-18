<?php
namespace Modules\Auth;
use MilkCore\AbstractController;
use MilkCore\Hooks; 
use MilkCore\Theme;
use MilkCore\Route;
use MilkCore\Permissions;
use MilkCore\Cli;
use MilkCore\Get;
use MilkCore\MessagesHandler;
use Modules\Install\Install;



!defined('MILK_DIR') && die(); // Avoid direct access

require_once(__DIR__ ."/auth.api.php");
/**
 * Authentication controller
 * Manages user authentication, permission settings, and administrative functions
 * 
 * @package     Modules
 * @subpackage  Auth
 * 
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @link        https://github.com/giuliopanda/milk-core
 * @version     1.0.0
 */
class AuthController extends AbstractController
{
    protected $access = 'public'; // because it includes login and password reset
    protected $page = 'auth';
    protected $title = 'Authentication';
    protected $disable_cli = true; // Enable CLI commands for admin recovery
  
    public function __construct() {
        /**
         * If the deny page is displayed (which means you don't have permission to view that page)
         * and you're not logged in, it redirects to the login page
         */
        Hooks::set('route_before_run', function($page) {
            if ($page == 'deny') {
                $user = Get::make('auth')->get_user();
                if ($user->is_guest) {
                    $redirect = ['page'=>'auth', 'action'=>'login'];
                    if (isset($_REQUEST['redirect'])) {
                        $redirect['redirect'] = $_REQUEST['redirect'];
                    } 
                    Route::redirect($redirect);
                }
            } 
            return $page;
        });
        
        // Register CLI commands
        if (Cli::is_cli()) {
            Cli::set('create-administrator', [$this, 'system_shell_create_administrator']);
        }

        // registrer log_page_activity
        Hooks::set('end-page', [$this, 'log_page_activity']);
        
        parent::__construct();
    }

    /**
     * Always executed
     */
    public function hook_init() {
        // only admin can manage users access permissions
        if (Permissions::check('_user.is_admin')) {
            Permissions::set('auth', ['manage' => 'Manage'], 'Users');
        }
         // If there's no version, it means the system still needs to be installed

        if (!Permissions::check('_user.is_guest')) {
            //  horizontal menu  
            $user = Get::make('auth')->get_user();       
            Theme::set('header.links', ['url' => Route::url('?page=auth&action=profile'), 'title' => $user->username, 'icon' => 'bi bi-person-circle', 'order' => 20]);              
            Theme::set('header.links', ['url' => Route::url('?page=auth&action=logout'), 'title' => 'Logout', 'icon' => 'bi bi-box-arrow-right', 'order' => 30]);
        } 
        if (Permissions::check('auth.manage')) {
            // The user is an administrator
            Theme::set('sidebar.links',
                ['url'=> '?page=auth&action=user-list', 'title'=> 'Users', 'icon'=> 'bi bi-people-fill', 'order'=> 20]
            );
        }

    }

    public function init() {    
        Theme::set('javascript', Route::url().'/modules/auth/assets/auth.js');
        
        // Get current action to determine active breadcrumb
        $current_action = $_REQUEST['action'] ?? 'user-list';
        
        // Build breadcrumb based on current action
        $breadcrumb_parts = [];
        
        if ($current_action === 'access-logs') {
            // When on access-logs page: User List (link) | Access Logs (text) | Help (link)
            $breadcrumb_parts[] = '<a class="link-action" href="'.Route::url('?page=auth&action=user-list').'">User List</a>';
            $breadcrumb_parts[] = 'Access Logs';
            $breadcrumb_parts[] = '<a class="link-action" href="'.Route::url('?page=docs&action=/modules/docs/pages/user-management-guide.page').'">Help</a>';
        } else {
            // When on user-list or other pages: User List (text) | Access Logs (link) | Help (link)
            $breadcrumb_parts[] = 'User List';
            $breadcrumb_parts[] = '<a class="link-action" href="'.Route::url('?page=auth&action=access-logs').'">Access Logs</a>';
            $breadcrumb_parts[] = '<a class="link-action" href="'.Route::url('?page=docs&action=/modules/docs/pages/user-management-guide.page').'">Help</a>';
        }
        
        Theme::set('header.breadcrumbs', implode(' | ', $breadcrumb_parts));

        // Setting a hook here to modify table actions for admin users
        Hooks::set('table_actions_row', function($header_options, $row, $table_id) {
            if ($table_id == 'userList') {
                if ($row->is_admin == 1 && !Permissions::check('_user.is_admin')) {
                    return [];
                }
            }
            return $header_options;
        });

        parent::init();
    }

    public function bootstrap() {
        $this->model = new UserModel();
        $this->router = new AuthRouter();
    }
    

    public function install_execute($data = []) {
        $this->install_auth();
        $user_id = Get::make('auth')->save_user(0, 'admin', 'admin@admin.com','admin', 1, 1 );
        if ($user_id == 0) {
            if (Cli::is_cli()) {
                Cli::error("Install Auth: SAVE USER ERROR: ". $this->model->get_last_error());
            } else {
                MessagesHandler::add_error("Install Auth: SAVE USER ERROR: ". $this->model->get_last_error());
            }
        }
        $auth_data = [
            'auth_expires_session' => ['value'=>'120','type'=>'number','comment' => 'Session duration in minutes']
        ];
        
        Install::set_config_file('AUTH', $auth_data);
    
        return $data;
    }

    public function install_update($html) {
        $this->install_auth();
        return $html;
    }


    private function install_auth() {
        if (!$this->model->build_table()) {
            if (Cli::is_cli()) {
                Cli::error($this->model->get_last_error());
            } else {
                MessagesHandler::add_error("Install Auth: SAVE TABLE ERROR: ".$this->model->get_last_error());
            }
        }
        $model2 = new SessionModel();
        if (!$model2->build_table()) {
            if (Cli::is_cli()) {
                Cli::error($model2->get_last_error());
            } else {
                MessagesHandler::add_error("Install Auth: SAVE SESSION TABLE ERROR: ". $model2->get_last_error());
            }
        }

        $model3 = new LoginAttemptsModel();
        if (!$model3->build_table()) {
            if (Cli::is_cli()) {
                Cli::error($model3->get_last_error());
            } else {
                MessagesHandler::add_error("Install Auth: SAVE ATTEMPTS TABLE ERROR: ". $model3->get_last_error());
            }
        }

        $model4 = new AccessLogModel();
        if (!$model4->build_table()) {
            if (Cli::is_cli()) {
                Cli::error($model4->get_last_error());
            } else {
                MessagesHandler::add_error("Install Auth: SAVE ACCESS LOG TABLE ERROR: ". $model4->get_last_error());
            }
        }
    }


    public function log_page_activity() {
        if (Permissions::check('_user.is_authenticated')) {
            // Skip logging for fetch requests (AJAX/XHR)
            if ($this->is_fetch_request()) {
                return;
            }
            
            // Skip logging if response content-type is JSON
            if ($this->is_json_response()) {
                return;
            }
            
            $page = $_REQUEST['page'] ?? '';
            $action = $_REQUEST['action'] ?? '';
            $session_id = session_id();
            $accessLogModel = new AccessLogModel();
            $accessLogModel->log_page_activity($session_id, $page, $action);
        }
    }
    
    /**
     * Check if current request is a fetch/AJAX request
     * 
     * @return bool True if it's a fetch request, false otherwise
     */
    private function is_fetch_request() {
        // Check for common fetch/AJAX indicators
        $headers_to_check = [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_X_FETCH_REQUEST' => '1',
            'HTTP_FETCH' => '1'
        ];
        
        foreach ($headers_to_check as $header => $expected_value) {
            if (isset($_SERVER[$header]) && $_SERVER[$header] === $expected_value) {
                return true;
            }
        }
        
        // Check for fetch-specific headers
        if (isset($_SERVER['HTTP_SEC_FETCH_MODE']) && $_SERVER['HTTP_SEC_FETCH_MODE'] === 'cors') {
            return true;
        }
        
        // Check if request accepts only JSON
        $accept_header = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept_header, 'application/json') !== false && 
            strpos($accept_header, 'text/html') === false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if response content-type is JSON
     * 
     * @return bool True if response is JSON, false otherwise
     */
    private function is_json_response() {
        // Check if Content-Type header has been set to JSON
        $headers = headers_list();
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0 && 
                stripos($header, 'application/json') !== false) {
                return true;
            }
        }
        
        // Check for common JSON response indicators in request
        if (isset($_REQUEST['page-output']) && $_REQUEST['page-output'] === 'json') {
            return true;
        }
        
        if (isset($_REQUEST['format']) && $_REQUEST['format'] === 'json') {
            return true;
        }
        
        return false;
    }

    /**
     * CLI function to create an administrator user
     * Usage: php cli.php create-administrator [username] [email]
     * 
     * This function is used for admin recovery when access is lost
     */
    public function system_shell_create_administrator($username = null, $email = null) {
        if (!Cli::is_cli()) {
            Cli::error("This function can only be executed from command line");
            return false;
        }

        // Generate default values if not provided
        if (empty($username)) {
            $username = 'emergency_admin_' . date('Ymd_His');
        }
        
        if (empty($email)) {
            $email = 'admin@' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost.com');
        }

        // Generate a secure random password
        $password = $this->generateSecurePassword();

        Cli::echo("Creating emergency administrator user...");
        Cli::echo("Username: $username");
        Cli::echo("Email: $email");
        Cli::echo("Generating secure password...");

        // Check if username already exists
        $existing_user = Get::db()->get_row('SELECT id FROM `#__users` WHERE username = ?', [$username]);
        if ($existing_user) {
            Cli::error("Username '$username' already exists. Please choose a different username.");
            Cli::echo("Usage: php cli.php create-administrator [username] [email]");
            return false;
        }

        // Create the administrator user
        $user_id = Get::make('auth')->save_user(0, $username, $email, $password, 1, 1);
        
        if ($user_id === false || $user_id == 0) {
            $error = Get::make('auth')->get_last_error();
            Cli::error("Failed to create administrator user: " . ($error ?: 'Unknown error'));
            return false;
        }

        Cli::success("Administrator user created successfully!");
        Cli::echo("");
        Cli::echo("=== ADMINISTRATOR CREDENTIALS ===");
        Cli::echo("Username: $username");
        Cli::echo("Password: $password");
        Cli::echo("Email: $email");
        Cli::echo("User ID: $user_id");
        Cli::echo("=================================");
        Cli::echo("");
        Cli::echo("IMPORTANT: Save these credentials securely!");
        Cli::echo("The password will not be displayed again.");
        Cli::echo("You can now log in to the system using these credentials.");
        
        return true;
    }

    /**
     * Generate a secure random password
     * 
     * @return string Secure random password
     */
    private function generateSecurePassword() {
        $length = 12;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    

}

Get::bind('auth', Auth::class);


// carico la classe contract
Hooks::set('modules_loaded', function() {
    // questo deve essere caricato in tutte le pagine!
   
    new AuthController();
    if (Cli::is_cli()) return;
    // inizializzo il contract
    Get::make('auth');
    $user = Get::make('auth')->get_user();
    if (!$user->is_guest) {
        Theme::set('javascript', Route::url().'/modules/auth/assets/sessions.js');
    }
}, 10);