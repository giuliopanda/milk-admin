<?php
namespace App\Abstracts;

use App\Abstracts\Traits\RouteControllerTrait;
use App\Abstracts\Traits\SelectedMenuSidebarTrait;
use App\{Get, Hooks, MessagesHandler, Response, Route, Token};

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Abstract Controller class for handling basic routing functionality
 *
 * @package     App
 * @subpackage  Abstracts
 */

abstract class AbstractController {
    use RouteControllerTrait;
    use SelectedMenuSidebarTrait;

    public $page = null;
    public $access = 'registered';
    public $title = null;
    public $model = null;
    public $module = null;

   
    /**
     * Constructor - sets up routing
     *
     */
    public function __construct() {      
        Hooks::set('init', [$this, 'hookInit']);

    }

    /**
     *Called when after the framework has been initialized and modules loaded
* Always called unlike init
*/
    public function hookInit() {
        Route::set($this->page, [$this, 'handleRoutes']);
    }

    /**
     * Set variables for routing from module
     * It's called by the module to set the variables for routing
     */
    public function setHandleRoutes(AbstractModule $module): void {
        $this->module = $module;
        $this->page = $module->getPage();
        $this->title = $module->getTitle();
        $this->model = $module->getModel();
    }

    /**
     * Get additional models registered for this module
     *
     * @return array|object|null Array of additional models [modelName => modelInstance], single model instance, or null if not found
     */
    public function getAdditionalModels(string $model_name = ''): array|object|null {
        return $this->module->getAdditionalModels($model_name);
    }

    /**
     * Get common data for the module
     */
    public function getCommonData(): array {
        return [
            'page' => $this->page,
            'title' => $this->title,
        ];
    }

    /**
     * Main route handler is now handled by AttributeRouteTrait
     *
     * This method automatically calls the appropriate action method based on the 'action' URL parameter.
     * It supports two systems:
     * 1. Deprecated legacy: action=view calls actionView() method
     * 2. Preferred: #[RequestAction('view')] on any method
     *
     * Priority: Attribute-based routes take precedence over legacy actionXxx methods
     *
     * ACCESS CONTROL:
     * - Module-level access is checked first (defined in AbstractModule)
     * - Method-level access can be defined using #[AccessLevel('public|registered|admin|authorized:permission')]
     * - Method-level access OVERRIDES module-level access for that specific method
     *
     * Example:
     * ```php
     * #[AccessLevel('public')]
     * #[RequestAction('view')]
     * protected function viewPost() {
     *     // This method is public even if module requires 'registered' access
     * }
     *
     * #[AccessLevel('admin')]
     * #[RequestAction('delete')]
     * protected function deletePost() {
     *     // Only admins can access this method
     * }
     * ```
     *
     * IMPORTANT:
     * - Action methods are automatically called based on the URL parameter 'action'
     * - Hyphens in the action name are automatically converted to underscores for legacy methods
     * - Attribute-based routes can map any action to any method name
     * - If no action is specified, 'actionHome()' is called by default
     * - If access is denied, user is redirected to ?page=deny
     * - If no method is found, it redirects to the 404 page
     *
     * @return void
     */

    protected function actionHome() {
        Response::themePage('default', '<h1>'.$this->title.'</h1>');
    }

    /**
     * Check if you have permission to access the page
     */
    protected function access(): bool {
        return $this->module->access();
    }


    protected function defaultRequestParams(): array {
        return   ['order_field' =>  $this->model?->getPrimaryKey(),
            'order_dir' => 'desc',
            'limit' => 5];
    }


    /**
    * @param string $table_id The table id
    * @return array Returns the sanitized parameters
    */

    protected function getRequestParams(string $table_id): array {
        $default = $this->defaultRequestParams();
        $new_request = [];
        $request = $_REQUEST[$table_id] ?? [];
        $new_request = $request;
        $new_request['order_field'] = $request['order_field'] ?? ($default['order_field'] ?? null);
        // Validate order_field against model columns to prevent invalid field names
        if ($new_request['order_field'] !== null && $this->model !== null) {
            $valid_fields = array_keys($this->model->getRules());
            if (!in_array($new_request['order_field'], $valid_fields, true)) {
                $new_request['order_field'] = $default['order_field'] ?? null;
            }
        }
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
            $new_request['limit'] = $default['limit'] ?? 10;
        }
        $new_request['limit_start'] = ($new_request['page'] *  $new_request['limit'] ) -  $new_request['limit'] ;
        if ( $new_request['limit_start'] < 0) {
            $new_request['limit_start'] = 0;
        }
        return $new_request;
    }
 
    /**
     * Used to automatically call table action functions.
     * Also verifies the table's security token.
     * Used within the function that prints the table so you can set the actions to be performed in JSON.
     */
     protected function callTableAction(string $table_id, array $actions) {
        $request = $_REQUEST[$table_id] ?? [];
        foreach ($actions as $action => $function) {
            if (isset($request['table_action']) && method_exists($this, $function) && $request['table_action'] == $action) {
                if (Token::check($table_id)) {
                    $ids = $request['table_ids'] ?? 0;
                    if (!is_array($ids)) {
                    $ids = [$ids];
                    }
                    $this->$function($ids, $request);
                } else {
                    MessagesHandler::addError('Invalid token');
                }
                return;
            }
        }
    }

}
