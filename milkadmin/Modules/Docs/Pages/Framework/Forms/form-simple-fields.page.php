<?php
namespace Modules\Docs\Pages;
use App\{Get, Form};
/**
 * @title Simple Fields
 * @guide framework
 * @order 20
 * @tags form, input, textarea, checkbox, radio, select, upload, Bootstrap, validation
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

    <div class="alert alert-primary">
        <h5 class="alert-heading">Quick Form Creation with FormBuilder</h5>
        <p class="mb-0">
            This is a manual, artisanal system for creating form fields. If you need to create forms quickly from your Models,
            we recommend using the <strong>FormBuilder</strong> which can generate complete forms in minutes:
            <br><br>
            <a href="?page=docs&action=Developer/Form/builders-form" class="alert-link">
                <strong>→ Getting Started - Forms with FormBuilder</strong>
            </a>
        </p>
    </div>

    <h1>Form</h1>
    <p>Form fields are formatted according to Bootstrap standards. The <code>Form</code> class helps to print them</p>
    <h2>The HTML</h2>
    <p>The class prints the form fields, not the structure</p>
    <h4>One column</h4>
    <p>The form class prints the input, label and error message. By default, fields are in floating style, but can be disabled.</p>
    <div class="bg-light p-2">
        <div class="form-group col-xl-6">
            <?php Form::input('text', 'name', 'Name', '', ['floating'=>false]); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;div class=&quot;form-group col-xl-6&quot;&gt;
    &lt;?php Form::input('text', 'name', 'Name', '', ['id'=&gt;'sdf', 'floating'=&gt;false]); ?&gt;
&lt;/div&gt;</code></pre>
<br>
    <h4>Two columns</h4>
    <div class="bg-light p-2">
    <div class="row g-2 mb-3">
            <div class="col-md">
                <?php Form::input('text', 'name', 'Name', ''); ?>
            </div>
            <div class="col-md">
                <?php Form::input('text', 'surname', 'Surname', ''); ?>
            </div>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;div class=&quot;row g-2 mb-3&quot;&gt;
    &lt;div class=&quot;col-md&quot;&gt;
        &lt;?php Form::input('text', 'name', 'Name', ''); ?&gt;
    &lt;/div&gt;
    &lt;div class=&quot;col-md&quot;&gt;
        &lt;?php Form::input('text', 'surname', 'Surname', ''); ?&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
    <br>

    <h2>The options</h2>
    <p>Within the functions to draw forms there is often an $options field, or in groups there are 2 fields options_fields and options_group. <code>Options and options_fields</code> are the same thing and allow you to add attributes to the field being drawn.<br>Some attributes are:<br>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">['class' => 'my-class', 'id' => 'my-id', 'required' => true, 'invalid-feedback'=>'The field is required','minlength' => 3, 'maxlength' => 10, 'pattern' => '[A-Za-z]{3,10}', 'placeholder' => 'Enter your name', 'size' => 10, 'step' => 2, 'min' => 0, 'max' => 10, 'multiple' => true, 'onchange' => 'alert("change")', 'oninput' => 'alert("input")']</code></pre>
    <p>The id, if not set, is automatically generated from the name. <code>invalid-feedback</code> is the error message if the field is not valid</p>
    <p><code>options_group</code> instead manages the options of checkboxes and radios groups. The settings are:<br>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">['form-check-class'=>'string', 'invalid-feedback'=>'instead of invalid_feedback of individual fields', 'form-group-class' => 'string', 'label' => 'string']</code></pre>
    <br>Note that placeholder doesn't work with floating enabled.
</p>
    <br>

    <h2>Input</h2>
    <h5><code>Form::input($type, $name, $label, $value = '', $options = array(), $return = false)</code></h5>
    <p> $field = Hook:set('form_input', $field, $type, $name, $label, $value, $options)</p>
    <div class="bg-light p-2">
        <div class="form-group col-xl-6">
            <?php Form::input('text', 'name', 'Name', '', ['id'=>'my-custom-id']); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::input('text', 'name', 'Name', '', ['id'=>'my-custom-id']);</code></pre>

    <h4>Input list</h4>
    <div class="bg-light p-2">
    <div class="form-group col-xl-6">
            <?php Form::input('text', 'food', 'Food','', ['list'=>[
                'Pizza', 'Pasta', 'Hamburger', 'Sushi', 'Sashimi', 'Ramen', 'Soba', 'Udon', 'Tempura', 'Tonkatsu' ]]); ?>
    </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::input('text', 'food', 'Food','', ['list'=>[
    'Pizza', 'Pasta', 'Hamburger', 'Sushi', 'Sashimi', 'Ramen', 'Soba', 'Udon', 'Tempura', 'Tonkatsu' ]]);</code></pre>

    <h4>Input file</h4>
    <div class="bg-light p-2">
    <div class="form-group col-xl-6">
            <?php Form::input('file', 'file', 'File'); ?>
    </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::input('file', 'file', 'File');</code></pre>
    <br>
    <h4>Upload files plugin</h4>
    <p>To handle uploads asynchronously and more comprehensively, a template plugin has been written</p>
    <div class="form-group col-xl-6">
    <?php echo Get::themePlugin('UploadFiles',['name'=>'file', 'label'=>'File', 'value'=>'', 'options'=>['multiple'=>true], 'upload_name' => 'my_upload2'] ); ?>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">echo Get::themePlugin('UploadFiles',['name'=>'file', 'label'=>'File', 'value'=>'', 'options'=>[], 'upload_name' => 'my_upload1'] );</code></pre>
    <p>In the module I add upload handling</p>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Hooks::set('upload_maxsize_my_upload1', function($max_size) {
    return 1024*1024*1000;
});
Hooks::set('upload_accept_my_upload1', function($accept) {
    return 'image/*';
});</code></pre>
    <p>In the 'upload_name' setting is the name of the upload that must be instantiated when I create the field and which allows you to set the Hooks that handle the upload</p>
    <p>If you want to allow multiple file uploads you can add the 'multiple' => true option</p>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Get::themePlugin('UploadFiles',['name'=>'file', 'label'=>'File', 'value'=>'', 'options'=>['multiple'=>true], 'upload_name' => 'my_upload2'] );</code></pre>
    
    <b>$max_size = Hooks::run("upload_maxsize_".$name, 10000000);</b><br>
    <b>$accept = Hooks::run("upload_accept_".$name, '');</b><br>
    <b>$error_msg = Hooks::run("upload_check_".$name, '', $_FILES['file']);</b><br>
    <p>I send the error message. If an error message is compiled then the upload is interrupted</p>
    <b>$temp_dir = Hooks::run('upload_save_dir_'.$name,  $temp_dir);</b><br>
    <p>The directory where to save the file can be changed with a hook</p>
    <b>$file_name = Hooks::run('upload_file_name_'.$name,  $file_name, $_FILES['file']);</b><br>
    <b>$permission = Hooks::run('upload_permission_file_'.$name, 0666);</b>

    <p>Files once uploaded and saved, then return the name of the uploaded file and the original name of the uploaded file</p>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-html">&lt;input type="hidden" class="js-filename1" name="{input_name}_file_name[]" value=""&gt;
&lt;input type="hidden" class="js-fileoriginalname1" name="{input_name}_file_original_name[]" value=""&gt;</code></pre><br>
    <b>Validation</b></br>
    The plugin handles required validation like a normal input field within bootstrap. Just set <code>options['required'=>'true' 'invalid-feedback'=>'error message']</code>. However, if you want to check from code whether a file has been uploaded from the upload field, since the field is always empty, the is_compiled() function is added to the field which returns boolean.
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">if (document.getElementById('myuploadfield').is_compiled()) { //... }</code></pre> 
    <br><br>

    <h4>color</h4>
    <div class="bg-light p-2">
    <div class="form-group col-lg-1">
            <?php Form::input('color', 'color', 'Color', '#ff0000'); ?>
    </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::input('color', 'color', 'Color', '#ff0000');</code></pre>

    <h4>groups</h4>
    <div class="bg-light p-2">
        <div class="input-group">
            <span class="input-group-text">Email</span>  
            <?php Form::input('Email1', '', '', '',  ['floating'=>false]); ?>
            <span class="input-group-text">@</span>
            <?php Form::select('Email2', '', ['gmail' => 'gmail', 'yahoo' => 'yahoo', 'hotmail' => 'hotmail'], 'gmail', ['floating'=>false]); ?>
            <span class="input-group-text">.com</span>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;div class=&quot;input-group&quot;&gt;
    &lt;span class=&quot;input-group-text&quot;&gt;Email&lt;/span&gt;
    &lt;?php Form::input('Email1', '', '', '',  ['floating'=&gt;false]); ?&gt;
    &lt;span class=&quot;input-group-text&quot;&gt;@&lt;/span&gt;
    &lt;?php Form::select('Email2', '', ['gmail' =&gt; 'gmail', 'yahoo' =&gt; 'yahoo', 'hotmail' =&gt; 'hotmail'], 'gmail', ['floating'=&gt;false]); ?&gt;
&lt;/div&gt;</code></pre>
          
    <br>
    <h2>Textarea</h2>
    <h5><code>Form::textarea($name, $label, $value = '', $rows = 3, $options = array(), $return = false)</code></h5>
    <p> $field = Hook:set('form_textarea', $field, $name, $label, $value, $rows, $options, $return)</p>
    <div class="bg-light p-2"> 
        <div class="form-group col-xl-6">
            <?php Form::textarea('myTextarea', 'Label', '', 6); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::textarea('myTextarea', 'Label', '', 6);</code></pre>
    <br>
    <h2>checkbox</h2>
    <h5><code>Form::checkbox($name, $label, $value = '', $is_checked = false, $options = array(), $return = false)</code></h5>
    <p> $field = Hook:set('form_checkbox', $field, $name, $label, $value, $checked, $options, $return)</p>
    <div class="bg-light p-2"> 
        <div class="form-group col-xl-6">
            <div class="form-check">
                <?php Form::checkbox('myName', 'Label', '1'); ?>
            </div>
            <div class="form-check">
                <?php Form::checkbox('myName', 'Checked', '1', true); ?>
            </div>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;div class=&quot;form-check&quot;&gt;Form::checkbox('myName', 'Label', '1');&lt;/div&gt;
&lt;div class=&quot;form-check&quot;&gt;Form::checkbox('myName', 'Checked', '1', true);&lt;/div&gt;
    </code></pre>

    <h2>Checkboxes</h2>
    <h5><code>Form::checkboxes($name, $list_of_checkbox, $selected_value, $inline, $options_group = [], $options_field = [], $return = false)</code></h5>
    <p> $field = Hook:set('form_checkboxes', $field, $name, $list_of_checkbox, $selected_value, $inline, $options_group, $options_field, $return)</p>
    <div class="bg-light p-2"> 
        <div class="form-group col-xl-6">
            <?php Form::checkboxes('myCheckboxes', ['1' => 'One', '2' => 'Two', '3' => 'Three'],  '2', true, ['label'=>'Select fields']); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::checkboxes('myCheckboxes', ['1' => 'One', '2' => 'Two', '3' => 'Three'], '2', true, ['label'=>'Select fields']);</code></pre>

    <h4>Switch</h4>
    
    <div class="bg-light p-2"> 
        <div class="form-group  col-xl-6">
            <?php Form::checkboxes('mySwitch', ['1' => 'One', '2' => 'Two', '3' => 'Three'],  '2', false, ['label'=>'Select Switch', 'form-check-class'=>'form-switch']); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::checkboxes('mySwitch', 
    ['1' => 'One', '2' => 'Two', '3' => 'Three'], 
    '2', 
    false, 
    ['label'=>'Select Switch', 'form-check-class'=>'form-switch']
);</code></pre>

    <br>
    <h2>Radio</h2>
    <h5><code>Form::radio($name, $label, $value = '', $options = array(), $return = false)</code></h5>
    <p> $field = Hook:set('form_radio', $field, $name, $label, $value, $options, $return)</p>
    <div class="bg-light p-2"> 
        <div class="form-group
        col-xl-6">
            <div class="form-check">
                <?php Form::radio('myRadio', 'Label', '1'); ?>
            </div>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::radio('myRadio', 'Label', '1');</code></pre>

    <h2>Radios</h2>
    <h5><code>Form::radios($name, $list_of_radio, $selected_value = '',  $inline = false, $options_group = [], $options_field = [], $return = false)</code></h5>
    <p> $field = Hook:set('form_radios', $field, $name, $list_of_radio, $selected_value,  $inline,  $options_group, $options_field)</p>
    <div class="bg-light p-2"> 
        <div class="form-group
        col-xl-6">
            <?php Form::radios('myRadios', ['1' => 'One', '2' => 'Two', '3' => 'Three'],  '2'); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::radios('myRadios', ['1' => 'One', '2' => 'Two', '3' => 'Three'], '2');</code></pre>

    <br>
    <h2>Select</h2>
    <h5><code>Form::select($name, $label, $options = array(), $value = '', $multiple = false, $options = array(), $return = false)</code></h5>
    <p> $field = Hook:set('form_select', $field, $name, $label, $options, $value, $multiple, $options, $return)</p>
    <div class="bg-light p-2"> 
        <div class="form-group col-xl-6">
            <?php Form::select('mySelect', 'Label', ['1' => 'One', '2' => 'Two', '3' => 'Three'], '2'); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::select('mySelect', 'Label', ['1' => 'One', '2' => 'Two', '3' => 'Three'], '2');</code></pre>
    
    <h4>select options_group</h4>
    <div class="bg-light p-2"> 
        <div class="form-group
        col-xl-6">
            <?php Form::select('mySelect', 'Label', 
            ['Group 1' => ['1' => 'One', '2' => 'Two', '3' => 'Three'], 
            'Group 2' => ['4' => 'Four', '5' => 'Five', '6' => 'Six']], '2'); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">Form::select('mySelect', 'Label', [
        'Group 1' => ['1' => 'One', '2' => 'Two', '3' => 'Three'], 
        'Group 2' => ['4' => 'Four', '5' => 'Five', '6' => 'Six']
], '2');</code></pre>

<br>
<h2>Action List</h2>
<h5><code>Form::actionList($name, $label, $list_options, $selected = '', $options = array(), $input_options = array(), $return = false)</code></h5>
<p>$field = Hook:set('form_action_list', $field, $name, $label, $list_options, $selected, $options, $input_options)</p>
<p>Creates a clickable action list with hidden input for value storage. Perfect for filters, tabs, or any selection interface where you need clickable elements instead of a traditional select dropdown.</p>

<div class="bg-light p-2"> 
    <div class="form-group col-xl-6">
        <?php
        $filters = [ 'all' => 'All', 'active' => 'Active',  'suspended' => 'Suspended', 'trash' => 'Trash'];
        Form::actionList('filter', 'Filter by status', $filters, 'trash', [
            'class' => 'filter-actions mb-3'
        ]);
        ?>
    </div>
</div>
<pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">$filters = ['all' => 'All', 'active' => 'Active', 'suspended' => 'Suspended', 'trash' => 'Trash'];
Form::actionList('filter', 'Filter by status', $filters, 'trash', [
    'class' => 'filter-actions mb-3'
]);</code></pre>

<h4>With JavaScript callback</h4>
<div class="bg-light p-2"> 
    <div class="form-group col-xl-6">
        <?php
        $categories = ['tech' => 'Technology', 'design' => 'Design', 'business' => 'Business'];
        Form::actionList('category', 'Select Category', $categories, 'tech', [
            'class' => 'btn-group',
            'item-class' => 'btn btn-outline-primary',
            'active-class' => 'active'
        ], [
            'onchange' => 'alert("Selected:", this.value)',
            'data-callback' => 'handleCategoryChange'
        ]);
        ?>
    </div>
</div>
<pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">$categories = ['tech' => 'Technology', 'design' => 'Design', 'business' => 'Business'];
Form::actionList('category', 'Select Category', $categories, 'tech', [
    'class' => 'btn-group',
    'item-class' => 'btn btn-outline-primary', 
    'active-class' => 'active'
], [
    'onchange' => 'alert("Selected:", this.value)',
    'data-callback' => 'handleCategoryChange'
]);</code></pre>

<h4>JavaScript API</h4>
<p>The action_list provides a JavaScript API for programmatic control:</p>
<pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-javascript">// Set value programmatically
Formaction_list.setValue('formfilter', 'active');

// Get current value
var currentValue = Formaction_list.getValue('formfilter');

// Add change listener
Formaction_list.onChange('formfilter', function(e) {
    console.log('New value:', e.target.value);
});

// Listen to custom event
document.addEventListener('action_listChange', function(e) {
    console.log('Changed from', e.detail.oldValue, 'to', e.detail.value);
});</code></pre>

<h4>Options</h4>
<p>Available options for customizing the action list:</p>

<h5>Container options ($options):</h5>
<ul>
    <li><code>class</code> - CSS class for the container</li>
    <li><code>item-class</code> - CSS class for each action item (default: 'link-action')</li>
    <li><code>active-class</code> - CSS class for the active item (default: 'active-action-list')</li>
    <li><code>container-tag</code> - HTML tag for container (default: 'div')</li>
    <li><code>item-tag</code> - HTML tag for items (default: 'span')</li>
    <li><code>onchange</code> - JavaScript to execute when selection changes (for backward compatibility)</li>
</ul>

<h5>Input options ($input_options):</h5>
<ul>
    <li><code>class</code> - CSS class for the hidden input</li>
    <li><code>onchange</code> - JavaScript to execute when value changes</li>
    <li><code>data-*</code> - Data attributes for the hidden input</li>
    <li><code>required</code> - Make the field required</li>
    <li><code>invalid-feedback</code> - Error message for validation</li>
    <li>Other standard HTML5 input attributes</li>
</ul>
    