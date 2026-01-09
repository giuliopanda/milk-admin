<?php
namespace App\Abstracts;

use App\{Config, Hooks, Logs, Permissions, Route, Theme, Get, Lang, ExtensionLoader};
use App\Abstracts\Traits\{InstallationTrait, RouteControllerTrait, AttributeShellTrait, AttributeApiTrait, AttributeHookTrait, ExtensionManagementTrait};

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Abstract Module Class
 *
 * This class serves as the base for module management in the framework. It provides
 * a standardized structure for initializing modules, handling routing, managing permissions,
 * and interacting with models. modules that extend this class gain access to automated
 * installation, update, and uninstallation functionality, as well as CLI command registration.
 *
 * @example
 * ```php
 * class PostsModule extends \App\AbstractModule {
 *     protected $page = 'posts';
 *     protected $title = 'Posts';
 *     protected $access = 'authorized';
 *     protected $menu_links = [
 *         ['url'=> '', 'name'=> 'Posts', 'icon'=> '', 'group'=> 'posts']
 *     ];
 *
 *     public function bootstrap() {
 *
 *     }
 * }
 * new PostsModule();
 * ```
 *
 * @package     App
 * @subpackage  Abstracts
 */

abstract class AbstractModule {

    use InstallationTrait;
    use RouteControllerTrait;
    use AttributeShellTrait;
    use AttributeApiTrait;
    use AttributeHookTrait;
    use ExtensionManagementTrait;

    /**
     * RuleBuilder instance for module configuration
     * @var ModuleRuleBuilder|null
     */
    private ?ModuleRuleBuilder $rule_builder = null;

    /**
     * The name of the module/page
     * 
     * This is used in URLs as ?page={$page} and for permission identification.
     * If not set, it will be automatically derived from the module class name.
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
     * protected $permissions = ['access' => 'Access Posts Module'];
     * ```
     * 
     * @var array
     */
    protected $permissions = ['access' => 'Access'];
    /**
     * Controller instance for handling routes
     * 
     * This can be either a string (class name) or an object instance.
     * If not set, it will be automatically derived from the module name.
     * 
     * @example
     * ```php
     * protected $controller = new CustomPostsController(); // Object instanq   
     * ```
     * 
     * @var object|null
     */
    protected $controller = null;

    /**
     * Shell instance for handling CLI commands
     * 
     * This can be either a string (class name) or an object instance.
     * If not set, it will be automatically derived from the module name.
     * 
     * @example
     * ```php
     * protected $shell = 'PostsShell'; // Class name
     * ```
     * 
     * @var object|null
     */
    protected $shell = null;

    /**
     * Hook instance for handling hooks
     * 
     * This can be either a string (class name) or an object instance.
     * If not set, it will be automatically derived from the module name.
     * 
     * @example
     * ```php
     * protected $hook = 'PostsHook'; // Class name
     * ```
     * 
     * @var object|null
     */
    protected $hook = null;

    /**
     * API instance for handling API endpoints
     * 
     * This can be either a string (class name) or an object instance.
     * If not set, it will be automatically derived from the module name.
     * 
     * @example
     * ```php
     * protected $api = 'PostsApi'; // Class name
     * ```
     * 
     * @var object|null
     */
    protected $api = null;
    /**
     * Disable CLI command generation
     * 
     * Set to true to prevent this module from registering CLI commands.
     * This is useful for modules that should not expose shell commands.
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
     * Indicates if this is a core module
     * 
     * Core modules are essential for the system and may have special handling.
     * 
     * @var bool
     */
    protected $is_core_module = false;
    /**
     * Model instance for handling data
     * 
     * This can be either a string (class name) or an object instance.
     * If not set, it will be automatically derived from the module name.
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
     * Install instance for handling installation operations
     * 
     * This will be automatically loaded if an Install class exists in the module folder.
     * If present, it handles all installation operations instead of the InstallationTrait.
     * 
     * @var object|null
     */
    protected $install = null;

    /**
     * Version of the module 
     * In Milk Admin the version is a number composed of year, month and progressive number es. 250801
     * 
     * @var int|null
     */
    protected $version = 0;

    /**
     * Additional models for the module
     *
     * @var array|null
     */
    protected ?array $additional_models = null;

    /**
     * Extensions to load for this module
     * @var array
     */
    protected array $extensions = [];

    /**
     * Loaded extension instances (Module extensions)
     * @var array
     */
    private array $loaded_extensions = [];

    /**
     * Loaded Install extension instances
     * @var array
     */
    private array $loaded_install_extensions = [];

    /**
     * Loaded Hook extension instances
     * @var array
     */
    private array $loaded_hook_extensions = [];

    /**
     * Loaded Api extension instances
     * @var array
     */
    private array $loaded_api_extensions = [];

    /**
     * Loaded Shell extension instances
     * @var array
     */
    private array $loaded_shell_extensions = [];

    /**
     * Loaded Controller extension instances
     * @var array
     */
    private array $loaded_controller_extensions = [];

    protected $bootstrap_loaded = false;
    

    /**
     * Constructor
     */
    function __construct() {
        // Initialize rule builder and call configure
        $this->rule_builder = new ModuleRuleBuilder();

        // Normalize extensions to associative format
        $this->extensions = $this->normalizeExtensions($this->extensions);



        // Call module's configure method
        $this->configure($this->rule_builder);

        // Apply configuration from rule builder
        if ($this->rule_builder->getPage() !== null) {
            $this->page = $this->rule_builder->getPage();
        }
        if ($this->rule_builder->getTitle() !== null) {
            $this->title = $this->rule_builder->getTitle();
        }

         // Merge extensions from rule_builder with existing extensions
        if ($this->rule_builder->getExtensions() !== null) {
            $new_extensions = $this->rule_builder->getExtensions();
            $original_extensions = $this->extensions;

            // Merge extensions using the new method
            $this->extensions = $this->mergeExtensions($original_extensions, $new_extensions);

            // Reload extensions if new ones were added
            if (count($this->extensions) > count($original_extensions)) {
                $this->loadExtensions();

                // Call hooks for newly loaded extensions
                \App\ExtensionLoader::callHook($this->loaded_extensions, 'configure', [$this->rule_builder]);
            }
        }
   
        if ($this->rule_builder->getMenuLinks() !== null) {
            $this->menu_links = $this->rule_builder->getMenuLinks();
        }
        if ($this->rule_builder->getAccess() !== null) {
            $this->access = $this->rule_builder->getAccess();
        }
        if ($this->rule_builder->getPermissions() !== null) {
            $this->permissions = $this->rule_builder->getPermissions();
        }
        if ($this->rule_builder->getController() !== null) {
            $this->controller = $this->rule_builder->getController();
        }
        if ($this->rule_builder->getShell() !== null) {
            $this->shell = $this->rule_builder->getShell();
        }
        if ($this->rule_builder->getHook() !== null) {
            $this->hook = $this->rule_builder->getHook();
        }
        if ($this->rule_builder->getApi() !== null) {
            $this->api = $this->rule_builder->getApi();
        }
        if ($this->rule_builder->getModel() !== null) {
            $this->model = $this->rule_builder->getModel();
        }
        if ($this->rule_builder->getInstall() !== null) {
            $this->install = $this->rule_builder->getInstall();
        }
        if ($this->rule_builder->getDisableCli() !== null) {
            $this->disable_cli = $this->rule_builder->getDisableCli();
        }
        if ($this->rule_builder->getIsCoreModule() !== null) {
            $this->is_core_module = $this->rule_builder->getIsCoreModule();
        }
        if ($this->rule_builder->getVersion() !== null) {
            $this->version = $this->rule_builder->getVersion();
        }
        if ($this->rule_builder->getAdditionalModels() !== null) {
            $this->additional_models = $this->rule_builder->getAdditionalModels();
        }

      

        // Set defaults if not configured
        if ($this->page == null) {
            $this->page = strtolower($this->getModuleName());
        }
        if ($this->title == null) {
            $this->title = ucfirst($this->page);
        }


        Hooks::set('cli-init', [$this, '_cli_init'], 20);
        Hooks::set('cli-init', [$this, 'setupAttributeShell'], 40);
        Hooks::set('test-init', [$this, '_test_init'], 20);
        Hooks::set('api-init', [$this, '_api_init'], 20);
        Hooks::set('api-init', [$this, 'setupAttributeApi'], 40);
        Hooks::set('jobs-init', [$this, '_jobs_init'], 20);
        Hooks::set('jobs-start', [$this, '_jobs_start'], 20);
        Hooks::set('init', [$this, '_hook_init']);

        Hooks::set('install.init', [$this, 'setupInstallClass'], 5);

        
       
        Permissions::setGroupTitle($this->page, $this->title);
        
        if (is_array($this->permissions) && $this->access == 'authorized') {
            foreach ($this->permissions as $key => $desc) {
                Permissions::set($this->page, [$key => $desc]);
            }
        } else if ($this->access == 'authorized') {
            Permissions::set($this->page, $this->permissions);
        }
       
     
        if ((isset($_REQUEST['page']) && $_REQUEST['page'] == $this->page)) {
            $this->loadLang();
            Hooks::set('after_modules_loaded', [$this, 'init'], 10);
            Hooks::set('after_modules_loaded', [$this, 'setStylesAndScripts'], 15);
            Hooks::set('after_modules_loaded', [$this, 'afterInit'], 11);

            // Set selected menu if configured
            $selected_menu = $this->rule_builder->getSelectedMenu();
            if ($selected_menu !== null) {
                Theme::set('sidebar.selected', $selected_menu);
            }
        }

        $folder = $this->getFolderOrFileCalled();
        Config::append('modules_active', [$this->page => ['version'=>$this->version, 'folder'=>$folder]]);
        // Load the contract if present
        $module_name = $this->getModuleName();
        $childPath = $this->getChildClassPath();
        $file = $childPath . '/' . $module_name . 'Contract.php';
        if (file_exists($file) ) {
            $contractClass = $this->getChildNameSpace() . '\\' . $this->getClassName('Contract');
            Get::bind($module_name, $contractClass);
        }

        // avvio hook
        $this->autoLoadHook();
        if ($this->hook !== null) {
            $this->hook->registerHooks();
        } else {
            $this->registerHooks();
        }
        // Call onRegisterHooks on all Hook extensions after registerHooks
        foreach ($this->loaded_hook_extensions as $extension) {
            if (method_exists($extension, 'onRegisterHooks')) {
                $extension->onRegisterHooks();
            }
        }

    }


    /**
     * Configuration method to be implemented by child classes
     * This method should define the module's structure and properties
     *
     * @param ModuleRuleBuilder $rule Rule builder instance
     * @return void
     */
    protected function configure(ModuleRuleBuilder $rule): void
    {
        // To be overridden by child classes
    }

    /**
     * Hook initialization method
     *
     * This method is called during the 'init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded.
     */
    public function hookInit() {
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
        $this->cliInit();
    }

    public function _test_init() {
        if (!$this->bootstrap_loaded)$this->_bootstrap();
        $this->testInit();
    }

    public function _api_init() {
        if (!$this->bootstrap_loaded)$this->_bootstrap();
        $this->apiInit();
    }

    public function _jobs_init() {
        if (!$this->bootstrap_loaded)$this->_bootstrap();
        $this->jobsInit();
    }

    public function _jobs_start() {
        if (!$this->bootstrap_loaded)$this->_bootstrap();
        $this->jobsStart();
    }

    /**
     * Call always during module initialization
     *
     * This method is called during the 'init' hook phase.
     */
    public function _hook_init() {

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

        $this->hookInit();
    }

     /**
     * Hook initialization method for API
     * 
     * This method is called during the 'api-init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded in API context.
     */
    public function apiInit() {
        // This method is called during the 'api-init' hook phase
    }

    /**
     * Hook initialization method for CLI
     * 
     * This method is called during the 'cli-init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded in CLI context.
     */
    public function cliInit() {
        // This method is called during the 'cli-init' hook phase
    }

    public function testInit() {
        // This method is called during the 'test-init' hook phase
    }

    public function _bootstrap() {

        $this->bootstrap_loaded = true;
        $this->autoLoadController();
        $this->autoLoadModel();
        $this->autoLoadApi();

        $this->bootstrap();

        // Call onHandleRoutes hook on all Controller extensions (always, regardless of controller existence)
        foreach ($this->loaded_controller_extensions as $extension) {
            if (method_exists($extension, 'onHandleRoutes')) {
                $extension->onHandleRoutes();
            }
        }

        if (is_object($this->controller) && method_exists($this->controller, 'setHandleRoutes')) {
            $this->controller->setHandleRoutes($this);

            // Call hookInit to register the route (needed for CLI context where 'init' hook doesn't run)
            if (method_exists($this->controller, 'hookInit')) {
                $this->controller->hookInit();
            }
        } elseif ($this->controller === null && method_exists($this, 'handleRoutes')) {
            // If no controller is set but module has handleRoutes method, use the module as controller
            $this->controller = $this;
            // Register the route handler like AbstractController does
            Route::set($this->page, [$this, 'handleRoutes']);
        }
    }

    /**
     * Configures the shell for the module
     *
     * This method is called during the 'cli-init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded in CLI context.
     */
    public function setupAttributeShell() {
        $this->autoLoadShell();

        $this->setupInstallClass();

        if ($this->shell !== null) {
            $this->shell->setHandleShell($this);

            // Call onSetup hook on all Shell extensions after setHandleShell
            foreach ($this->loaded_shell_extensions as $extension) {
                if (method_exists($extension, 'onSetup')) {
                    $extension->onSetup();
                }
            }

            $this->shell->setupAttributeShellTraitCliHooks();
        }  else {
            $this->setupAttributeShellTraitCliHooks();
        }
    }

    /**
     * Configures the API for the module
     *
     * This method is called during the 'api-init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded in API context.
     */
    public function setupAttributeApi() {
        $this->autoLoadApi();

        if ($this->api !== null) {
            $this->api->setHandleApi($this);

            // Call onSetup hook on all Api extensions after setHandleApi
            foreach ($this->loaded_api_extensions as $extension) {
                if (method_exists($extension, 'onSetup')) {
                    $extension->onSetup();
                }
            }

            $this->api->setupAttributeApiTraitHooks();
        } else {
            $this->setupAttributeApiTraitHooks();
        }
    }

    /**
     * Load the language files for the module
     *
     * @return void
     */
     protected function loadLang() {
        // Load the module's language files
        $reflection = new \ReflectionClass($this);
        $moduleDir = dirname($reflection->getFileName());
        $langDir = $moduleDir . '/Lang/';
        if (is_dir($langDir)) {
            $lang = Get::userLocale();
            Lang::loadPhpFile($langDir . '/' . $lang . '.php', $this->page);
        }
     }
    

    /**
     * Bootstrap method
     * 
     * This method is called during the 'init', 'jobs-init', 'cli-init' and 'api-init' hook phases.
     * Since modules are loaded at system startup, to avoid loading all other classes
     * required by the module, it's better to avoid initializing them here when possible.
     * It's not necessary to include class files via require because the system uses
     * lazy loading to load classes when they are needed.
     * 
     * Override this method in child classes to initialize module-specific components
     * like models and controllers that should be available across different contexts.
     * 
     * @example
     * ```php
     * public function bootstrap() {
     *     $this->model = new CustomPostsModel();
     *     $this->controller = new CustomPostsController();
     * }
     * ```
     */
    public function bootstrap() {
        // Call extension hook: after bootstrap
        ExtensionLoader::callHook($this->loaded_extensions, 'bootstrap', []);
    }

    /**
     * Hook initialization method for background jobs
     * 
     * This method is called during the 'jobs-init' hook phase.
     * It can be overridden in child classes to perform actions when the system is initialized
     * and all modules are loaded in the background jobs context.
     */
    public function jobsInit() {
        // This method is called during the 'jobs-init' hook phase
    }

    /**
     * Hook method for job execution start
     * 
     * This method is called during the 'jobs-start' hook phase.
     * It can be overridden in child classes to perform actions when background jobs
     * are about to start executing.
     */
    public function jobsStart() {
        // This method is called during the 'jobs-start' hook phase
    }

    /**
     * Initialize the module for page rendering
     * 
     * This method is called only when the current page matches this module's page.
     * It can be used to load JavaScript and CSS files specific to the module.
     * The parent::init() method starts the bootstrap process and initializes the controller
     * if present, ensuring the same model instance is available in the controller.
     * 
     * Override this method in child classes to add module-specific assets and
     * perform page-specific initialization tasks.
     * 
     * @example
     * ```php
     * public function init() {
     *     Theme::set('javascript', Route::url().'/Modules/my_module/assets/my_module.js');
     *     Theme::set('styles', Route::url().'/Modules/my_module/assets/my_module.css');
     * }
     * ```
     */
    public function init() {
        // Call extension hook: after init
        ExtensionLoader::callHook($this->loaded_extensions, 'init', []);
    }


    /**
     * Load JavaScript and CSS files configured via configure()
     * This method is called automatically during afterInit
     *
     * @return void
     */
    public function setStylesAndScripts(): void
    {
        // Get JS and CSS files from rule builder
        $js_files = $this->rule_builder->getJs();
        $css_files = $this->rule_builder->getCss();

        // Get the module folder path
        $module_folder = $this->getChildClassPath();
       
        // Process JavaScript files
        foreach ($js_files as $path) {
            $full_path = $this->resolveAssetPath($path, $module_folder);
            Theme::set('javascript', $full_path);
        }

        // Process CSS files
        foreach ($css_files as $path) {
            $full_path = $this->resolveAssetPath($path, $module_folder);
            Theme::set('styles', $full_path);
        }
    }

    /**
     * Resolve asset path to full URL
     * Handles both relative paths (from module) and absolute paths (from Modules directory)
     *
     * @param string $path Asset path
     * @param string $module_folder Module folder absolute path
     * @return string Full URL to asset
     */
    protected function resolveAssetPath(string $path, string $module_folder): string
    {
        // If path starts with '/' or './', it's relative to the module folder
        if (str_starts_with($path, '/') || str_starts_with($path, './')) {
            // Remove leading '/' or './'
            $path = ltrim($path, './');
            // Get module folder relative to MILK_DIR
            $relative_module = str_replace(MILK_DIR . '/', '', $module_folder);
            if (strpos(LOCAL_DIR, $module_folder) !== false) {
                $relative_module = str_replace(LOCAL_DIR . '/', '', $relative_module);
            }
            return Route::url() . '/' . $relative_module . '/' . ltrim($path, '/');
        }

        // If path starts with 'Modules/', it's already an absolute path from Modules directory
        if (str_starts_with($path, 'Modules/') || str_starts_with($path, 'modules/')) {
            return Route::url() . '/' . $path;
        }

        // Otherwise, treat it as relative to the module folder
        $relative_module = str_replace(MILK_DIR . '/', '', $module_folder);
        $relative_module = str_replace(LOCAL_DIR . '/', '', $relative_module);
        return Route::url() . '/' . $relative_module . '/' . $path;
    }

    /**
     * Set header configurations (title, description, links)
     * This method is called automatically during afterInit
     *
     * @return void
     */
    public function setHeader(): void
    {
        // Set header title
        $header_title = $this->rule_builder->getHeaderTitle();
        if ($header_title !== null) {
            Theme::set('header.title', $header_title);
        }

        // Set header description
        $header_description = $this->rule_builder->getHeaderDescription();
        if ($header_description !== null) {
            Theme::set('header.description', $header_description);
        }

        // Set header links
        $header_links = $this->rule_builder->getHeaderLinks();
        if (!empty($header_links)) {
            $this->buildHeaderLinks($header_links);
        }
    }

    /**
     * Build header links using LinksBuilder
     *
     * @param array $links Header links array
     * @return void
     */
    protected function buildHeaderLinks(array $links): void
    {
        if (empty($links)) {
            return;
        }

        // Get style and position
        $style = $this->rule_builder->getHeaderLinksStyle();
        $position = $this->rule_builder->getHeaderLinksPosition();

        // Create LinksBuilder instance
        $builder = \Builders\LinksBuilder::create();

        // Add each link
        foreach ($links as $link) {
            $title = $link['title'] ?? '';
            $url = $link['url'] ?? '#';

            $builder->add($title, $url);

            // Add icon if present
            if (isset($link['icon']) && $link['icon']) {
                $builder->icon($link['icon']);
            }
        }

        // Render and set to theme
        $navbar = $builder->render($style);
        Theme::set('header.' . $position, $navbar);
    }

    public function afterInit() {
        $this->_bootstrap();
        $this->setHeader();
    }

    public function getTitle() {
        return $this->title;
    }
    public function getPage() {
        return $this->page;
    }
    public function getDisableCli() {
        return $this->disable_cli;
    }
    public function isCoreModule() {
        return $this->is_core_module;
    }
    public function getModel() {
        return $this->model;
    }

    public function getInstall() {
        return $this->install;
    }

    
    public function setupInstallClass() {
        $this->_bootstrap();
        $this->autoLoadInstall();
        if (is_object($this->install) && method_exists($this->install, 'setHandleInstall')) {
            $this->install->setHandleInstall($this);

            // Pass loaded extensions to Install class
            if (method_exists($this->install, 'setLoadedExtensions')) {
                $this->install->setLoadedExtensions($this->loaded_install_extensions);
            }

            // Call onSetup hook on all Install extensions after setHandleInstall
            foreach ($this->loaded_install_extensions as $extension) {
                if (method_exists($extension, 'onSetup')) {
                    $extension->onSetup();
                }
            }
        }
        if ($this->install === null) {
            $this->setupInstallationCliHooks();
            $this->setupInstallationHooks();
        } else {
            $this->install->setupInstallationCliHooks();
            $this->install->setupInstallationHooks();
        }

    }

    /**
     * Get module name from class name
     * 
     * Extracts the module name from the module class name by removing 'Module'
     * and converting to lowercase.
     * 
     * @return string The module name
     */
    public function getModuleName() {
        $reflection = new \ReflectionClass($this);
        $class_name = $reflection->getShortName();
        return str_replace('Module', '', $class_name);
    }

    /**
     * Initialize a class from its name
     * 
     * Creates an instance of a class based on its name, using the namespace
     * of the child module class.
     * 
     * @param string|object &$class Class name or object reference
     * @return void
     */
    protected function inizialeClass(&$class) {
        if (!is_scalar($class)) {
            return;
        }
        $name_space = $this->getChildNameSpace();
        $class_name = $name_space."\\".$class;
        if (class_exists($class_name)) {
            $class = new $class_name();
        } else if (class_exists($class)) {
            $class = new $class();
        } else {
            Logs::set('SYSTEM', 'Class not found: ' . $class, 'WARNING');
            $class = null;
        }
    }

    /**
     * Get the file path of the child class
     * 
     * Returns the directory path of the child module class.
     * 
     * @return string Directory path
     */
    public function getChildClassPath() {
        $childClass = get_called_class();
        $reflection = new \ReflectionClass($childClass);
        $filePath = $reflection->getFileName();
        $directoryPath = dirname($filePath);
        return $directoryPath;
    }

    /**
     * Get class name with suffix
     * 
     * Generates a class name by replacing 'Module' with the specified suffix.
     * Used to automatically determine model and controller class names.
     * 
     * @param string $suffix Suffix to append to the base name
     * @return string|null The generated class name or null if unchanged
     */
    protected function getClassName($suffix = '') {
        $reflection = new \ReflectionClass($this);
        $class_name = $reflection->getShortName();
        $new_class_name =  str_replace('Module', '', $class_name).$suffix;
        if ($new_class_name != $class_name) {
            return $new_class_name;
        } else {
            return null;
        }
    }

    /**
     * Get the namespace of the child class
     * 
     * Returns the namespace of the child module class.
     * 
     * @return string Namespace
     */
    protected function getChildNameSpace(): string {
        return (new \ReflectionClass(get_called_class()))->getNamespaceName();
    }

    /**
     * Check if the current user has access to the module
     * 
     * Verifies permissions based on the module's access level setting.
     * 
     * @return bool True if the user has access, false otherwise
     */
    public function access(): bool {
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
                $permission_name = (is_array($this->permissions) && count($this->permissions) > 0) ?
                             array_key_first($this->permissions) : 'access';
                $permission = Permissions::check($this->page.".".$permission_name, $hook);
               
               break;
           case 'admin':
               $permission = Permissions::check('_user.is_admin', $hook);
               break;
       }
     
       return $permission;
    }

    public function getPermissionName() {
        return (is_array($this->permissions) && count($this->permissions) > 0) ?
                             array_key_first($this->permissions) : 'access';
    }

    /**
     * Get the version of the module
     * 
     * @return int|null The version of the module
     */
    public function getVersion() {
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
    private function getFolderOrFileCalled() {
        $childClass = get_called_class();
        $reflection = new \ReflectionClass($childClass);
        $filePath = $reflection->getFileName();
        $directoryPath = dirname($filePath);
        
        // Check if the file is inside the modules directory
        $modulesPath = MILK_DIR . '/Modules';
        if (strpos($directoryPath, $modulesPath) === 0) {
            // Extract module name from path
            $relativePath = str_replace($modulesPath . '/', '', $directoryPath);
            $pathParts = explode('/', $relativePath);
            if ($pathParts == 'Modules') {
                // find filename
                $file_name = basename($filePath);
                return $file_name;
            }
            return $pathParts[0]; // Return the first directory name (module name)
        } else {
             if (basename($directoryPath) == 'Modules') {
                // find filename
                $file_name = basename($filePath);
                return $file_name;
            }
            // Return the directory name if not in modules
            return basename($directoryPath);
        }
    }

    /**
     * Automatically load controller file and class if they exist
     * Also loads Controller extensions and scans for #[RequestAction] attributes (always, even if Controller class doesn't exist)
     *
     * @return void
     */
    private function autoLoadController(): void
    {
        if ($this->controller !== null) return;

        $reflection = new \ReflectionClass($this);
        $controllerClass = $reflection->getNamespaceName() . '\\' .
                       str_replace('Module', '', $reflection->getShortName()).'Controller';

        // Load the Controller class if it exists
        if (class_exists($controllerClass)) {
            $this->controller = new $controllerClass();
        }

        // Load Controller extensions ALWAYS (even if Controller class doesn't exist)
        if (!empty($this->extensions)) {
            $this->loaded_controller_extensions = ExtensionLoader::load($this->extensions, 'Controller', $this);

            // Call onInit hook on all Controller extensions
            foreach ($this->loaded_controller_extensions as $extension) {
                if (method_exists($extension, 'onInit')) {
                    $extension->onInit();
                }
            }

            // Scan and register #[RequestAction] attributes from extensions
            $this->scanControllerExtensionsForAttributes();
        }
    }

    /**
     * Automatically load model file and class if they exist
     * 
     * @return void
     */
    private function autoLoadModel() {
        // Check if *Model.php file exists in the module folder
        if ( $this->model !== null) return;
        
        $reflection = new \ReflectionClass($this);
        $modelClass = $reflection->getNamespaceName() . '\\' . 
                       str_replace('Module', '', $reflection->getShortName()).'Model';
        
        if (class_exists($modelClass)) {
            $this->model = new $modelClass();
        }
    }

    /**
     * Automatically load shell file and class if they exist
     * Also loads Shell extensions and scans for #[Shell] attributes (always, even if Shell class doesn't exist)
     *
     * @return void
     */
    private function autoLoadShell() {

        // Check if *Shell.php file exists in the module folder
        if ( $this->shell !== null) return;

        $reflection = new \ReflectionClass($this);
        $shellClass = $reflection->getNamespaceName() . '\\' .
                       str_replace('Module', '', $reflection->getShortName()).'Shell';

        // Load the Shell class if it exists
        if (class_exists($shellClass)) {
            $this->shell = new $shellClass();
        }

        // Load Shell extensions ALWAYS (even if Shell class doesn't exist)
        if (!empty($this->extensions)) {
            $this->loaded_shell_extensions = ExtensionLoader::load($this->extensions, 'Shell', $this);

            // Call onInit hook on all Shell extensions
            foreach ($this->loaded_shell_extensions as $extension) {
                if (method_exists($extension, 'onInit')) {
                    $extension->onInit();
                }
            }

            // Scan and register #[Shell] attributes from extensions
            $this->scanShellExtensionsForAttributes();
        }
    }

    /**
     * Automatically load hooks file and class if they exist
     * Also loads Hook extensions and scans for #[HookCallback] attributes
     *
     * @return void
     */
    private function autoLoadHook() {
      
        // Check if *Hook.php file exists in the module folder
        if ( $this->hook !== null) return;

        $reflection = new \ReflectionClass($this);
        $hookClass = $reflection->getNamespaceName() . '\\' .
                        str_replace('Module', '', $reflection->getShortName()).'Hook';
     
        if (class_exists($hookClass)) {
            $this->hook = new $hookClass();
        } 
        // Load Hook extensions
        if (!empty($this->extensions)) {
            
            $this->loaded_hook_extensions = ExtensionLoader::load($this->extensions, 'Hook', $this);
            
            // Call onInit hook on all Hook extensions
            foreach ($this->loaded_hook_extensions as $extension) {
                if (method_exists($extension, 'onInit')) {
                    $extension->onInit();
                }
            }

            // Scan and register #[HookCallback] attributes from extensions
            $this->scanHookExtensionsForAttributes();
        }
         
    }
        

    /**
     * Automatically load api file and class if they exist
     * Also loads Api extensions and scans for #[ApiEndpoint] attributes (always, even if Api class doesn't exist)
     *
     * @return void
     */
    private function autoLoadApi() {
        // Check if *Api.php file exists in the module folder
        if ( $this->api !== null) return;

        $reflection = new \ReflectionClass($this);
        $apiClass = $reflection->getNamespaceName() . '\\' .
                       str_replace('Module', '', $reflection->getShortName()).'Api';

        // Load the Api class if it exists
        if (class_exists($apiClass)) {
            $this->api = new $apiClass();
        }

        // Load Api extensions ALWAYS (even if Api class doesn't exist)
        if (!empty($this->extensions)) {
            $this->loaded_api_extensions = ExtensionLoader::load($this->extensions, 'Api', $this);

            // Call onInit hook on all Api extensions
            foreach ($this->loaded_api_extensions as $extension) {
                if (method_exists($extension, 'onInit')) {
                    $extension->onInit();
                }
            }

            // Scan and register #[ApiEndpoint] attributes from extensions
            $this->scanApiExtensionsForAttributes();
        }
    }


    /**
     * Automatically load install file and class if they exist
     * Also loads Install extensions (always, even if Install class doesn't exist)
     *
     * @return void
     */
    private function autoLoadInstall(): void
    {
        if ($this->install !== null) return;

        $reflection = new \ReflectionClass($this);
        $installClass = $reflection->getNamespaceName() . '\\' .
                       str_replace('Module', '', $reflection->getShortName()).'Install';

        // Load the Install class if it exists
        if (class_exists($installClass)) {
            $this->install = new $installClass();
        }

        // Load Install extensions ALWAYS (even if Install class doesn't exist)
        if (!empty($this->extensions)) {
            $this->loaded_install_extensions = ExtensionLoader::load($this->extensions, 'Install', $this);

            // Call onInit hook on all Install extensions
            foreach ($this->loaded_install_extensions as $extension) {
                if (method_exists($extension, 'onInit')) {
                    $extension->onInit();
                }
            }
        }
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
     * Load extensions defined in $this->extensions array
     *
     * @return void
     * @throws \Exception If extension is not found
     */
    protected function loadExtensions(): void {
        if (empty($this->extensions)) {
            return;
        }

        $this->loaded_extensions = ExtensionLoader::load($this->extensions, 'Module', $this);
    }

    /**
     * Get loaded extensions
     *
     * @return array
     */
    public function getLoadedExtensions(): array {
        return $this->loaded_extensions;
    }


    /**
     * Get loaded Controller extensions
     * Controller extensions are managed by the Module, not the Controller
     *
     * @return array
     */
    public function getLoadedControllerExtensions(): array
    {
        return $this->loaded_controller_extensions;
    }

    /**
     * Scan Hook extensions for #[HookCallback] attributes and register them
     *
     * @return void
     */
    private function scanHookExtensionsForAttributes(): void
    {
        foreach ($this->loaded_hook_extensions as $extension) {
            $reflection = new \ReflectionClass($extension);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(\App\Attributes\HookCallback::class);

                foreach ($attributes as $attribute) {
                    $hook = $attribute->newInstance();
                    $methodName = $method->getName();

                    // Register the method as a hook callback
                    Hooks::set(
                        $hook->hook_name,
                        [$extension, $methodName],
                        $hook->order
                    );
                }
            }
        }
    }

    /**
     * Scan Api extensions for #[ApiEndpoint] attributes and register them
     *
     * @return void
     */
    private function scanApiExtensionsForAttributes(): void
    {
        if (!defined('MILK_API_CONTEXT')) {
            return;
        }

        foreach ($this->loaded_api_extensions as $extension) {
            $reflection = new \ReflectionClass($extension);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(\App\Attributes\ApiEndpoint::class);

                foreach ($attributes as $attribute) {
                    $api = $attribute->newInstance();
                    $methodName = $method->getName();

                    // Register the API endpoint
                    $options = array_merge($api->options, [
                        'method' => $api->method ?? 'ANY'
                    ]);
                    \App\API::set($api->url, [$extension, $methodName], $options);

                    // Check if this method also has ApiDoc attribute
                    $docAttributes = $method->getAttributes(\App\Attributes\ApiDoc::class);
                    if (!empty($docAttributes)) {
                        $apiDoc = $docAttributes[0]->newInstance();
                        \App\API::setDocumentation($api->url, $apiDoc->toArray());
                    }
                }
            }
        }
    }

    /**
     * Scan Shell extensions for #[Shell] attributes and register them
     *
     * @return void
     */
    private function scanShellExtensionsForAttributes(): void
    {
        foreach ($this->loaded_shell_extensions as $extension) {
            $reflection = new \ReflectionClass($extension);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(\App\Attributes\Shell::class);

                foreach ($attributes as $attribute) {
                    $shell = $attribute->newInstance();
                    $methodName = $method->getName();

                    // Register the shell command
                    if (isset($shell->system) && $shell->system === true) {
                        // System command without module prefix
                        \App\Cli::set($shell->command, [$extension, $methodName]);
                    } else {
                        // Module command with prefix
                        if ($this->page) {
                            \App\Cli::set($this->page . ":" . $shell->command, [$extension, $methodName]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Scan Controller extensions for #[RequestAction] and #[AccessLevel] attributes and register them
     *
     * This method scans all loaded Controller extensions for #[RequestAction] attributes
     * and stores them for later use by the Controller's RouteControllerTrait.
     *
     * @return void
     */
    private function scanControllerExtensionsForAttributes(): void
    {
        // Controller extensions are managed by the Module, not passed to the Controller
        // The Module's overridden buildRouteMap() method (from RouteControllerTrait)
        // will scan controller extensions when building routes
        // This happens automatically when the Module acts as the route handler
    }

    /**
     * Override RouteControllerTrait's buildRouteMap to include Controller extensions
     * This ensures Controller extensions work even when no Controller class exists
     *
     * @return void
     */
    protected function buildRouteMap(): void
    {
        // Scan the module itself (if it has route methods)
        if (method_exists($this, 'scanAttributesFromClass')) {
            $this->scanAttributesFromClass($this);
        }

        // Scan module-level extensions
        if (isset($this->loaded_extensions) && !empty($this->loaded_extensions)) {
            foreach ($this->loaded_extensions as $extension) {
                if (method_exists($this, 'scanAttributesFromClass')) {
                    $this->scanAttributesFromClass($extension);
                }
            }
        }

        // Scan Controller extensions (managed by Module, not Controller)
        if (isset($this->loaded_controller_extensions) && !empty($this->loaded_controller_extensions)) {
            foreach ($this->loaded_controller_extensions as $extension) {
                if (method_exists($this, 'scanAttributesFromClass')) {
                    $this->scanAttributesFromClass($extension);
                }
            }
        }
    }


}
