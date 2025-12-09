<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
* @title Module Structure Guide
* @order 10
* @tags abstract, classes, AbstractModule, AbstractController, AbstractModel, AbstractObject, modules, permissions, installation, hooks, module-development, bootstrap, init, init_rules, data-structure, routing, actions, MVC, framework, getting-started, extending, inheritance, base-classes, chain-methods, PSR-4, autoloading, snake-case, pascal-case
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

   <h1>Writing modules with abstract classes</h1>

   <p>Abstract classes are the foundation for creating advanced modules in a simple and structured way. A module can be created in a single file or organized in a separate folder structure.</p>

   <h2>Module Structure</h2>

   <h3 class="mt-4">Option 1: Single File Module</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>Modules/
└── my_module_module.php    # Name in snake_case if you don't use AbstractModule Class</code></pre>
   <p>All module classes (Module, Controller, Model, Object, Shell, Api) are defined in the same file.</p>

   <h3 class="mt-4">Option 2: Structured Module</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>Modules/
└── MyModule/                      # Folder with PascalCase name
    ├── MyModuleModule.php         # Main class loaded automatically by the system
    ├── MyModuleController.php     # Routing management
    ├── MyModuleModel.php          # Database management & Data structure
    ├── MyModuleInstall.php        # Installation (optional)
    ├── MyModuleShell.php          # Shell commands (optional)
    ├── MyModuleApi.php            # API endpoints (optional)
    ├── MyModuleService.php        # Static class with optional methods (optional)
    ├── MyModuleContract.php       # Contract (optional) - used externally via Get::make('MyModule')
    ├── Assets/                    # Static files (optional)
    │   ├── MyModule.js
    │   ├── MyModule.css
    ├── Lang/                      # Translation files (optional)
    │   └── it_IT.php
    └── Views/                     # Templates
        ├── list_page.php
        └── edit_page.php</code></pre>
   <p>Each class is in a separate file, names in PascalCase for PSR-4 autoloading. Class names inside files must match the file name.</p>

   <h2 class="mt-4">Standard Abstract Classes</h2>

   <h5 class="mt-3">AbstractModule</h5>
   <p>Main module class. Manages initialization, menu, permissions and hooks. Accepts all attributes.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModuleModule extends AbstractModule</code></pre>

   <h5 class="mt-3">AbstractController</h5>
   <p>Handles HTTP calls. Each method with <code>#[RequestAction]</code> attribute becomes a page.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModuleController extends AbstractController</code></pre>

   <h5 class="mt-3">AbstractModel</h5>
   <p>Manages database connection and CRUD operations.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModuleModel extends AbstractModel</code></pre>

   <h5 class="mt-3">AbstractObject</h5>
   <p>Defines data structure. Automatically creates database tables.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModuleObject extends AbstractObject</code></pre>

   <h2 class="mt-4">Attributes</h2>

   <p>Use attributes from <code>App\Attributes</code> to define routing, validation, hooks and other functionalities. All attributes are applied to methods.</p>

   <h3 class="mt-4">Routing & Actions</h3>

   <h5 class="mt-3">#[RequestAction('name')]</h5>
   <p>Creates a page accessible via <code>?page=module&action=name</code>. Can be applied to Module or Controller.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('edit')]
public function edit() { /* ... */ }</code></pre>

   <h5 class="mt-3">#[ApiEndpoint('path', 'METHOD', $options)]</h5>
   <p>Creates a REST API endpoint accessible via <code>/api/path</code>. Can be applied to Module or Api class.</p>
   <p><strong>Parameters:</strong></p>
   <ul>
      <li><code>path</code> - API path (e.g., 'MyModule/list')</li>
      <li><code>METHOD</code> - HTTP method: GET, POST, PUT, DELETE, ANY (default: ANY)</li>
      <li><code>options</code> - Array with auth and permissions (optional)</li>
   </ul>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ApiEndpoint('MyModule/list', 'GET')]
public function apiList() { /* ... */ }

#[ApiEndpoint('users/create', 'POST', ['auth' => true, 'permissions' => 'users.create'])]
public function createUser() { /* ... */ }</code></pre>

   <h5 class="mt-3">#[Shell('command')]</h5>
   <p>Creates a CLI command executable with <code>php milkadmin/cli.php module:command</code>. Can be applied to Module or Shell class.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Shell('test')]
public function shellTest() { /* ... */ }</code></pre>

   <h3 class="mt-4">Hooks & Events</h3>

   <h5 class="mt-3">#[HookCallback('hook_name', $order)]</h5>
   <p>Registers the method as a callback for a system hook.</p>
   <p><strong>Parameters:</strong></p>
   <ul>
      <li><code>hook_name</code> - Name of the hook (e.g., 'init', 'post_save', 'before_render')</li>
      <li><code>order</code> - Execution order (default: 20, lower = earlier)</li>
   </ul>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[HookCallback('init')]
public function onInit() { /* ... */ }

#[HookCallback('post_save', 10)]
public function afterSave($data) { /* ... */ }</code></pre>

   <h3 class="mt-4">Model & Object Attributes</h3>

   <h5 class="mt-3">#[Validate('field_name')]</h5>
   <p>Defines custom validation logic for a specific field. Applied to Model methods.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('title')]
public function validateTitle($value) {
    if (strlen($value) < 3) {
        return 'Title must be at least 3 characters';
    }
    return true;
}</code></pre>


   <h5 class="mt-3">#[ToDatabaseValue('field_name')]</h5>
   <p>Transforms field value before saving to database. Applied to Model methods.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDatabaseValue('password')]
public function hashPassword($value) {
    return password_hash($value, PASSWORD_DEFAULT);
}</code></pre>


   <h5 class="mt-3">#[ToDisplayValue('field_name')]</h5>
   <p>Formats field value for display. Applied to Model methods.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDisplayValue('created_at')]
public function formatCreatedAt($value) {
    return date('Y-m-d H:i:s', $value);
}</code></pre>

   <h5 class="mt-3">#[SetValue('field_name')]</h5>
   <p>Customizes how a field value is set. Applied to Model methods.</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('tags')]
public function setTags($value) {
    return is_array($value) ? implode(',', $value) : $value;
}</code></pre>

   <h2 class="mt-4">Configuration via Chain Methods</h2>

   <p>Abstract classes use a configuration system via <strong>chain methods</strong> that always ends with <code>apply()</code>:</p>

   <h5 class="mt-3">Module Configuration</h5>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">FileuploadModule::set()
    ->page('fileupload')
    ->title('File Upload Test')
    ->menu('fileupload')
    ->apply();</code></pre>

   <h5 class="mt-3">Model Configuration</h5>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">FileuploadModel::set()
    ->primaryKey('id')
    ->table('#__fileupload')
    ->objectClass('FileuploadObject')
    ->apply();</code></pre>

   <h5 class="mt-3">Object Configuration (Data Structure)</h5>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">FileuploadObject::set()
    ->id()
    ->string('title', 255)
    ->file('upload_file')
        ->multiple(3)
        ->maxSize(1024)
        ->accept('image/jpeg,image/png,image/gif')
        ->uploadDir('gallery/images')
        ->required()
    ->apply();</code></pre>
   <h2 class="mt-4">Quick Examples</h2>

   <p><strong>Single file module:</strong> <code>fileupload_module.php</code> - All classes in one file</p>
   <p><strong>Structured module:</strong> <code>Modules/Posts/</code> - Separate classes with PSR-4 autoloading</p>

   <ul>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/GettingStarted/getting-started-post'); ?>" >Getting started post</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/AbstractsClass/abstract-module'); ?>" >AbstractModule</a></li>
   </ul>

</div>