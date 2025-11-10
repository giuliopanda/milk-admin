<?php
namespace Modules\Docs\Pages;
/**
 * @title Messages
 * @guide framework
 * @order 
 * @tags messages, errors, success, validation, alerts, feedback, notifications 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>MessagesHandler Class</h1>

    <p>The <strong>MessagesHandler</strong> class manages error and success messages within the application. It provides methods to add, retrieve, and reset messages, facilitating user notification management.</p>

    <p>Error messages within app classes and logic-handling classes are managed solely through the last_error variable. Modules or services, needing more detailed feedback for the end user, can use this class to facilitate message management. Messages can also be passed during redirects.</p>

    <h2>Functions</h2>

    <h4 class="mt-4">addError(string $message, mixed $field = '')</h4>
    <p>Adds an error message to the error message list. If a field is specified, the message will be associated with that field.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::addError('Error message', 'field_name');</code></pre>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::addError('Error message', ['field1', 'field2']);</code></pre>

    <h4 class="mt-4">addSuccess(string $message)</h4>
    <p>Adds a success message to the success message list.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::addSuccess('Success message');</code></pre>

    <h4 class="mt-4">addFieldError(string $field)</h4>
    <p>Adds a field to the list of invalid fields. <br> Invalid fields are automatically handled by the form class to add the is-invalid class to invalid fields.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::addFieldError('field_name');</code></pre>

    <h4 class="mt-4">hasErrors()</h4>
    <p>Returns true if there are error messages or invalid fields, otherwise false.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (MessagesHandler::hasErrors()) { echo 'the form has errors'; }</code></pre>

    <h4 class="mt-4">getInvalidClass(string $field_name)</h4>
    <p>Returns the CSS class to apply to a field if it has been declared invalid.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$invalidClass = MessagesHandler::getInvalidClass('field_name');</code></pre>

    <h4 class="mt-4">echo_messages()</h4>
    <p>Prints all error and success messages as Bootstrap alerts.</p>

    <h4 class="mt-4">getErrorAlert()</h4>
    <p>Returns an HTML block containing all error messages, formatted as a Bootstrap alert.</p>

    <h4 class="mt-4">getErrors()</h4>
    <p>Gets an array of all currently stored error messages.</p>

    <h4 class="mt-4">errorsToString($br = false)</h4>
    <p>Converts all error messages into a single string, separated by a line break or an HTML &lt;br&gt; tag.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$errorString = MessagesHandler::errorsToString(true);</code></pre>

    <h4 class="mt-4">getSuccessAlert()</h4>
    <p>Returns an HTML block containing all success messages, formatted as a Bootstrap alert.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$successAlert = MessagesHandler::getSuccessAlert();</code></pre>

    <h4 class="mt-4">getSuccessMessages()</h4>
    <p>Gets an array of all currently stored success messages.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$successMessages = MessagesHandler::getSuccessMessages();</code></pre>

    <h4 class="mt-4">successToString($br = false)</h4>
    <p>Converts all success messages into a single string, separated by a line break or an HTML &lt;br&gt; tag.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$successString = MessagesHandler::successToString();</code></pre>

    <h4 class="mt-4">reset()</h4>
    <p>Resets all stored error and success messages, emptying the corresponding arrays.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::reset();</code></pre>

    <h4 class="mt-4">Messages in Redirects</h4>
    <p>You can use <code>Route::redirectSuccess($url, $message = '')</code> and <code>Route::redirectError($url, $message = '')</code> to perform a redirect with a success or error message.</p>
</div>