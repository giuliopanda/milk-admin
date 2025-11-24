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
    <h1>Custom Form Validation</h1>

    <p>This guide shows how to implement <strong>custom client-side and server-side validation</strong> for specific business logic that cannot be handled by standard Model validation rules.</p>

    <div class="alert alert-warning">
        <strong>Important:</strong> This documentation covers <strong>custom validation</strong> for complex scenarios. For standard field validation (required, min/max length, data types, etc.), use <strong>Model validation rules</strong> instead.
        <ul class="mb-0 mt-2">
            <li><a href="?page=docs&action=Developer/Model/model-rules" class="alert-link">Model Validation Rules (TODO)</a></li>
        </ul>
    </div>

    <div class="alert alert-info">
        <strong>For module and form basics, see:</strong>
        <ul class="mb-0 mt-2">
            <li><a href="?page=docs&action=Developer/Form/builders-form-fields" class="alert-link">Field Management</a></li>
            <li><a href="?page=docs&action=Developer/Form/builders-form-containers" class="alert-link">Organizing Fields with Containers</a></li>
            <li><a href="?page=docs&action=Developer/Module/module-basics" class="alert-link">Module Basics</a></li>
        </ul>
    </div>

    <h2>When to Use Custom Validation</h2>

    <p>Use custom validation for:</p>
    <ul>
        <li>Comparing multiple field values (e.g., start date vs end date)</li>
        <li>Complex business rules (e.g., conditional requirements)</li>
        <li>Custom regex patterns or domain-specific validations</li>
        <li>Cross-field dependencies</li>
    </ul>

    <h2>Client-Side Validation Events</h2>

    <p>The framework provides JavaScript events for custom validation:</p>

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
                <td>On submit, then on each field change after first submit</td>
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

    <h3>Validation Flow</h3>
    <ol>
        <li><strong>First validation:</strong> Runs when user submits the form</li>
        <li><strong>Re-validation:</strong> After first submit, fields are re-validated each time the user modifies them</li>
        <li><strong>Error clearing:</strong> When user modifies an invalid field, the error message is cleared automatically</li>
    </ol>

    <h2>Where to Place Validation Code</h2>

    <h3>For Static Forms (Loaded on Page Load)</h3>
    <p>Use <code>DOMContentLoaded</code> for forms that are present when the page loads:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">document.addEventListener('DOMContentLoaded', function() {
    // Your validation code here
})</code></pre>

    <h3>For Dynamic Forms (Loaded via AJAX/Modal)</h3>
    <p>Use <code>updateContainer</code> for forms loaded dynamically after the page loads:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">document.addEventListener('updateContainer', function(event) {
    // Your validation code here
})</code></pre>

    <div class="alert alert-warning">
        <strong>Important:</strong> Dynamic forms need <code>updateContainer</code> because they are loaded after the page's <code>DOMContentLoaded</code> event has already fired.
    </div>

    <h2>Client-Side Validation Example: Date Range</h2>

    <p>This example validates that an end datetime is greater than a start datetime. This validation is used in the Events module.</p>

    <h3>JavaScript Code</h3>
    <p>Place this in your module's JavaScript file (e.g., <code>milkadmin/Modules/Events/assets/events.js</code>):</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">// For dynamic forms loaded via AJAX/modal
document.addEventListener('updateContainer', function(event) {
    const end_datetime = document.querySelector('[name="data[end_datetime]"]')
    const start_datetime = document.querySelector('[name="data[start_datetime]"]')

    if (end_datetime && start_datetime) {
        end_datetime.addEventListener('fieldValidation', function(e) {
            if (end_datetime.value < start_datetime.value) {
                end_datetime.setCustomValidity('End datetime must be greater than start datetime')
            } else {
                end_datetime.setCustomValidity('')
            }
        });
    }
})</code></pre>

    <h3>How It Works</h3>
    <ol>
        <li>The validation listens to the <code>fieldValidation</code> event on the <code>end_datetime</code> field</li>
        <li>When triggered, it compares the end datetime with the start datetime</li>
        <li>If invalid, it sets a custom error message using <code>setCustomValidity()</code></li>
        <li>If valid, it clears the error by calling <code>setCustomValidity('')</code></li>
        <li>Bootstrap automatically displays the error message below the field with red styling</li>
    </ol>

    <div class="alert alert-success">
        <strong>✓ Result:</strong> The form validates that end_datetime is greater than start_datetime, showing error feedback in Bootstrap style.
    </div>

    <h2>Server-Side Validation</h2>

    <p>Server-side validation is essential for data integrity, as client-side validation can be bypassed. Use <code>MessagesHandler</code> to display validation errors.</p>

    <h3>Backend Validation Example: Date Range</h3>
    <p>This example from the Events module (<code>milkadmin/Modules/Events/EventsController.php</code>) validates that the start date is not later than the end date:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function saveEvent($form_builder, $request) {
    $this->model->fill($request);

    // --- Date range validation ---
    if (!empty($request['start_datetime']) && !empty($request['end_datetime'])) {

        $start = strtotime($request['start_datetime']);
        $end   = strtotime($request['end_datetime']);

        if ($start > $end) {

            // Add error message for specific fields
            \App\MessagesHandler::addError(
                'The start date cannot be later than the end date',
                ['start_datetime', 'end_datetime']
            );

            // Return error response with updated form
            return $this->jsonModalError(
                'There are validation errors in the form',
                $form_builder
            );
        }
    }

    // --- Model validation ---
    if (!$this->model->validate()) {
        return $this->jsonModalError('Error saving event', $form_builder);
    }

    // --- Saving ---
    if (!$this->model->save()) {
        return $this->jsonModalError(
            'Error saving event: ' . $this->model->getLastError(),
            $form_builder
        );
    }

    // --- Success response ---
    Response::json([
        'success'  => true,
        'message'  => 'Event saved successfully!',
        'modal'    => ['action' => 'hide'],
        'calendar' => ['id' => 'calendar_events', 'action' => 'reload']
    ]);
}</code></pre>

    <h3>Helper Method for Error Responses</h3>
    <p>The <code>jsonModalError</code> method returns the form with validation errors highlighted:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">private function jsonModalError($message, $form_builder)
{
    Response::json([
        'success' => false,
        'message' => $message,
        'modal'   => [
            'title' => 'Edit Event',
            'body'  => $form_builder->getForm(),  // Re-render form with errors
            'size'  => 'lg'
        ]
    ]);
}</code></pre>

    <h3>How Backend Validation Works</h3>
    <ol>
        <li><strong>Validation fails:</strong> <code>MessagesHandler::addError()</code> marks fields as invalid</li>
        <li><strong>Form reloads:</strong> The form is returned with invalid field styling (red border)</li>
        <li><strong>User modifies field:</strong> The invalid state is cleared automatically when the user types</li>
        <li><strong>No re-validation:</strong> Client-side validation doesn't run again until the user submits the form</li>
    </ol>

    <h3>MessagesHandler Methods</h3>
    <ul>
        <li><code>MessagesHandler::addError($message, $field)</code> - Adds an error message for one or more fields (accepts string or array)</li>
        <li><code>MessagesHandler::addFieldError($field)</code> - Marks a field as invalid without a message</li>
        <li><code>MessagesHandler::getErrorAlert()</code> - Returns an alert div with all error messages</li>
    </ul>

    <h2>Best Practices</h2>

    <h3>1. Always Clear Custom Validity</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">// Always include an else statement to clear the error
if (condition_fails) {
    field.setCustomValidity('Error message')
} else {
    field.setCustomValidity('')  // Critical: must clear when valid!
}
</code></pre>

    <h3>2. Use Descriptive Error Messages</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">// Good - Specific and actionable
field.setCustomValidity('End date must be greater than start date')

// Bad - Too vague
field.setCustomValidity('Invalid date')
</code></pre>

    <h3>3. Validate on Both Client and Server</h3>
    <p>Always implement critical validations on both sides:</p>
    <ul>
        <li><strong>Client-side:</strong> Provides immediate feedback to users</li>
        <li><strong>Server-side:</strong> Ensures data integrity (client-side can be bypassed)</li>
    </ul>

    <h3>4. Link JavaScript File in Module</h3>
    <p>Don't forget to link your validation JavaScript file in your module configuration:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    $rule->page('events')
         ->title('Events')
         ->setJs('Modules/Events/assets/events.js')  // Link validation file
         ->access('registered');
}
</code></pre>


    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Model/model-rules"><strong>Model Validation Rules (TODO)</strong></a>: Standard field validation using Model rules</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-fields"><strong>Field Management</strong></a>: Adding, removing, and configuring form fields</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-conditional-visibility"><strong>Conditional Field Visibility</strong></a>: Show/hide fields based on other field values</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-containers"><strong>Organizing Fields with Containers</strong></a>: Grouping fields into columns and sections</li>
    </ul>

</div>
