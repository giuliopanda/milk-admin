<?php
namespace Modules\Docs\Pages;
/**
 * @title Abstract Module
 * @guide developer
 * @order 30
 * @tags AbstractModule, module, module, configure, init, bootstrap, install, update, uninstall, access, permissions, menu_links, page, title, router, model, hooks, modules_loaded, install_execute, install_check_data, install_get_html_modules, install_done, install_update, shell, cli, get_module_name, authorized, public, registered, admin, multiple-tables, sidebar-menu, icon, order, URL, ModuleRuleBuilder, addModels
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract Module Class</h1>
    <p class="text-muted">Revision: 2026/02/26</p>

    <p>When creating a new module, the first step is to create a folder inside <code>/Modules/module-name</code>.
        <br>Once the folder is created, you need to create a file <code>{module-name}Module.php</code><br />
        The module file is automatically loaded by <code>Get::loadModules()</code>;<br />
        Any PHP code written inside is therefore executed on every page. <br />
        If you want to use the recommended structure, you can create a class inside the module file that extends <code>AbstractModule</code> called <code>{ModuleName}Module</code>.
    </p>

    <h2 class="mt-4">Two Ways to Configure a Module</h2>
    <p>There are two ways to configure a module:</p>
    <ul>
        <li><strong>New method (recommended)</strong>: Using the <code>configure()</code> method with fluent syntax</li>
        <li><strong>Legacy method</strong>: Using protected properties (still supported for backward compatibility)</li>
    </ul>

    <h3 class="mt-4">Method 1: Using configure() (Recommended)</h3>
    <p>The recommended way to configure a module is using the <code>configure()</code> method with the <code>ModuleRuleBuilder</code>. This provides a clean, fluent interface similar to the RuleBuilder for models.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules\Posts;

use App\Abstracts\AbstractModule;

class PostsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('posts')
             ->title('Posts Management')
             ->access('authorized')
             ->permissions(['access' => 'Access Posts'])
             ->menu('Posts', '', 'bi bi-file-earmark-post-fill', 10)
             ->menu('Categories', 'action=categories', 'bi bi-tags-fill', 20)
             ->version(250901);
    }

    public function bootstrap()
    {
        // Bootstrap code here
    }
}

</code></pre>

    <h4 class="mt-3">Available Configuration Methods (ModuleRuleBuilder)</h4>
    <p>
        The methods listed below are <strong>not</strong> methods of <code>AbstractModule</code> itself.
        They are fluent methods available on the <code>$rule</code> parameter passed to
        <code>configure($rule)</code>, where <code>$rule</code> is an instance of
        <code>App\Abstracts\ModuleRuleBuilder</code>.
    </p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Abstracts\ModuleRuleBuilder;

protected function configure(ModuleRuleBuilder $rule): void
{
    $rule->page('posts')->title('Posts');
}</code></pre>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>page(string)</code></td>
                <td>Module name</td>
                <td>Sets the module page name (used in URLs: <code>?page=posts</code>)</td>
            </tr>
            <tr>
                <td><code>title(string)</code></td>
                <td>Module title</td>
                <td>Sets the module title displayed in the interface</td>
            </tr>
            <tr>
                <td><code>menu(string, string, string, int)</code></td>
                <td>name, url, icon, order</td>
                <td>Adds a menu link to the sidebar. URL is relative to page. Order determines position (lower = higher)</td>
            </tr>
            <tr>
                <td><code>menuLinks(array)</code></td>
                <td>Array of links</td>
                <td>Sets all menu links at once. Array format: <code>[['name'=>'...', 'url'=>'...', 'icon'=>'...', 'order'=>10]]</code></td>
            </tr>
            <tr>
                <td><code>access(string)</code></td>
                <td>public|registered|authorized|admin</td>
                <td>Sets the access level required for the module</td>
            </tr>
            <tr>
                <td><code>permission(array)</code></td>
                <td>['key' => 'description']</td>
                <td>Defines permission for 'authorized' access. Example: <code>['access' => 'Access Posts']</code></td>
            </tr>
            <tr>
                <td><code>router($router)</code></td>
                <td>Class name or instance</td>
                <td>Sets a custom router (string class name or object instance)</td>
            </tr>
            <tr>
                <td><code>model($model)</code></td>
                <td>Class name or instance</td>
                <td>Sets a custom model (string class name or object instance)</td>
            </tr>
            <tr>
                <td><code>shell($shell)</code></td>
                <td>Class name or instance</td>
                <td>Sets a custom shell handler for CLI commands</td>
            </tr>
            <tr>
                <td><code>hook($hook)</code></td>
                <td>Class name or instance</td>
                <td>Sets a custom hook handler</td>
            </tr>
            <tr>
                <td><code>api($api)</code></td>
                <td>Class name or instance</td>
                <td>Sets a custom API handler</td>
            </tr>
            <tr>
                <td><code>install($install)</code></td>
                <td>Class name or instance</td>
                <td>Sets a custom installation handler</td>
            </tr>
            <tr>
                <td><code>disableCli()</code></td>
                <td></td>
                <td>Disables automatic CLI commands for installation/update/uninstall</td>
            </tr>
            <tr>
                <td><code>isCoreModule()</code></td>
                <td></td>
                <td>Marks the module as a core system module, preventing its removal or disabling from the backend</td>
            </tr>
            <tr>
                <td><code>version(int)</code></td>
                <td>Version number</td>
                <td>Sets the module version (format: YYMMDD, e.g., 250901 for 2025-09-01)</td>
            </tr>
            <tr>
                <td><code>addModels(array)</code></td>
                <td>['name' => ModelClass::class]</td>
                <td>Adds additional models for modules with multiple tables</td>
            </tr>
            <tr>
                <td><code>setJs(string)</code></td>
                <td>Path to JavaScript file</td>
                <td>Adds a JavaScript file to load. Supports relative paths (/Assets/script.js) and absolute paths (Modules/MyModule/Assets/script.js)</td>
            </tr>
            <tr>
                <td><code>setCss(string)</code></td>
                <td>Path to CSS file</td>
                <td>Adds a CSS file to load. Supports relative paths (/Assets/style.css) and absolute paths (Modules/MyModule/Assets/style.css)</td>
            </tr>
            <tr>
                <td><code>headerTitle(string)</code></td>
                <td>Title text</td>
                <td>Sets the page header title (displayed in the page header area)</td>
            </tr>
            <tr>
                <td><code>headerDescription(string)</code></td>
                <td>Description text</td>
                <td>Sets the page header description (displayed below the title)</td>
            </tr>
            <tr>
                <td><code>addHeaderLink(string, string, string)</code></td>
                <td>title, url, icon</td>
                <td>Adds a navigation link to the header (similar to menu())</td>
            </tr>
            <tr>
                <td><code>headerStyle(string)</code></td>
                <td>Style name</td>
                <td>Sets header links style: pills, tabs, underline, default (default: pills)</td>
            </tr>
            <tr>
                <td><code>headerPosition(string)</code></td>
                <td>Position name</td>
                <td>Sets header links position: top-left, top-right (default: top-left)</td>
            </tr>
        </tbody>
    </table>

    <h4 class="mt-4">Public Methods Reference (AbstractModule + Traits)</h4>
    <p>This table lists all public methods available on a module class extending <code>AbstractModule</code> in the current codebase.</p>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Method</th>
                <th>Source</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>__construct()</code></td><td>AbstractModule</td><td>Framework constructor; initializes rule builder, hooks, access rules, and extension loading.</td></tr>
            <tr><td><code>hookInit()</code></td><td>AbstractModule</td><td>Optional hook extension point for generic init phase.</td></tr>
            <tr><td><code>_cli_init()</code></td><td>AbstractModule</td><td>Internal CLI entry point called by framework hooks.</td></tr>
            <tr><td><code>_test_init()</code></td><td>AbstractModule</td><td>Internal test-context entry point.</td></tr>
            <tr><td><code>_api_init()</code></td><td>AbstractModule</td><td>Internal API-context entry point.</td></tr>
            <tr><td><code>_jobs_init()</code></td><td>AbstractModule</td><td>Internal jobs/cron init entry point.</td></tr>
            <tr><td><code>_jobs_start()</code></td><td>AbstractModule</td><td>Internal jobs start entry point.</td></tr>
            <tr><td><code>_hook_init()</code></td><td>AbstractModule</td><td>Internal web-hook init entry point.</td></tr>
            <tr><td><code>apiInit()</code></td><td>AbstractModule</td><td>Override for custom API initialization logic.</td></tr>
            <tr><td><code>cliInit()</code></td><td>AbstractModule</td><td>Override for custom CLI initialization logic.</td></tr>
            <tr><td><code>testInit()</code></td><td>AbstractModule</td><td>Override for custom test initialization logic.</td></tr>
            <tr><td><code>_bootstrap()</code></td><td>AbstractModule</td><td>Internal bootstrap orchestrator (called once per request context).</td></tr>
            <tr><td><code>setupAttributeShell()</code></td><td>AbstractModule</td><td>Registers attribute-based shell handlers.</td></tr>
            <tr><td><code>setupAttributeApi()</code></td><td>AbstractModule</td><td>Registers attribute-based API handlers.</td></tr>
            <tr><td><code>bootstrap()</code></td><td>AbstractModule</td><td>Main override point for one-time module bootstrap logic.</td></tr>
            <tr><td><code>jobsInit()</code></td><td>AbstractModule</td><td>Override for jobs initialization logic.</td></tr>
            <tr><td><code>jobsStart()</code></td><td>AbstractModule</td><td>Override for jobs start logic.</td></tr>
            <tr><td><code>init()</code></td><td>AbstractModule</td><td>Main override point for page-request module initialization.</td></tr>
            <tr><td><code>setStylesAndScripts()</code></td><td>AbstractModule</td><td>Override to register CSS/JS assets at runtime.</td></tr>
            <tr><td><code>setHeader()</code></td><td>AbstractModule</td><td>Sets runtime header data from configured rule values.</td></tr>
            <tr><td><code>afterInit()</code></td><td>AbstractModule</td><td>Optional hook called after <code>init()</code>.</td></tr>
            <tr><td><code>getTitle()</code></td><td>AbstractModule</td><td>Returns module title.</td></tr>
            <tr><td><code>getPage()</code></td><td>AbstractModule</td><td>Returns module page slug.</td></tr>
            <tr><td><code>getDisableCli()</code></td><td>AbstractModule</td><td>Returns whether CLI install/update commands are disabled.</td></tr>
            <tr><td><code>isCoreModule()</code></td><td>AbstractModule</td><td>Returns whether module is marked as core.</td></tr>
            <tr><td><code>getModel()</code></td><td>AbstractModule</td><td>Returns the main model instance for the module.</td></tr>
            <tr><td><code>getAllInstances()</code></td><td>AbstractModule</td><td>Returns all loaded module instances (static registry).</td></tr>
            <tr><td><code>getInstance(string $page)</code></td><td>AbstractModule</td><td>Returns a specific module instance by page slug.</td></tr>
            <tr><td><code>getAllModels()</code></td><td>AbstractModule</td><td>Returns map of all primary models loaded by modules.</td></tr>
            <tr><td><code>getInstall()</code></td><td>AbstractModule</td><td>Returns install component instance, creating it if needed.</td></tr>
            <tr><td><code>setupInstallClass()</code></td><td>AbstractModule</td><td>Initializes install class and related extension install components.</td></tr>
            <tr><td><code>getModuleName()</code></td><td>AbstractModule</td><td>Returns conventional module base name from class.</td></tr>
            <tr><td><code>getChildClassPath()</code></td><td>AbstractModule</td><td>Returns filesystem path of the concrete module class.</td></tr>
            <tr><td><code>access()</code></td><td>AbstractModule</td><td>Checks whether current user/session can access module.</td></tr>
            <tr><td><code>getPermissionName()</code></td><td>AbstractModule</td><td>Returns permission namespace name for module checks.</td></tr>
            <tr><td><code>getVersion()</code></td><td>AbstractModule</td><td>Returns normalized module version string.</td></tr>
            <tr><td><code>getCommonData()</code></td><td>AbstractModule</td><td>Returns common metadata array (<code>page</code>, <code>title</code>).</td></tr>
            <tr><td><code>getLoadedExtensions()</code></td><td>AbstractModule</td><td>Returns already-loaded module-level extensions for current module page.</td></tr>
            <tr><td><code>getLoadedControllerExtensions()</code></td><td>AbstractModule</td><td>Returns already-loaded controller extension instances.</td></tr>

            <tr><td><code>setupInstallationHooks()</code></td><td>InstallationTrait</td><td>Registers installation-related hooks.</td></tr>
            <tr><td><code>setupInstallationCliHooks()</code></td><td>InstallationTrait</td><td>Registers installation CLI shell commands.</td></tr>
            <tr><td><code>shellInstallModule()</code></td><td>InstallationTrait</td><td>CLI handler for module installation.</td></tr>
            <tr><td><code>shellUninstallModule()</code></td><td>InstallationTrait</td><td>CLI handler for module uninstall command.</td></tr>
            <tr><td><code>shellUpdateModule()</code></td><td>InstallationTrait</td><td>CLI handler for module update command.</td></tr>
            <tr><td><code>_installDone($html)</code></td><td>InstallationTrait</td><td>Internal finalization for install UI flow.</td></tr>
            <tr><td><code>_installGetHtmlModules(string $html, ?array $errors = [])</code></td><td>InstallationTrait</td><td>Internal builder for module install form HTML.</td></tr>
            <tr><td><code>installGetHtmlModules(string $html, $errors = [])</code></td><td>InstallationTrait</td><td>Override for custom install form rendering.</td></tr>
            <tr><td><code>installExecuteConfig($data = [])</code></td><td>InstallationTrait</td><td>Stores configuration before install execution.</td></tr>
            <tr><td><code>installExecute($data = [])</code></td><td>InstallationTrait</td><td>Main install execution method (override for custom logic).</td></tr>
            <tr><td><code>installCheckData($errors, $data = [])</code></td><td>InstallationTrait</td><td>Override for validating custom install form data.</td></tr>
            <tr><td><code>installDone($html)</code></td><td>InstallationTrait</td><td>Override for install completion HTML content.</td></tr>
            <tr><td><code>installUpdate($html)</code></td><td>InstallationTrait</td><td>Override for update completion HTML content.</td></tr>
            <tr><td><code>uninstallExecute()</code></td><td>InstallationTrait</td><td>Override for custom uninstall flow.</td></tr>
            <tr><td><code>getAdditionalModels($model_name = '')</code></td><td>InstallationTrait</td><td>Gets all or one additional model configured via <code>addModels()</code>.</td></tr>

            <tr><td><code>registerRequestAction(string $action, array|string $handler, ?string $accessLevel = null)</code></td><td>RouteControllerTrait</td><td>Registers a runtime request action handler.</td></tr>
            <tr><td><code>handleRoutes()</code></td><td>RouteControllerTrait</td><td>Main action routing entry point for module requests.</td></tr>
            <tr><td><code>relatedSearchField()</code></td><td>RouteControllerTrait</td><td>Helper endpoint for relation search/autocomplete requests.</td></tr>

            <tr><td><code>setupAttributeShellTraitCliHooks()</code></td><td>AttributeShellTrait</td><td>Registers CLI commands from <code>#[Shell]</code> attributes.</td></tr>

            <tr><td><code>setupAttributeApiTraitHooks()</code></td><td>AttributeApiTrait</td><td>Registers hooks for attribute-based API handling.</td></tr>
            <tr><td><code>registerApiEndpoints()</code></td><td>AttributeApiTrait</td><td>Registers HTTP API endpoints declared with attributes.</td></tr>
            <tr><td><code>getEndpointDocumentation(string $url)</code></td><td>AttributeApiTrait</td><td>Returns API endpoint documentation for a specific URL.</td></tr>
            <tr><td><code>getAllEndpointsDocumentation()</code></td><td>AttributeApiTrait</td><td>Returns documentation for all registered endpoints.</td></tr>

            <tr><td><code>registerHooks()</code></td><td>AttributeHookTrait</td><td>Registers hooks declared via attributes.</td></tr>
            <tr><td><code>getRegisteredHooks()</code></td><td>AttributeHookTrait</td><td>Returns list/map of hooks registered by the module.</td></tr>
        </tbody>
    </table>
    <p class="mb-0">
        Methods prefixed with <code>_</code> are framework/internal entry points. In normal modules, prefer overriding
        non-underscore methods such as <code>configure()</code>, <code>bootstrap()</code>, <code>init()</code>,
        <code>installExecute()</code>, <code>installUpdate()</code>, and <code>uninstallExecute()</code>.
    </p>

<p>If your module file and class names follow the convention, you don't have to manually append the module class to the end of the file.</p>

    <h4 class="mt-3">Complete Configuration Example</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    $rule->page('mymodule')
         ->title('My Module')
         ->access('authorized')
         ->permission(['access' => 'Access My Module', 'edit' => 'Edit My Module'])
         ->menu('Dashboard', '', 'bi bi-speedometer2', 10)
         ->menu('Settings', 'action=settings', 'bi bi-gear-fill', 20)
         ->menu('Reports', 'action=reports', 'bi bi-file-earmark-bar-graph', 30)
         ->setJs('/Assets/mymodule.js')
         ->setCss('/Assets/mymodule.css')
         ->headerTitle('My Module Dashboard')
         ->headerDescription('Comprehensive module management system')
         ->addHeaderLink('Home', '?page=mymodule', 'bi bi-house')
         ->addHeaderLink('Analytics', '?page=mymodule&action=analytics', 'bi bi-graph-up')
         ->headerStyle('pills')
         ->headerPosition('top-left')
         ->addModels([
             'Reports' => ReportsModel::class,
             'Categories' => CategoriesModel::class
         ])
         ->disableCli(false)
         ->version(250901);
}
</code></pre>

    <h4 class="mt-3">Loading JavaScript and CSS Files</h4>
    <p>The <code>setJs()</code> and <code>setCss()</code> methods automatically load JavaScript and CSS files when the module page is accessed. They support multiple path formats:</p>

    <h5 class="mt-2">Relative Paths (from module folder)</h5>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// These paths are relative to your module folder
$rule->setJs('/Assets/script.js')        // Loads: Modules/YourModule/Assets/script.js
     ->setCss('./Assets/style.css')      // Loads: Modules/YourModule/Assets/style.css
     ->setJs('Assets/main.js');          // Loads: Modules/YourModule/Assets/main.js
</code></pre>

    <h5 class="mt-2">Absolute Paths (from Modules directory)</h5>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// These paths start from the Modules directory
$rule->setJs('Modules/Auth/Assets/auth.js')           // Loads from Auth module
     ->setCss('Modules/FileManager/Assets/style.css'); // Loads from FileManager module
</code></pre>

    <h5 class="mt-2">Multiple Files</h5>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->setJs('/Assets/jquery.min.js')
     ->setJs('/Assets/main.js')
     ->setCss('/Assets/bootstrap.css')
     ->setCss('/Assets/custom.css');
</code></pre>

    <p><strong>Note:</strong> Files are loaded automatically when the module page is accessed. You don't need to manually load them in <code>init()</code> or <code>bootstrap()</code>.</p>

    <h4 class="mt-3">Configuring the Page Header</h4>
    <p>You can customize the page header with title, description, and navigation links:</p>

    <h5 class="mt-2">Header Title and Description</h5>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->headerTitle('Posts Management')
     ->headerDescription('Create, edit, and manage all your blog posts');
</code></pre>

    <h5 class="mt-2">Header Navigation Links</h5>
    <p>Add navigation links to the header using the <code>addHeaderLink()</code> method (similar to <code>menu()</code>):</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->addHeaderLink('All Posts', '?page=posts', 'bi bi-list')
     ->addHeaderLink('Add New', '?page=posts&action=new', 'bi bi-plus-circle')
     ->addHeaderLink('Categories', '?page=posts&action=categories', 'bi bi-tags')
     ->addHeaderLink('Settings', '?page=posts&action=settings', 'bi bi-gear');
</code></pre>

    <p><strong>Parameters for addHeaderLink():</strong></p>
    <ul>
        <li><code>title</code> (string, required): Display text for the link</li>
        <li><code>url</code> (string, optional): Link URL (default: empty string)</li>
        <li><code>icon</code> (string, optional): Bootstrap icon class (default: empty string)</li>
    </ul>

    <h5 class="mt-2">Customizing Style and Position</h5>
    <p>Control how the links are displayed and positioned:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->headerStyle('tabs')        // Style: pills, tabs, underline, default
     ->headerPosition('top-right'); // Position: top-left, top-right
</code></pre>

    <h5 class="mt-2">Complete Header Example</h5>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    $rule->page('blog')
         ->title('Blog')
         ->headerTitle('Blog Management System')
         ->headerDescription('Create and manage your blog content')
         ->addHeaderLink('Dashboard', '?page=blog', 'bi bi-speedometer2')
         ->addHeaderLink('New Post', '?page=blog&action=new', 'bi bi-plus-lg')
         ->addHeaderLink('Categories', '?page=blog&action=categories', 'bi bi-tags')
         ->addHeaderLink('Comments', '?page=blog&action=comments', 'bi bi-chat-dots')
         ->headerStyle('pills')
         ->headerPosition('top-left');
}
</code></pre>

    <h3 class="mt-4">Method 2: Using Protected Properties (Legacy)</h3>
    <p>The traditional way to configure a module is using protected properties. This method is still fully supported for backward compatibility.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules\Posts;

use App\Abstracts\AbstractModule;

class PostsModule extends AbstractModule
{
    protected $page = 'posts';
    protected $title = 'Posts';
    protected $access = 'authorized';
    protected $permission = ['access' => 'Access Posts Module'];
    protected $menu_links = [
        ['url' => '', 'name' => 'Posts', 'icon' => 'bi bi-file-earmark-post-fill', 'order' => 10],
        ['url' => 'action=categories', 'name' => 'Categories', 'icon' => 'bi bi-tags-fill', 'order' => 20]
    ];
    protected $version = 250901;
    protected ?array $additional_models = ['Reports' => ReportsModel::class];

    public function bootstrap()
    {
        // Bootstrap code here
    }
}

</code></pre>

    <h2 class="mt-4">Access Control</h2>
    <p>The <code>access</code> property/method defines who can access the module. Possible values:</p>
    <ul>
        <li><code>public</code>: Anyone can access, including guests</li>
        <li><code>registered</code>: Only logged-in users can access (default)</li>
        <li><code>authorized</code>: Only users with specific permissions can access (requires <code>permission</code> configuration)</li>
        <li><code>admin</code>: Only administrators can access</li>
    </ul>

    <h3 class="mt-3">Permissions for Authorized Access</h3>
    <p>When using <code>access('authorized')</code>, you must define permissions:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->access('authorized')
     ->permission(['access' => 'Access Posts', 'edit' => 'Edit Posts']);
</code></pre>
    <p>These permissions can be managed from the Users module.</p>
    <p>You can also share a single permission across multiple modules by using the <code>group.permission</code> format as the array key. If the key contains exactly one dot, the part before the dot is used as the permission group and the part after the dot is the permission key. This makes the Users module show a single permission entry that grants access to all modules using that same group and key.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->access('authorized')
     ->permission(['school.access' => 'Access School']);
</code></pre>

    <h2 class="mt-4">Menu Links</h2>
    <p>The <code>menu()</code> method adds links to the sidebar navigation. Parameters:</p>
    <ul>
        <li><code>name</code>: Display name of the link</li>
        <li><code>url</code>: URL relative to the module page (e.g., <code>'action=settings'</code> becomes <code>?page=mymodule&action=settings</code>)</li>
        <li><code>icon</code>: Bootstrap icon class (e.g., <code>'bi bi-gear-fill'</code>)</li>
        <li><code>order</code>: Display order (lower numbers appear first, default: 10)</li>
    </ul>

    <h3 class="mt-3">Menu Examples</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Single menu item
$rule->menu('Dashboard', '', 'bi bi-speedometer2', 10);

// Multiple menu items
$rule->menu('Posts', '', 'bi bi-file-post', 10)
     ->menu('Categories', 'action=categories', 'bi bi-tags', 20)
     ->menu('Settings', 'action=settings', 'bi bi-gear', 30);

// Or set all at once with menuLinks()
$rule->menuLinks([
    ['name' => 'Dashboard', 'url' => '', 'icon' => 'bi bi-speedometer2', 'order' => 10],
    ['name' => 'Settings', 'url' => 'action=settings', 'icon' => 'bi bi-gear', 'order' => 20]
]);
</code></pre>

    <h2 class="mt-4">Lifecycle Methods</h2>

    <h3 class="mt-3"><code>bootstrap()</code></h3>
    <p>The <code>bootstrap()</code> method is called when the module is being used (in pages, shell, APIs, or hooks). Use it to initialize models, routers, and other resources. It's called only once per request.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function bootstrap()
{
    // Load JavaScript/CSS
    Theme::set('javascript', Route::url().'/Modules/Posts/Assets/posts.js');
    Theme::set('styles', Route::url().'/Modules/Posts/Assets/posts.css');

    // Initialize custom resources
    $this->loadTranslations();
}
</code></pre>

    <h3 class="mt-3"><code>init()</code></h3>
    <p>The <code>init()</code> method is called only when the module page is accessed (<code>?page=module-name</code>). This is where you load page-specific assets.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function init()
{
    Theme::set('javascript', Route::url().'/Modules/Posts/Assets/page.js');
}
</code></pre>

    <h3 class="mt-3">Context-Specific Init Methods</h3>
    <p>These methods are called in specific contexts:</p>
    <ul>
        <li><code>hookInit()</code>: Called during the 'init' hook phase</li>
        <li><code>cliInit()</code>: Called when CLI commands are run</li>
        <li><code>apiInit()</code>: Called when API requests are handled</li>
        <li><code>jobsInit()</code>: Called during cron job initialization</li>
        <li><code>jobsStart()</code>: Called when background jobs start</li>
    </ul>

    <h2 class="mt-4">Multiple Tables Management</h2>
    <p>For modules that need multiple tables, use the <code>addModels()</code> method:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModuleModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('mymodule')
             ->title('My Module')
             ->addModels([
                 'Reports' => ReportsModel::class,
                 'Categories' => CategoriesModel::class
             ]);
    }

}
</code></pre>

    <p>Access additional models using <code>getAdditionalModels()</code>:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get all additional models
$models = $this->getAdditionalModels();

// Get a specific model by name
$reportsModel = $this->getAdditionalModels('Reports');
</code></pre>

    <h2 class="mt-4">Module Installation</h2>
    <p>Modules are installed by placing them in the <code>/Modules/</code> folder. If the module has a model, it can use automatic CLI commands for installation:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">php milkadmin/cli.php {module-name}:install
php milkadmin/cli.php {module-name}:update
php milkadmin/cli.php {module-name}:uninstall
</code></pre>

    <p>If the model properly implements <code>buildTable()</code>, no additional code is needed. Otherwise, you can override these methods:</p>

    <h3 class="mt-3"><code>installExecute()</code></h3>
    <p>Executes module installation. Override to customize installation process:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function installExecute(): void
{
    // Create tables
    $this->model->buildTable();

    // Insert default data
    $this->model->store(['title' => 'Default Post']);
    
    // Save configuration
    Config::set('posts_per_page', 10);
}
</code></pre>

    <h3 class="mt-3"><code>installUpdate($html)</code></h3>
    <p>Called after module update. Returns HTML for the update completion page:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function installUpdate($html): string
{
    // Update tables
    $this->model->buildTable();

    // Perform data migration if needed
    // ...

    $html .= '<div class="alert alert-success">Module updated successfully!</div>';
    return $html;
}
</code></pre>

    <h3 class="mt-3"><code>uninstallModule()</code></h3>
    <p>Called during module uninstallation. The module folder is not deleted but disabled (renamed with a dot prefix):</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function uninstallModule()
{
    // Drop additional tables first
    $models = $this->getAdditionalModels();
    foreach ($models as $model) {
        $model->dropTable();
    }

    // Delete configuration
    Config::delete('posts_per_page');

    // Call parent to drop main table
    parent::uninstallModule();
}
</code></pre>

    <h3 class="mt-3">Installation Form Validation</h3>
    <p>To add custom fields to the installation form and validate them:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function installCheckData($errors, array $data = []): array
{
    if (empty($data['api_key'])) {
        $errors['api_key'] = 'API Key is required';
    }

    if (strlen($data['api_key']) < 20) {
        $errors['api_key'] = 'API Key must be at least 20 characters';
    }

    return $errors;
}
</code></pre>

    <h3 class="mt-3">Disabling CLI Commands</h3>
    <p>To prevent a module from having automatic CLI installation commands:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    $rule->page('system')
         ->title('System Module')
         ->disableCli(true); // No CLI commands
}
</code></pre>

    <h2 class="mt-4">CLI Commands (Shell)</h2>
    <p>Use PHP attributes to define CLI commands:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Attributes\Shell;
use App\Cli;

#[Shell('test')]
public function testCommand()
{
    Cli::success("Command executed successfully!");
}

#[Shell('import')]
public function importCommand($file)
{
    Cli::info("Importing file: $file");
    // Import logic here
}
</code></pre>

    <p>Execute from command line:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">php milkadmin/cli.php posts:test
php milkadmin/cli.php posts:import data.csv
</code></pre>

    <h2 class="mt-4">Version Management</h2>
    <p>The version property uses the format YYMMDD (year, month, day):</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->version(250901);  // Version for September 1, 2025
$rule->version(250902); // Version for September 2, 2025
</code></pre>


</div>
