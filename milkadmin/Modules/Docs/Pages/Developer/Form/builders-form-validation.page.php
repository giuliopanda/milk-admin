<?php
namespace Modules\Docs\Pages;
use App\{Get, Form, MessagesHandler};
/**
 * @title Form Validation with FormBuilder
 * @guide developer
 * @order 46
 * @tags FormBuilder, form-validation, validation, JavaScript-validation, fieldValidation, customValidation, beforeFormSubmit, custom-validation, password-confirm, MessagesHandler, PHP-validation, client-side, server-side
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Form Validation with FormBuilder</h1>

    <p>This guide shows how to implement custom validation in forms created with <strong>FormBuilder</strong>. The validation system integrates seamlessly with Bootstrap's validation styling and provides JavaScript hooks for custom validation logic.</p>

    <div class="alert alert-info">
        <strong>Note:</strong> This documentation focuses on validation for forms built with <code>FormBuilder</code>.
        If you're using Bootstrap forms directly with the <code>Form</code> class, see
        <a href="?page=docs&action=Framework/Forms/form-validation" class="alert-link">Form Validation (Bootstrap Forms)</a>.
    </div>

    <h2>Validation Overview</h2>

    <p>FormBuilder forms automatically support:</p>
    <ul>
        <li><strong>Server-side validation</strong>: Through Model validation rules</li>
        <li><strong>Client-side validation</strong>: Using Bootstrap's built-in HTML5 validation</li>
        <li><strong>Custom JavaScript validation</strong>: Via validation events</li>
        <li><strong>Real-time validation</strong>: Validates as user types (after first submit attempt)</li>
    </ul>

    <h2>Custom JavaScript Validation</h2>

    <p>The framework provides three JavaScript events for custom validation:</p>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Event</th>
                <th>When Triggered</th>
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

    <h2>Complete Example: Password Confirmation Validation</h2>

    <p>This example demonstrates a complete working module with custom validation to ensure two password fields match.</p>

    <h3>Step 1: Create the Module PHP File</h3>
    <p>Create <code>milkadmin/Modules/TestFormValidationModule.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\Response;

class TestFormValidationModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('testFormValidation')
             ->title('Test Form Validation')
             ->menu('Test Form Validation')
             ->setJs('Modules/TestFormValidation.js')  // Link the JavaScript file
             ->access('registered');
    }

    #[RequestAction('home')]
    public function home() {
        // Create form using FormBuilder
        $form = \Builders\FormBuilder::create($this->model, $this->page)
            ->addStandardActions()
            ->render();

        Response::render(['form' => $form], [
            'title' => $this->title,
            'form' => $form
        ]);
    }
}

class TestFormValidationModel extends AbstractModel {
    protected function configure($rule): void
    {
        $rule
        ->table('test_form_validation')
        ->id()
        ->string('name', 100)
        ->string('email', 255)
        ->string('password', 100, false)
        ->string('password_confirm', 100, false);  // Confirmation field
    }
}</code></pre>

    <h3>Step 2: Create the JavaScript Validation File</h3>
    <p>Create <code>milkadmin/Modules/TestFormValidation.js</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const passwordConfirmField = document.querySelector('[name="password_confirm"]')

    if (passwordConfirmField) {
        // Listen to the fieldValidation event
        passwordConfirmField.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field
            const form = e.detail.form
            const password = form.querySelector('[name="password"]').value

            // Check if passwords match
            if (field.value !== password || field.value === '') {
                field.setCustomValidity('Passwords do not match or are empty')
            } else {
                field.setCustomValidity('')  // Clear the error
            }
        })
    }
})</code></pre>

    <h3>How It Works</h3>
    <ol>
        <li>The <strong>Module</strong> defines the page, menu, and links the JavaScript file with <code>setJs()</code></li>
        <li>The <strong>Model</strong> defines the database table and fields</li>
        <li>The <strong>JavaScript file</strong> listens to the <code>fieldValidation</code> event on the password_confirm field</li>
        <li>The validation runs:
            <ul>
                <li>On form submit (all fields are validated)</li>
                <li>In real-time after first submit attempt (when user types)</li>
            </ul>
        </li>
        <li>Bootstrap automatically displays the error message in red below the field</li>
    </ol>

    <h3>Result</h3>
    <div class="alert alert-success">
        <strong>✓ Result:</strong> The form will validate that password and password_confirm match, showing error feedback in Bootstrap style. The error appears below the field with red styling.
    </div>

    <h2>More Validation Examples</h2>

    <h3>Example: Phone Number Validation with Regex</h3>
    <p>Validate that a phone number field contains exactly 10 digits:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">document.addEventListener('DOMContentLoaded', function() {
    const phoneField = document.querySelector('[name="phone"]')

    if (phoneField) {
        phoneField.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field
            const phoneRegex = /^[0-9]{10}$/

            if (!phoneRegex.test(field.value)) {
                field.setCustomValidity('Phone must be exactly 10 digits')
            } else {
                field.setCustomValidity('')
            }
        })
    }
})</code></pre>

    <h3>Example: Email Confirmation</h3>
    <p>Ensure two email fields match:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">document.addEventListener('DOMContentLoaded', function() {
    const emailConfirmField = document.querySelector('[name="email_confirm"]')

    if (emailConfirmField) {
        emailConfirmField.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field
            const form = e.detail.form
            const email = form.querySelector('[name="email"]').value

            if (field.value !== email) {
                field.setCustomValidity('Email addresses must match')
            } else {
                field.setCustomValidity('')
            }
        })
    }
})</code></pre>

    <h3>Example: Date Range Validation (Form-Level)</h3>
    <p>Validate that end date is after start date using form-level validation:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">document.addEventListener('DOMContentLoaded', function() {
    const myForm = document.querySelector('#formmypage')  // Replace 'mypage' with your page name

    if (myForm) {
        myForm.addEventListener('customValidation', function(e) {
            const form = e.detail.form
            const startDate = form.querySelector('[name="start_date"]').value
            const endDate = form.querySelector('[name="end_date"]').value

            if (new Date(startDate) > new Date(endDate)) {
                alert('End date must be after start date')
                e.preventDefault()
                return false
            }
        })
    }
})</code></pre>

    <h3>Example: Minimum Age Validation</h3>
    <p>Validate that user is at least 18 years old:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">document.addEventListener('DOMContentLoaded', function() {
    const birthdateField = document.querySelector('[name="birthdate"]')

    if (birthdateField) {
        birthdateField.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field
            const birthdate = new Date(field.value)
            const today = new Date()
            const age = Math.floor((today - birthdate) / (365.25 * 24 * 60 * 60 * 1000))

            if (age < 18) {
                field.setCustomValidity('You must be at least 18 years old')
            } else {
                field.setCustomValidity('')
            }
        })
    }
})</code></pre>

    <h2>Server-Side Validation with MessagesHandler</h2>

    <p>If the form is submitted but server-side validation fails, you can use the <code>MessagesHandler</code> class to display errors.</p>

    <h3>Available Methods</h3>
    <ul>
        <li><code>MessagesHandler::addError($msg, $field)</code> - Adds an error message for a specific field</li>
        <li><code>MessagesHandler::addFieldError($field)</code> - Marks a field as invalid</li>
        <li><code>MessagesHandler::getErrorAlert()</code> - Returns an alert div with all error messages</li>
    </ul>

    <h3>Example: Custom Save Action with Validation</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\{Response, MessagesHandler};

class UserModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('users')
             ->title('User Management')
             ->menu('Users')
             ->access('registered');
    }

    #[RequestAction('edit')]
    public function edit() {
        $id = _absint($_REQUEST['id'] ?? 0);
        $user = $this->model->getByIdForEdit($id);

        $form = \Builders\FormBuilder::create($this->model, $this->page)
            ->addFieldsFromObject($user, 'edit')
            ->setActions([
                'save' => [
                    'label' => 'Save User',
                    'class' => 'btn btn-primary',
                    'action' => [$this, 'saveWithValidation']
                ],
                'cancel' => [
                    'label' => 'Cancel',
                    'type' => 'link',
                    'class' => 'btn btn-secondary ms-2',
                    'link' => '?page=' . $this->page
                ]
            ])
            ->render();

        // Display validation errors if any
        echo MessagesHandler::getErrorAlert();

        Response::render(['form' => $form], [
            'title' => $this->title,
            'form' => $form
        ]);
    }

    // Custom save callback with validation
    public function saveWithValidation($form_builder, $request) {
        // Custom business logic validation
        if (isset($request['username'])) {
            $username = $request['username'];

            // Check if username is too short
            if (strlen($username) < 3) {
                MessagesHandler::addError('Username must be at least 3 characters', 'username');
                return ['success' => false, 'message' => 'Validation failed'];
            }

            // Check if username already exists
            if ($this->model->usernameExists($username, $request['id'] ?? 0)) {
                MessagesHandler::addError('This username is already taken', 'username');
                return ['success' => false, 'message' => 'Username already exists'];
            }
        }

        // If validation passes, save the data
        return $form_builder->save($request, '?page=' . $this->page);
    }
}
</code></pre>

    <h2>Complete Module Example with Multiple Validations</h2>

    <h3>Module with Registration Form</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\Response;

class RegistrationModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('registration')
             ->title('User Registration')
             ->menu('Registration')
             ->setJs('Modules/Registration.js')
             ->access('public');
    }

    #[RequestAction('home')]
    public function home() {
        $form = \Builders\FormBuilder::create($this->model, $this->page)
            ->setActions([
                'register' => [
                    'label' => 'Create Account',
                    'class' => 'btn btn-primary',
                    'action' => [$this, 'registerCallback']
                ]
            ])
            ->render();

        Response::render(['form' => $form], [
            'title' => 'Create Your Account',
            'form' => $form
        ]);
    }

    public function registerCallback($form_builder, $request) {
        // Custom validation will be done in JavaScript
        // Here we just save the user
        $request['password'] = password_hash($request['password'], PASSWORD_DEFAULT);
        unset($request['password_confirm']);  // Don't save confirmation

        return $form_builder->save($request, '?page=auth&action=login&registered=1');
    }
}

class RegistrationModel extends AbstractModel {
    protected function configure($rule): void
    {
        $rule
        ->table('users')
        ->id()
        ->string('username', 50)
        ->string('email', 255)
        ->string('phone', 20)
        ->date('birthdate')
        ->string('password', 255, false)
        ->string('password_confirm', 255, false)
        ->string('email_confirm', 255, false);
    }
}
</code></pre>

    <h3>JavaScript File: Modules/Registration.js</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">document.addEventListener('DOMContentLoaded', function() {

    // 1. Password confirmation validation
    const passwordConfirmField = document.querySelector('[name="password_confirm"]')
    if (passwordConfirmField) {
        passwordConfirmField.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field
            const form = e.detail.form
            const password = form.querySelector('[name="password"]').value

            if (field.value !== password || field.value === '') {
                field.setCustomValidity('Passwords do not match or are empty')
            } else {
                field.setCustomValidity('')
            }
        })
    }

    // 2. Email confirmation validation
    const emailConfirmField = document.querySelector('[name="email_confirm"]')
    if (emailConfirmField) {
        emailConfirmField.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field
            const form = e.detail.form
            const email = form.querySelector('[name="email"]').value

            if (field.value !== email) {
                field.setCustomValidity('Email addresses must match')
            } else {
                field.setCustomValidity('')
            }
        })
    }

    // 3. Phone validation (10 digits)
    const phoneField = document.querySelector('[name="phone"]')
    if (phoneField) {
        phoneField.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field
            const phoneRegex = /^[0-9]{10}$/

            if (!phoneRegex.test(field.value)) {
                field.setCustomValidity('Phone must be exactly 10 digits')
            } else {
                field.setCustomValidity('')
            }
        })
    }

    // 4. Age validation (minimum 18 years)
    const birthdateField = document.querySelector('[name="birthdate"]')
    if (birthdateField) {
        birthdateField.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field
            const birthdate = new Date(field.value)
            const today = new Date()
            const age = Math.floor((today - birthdate) / (365.25 * 24 * 60 * 60 * 1000))

            if (age < 18) {
                field.setCustomValidity('You must be at least 18 years old')
            } else {
                field.setCustomValidity('')
            }
        })
    }

    // 5. Before submit - show loading indicator
    const form = document.querySelector('#formregistration')
    if (form) {
        form.addEventListener('beforeFormSubmit', function(e) {
            const submitBtn = form.querySelector('[type="submit"]')
            submitBtn.disabled = true
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating account...'
        })
    }
})
</code></pre>

    <h2>Best Practices</h2>

    <h3>1. Use Descriptive Error Messages</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">// Good
field.setCustomValidity('Password must be at least 8 characters and contain a number')

// Not clear
field.setCustomValidity('Invalid password')
</code></pre>

    <h3>2. Always Clear Custom Validity</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">// Always include an else statement to clear the error
if (condition_fails) {
    field.setCustomValidity('Error message')
} else {
    field.setCustomValidity('')  // Important!
}
</code></pre>

    <h3>3. Validate Related Fields Together</h3>
    <p>For fields that depend on each other (like password/confirm), validate both when either changes:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">const passwordField = document.querySelector('[name="password"]')
const passwordConfirmField = document.querySelector('[name="password_confirm"]')

function validatePasswords() {
    const password = passwordField.value
    const confirm = passwordConfirmField.value

    if (confirm !== password) {
        passwordConfirmField.setCustomValidity('Passwords must match')
    } else {
        passwordConfirmField.setCustomValidity('')
    }
}

// Validate when either field changes
passwordField.addEventListener('fieldValidation', validatePasswords)
passwordConfirmField.addEventListener('fieldValidation', validatePasswords)
</code></pre>

    <h3>4. Link JavaScript File in Module Configuration</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    $rule->page('mypage')
         ->title('My Page')
         ->setJs('Modules/MyValidation.js')  // Link your JS file
         ->access('registered');
}
</code></pre>

    <h2>Troubleshooting</h2>

    <h3>Validation Not Working</h3>
    <ol>
        <li>Check that JavaScript file is linked in module with <code>setJs()</code></li>
        <li>Verify field names match exactly (case-sensitive)</li>
        <li>Check browser console for JavaScript errors</li>
        <li>Ensure <code>DOMContentLoaded</code> event listener is present</li>
    </ol>

    <h3>Error Messages Not Appearing</h3>
    <ol>
        <li>Verify Bootstrap CSS is loaded</li>
        <li>Check that form has <code>needs-validation</code> class</li>
        <li>Ensure <code>setCustomValidity()</code> is being called</li>
        <li>For server-side errors, verify <code>MessagesHandler::getErrorAlert()</code> is echoed</li>
    </ol>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Form/builders-form-fields"><strong>Field Management</strong></a>: Adding, removing, modifying, and organizing form fields</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-conditional-visibility"><strong>Conditional Field Visibility</strong></a>: Show/hide fields based on other field values</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-validation"><strong>Form Validation</strong></a>: Custom validation and error handling</li>
          <li><a href="?page=docs&action=Developer/Form/builders-form-containers"><strong>Organizing Fields in columns with Containers</strong></a>: Grouping fields into containers for better organization</li>
        
    </ul>

</div>
