<?php
namespace Modules\Auth;

use App\Abstracts\AbstractModule;
use App\{Cli, Get, Hooks, Permissions, Route, Theme, Config};

use Builders\LinksBuilder;


!defined('MILK_DIR') && die(); // Avoid direct access


/**
 * Authentication module
 * Manages user authentication, permission settings, and administrative functions
 * 
 * @package     Modules
 * @subpackage  Auth
 * 
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @version     1.0.0
 */
class AuthModule extends AbstractModule
{
    protected $access = 'public'; // because it includes login and password reset
    protected $page = 'auth';
    protected $title = 'Authentication';
    protected $is_core_module = true; // Enable CLI commands for admin recovery
    protected $model = 'UserModel';
  
    public function __construct() {
        // first Time used to set permissions
        /**
         * If the deny page is displayed (which means you don't have permission to view that page)
         * and you're not logged in, it redirects to the login page
         */
        Hooks::set('route_before_run', function($page) {
            if ($page == 'deny') {
                $user = Get::make('Auth')->getUser();
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
        if (Cli::isCli()) {
            Cli::set('create-administrator', [$this, 'systemShellCreateAdministrator']);
        }

        Hooks::set('api-init', function() {
            require_once __DIR__ . '/authApi.php';
        });

        // registrer log_page_activity
        Hooks::set('end-page', [$this, 'logPageActivity']);
        parent::__construct();
    }

    /**
     * Always executed
     */
    public function hookInit() {
        // only admin can manage users access permissions
        if (Permissions::check('_user.is_admin')) {
            Permissions::set('auth', ['manage' => 'Manage'], 'Users');
        }
         // If there's no version, it means the system still needs to be installed

         $links = LinksBuilder::fill();
        if (!Permissions::check('_user.is_guest')) {
            //  horizontal menu  
            $user = Get::make('Auth');
            if ($user) {
                $user->getUser();     
                $links->add('Profile', '?page=auth&action=profile')->icon('bi bi-person-circle')
                ->add('Logout', '?page=auth&action=logout')->icon('bi bi-box-arrow-right');
            }
        } 
        if (Permissions::check('auth.manage')) {
            // The user is an administrator
            Theme::set('sidebar.links',
                ['url'=> '?page=auth&action=user-list', 'title'=> 'Users', 'icon'=> 'bi bi-people-fill', 'order'=> 20]
            );
         
        }
        Theme::set('header.top-right', $links->render('navbar'));

    }

    public function init() {   
        Theme::set('javascript', Route::url().'/Modules/Auth/Assets/auth.js');
        
        // Get current action to determine active breadcrumb
        $current_action = $_REQUEST['action'] ?? 'user-list';
        
        // Build breadcrumb based on current action
        $links = LinksBuilder::fill()
            ->add('User list', '?page=auth&action=user-list')->icon('bi bi-people-fill')
            ->add('Access logs', '?page=auth&action=access-logs')->icon('bi bi-lock-fill')
            ->add('Help', '?page=docs&action=User/Administration/user-management-guide')->icon('bi bi-question-circle-fill');
           
        
        Theme::set('header.top-left', $links->render());

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
        $this->controller = new AuthController();
    }

    
    public function logPageActivity() {
      
        if (Permissions::check('_user.is_authenticated')) {
            // Skip logging for fetch requests (AJAX/XHR)
            if ($this->isFetchRequest()) {
                return;
            }
            
            // Skip logging if response content-type is JSON
            if ($this->isJsonResponse()) {
                return;
            }
           
            $page = $_REQUEST['page'] ?? '';
            $action = $_REQUEST['action'] ?? '';
            $session_id = session_id();
            $accessLogModel = new AccessLogModel();
            $accessLogModel->logPageActivity($session_id, $page, $action);
        }
    }
    /**
     * Check if current request is a fetch/AJAX request
     * 
     * @return bool True if it's a fetch request, false otherwise
     */
    private function isFetchRequest() {
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
    private function isJsonResponse() {
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
     * Usage: php milkadmin/cli.php create-administrator [username] [email]
     * 
     * This function is used for admin recovery when access is lost
     */
    public function systemShellCreateAdministrator($username = null, $email = null) {
        if (!Cli::isCli()) {
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

        // Check if username already exists
        $existing_user = Get::db()->getRow('SELECT id FROM `#__users` WHERE username = ?', [$username]);
        if ($existing_user) {
            Cli::error("Username '$username' already exists. Please choose a different username.");
            Cli::echo("Usage: php milkadmin/cli.php create-administrator [username] [email]");
            return false;
        }

        // Create the administrator user
        $user_id = Get::make('Auth')->saveUser(0, $username, $email, $password, 1, 1);
        
        if ($user_id === false || $user_id == 0) {
            $error = Get::make('Auth')->getLastError();
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

// carico la classe contract
Hooks::set('modules_loaded', function() {
    if (Cli::isCli()) return;
    $auth = Get::make('Auth');

    if (Config::get('version') != null) { 
       
        if ($auth) {
            $user = $auth->getUser();
            if (!$user->is_guest) {
                Theme::set('javascript', Route::url().'/Modules/Auth/Assets/sessions.js');
            }
        }
    }
}, 9);