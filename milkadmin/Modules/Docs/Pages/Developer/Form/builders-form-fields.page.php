<?php
namespace Modules\Docs\Pages;

/**
 * @title Field Configuration
 * @guide developer
 * @order 41
 * @tags FormBuilder, fields, field-configuration, form-fields
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>FormBuilder - Field Configuration</h1>

    <p>Form field configuration starts from the Model definition. FormBuilder methods allow you to modify this base configuration to customize the fields.</p>

    <h2>Available Methods</h2>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th style="width: 30%">Method</th>
                <th style="width: 40%">Description</th>
                <th style="width: 30%">Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>field(string $key)</code></td>
                <td>Selects an existing field or creates it if it doesn't exist</td>
                <td><code>->field('email')</code></td>
            </tr>
            <tr>
                <td><code>type(string $type)</code></td>
                <td>Sets the data type (string, int, date, etc.)</td>
                <td><code>->type('string')</code></td>
            </tr>
            <tr>
                <td><code>formType(string $type)</code></td>
                <td>Sets the form field type (text, select, textarea, etc.)</td>
                <td><code>->formType('select')</code></td>
            </tr>
            <tr>
                <td><code>label(string $label)</code></td>
                <td>Sets the field label</td>
                <td><code>->label('Email')</code></td>
            </tr>
            <tr>
                <td><code>options(array $options)</code></td>
                <td>Sets options for select/checkbox/radio</td>
                <td><code>->options(['1' => 'Yes'])</code></td>
            </tr>
            <tr>
                <td><code>required(bool $req = true)</code></td>
                <td>Makes the field required</td>
                <td><code>->required()</code></td>
            </tr>
            <tr>
                <td><code>helpText(string $text)</code></td>
                <td>Sets the help text below the field</td>
                <td><code>->helpText('Format: xxx-xxxx')</code></td>
            </tr>
            <tr>
                <td><code>value(mixed $value)</code></td>
                <td>Sets the field value</td>
                <td><code>->value('default')</code></td>
            </tr>
            <tr>
                <td><code>default(mixed $value)</code></td>
                <td>Sets the default value if no value exists</td>
                <td><code>->default('IT')</code></td>
            </tr>
            <tr>
                <td><code>checkboxValues($checked, $unchecked)</code></td>
                <td>Sets custom values for checkbox (e.g., 'S'/'N', 'Y'/'N')</td>
                <td><code>->checkboxValues('S', 'N')</code></td>
            </tr>
            <tr>
                <td><code>disabled(bool $dis = true)</code></td>
                <td>Disables the field</td>
                <td><code>->disabled()</code></td>
            </tr>
            <tr>
                <td><code>readonly(bool $ro = true)</code></td>
                <td>Makes the field readonly</td>
                <td><code>->readonly()</code></td>
            </tr>
            <tr>
                <td><code>class(string $class)</code></td>
                <td>Sets the CSS class</td>
                <td><code>->class('form-control-lg')</code></td>
            </tr>
            <tr>
                <td><code>errorMessage(string $msg)</code></td>
                <td>Sets a custom error message</td>
                <td><code>->errorMessage('Invalid email')</code></td>
            </tr>
            <tr>
                <td><code>moveBefore(string $field)</code></td>
                <td>Moves the field before another field</td>
                <td><code>->moveBefore('status')</code></td>
            </tr>
        </tbody>
    </table>

    <h2>Basic Usage</h2>

    <p>Methods are chained after <code>field()</code> which selects the field to configure:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = FormBuilder::create($model, $this->page)
    ->field('email')
        ->label('Email Address')
        ->required()
        ->errorMessage('Please enter a valid email')
        ->helpText('We will never share your email with anyone else')
    ->field('status')
        ->formType('select')
        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
        ->value('active')
    ->getForm();
</code></pre>

    <h2>Creating New Fields</h2>

    <p>If the field doesn't exist in the Model, it will be created automatically:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('phone')
    ->type('string')
    ->label('Phone Number')
    ->required()
    ->helpText('Format: 555-1234')
</code></pre>

    <h2>Modifying Existing Fields</h2>

    <p>Fields defined in the Model can be modified:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// 'email' field defined in the Model
->field('email')
    ->label('Email Address')  // Modifies the label
    ->helpText('We will never share your email')  // Adds help text
    ->required()  // Makes it required
</code></pre>

    <h2>Examples by Field Type</h2>

    <h3>Text Field</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('username')
    ->type('string')
    ->label('Username')
    ->required()
    ->errorMessage('Username is required')
    ->helpText('Choose a unique username')
</code></pre>

    <h3>Select Field</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('category')
    ->formType('select')
    ->label('Category')
    ->options([
        '1' => 'Electronics',
        '2' => 'Books',
        '3' => 'Clothing'
    ])
    ->value('1')
    ->required()
</code></pre>

    <h3>Textarea Field</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('description')
    ->formType('textarea')
    ->label('Description')
    ->helpText('Provide a detailed description')
</code></pre>

    <h3>Date Field</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('publish_date')
    ->type('date')
    ->label('Publish Date')
    ->value(date('Y-m-d'))
</code></pre>

    <h3>Checkbox with Custom Values</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('active')
    ->formType('checkbox')
    ->label('Active')
    ->checkboxValues('S', 'N')  // 'S' when checked, 'N' when unchecked
</code></pre>

    <h3>Switch Field</h3>
    <p>A switch is a checkbox with Bootstrap's <code>form-switch</code> class. Use <code>checkboxValues()</code> for custom values and <code>formParams(['form-check-class' => 'form-switch'])</code> to enable the switch style:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// In your Model
->string('active', 1)
    ->label('Active')
    ->formType('checkbox')
    ->checkboxValues('S', 'N')  // 'S' = On, 'N' = Off
    ->formParams(['form-check-class' => 'form-switch'])

// Or with FormBuilder
->field('notifications')
    ->formType('checkbox')
    ->label('Enable Notifications')
    ->checkboxValues('Y', 'N')
    ->formParams(['form-check-class' => 'form-switch'])
</code></pre>

    <h3>Disabled Field</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('created_at')
    ->label('Created At')
    ->readonly()
</code></pre>

    <h3>Field with Set Value</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('post_id')
    ->type('int')
    ->value($post_id)
    ->readonly()
</code></pre>

    <h2>Field Repositioning</h2>

    <p>The <code>moveBefore()</code> method moves the field before another field:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('email')
    ->label('Email')
    ->moveBefore('password')  // Email will appear before password

->field('phone')
    ->type('string')
    ->label('Phone')
    ->moveBefore('address')  // Phone will appear before address
</code></pre>

    <h2>Complete Example</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules\Posts;

use App\Abstracts\AbstractController;
use App\{Response, Route};
use Builders\FormBuilder;

class PostsController extends AbstractController
{
    public function edit() {
        $response = $this->getCommonData();

        $response['form'] = FormBuilder::create($this->model, $this->page)
            // Modify existing fields from the Model
            ->field('title')
                ->label('Post Title')
                ->required()
                ->errorMessage('Title is required')
                ->helpText('Enter a catchy title for your post')

            ->field('content')
                ->formType('editor')
                ->label('Post Content')
                ->required()

            ->field('status')
                ->formType('select')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived'
                ])
                ->value('draft')

            // Create a new field not present in the Model
            ->field('excerpt')
                ->type('text')
                ->formType('textarea')
                ->label('Excerpt')
                ->helpText('Brief summary of the post content')
                ->moveBefore('content')

            ->field('created_at')
                ->readonly()

            ->addStandardActions()
            ->getForm();

        $response['title'] = 'Edit Post';
        Response::render(__DIR__ . '/Views/edit_page.php', $response);
    }
}
</code></pre>

    <h2>Additional Methods</h2>

    <h3>Adding New Fields with addField()</h3>

    <p>The <code>addField()</code> method allows you to add a new field programmatically, specifying all properties in a single options array.</p>

    <p><strong>Syntax:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addField(string $field_name, string $type, array $options = [], string $position_before = '')
</code></pre>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$field_name</code>: Name of the field to add</li>
        <li><code>$type</code>: Data type (string, int, date, datetime, bool, etc.)</li>
        <li><code>$options</code>: Array with all field configurations (label, form-type, required, options, etc.)</li>
        <li><code>$position_before</code>: (Optional) Name of the field before which to insert the new field</li>
    </ul>

    <p><strong>Basic Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Adds a simple text field at the end of the form
->addField('phone', 'string', [
    'label' => 'Phone Number',
    'form-type' => 'text',
    'required' => true,
    'form-params' => [
        'help-text' => 'Format: +39 123 456 7890'
    ]
])
</code></pre>

    <p><strong>Example with Positioning:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Adds a select field before the 'status' field
->addField('priority', 'int', [
    'label' => 'Priority',
    'form-type' => 'select',
    'options' => [
        1 => 'Low',
        2 => 'Medium',
        3 => 'High',
        4 => 'Critical'
    ],
    'default' => 2
], 'status')
</code></pre>

    <p><strong>Complete Example with All Options:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addField('custom_field', 'string', [
    'label' => 'Custom Field',
    'form-type' => 'textarea',
    'required' => true,
    'default' => '',
    'form-params' => [
        'help-text' => 'Enter your custom content here',
        'class' => 'custom-textarea',
        'rows' => 5,
        'readonly' => false,
        'disabled' => false,
        'invalid-feedback' => 'This field is required'
    ]
], 'content')
</code></pre>

    <p><strong>Difference between addField() and field():</strong></p>
    <ul>
        <li><code>field()</code>: Fluent approach with method chaining. Ideal for step-by-step configurations</li>
        <li><code>addField()</code>: Complete configuration in a single array. Ideal for adding fields in loops or when you already have all configurations in an array</li>
    </ul>

    <h3>Modifying Existing Fields with modifyField()</h3>

    <p>The <code>modifyField()</code> method allows you to modify an existing field by merging new options with existing ones, and optionally repositioning it.</p>

    <p><strong>Syntax:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->modifyField(string $field_name, array $options, string $position_before = '')
</code></pre>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$field_name</code>: Name of the field to modify</li>
        <li><code>$options</code>: Array with properties to modify or add (will be merged with existing properties)</li>
        <li><code>$position_before</code>: (Optional) Name of the field before which to move the modified field</li>
    </ul>

    <p><strong>Basic Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Modifies the label and makes an existing field required
->modifyField('email', [
    'label' => 'Email Address',
    'required' => true
])
</code></pre>

    <p><strong>Example with Repositioning:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Modifies a field and moves it before 'password'
->modifyField('email', [
    'label' => 'User Email',
    'form-params' => [
        'help-text' => 'This will be your login username'
    ]
], 'password')
</code></pre>

    <p><strong>Example: Changing a Field from Text to Select:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Transforms the 'status' field into a select with options
->modifyField('status', [
    'form-type' => 'select',
    'options' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived'
    ]
])
</code></pre>

    <p><strong>Example: Adding Help Text and Validation:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Adds help text and custom error message
->modifyField('phone', [
    'form-params' => [
        'help-text' => 'Format: +39 123 456 7890',
        'invalid-feedback' => 'Please enter a valid phone number',
        'pattern' => '^\+?[0-9\s]+$'
    ]
])
</code></pre>

    <p><strong>Difference between modifyField() and field():</strong></p>
    <ul>
        <li><code>field()</code>: Creates the field if it doesn't exist, otherwise modifies it. Fluent approach</li>
        <li><code>modifyField()</code>: Only modifies existing fields. Allows merging complex arrays and repositioning in a single call</li>
    </ul>

    <h3>Removing Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->removeField('created_at')
->removeField('updated_at')
</code></pre>

    <h3>Field Order</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->fieldOrder(['id', 'title', 'content', 'status'])
</code></pre>

    <h3>Conditional Visibility</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->showFieldWhen('publish_date', 'status', 'published')
</code></pre>

</div>
