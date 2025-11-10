<?php
namespace App\Abstracts;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * ModuleRuleBuilder - Fluent interface for building module configurations
 *
 * Provides a clean way to configure module properties using method chaining.
 * Similar to RuleBuilder for AbstractModel.
 * Does not modify the module directly, but stores configuration that can be retrieved.
 *
 * Usage:
 * protected function configure($rule): void {
 *     $rule->page('posts')
 *          ->title('Posts')
 *          ->menu('Posts', '', 'bi bi-file-earmark-post-fill', 10)
 *          ->access('registered')
 *          ->version(250901);
 * }
 */
class ModuleRuleBuilder
{
    /**
     * The name of the module/page
     * @var string|null
     */
    protected ?string $page = null;

    /**
     * The title of the module/page
     * @var string|null
     */
    protected ?string $title = null;

    /**
     * Links to be displayed in the sidebar menu
     * @var array|null
     */
    protected ?array $menu_links = null;

    /**
     * Access level required for the module
     * @var string|null
     */
    protected ?string $access = null;

    /**
     * Permissions definition for the module
     * @var array|null
     */
    protected ?array $permissions = null;

    /**
     * Controller instance for handling routes
     * @var string|object|null
     */
    protected $router = null;

    /**
     * Shell instance for handling CLI commands
     * @var string|object|null
     */
    protected $shell = null;

    /**
     * Hook instance for handling hooks
     * @var string|object|null
     */
    protected $hook = null;

    /**
     * API instance for handling API endpoints
     * @var string|object|null
     */
    protected $api = null;

    /**
     * Model instance for handling data
     * @var string|object|null
     */
    protected $model = null;

    /**
     * Install instance for handling installation operations
     * @var string|object|null
     */
    protected $install = null;

    /**
     * Disable CLI command generation
     * @var bool|null
     */
    protected ?bool $disable_cli = null;
    /**
     * Indicates if this is a core module
     * @var bool|null
     */
    protected ?bool $is_core_module = null;

    /**
     * Version of the module
     * @var int|null
     */
    protected ?int $version = null;

    /**
     * Additional models for the module
     * @var array|null
     */
    protected ?array $additional_models = null;

    /**
     * JavaScript files to load
     * @var array
     */
    protected array $js = [];

    /**
     * CSS files to load
     * @var array
     */
    protected array $css = [];

    /**
     * Header title
     * @var string|null
     */
    protected ?string $header_title = null;

    /**
     * Header description
     * @var string|null
     */
    protected ?string $header_description = null;

    /**
     * Header links
     * @var array
     */
    protected array $header_links = [];

    /**
     * Header links style
     * @var string
     */
    protected string $header_links_style = 'pills';

    /**
     * Header links position
     * @var string
     */
    protected string $header_links_position = 'top-left';

    /**
     * Set the page name
     *
     * @param string $page Page name
     * @return self
     */
    public function page(string $page): self
    {
        $this->page = $page;
        return $this;
    }

    /**
     * Set the title
     *
     * @param string $title Title
     * @return self
     */
    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Add a menu link
     *
     * @param string $name Display name
     * @param string $url URL (relative to page)
     * @param string $icon Icon class
     * @param int $order Order value
     * @return self
     */
    public function menu(string $name, string $url = '', string $icon = '', int $order = 10): self
    {
        if ($this->menu_links === null) {
            $this->menu_links = [];
        }
        $this->menu_links[] = [
            'name' => $name,
            'url' => $url,
            'icon' => $icon,
            'order' => $order
        ];
        return $this;
    }

    /**
     * Set multiple menu links at once
     *
     * @param array $links Array of menu links
     * @return self
     */
    public function menuLinks(array $links): self
    {
        $this->menu_links = $links;
        return $this;
    }

    /**
     * Set the access level
     *
     * @param string $access Access level (public, registered, authorized, admin)
     * @return self
     */
    public function access(string $access): self
    {
        $this->access = $access;
        return $this;
    }

    /**
     * Set the permission
     *
     * @param array $permission Permission definition
     * @return self
     */
    public function permissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Set the router
     *
     * @param string|object $router Controller class name or instance
     * @return self
     */
    public function router($router): self
    {
        $this->router = $router;
        return $this;
    }

    /**
     * Set the shell
     *
     * @param string|object $shell Shell class name or instance
     * @return self
     */
    public function shell($shell): self
    {
        $this->shell = $shell;
        return $this;
    }

    /**
     * Set the hook
     *
     * @param string|object $hook Hook class name or instance
     * @return self
     */
    public function hook($hook): self
    {
        $this->hook = $hook;
        return $this;
    }

    /**
     * Set the API
     *
     * @param string|object $api API class name or instance
     * @return self
     */
    public function api($api): self
    {
        $this->api = $api;
        return $this;
    }

    /**
     * Set the model
     *
     * @param string|object $model Model class name or instance
     * @return self
     */
    public function model($model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set the install handler
     *
     * @param string|object $install Install class name or instance
     * @return self
     */
    public function install($install): self
    {
        $this->install = $install;
        return $this;
    }

    /**
     * Disable CLI command generation
     *
     * @return self
     */
    public function disableCli(): self
    {
        $this->disable_cli = true;
        return $this;
    }

    /**
     * Set whether this is a core module
     *
     * @return self
     */
    public function isCoreModule(): self
    {
        $this->is_core_module = true;
        return $this;
    }

    /**
     * Set the module version
     *
     * @param int $version Version number
     * @return self
     */
    public function version(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Add additional models to the module
     *
     * @param array $models Array of models ['name' => ModelClass::class]
     * @return self
     */
    public function addModels(array $models): self
    {
        if ($this->additional_models === null) {
            $this->additional_models = [];
        }
        $this->additional_models = array_merge($this->additional_models, $models);
        return $this;
    }

    /**
     * Add JavaScript file to load
     * Accepts both relative paths from module root (e.g., '/assets/script.js')
     * and absolute paths from Modules directory (e.g., 'Modules/MyModule/Assets/script.js')
     *
     * @param string $path Path to JavaScript file
     * @return self
     */
    public function setJs(string $path): self
    {
        $this->js[] = $path;
        return $this;
    }

    /**
     * Add CSS file to load
     * Accepts both relative paths from module root (e.g., '/assets/style.css')
     * and absolute paths from Modules directory (e.g., 'Modules/MyModule/Assets/style.css')
     *
     * @param string $path Path to CSS file
     * @return self
     */
    public function setCss(string $path): self
    {
        $this->css[] = $path;
        return $this;
    }

    /**
     * Set the header title
     *
     * @param string $title Header title
     * @return self
     */
    public function headerTitle(string $title): self
    {
        $this->header_title = $title;
        return $this;
    }

    /**
     * Set the header description
     *
     * @param string $description Header description
     * @return self
     */
    public function headerDescription(string $description): self
    {
        $this->header_description = $description;
        return $this;
    }

    /**
     * Add a header link
     *
     * @param string $title Display title
     * @param string $url URL (relative or absolute)
     * @param string $icon Icon class (e.g., 'bi bi-house')
     * @return self
     */
    public function addHeaderLink(string $title, string $url = '', string $icon = ''): self
    {
        $this->header_links[] = [
            'title' => $title,
            'url' => $url,
            'icon' => $icon
        ];
        return $this;
    }

    /**
     * Set header links style
     *
     * @param string $style Style: 'pills', 'tabs', 'underline', 'default'
     * @return self
     */
    public function headerStyle(string $style): self
    {
        $this->header_links_style = $style;
        return $this;
    }

    /**
     * Set header links position
     *
     * @param string $position Position: 'top-left', 'top-right'
     * @return self
     */
    public function headerPosition(string $position): self
    {
        $this->header_links_position = $position;
        return $this;
    }

    // ========================================
    // Getters
    // ========================================

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getMenuLinks(): ?array
    {
        return $this->menu_links;
    }

    public function getAccess(): ?string
    {
        return $this->access;
    }

    public function getPermissions(): ?array
    {
        return $this->permissions;
    }

    public function getController()
    {
        return $this->router;
    }

    public function getShell()
    {
        return $this->shell;
    }

    public function getHook()
    {
        return $this->hook;
    }

    public function getApi()
    {
        return $this->api;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getInstall()
    {
        return $this->install;
    }

    public function getDisableCli(): ?bool
    {
        return $this->disable_cli;
    }

    public function getIsCoreModule(): ?bool
    {
        return $this->is_core_module;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function getAdditionalModels(): ?array
    {
        return $this->additional_models;
    }

    public function getJs(): array
    {
        return $this->js;
    }

    public function getCss(): array
    {
        return $this->css;
    }

    public function getHeaderTitle(): ?string
    {
        return $this->header_title;
    }

    public function getHeaderDescription(): ?string
    {
        return $this->header_description;
    }

    public function getHeaderLinks(): array
    {
        return $this->header_links;
    }

    public function getHeaderLinksStyle(): string
    {
        return $this->header_links_style;
    }

    public function getHeaderLinksPosition(): string
    {
        return $this->header_links_position;
    }
}
