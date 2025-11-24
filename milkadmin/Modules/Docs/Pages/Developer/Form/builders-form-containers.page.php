<?php
namespace Modules\Docs\Pages;
/**
 * @title Form Containers with FormBuilder
 * @guide developer
 * @order 45
 * @tags FormBuilder, form-containers, addContainer, Bootstrap-grid, responsive-layout, column-layout, field-organization, grid-system, container-management, form-layout
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Form Containers with FormBuilder</h1>

    <p>This guide shows how to organize form fields using Bootstrap grid layouts with the <strong><code>addContainer()</code></strong> method in FormBuilder. Containers allow you to create professional, responsive multi-column layouts for better form organization.</p>

    <div class="alert alert-info">
        <strong>Note:</strong> The <code>addContainer()</code> method is implemented in <code>FormContainerManagementTrait</code> and automatically integrated into FormBuilder.
    </div>

    <h2>Container Overview</h2>

    <p>The <code>addContainer()</code> method provides:</p>
    <ul>
        <li><strong>Bootstrap Grid Layout</strong>: Uses Bootstrap's responsive <code>col-md-X</code> classes</li>
        <li><strong>Equal or Custom Columns</strong>: Specify number of columns or custom column sizes</li>
        <li><strong>Automatic Wrapping</strong>: Extra fields automatically wrap to new rows</li>
        <li><strong>Custom Styling</strong>: Add class, style, id, or any HTML attributes</li>
        <li><strong>Positioning Control</strong>: Insert before specific fields or append at the end</li>
        <li><strong>Optional Titles</strong>: Add descriptive titles to each container</li>
    </ul>

    <h2>Method Signature</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function addContainer(
    string $id,              // Unique container ID
    array $fields,           // Array of field names to include
    int|array $cols,         // Number of columns OR array of column sizes
    string $position_before, // Field name before which to insert (empty = append)
    string $title,           // Optional container title
    array $attributes        // Additional HTML attributes (class, style, etc.)
): self</code></pre>

    <h2>Basic Usage</h2>

    <h3>Example 1: Equal Columns</h3>
    <p>Create a container with 3 equal columns:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)

    // Add 3 fields in 3 equal columns (col-md-4 each)
    ->addContainer(
        'contact_info',                           // Container ID
        ['name', 'email', 'phone'],              // Fields to include
        3,                                        // 3 equal columns
        'status',                                 // Insert before 'status' field
        'Contact Information',                    // Container title
        ['class' => 'border rounded p-3 mb-4']   // Custom styling
    )

    ->addStandardActions()
    ->render();</code></pre>

    <div class="alert alert-success">
        <strong>✓ Result:</strong> Creates a Bootstrap grid with 3 equal columns (col-md-4), each containing one field. The container appears before the 'status' field.
    </div>

    <h3>Example 2: Custom Column Sizes</h3>
    <p>Create a container with custom column sizes using Bootstrap grid (total 12):</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Custom column sizes: [4, 5, 3] = col-md-4, col-md-5, col-md-3
    ->addContainer(
        'address_info',
        ['address', 'city', 'zip_code'],
        [4, 5, 3],                              // Custom sizes (must sum to 12)
        '',                                      // Empty = append at end
        'Address Information',
        ['class' => 'border rounded p-3', 'style' => 'background-color: #f8f9fa;']
    )

    ->render();</code></pre>

    <div class="alert alert-success">
        <strong>✓ Result:</strong> Creates a container with three columns of different widths. Address takes 4/12, city takes 5/12, and zip_code takes 3/12 of the row.
    </div>

    <h3>Example 3: Automatic Wrapping</h3>
    <p>When you have more fields than columns, they automatically wrap to new rows:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)

    // 5 fields in 3 columns = 2 rows (3 fields + 2 fields)
    ->addContainer(
        'user_details',
        ['first_name', 'last_name', 'email', 'phone', 'birthdate'],
        3,                                      // 3 columns per row
        'password',
        'User Details (Auto-wrapping)',
        ['class' => 'border p-3 mb-4', 'style' => 'background-color: #e7f3ff;']
    )

    ->render();</code></pre>

    <div class="alert alert-success">
        <strong>✓ Result:</strong> Creates 2 rows. First row has 3 fields (first_name, last_name, email), second row has 2 fields (phone, birthdate).
    </div>

    <h2>Complete Module Example</h2>

    <p>This example demonstrates a complete working module with multiple containers:</p>

    <h3>Step 1: Create the Module PHP File</h3>
    <p>Create <code>milkadmin/Modules/TestFormContainerModule.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\Response;

class TestFormContainerModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('testFormContainer')
             ->title('Test Form Container')
             ->menu('Test Form Container')
             ->access('public');
    }

    #[RequestAction('home')]
    public function home() {
        $id = _absint($_REQUEST['id'] ?? 0);
        $data = $this->model->getByIdForEdit($id);

        $form = \Builders\FormBuilder::create($this->model, $this->page)
            // Add extra fields for testing
            ->addField('email', 'email', ['label' => 'Email Address'])
            ->addField('phone', 'tel', ['label' => 'Phone Number'])
            ->addField('address', 'string', ['label' => 'Address'])
            ->addField('city', 'string', ['label' => 'City'])
            ->addField('zip_code', 'string', ['label' => 'ZIP Code'])

            // Demo 1: Equal columns with wrapping
            ->addContainer('container1',
                ['name', 'email', 'phone', 'city', 'zip_code'],
                3,                                      // 3 equal columns
                'status',
                'Contact Information (3 cols, 5 fields = 2 rows)',
                ['class' => 'border rounded p-3 mb-4', 'style' => 'background-color: #f8f9fa;']
            )

            // Demo 2: Custom column sizes
            ->addContainer('container2',
                ['address', 'status', 'password'],
                [4, 5, 3],                             // Custom sizes
                '',                                     // Append at end
                'Address & Status (custom sizes: 4, 5, 3)',
                ['class' => 'border rounded p-3 mb-4', 'style' => 'background-color: #e7f3ff;']
            )

            ->addStandardActions()
            ->render();

        Response::render($form);
    }
}

class TestFormContainerModel extends AbstractModel {
    protected function configure($rule): void
    {
        $rule
        ->table('test_form_container')
        ->id()
        ->string('name', 100)
        ->string('status', 50)->options([
            'pending' => 'Pending',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'archived' => 'Archived'
        ])->formType('list')
        ->string('password', 100, false);
    }
}</code></pre>

  

    <h2>Container Features in Detail</h2>

    <h3>1. Container ID</h3>
    <p>The container ID is used as the HTML <code>id</code> attribute for the container div:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addContainer('my_container', [...], 3, '', '', [])

// Generates: &lt;div id="my_container" class="..."&gt;...&lt;/div&gt;</code></pre>

    <h3>2. Field Names Array</h3>
    <p>Specify which fields to include in the container. All fields must exist in the form:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// ✓ Valid - fields exist
->addContainer('container1', ['name', 'email', 'phone'], 3, '', '', [])

// ✗ Invalid - 'nonexistent_field' doesn't exist
->addContainer('container2', ['name', 'nonexistent_field'], 2, '', '', [])
// Throws: InvalidArgumentException: Field 'nonexistent_field' does not exist in the form</code></pre>

    <h3>3. Column Configuration</h3>
    <p><strong>Integer (Equal Columns):</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addContainer('container1', ['field1', 'field2', 'field3'], 3, '', '', [])
// Creates: col-md-4, col-md-4, col-md-4 (12/3 = 4)</code></pre>

    <p><strong>Array (Custom Sizes):</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addContainer('container2', ['field1', 'field2', 'field3'], [4, 5, 3], '', '', [])
// Creates: col-md-4, col-md-5, col-md-3 (total = 12)</code></pre>

    <div class="alert alert-warning">
        <strong>Note:</strong> Bootstrap uses a 12-column grid system. Column sizes should ideally sum to 12 per row for best results.
    </div>

    <h3>4. Position Control</h3>
    <p>Control where the container appears in the form:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Insert before 'status' field
->addContainer('container1', [...], 3, 'status', '', [])

// Append at the end (empty string or omit)
->addContainer('container2', [...], 3, '', '', [])</code></pre>

    <h3>5. Container Title</h3>
    <p>Add a descriptive title to the container:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addContainer('container1', [...], 3, '', 'Contact Information', [])
// Generates: &lt;h4 class="mb-3"&gt;Contact Information&lt;/h4&gt;</code></pre>

    <h3>6. Custom Attributes</h3>
    <p>Add any HTML attributes to the container div:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addContainer('container1', [...], 3, '', 'Title', [
    'class' => 'border rounded p-3 mb-4',
    'style' => 'background-color: #f8f9fa;',
    'data-section' => 'contact',
    'id' => 'custom-id'                    // Note: 'id' will be overridden by container ID
])</code></pre>

    <h2>Generated HTML Structure</h2>

    <p>The <code>addContainer()</code> method generates a Bootstrap grid structure:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;!-- Container with 3 equal columns --&gt;
&lt;div class="border rounded p-3 mb-4" id="contact_info" style="background-color: #f8f9fa;"&gt;
    &lt;h4 class="mb-3"&gt;Contact Information&lt;/h4&gt;

    &lt;!-- Row 1 (first 3 fields) --&gt;
    &lt;div class="row g-3 milk-row-1 mb-3"&gt;
        &lt;div class="col-md-4"&gt;
            &lt;!-- name field HTML --&gt;
        &lt;/div&gt;
        &lt;div class="col-md-4"&gt;
            &lt;!-- email field HTML --&gt;
        &lt;/div&gt;
        &lt;div class="col-md-4"&gt;
            &lt;!-- phone field HTML --&gt;
        &lt;/div&gt;
    &lt;/div&gt;

    &lt;!-- Row 2 (remaining fields, if wrapping) --&gt;
    &lt;div class="row g-3 milk-row-2"&gt;
        &lt;div class="col-md-4"&gt;
            &lt;!-- additional field --&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>

    
    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Form/builders-form-fields"><strong>Field Management</strong></a>: Adding, removing, modifying, and organizing form fields</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-conditional-visibility"><strong>Conditional Field Visibility</strong></a>: Show/hide fields based on other field values</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-validation"><strong>Form Validation</strong></a>: Custom validation and error handling</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-containers"><strong>Organizing Fields in columns with Containers</strong></a>: Grouping fields into containers for better organization</li>
        
    </ul>

</div>
