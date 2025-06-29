<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Messages
 * @category Framework
 * @order 
 * @tags 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>MessagesHandler Class</h1>
    

    <p>The <strong>MessagesHandler</strong> class manages error and success messages within the application. It provides methods to add, retrieve, and reset messages, facilitating user notification management.</p>

    <p>Error messages within milk-core classes and logic-handling classes are managed solely through the last_error variable. Controllers or services, needing more detailed feedback for the end user, can use this class to facilitate message management. Messages can also be passed during redirects.</p>

    <h2>Functions</h2>

    <h4 class="mt-4">add_error(string $message, mixed $field = '')</h4>
    <p>Adds an error message to the error message list. If a field is specified, the message will be associated with that field.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::add_error('Error message', 'field_name');</code></pre>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::add_error('Error message', ['field1', 'field2']);</code></pre>

    <h4 class="mt-4">add_success(string $message)</h4>
    <p>Adds a success message to the success message list.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::add_success('Success message');</code></pre>

    <h4 class="mt-4">add_field_error(string $field)</h4>
    <p>Adds a field to the list of invalid fields. <br> Invalid fields are automatically handled by the form class to add the is-invalid class to invalid fields.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::add_field_error('field_name');</code></pre>

    <h4 class="mt-4">has_errors()</h4>
    <p>Returns true if there are error messages or invalid fields, otherwise false.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (MessagesHandler::has_errors()) { echo 'the form has errors'; }</code></pre>

    <h4 class="mt-4">get_invalid_class(string $field_name)</h4>
    <p>Returns the CSS class to apply to a field if it has been declared invalid.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$invalidClass = MessagesHandler::get_invalid_class('field_name');</code></pre>

    <h4 class="mt-4">echo_messages()</h4>
    <p>Prints all error and success messages as Bootstrap alerts.</p>

    <h4 class="mt-4">get_error_alert()</h4>
    <p>Returns an HTML block containing all error messages, formatted as a Bootstrap alert.</p>

    <h4 class="mt-4">get_errors()</h4>
    <p>Gets an array of all currently stored error messages.</p>

    <h4 class="mt-4">errors_to_string($br = false)</h4>
    <p>Converts all error messages into a single string, separated by a line break or an HTML &lt;br&gt; tag.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$errorString = MessagesHandler::errors_to_string(true);</code></pre>

    <h4 class="mt-4">get_success_alert()</h4>
    <p>Returns an HTML block containing all success messages, formatted as a Bootstrap alert.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$successAlert = MessagesHandler::get_success_alert();</code></pre>

    <h4 class="mt-4">get_success_messages()</h4>
    <p>Gets an array of all currently stored success messages.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$successMessages = MessagesHandler::get_success_messages();</code></pre>

    <h4 class="mt-4">success_to_string($br = false)</h4>
    <p>Converts all success messages into a single string, separated by a line break or an HTML &lt;br&gt; tag.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$successString = MessagesHandler::success_to_string();</code></pre>

    <h4 class="mt-4">reset()</h4>
    <p>Resets all stored error and success messages, emptying the corresponding arrays.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">MessagesHandler::reset();</code></pre>


    <h4 class="mt-4">Messages in Redirects</h4>
    <p>You can use <code>Route::redirect_success($url, $message = '')</code> and <code>Route::redirect_error($url, $message = '')</code> to perform a redirect with a success or error message.</p>
</div>