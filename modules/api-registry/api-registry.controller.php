<?php
namespace Modules\ApiRegistry;
use MilkCore\AbstractController;
use MilkCore\Get;
use MilkCore\Hooks;
use MilkCore\Theme;
use MilkCore\Token;
use MilkCore\Route;
use MilkCore\File;
use Modules\Install\Install;   

!defined('MILK_DIR') && die(); // Prevent direct access


class ApiRegistryController extends AbstractController {
    protected $page = 'api-registry';
    protected $title = 'API Registry';
    protected $access = 'authorized';
    protected $permission = ['manage' => 'Manage'];
    protected $menu_links = [
        ['url'=> '', 'name'=> 'API Registry', 'icon'=> 'bi bi-hdd-network', 'group'=> 'system', 'order'=> 85]
    ];

    /**
     * Initialize the module
     */
    public function init() {
        Theme::set('javascript', Route::url().'/modules/api-registry/assets/api-registry.js');
        Theme::set('header.breadcrumbs', 'API Registry <a class="link-action" href="'.Route::url('?page=docs&action=/modules/docs/pages/getting-started-api.page').'">Help</a>');
        parent::init();
    }

    public function bootstrap() {
        $this->model = new ApiRegistryLogModel();
        ApiRegistryServices::set_model($this->model);
        $this->router = new ApiRegistryRouter(); // Instantiate without arguments
    }

    /**
     * Initialize the module for API
     */
    public function api_init() {
        parent::api_init();
        // Hook per logging delle chiamate API
        Hooks::set('api_before_run', [$this, 'api_before_run']);

        // Hook per logging delle risposte API
        Hooks::set('api_after_run', [$this, 'api_after_run']);
    }


    public function api_before_run($array_info, $endpoint) {
        // $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $page = $endpoint['page'] ?? 'unknown';

        $auth = Get::make('auth');
        $user_id = '';
        if ($auth) {
            $user = $auth->get_user();
            $user_id = $user->id;
        }
        $request_data = [];
        if ($_POST) {
            $request_data['post'] = $_POST;
        }
        if ($_GET) {
            $request_data['get'] = $_GET;
        }
        if ($_FILES) {
            $files = [];
            foreach ($_FILES as $file) {
                $files[] = [
                    'name' => $file['name'],
                    'size' => $file['size']
                ];
            }
            $request_data['files'] = $files;
        }
       
        if ($this->model->save([
            'api_name' => $page,
            'started_at' => date('Y-m-d H:i:s'),
            'completed_at' => null,
            'caller_ip' => Get::client_ip(),
            'user_id' => $user_id,
            'response_status' => 'pending',
            'request_data' => json_encode($request_data)
        ])) {
            $array_info['table_id'] = $this->model->get_last_insert_id();
        } else {
            $array_info['table_id'] = 0;
        }

       // Logs::set('api', 'INFO', "API Request: {$method} {$page} (auth: " . ($auth ? 'yes' : 'no') . ")");
        
        return $array_info;
    }
    
    public function api_after_run($array_info, $endpoint) {
        if (isset($array_info['error'])) {
           $response_status = 'error';
           $response_data = $array_info['error'];
        } else {
            $response_status = 'completed';
            $response_data = $array_info['response'];
        }
        $auth = Get::make('auth');
        $user_id = '';
        if ($auth) {
            $user = $auth->get_user();
            $user_id = $user->id;
        }

        if ($array_info['table_id'] > 0) {
            $this->model->save([
                'id' => $array_info['table_id'],
                'completed_at' => date('Y-m-d H:i:s'),
                'user_id' => $user_id,
                'response_status' => $response_status,
                'response_data' => json_encode($response_data)
            ]);

            $this->model->cleanup_old_logs();
            $this->model->cleanup_excess_logs();
        }
    
        return $array_info;
    }

    public function install_execute($data = []) {
        $ris = Token::generate_key_pair();
        if ($ris) {
            $data['private_key'] = $ris['private_key'];
            $data['public_key'] = $ris['public_key'];
            File::put_contents(STORAGE_DIR.'/private-key.pem', $ris['private_key']);
            File::put_contents(STORAGE_DIR.'/public-key.pem', $ris['public_key']);
            $auth_data = [
                'jwt_private_key' => 'private-key.pem',
                'jwt_public_key' => 'public-key.pem',
            ];
            Install::set_config_file('API', $auth_data); 
        }
        parent::install_execute($data);
    }
   // 


}


Hooks::set('modules_loaded', function() {
    new ApiRegistryController();
});
