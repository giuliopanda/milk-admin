<?php
namespace Modules\Auth;
use MilkCore\AbstractController;
use MilkCore\Hooks; 
use MilkCore\Theme;
use MilkCore\Route;
use MilkCore\Permissions;
use MilkCore\Config;
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
        parent::__construct();
    }

    /**
     * Always executed
     */
    public function hook_init() {
      
        Permissions::set('auth', ['manage' => 'Manage'], 'Users');
         // If there's no version, it means the system still needs to be installed

        if (!Permissions::check('_user.is_guest')) {
            //  horizontal menu  
            $user = Get::make('auth')->get_user();       
            Theme::set('header.links', [ 'title' => $user->username, 'order' => 20]);              
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
        Theme::set('header.breadcrumbs', 'User List <a class="link-action" href="'.Route::url('?page=docs&action=/modules/docs/pages/modules-auth.page').'">Help</a>');

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