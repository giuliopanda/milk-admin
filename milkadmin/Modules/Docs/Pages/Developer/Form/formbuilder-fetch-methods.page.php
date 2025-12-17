<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title FormBuilder Fetch Methods Reference
 * @guide developer
 * @order 47
 * @tags fetch, formbuilder, methods, reference, api, offcanvas, modal, ajax
 */

 !defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>FormBuilder Fetch Methods Reference</h1>
    <p class="text-muted">Revision: 2025/12/11</p>
    <p class="lead">Complete reference for FormBuilder methods that enable fetch-based form handling without page reloads.</p>

    <div class="alert alert-info">
        <strong>Quick Links:</strong>
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/Advanced/fetch-modal-crud">Complete Tutorial - Fetch-Based Forms with Offcanvas</a></li>
            <li><a href="?page=docs&action=Framework/Theme/theme-json-actions">JSON Actions System - How Responses Work</a></li>
        </ul>
    </div>

    <hr>

    <h2>Quick Reference Table</h2>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Purpose</th>
                <th>Returns</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>activeFetch()</code></td>
                <td>Enables fetch mode for form submission</td>
                <td>self</td>
            </tr>
            <tr>
                <td><code>asOffcanvas()</code></td>
                <td>Sets response type to offcanvas panel</td>
                <td>self</td>
            </tr>
            <tr>
                <td><code>asModal()</code></td>
                <td>Sets response type to modal dialog</td>
                <td>self</td>
            </tr>
            <tr>
                <td><code>asDom($id)</code></td>
                <td>Sets response type to DOM element</td>
                <td>self</td>
            </tr>
            <tr>
                <td><code>setTitle($new, $edit)</code></td>
                <td>Sets dynamic titles for new/edit modes</td>
                <td>self</td>
            </tr>
            <tr>
                <td><code>dataListId($id)</code></td>
                <td>Enables automatic table reload on success</td>
                <td>self</td>
            </tr>
            <tr>
                <td><code>size($size)</code></td>
                <td>Sets modal/offcanvas size</td>
                <td>self</td>
            </tr>
            <tr>
                <td><code>getResponse()</code></td>
                <td>Generates complete JSON response</td>
                <td>array</td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2>Method Details</h2>

    <h3>activeFetch()</h3>
    <p>Converts form submission and action buttons into fetch calls. Without this method, the page will reload on submit.</p>
    <pre class="border p-2 bg-light"><code class="language-php">FormBuilder::create($model, $page)
    ->activeFetch()  // Enables fetch mode
    ->getForm();</code></pre>
    <p><strong>Effect:</strong></p>
    <ul>
        <li>Form submission becomes AJAX request</li>
        <li>Submit buttons trigger fetch calls</li>
        <li>Response must be JSON</li>
    </ul>

    <h3>asOffcanvas()</h3>
    <p>Configures the response to display the form in an offcanvas panel (sliding panel from the right).</p>
    <pre class="border p-2 bg-light"><code class="language-php">FormBuilder::create($model, $page)
    ->activeFetch()
    ->asOffcanvas()  // Display in offcanvas
    ->getResponse();</code></pre>
    <p><strong>JSON Response Key:</strong> <code>offcanvas_end</code></p>

    <h3>asModal()</h3>
    <p>Configures the response to display the form in a centered modal dialog.</p>
    <pre class="border p-2 bg-light"><code class="language-php">FormBuilder::create($model, $page)
    ->activeFetch()
    ->asModal()  // Display in modal
    ->getResponse();</code></pre>
    <p><strong>JSON Response Key:</strong> <code>modal</code></p>

    <h3>asDom($id)</h3>
    <p>Configures the response to render the form directly into a DOM element.</p>
    <pre class="border p-2 bg-light"><code class="language-php">FormBuilder::create($model, $page)
    ->activeFetch()
    ->asDom('contentWrapper')  // Render in element with ID
    ->getResponse();</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$id</code> - The ID of the target DOM element</li>
    </ul>
    <p><strong>JSON Response Key:</strong> <code>element</code></p>

    <h3>setTitle($new, $edit = null)</h3>
    <p>Sets dynamic titles that change automatically based on whether you're creating or editing a record.</p>
    <pre class="border p-2 bg-light"><code class="language-php">FormBuilder::create($model, $page)
    ->setTitle('New Recipe', 'Edit Recipe')  // Different titles
    ->getResponse();</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$new</code> - Title when creating a new record</li>
        <li><code>$edit</code> - Title when editing (optional, defaults to <code>$new</code>)</li>
    </ul>
    <p><strong>Auto-detection:</strong> The system checks if the record has an ID to determine which title to use.</p>

    <h3>dataListId($id)</h3>
    <p>Enables automatic table reload when an action (save/delete) completes successfully.</p>
    <pre class="border p-2 bg-light"><code class="language-php">FormBuilder::create($model, $page)
    ->dataListId('idTableRecipes')  // Table ID to reload
    ->getResponse();</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$id</code> - The ID of the table to reload</li>
    </ul>
    <p><strong>Behavior:</strong> When set and action succeeds, automatically adds <code>list => ['id' => ..., 'action' => 'reload']</code> to response and closes offcanvas/modal.</p>

    <h3>size($size)</h3>
    <p>Sets the size of the offcanvas panel or modal dialog.</p>
    <pre class="border p-2 bg-light"><code class="language-php">FormBuilder::create($model, $page)
    ->asOffcanvas()
    ->size('lg')  // or 'sm', 'xl', 'fullscreen'
    ->getResponse();</code></pre>
    <p><strong>Available Sizes:</strong></p>
    <ul>
        <li><code>sm</code> - Small</li>
        <li><code>lg</code> - Large</li>
        <li><code>xl</code> - Extra large</li>
        <li><code>fullscreen</code> - Full screen</li>
    </ul>

    <h3>getResponse()</h3>
    <p>Generates the complete JSON response array based on all configured options.</p>
    <pre class="border p-2 bg-light"><code class="language-php">$response = FormBuilder::create($model, $page)
    ->activeFetch()
    ->asOffcanvas()
    ->setTitle('New Item', 'Edit Item')
    ->dataListId('myTable')
    ->size('lg')
    ->getResponse();  // Returns array

Response::json($response);</code></pre>
    <p><strong>Returns:</strong> Array with keys:</p>
    <ul>
        <li><code>executed_action</code> - Name of executed action (save, delete, etc.)</li>
        <li><code>action_success</code> - Whether action succeeded</li>
        <li><code>list</code> - Table reload instructions (if dataListId set and success)</li>
        <li><code>offcanvas_end</code> / <code>modal</code> / <code>element</code> - Display configuration</li>
    </ul>

    <hr>

    <h2>Complete Example</h2>
    <p>A typical fetch-based form implementation:</p>

    <pre class="border p-2 bg-light"><code class="language-php"><?php echo htmlspecialchars('#[RequestAction(\'edit\')]
public function recipeEdit() {
    $response = [\'page\' => $this->page, \'title\' => $this->title];

    // Build form with fetch methods
    $response = array_merge($response, FormBuilder::create($this->model, $this->page)
        ->activeFetch()                              // Enable fetch mode
        ->asOffcanvas()                              // Display in offcanvas
        ->setTitle(\'New Recipe\', \'Edit Recipe\')      // Dynamic titles
        ->dataListId(\'idTableRecipes\')               // Auto-reload table
        ->size(\'lg\')                                  // Large size
        ->getResponse());                            // Generate response

    // Send JSON response
    Response::json($response);
}'); ?></code></pre>

    <p><strong>Generated JSON Response:</strong></p>
    <pre class="border p-2 bg-light"><code class="language-json">{
    "page": "recipes",
    "title": "My Recipes",
    "executed_action": null,
    "action_success": false,
    "offcanvas_end": {
        "title": "New Recipe",      // or "Edit Recipe"
        "action": "show",            // or "hide" if success
        "body": "<form>...</form>",
        "size": "lg"
    }
}</code></pre>

    <p>After successful save:</p>
    <pre class="border p-2 bg-light"><code class="language-json">{
    "executed_action": "save",
    "action_success": true,
    "list": {
        "id": "idTableRecipes",
        "action": "reload"
    },
    "offcanvas_end": {
        "title": "Edit Recipe",
        "action": "hide",
        "body": "<form>...</form>",
        "size": "lg"
    }
}</code></pre>

    <hr>

    <h2>Method Chaining</h2>
    <p>All methods except <code>getResponse()</code> return <code>self</code>, enabling fluent method chaining:</p>

    <pre class="border p-2 bg-light"><code class="language-php">FormBuilder::create($model, $page)
    ->activeFetch()                    // Returns self
    ->asOffcanvas()                    // Returns self
    ->setTitle('New', 'Edit')          // Returns self
    ->dataListId('myTable')            // Returns self
    ->size('lg')                       // Returns self
    ->getResponse();                   // Returns array</code></pre>

    <hr>

    <h2>Common Patterns</h2>

    <h3>Pattern 1: Offcanvas with Auto-Reload</h3>
    <pre class="border p-2 bg-light"><code class="language-php">->activeFetch()
->asOffcanvas()
->setTitle('New Item', 'Edit Item')
->dataListId('myTableId')
->getResponse()</code></pre>

    <h3>Pattern 2: Modal with Custom Size</h3>
    <pre class="border p-2 bg-light"><code class="language-php">->activeFetch()
->asModal()
->setTitle('Add User', 'Edit User')
->size('xl')
->getResponse()</code></pre>

    <h3>Pattern 3: DOM Element Replacement</h3>
    <pre class="border p-2 bg-light"><code class="language-php">->activeFetch()
->asDom('formContainer')
->setTitle('Form Title')
->getResponse()</code></pre>

    <hr>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Advanced/fetch-modal-crud">Complete Tutorial - Creating Fetch-Based Forms</a></li>
        <li><a href="?page=docs&action=Framework/Theme/theme-json-actions">JSON Actions (MilkActions) - Response System</a></li>
        <li><a href="?page=docs&action=Developer/Form/builders-form">FormBuilder - Complete Reference</a></li>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder - activeFetch() for Tables</a></li>
    </ul>

</div>

<style>
pre {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}
.alert {
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 0.5rem;
}
.alert-info {
    background: #cfe2ff;
    border-left: 4px solid #0d6efd;
}
.table-dark {
    background: #212529;
    color: white;
}
code {
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.9em;
}
</style>
