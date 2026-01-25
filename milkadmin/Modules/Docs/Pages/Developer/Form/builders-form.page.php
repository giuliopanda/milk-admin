<?php
namespace Modules\Docs\Pages;

/**
 * @title Form
 * @guide developer
 * @order 40
 * @tags FormBuilder, fluent-interface, method-chaining, form-management, form-generation, actions, validation, PHP-classes, simplified-API, save, delete, input, form, fields, callbacks, CSRF, model-integration
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>FormBuilder Class Documentation</h1>

    <p>The FormBuilder class provides a fluent interface for creating and managing dynamic forms, simplifying form generation and handling compared to using ObjectToForm directly.</p>

    <h2>System Overview</h2>
    <p>FormBuilder acts as a wrapper that combines:</p>
    <ul>
        <li><strong>ObjectToForm</strong>: Form field generation and HTML creation</li>
        <li><strong>Model Integration</strong>: Data validation and save/delete operations</li>
        <li><strong>Action System</strong>: Configurable form buttons and callbacks</li>
        <li><strong>CSRF Protection</strong>: Automatic token handling</li>
    </ul>
    <p>It provides a single, chainable API for building forms with less code and better readability.</p>

    <h2>Method Reference Summary</h2>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Category</th>
                <th>Method</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td rowspan="8">Form Setup</td>
                <td><code>create($model, $page = '', $url_success = null, $url_error = null)</code></td>
                <td>Factory method to create FormBuilder instance (static)</td>
            </tr>
            <tr>
                <td><code>page(string $page)</code></td>
                <td>Set the page identifier</td>
            </tr>
            <tr>
                <td><code>currentAction(string $action)</code></td>
                <td>Set form context (edit/create)</td>
            </tr>
            <tr>
                <td><code>formAttributes(array $attributes)</code></td>
                <td>Set form HTML attributes</td>
            </tr>
            <tr>
                <td><code>setId(string $formId)</code></td>
                <td>Set form ID attribute</td>
            </tr>
            <tr>
                <td><code>urlSuccess(string $url)</code></td>
                <td>Set success redirect URL</td>
            </tr>
            <tr>
                <td><code>urlError(string $url)</code></td>
                <td>Set error redirect URL</td>
            </tr>
            <tr>
                <td><code>customData(string $key, $value)</code></td>
                <td>Add custom hidden field data to the form</td>
            </tr>
            <tr>
                <td rowspan="6">Field Management</td>
                <td><code>addFieldsFromObject(object $obj, string $action)</code></td>
                <td>Add fields from data object</td>
            </tr>
            <tr>
                <td><code>addField(string $name, array $config)</code></td>
                <td>Add individual field</td>
            </tr>
            <tr>
                <td><code>addRelatedField(string $field)</code></td>
                <td>Add field from hasOne related table (e.g., <code>'badge.badge_number'</code>)</td>
            </tr>
            <tr>
                <td><code>removeField(string $name)</code></td>
                <td>Remove field</td>
            </tr>
            <tr>
                <td><code>fieldOrder(array $order)</code></td>
                <td>Set field display order</td>
            </tr>
            <tr>
                <td><code>addHtml(string $key, string $html)</code></td>
                <td>Add custom HTML content</td>
            </tr>
            <tr>
                <td rowspan="7">Actions</td>
                <td><code>setActions(array $actions)</code></td>
                <td>Replace all existing actions</td>
            </tr>
            <tr>
                <td><code>addActions(array $actions)</code></td>
                <td>Add actions without replacing existing ones</td>
            </tr>
            <tr>
                <td><code>addStandardActions(bool $include_delete = false, ?string $cancel_link = null)</code></td>
                <td>Add pre-configured save/delete/cancel actions</td>
            </tr>
            <tr>
                <td><code>getPressedAction()</code></td>
                <td>Return the action key that triggered the submit (save, cancel, custom)</td>
            </tr>
            <tr>
                <td><code>setMessageSuccess(string $message)</code></td>
                <td>Customize success message (default: "Save successful")</td>
            </tr>
            <tr>
                <td><code>setMessageError(string $message)</code></td>
                <td>Customize error message (default: "Save failed")</td>
            </tr>
            <tr>
                <td><code>saveAction() / deleteAction()</code></td>
                <td>Static helper methods for standard actions</td>
            </tr>
            <tr>
                <td rowspan="5">Response Types</td>
                <td><code>asOffcanvas()</code></td>
                <td>Set response type to offcanvas</td>
            </tr>
            <tr>
                <td><code>asModal()</code></td>
                <td>Set response type to modal</td>
            </tr>
            <tr>
                <td><code>asDom(string $id)</code></td>
                <td>Set response type to DOM element</td>
            </tr>
            <tr>
                <td><code>setTitle(string $new, ?string $edit = null)</code></td>
                <td>Set titles for new and edit modes</td>
            </tr>
            <tr>
                <td><code>size(string $size)</code></td>
                <td>Set size for modal/offcanvas ('sm', 'lg', 'xl', 'fullscreen')</td>
            </tr>
            <tr>
                <td rowspan="3">Operations</td>
                <td><code>save(array $request)</code></td>
                <td>Save form data with validation</td>
            </tr>
            <tr>
                <td><code>delete(array $request, ?string $url_success, ?string $url_error)</code></td>
                <td>Delete record</td>
            </tr>
            <tr>
                <td><code>getModel()</code></td>
                <td>Get model instance</td>
            </tr>
            <tr>
                <td rowspan="3">Output</td>
                <td><code>getForm()</code></td>
                <td>Get form HTML</td>
            </tr>
            <tr>
                <td><code>getResponse()</code></td>
                <td>Get response array for offcanvas/modal/dom</td>
            </tr>
            <tr>
                <td><code>render()</code></td>
                <td>Render form HTML (alias for getForm)</td>
            </tr>
        </tbody>
    </table>

    <h2>Basic Usage</h2>

    <h3>Constructor and Factory Method</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Factory method (recommended)
$form = \Builders\FormBuilder::create($model, $page = '', $url_success = '', $url_error = ''); </code></pre>

    <h3>Simple Form Creation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$form = \Builders\FormBuilder::create($model)
    ->getForm();

echo $form;
    </code></pre>

   

    <h3>Field Management</h3>
    <p>FormBuilder provides flexible methods to manage form fields. For detailed information on adding, removing, modifying, and organizing fields, see the dedicated guide:</p>

    <div class="alert alert-primary">
        <h5 class="alert-heading">📘 Field Management Guide</h5>
        <p class="mb-0">
            <a href="?page=docs&action=Developer/Form/builders-form-fields" class="alert-link">
                <strong>→ FormBuilder - Field Management</strong>
            </a>
            <br>
            Learn how to add fields automatically from Models, add custom fields manually, remove/modify fields, set field order, and more.
        </p>
    </div>

    <h3>Working with Related Tables (hasOne)</h3>
    <p>FormBuilder supports editing fields from related tables using the <code>hasOne</code> relationship. For detailed documentation on using <code>addRelatedField()</code>, see the Field Management guide:</p>

    <div class="alert alert-primary">
        <h5 class="alert-heading">📘 Related Fields Documentation</h5>
        <p class="mb-0">
            <a href="?page=docs&action=Developer/Form/builders-form-fields#addRelatedField" class="alert-link">
                <strong>→ FormBuilder - Field Management: Working with Related Tables</strong>
            </a>
            <br>
            Learn how to add fields from hasOne related tables, including examples with EmployeeBadge.
        </p>
    </div>

    <h4>Quick Overview</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, 'posts', '?page=posts')

    // Remove unwanted fields
    ->removeField('created_at')
    ->removeField('updated_at')

    // Modify existing fields
    ->modify_field('title', ['label' => 'Post Title'])

    // Add custom fields
    ->addField('custom_field', ['label' => 'Custom', 'form-type' => 'string'])

    // Set field order
    ->fieldOrder(['id', 'title', 'content', 'status'])

    ->render();
    </code></pre>

    <h2>Actions Configuration</h2>

    <p>FormBuilder provides a flexible action system for managing form buttons (save, delete, custom actions, etc.). For comprehensive documentation on actions, see the dedicated guide:</p>

    <div class="alert alert-primary">
        <h5 class="alert-heading">📘 Action Management Guide</h5>
        <p class="mb-0">
            <a href="?page=docs&action=Developer/Form/builders-form-actions" class="alert-link">
                <strong>→ FormBuilder - Action Management</strong>
            </a>
            <br>
            Learn how to create standard buttons, custom actions, link buttons, and conditional visibility with showIf.
        </p>
    </div>

    <h3>Quick Overview</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Standard actions (recommended for CRUD)
$form = \Builders\FormBuilder::create($this->model,'posts', '?page=posts')
    ->addStandardActions(true, '?page=posts') // save, delete, cancel
    ->getForm();

// Custom actions
$form = \Builders\FormBuilder::create($this->model,'posts', '?page=posts')
    ->setDefaultActions()
    ->getForm();
    </code></pre>

    <h2>Custom Action Callbacks</h2>

    <h3>Creating Custom Callbacks</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
class PostModule extends AbstractModule {
    
    public function actionEdit() {
        $data_object = $this->model->getByIdForEdit($id);
        
        $form_builder = FormBuilder::create($this->model, $this->page, '?page='.$this->page)
            ->setActions([
                'save_and_continue' => [
                    'label' => 'Save & Continue',
                    'class' => 'btn btn-success',
                    'action' => [$this, 'saveAndContinueCallback']
                ],
                'publish' => [
                    'label' => 'Publish',
                    'class' => 'btn btn-primary',
                    'action' => [$this, 'publishCallback']
                ]
            ]);
        
        $form_html = $form_builder->getForm();
        // Handle form output...
    }
    
    // Custom callback - receives FormBuilder instance and request data
    public function saveAndContinueCallback($form_builder, $request) {
        // Use FormBuilder's built-in save method
        $result = $form_builder->store($request, null, null);
        
        if ($result['success']) {
            // Custom redirect logic
            $model = $form_builder->getModel();
            $id = $request[$model->getPrimaryKey()] ?? 0;
            Route::redirectSuccess('?page='.$this->page.'&action=edit&id='.$id, 'Saved successfully');
        }
        
        return $result;
    }
    
    public function publishCallback($form_builder, $request) {
        // Custom business logic
        $request['status'] = 'published';
        $request['published_at'] = date('Y-m-d H:i:s');
        
        // Save with FormBuilder
        $result = $form_builder->store($request, '?page='.$this->page);
        
        if ($result['success']) {
            MessagesHandler::addSuccess('Post published successfully');
        }
        
        return $result;
    }
}
    </code></pre>

    <h2>Built-in Methods</h2>

    <h3>Save Method</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// FormBuilder provides a built-in save method that handles:
// - Data validation using model
// - Automatic updated_at timestamp
// - Success/error handling
// - Redirects

$result = $form_builder->store($request, $redirect_success, $redirect_error);
// Returns: ['success' => true/false, 'message' => '...', 'data' => [...]]

// Usage in callback
public function customSaveCallback($form_builder, $request) {
    // Modify data before saving
    $request['custom_field'] = 'custom_value';
    
    // Use FormBuilder's save method
    $form_builder->validate($request);
    return $form_builder->store($request, '?page=success', '?page=error');
}
    </code></pre>

    <h3>Delete Method</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Built-in delete method
$result = $form_builder->delete($id, $redirect_success, $redirect_error);

// Usage in callback
public function customDeleteCallback($form_builder, $request) {
    $id = $request['id'] ?? 0;
    
    // Custom pre-delete logic
    $this->cleanup_related_data($id);
    
    // Use FormBuilder's delete method
    return $form_builder->delete($id, '?page=list', '?page=error');
}
    </code></pre>

    <h2>Form Output and Integration</h2>

    <h3>Getting Form HTML</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$form_builder = FormBuilder::create($this->model,'posts', '?page=posts')
    ->setActions([...]);

// Get form HTML
$form_html = $form_builder->getForm();

// Get any action results (from POST processing)
$action_results = $form_builder->getFunctionResults();

// Prepare response data
$response = [
    'id' => $id,
    'form' => $form_html,
    'page' => $this->page
];

// Render with Response
Response::render(__DIR__ . '/views/edit_page.php', $response);
    </code></pre>

    <h3>Module Integration</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Before FormBuilder (verbose approach)
public function actionEdit() {
    $id = _absint($_REQUEST['id'] ?? 0);
    $data_object = $this->model->getByIdForEdit($id, Route::getSessionData());
    
    $object_to_form = new ObjectToForm($this->page, '?page='.$this->page, '');
    $object_to_form->current_action = 'edit';
    $form_html = $object_to_form->getForm($data_object, 'edit');
    
    Response::render(__DIR__ . '/views/edit_page.php', ['form' => $form_html]);
}

// After FormBuilder (simplified approach)
public function actionEdit() {
    $data_object = $this->model->getByIdForEdit(_absint($_REQUEST['id'] ?? 0), Route::getSessionData());
    
    $form_builder = FormBuilder::create($this->model,'posts', '?page='.$this->page)
        ->currentAction('edit')
        ->setActions([
            'save' => ['label' => 'Save', 'class' => 'btn btn-primary', 'action' => FormBuilder::saveAction('?page='.$this->page)],
            'delete' => ['label' => 'Delete', 'class' => 'btn btn-danger', 'action' => FormBuilder::deleteAction('?page='.$this->page), 'confirm' => 'Are you sure?']
        ]);

    $form_html = $form_builder->getForm();
    $action_results = $form_builder->getFunctionResults();

    $response = ['form' => $form_html, 'page' => $this->page];
    Response::render(__DIR__ . '/views/edit_page.php', $response);
}
    </code></pre>

    <div class="alert alert-info mt-4">
        <h5 class="alert-heading">📘 Complete Examples</h5>
        <p class="mb-0">For real-world examples including blog post forms, user registration, and multi-step forms, see the dedicated examples page (coming soon).</p>
    </div>

    <h2>Next Steps</h2>
    <p>Now that you understand FormBuilder basics, explore these related topics:</p>
    <ul>
        <li><a href="?page=docs&action=Developer/Form/builders-form-fields"><strong>Field Management</strong></a>: Adding, removing, modifying, and organizing form fields</li>
        <li><a href="?page=docs&action=Developer/Model/abstract-model-relationships"><strong>Model Relationships</strong></a>: Understanding hasOne, belongsTo, and hasMany relationships</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-conditional-visibility"><strong>Conditional Field Visibility</strong></a>: Show/hide fields based on other field values</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-validation"><strong>Form Validation</strong></a>: Custom validation and error handling</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-containers"><strong>Organizing Fields in columns with Containers</strong></a>: Grouping fields into containers for better organization</li>
    </ul>
</div>
