<?php
namespace Modules\docs;
/**
 * @title Abstract Object   
 * @category Abstracts Class
 * @order 60
 * @tags AbstractObject, object, model, database, table, schema, rules, fields, init_rules, validation, getter, setter, form, MySQL, data-structure, ORM, field-types, string, int, float, bool, date, datetime, array, enum, list, nullable, default, primary, get_value, set_value, to_array, to_mysql_array, form-validation, form-types, text, textarea, select, file, checkbox, radio, hidden, password, email, url, custom-validation, _validate, _get, _set, _edit, _get_raw, MessagesHandler, frontend-validation, backend-validation, checkboxes, radios, options, form-params, invalid-feedback, required, placeholder
 */
use MilkCore\Route;

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract class object</h1>
    <p>Model objects are the data structures that are extracted from the model itself.</p>
    <p>To create a custom object you can extend the <code>AbstractObject</code> class and define the structure rules.</p>
    <pre class="language-php"><code>namespace Modules\BaseModule;
use MilkCore\AbstractObject;
!defined('MILK_DIR') && die(); // Avoid direct access
class BaseModuleObject extends AbstractObject
{
    public function init_rules() {
        $this->rule('id', [
            'type' => 'int', 'primary' => true, 'list' => false
        ]);
        $this->rule('title', [
            'type' => 'string', 'length' => 100, 'label' => 'Title'
        ]);
        $this->rule('created_at', [
            'type' => 'datetime'
        ]);
        $this->rule('category', [
            'type' => 'string', 'length' => 100,
            //  '_get' => [$this, 'get_category'],
           
        ]);
        $this->rule('status', [
            'type' => 'list', 'length' => 100,
            'options' => ['active'=>'<span class="btn btn-info">ACTIVE</span>', 'inactive' => 'INACTIVE']
        ]);
    }
}</code></pre>

    <p>To access the object data you can use the <code>$this->object</code> property that contains the current object. To access the processed data you can use the
    <code>get_val('class', 'field')</code> function that returns the value of the <code>field</code> field of the <code>class</code> class.</p>
    <p class="alert alert-warning">WARNING: <b>get_property</b> does not work with custom Object classes.</p>


    <p>When printing fields using the get_val($class, $property) function it is possible to customize a method with the field name and the <code>get_</code> prefix for example <code>get_category</code> for the <code>category</code> field.</p>
    <p>If you want to customize the calling function you can use the <code>_get</code> property that accepts a callable function set within the rules</p>

    <p>When preparing fields for mysql saving you can use the function with prefix <code>set_</code> + the field name for example <code>set_category</code> for the <code>category</code> field.</p>
    <p>If you want to customize the saving function you can use the <code>_set</code> property that accepts a callable function set within the rules</p>

    <p>If the field must always be transformed in a certain way when called
        you can use the rules property _get_raw. This property accepts a callable function that will be called every time the field is called. Note that there is no function that is always called even if this variable is not set. while for _set, _get and _edit there exists the corresponding function get_{field_name}, set_{field_name} edit_{field_name}</p>


    <h2>Rules and their configuration</h2>

    <p>Rules define the structure of object fields and are configured in the <code>init_rules()</code> method. 
    Each rule is defined through the <code>rule($name, $options)</code> method where:</p>

    <ul>
        <li><code>$name</code>: field name (must be lowercase and without special characters)</li>
        <li><code>$options</code>: array of configuration options</li>
    </ul>

    <h3 class="mt-3">Main options</h3>

    <pre><code class="language-php">
    // Basic fields
    $this->rule('title', [
        'type' => 'string',      // field type
        'length' => 100,         // max length for strings 
        'label' => 'Title',      // label for forms/lists
        'nullable' => false,     // if it can be null
        'default' => null,       // default value
        'mysql' => true         // if to save in DB
    ]);

    // Select/Enum
    $this->rule('status', [
        'type' => 'list',  // or 'enum'
        'options' => [
            'active' => 'Active',
            'inactive' => 'Inactive' 
        ]
    ]);

    // Form customization
    $this->rule('description', [
        'type' => 'text',
        'form' => 'textarea',  // form field type
        'form-params' => [     // form parameters
            'required' => true,
            'invalid-feedback' => 'Required field'
        ]
    ]);

    // File upload
    $this->rule('image', [
        'type' => 'string',
        'form' => 'file',
        'form-params' => [
            'accept' => 'image/*'
        ]
    ]);

    // Multiple checkboxes
    $this->rule('permissions', [
        'type' => 'array',
        'form' => 'checkboxes', 
        'options' => [
            'read' => 'Read',
            'write' => 'Write'
        ]
    ]);
    </code></pre>

    <h3 class="mt-3">Available field types</h3>

    <ul>
        <li><code>string</code>: text field</li>
        <li><code>text</code>: long text</li>
        <li><code>int</code>: integer numeric</li>
        <li><code>float</code>: decimal numeric</li>
        <li><code>bool</code>: boolean</li>
        <li><code>date</code>: date</li>
        <li><code>datetime</code>: date and time</li>
        <li><code>time</code>: time</li>
        <li><code>list</code>: select with options</li>
        <li><code>enum</code>: enum with fixed options</li>
        <li><code>array</code>: array/json</li>
    </ul>

    <h3 class="mt-3">Forms and validation</h3>

    <p>It is possible to customize the HTML form through the options:</p>

    <pre><code class="language-php">
    $this->rule('field', [
        'form-type' => 'text|textarea|select|file|etc',  // input type
        'form-params' => [
            'required' => true,
            'placeholder' => '...',
            'class' => 'custom-class',
            'invalid-feedback' => 'Error message'
        ]
    ]);
    </code></pre>

    <h3 class="mt-3">Available form types</h3>
    <ul>
        <li><code>text</code>: text field</li>
        <li><code>textarea</code>: long text</li>
        <li><code>select</code>: select with options. The options parameter is mandatory.</li>
        <li><code>list</code>: Alias of select</li>
        <li><code>enum</code>: select with fixed options. The options parameter without keys is mandatory (keys are equal to values)</li>
        <li><code>file</code>: file upload</li>
        <li><code>checkbox</code>: single checkbox. The value parameter is also mandatory</li>
        <li><code>checkboxes</code>: multiple checkboxes. The options parameter is mandatory.</li>
        <li><code>hidden</code>: hidden field</li>
        <li><code>password</code>: password field</li>
        <li><code>email</code>: email field</li>
        <li><code>url</code>: url field</li>
        <li><code>date</code>: date field</li>
        <li><code>datetime-local</code>: date and time field</li>
        <li><code>time</code>: time field</li>
        <li><code>radios</code>: radio buttons. The options parameter is mandatory.</li>
        
    </ul>

    <h2 class="mt-3">Validation and custom Getter/Setter</h2>

    <h3>Backend Validation</h3>
    <p>Validation functions don't return anything, but set an error message if validation fails.</p>
    <p>There are two ways to validate fields:</p>

    <h4>1. validate_{field_name} method</h4>
    <pre><code class="language-php">
    public function validate_multiple_options($value, array $data): void {
        if (!in_array('opt1', $value)) {
            MessagesHandler::add_error('Option 1 is required', 'multiple_options');
        }
    }
    </code></pre>

    <h4>2. Through rule _validate</h4>
    <pre><code class="language-php">
    $this->rule('field', [
        'type' => 'string',
        '_validate' => function($value, $data) {
            if (empty($value)) {
                MessagesHandler::add_error('Field is required', 'field');
            }
        }
    ]);
    </code></pre>

    <h3 class="mt-3">Custom Getter/Setter</h3>




    <h4 class="mt-3">1. Custom methods for fields</h4>
    <p>Methods <code>get_{field_name}</code> / <code>set_{field_name}</code> / <code>edit_{field_name}</code> for editing</p>
    <pre><code class="language-php">
    // Custom getter for display 
    public function get_image($value) {
        $data = json_decode($value, true);
        return '<img src="'.$data['url'].'" width="100">';
    }

    // Custom setter for MySQL saving
    public function set_image($value) {
        if(is_array($value)) {
            return json_encode($value);
        }
        return $value;
    }

    // Custom edit for edit form
    public function edit_image($value) {
        $data = json_decode($value, true);
        return $data['url'] ?? '';
    }
    </code></pre>

    <h4>2. Through rules with _get, _set, _edit and _get_raw</h4>

    <pre><code class="language-php">
    $this->rule('status', [
        'type' => 'string',
        '_get' => function($value) {
            return strtoupper($value);
        },
        '_set' => function($value) {
            return strtolower($value); 
        },
        '_edit' => function($value) {
            return ucfirst($value);
        },
        '_get_raw' => function($value) {
            return json_decode($value, true);
        }
    ]); 
    </code></pre>

    <h3 class="mt-3">Frontend Validation</h3>

    <p>It is possible to implement client-side validations using the <code>form:validate</code> event:</p>

    <pre><code class="language-javascript">
    document.getElementById('myform').addEventListener('form:validate', function(e) {
        // Custom validation
        if (!document.getElementById('required_field').checked) {
            // Invalidate the form
            e.detail.isValid = false;
            
            // Mark field as invalid
            document.getElementById('required_field')
                .closest('.js-form-checkboxes-group')
                .classList.add('is-invalid');
        }
    });

    // Reset validation on input
    document.getElementById('myform').addEventListener('input', function() {
        document.getElementById('required_field')
            .closest('.js-form-checkboxes-group')
            .classList.remove('is-invalid'); 
    });
    </code></pre>

    <p>The <code>form:validate</code> event is fired before submit and allows to:</p>

    <ul>
        <li>Block form submission (<code>e.detail.isValid = false</code>)</li>
        <li>Mark fields as invalid</li> 
        <li>Show custom error messages</li>
    </ul>

    <hr class="mt-3">

    <h1 class="mt-4">AbstractObject public methods documentation</h1>

    <h3>__construct($attributes = null)</h3>
    <p>Initializes the object setting rules and initial fields.</p>
    <pre><code class="language-php">
    // Example
    $object = new MyObject(['title' => 'Demo']);
    </code></pre>

    <h3>init_rules()</h3>
    <p>Put here the definition of rules for object fields.</p>
    <pre><code class="language-php">
    // Example
    public function init_rules() {
    $this->rule('title', ['type' => 'string', 'label' => 'Title']);
    }
    </code></pre>

    <h3>getIterator(): \Traversable</h3>
    <p>Allows to iterate through the object with foreach or while.</p>

    <h3>rule(string $name, array $options): void</h3>
    <p>Adds or modifies a rule for a field.</p>
    <pre><code class="language-php">
    // Example
    $this->rule('status', ['type' => 'enum', 'options' => ['draft','published']]);
    </code></pre>

    <h3>__get(string $name)</h3>
    <p>Retrieves the value of a field.</p>
    <pre><code class="language-php">
    // Example
    echo $object->title;
    </code></pre>

    <h3>__set(string $name, $value): void</h3>
    <p>Sets a value in the field, with possible validation.</p>
    <pre><code class="language-php">
    $object->title = 'New Title';
    </code></pre>

    <h3>__isset($name)</h3>
    <p>Checks if a field is set.</p>

    <h3>get_value(string $name)</h3>
    <p>Returns the field value applying any custom formatting.</p>

    <h3>merge($data)</h3>
    <p>Merges current data with others passed as array or object.</p>
    <pre><code class="language-php">
    $object->merge(['title' => 'Updated', 'status' => 'published']);
    </code></pre>

    <h3>get_rules($key = '', $value = '')</h3>
    <p>Filters rules based on a key and value.</p>

    <h3>to_array(): array</h3>
    <p>Converts the object to an associative array.</p>
    <pre><code class="language-php">
    $array = $object->to_array();
    </code></pre>

    <h3>to_mysql_array(): array</h3>
    <p>Prepares data to be saved in the database (e.g. JSON fields handling).</p>

    <h3>property_exists($name)</h3>
    <p>Verifies if a given field exists in the attributes array.</p>

    <h3>get_primaries(): array</h3>
    <p>Returns the primary keys defined in the rules.</p>

    <h3>get_primary_key()</h3>
    <p>Returns the primary key if single, otherwise null.</p>

    <h3>get_schema($table): Schema</h3>
    <p>Creates the MySQL schema structure based on rules and primary keys.</p>

</div>