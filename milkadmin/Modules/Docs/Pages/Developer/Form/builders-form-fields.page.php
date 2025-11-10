<?php
namespace Modules\Docs\Pages;

/**
 * @title Field Management
 * @guide developer
 * @order 41
 * @tags FormBuilder, fields, field-management, addField, removeField, modify_field, fieldOrder, addFieldsFromObject, field-configuration, form-fields
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>FormBuilder - Field Management</h1>

    <p>This guide covers how to add, remove, modify, and organize fields in forms created with <strong>FormBuilder</strong>. FormBuilder provides flexible methods to manage form fields either automatically from your Model or manually.</p>

    <h2>Two Approaches to Adding Fields</h2>

    <p>FormBuilder supports two main approaches for adding fields to your form:</p>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Approach</th>
                <th>Method</th>
                <th>Best For</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Automatic</strong></td>
                <td><code>addFieldsFromObject()</code></td>
                <td>Standard CRUD forms based on Model definitions</td>
            </tr>
            <tr>
                <td><strong>Manual</strong></td>
                <td><code>addField()</code></td>
                <td>Custom forms or when you need precise control</td>
            </tr>
        </tbody>
    </table>

    <h2>Adding Fields Automatically from Model</h2>

    <h3>Basic Usage</h3>
    <p>The recommended approach is to add fields automatically from your Model using <code>addFieldsFromObject()</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$data_object = $this->model->getByIdForEdit($id);

$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->addFieldsFromObject($data_object, 'edit')
    ->render();

// Parameters:
// - $data_object: Object returned by getByIdForEdit() or similar
// - 'edit': Context to filter fields ('edit', 'create', or custom)
</code></pre>

    <h3>Complete Example</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\Response;

class ProductModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('products')
             ->title('Product Management')
             ->menu('Products')
             ->access('registered');
    }

    #[RequestAction('edit')]
    public function edit() {
        $id = _absint($_REQUEST['id'] ?? 0);
        $product = $this->model->getByIdForEdit($id);

        $form = \Builders\FormBuilder::create($this->model, $this->page)
            // Add all fields from the Model
            ->addFieldsFromObject($product, 'edit')
            ->addStandardActions()
            ->render();

        Response::render(['form' => $form], [
            'title' => $id > 0 ? 'Edit Product' : 'Add Product',
            'form' => $form
        ]);
    }
}

class ProductModel extends AbstractModel {
    protected function configure($rule): void
    {
        $rule
        ->table('products')
        ->id()
        ->string('name', 200)
        ->text('description', false)
        ->float('price')
        ->string('sku', 50)
        ->int('stock_quantity')
        ->string('category', 100);
    }
}
</code></pre>

    <h3>How It Works</h3>
    <ol>
        <li>The Model defines the fields with their types and validation rules</li>
        <li><code>getByIdForEdit()</code> retrieves the record data</li>
        <li><code>addFieldsFromObject()</code> reads the Model's field definitions via <code>getRules()</code></li>
        <li>FormBuilder automatically creates appropriate form fields for each type</li>
        <li>Field values are populated from the data object</li>
    </ol>

    <h2>Adding Fields Manually</h2>

    <h3>New addField() Method with Positioning</h3>
    <p>The <code>addField()</code> method now supports precise positioning control:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Syntax
->addField($field_name, $type, $options = [], $position_before = '')

// Parameters:
// - $field_name: Name of the field (required)
// - $type: Field type ('string', 'text', 'select', 'date', etc.) (required)
// - $options: Array of field options (label, form-params, etc.) (optional)
// - $position_before: Field name before which to insert this field (optional)
</code></pre>

    <div class="alert alert-warning">
        <strong>Important:</strong> The syntax has changed from the old format. The second parameter is now the <code>$type</code>, not an options array.
    </div>

    <h3>Basic Usage Examples</h3>

    <h4>1. Add Field at the End (No Position)</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->addFieldsFromObject($data_object, 'edit')

    // Add field at the end of the form
    ->addField('phone', 'string', [
        'label' => 'Phone Number',
        'form-params' => [
            'placeholder' => '555-1234',
            'required' => true
        ]
    ])
    ->render();
</code></pre>

    <h4>2. Add Field Before Another Field</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->addFieldsFromObject($data_object, 'edit')

    // Add 'surname' field BEFORE 'status' field
    ->addField('surname', 'string', [
        'label' => 'Surname'
    ], 'status')  // <-- Position parameter
    ->render();

// Result field order: ..., name, surname, status, ...
</code></pre>

    <h3>Field Configuration Options</h3>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Option</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>name</code></td>
                <td>Field name (required)</td>
                <td><code>'name' => 'email'</code></td>
            </tr>
            <tr>
                <td><code>label</code></td>
                <td>Field label displayed to user</td>
                <td><code>'label' => 'Email Address'</code></td>
            </tr>
            <tr>
                <td><code>form-type</code></td>
                <td>Input type (string, text, select, date, etc.)</td>
                <td><code>'form-type' => 'email'</code></td>
            </tr>
            <tr>
                <td><code>value</code></td>
                <td>Current field value</td>
                <td><code>'value' => $user->email</code></td>
            </tr>
            <tr>
                <td><code>form-params</code></td>
                <td>Additional field attributes</td>
                <td><code>'form-params' => ['required' => true]</code></td>
            </tr>
            <tr>
                <td><code>options</code></td>
                <td>Options for select/radio/checkbox fields</td>
                <td><code>'options' => ['1' => 'Active', '0' => 'Inactive']</code></td>
            </tr>
        </tbody>
    </table>

    <h3>Examples by Field Type</h3>

    <h4>Text Input</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addField('title', 'string', [
    'label' => 'Post Title',
    'value' => $post->title ?? '',
    'form-params' => [
        'required' => true,
        'placeholder' => 'Enter post title',
        'maxlength' => 200
    ]
])
</code></pre>

    <h4>Textarea</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addField('content', 'textarea', [
    'label' => 'Content',
    'value' => $post->content ?? '',
    'form-params' => [
        'rows' => 10,
        'placeholder' => 'Enter post content...'
    ]
])
</code></pre>

    <h4>Select Dropdown</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addField('status', 'select', [
    'label' => 'Status',
    'options' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived'
    ],
    'value' => $post->status ?? 'draft',
    'form-params' => [
        'required' => true
    ]
])
</code></pre>

    <h4>Date Field</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addField('publish_date', 'date', [
    'label' => 'Publish Date',
    'value' => $post->publish_date ?? date('Y-m-d'),
    'form-params' => [
        'min' => date('Y-m-d')
    ]
])
</code></pre>

    <h4>Checkbox</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addField('featured', 'checkbox', [
    'label' => 'Featured Post',
    'value' => '1',  // Value when checked
    'form-params' => [
        'checked' => $post->featured == 1
    ]
])
</code></pre>

    <h4>File Upload</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addField('thumbnail', 'image', [
    'label' => 'Thumbnail Image',
    'value' => $post->thumbnail ?? '',
    'form-params' => [
        'accept' => 'image/*',
        'upload-dir' => 'media/products/'
    ]
])
</code></pre>

    <h4>Rich Text Editor</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addField('description', 'editor', [
    'label' => 'Description',
    'value' => $product->description ?? '',
    'form-params' => [
        'height' => '300px'
    ]
])
</code></pre>

    <h2>Removing Fields</h2>

    <p>Use <code>removeField()</code> to exclude fields from the form:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->addFieldsFromObject($data_object, 'edit')

    // Remove system fields that shouldn't be edited
    ->removeField('created_at')
    ->removeField('updated_at')
    ->removeField('deleted_at')

    ->render();
</code></pre>

    <h3>Common Use Cases for Removing Fields</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Remove timestamp fields
->removeField('created_at')
->removeField('updated_at')

// Remove ID field (usually auto-increment)
->removeField('id')

// Remove computed or system-managed fields
->removeField('last_login')
->removeField('token')
->removeField('password_reset_hash')
</code></pre>

    <h2>Modifying Existing Fields</h2>

    <p>Use <code>modify_field()</code> to change properties of fields added by <code>addFieldsFromObject()</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->addFieldsFromObject($data_object, 'edit')

    // Change the label
    ->modify_field('name', [
        'label' => 'Full Name'
    ])

    // Add custom CSS class
    ->modify_field('email', [
        'form-params' => [
            'class' => 'form-control-lg',
            'placeholder' => 'your@email.com'
        ]
    ])

    // Change field type
    ->modify_field('description', [
        'form-type' => 'editor'  // Change from textarea to rich editor
    ])

    ->render();
</code></pre>

    <h3>Modify Field Examples</h3>

    <h4>Making a Field Read-Only</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->modify_field('username', [
    'form-params' => [
        'readonly' => true,
        'class' => 'form-control-plaintext'
    ]
])
</code></pre>

    <h4>Adding Help Text</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->modify_field('api_key', [
    'label' => 'API Key',
    'form-params' => [
        'help' => 'Your unique API key for integration. Keep it secret!',
        'readonly' => true
    ]
])
</code></pre>

    <h4>Changing Select Options</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->modify_field('category', [
    'options' => $this->getCategoryOptions(),  // Dynamic options
    'form-params' => [
        'class' => 'form-select-lg'
    ]
])
</code></pre>

    <h2>Setting Field Order</h2>

    <p>Use <code>fieldOrder()</code> to control the display order of fields:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->addFieldsFromObject($data_object, 'edit')

    // Remove system fields
    ->removeField('created_at')
    ->removeField('updated_at')

    // Set custom field order
    ->fieldOrder([
        'id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'status'
    ])

    ->render();
</code></pre>

    <h3>Field Order Behavior</h3>
    <ul>
        <li>Fields are displayed in the order specified in the array</li>
        <li>Fields not listed in <code>fieldOrder()</code> appear after the ordered fields</li>
        <li>If a field in the order doesn't exist, it's simply skipped</li>
    </ul>

    <h2>Complete Example: Advanced Field Management</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\Response;

class UserModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('users')
             ->title('User Management')
             ->menu('Users')
             ->access('admin');
    }

    #[RequestAction('edit')]
    public function edit() {
        $id = _absint($_REQUEST['id'] ?? 0);
        $user = $this->model->getByIdForEdit($id);

        $form = \Builders\FormBuilder::create($this->model, $this->page)
            // 1. Add all fields from Model
            ->addFieldsFromObject($user, 'edit')

            // 2. Remove system fields
            ->removeField('created_at')
            ->removeField('updated_at')
            ->removeField('last_login')
            ->removeField('password_hash')

            // 3. Modify existing fields
            ->modify_field('username', [
                'label' => 'Username (cannot be changed)',
                'form-params' => [
                    'readonly' => $id > 0,  // Read-only when editing
                    'class' => 'form-control-lg'
                ]
            ])

            ->modify_field('email', [
                'form-params' => [
                    'placeholder' => 'user@example.com'
                ]
            ])

            ->modify_field('bio', [
                'form-type' => 'editor',  // Use rich text editor
                'form-params' => [
                    'height' => '200px'
                ]
            ])

            // 4. Add custom fields not in Model
            ->addField('new_password', [
                'label' => 'New Password (leave blank to keep current)',
                'form-type' => 'password',
                'value' => '',
                'form-params' => [
                    'required' => false,
                    'autocomplete' => 'new-password'
                ]
            ])

            ->addField('confirm_password', [
                'label' => 'Confirm New Password',
                'form-type' => 'password',
                'value' => '',
                'form-params' => [
                    'required' => false
                ]
            ])

            // 5. Set field order
            ->fieldOrder([
                'id',
                'username',
                'email',
                'first_name',
                'last_name',
                'phone',
                'bio',
                'role',
                'status',
                'new_password',
                'confirm_password',
                'avatar'
            ])

            // 6. Add conditional visibility
            ->showFieldsWhen(['new_password', 'confirm_password'], 'change_password_checkbox', '1')

            // 7. Add actions
            ->addStandardActions('?page=' . $this->page, $id > 0)

            ->render();

        Response::render(['form' => $form], [
            'title' => $id > 0 ? 'Edit User' : 'Add User',
            'form' => $form
        ]);
    }
}

class UserModel extends AbstractModel {
    protected function configure($rule): void
    {
        $rule
        ->table('users')
        ->id()
        ->string('username', 50)
        ->string('email', 255)
        ->string('first_name', 100)
        ->string('last_name', 100)
        ->string('phone', 20, false)
        ->text('bio', false)
        ->string('role', 50)->options([
            'user' => 'User',
            'editor' => 'Editor',
            'admin' => 'Administrator'
        ])
        ->string('status', 20)->options([
            'active' => 'Active',
            'inactive' => 'Inactive',
            'suspended' => 'Suspended'
        ])
        ->string('avatar', 255, false)->formType('image')
        ->timestamp('created_at', false)
        ->timestamp('updated_at', false)
        ->timestamp('last_login', false);
    }
}
</code></pre>

    <h2>Working with Related Tables (hasOne)</h2>
    <p>FormBuilder supports editing fields from related tables using the <code>hasOne</code> relationship. This allows you to include fields from a 1-to-1 related table directly in your form.</p>

    <h3>Basic Usage</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Model setup - define hasOne relationship in your Model
class EmployeesModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__employees')
            ->id()
                ->hasOne('badge', EmployeeBadgeModel::class, 'employee_id', 'CASCADE')
            ->string('name', 100)->required();
    }
}

// Controller - add related fields to form
$form = FormBuilder::create($this->model, 'employees', '?page=employees')
    ->addFieldsFromObject($data_object, 'edit')
    ->addRelatedField('badge.badge_number', 'Badge Number')
    ->addRelatedField('badge.issue_date', 'Issue Date')
    ->addRelatedField('badge.status', 'Status')
    ->addStandardActions(true)
    ->getForm();</code></pre>

    <h3>How it Works</h3>
    <ul>
        <li><strong>Syntax:</strong> <code>->addRelatedField('relationship_alias.field_name', 'Label')</code></li>
        <li><strong>Automatic Loading:</strong> Related data is loaded using <code>->with('badge')</code> automatically</li>
        <li><strong>Automatic Saving:</strong> Related fields are saved automatically when the form is submitted</li>
        <li><strong>Hidden Fields:</strong> Required fields from the related table are added as hidden fields automatically to ensure valid saves</li>
    </ul>

    <div class="alert alert-warning">
        <h5 class="alert-heading">⚠️ Important Notes</h5>
        <ul class="mb-0">
            <li>The relationship must be defined in the Model using <code>->hasOne()</code></li>
            <li>Use the relationship <strong>alias</strong> (not the model class name) in the field path</li>
            <li>The related table must have its own Model class</li>
            <li>Foreign key must be in the related table (e.g., <code>employee_id</code> in <code>badges</code> table)</li>
        </ul>
    </div>

    <h2>Adding Custom HTML</h2>

    <p>FormBuilder provides two approaches for adding custom HTML to your forms:</p>

    <h3>1. Position-Based HTML (New Method)</h3>
    <p>Use <code>addHtml()</code> with precise positioning control - works just like <code>addField()</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Syntax
->addHtml($html, $position_before = '')

// Parameters:
// - $html: The HTML content to add (required)
// - $position_before: Field name before which to insert HTML (optional)
</code></pre>

    <h4>Examples</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->addFieldsFromObject($data_object, 'edit')

    // Add HTML BEFORE a specific field
    ->addHtml(
        '<div class="alert alert-info">Please provide your full name</div>',
        'name'  // <-- Will appear before the 'name' field
    )

    // Add HTML at the end (no position parameter)
    ->addHtml('<hr><h4>Additional Information</h4>')

    ->render();
</code></pre>

    <h3>2. Fixed Position Methods (Dedicated Methods)</h3>
    <p>Use dedicated methods for standard positions:</p>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Position</th>
                <th>Use Case</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>addHtmlBeforeFields()</code></td>
                <td>Before all fields</td>
                <td>Page-level instructions, warnings</td>
            </tr>
            <tr>
                <td><code>addHtmlAfterFields()</code></td>
                <td>After all fields</td>
                <td>Separators, section headers</td>
            </tr>
            <tr>
                <td><code>addHtmlBeforeSubmit()</code></td>
                <td>Before submit buttons</td>
                <td>Final warnings, confirmations</td>
            </tr>
        </tbody>
    </table>

    <h4>Examples</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->addFieldsFromObject($data_object, 'edit')

    // Add HTML before all fields
    ->addHtmlBeforeFields(
        '<div class="alert alert-primary">
            <h5>Form Instructions</h5>
            <p>Please fill all required fields marked with *</p>
        </div>'
    )

    // Add HTML after all fields
    ->addHtmlAfterFields('<hr><h4>Optional Information</h4>')

    // Add HTML before submit buttons
    ->addHtmlBeforeSubmit(
        '<div class="alert alert-warning">
            Changes cannot be undone!
        </div>'
    )

    ->render();
</code></pre>

    <h3>Complete Example: Mixing Both Approaches</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Add all fields from model
    ->addFieldsFromObject($data_object, 'edit')

    // Global: Add instructions at the top
    ->addHtmlBeforeFields(
        '<div class="alert alert-info">Please complete this form</div>'
    )

    // Specific: Add help text before 'email' field
    ->addHtml(
        '<p class="text-muted"><small>We\'ll never share your email</small></p>',
        'email'
    )

    // Specific: Add section divider before 'password' field
    ->addHtml('<hr><h5>Security Information</h5>', 'password')

    // Global: Add warning before submit
    ->addHtmlBeforeSubmit(
        '<div class="alert alert-warning">Review before submitting</div>'
    )

    ->render();
</code></pre>

    <h3>HTML Field Naming</h3>
    <p>When you use <code>addHtml()</code>, FormBuilder automatically generates field names like <code>H001</code>, <code>H002</code>, etc. These are internal identifiers and won't affect your form's functionality.</p>

    <h2>Method Chaining</h2>

    <p>All field management methods support method chaining for clean, readable code:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->addFieldsFromObject($data_object, 'edit')
    ->removeField('created_at')
    ->removeField('updated_at')
    ->modify_field('name', ['label' => 'Full Name'])
    ->fieldOrder(['id', 'name', 'email', 'phone'])
    ->addStandardActions()
    ->render();
</code></pre>

    <h2>Best Practices</h2>

    <h3>1. Use addFieldsFromObject() as Starting Point</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Good - Start with Model fields, then customize
->addFieldsFromObject($data_object, 'edit')
->removeField('created_at')
->modify_field('name', ['label' => 'Full Name'])

// Not recommended - Manually adding all fields when you have a Model
->addField('id', [...])
->addField('name', [...])
->addField('email', [...])
// ... many more fields
</code></pre>

    <h3>2. Remove Rather Than Skip</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Good - Explicitly remove unwanted fields
->removeField('created_at')
->removeField('updated_at')

// Not recommended - Relying on field context filtering
// (less clear and harder to maintain)
</code></pre>

    <h3>3. Group Related Modifications</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Good - Logical grouping with comments
// Remove system fields
->removeField('created_at')
->removeField('updated_at')

// Customize user fields
->modify_field('username', [...])
->modify_field('email', [...])

// Add custom fields
->addField('new_password', [...])
->addField('confirm_password', [...])
</code></pre>

    <h3>4. Set Field Order for Better UX</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Good - Logical field order
->fieldOrder([
    'id',           // ID first
    'name',         // Basic info
    'email',
    'phone',
    'address',      // Address info grouped
    'city',
    'zip',
    'status',       // Status/admin fields last
    'role'
])
</code></pre>

    <h2>Troubleshooting</h2>

    <h3>Field Not Appearing</h3>
    <ul>
        <li>Check that the field is defined in the Model's <code>configure()</code> method</li>
        <li>Verify the field context ('edit', 'create') matches your <code>addFieldsFromObject()</code> call</li>
        <li>Ensure you haven't accidentally removed the field with <code>removeField()</code></li>
    </ul>

    <h3>Field Order Not Working</h3>
    <ul>
        <li>Verify field names in <code>fieldOrder()</code> match exactly (case-sensitive)</li>
        <li>Check that fields exist before setting order</li>
        <li>Remember: unlisted fields appear after ordered ones</li>
    </ul>

    <h3>Modified Field Not Showing Changes</h3>
    <ul>
        <li>Ensure <code>modify_field()</code> is called after <code>addFieldsFromObject()</code></li>
        <li>Check that the field name matches exactly</li>
        <li>Verify the modification array structure is correct</li>
    </ul>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Form/builders-form-fields"><strong>Field Management</strong></a>: Adding, removing, modifying, and organizing form fields</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-conditional-visibility"><strong>Conditional Field Visibility</strong></a>: Show/hide fields based on other field values</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-validation"><strong>Form Validation</strong></a>: Custom validation and error handling</li>
          <li><a href="?page=docs&action=Developer/Form/builders-form-containers"><strong>Organizing Fields in columns with Containers</strong></a>: Grouping fields into containers for better organization</li>
        
    </ul>

</div>
