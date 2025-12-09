<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
* @title Extensions Introduction
* @order 5
* @tags extensions, extension-system, AbstractModelExtension, AbstractModuleExtension, reusable-components, module-extensions, model-extensions, behaviors, standardization, code-reuse, audit, author, custom-extensions
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

   <h1>Extensions System</h1>

   <p>Extensions are reusable code components that can be added to modules to extend their functionality with standard behaviors. They help you avoid code duplication by centralizing common features like audit trails, author tracking, timestamps, and more.</p>

   <h2>What are Extensions?</h2>

   <p>Extensions are separate classes that can be attached to modules and models through their <code>configure()</code> methods. They can:</p>

   <ul>
      <li><strong>Add fields</strong> to your data structure automatically</li>
      <li><strong>Process data</strong> before saving or displaying</li>
      <li><strong>Add UI elements</strong> like header links and buttons</li>
      <li><strong>Hook into lifecycle events</strong> (bootstrap, init, save, delete)</li>
      <li><strong>Extend validation</strong> with custom rules</li>
      <li><strong>Register custom handlers</strong> for data formatting</li>
   </ul>

   <h2>Extension Types</h2>

   <h3 class="mt-4">Module Extensions</h3>
   <p>Extend module functionality by adding UI elements, permissions, and hooks.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\MyExtension;

class Module extends AbstractModuleExtension
{
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // Add header links, permissions, etc.
    }
}</code></pre>

   <h3 class="mt-4">Model Extensions</h3>
   <p>Extend model functionality by adding fields, validation, and data processing.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\MyExtension;

class Model extends AbstractModelExtension
{
    public function configure(RuleBuilder $rule_builder): void
    {
        // Add fields to the model
    }
}</code></pre>

   <h2 class="mt-4">Extension Locations</h2>

   <p>Extensions can be stored in two locations:</p>

   <h3 class="mt-4">Global Extensions</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>milkadmin/Extensions/
└── MyExtension/
    ├── Module.php           # Module extension
    ├── Model.php            # Model extension
    └── Install.php          # Optional installation
</code></pre>
   <p>Available to all modules in your application.</p>

   <h3 class="mt-4">Module-Specific Extensions</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>Modules/
└── MyModule/
    └── Extensions/
        └── CustomBehavior/
            ├── Module.php
            └── Model.php
</code></pre>
   <p>Available only to the specific module.</p>

   <h2 class="mt-4">Using Extensions</h2>

   <h3 class="mt-4">In Module Configuration</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModule extends AbstractModule
{
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        $rule_builder
            ->addExtension('Audit')              // Global extension
            ->addExtension('Author', [           // With parameters
                'show_username' => true,
                'show_in_list' => true
            ]);
    }
}</code></pre>

   <h3 class="mt-4">In Model Configuration</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModel extends AbstractModel
{
    public function configure(RuleBuilder $rule_builder): void
    {
        $rule_builder
            ->addExtension('Audit')              // Global extension
            ->addExtension('Author', [           // With parameters
                'show_email' => false
            ]);
    }
}</code></pre>

   <h2 class="mt-4">Built-in Extensions</h2>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Extension</th>
            <th>Type</th>
            <th>Description</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><strong>Audit</strong></td>
            <td>Module + Model</td>
            <td>Complete audit trail system tracking all changes to records with full snapshots, user info, and timestamps</td>
         </tr>
         <tr>
            <td><strong>Author</strong></td>
            <td>Module + Model</td>
            <td>Tracks who created each record with automatic user ID capture and formatted display</td>
         </tr>
      </tbody>
   </table>

   <h2 class="mt-4">Extension Parameters</h2>

   <p>Extensions can accept configuration parameters that customize their behavior:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// In extension class
class Model extends AbstractModelExtension
{
    protected $show_username = true;    // Default value
    protected $show_email = false;
}

// When adding extension
$rule_builder->addExtension('Author', [
    'show_username' => false,
    'show_email' => true
]);</code></pre>

   <p>Parameters are automatically applied to protected properties via <code>applyParameters()</code>.</p>

   <h2 class="mt-4">Extension Lifecycle Hooks</h2>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Hook</th>
            <th>When Called</th>
            <th>Available In</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>configure()</code></td>
            <td>During module/model configuration</td>
            <td>Module, Model</td>
         </tr>
         <tr>
            <td><code>bootstrap()</code></td>
            <td>After module initialization</td>
            <td>Module</td>
         </tr>
         <tr>
            <td><code>init()</code></td>
            <td>On page load (per-request)</td>
            <td>Module</td>
         </tr>
         <tr>
            <td><code>afterSave()</code></td>
            <td>After records are saved</td>
            <td>Model</td>
         </tr>
         <tr>
            <td><code>beforeDelete()</code></td>
            <td>Before records are deleted</td>
            <td>Model</td>
         </tr>
         <tr>
            <td><code>afterDelete()</code></td>
            <td>After records are deleted</td>
            <td>Model</td>
         </tr>
      </tbody>
   </table>

   <h2 class="mt-4">Quick Example: Author Extension</h2>

   <p>This extension automatically tracks who created each record:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Extensions/Author/Model.php
namespace Extensions\Author;

class Model extends AbstractModelExtension
{
    protected $show_username = true;

    public function configure(RuleBuilder $rule_builder): void
    {
        // Add created_by field
        $rule_builder
            ->int('created_by')
            ->default(0)
            ->label('Created By');
    }

    #[ToDatabaseValue('created_by')]
    public function setCreatedBy($current_record)
    {
        // Auto-fill with current user on insert
        if (empty($current_record->created_by)) {
            $user = Get::make('Auth')->getUser();
            return $user->id ?? 0;
        }
        return $current_record->created_by;
    }

    #[ToDisplayValue('created_by')]
    public function getFormattedCreatedBy($current_record)
    {
        // Display username instead of ID
        $user = Get::make('Auth')->getUser($current_record->created_by);
        return $user->username ?? '-';
    }
}</code></pre>

   <h2 class="mt-4">Next Steps</h2>

   <ul>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-model'); ?>">Model Extensions</a> - Learn about model extension features</li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-module'); ?>">Module Extensions</a> - Learn about module extension features</li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/creating-extensions'); ?>">Creating Extensions</a> - Build your own extensions</li>
   </ul>

</div>
