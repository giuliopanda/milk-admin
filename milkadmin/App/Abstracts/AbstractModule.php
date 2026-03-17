<?php
namespace App\Abstracts;

use App\{Config, ExtensionLoader, Get, Hooks, Route, Theme, Version};
use App\Abstracts\Services\ModuleMenuRegistryService;
use App\Abstracts\Services\AbstractModule\{
    ModuleAccessService,
    ModuleAssetsService,
    ModuleComponentLoaderService,
    ModuleExtensionAttributeScannerService,
    ModuleModelMetadataService,
    ModuleReflectionService
};
use App\Abstracts\Traits\{
    AttributeApiTrait,
    AttributeHookTrait,
    AttributeShellTrait,
    ExtensionManagementTrait,
    InstallationTrait,
    RouteControllerTrait,
    SelectedMenuSidebarTrait
};

!defined('MILK_DIR') && die();

abstract class AbstractModule implements ProjectModuleInterface
{
    use InstallationTrait;
    use RouteControllerTrait;
    use AttributeShellTrait;
    use AttributeApiTrait;
    use AttributeHookTrait;
    use ExtensionManagementTrait;
    use SelectedMenuSidebarTrait;

    private static array $instances = [];
    private ?ModuleRuleBuilder $rule_builder = null;

    protected $page = null;
    protected $title = null;
    protected $menu_links = [];
    protected $access = 'registered';
    protected $permissions = ['access' => 'Access'];
    protected $controller = null;
    protected $shell = null;
    protected $hook = null;
    protected $api = null;
    protected $disable_cli = false;
    protected $is_core_module = false;
    protected $model = null;
    protected $install = null;
    protected ?string $version = null;
    protected ?array $additional_models = null;
    protected array $extensions = [];

    private array $loaded_extensions = [];
    protected $bootstrap_loaded = false;

    /**
     * Check whether current HTTP request is targeting this module page.
     */
    private function isCurrentModulePageRequest(): bool
    {
        return isset($_REQUEST['page']) && $_REQUEST['page'] == $this->page;
    }

    /**
     * Boot the module with the original initialization sequence.
     */
    public function __construct()
    {
        $this->initializeRuleBuilderAndConfiguration();
        $this->applyConfiguredRuleValues();
        $this->applyDefaultIdentityValues();
        $this->syncMenuRegistry();
        $this->registerCoreLifecycleHooks();
        $this->registerModulePermissions();
        $this->initializeCurrentPageState();
        $this->registerAsActiveModuleAndBindContract();
        $this->initializeHooksAndHookExtensions();
    }

    /** Initialize rule builder, normalize extensions and invoke module configure(). */
    private function initializeRuleBuilderAndConfiguration(): void
    {
        $this->rule_builder = new ModuleRuleBuilder();
        $this->extensions = $this->normalizeExtensions($this->extensions);
        $this->configure($this->rule_builder);
    }

    /** Apply values declared in ModuleRuleBuilder to the module state. */
    private function applyConfiguredRuleValues(): void
    {
        if ($this->rule_builder->getPage() !== null) {
            $this->page = $this->rule_builder->getPage();
        }
        if ($this->rule_builder->getTitle() !== null) {
            $this->title = $this->rule_builder->getTitle();
        }
        if ($this->rule_builder->getExtensions() !== null) {
            $new_extensions = $this->rule_builder->getExtensions();
            $original_extensions = $this->extensions;
            $this->extensions = $this->mergeExtensions($original_extensions, $new_extensions);
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
            $this->version = Version::normalize($this->rule_builder->getVersion());
        }
        if ($this->rule_builder->getAdditionalModels() !== null) {
            $this->additional_models = $this->rule_builder->getAdditionalModels();
        }
    }

    /** Fill default page/title when not configured by module. */
    private function applyDefaultIdentityValues(): void
    {
        if ($this->page == null) {
            $this->page = strtolower($this->getModuleName());
        }
        if ($this->title == null) {
            $this->title = ucfirst($this->page);
        }
    }

    /** Register module lifecycle hooks in the same order as legacy flow. */
    private function registerCoreLifecycleHooks(): void
    {
        Hooks::set('cli-init', [$this, '_cli_init'], 20);
        Hooks::set('cli-init', [$this, 'setupAttributeShell'], 40);
        Hooks::set('test-init', [$this, '_test_init'], 20);
        Hooks::set('api-init', [$this, '_api_init'], 20);
        Hooks::set('api-init', [$this, 'setupAttributeApi'], 40);
        Hooks::set('jobs-init', [$this, '_jobs_init'], 20);
        Hooks::set('jobs-start', [$this, '_jobs_start'], 20);
        Hooks::set('init', [$this, '_hook_init']);
        Hooks::set('install.init', [$this, 'setupInstallClass'], 5);
    }

    /** Register permission groups and permissions for this module. */
    private function registerModulePermissions(): void
    {
        ModuleAccessService::registerModulePermissions(
            $this->page,
            $this->title,
            $this->permissions,
            $this->access
        );
    }

    /** Initialize per-request runtime state for the current module page. */
    private function initializeCurrentPageState(): void
    {
        if (!$this->isCurrentModulePageRequest()) {
            return;
        }

        if (!empty($this->extensions)) {
            $this->loadExtensions();
            ExtensionLoader::callHook($this->loaded_extensions, 'configure', [$this->rule_builder]);

            if ($this->rule_builder->getMenuLinks() !== null) {
                $this->menu_links = $this->rule_builder->getMenuLinks();
            }
            if ($this->rule_builder->getAdditionalModels() !== null) {
                $this->additional_models = $this->rule_builder->getAdditionalModels();
            }
            if ($this->rule_builder->getModel() !== null) {
                $this->model = $this->rule_builder->getModel();
            }

            $this->syncMenuRegistry();
        }

        $this->loadLang();
        Hooks::set('after_modules_loaded', [$this, 'init'], 10);
        Hooks::set('after_modules_loaded', [$this, 'setStylesAndScripts'], 15);
        Hooks::set('after_modules_loaded', [$this, 'afterInit'], 11);

        $selected_menu = $this->rule_builder->getSelectedMenu();
        if ($selected_menu !== null) {
            Theme::set('sidebar.selected', $this->resolveSelectedSidebarTarget((string) $selected_menu));
        }
    }

    /**
     * Resolve a stable sidebar selection target for selectMenu values.
     * If selectMenu looks like a module page slug, prefer its real main-menu URL.
     */
    private function resolveSelectedSidebarTarget(string $selectedMenu): string
    {
        $selectedMenu = trim($selectedMenu);
        if ($selectedMenu === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $selectedMenu) !== 1) {
            return $selectedMenu;
        }

        $ownerLinks = ModuleMenuRegistryService::getConfiguredMenuLinksByModule($selectedMenu);
        $firstOwnerLink = is_array($ownerLinks[0] ?? null) ? $ownerLinks[0] : null;
        if (is_array($firstOwnerLink)) {
            $ownerUrl = trim((string) $firstOwnerLink['url']);
            if ($ownerUrl !== '') {
                return $ownerUrl;
            }
        }

        return Route::url('?page=' . rawurlencode($selectedMenu));
    }

    /** Register module in active config and bind optional module contract. */
    private function registerAsActiveModuleAndBindContract(): void
    {
        $folder = $this->getFolderOrFileCalled();
        $module_version = Version::normalize($this->version) ?? Version::DEFAULT;
        Config::append('modules_active', [$this->page => ['version' => $module_version, 'folder' => $folder]]);
        self::$instances[$this->page] = $this;

        $module_name = $this->getModuleName();
        $childPath = $this->getChildClassPath();
        $file = $childPath . '/' . $module_name . 'Contract.php';
        if (file_exists($file)) {
            $contractClass = $this->getChildNameSpace() . '\\' . $this->getClassName('Contract');
            Get::bind($module_name, $contractClass);
        }
    }

    /** Initialize hook component and run post-hook-registration extension callbacks. */
    private function initializeHooksAndHookExtensions(): void
    {
        $this->autoLoadHook();
        if ($this->hook !== null) {
            $this->hook->registerHooks();
        } else {
            $this->registerHooks();
        }

        ModuleComponentLoaderService::callOnRegisterHooks($this->loaded_hook_extensions);
    }

    /**
     * Keep centralized menu registry in sync with current module configuration.
     */
    private function syncMenuRegistry(): void
    {
        ModuleMenuRegistryService::registerModuleConfiguration(
            (string) $this->page,
            (string) $this->title,
            is_array($this->menu_links) ? $this->menu_links : [],
            $this->rule_builder?->getSelectedMenu(),
            $this->rule_builder?->getSelectedMenuEntry()
        );
    }

    /**
     * Extension point used by concrete modules to configure RuleBuilder values.
     */
    protected function configure(ModuleRuleBuilder $rule): void
    {
    }

    /**
     * Hook callback extension point for generic web init.
     */
    public function hookInit()
    {
    }

    /**
     * Entry point for CLI context.
     */
    public function _cli_init()
    {
        if (!$this->bootstrap_loaded) {
            $this->_bootstrap();
        }
        $this->cliInit();
    }

    /**
     * Entry point for test context.
     */
    public function _test_init()
    {
        if (!$this->bootstrap_loaded) {
            $this->_bootstrap();
        }
        $this->testInit();
    }

    /**
     * Entry point for API context.
     */
    public function _api_init()
    {
        if (!$this->bootstrap_loaded) {
            $this->_bootstrap();
        }
        $this->apiInit();
    }

    /**
     * Entry point for jobs initialization phase.
     */
    public function _jobs_init()
    {
        if (!$this->bootstrap_loaded) {
            $this->_bootstrap();
        }
        $this->jobsInit();
    }

    /**
     * Entry point for jobs execution start phase.
     */
    public function _jobs_start()
    {
        if (!$this->bootstrap_loaded) {
            $this->_bootstrap();
        }
        $this->jobsStart();
    }

    /**
     * Register sidebar links and invoke custom init hook.
     */
    public function _hook_init()
    {
        if ($this->access() && $this->shouldPublishInMainSidebar()) {
            ModuleAssetsService::appendSidebarLinks((string) $this->page, $this->menu_links);
        }

        $this->hookInit();
    }

    /**
     * Modules attached to a selected menu group must not duplicate an entry in main sidebar.
     */
    protected function shouldPublishInMainSidebar(): bool
    {
        $selectedMenu = trim((string) ($this->rule_builder?->getSelectedMenu() ?? ''));
        return $selectedMenu === '';
    }

    /**
     * API-context extension point for modules.
     */
    public function apiInit()
    {
    }

    /**
     * CLI-context extension point for modules.
     */
    public function cliInit()
    {
    }

    /**
     * Test-context extension point for modules.
     */
    public function testInit()
    {
    }

    /**
     * Bootstrap runtime components and route handling linkage.
     */
    public function _bootstrap()
    {
        $this->bootstrap_loaded = true;
        $this->autoLoadController();
        $this->autoLoadModel();
        $this->autoLoadApi();

        $this->bootstrap();

        foreach ($this->loaded_controller_extensions as $extension) {
            if (method_exists($extension, 'onHandleRoutes')) {
                $extension->onHandleRoutes();
            }
        }

        if (is_object($this->controller) && method_exists($this->controller, 'setHandleRoutes')) {
            if (method_exists($this->controller, 'registerRequestAction') && !empty($this->programmaticRouteMap)) {
                foreach ($this->programmaticRouteMap as $action => $handler) {
                    $forwardHandler = $handler;

                    if (
                        is_string($handler)
                        && method_exists($this, $handler)
                        && !method_exists($this->controller, $handler)
                    ) {
                        $forwardHandler = [$this, $handler];
                    }

                    $accessLevel = $this->programmaticAccessByAction[$action] ?? null;
                    $this->controller->registerRequestAction($action, $forwardHandler, $accessLevel);
                }
            }

            $this->controller->setHandleRoutes($this);
            if (method_exists($this->controller, 'hookInit')) {
                $this->controller->hookInit();
            }
        } elseif ($this->controller === null) {
            $this->controller = $this;
            Route::set($this->page, [$this, 'handleRoutes']);
        }
    }

    /**
     * Configure Shell component and Shell-related extensions.
     */
    public function setupAttributeShell()
    {
        $this->autoLoadShell();
        $this->setupInstallClass();

        if ($this->shell !== null) {
            $this->shell->setHandleShell($this);
            ModuleComponentLoaderService::callOnSetup($this->loaded_shell_extensions);
            $this->shell->setupAttributeShellTraitCliHooks();
            return;
        }

        $this->setupAttributeShellTraitCliHooks();
    }

    /**
     * Configure API component and API-related extensions.
     */
    public function setupAttributeApi()
    {
        $this->autoLoadApi();

        if ($this->api !== null) {
            $this->api->setHandleApi($this);
            ModuleComponentLoaderService::callOnSetup($this->loaded_api_extensions);
            $this->api->setupAttributeApiTraitHooks();
            return;
        }

        $this->setupAttributeApiTraitHooks();
    }

    /**
     * Load module language files from its local Lang directory.
     */
    protected function loadLang()
    {
        ModuleReflectionService::loadLangForModule($this, $this->page);
    }

    /**
     * Module-level bootstrap extension point (plus extension hooks).
     */
    public function bootstrap()
    {
        ExtensionLoader::callHook($this->loaded_extensions, 'bootstrap', []);
    }

    /**
     * Jobs init extension point.
     */
    public function jobsInit()
    {
    }

    /**
     * Jobs start extension point.
     */
    public function jobsStart()
    {
    }

    /**
     * Page init extension point (plus extension hooks).
     */
    public function init()
    {
        ExtensionLoader::callHook($this->loaded_extensions, 'init', []);
    }

    /**
     * Register JS/CSS assets declared in rule builder.
     */
    public function setStylesAndScripts(): void
    {
        ModuleAssetsService::setStylesAndScripts(
            $this->rule_builder,
            $this->getChildClassPath(),
            fn(string $path, string $moduleFolder): string => $this->resolveAssetPath($path, $moduleFolder)
        );
    }

    /**
     * Resolve asset path preserving original module path conventions.
     */
    protected function resolveAssetPath(string $path, string $module_folder): string
    {
        return ModuleAssetsService::resolveAssetPath($path, $module_folder);
    }

    /**
     * Publish header title/description/links to Theme.
     */
    public function setHeader(): void
    {
        ModuleAssetsService::setHeader(
            $this->rule_builder,
            function (array $links): void { $this->buildHeaderLinks($links); }
        );
    }
    /**
     * Build and render configured header links.
     */
    protected function buildHeaderLinks(array $links): void
    {
        ModuleAssetsService::buildHeaderLinks($this->rule_builder, $links);
    }

    /**
     * Late initialization phase after modules are loaded.
     */
    public function afterInit()
    {
        $this->_bootstrap();
        $this->setHeader();
    }

    /**
     * Return module title.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Return module page slug.
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Return whether automatic CLI generation is disabled.
     */
    public function getDisableCli()
    {
        return $this->disable_cli;
    }

    /**
     * Return whether this module is marked as core.
     */
    public function isCoreModule()
    {
        return $this->is_core_module;
    }

    /**
     * Return module model configuration or instance.
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Return all instantiated module objects indexed by page.
     */
    public static function getAllInstances(): array
    {
        return self::$instances;
    }

    /**
     * Return a module instance by page key.
     */
    public static function getInstance(string $page): ?self
    {
        return self::$instances[$page] ?? null;
    }

    /**
     * Return model metadata for every instantiated module.
     */
    public static function getAllModels(): array
    {
        return ModuleModelMetadataService::getAllModels(self::$instances);
    }

    /**
     * Return resolved install component.
     */
    public function getInstall()
    {
        return $this->install;
    }

    /**
     * Wire install component and register install hooks/commands.
     */
    public function setupInstallClass()
    {
        $this->_bootstrap();
        $this->autoLoadInstall();

        if (is_object($this->install) && method_exists($this->install, 'setHandleInstall')) {
            $this->install->setHandleInstall($this);

            if (method_exists($this->install, 'setLoadedExtensions')) {
                $this->install->setLoadedExtensions($this->loaded_install_extensions);
            }

            ModuleComponentLoaderService::callOnSetup($this->loaded_install_extensions);
        }

        if ($this->install === null) {
            $this->setupInstallationCliHooks();
            $this->setupInstallationHooks();
            return;
        }

        $this->install->setupInstallationCliHooks();
        $this->install->setupInstallationHooks();
    }

    /**
     * Return module base name inferred from class name.
     */
    public function getModuleName()
    {
        return ModuleReflectionService::getModuleName($this);
    }

    /** 
     * Instantiate a class configured as string in module namespace.
     */
    protected function initializeClass(&$class): void
    {
        ModuleReflectionService::initializeClass($this->getChildNameSpace(), $class);
    }

    /**
     * Return physical directory path of concrete module class.
     */
    public function getChildClassPath()
    {
        return ModuleReflectionService::getChildClassPath(get_called_class());
    }

    /**
     * Build sibling class short name replacing "Module" suffix.
     */
    protected function getClassName($suffix = '')
    {
        return ModuleReflectionService::getClassName($this, (string) $suffix);
    }

    /**
     * Return namespace of concrete module class.
     */
    protected function getChildNameSpace(): string
    {
        return ModuleReflectionService::getChildNamespace(get_called_class());
    }

    /**
     * Evaluate if current user can access the module.
     */
    public function access(): bool
    {
        return ModuleAccessService::canAccess($this->page, $this->access, $this->permissions);
    }

    /**
     * Return primary permission name used for authorized access checks.
     */
    public function getPermissionName()
    {
        return ModuleAccessService::getPermissionName($this->permissions);
    }

    /**
     * Return normalized module version value.
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Resolve folder/file identifier used in active module registry.
     */
    private function getFolderOrFileCalled()
    {
        return ModuleReflectionService::getFolderOrFileCalled(get_called_class());
    }

    /**
     * Auto-load controller component and controller extensions.
     */
    private function autoLoadController(): void
    {
        if ($this->controller !== null) {
            return;
        }

        $this->controller = ModuleComponentLoaderService::instantiateConventionalComponent($this, 'Controller');

        if ($this->isCurrentModulePageRequest() && !empty($this->extensions)) {
            $this->loaded_controller_extensions = ModuleComponentLoaderService::loadExtensions($this->extensions, 'Controller', $this);
            ModuleComponentLoaderService::callOnInit($this->loaded_controller_extensions);
        }
    }

    /**
     * Auto-load model component by naming convention.
     */
    private function autoLoadModel()
    {
        if ($this->model !== null) {
            return;
        }

        $this->model = ModuleComponentLoaderService::instantiateConventionalComponent($this, 'Model');
    }

    /**
     * Auto-load shell component and shell extensions.
     */
    private function autoLoadShell()
    {
        if ($this->shell !== null) {
            return;
        }

        $this->shell = ModuleComponentLoaderService::instantiateConventionalComponent($this, 'Shell');

        if ($this->isCurrentModulePageRequest() && !empty($this->extensions)) {
            $this->loaded_shell_extensions = ModuleComponentLoaderService::loadExtensions($this->extensions, 'Shell', $this);
            ModuleComponentLoaderService::callOnInit($this->loaded_shell_extensions);
            $this->scanShellExtensionsForAttributes();
        }
    }

    /**
     * Auto-load hook component and hook extensions.
     */
    private function autoLoadHook()
    {
        if ($this->hook !== null) {
            return;
        }

        $this->hook = ModuleComponentLoaderService::instantiateConventionalComponent($this, 'Hook');

        if ($this->isCurrentModulePageRequest() && !empty($this->extensions)) {
            $this->loaded_hook_extensions = ModuleComponentLoaderService::loadExtensions($this->extensions, 'Hook', $this);
            ModuleComponentLoaderService::callOnInit($this->loaded_hook_extensions);
            $this->scanHookExtensionsForAttributes();
        }
    }

    /**
     * Auto-load API component and API extensions.
     */
    private function autoLoadApi()
    {
        if ($this->api !== null) {
            return;
        }

        $this->api = ModuleComponentLoaderService::instantiateConventionalComponent($this, 'Api');

        if ($this->isCurrentModulePageRequest() && !empty($this->extensions)) {
            $this->loaded_api_extensions = ModuleComponentLoaderService::loadExtensions($this->extensions, 'Api', $this);
            ModuleComponentLoaderService::callOnInit($this->loaded_api_extensions);
            $this->scanApiExtensionsForAttributes();
        }
    }

    /**
     * Auto-load install component and install extensions.
     */
    private function autoLoadInstall(): void
    {
        if ($this->install !== null) {
            return;
        }

        $this->install = ModuleComponentLoaderService::instantiateConventionalComponent($this, 'Install');

        if ($this->isCurrentModulePageRequest() && !empty($this->extensions)) {
            $this->loaded_install_extensions = ModuleComponentLoaderService::loadExtensions($this->extensions, 'Install', $this);
            ModuleComponentLoaderService::callOnInit($this->loaded_install_extensions);
        }
    }

    /**
     * Return common metadata shared by module and extension layers.
     */
    public function getCommonData(): array
    {
        return [
            'page' => $this->page,
            'title' => $this->title,
        ];
    }

    /**
     * Load module-level extensions when request targets current module page.
     */
    protected function loadExtensions(): void
    {
        if (!$this->isCurrentModulePageRequest() || empty($this->extensions)) {
            return;
        }

        $this->loaded_extensions = ModuleComponentLoaderService::loadExtensions($this->extensions, 'Module', $this);
    }

    /**
     * Return loaded module extensions, or a single extension instance by name.
     *
     * @param string|null $extensionName Extension key (e.g. "Projects")
     * @return array|object|null
     */
    public function getLoadedExtensions(?string $extensionName = null): array|object|null
    {
        if ($extensionName === null) {
            return $this->loaded_extensions;
        }

        $extensionName = trim($extensionName);
        if ($extensionName === '') {
            return null;
        }

        return $this->loaded_extensions[$extensionName] ?? null;
    }

    /**
     * Return loaded controller extensions managed by this module.
     */
    public function getLoadedControllerExtensions(): array
    {
        return $this->loaded_controller_extensions;
    }

    /**
     * Register Hook attributes exposed by hook extensions.
     */
    private function scanHookExtensionsForAttributes(): void
    {
        ModuleExtensionAttributeScannerService::scanHookExtensions($this->loaded_hook_extensions);
    }

    /**
     * Register API attributes exposed by API extensions.
     */
    private function scanApiExtensionsForAttributes(): void
    {
        ModuleExtensionAttributeScannerService::scanApiExtensions($this->loaded_api_extensions);
    }

    /**
     * Register Shell attributes exposed by shell extensions.
     */
    private function scanShellExtensionsForAttributes(): void
    {
        ModuleExtensionAttributeScannerService::scanShellExtensions($this->loaded_shell_extensions, $this->page);
    }

    /**
     * Build route map including module extensions and controller extensions.
     */
    protected function buildRouteMap(): void
    {
        $this->routeMapBuilt = true;

        $this->scanAttributesFromClass($this);

        if (!empty($this->loaded_extensions)) {
            foreach ($this->loaded_extensions as $extension) {
                $this->scanAttributesFromClass($extension);
            }
        }

        if (!empty($this->loaded_controller_extensions)) {
            foreach ($this->loaded_controller_extensions as $extension) {
                $this->scanAttributesFromClass($extension);
            }
        }

        $this->applyProgrammaticRequestActions();
    }
}
