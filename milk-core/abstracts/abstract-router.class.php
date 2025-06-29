<?php
namespace MilkCore;
use MilkCore\Route;
use MilkCore\Get;
use MilkCore\ModelList;
!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Abstract Router class for handling basic routing functionality
 *
 * @package     MilkCore
 * @subpackage  Abstracts
 */

abstract class AbstractRouter {
    
    var $page = null;
    var $access = 'registered';
    var $title = null;
    var $model = null;
    var $controller = null; 
    /**
     * Constructor - sets up routing
     */
    public function __construct() {
        Hooks::set('init', [$this, 'hook_init']);
    }

    /**
     *Called when after the framework has been initialized and modules loaded
* Always called unlike init
*/
    public function hook_init() {
        Route::set($this->page, [$this, 'handle_routes']);
    }

    /**
     * Set variables for routing from controller
     */
    public function set_handle_routes($controller): void { 
        $this->controller = $controller; 
        $this->page = $controller->get_page();
        $this->title = $controller->get_title();
        $this->model = $controller->get_model();
       
    }

    /**
     * Main route handler - determines which action to take based on the request
     * 
     * This method automatically calls the appropriate action method based on the 'action' URL parameter.
     * For example, if the URL is '?page=example&action=view', it will call the method 'action_view()'.
     * 
     * IMPORTANT: 
     * - Action methods are automatically called based on the URL parameter 'action'
     * - Hyphens in the action name are automatically converted to underscores
     *   (e.g., 'action=user-profile' will call the method 'action_user_profile()')
     * - The action name is sanitized to only include lowercase letters, numbers, and underscores
     * - If no action is specified, 'action_home()' is called by default
     * - If the specified action method doesn't exist, it redirects to the 404 page
     * 
     * @return void
     */
    public function handle_routes() {
        
        if (!$this->access()) {
            $queryString = Route::get_query_string();
            Route::redirect('?page=deny');
            return;
        }
        Theme::set('header.title', Theme::get('site.title')." - ". $this->title);

        // Call the appropriate method based on action
        $action = $_REQUEST['action'] ?? null;
        if (isset($action) && !empty($action)) {
            // Sanitize action name: convert to lowercase, replace hyphens with underscores,
            // and remove any characters that aren't alphanumeric or underscores
            $action = strtolower(_raz(str_replace("-","_", $action)));
            $function = 'action_' . $action;
            
            // Call the action method if it exists
            if (method_exists($this, $function)) {
                $this->$function();
            } else {
                 Route::redirect('?page=404');
                return;
            }
        } else {
            // No action specified, call the default home action
            $this->action_home();
        }
    }

    protected function action_home() {
        Get::theme_page('default', '<h1>'.$this->title.'</h1>');
    }

    /**
     * Check if you have permission to access the page
     */
    protected function access() {
        return $this->controller->access();
    }

    /**
     * Output the response based on the requested format.
     *
     * @param string $theme_path Relative path to the theme, e.g., '/views/list.page.php'
     * @param string $outputType Output type (html or json).
     * @param array $model_list_data Data to display. ['info' => $info, 'rows' => $rows, 'page_info' => $page_info]
     * @return void
     */
    protected function output_table_response(string $theme_path, $model_list_data, ?string $outputType = null): void
    {
        // @Todo permettere id passare parametri aggiuntivi
        
        if (!$outputType) {
            $outputType = $_REQUEST['page-output'] ?? '';
        }
       
        $table_html = Get::theme_plugin('table', $model_list_data); 
        
        $table_id = $model_list_data['page_info']['id'];
        $theme_path = realpath($theme_path);
       
       

        if ($outputType === 'json') {
            $response = [
                'html' => $table_html,
                'success' => !MessagesHandler::has_errors(),
                'msg' => MessagesHandler::errors_to_string()
            ];
            Get::response_json($response);
        } else {
            Get::theme_page('default',  $theme_path, [
                'table_html' => $table_html,
                'table_id' => $table_id,
                'page' => $this->page
            ]);
        }
    }

    protected function default_request_params() {
        return   ['order_field' =>  $this->model->get_primary_key(), 
            'order_dir' => 'desc', 
            'limit' => 5];
    }

    protected function get_request_params($table_id) {
        $default = $this->default_request_params();
        $new_request = [];
        $request = $_REQUEST[$table_id] ?? [];
        $new_request = $request;
        $new_request['order_field'] = $request['order_field'] ?? ($default['order_field'] ?? null);
        $new_request['order_dir'] = $request['order_dir'] ?? ($default['order_dir'] ?? 'desc');
        if (!in_array($new_request['order_dir'], ['asc', 'desc'])) {
            $new_request['order_dir'] = 'desc';
        }
        $new_request['limit'] = _absint($request['limit'] ?? 0);
        $new_request['page'] = _absint($request['page'] ?? 1);
        $new_request['filters'] = $request['filters'] ?? '';
        if ($new_request['page'] < 1) {
            $new_request['page'] = 1;
        }
        if ($new_request['limit'] < 1) {
            $new_request['limit'] = $default_limit ?? 10;
        }
        $new_request['limit_start'] = ($new_request['page'] *  $new_request['limit'] ) -  $new_request['limit'] ;
        if ( $new_request['limit_start'] < 0) {
            $new_request['limit_start'] = 0;
        }
        return $new_request;
    }
 
    /**
     * Build the modellist data
     */
    protected function get_modellist_data($table_id, $fn_filter_applier = null): array {
        $request = $this->get_request_params($table_id);
        $model_list = $this->get_modellist_service($this->model, $table_id, $request);
        if (is_callable($fn_filter_applier)) {
            $fn_filter_applier($model_list);
        }
        $query = $model_list->query_from_request();
        $rows = $this->model->get(...$query->get());
        $total = $this->model->get(...$query->get_total());
        return ['rows'=> $rows, 'info' => $model_list->get_list_structure(), 'page_info' =>  $model_list->get_page_info($total)];
    }

    /**
     * Cosi se devo riscrivere il modellist_data perché il model è cambiato parte del codice lo riuso
     */
    protected function get_modellist_service($model, $table_id, $request): ModelList {
        $model_list = new ModelList($this->model->table, $table_id);
        $list = $model->get_filtered_columns('list', true);
        $all_data = $model->get_filtered_columns();
        // tutte quelle che  sono in all data ma non sono in $list le metto come type hidden 
        foreach ($all_data as $key => $value) {
            if (!isset($list[$key])) {
                $list[$key] = $value;
                $list[$key]['type'] = 'hidden';
            }
        }
        $model_list->set_list_structure($list);
        $model_list->set_primary_key($model->get_primary_key());
        $model_list->set_request($request);
      
        return $model_list;
    }

    /**
     * Serve a chiamare le funzioni di azione delle tabelle in modo automatico
     * Verifica anche il token di sicurezza della tabella.
     * Lo si usa dentro la funzione che stampa la tabella così da poter settare le azioni da fare in json
     */
    protected function call_table_action($table_id, $action, $function) {
        $request = $_REQUEST[$table_id] ?? [];
        if (isset($request['table_action']) && method_exists($this, $function) && $request['table_action'] == $table_id.'-'.$action) {
            if (Token::check('table')) {
                $ids = $request['table_ids'] ?? 0;
                $result = true;
                if (is_array($ids)) {
                   
                   foreach ($ids as $id) {
                      $this->$function($id, $request);
                   }
                } else if ($ids > 0) {
                    !$this->$function($ids, $request);
                } else {
                    MessagesHandler::add_error('Invalid id');
                }
                
            } else {
                MessagesHandler::add_error('Invalid token');
            }
           
        }
    }

}