<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
* @title Creating Custom Extensions
* @order 20
* @tags extensions, custom-extensions, extension-development, best-practices, testing, debugging, reusable-components, extension-structure, global-extensions, module-specific-extensions
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

   <h1>Creating Custom Extensions</h1>
    <p class="text-muted">Revision: 2025/12/02</p>
   <p>Learn how to create your own reusable extensions to standardize behaviors across your modules.</p>

   <h2>Extension Structure</h2>

   <p>Extensions can have multiple components:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code>Extensions/
└── MyExtension/
    ├── Module.php        # Module extension (UI, permissions)
    ├── Model.php         # Model extension (fields, data processing)
    ├── Install.php       # Optional: Installation/migration logic
    ├── Controller.php    # Optional: Custom pages/actions
    ├── Shell.php         # Optional: CLI commands
    ├── Api.php           # Optional: API endpoints
    ├── Hook.php          # Optional: System hooks
    ├── Assets/           # Optional: JavaScript/CSS
    │   ├── script.js
    │   └── style.css
    └── Views/            # Optional: Custom views
        └── page.php</code></pre>

   <h2 class="mt-4">Step-by-Step: Creating a Timestamp Extension</h2>

   <p>Let's create an extension that automatically adds created_at and updated_at timestamps to any model.</p>

   <h3 class="mt-4">Step 1: Create Extension Folder</h3>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash"># For global extensions
mkdir -p milkadmin/Extensions/Timestamp

# For module-specific extensions
mkdir -p Modules/MyModule/Extensions/Timestamp</code></pre>

   <h3 class="mt-4">Step 2: Create Model Extension</h3>

   <p>File: <code>Extensions/Timestamp/Model.php</code></p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Extensions\Timestamp;

use App\Abstracts\{AbstractModelExtension, RuleBuilder};
use App\Attributes\{ToDatabaseValue};

!defined('MILK_DIR') && die();

/**
 * Timestamp Model Extension
 *
 * Automatically adds and manages created_at and updated_at timestamps.
 */
class Model extends AbstractModelExtension
{
    /**
     * Include created_at field
     * @var bool
     */
    protected $include_created_at = true;

    /**
     * Include updated_at field
     * @var bool
     */
    protected $include_updated_at = true;

    /**
     * Add timestamp fields to the model
     */
    public function configure(RuleBuilder $rule_builder): void
    {
        if ($this->include_created_at) {
            $rule_builder
                ->timestamp('created_at')
                ->default('CURRENT_TIMESTAMP')
                ->label('Created At');
        }

        if ($this->include_updated_at) {
            $rule_builder
                ->timestamp('updated_at')
                ->default('CURRENT_TIMESTAMP')
                ->onUpdate('CURRENT_TIMESTAMP')
                ->label('Updated At');
        }
    }

    /**
     * Preserve created_at on updates
     */
    #[ToDatabaseValue('created_at')]
    public function preserveCreatedAt($current_record)
    {
        // On insert, use default
        if (empty($current_record->created_at)) {
            return date('Y-m-d H:i:s');
        }

        // On update, preserve original value
        $id_field = $this->model->get()->getPrimaryKey();
        if (!empty($current_record->$id_field)) {
            $old_record = $this->model->get()->getById($current_record->$id_field);
            if ($old_record && isset($old_record->created_at)) {
                return $old_record->created_at;
            }
        }

        return $current_record->created_at;
    }

    /**
     * Always update updated_at on save
     */
    #[ToDatabaseValue('updated_at')]
    public function setUpdatedAt($current_record)
    {
        return date('Y-m-d H:i:s');
    }
}</code></pre>

   <h3 class="mt-4">Step 3: Create Module Extension (Optional)</h3>

   <p>File: <code>Extensions/Timestamp/Module.php</code></p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Extensions\Timestamp;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};

!defined('MILK_DIR') && die();

/**
 * Timestamp Module Extension
 *
 * No UI components needed - all functionality is in Model extension.
 */
class Module extends AbstractModuleExtension
{
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // No additional module configuration needed
    }
}</code></pre>

   <h3 class="mt-4">Step 4: Use the Extension</h3>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModel extends AbstractModel
{
    public function configure(RuleBuilder $rule_builder): void
    {
        $rule_builder
            ->table('#__my_table')
            ->id()
            ->string('title', 255)

            // Add timestamp extension
            ->addExtension('Timestamp', [
                'include_created_at' => true,
                'include_updated_at' => true
            ]);
    }
}</code></pre>

   <h2 class="mt-4">Advanced Example: Soft Delete Extension</h2>

   <p>An extension that adds soft delete functionality (mark as deleted instead of removing):</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Extensions\SoftDelete;

use App\Abstracts\{AbstractModelExtension, RuleBuilder};
use App\Attributes\{ToDatabaseValue};

class Model extends AbstractModelExtension
{
    /**
     * Field name for deleted flag
     * @var string
     */
    protected $field_name = 'deleted_at';

    /**
     * Add soft delete field
     */
    public function configure(RuleBuilder $rule_builder): void
    {
        $rule_builder
            ->timestamp($this->field_name)
            ->nullable(true)
            ->default(null)
            ->label('Deleted At');
    }

    /**
     * Override beforeDelete to mark as deleted instead
     */
    public function beforeDelete($ids)
    {
        $model = $this->model->get();

        // Mark records as deleted instead of removing
        foreach ($ids as $id) {
            $record = $model->getById($id);
            if ($record) {
                $record->{$this->field_name} = date('Y-m-d H:i:s');
                $model->store((array)$record);
            }
        }

        // Prevent actual deletion
        return false;
    }

    /**
     * Hook into queries to filter out deleted records
     */
    public function onAttributeMethodsScanned(): void
    {
        parent::onAttributeMethodsScanned();

        // Auto-filter deleted records in queries
        $model = $this->model->get();
        $model->where("{$this->field_name} IS NULL");
    }
}</code></pre>

   <h2 class="mt-4">Extension Development Checklist</h2>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Step</th>
            <th>Description</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td>1. Plan</td>
            <td>Define what behavior the extension should provide</td>
         </tr>
         <tr>
            <td>2. Structure</td>
            <td>Decide which components you need: Module, Model, Shell, Api, Hook, Controller</td>
         </tr>
         <tr>
            <td>3. Parameters</td>
            <td>Define configurable protected properties</td>
         </tr>
         <tr>
            <td>4. Configure</td>
            <td>Add fields, permissions, UI elements in configure()</td>
         </tr>
         <tr>
            <td>5. Attributes</td>
            <td>Use attributes for data processing and validation</td>
         </tr>
         <tr>
            <td>6. Hooks</td>
            <td>Implement lifecycle hooks (bootstrap, init, afterSave, etc.)</td>
         </tr>
         <tr>
            <td>7. Test</td>
            <td>Test with a simple module first</td>
         </tr>
         <tr>
            <td>8. Document</td>
            <td>Add docblocks and usage examples</td>
         </tr>
      </tbody>
   </table>

   <h2 class="mt-4">Best Practices</h2>

   <h3 class="mt-4">Naming Conventions</h3>
   <ul>
      <li>Extension folder: <code>PascalCase</code> (e.g., <code>Timestamp</code>, <code>SoftDelete</code>)</li>
      <li>Class files: <code>Module.php</code>, <code>Model.php</code></li>
      <li>Namespace: <code>Extensions\ExtensionName</code></li>
      <li>Parameters: <code>snake_case</code> (e.g., <code>$include_created_at</code>)</li>
   </ul>

   <h3 class="mt-4">Code Quality</h3>
   <ul>
      <li><strong>Single Responsibility</strong> - Each extension should handle one specific behavior</li>
      <li><strong>Configurable</strong> - Use parameters to make extensions flexible</li>
      <li><strong>Non-Breaking</strong> - Extensions should not break existing functionality</li>
      <li><strong>Documented</strong> - Add docblocks for all properties and methods</li>
      <li><strong>Tested</strong> - Test with different configurations and edge cases</li>
   </ul>

   <h3 class="mt-4">Performance</h3>
   <ul>
      <li><strong>Lazy Loading</strong> - Load dependencies only when needed</li>
      <li><strong>Caching</strong> - Use static caches for repeated queries</li>
      <li><strong>Avoid N+1</strong> - Batch database queries when possible</li>
      <li><strong>WeakReference</strong> - Parent model/module are stored as WeakReferences</li>
   </ul>

   <h2 class="mt-4">Testing Your Extension</h2>

   <h3 class="mt-4">1. Create a Test Module</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class TestExtensionModule extends AbstractModule
{
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        $rule_builder
            ->page('test_extension')
            ->title('Test Extension')
            ->addExtension('MyExtension', [
                'parameter1' => true
            ]);
    }
}</code></pre>

   <h3 class="mt-4">2. Create a Test Model</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class TestExtensionModel extends AbstractModel
{
    public function configure(RuleBuilder $rule_builder): void
    {
        $rule_builder
            ->table('#__test_extension')
            ->id()
            ->string('title', 255)
            ->addExtension('MyExtension');
    }
}</code></pre>

   <h3 class="mt-4">3. Test Scenarios</h3>
   <ul>
      <li>Test with default parameters</li>
      <li>Test with custom parameters</li>
      <li>Test insert, update, delete operations</li>
      <li>Test data formatting (raw, formatted, sql)</li>
      <li>Test permissions and UI elements</li>
      <li>Test edge cases (null values, missing data)</li>
   </ul>

   <h2 class="mt-4">Debugging Extensions</h2>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure(RuleBuilder $rule_builder): void
{
    // Log extension initialization
    error_log("MyExtension initialized with parameters: " . json_encode([
        'param1' => $this->param1,
        'param2' => $this->param2
    ]));

    // Debug parent model
    $model = $this->model->get();
    error_log("Parent model table: " . $model->getTable());
}

#[ToDatabaseValue('my_field')]
public function processMyField($current_record)
{
    // Log processing
    $value = $current_record->my_field;
    error_log("Processing my_field: {$value}");

    return $processed_value;
}</code></pre>

   <h2 class="mt-4">Sharing Extensions</h2>

   <p>To share your extension with other projects:</p>

   <ol>
      <li><strong>Document parameters</strong> - List all configurable options with defaults</li>
      <li><strong>Add usage examples</strong> - Show how to use the extension</li>
      <li><strong>List requirements</strong> - Note any dependencies or required modules</li>
      <li><strong>Package as folder</strong> - Share the entire extension folder</li>
      <li><strong>Include Install.php</strong> - For extensions requiring setup</li>
   </ol>

   <h2 class="mt-4">Common Extension Patterns</h2>

   <h3 class="mt-4">Auto-Fill Current User</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDatabaseValue('user_id')]
public function setUserId($current_record)
{
    if (empty($current_record->user_id)) {
        $user = Get::make('Auth')->getUser();
        return $user->id ?? 0;
    }
    return $current_record->user_id;
}</code></pre>

   <h3 class="mt-4">Preserve Field on Update</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDatabaseValue('created_at')]
public function preserveCreatedAt($current_record)
{
    $id_field = $this->model->get()->getPrimaryKey();

    if (!empty($current_record->$id_field)) {
        $old = $this->model->get()->getById($current_record->$id_field);
        return $old->created_at ?? $current_record->created_at;
    }

    return $current_record->created_at;
}</code></pre>

   <h3 class="mt-4">Format for Display</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDisplayValue('status')]
public function formatStatus($current_record)
{
    $statuses = [
        0 => 'Inactive',
        1 => 'Active',
        2 => 'Pending'
    ];

    return $statuses[$current_record->status] ?? 'Unknown';
}</code></pre>

   <h2 class="mt-4">Complete Extension Example</h2>

   <p>Here's a complete example showing how to use an extension with all its components:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// In your Model
class MyModel extends AbstractModel
{
    public function configure(RuleBuilder $rule_builder): void
    {
        $rule_builder
            ->table('#__my_table')
            ->id()
            ->string('title', 255)

            // Add the extension with parameters
            ->addExtension('MyExtension', [
                'enable_api' => true,
                'enable_hooks' => true,
                'custom_setting' => 'value'
            ]);
    }
}

// In your Module
class MyModule extends AbstractModule
{
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        $rule_builder
            ->page('my_module')
            ->title('My Module')

            // Add the extension to the module too
            ->addExtension('MyExtension', [
                'enable_api' => true
            ]);
    }
}</code></pre>

   <p>The extension will automatically load all its components (Module.php, Model.php, Shell.php, Api.php, Hook.php) and pass the parameters to each component.</p>

   <h2 class="mt-4">Shell, API, and Hook Extensions</h2>

   <p>Extensions can also include Shell, API, and Hook components to extend CLI commands, API endpoints, and system hooks.</p>

   <h3 class="mt-4">Shell Extension (CLI Commands)</h3>

   <p>Shell extensions add CLI command functionality to your extension.</p>

   <p>File: <code>Extensions/MyExtension/Shell.php</code></p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Extensions\MyExtension;

use App\Abstracts\AbstractShellExtension;

!defined('MILK_DIR') && die();

/**
 * MyExtension Shell Extension
 */
class Shell extends AbstractShellExtension
{
    /**
     * Hook called after Shell initialization
     * At this point, module/model are not yet available
     */
    public function onInit(): void
    {
        // Initialize CLI helpers or global command handlers
        // Example: Register custom CLI handlers
    }

    /**
     * Hook called after setHandleShell
     * Module, page, and model are now available
     */
    public function onSetup(): void
    {
        // Shell is now fully configured
        // Access module: $this->module->get()
        // Access model: $this->module->get()->getModel()
        // Access page: $this->module->get()->getPage()

        // Example: Log CLI initialization
        \App\Cli::info('MyExtension Shell loaded for: ' . $this->module->get()->getPage());
    }
}</code></pre>

   <h3 class="mt-4">API Extension (REST Endpoints)</h3>

   <p>API extensions add REST API endpoints to your extension using the <code>#[ApiEndpoint]</code> attribute.</p>

   <p>File: <code>Extensions/MyExtension/Api.php</code></p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Extensions\MyExtension;

use App\Abstracts\AbstractApiExtension;
use App\Attributes\ApiEndpoint;

!defined('MILK_DIR') && die();

/**
 * MyExtension API Extension
 */
class Api extends AbstractApiExtension
{
    /**
     * Hook called after API initialization
     * At this point, module/model are not yet available
     */
    public function onInit(): void
    {
        // Set up API middleware or global handlers
    }

    /**
     * Hook called after setHandleApi
     * Module, page, and model are now available
     */
    public function onSetup(): void
    {
        // API is now fully configured
        // Access module: $this->module->get()
        // Access model: $this->module->get()->getModel()
        // Access page: $this->module->get()->getPage()
    }

    /**
     * Example API endpoint
     * Accessible at: /api/myextension/status
     */
    #[ApiEndpoint('/myextension/status', 'GET')]
    public function getStatus()
    {
        return [
            'status' => 'ok',
            'module' => $this->module->get()->getPage(),
            'extension' => 'MyExtension'
        ];
    }

    /**
     * POST endpoint with data processing
     * Accessible at: /api/myextension/process
     */
    #[ApiEndpoint('/myextension/process', 'POST')]
    public function processData()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Process data using the model
        $model = $this->module->get()->getModel();
        $result = $model->store($data);

        return [
            'success' => true,
            'id' => $result
        ];
    }
}</code></pre>

   <h3 class="mt-4">Hook Extension (System Hooks)</h3>

   <p>Hook extensions register callbacks to system hooks using the <code>#[HookCallback]</code> attribute.</p>

   <p>File: <code>Extensions/MyExtension/Hook.php</code></p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Extensions\MyExtension;

use App\Abstracts\AbstractHookExtension;
use App\Attributes\HookCallback;

!defined('MILK_DIR') && die();

/**
 * MyExtension Hook Extension
 */
class Hook extends AbstractHookExtension
{
    /**
     * Hook called after Hook initialization
     */
    public function onInit(): void
    {
        // Set up extension state or logging
    }

    /**
     * Hook called after all hooks are registered
     * Main class and extension hooks are now registered
     */
    public function onRegisterHooks(): void
    {
        // All hooks have been registered
        // Access registered hooks: $this->hook->getRegisteredHooks();
    }

    /**
     * Example hook callback for initialization
     * Called when Hooks::run('init') is executed
     *
     * @param array $data Hook data
     * @return array Modified data
     */
    #[HookCallback('init', 10)]
    public function onInitHook($data = [])
    {
        // Execute custom logic on init
        // Priority: 10 (lower number = higher priority)
        return $data;
    }

    /**
     * Example hook for user login events
     * Called when Hooks::run('user.login', $userData) is executed
     *
     * @param array $user_data User login data
     * @return array Modified user data
     */
    #[HookCallback('user.login', 20)]
    public function onUserLogin($user_data)
    {
        // Log user login or perform additional checks
        $model = $this->module->get()->getModel();

        // Store login event
        $model->store([
            'user_id' => $user_data['id'] ?? 0,
            'event' => 'login',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        return $user_data;
    }

    /**
     * Example hook for before save operations
     *
     * @param object $record Record being saved
     * @return object Modified record
     */
    #[HookCallback('before.save', 5)]
    public function beforeSave($record)
    {
        // Modify record before saving
        $record->modified_by_extension = 'MyExtension';
        return $record;
    }
}</code></pre>

   <h3 class="mt-4">Lifecycle Hooks Summary</h3>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Extension Type</th>
            <th>Lifecycle Hooks</th>
            <th>Purpose</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><strong>Module</strong></td>
            <td><code>configure()</code>, <code>bootstrap()</code>, <code>init()</code></td>
            <td>UI configuration, module setup, page initialization</td>
         </tr>
         <tr>
            <td><strong>Model</strong></td>
            <td><code>configure()</code>, <code>onAttributeMethodsScanned()</code></td>
            <td>Field configuration, attribute registration</td>
         </tr>
         <tr>
            <td><strong>Shell</strong></td>
            <td><code>onInit()</code>, <code>onSetup()</code></td>
            <td>CLI initialization, command setup</td>
         </tr>
         <tr>
            <td><strong>Api</strong></td>
            <td><code>onInit()</code>, <code>onSetup()</code></td>
            <td>API initialization, endpoint setup</td>
         </tr>
         <tr>
            <td><strong>Hook</strong></td>
            <td><code>onInit()</code>, <code>onRegisterHooks()</code></td>
            <td>Hook initialization, callback registration</td>
         </tr>
      </tbody>
   </table>

   <h3 class="mt-4">Attributes Reference</h3>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Attribute</th>
            <th>Extension Type</th>
            <th>Usage</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>#[ToDatabaseValue]</code></td>
            <td>Model</td>
            <td>Process field value before saving to database</td>
         </tr>
         <tr>
            <td><code>#[ToDisplayValue]</code></td>
            <td>Model</td>
            <td>Format field value for display</td>
         </tr>
         <tr>
            <td><code>#[SetValue]</code></td>
            <td>Model</td>
            <td>Set field value programmatically</td>
         </tr>
         <tr>
            <td><code>#[Validate]</code></td>
            <td>Model</td>
            <td>Custom field validation</td>
         </tr>
         <tr>
            <td><code>#[ApiEndpoint]</code></td>
            <td>Api</td>
            <td>Register REST API endpoint (path, method)</td>
         </tr>
         <tr>
            <td><code>#[HookCallback]</code></td>
            <td>Hook</td>
            <td>Register system hook callback (name, priority)</td>
         </tr>
      </tbody>
   </table>

   <h2 class="mt-4">See Also</h2>

   <ul>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-introduction'); ?>">Extensions Introduction</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-model'); ?>">Model Extensions</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-module'); ?>">Module Extensions</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-attributes'); ?>">Model Attributes</a></li>
   </ul>

</div>
