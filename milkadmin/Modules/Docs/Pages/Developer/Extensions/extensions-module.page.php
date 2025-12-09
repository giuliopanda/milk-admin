<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
* @title Module Extensions
* @order 15
* @tags extensions, module-extensions, AbstractModuleExtension, ui-components, permissions, header-links, bootstrap, init, lifecycle-hooks, module-configuration, audit-ui, navigation
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

   <h1>Module Extensions</h1>

   <p>Module Extensions allow you to extend module functionality by adding UI components, permissions, navigation links, and lifecycle hooks without modifying the module class itself. They extend the <code>AbstractModuleExtension</code> class.</p>

   <h2>Creating a Module Extension</h2>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\MyExtension;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};

class Module extends AbstractModuleExtension
{
    // Configuration parameters
    protected $show_in_header = true;

    // Add UI components during configuration
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        if ($this->show_in_header) {
            $rule_builder->addHeaderLink(
                'My Link',
                '?page=mymodule&action=custom',
                'bi bi-star'
            );
        }
    }

    // Called after module bootstrap
    public function bootstrap(): void
    {
        // Initialize extension
    }

    // Called on each page load
    public function init(): void
    {
        // Page-specific initialization
    }
}</code></pre>

   <h2 class="mt-4">Extension Parameters</h2>

   <p>Extensions can have configurable parameters defined as protected properties:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class Module extends AbstractModuleExtension
{
    protected $show_in_list = true;
    protected $link_position = 'top-left';
}</code></pre>

   <p>Pass parameters when adding the extension:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule_builder->addExtension('MyExtension', [
    'show_in_list' => false,
    'link_position' => 'top-right'
]);</code></pre>

   <h2 class="mt-4">Configuration Hook</h2>

   <p>The <code>configure()</code> method is called during module initialization. Use it to add UI elements and permissions:</p>

   <h3 class="mt-4">Adding Header Links</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure(ModuleRuleBuilder $rule_builder): void
{
    $page = $this->module->getPage();

    $rule_builder
        ->addHeaderLink(
            'Audit Trail',                    // Link text
            "?page={$page}&action=audit",    // URL
            'bi bi-database'                  // Icon class
        )
        ->headerPosition('top-left');        // Position: top-left, top-right, etc.
}</code></pre>

   <h3 class="mt-4">Adding Permissions</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure(ModuleRuleBuilder $rule_builder): void
{
    $rule_builder->addPermissions([
        'audit' => 'View Audit Trail',
        'export' => 'Export Data'
    ]);
}</code></pre>

   <h3 class="mt-4">Checking Permissions</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Permissions;

public function configure(ModuleRuleBuilder $rule_builder): void
{
    $page = $this->module->getPage();

    // Only add link if user has permission
    if (Permissions::check($page . ".audit")) {
        $rule_builder->addHeaderLink(
            'Audit Trail',
            "?page={$page}&action=audit",
            'bi bi-database'
        );
    }
}</code></pre>

   <h2 class="mt-4">Lifecycle Hooks</h2>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Hook</th>
            <th>When Called</th>
            <th>Use Case</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>configure()</code></td>
            <td>During module configuration</td>
            <td>Add UI elements, permissions, settings</td>
         </tr>
         <tr>
            <td><code>bootstrap()</code></td>
            <td>After module initialization (once)</td>
            <td>Initialize services, register hooks, load dependencies</td>
         </tr>
         <tr>
            <td><code>init()</code></td>
            <td>On each page load</td>
            <td>Load page-specific assets, track page views</td>
         </tr>
      </tbody>
   </table>

   <h3 class="mt-4">bootstrap() Example</h3>
   <p>Called once after module initialization. Use for one-time setup:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function bootstrap(): void
{
    // Load extension dependencies
    require_once __DIR__ . '/AuditModel.php';

    // Configure extension settings
    \Extensions\Audit\Model::setMaxAuditRecords(100);

    // Register global hooks
    Hooks::set('model_save', [$this, 'onModelSave']);

    // Log initialization
    $page = $this->module->getPage();
    error_log("Extension loaded for: {$page}");
}</code></pre>

   <h3 class="mt-4">init() Example</h3>
   <p>Called on every page request. Use for page-specific initialization:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function init(): void
{
    // Load page-specific JavaScript/CSS
    Theme::set('javascript', Route::url() . '/Extensions/Audit/Assets/audit.js');
    Theme::set('styles', Route::url() . '/Extensions/Audit/Assets/audit.css');

    // Track page view
    $page = $this->module->getPage();
    $action = $_REQUEST['action'] ?? 'home';
    error_log("Page view: {$page}/{$action}");
}</code></pre>

   <h2 class="mt-4">Accessing the Parent Module</h2>

   <p>Extensions have access to the parent module via <code>$this->module->get()</code>:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function myMethod()
{
    $module = $this->module->get();

    $page = $module->getPage();
    $title = $module->getTitle();
    $permissions = $module->getPermissions();

    // Access module configuration
    $config = $module->getConfig();
}</code></pre>

   <h2 class="mt-4">Complete Example: Audit Module Extension</h2>

   <p>This extension adds audit trail UI and functionality to modules:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\Audit;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};
use App\{Permissions, Get};

class Module extends AbstractModuleExtension
{
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // Initialize Auth to check permissions
        Get::make('Auth');

        $page = $this->module->getPage();

        // Add header link if user has permission
        if (Permissions::check($page . ".audit")) {
            $rule_builder
                ->addHeaderLink(
                    'Audit Trail',
                    "?page={$page}&action=audit",
                    'bi bi-database'
                )
                ->headerPosition('top-left');
        }

        // Add audit permission
        $rule_builder->addPermissions([
            'audit' => 'View Audit Trail'
        ]);
    }

    public function bootstrap(): void
    {
        // Load audit model
        require_once __DIR__ . '/AuditModel.php';

        // Configure audit settings
        \Extensions\Audit\Model::setMaxAuditRecords(100);
        \Extensions\Audit\Model::setSessionTimeWindow(18000); // 5 hours

        // Log module initialization
        $page = $this->module->getPage();
        $title = $this->module->getTitle();
        error_log("Audit extension loaded: {$page} ({$title})");
    }

    public function init(): void
    {
        // Load audit-specific assets for the page
        $action = $_REQUEST['action'] ?? 'home';

        if ($action === 'audit') {
            // Load audit viewer JavaScript
            Theme::set('javascript', Route::url() . '/Extensions/Audit/Assets/audit-viewer.js');
        }
    }
}</code></pre>

   <h2 class="mt-4">UI Customization</h2>

   <h3 class="mt-4">Header Link Positions</h3>
   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Position</th>
            <th>Description</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>top-left</code></td>
            <td>Top navigation bar, left side</td>
         </tr>
         <tr>
            <td><code>top-right</code></td>
            <td>Top navigation bar, right side</td>
         </tr>
         <tr>
            <td><code>sidebar</code></td>
            <td>Left sidebar menu</td>
         </tr>
      </tbody>
   </table>

   <h3 class="mt-4">Icon Classes</h3>
   <p>Use Bootstrap Icons classes for header links:</p>
   <ul>
      <li><code>bi bi-database</code> - Database icon</li>
      <li><code>bi bi-person</code> - Person icon</li>
      <li><code>bi bi-gear</code> - Settings icon</li>
      <li><code>bi bi-file-earmark</code> - File icon</li>
   </ul>
   <p>See <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a> for more options.</p>

   <h2 class="mt-4">Example: Author Extension</h2>

   <p>Simple extension that adds author tracking UI:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\Author;

class Module extends AbstractModuleExtension
{
    protected $show_username = true;
    protected $show_in_list = true;

    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // No UI elements needed for author tracking
        // All functionality is handled in the Model extension
    }

    public function getDisplayField(): string
    {
        return $this->show_email ? 'email' : 'username';
    }

    public function shouldShowInList(): bool
    {
        return $this->show_in_list;
    }
}</code></pre>

   <h2 class="mt-4">Best Practices</h2>

   <ul>
      <li><strong>Check permissions</strong> - Always verify user permissions before adding UI elements</li>
      <li><strong>Use parameters</strong> - Make extensions configurable for different use cases</li>
      <li><strong>Initialize Auth early</strong> - Call <code>Get::make('Auth')</code> in configure() if checking permissions</li>
      <li><strong>Load assets conditionally</strong> - Only load JavaScript/CSS when needed</li>
      <li><strong>Keep bootstrap light</strong> - Heavy initialization should be done in init() or lazily</li>
      <li><strong>Use WeakReference</strong> - The parent module is stored as a WeakReference to prevent memory leaks</li>
      <li><strong>Log important events</strong> - Use error_log() for debugging and tracking</li>
   </ul>

   <h2 class="mt-4">Combining Module and Model Extensions</h2>

   <p>Most extensions have both Module and Model components working together:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code>Extensions/
└── Audit/
    ├── Module.php        # Adds UI (header links, permissions)
    ├── Model.php         # Adds functionality (audit trail tracking)
    ├── AuditModel.php    # Separate model for audit data
    └── Controller.php    # Handles audit viewer pages</code></pre>

   <p>Add both in your module configuration:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// In Module configure()
$rule_builder->addExtension('Audit');

// In Model configure()
$rule_builder->addExtension('Audit');</code></pre>

   <h2 class="mt-4">See Also</h2>

   <ul>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-introduction'); ?>">Extensions Introduction</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-model'); ?>">Model Extensions</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/AbstractsClass/abstract-module'); ?>">AbstractModule</a></li>
   </ul>

</div>
