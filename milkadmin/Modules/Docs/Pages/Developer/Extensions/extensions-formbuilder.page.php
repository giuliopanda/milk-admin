<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
* @title FormBuilder Extensions
* @order 15
* @tags extensions, formbuilder-extensions, AbstractFormBuilderExtension, form-hooks, configure, beforeRender, beforeSave, afterSave, getLoadedExtension, model-extensions
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

   <h1>FormBuilder Extensions</h1>

   <p>FormBuilder Extensions allow you to modify form behavior, add custom fields, buttons, and logic to forms without modifying the FormBuilder class itself. They extend the <code>AbstractFormBuilderExtension</code> class.</p>

   <h2>Creating a FormBuilder Extension</h2>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\MyExtension;

use App\Abstracts\AbstractFormBuilderExtension;

class FormBuilder extends AbstractFormBuilderExtension
{
    // Configuration parameters
    protected bool $my_option = true;

    // Hook called during form configuration
    public function configure(object $builder): void
    {
        // Add fields, actions, HTML elements
    }

    // Hook called before form render
    public function beforeRender(array $fields): array
    {
        // Modify fields before rendering
        return $fields;
    }

    // Hook called before data is saved
    public function beforeSave(array $request): array
    {
        // Modify request data
        return $request;
    }

    // Hook called after data is saved
    public function afterSave(array $request): void
    {
        // Execute custom logic
    }
}</code></pre>

   <h2 class="mt-4">Accessing the Model</h2>

   <p>Get the model instance from the FormBuilder:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure(object $builder): void
{
    // Get the model
    $model = $builder->getModel();

    // Access model properties
    $primary_key = $model->getPrimaryKey();
    $table = $model->getTable();
}</code></pre>

   <h2 class="mt-4">Accessing Model Extensions</h2>

   <p>Get loaded model extensions to access their data and configuration:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure(object $builder): void
{
    $model = $builder->getModel();

    // Get a specific extension
    $soft_del_ext = $model->getLoadedExtension('SoftDelete');
    if (!$soft_del_ext) {
        return; // Extension not loaded
    }

    // Access extension properties
    $field_name = $soft_del_ext->field_name;

    // Check field value
    if ($model->$field_name != null) {
        // Record is soft deleted
    }
}</code></pre>

   <h2 class="mt-4">Available Hooks</h2>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Hook</th>
            <th>Parameters</th>
            <th>Return</th>
            <th>Description</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>configure()</code></td>
            <td><code>object $builder</code></td>
            <td><code>void</code></td>
            <td>Add fields, actions, HTML during form build</td>
         </tr>
         <tr>
            <td><code>beforeRender()</code></td>
            <td><code>array $fields</code></td>
            <td><code>array</code></td>
            <td>Modify fields array before render</td>
         </tr>
         <tr>
            <td><code>beforeSave()</code></td>
            <td><code>array $request</code></td>
            <td><code>array</code></td>
            <td>Modify request data before save</td>
         </tr>
         <tr>
            <td><code>afterSave()</code></td>
            <td><code>array $request</code></td>
            <td><code>void</code></td>
            <td>Execute logic after save</td>
         </tr>
      </tbody>
   </table>

   <h3 class="mt-4">Hook Details</h3>

   <h4>configure(object $builder): void</h4>
   <p><strong>When called:</strong> During form builder initialization, before fields are processed.</p>
   <p><strong>Input:</strong> The FormBuilder instance (<code>$builder</code>)</p>
   <p><strong>Use cases:</strong></p>
   <ul>
      <li>Add new fields with <code>$builder->addField()</code></li>
      <li>Add custom actions/buttons with <code>$builder->addActions()</code></li>
      <li>Add HTML alerts or messages with <code>$builder->addHtml()</code></li>
      <li>Access control checks (redirect unauthorized users)</li>
      <li>Show/hide UI elements based on record state</li>
   </ul>

   <h4>beforeRender(array $fields): array</h4>
   <p><strong>When called:</strong> After fields are built but before rendering to HTML.</p>
   <p><strong>Input:</strong> Array of field definitions</p>
   <p><strong>Return:</strong> Modified array of field definitions</p>
   <p><strong>Use cases:</strong></p>
   <ul>
      <li>Modify field properties (type, label, options, edit mode)</li>
      <li>Hide/show fields conditionally</li>
      <li>Change field types dynamically</li>
      <li>Adjust field permissions</li>
   </ul>

   <h4>beforeSave(array $request): array</h4>
   <p><strong>When called:</strong> Before data is saved to the database.</p>
   <p><strong>Input:</strong> Request data array to be saved</p>
   <p><strong>Return:</strong> Modified request data array</p>
   <p><strong>Use cases:</strong></p>
   <ul>
      <li>Validate ownership before save</li>
      <li>Auto-fill fields (author, timestamps)</li>
      <li>Remove fields that shouldn't be saved</li>
      <li>Transform data before save</li>
      <li>Deny unauthorized save attempts</li>
   </ul>

   <h4>afterSave(array $request): void</h4>
   <p><strong>When called:</strong> After data has been successfully saved.</p>
   <p><strong>Input:</strong> The saved request data</p>
   <p><strong>Use cases:</strong></p>
   <ul>
      <li>Log actions or create audit trail</li>
      <li>Send notifications</li>
      <li>Update related records</li>
      <li>Clear caches</li>
      <li>Trigger external APIs</li>
   </ul>

   <h2 class="mt-4">FormBuilder Methods</h2>

   <p>Common methods available in the FormBuilder instance:</p>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Method</th>
            <th>Parameters</th>
            <th>Description</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>getModel()</code></td>
            <td>-</td>
            <td>Get the associated model instance</td>
         </tr>
         <tr>
            <td><code>getPage()</code></td>
            <td>-</td>
            <td>Get the current page name</td>
         </tr>
         <tr>
            <td><code>addField()</code></td>
            <td><code>string $name, string $type, array $options</code></td>
            <td>Add a new form field</td>
         </tr>
         <tr>
            <td><code>addHtml()</code></td>
            <td><code>string $html</code></td>
            <td>Add custom HTML to the form</td>
         </tr>
         <tr>
            <td><code>addActions()</code></td>
            <td><code>array $actions</code></td>
            <td>Add custom action buttons</td>
         </tr>
      </tbody>
   </table>

   <h2 class="mt-4">Extension Parameters</h2>

   <p>Define configurable parameters as protected properties:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class FormBuilder extends AbstractFormBuilderExtension
{
    // Default values
    protected bool $show_author_info = true;
    protected bool $show_restore_button = true;
}

// Override in module configuration
$rule_builder->addExtension('Author', [
    'show_author_info' => false
]);</code></pre>

   <h2 class="mt-4">Example 1: SoftDelete FormBuilder Extension</h2>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\SoftDelete;

class FormBuilder extends AbstractFormBuilderExtension
{
    protected bool $show_restore_button = true;

    public function configure(object $builder): void
    {
        $model = $builder->getModel();
        $soft_del_ext = $model->getLoadedExtension('SoftDelete');

        if (!$soft_del_ext) {
            return;
        }

        $name = $soft_del_ext->field_name;

        // Show alert for deleted records
        if ($model->$name != null) {
            $formatted = $model->getFormattedValue($name);
            $builder->addHtml('&lt;div class="alert alert-warning"&gt;
                Deleted on &lt;b&gt;' . $formatted . '&lt;/b&gt;
            &lt;/div&gt;');
        }

        // Add restore button
        if ($model->$name != null) {
            $builder->addActions([
                'restore' => [
                    'label' => 'Restore',
                    'type' => 'submit',
                    'class' => 'btn btn-warning',
                    'action' => function($form_builder, $request) {
                        $model = $form_builder->getModel();
                        $soft_del_ext = $model->getLoadedExtension('SoftDelete');

                        $id = $request[$model->getPrimaryKey()] ?? 0;
                        $record = $model->getById($id);

                        if (!$record->isEmpty()) {
                            $record->{$soft_del_ext->field_name} = null;
                            $record->save();

                            return [
                                'success' => true,
                                'message' => 'Record restored'
                            ];
                        }

                        return [
                            'success' => false,
                            'message' => 'Failed to restore'
                        ];
                    }
                ]
            ]);
        }
    }
}</code></pre>

   <h2 class="mt-4">Example 2: Author Access Control</h2>

   <p>Prevents users from editing records they didn't create:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\Author;

use App\Abstracts\AbstractFormBuilderExtension;
use App\{Get, Permissions, Route};

class FormBuilder extends AbstractFormBuilderExtension
{
    protected bool $show_author_info = true;

    public function configure(object $builder): void
    {
        Get::make('Auth');
        $model = $builder->getModel();
        $page = $builder->getPage();

        // Check if user has "manage_own_only" permission
        if ($page && Permissions::check($page . '.manage_own_only')) {
            $user = Get::make('Auth')->getUser();
            $current_user_id = $user->id ?? 0;

            $primary_key = $model->getPrimaryKey();
            $record_id = $model->$primary_key ?? 0;

            // Check ownership for existing records
            if ($record_id > 0) {
                $created_by = $model->created_by ?? 0;

                if ($created_by != $current_user_id) {
                    // Deny access
                    $builder->addHtml(
                        '&lt;div class="alert alert-danger"&gt;
                            Access Denied: You can only edit your own records.
                        &lt;/div&gt;'
                    );

                    $queryString = Route::getQueryString();
                    Route::redirect('?page=deny&redirect=' .
                        Route::urlsafeB64Encode($queryString));
                }
            }
        }
    }

    public function beforeSave(array $request): array
    {
        Get::make('Auth');

        // Skip for administrators
        if (Permissions::check('_user.is_admin')) {
            return $request;
        }

        $model = $this->builder->getModel();
        $page = $this->builder->getPage();

        if ($page && Permissions::check($page . '.manage_own_only')) {
            $user = Get::make('Auth')->getUser();
            $current_user_id = $user->id ?? 0;

            // Auto-fill created_by for new records
            $primary_key = $model->getPrimaryKey();
            $record_id = $request[$primary_key] ?? 0;

            if ($record_id == 0) {
                $request['created_by'] = $current_user_id;
            }
        }

        return $request;
    }
}</code></pre>

   <h2 class="mt-4">Example 3: Adding Custom Action Buttons</h2>

   <p>Add a custom "Duplicate" button with action logic:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\Duplicate;

use App\Abstracts\AbstractFormBuilderExtension;

class FormBuilder extends AbstractFormBuilderExtension
{
    protected bool $show_duplicate_button = true;

    public function configure(object $builder): void
    {
        if (!$this->show_duplicate_button) {
            return;
        }

        $model = $builder->getModel();
        $primary_key = $model->getPrimaryKey();
        $record_id = $model->$primary_key ?? 0;

        // Only show for existing records
        if ($record_id > 0) {
            $builder->addActions([
                'duplicate' => [
                    'label' => 'Duplicate Record',
                    'type' => 'submit',
                    'class' => 'btn btn-info',
                    'action' => function($form_builder, $request) {
                        $model = $form_builder->getModel();
                        $pk = $model->getPrimaryKey();
                        $id = $request[$pk] ?? 0;

                        // Load original record
                        $record = $model->getById($id);

                        if ($record->isEmpty()) {
                            return [
                                'success' => false,
                                'message' => 'Record not found'
                            ];
                        }

                        // Create duplicate
                        $duplicate = $model->createRow();
                        foreach ($record->toArray() as $key => $value) {
                            if ($key !== $pk) {
                                $duplicate->$key = $value;
                            }
                        }
                        $duplicate->save();

                        return [
                            'success' => true,
                            'message' => 'Record duplicated successfully',
                            'redirect' => '?page=' . $form_builder->getPage() .
                                         '&action=edit&id=' . $duplicate->$pk
                        ];
                    }
                ]
            ]);
        }
    }
}</code></pre>

   <h2 class="mt-4">Example 4: Conditional Field Visibility</h2>

   <p>Show/hide fields based on other field values:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\ConditionalFields;

use App\Abstracts\AbstractFormBuilderExtension;

class FormBuilder extends AbstractFormBuilderExtension
{
    public function beforeRender(array $fields): array
    {
        $model = $this->builder->getModel();

        // Hide shipping fields if product is digital
        if (isset($fields['product_type']) &&
            $model->product_type === 'digital') {

            if (isset($fields['weight'])) {
                $fields['weight']['edit'] = false;
            }
            if (isset($fields['shipping_cost'])) {
                $fields['shipping_cost']['edit'] = false;
            }
        }

        // Show discount field only for admin users
        if (isset($fields['discount'])) {
            if (!Permissions::check('_user.is_admin')) {
                $fields['discount']['edit'] = false;
            }
        }

        return $fields;
    }
}</code></pre>

   <h2 class="mt-4">Example 5: Data Transformation Before Save</h2>

   <p>Transform or validate data before saving:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\DataTransform;

use App\Abstracts\AbstractFormBuilderExtension;

class FormBuilder extends AbstractFormBuilderExtension
{
    public function beforeSave(array $request): array
    {
        // Normalize phone number format
        if (isset($request['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $request['phone']);
            $request['phone'] = $phone;
        }

        // Generate slug from title
        if (isset($request['title']) && empty($request['slug'])) {
            $slug = strtolower($request['title']);
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $request['slug'] = trim($slug, '-');
        }

        // Convert empty strings to null for numeric fields
        $numeric_fields = ['price', 'quantity', 'discount'];
        foreach ($numeric_fields as $field) {
            if (isset($request[$field]) && $request[$field] === '') {
                $request[$field] = null;
            }
        }

        return $request;
    }
}</code></pre>

   <h2 class="mt-4">Common Use Cases</h2>

   <ul>
      <li><strong>Conditional fields</strong> - Show/hide fields based on record state or user permissions</li>
      <li><strong>Custom actions</strong> - Add buttons with custom logic (duplicate, restore, approve)</li>
      <li><strong>Alerts and messages</strong> - Display contextual information (deleted status, warnings)</li>
      <li><strong>Field modification</strong> - Change field properties, types, or options before render</li>
      <li><strong>Access control</strong> - Prevent unauthorized users from editing specific records</li>
      <li><strong>Data validation</strong> - Additional checks and validations before save</li>
      <li><strong>Data transformation</strong> - Format, normalize, or enrich data before save</li>
      <li><strong>Auto-fill fields</strong> - Automatically set values (author, timestamps, defaults)</li>
      <li><strong>Integration with model extensions</strong> - React to extension data and state</li>
   </ul>

   <h2 class="mt-4">Best Practices</h2>

   <ul>
      <li>Use <code>configure()</code> for adding UI elements and checking initial access control</li>
      <li>Use <code>beforeRender()</code> for modifying field properties and conditional visibility</li>
      <li>Use <code>beforeSave()</code> for data validation, transformation, and ownership checks</li>
      <li>Use <code>afterSave()</code> for side effects (logging, notifications, related updates)</li>
      <li>Always check permissions before granting access or modifying data</li>
      <li>Use protected properties for configurable extension parameters</li>
      <li>Return modified arrays in <code>beforeRender()</code> and <code>beforeSave()</code></li>
      <li>Handle errors gracefully and provide clear user feedback</li>
      <li>Access the model via <code>$builder->getModel()</code> to get record data</li>
   </ul>

   <h2 class="mt-4">See Also</h2>

   <ul>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-introduction'); ?>">Extensions Introduction</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-model'); ?>">Model Extensions</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-getdatabuilder'); ?>">GetDataBuilder Extensions</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-searchbuilder'); ?>">SearchBuilder Extensions</a></li>
   </ul>

</div>
