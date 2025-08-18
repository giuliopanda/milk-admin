<?php
namespace MilkCore;
use MilkCore\Route;
use MilkCore\Hooks;
use MilkCore\Permissions;
use MilkCore\Cli;
use MilkCore\Config;
use MilkCore\Theme;
use MilkCore\MessagesHandler;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Abstract Controller Class
 *
 * This class serves as the base for module management in the framework. It provides
 * a standardized structure for initializing modules, handling routing, managing permissions,
 * and interacting with models. Controllers that extend this class gain access to automated
 * installation, update, and uninstallation functionality, as well as CLI command registration.
 *
 * @example
 * ```php
 * class PostsController extends \MilkCore\AbstractController {
 *     protected $page = 'posts';
 *     protected $title = 'Posts';
 *     protected $access = 'authorized';
 *     protected $menu_links = [
 *         ['url'=> '', 'name'=> 'Posts', 'icon'=> '', 'group'=> 'posts']
 *     ];
 *
 *     public function bootstrap() {
 *         $this->model = new PostsModel();
 *         $this->router = new PostsRouter();
 *     }
 * }
 * new PostsController();
 * ```
 *
 * @package     MilkCore
 * @subpackage  Abstracts
 */

abstract class AbstractController {
    /**
     * The name of the module/page
     * 
     * This is used in URLs as ?page={$page} and for permission identification.
     * If not set, it will be automatically derived from the controller class name.
     * 
     * @example
     * ```php
     * protected $page = 'posts';
     * ```
     * 
     * @var string|null
     */
    protected $page = null;
    /**
     * The title of the module/page
     * 
     * This is displayed in the interface and used for menu items.
     * If not set, it will be automatically derived from the page name.
     * 
     * @example
     * ```php
     * protected $title = 'Posts';
     * ```
     * 
     * @var string|null
     */
    protected $title = null;
    /**
     * Links to be displayed in the sidebar menu
     * 
     * Each link is an array with the following properties:
     * - url: The URL of the link (relative to the module)
     * - name: The display name of the link
     * - icon: The icon class for the link
     * - group: The group name for organizing links
     * - order: Optional ordering value (default: 10)
     * 
     * @example
     * ```php
     * protected $menu_links = [
     *     ['url'=> '', 'name'=> 'home', 'icon'=> '', 'group'=> 'base_module'],
     *     ['url'=> 'action=page2', 'name'=> 'page2', 'icon'=> '', 'group'=> 'base_module']
     * ];
     * ```
     * 
     * @var array [['url', 'name', 'icon', 'group', 'order']];
     */
    protected $menu_links = [];
    /**
     * Access level required for the module
     * 
     * Possible values:
     * - public: Anyone can access
     * - registered: Only logged-in users can access
     * - authorized: Only users with specific permissions can access
     * - admin: Only administrators can access
     * 
     * @example
     * ```php
     * protected $access = 'authorized';
     * ```
     * 
     * @var string
     */
    protected $access = 'registered';
    /**
     * Permission definition for the module
     * 
     * This is a key-value pair that defines the permission name and description.
     * Used when $access is set to 'authorized'.
     * 
     * @example
     * ```php
     * protected $permission = ['access' => 'Access Posts Module'];
     * ```
     * 
     * @var array
     */
    protected $permission = ['access' => 'Access'];
    /**
     * Router instance for handling routes
     * 
     * This can be either a string (class name) or an object instance.
     * If not set, it will be automatically derived from the controller name.
     * 
     * @example
     * ```php
     * protected $router = 'PostsRouter'; // Class name
     * // OR
     * protected $router = new PostsRouter(); // Object instance
     * ```
     * 
     * @var string|object|null
     */
    protected $router = null;
    /**
     * Disable CLI command generation
     * 
     * Set to true to prevent this controller from registering CLI commands.
     * This is useful for controllers that should not expose shell commands.
     * 
     * @example
     * ```php
     * protected $disable_cli = true; // Disables all CLI command registration
     * ```
     * 
     * @var bool
     */
    protected $disable_cli = false;
    /**
     * Model instance for handling data
     * 
     * This can be either a string (class name) or an object instance.
     * If not set, it will be automatically derived from the controller name.
     * 
     * @example
     * ```php
     * protected $model = 'PostsModel'; // Class name
     * // OR
     * protected $model = new PostsModel(); // Object instance
     * ```
     * 
     * @var string|object|null
     */
    protected $model = null;

    /**
     * Version of the module 
     * In Milk Admin the version is a number composed of year, month and progressive number es. 250801
     * 
     * @var int|null
     */
    protected $version = 0;

    protected $bootstrap_loaded = false;


    /**
     * Constructor
     * 
     * Initializes the module, registers hooks, and sets permissions for authorized modules.
     * If the current page matches this module's page or if running in CLI mode,
     * the init() method is called.
     */
    function __construct() {
        if ($this->page == null) {
            $this->page = $this->get_module_name();
        }
        if ($this->title == null) {
            $this->title = ucfirst($this->page);
        }

        Hooks::set('cli-init', [$this, '_cli_init'], 20);
        Hooks::set('api-init', [$this, '_api_init'], 20);
        Hooks::set('jobs-init', [$this, '_jobs_init'], 20);
        Hooks::set('jobs-start', [$this, '_jobs_start'], 20);
        Hooks::set('init', [$this, '_hook_init']);
        
        if ($this->access == 'authorized') {
            Permissions::set_group_title($this->page, $this->title);
            Permissions::set($this->page, $this->permission);
        }

        if ((isset($_REQUEST['page']) && $_REQUEST['page'] == $this->page)) {
            // devo eseguirlo dopo aver caricato tutti  construct
             Hooks::set('after_modules_loaded', [$this, 'init'], 10);
        }
        Hooks::set('cli-init', [$this, 'setup_cli_hooks'], 90);

        $folder = $this->getFolderCalled();
        Config::append('module_version', [$this->page => ['version'=>$this->version, 'folder'=>$folder]]);
    }

    /**
     * Hook initialization method
     * 
     * This method is called during the 'init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded.
     */
    public function hook_init() {
        // This method is called during the 'init' hook phase
    }

    /**
     * Hook initialization method for CLI
     * 
     * This method is called during the 'cli-init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded in CLI context.
     */
    public function _cli_init() {
        if (!$this->bootstrap_loaded)$this->_bootstrap();
        $this->cli_init();
    }

    public function _api_init() {
        if (!$this->bootstrap_loaded)$this->_bootstrap();
        $this->api_init();
    }

    public function _jobs_init() {
        if (!$this->bootstrap_loaded)$this->_bootstrap();
        $this->jobs_init();
    }

    public function _jobs_start() {
        if (!$this->bootstrap_loaded)$this->_bootstrap();
        $this->jobs_start();
    }

    public function _hook_init() {
         // Se non c'è la versione vuol dire che il sistema deve essere ancora installato
         if (Config::get('version') == null || NEW_VERSION > Config::get('version')) {
            // function($html, $errors)
            Hooks::set('install.get_html_modules', [$this, '_install_get_html_modules'], 50);
            // function($errors, $data)
            Hooks::set('install.check_data', [$this, '_install_check_data'], 50);
            // function($data)
             Hooks::set('install.execute', [$this, '_install_execute'], 50);
            // function($html)
            Hooks::set('install.done', [$this, '_install_done'], 50);
            // function($html)
            Hooks::set('install.update', [$this, '_install_update'], 50);
        }

        // Set up sidebar menu
        if ($this->access()) {
            foreach ($this->menu_links as &$link) {
                // Set defaults if not provided
                $link['name'] = $link['name'] ?? $this->page;
                $link['url'] = $link['url'] ?? '';
                $link['icon'] = $link['icon'] ?? '';
                $link['order'] = $link['order'] ?? 10;
                // $link['url'] se c'è un ? iniziale lo tolgo
                if (strpos($link['url'], '?') === 0 || strpos($link['url'], '&') === 0 || strpos($link['url'], '/') === 0) {
                    $link['url'] = substr($link['url'], 1);
                }
                if ($link['url'] == '') {
                    $link['url'] = 'page=' . $this->page;
                } else {
                    $link['url'] = '?page=' . $this->page . '&' . $link['url'];
                }           
                // Add link to main sidebar
                Theme::set('sidebar.links', [
                    'url' => Route::url($link['url']),
                    'title' => $link['name'],
                    'icon' => $link['icon'],
                    'order' => $link['order'],
                ]);
            }
        }

        $this->hook_init(); 
    }


     /**
     * Hook initialization method for API
     * 
     * This method is called during the 'api-init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded in API context.
     */
    public function api_init() {
        // This method is called during the 'api-init' hook phase
    }

    /**
     * Hook initialization method for CLI
     * 
     * This method is called during the 'cli-init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded in CLI context.
     */
    public function cli_init() {
        // This method is called during the 'cli-init' hook phase
    }


    public function _bootstrap() {
        $this->bootstrap_loaded = true;
       $this->bootstrap();
    }

    /**
     * Bootstrap method
     * 
     * This method is called during the 'init', 'jobs-init', 'cli-init' and 'api-init' hook phases.
     * Since controllers are loaded at system startup, to avoid loading all other classes
     * required by the module, it's better to avoid initializing them here when possible.
     * It's not necessary to include class files via require because the system uses
     * lazy loading to load classes when they are needed.
     * 
     * Override this method in child classes to initialize module-specific components
     * like models and routers that should be available across different contexts.
     * 
     * @example
     * ```php
     * public function bootstrap() {
     *     $this->model = new PostsModel();
     *     $this->router = new PostsRouter();
     * }
     * ```
     */
    public function bootstrap() {
        
    }

    /**
     * Hook initialization method for background jobs
     * 
     * This method is called during the 'jobs-init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded in the background jobs context.
     */
    public function jobs_init() {
        // This method is called during the 'jobs-init' hook phase
    }

    /**
     * Hook method for job execution start
     * 
     * This method is called during the 'jobs-start' hook phase.
     * It can be overridden in child classes to perform actions when background jobs
     * are about to start executing.
     */
    public function jobs_start() {
        // This method is called during the 'jobs-start' hook phase
    }

    /**
     * Initialize the module for page rendering
     * 
     * This method is called only when the current page matches this module's page.
     * It can be used to load JavaScript and CSS files specific to the module.
     * The parent::init() method starts the bootstrap process and initializes the router
     * if present, ensuring the same model instance is available in the router.
     * 
     * Override this method in child classes to add module-specific assets and
     * perform page-specific initialization tasks.
     * 
     * @example
     * ```php
     * public function init() {
     *     Theme::set('javascript', Route::url().'/modules/my_module/assets/my_module.js');
     *     Theme::set('styles', Route::url().'/modules/my_module/assets/my_module.css');
     *     parent::init();
     * }
     * ```
     */
    public function init() {  
       $this->_bootstrap();   
        if (is_object($this->router) && method_exists($this->router, 'set_handle_routes')) {
            $this->router->set_handle_routes($this);
        }
    }

    public function get_title() {
        return $this->title;
    }
    public function get_page() {
        return $this->page;
    }
    public function get_model() {
        return $this->model;
    }

    /**
     * Set up CLI hooks
     * 
     * Registers shell commands for installing, updating, and uninstalling the module
     * if the model has the necessary methods. Also automatically registers any method
     * that starts with 'shell_' as a CLI command.
     * 
     * @example
     * ```php
     * public function setup_cli_hooks() {
     *     parent::setup_cli_hooks();
     *     // Add another command to the shell
     *     Cli::set($this->page.":my_command", [$this, 'my_command']);
     * }
     * 
     * public function my_command($param1, $param2) {
     *     Cli::echo("My command called with params ".$param1." ".$param2);
     * }
     * ```
     */
    public function setup_cli_hooks() {
        // Check if CLI command generation is disabled for this controller
        if ($this->disable_cli) {
            return;
        }
        
        if (is_object($this->model) && method_exists($this->model, 'build_table') && method_exists($this->model, 'drop_table')) {
            Cli::set($this->page.":install", [$this, 'shell_install_module']);
            Cli::set($this->page.":uninstall", [$this, 'shell_uninstall_module']);
            Cli::set($this->page.":update", [$this, 'shell_update_module']);
        }
        // Aggiungo tutti i metodi che iniziano con shell_ come comandi cli
        $methods = $this->get_shell_methods();
        foreach ($methods as $method) {
            $method_name = str_replace('shell_', '', $method);
            Cli::set($this->page.":".$method_name, [$this, $method]);
        }
        
    }


    /**
     * Get all shell methods from the child class
     * 
     * Finds all methods that start with 'shell_' in the child class,
     * excluding the standard install/uninstall/update methods.
     * 
     * @return array List of shell method names
     */
    protected function get_shell_methods() {
        $exclude = ['shell_install_module', 'shell_uninstall_module', 'shell_update_module'];
        $childClass = get_called_class();
        $reflection = new \ReflectionClass($childClass);
        $methods = $reflection->getMethods();
        $shellMethods = [];
        
        foreach ($methods as $method) {
            if (strpos($method->getName(), 'shell_') === 0) {
                if (in_array($method->getName(), $exclude))  continue;
                $shellMethods[] = $method->getName();
            }
        }
        
        return $shellMethods;
    }

    /**
     * Install the module
     * 
     * This shell command installs the module by calling the install_execute method.
     * It can be run via CLI or by an admin user.
     * 
     * @example
     * ```bash
     * php cli.php posts:install
     * ```
     */
    public function shell_install_module() {
        if (Cli::is_cli() || Permissions::check('_user.is_admin')) {
            $this->_install_execute();
        }
    }

    /**
     * Uninstall the module
     * 
     * This shell command uninstalls the module by dropping its tables and disabling
     * the module directory. It can be run via CLI or by an admin user.
     * 
     * @example
     * ```bash
     * php cli.php posts:uninstall
     * ```
     */
    public function shell_uninstall_module() {
        if (Cli::is_cli() || Permissions::check('_user.is_admin')) {
            $this->model->drop_table();
            // trova la directory della classe che estende questa classe
           
            $dir = $this->get_child_class_path();
            if (is_dir($dir) == false)  return;
            // Rimuove completamente la directory invece di rinominarla con il punto
              $this->remove_directory($dir);
            
            if (Cli::is_cli()) {
                Cli::success('Module '.$this->title.' uninstalled');
            }
        }
    }

    /**
     * Update the module
     * 
     * This shell command updates the module by calling the install_update method.
     * It can be run via CLI or by an admin user.
     * 
     * @example
     * ```bash
     * php cli.php posts:update
     * ```
     */
    public function shell_update_module() {
       if (Cli::is_cli() || Permissions::check('_user.is_admin')) {
            $this->_install_update();
        } 
    }

    /**
     * Questo serve solo a caricare il bootstrap della classe se non è già stato caricato.
     */
    public function _install_execute($data = []) {
        if (!is_object($this->model))$this->_bootstrap();
        $this->install_execute($data);
    }

    public function _install_update($html = '') {
        if (!is_object($this->model))$this->_bootstrap();
        return $this->install_update($html);
    }

    public function _install_done($html) {
        if (!is_object($this->model))$this->_bootstrap();
        $settings_versions = Settings::get('module_version') ?: [];
        $settings_versions[$this->page] = [
            'version' => $this->version ?? 1,
            'folder' => $this->get_child_class_path()
        ];
        $module_actions = Settings::get('module_actions') ?: [];
        $module_actions[$this->page] = [
            'action' => 'install',
            'user_id' => 1,
            'date' => date('Y-m-d H:i:s')
        ];
        
        // Save to settings
        Settings::set('module_actions', $module_actions);
        Settings::set('module_version', $settings_versions);
        return $this->install_done($html);
    }

    public function _install_check_data($errors, $data) {
        return $this->install_check_data($errors, $data);
    }

    public function _install_get_html_modules(string $html, $errors) {
        if (!is_object($this->model))$this->_bootstrap();
        return $this->install_get_html_modules($html, $errors);
    }

     /**
     * Get HTML for the installation page
     * 
     * Allows modifying the installation page with custom form elements.
     * This method is called if the system is not installed or there's a new version.
     * 
     * @param string $html The current HTML of the installation page
     * @param array $errors Any validation errors from the installation form
     * @return string The modified HTML
     */
    public function install_get_html_modules(string $html, $errors) {
        return $html;
    }


    /**
     * Execute the module installation
     * 
     * Performs the actual installation of the module, typically by creating
     * database tables through the model's build_table method.
     * 
     * @example
     * ```php
     * public function install_execute($data = []) {
     *     // Install table
     *     $this->model->build_table();
     *     // Save other data from the form
     *     Config::set('my_setting', $data['my_setting']);
     * }
     * ```
     * 
     * @param array $data Data from the installation form
     * @return void
     */
    public function install_execute($data = []) {
        if (is_object($this->model) && method_exists($this->model, 'build_table')) {
            $this->model->build_table();
             if (Cli::is_cli()) {
                if ($this->model->last_error != '') {
                    Cli::error($this->model->last_error);
                    return false;
                } else {
                    Cli::success('Module '.$this->title.' installed');
                    return true;
                }
            } else {
                if ($this->model->last_error != '') {
                    MessagesHandler::add_error($this->model->last_error);
                    return false;
                } else {
                    MessagesHandler::add_success('Module '.$this->title.' installed');
                    return true;
                }
            }
        } else {
            if (Cli::is_cli()) {
                Cli::error('Model not found or does not have build_table method');
            } 
            return false;
        }
    }

    /**
     * Validate installation data
     * 
     * Performs validation on the data submitted in the installation form.
     * 
     * @example
     * ```php
     * public function install_check_data($errors, array $data = []) {
     *     if ($data['my_setting'] == '') {
     *         $errors['my_setting'] = 'My setting is required';
     *     }
     *     return $errors;
     * }
     * ```
     * 
     * @param array $errors Current validation errors
     * @param array $data Data from the installation form
     * @return array Updated validation errors
     */
    public function install_check_data($errors, $data = []) {
        return $errors;
    }
    /**
     * Installation completion message
     * 
     * This method is called after installation and allows modifying the
     * HTML of the installation completion page.
     * 
     * @example
     * ```php
     * public function install_done($html) {
     *     $html .= '<div>Module installed correctly</div>';
     *     return $html;
     * }
     * ```
     * 
     * @param string $html Current HTML of the completion page
     * @return string Modified HTML
     */
    public function install_done($html) {
        return $html;
    }

    /**
     * Update the module
     * 
     * This method is called when updating the module. It typically rebuilds
     * the database tables to incorporate any schema changes.
     * 
     * @param string $html Current HTML of the update page
     * @return string Modified HTML
     */
    public function install_update($html) {
        $this->_install_execute();
        return $html;
    }


    /**
     * Get module name from class name
     * 
     * Extracts the module name from the controller class name by removing 'Controller'
     * and converting to lowercase.
     * 
     * @return string The module name
     */
    public function get_module_name() {
        $reflection = new \ReflectionClass($this);
        $className = $reflection->getShortName();
        return strtolower(str_replace('Controller', '', $className));
    }


    /**
     * Initialize a class from its name
     * 
     * Creates an instance of a class based on its name, using the namespace
     * of the child controller class.
     * 
     * @param string|object &$class Class name or object reference
     * @return void
     */
    protected function iniziale_class(&$class) {
        if (!is_scalar($class)) {
            return;
        }
        $name_space = $this->get_child_name_space();
        $class_name = $name_space."\\".$class;
        if (class_exists($class_name)) {
            $class = new $class_name();
        } else if (class_exists($class)) {
            $class = new $class();
        } else {
            Logs::set('system', 'WARNING', 'Class not found: ' . $class);
            $class = null;
        }
    }

    /**
     * Get the file path of the child class
     * 
     * Returns the directory path of the child controller class.
     * 
     * @return string Directory path
     */
    protected function get_child_class_path() {
        $childClass = get_called_class();
        $reflection = new \ReflectionClass($childClass);
        $filePath = $reflection->getFileName();
        $directoryPath = dirname($filePath);
        return $directoryPath;
    }

    /**
     * Get class name with suffix
     * 
     * Generates a class name by replacing 'Controller' with the specified suffix.
     * Used to automatically determine model and router class names.
     * 
     * @param string $suffix Suffix to append to the base name
     * @return string|null The generated class name or null if unchanged
     */
    protected function get_class_name($suffix = '') {
        $reflection = new \ReflectionClass($this);
        $class_name = $reflection->getShortName();
        $new_class_name =  str_replace('Controller', '', $class_name).$suffix;
        if ($new_class_name != $class_name) {
            return $new_class_name;
        } else {
            return null;
        }
    }

    /**
     * Get the namespace of the child class
     * 
     * Returns the namespace of the child controller class.
     * 
     * @return string Namespace
     */
    protected function get_child_name_space(): string {
        return (new \ReflectionClass(get_called_class()))->getNamespaceName();
    }

    /**
     * Check if the current user has access to the module
     * 
     * Verifies permissions based on the module's access level setting.
     * 
     * @return bool True if the user has access, false otherwise
     */
    public function access() {
       $hook = $this->page != null ? $this->page : null;
       $permission = false;
       switch ($this->access) {
           case 'public':
               $permission = true;
               break;
           case 'registered':
               $permission = (Permissions::check('_user.is_guest', $hook) == false);
               break;
           case 'authorized':
               $permission = Permissions::check($this->page.".".key($this->permission), $hook);
               break;
           case 'admin':
               $permission = Permissions::check('_user.is_admin', $hook);
               break;
       }
     
       return $permission;
    }

    /**
     * Get the version of the module
     * 
     * @return int|null The version of the module
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get the folder name of the calling class
     * 
     * This function determines the folder name where the class that extends this abstract
     * class is located. If it's inside the modules directory, it returns the module name.
     * Otherwise, it returns the directory name.
     * 
     * @return string The folder name
     */
    private function getFolderCalled() {
        $childClass = get_called_class();
        $reflection = new \ReflectionClass($childClass);
        $filePath = $reflection->getFileName();
        $directoryPath = dirname($filePath);
        
        // Check if the file is inside the modules directory
        $modulesPath = MILK_DIR . '/modules';
        if (strpos($directoryPath, $modulesPath) === 0) {
            // Extract module name from path
            $relativePath = str_replace($modulesPath . '/', '', $directoryPath);
            $pathParts = explode('/', $relativePath);
            return $pathParts[0]; // Return the first directory name (module name)
        } else {
            // Return the directory name if not in modules
            return basename($directoryPath);
        }
    }

    /**
     * Remove a directory and all its contents recursively
     * Only allows removal of directories within the modules folder for security
     * 
     * @param string $dir Directory path to remove
     * @return bool True if successful, false otherwise
     */
    private function remove_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        // Verifica di sicurezza: la directory deve essere dentro /modules
        $modulesPath = realpath(MILK_DIR . '/modules');
        $targetPath = realpath($dir);
        
        if ($targetPath === false || strpos($targetPath, $modulesPath) !== 0) {
            if (Cli::is_cli()) {
                Cli::error('Security error: Cannot remove directory outside modules folder');
            }
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                if (!is_writable($path)) {
                    if (Cli::is_cli()) {
                        Cli::error('Security error: Cannot remove directory that is not writable');
                    }
                    return false;
                }
                $this->remove_directory($path);
            } else {
                if (!is_writable($path)) {
                    if (Cli::is_cli()) {
                        Cli::error('Security error: Cannot remove file that is not writable');
                    }
                    return false;
                }
                unlink($path);
            }
        }
        if (!is_writable($dir)) {
            if (Cli::is_cli()) {
                Cli::error('Security error: Cannot remove directory that is not writable');
            }
            return false;
        }
        return rmdir($dir);
    }
    
}
