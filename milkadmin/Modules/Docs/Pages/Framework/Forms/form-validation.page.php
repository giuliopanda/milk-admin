<?php
namespace Modules\Docs\Pages;
use App\{Get, Form, MessagesHandler};
/**
 * @title Form Validation
 * @guide framework
 * @order 20
 * @tags form-validation, validation, Bootstrap, Form, textarea, checkboxes, radios, select, UploadFiles, required, invalid-feedback, was-validated, checkValidity, preventDefault, stopPropagation, MessagesHandler, add_error, add_success, get_error_alert, add_field_error, PHP-validation, JavaScript-validation, client-side, server-side, form-submission, error-messages, feedback, alerts
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

    <div class="alert alert-primary">
        <h5 class="alert-heading">Quick Form Creation with FormBuilder</h5>
        <p class="mb-0">
            This is a manual, artisanal system for form validation. If you need to create forms quickly from your Models,
            we recommend using the <strong>FormBuilder</strong> which can generate complete forms with validation in minutes:
            <br><br>
            <a href="?page=docs&guide=developer&action=Developer/Form/builders-form-validation" class="alert-link">
                <strong>→ Getting Started - Forms with FormBuilder</strong>
            </a>
        </p>
    </div>

    <h2>Validation</h2>

    <p>The system follows the Bootstrap standard for form validation.</p>

    <form class="was-validated">

    <div class="mb-3">
        <?php Form::textarea('myValidTextarea', 'Label', '', 4, ['required'=>true, 'invalid-feedback'=>'Please enter a message in the textarea.']); ?>
    </div>

    <?php Form::checkboxes('myValidCheckboxes', ['1' => 'Check this checkbox'], '', false, ['invalid-feedback'=>'Example invalid feedback text', 'form-group-class'=>'mb-3'],['required'=>true]); ?>

    <?php Form::radios('myValidRadios', ['1' => 'Toggle this radio', '2' => 'Or toggle this other radio'],  '', false, ['label'=>'Select fields', 'invalid-feedback'=>'Please select a value', 'form-group-class'=>'mb-3'],['required'=>true]); ?>

    <div class="mb-3">
        <?php Form::select('mySelect', 'Label', [''=>'Open this select menu', '1' => 'One', '2' => 'Two', '3' => 'Three'], '', ['required'=>true, 'floating'=>true, 'invalid-feedback'=>'Please select a value']); ?>
    </div>

    <div class="mb-3">
    <?php echo Get::themePlugin('UploadFiles',['name'=>'filet', 'label'=>'File', 'value'=>'', 'options'=>['multiple'=>true, 'required'=>true, 'invalid-feedback'=>'Please upload a file'], 'upload_name' => 'my_upload3'] ); ?>
    </div>

    <div class="mb-3">
        <button class="btn btn-primary" type="submit" disabled>Submit form</button>
    </div>
    </form>

    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;form class=&quot;was-validated&quot;&gt;
        &lt;div class=&quot;mb-3&quot;&gt;
        &lt;?php Form::textarea(&#39;myValidTextarea&#39;, &#39;Label&#39;, &#39;&#39;, 4, [&#39;required&#39;=&gt;true, &#39;invalid-feedback&#39;=&gt;&#39;Please enter a message in the textarea.&#39;]); ?&gt;
        &lt;/div&gt;
        &lt;?php 
        Form::checkboxes(&#39;myValidCheckboxes&#39;, [&#39;1&#39; =&gt; &#39;Check this checkbox&#39;], &#39;&#39;, false, [&#39;invalid-feedback&#39;=&gt;&#39;Example invalid feedback text&#39;, &#39;form-group-class&#39;=&gt;&#39;mb-3&#39;],[&#39;required&#39;=&gt;true]);
        Form::radios(&#39;myValidRadios&#39;, [&#39;1&#39; =&gt; &#39;Toggle this radio&#39;, &#39;2&#39; =&gt; &#39;Or toggle this other radio&#39;],  &#39;&#39;, false, [&#39;label&#39;=&gt;&#39;Select fields&#39;, &#39;invalid-feedback&#39;=&gt;&#39;Please select a value&#39;, &#39;form-group-class&#39;=&gt;&#39;mb-3&#39;],[&#39;required&#39;=&gt;true]); 
        ?&gt;
        &lt;div class=&quot;mb-3&quot;&gt;
        &lt;?php Form::select(&#39;mySelect&#39;, &#39;Label&#39;, [&#39;&#39;=&gt;&#39;Open this select menu&#39;, &#39;1&#39; =&gt; &#39;One&#39;, &#39;2&#39; =&gt; &#39;Two&#39;, &#39;3&#39; =&gt; &#39;Three&#39;], &#39;&#39;, [&#39;required&#39;=&gt;true, &#39;floating&#39;=&gt;true, &#39;invalid-feedback&#39;=&gt;Please select a value&#39;]); ?&gt;
        &lt;/div&gt;
        &lt;div class=&quot;mb-3&quot;&gt;
        &lt;?php Form::input(&#39;file&#39;, &#39;file&#39;, &#39;&#39;, &#39;&#39;, [&#39;required&#39;=&gt;true, &#39;invalid-feedback&#39;=&gt;&#39;Example invalid form file feedback&#39;]); ?&gt;
        &lt;/div&gt;
        &lt;div class=&quot;mb-3&quot;&gt;
        &lt;button class=&quot;btn btn-primary&quot; type=&quot;submit&quot; disabled&gt;Submit form&lt;/button&gt;
        &lt;/div&gt;
    &lt;/form&gt;
    </code></pre>

    <br><br>
    <h3>Custom JavaScript Validation</h3>

    <p>The framework automatically handles form validation. You can add custom validation logic by listening to specific JavaScript events.</p>

    <h4>Available Events</h4>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Event</th>
                <th>When</th>
                <th>Usage</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>fieldValidation</code></td>
                <td>On submit, and in real-time after first submit attempt</td>
                <td>Add custom validation for specific fields</td>
            </tr>
            <tr>
                <td><code>customValidation</code></td>
                <td>On submit, before checking validity</td>
                <td>Form-level validation involving multiple fields</td>
            </tr>
            <tr>
                <td><code>beforeFormSubmit</code></td>
                <td>After validation passes, before actual submit</td>
                <td>Perform actions before submission (e.g., show loading)</td>
            </tr>
        </tbody>
    </table>

    <h4>Example: Field Validation with Regex</h4>
    <p>Validate a phone number field using a regular expression:</p>

    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-js">document.addEventListener('DOMContentLoaded', function() {
    const phoneField = document.querySelector('[name="phone"]')

    if (phoneField) {
        phoneField.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field
            const phoneRegex = /^[0-9]{10}$/

            if (!phoneRegex.test(field.value)) {
                field.setCustomValidity('Phone must be 10 digits')
            } else {
                field.setCustomValidity('')
            }
        })
    }
})</code></pre>

    <h4>Example: Form-Level Validation</h4>
    <p>Validate multiple fields together:</p>

    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-js">document.addEventListener('DOMContentLoaded', function() {
    const myForm = document.querySelector('#myForm')

    if (myForm) {
        myForm.addEventListener('customValidation', function(e) {
            const form = e.detail.form
            const startDate = form.querySelector('[name="start_date"]').value
            const endDate = form.querySelector('[name="end_date"]').value

            if (new Date(startDate) &gt; new Date(endDate)) {
                alert('End date must be after start date')
                e.preventDefault()
                return false
            }
        })
    }
})</code></pre>

    <br><br>
    <h4>Invalid form via PHP</h4>
    <p>If the form is submitted, but some fields are wrong and an alert with error messages must be shown, you can use the <code>MessagesHandler</code> class.</p>

    <?php
    MessagesHandler::addError('This is a test name error message', 'test-name');
    echo MessagesHandler::getErrorAlert();
    ?>
    <div class="bg-light p-2">
            <div class="form-group col-xl-6">
            <?php Form::input('text', 'test-name', 'Name', '', ['id'=>'my-custom-test-name-id', 'invalid-feedback'=>'Please enter a test name.']); ?>
        </div>
    </div>

    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;?php
        MessagesHandler::addError('This is a test name error message', 'test-name');
        echo MessagesHandler::getErrorAlert(); 
    ?&gt;

    &lt;div class=&quot;bg-light p-2&quot;&gt;
        &lt;div class=&quot;form-group col-xl-6&quot;&gt;
            &lt;?php Form::input('text', 'test-name', 'Name', '', ['id'=&gt;'my-custom-test-name-id', 'invalid-feedback'=&gt;'Please enter a test name.']); ?&gt;
        &lt;/div&gt;
    &lt;/div&gt;</code></pre>

    <p>From PHP you may want to specify which fields were not validated during form submission through the <code>MessagesHandler</code> class.</p>
    <p>The functions are:</p>
    <h6 class="fw-bold"> MessagesHandler::addError($msg, $field);</h6>
    <p>Adds an error message for a field</p>
    <h6 class="fw-bold"> MessagesHandler::addFieldError($field);</h6>
    <p>Adds a field as invalid</p>
    <h6 class="fw-bold"> MessagesHandler::getErrorAlert($field_name);</h6>
    <p>Returns an alert with error messages</p>

    <br><br>
   
    <h3>Related Documentation</h3>
     <ul>
        <li><a href="?page=docs&action=Developer/Form/builders-form-fields"><strong>Field Management</strong></a>: Adding, removing, modifying, and organizing form fields</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-conditional-visibility"><strong>Conditional Field Visibility</strong></a>: Show/hide fields based on other field values</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-validation"><strong>Form Validation</strong></a>: Custom validation and error handling</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-containers"><strong>Organizing Fields in columns with Containers</strong></a>: Grouping fields into containers for better organization</li>
        
    </ul>

</div>