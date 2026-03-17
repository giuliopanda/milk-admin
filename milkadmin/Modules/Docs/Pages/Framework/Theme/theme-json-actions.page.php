<?php
namespace Modules\Docs\Pages;
/**
 * @title  JSON Actions (MilkActions)
 * @guide framework
 * @order 65
 * @tags json, ajax, actions, server-response, modal, toast, dom-manipulation, fetch, hooks, form-management
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
<h1 class="mb-4">MilkActions - JSON Action Response System</h1>

<p class="lead">
<strong>MilkActions</strong> is a structured JSON response system that allows backend PHP code to control frontend components via AJAX responses.
It provides a unified way to manipulate the DOM, show modals, display notifications, and much more, without writing custom JavaScript.
</p>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> <strong>Note:</strong>
    JSON actions are automatically processed when using links with <code>data-fetch="get"</code> or <code>data-fetch="post"</code>,
    and forms with <code>.js-needs-validation</code> class.
</div>

<hr class="my-4">

<!-- ========== FEATURES ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-star-fill"></i> Features</h2>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-window"></i> Modal Management</h5>
                <p class="card-text">Complete control of modal sizes, loading states, and content</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-bell"></i> Toast Notifications</h5>
                <p class="card-text">Success, error, warning, and info notifications</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-input-cursor"></i> Form Management</h5>
                <p class="card-text">Reset, set values, and display validation errors</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-code-square"></i> DOM Manipulation</h5>
                <p class="card-text">Show/hide, update content, modify classes, styles, and attributes</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-type-h1"></i> Title Update</h5>
                <p class="card-text">Update page titles via AJAX with automatic JS re-initialization</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-arrow-up-circle"></i> Scroll Control</h5>
                <p class="card-text">Scroll to top or specific elements</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-link-45deg"></i> JavaScript Hooks</h5>
                <p class="card-text">Execute custom JavaScript functions from the server</p>
            </div>
        </div>
    </div>
</div>

<hr class="my-4">

<!-- ========== BASIC USAGE ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-play-circle"></i> Basic Usage</h2>

<h4 class="mt-4">Backend (PHP)</h4>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-php">use App\Response;

#[RequestAction('myAction')]
public function myAction() {
    Response::json([
        'success' => true,
        'modal' => [
            'title' => 'Hello',
            'body' => '&lt;p&gt;This is a modal&lt;/p&gt;'
        ],
        'toast' => [
            'message' => 'Operation completed',
            'type' => 'success'
        ]
    ]);
}</code></pre>

<h4 class="mt-4">Frontend (HTML)</h4>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-html">&lt;!-- Links with data-fetch automatically use MilkActions --&gt;
&lt;a href="?page=myModule&amp;action=myAction" data-fetch="get" class="btn btn-primary"&gt;
    Click Me
&lt;/a&gt;</code></pre>

<hr class="my-4">

<!-- ========== MODAL ACTIONS ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-window"></i> 1. Modal</h2>

<p>Complete control of Bootstrap modals with management of sizes, content, and loading states.</p>

<h5 class="mt-4">JSON Example</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "modal": {
        "size": "lg",              // sm, lg, xl, fullscreen, or default
        "action": "show",          // show, hide, loading_show, loading_hide
        "title": "My Title",
        "body": "&lt;p&gt;Content&lt;/p&gt;",
        "footer": "&lt;button&gt;OK&lt;/button&gt;"
    }
}</code></pre>

<h5 class="mt-4">Available Methods</h5>
<ul>
    <li><code>show</code> - Display modal</li>
    <li><code>hide</code> - Close modal</li>
    <li><code>loading_show</code> - Show with loading spinner</li>
    <li><code>loading_hide</code> - Hide loading spinner</li>
</ul>

<h5 class="mt-4">Sizes</h5>
<ul>
    <li><code>sm</code> - Small modal</li>
    <li><code>lg</code> - Large modal</li>
    <li><code>xl</code> - Extra large modal</li>
    <li><code>fullscreen</code> - Fullscreen modal</li>
    <li>(empty) - Default size</li>
</ul>

<div class="alert alert-warning mt-3">
    <strong>PHP Example:</strong>
    <pre class="mb-0 mt-2"><code>Response::json([
    'modal' => [
        'size' => 'lg',
        'action' => 'loading_show',
        'title' => 'Loading...',
        'body' => '&lt;p&gt;Please wait...&lt;/p&gt;'
    ]
]);</code></pre>
</div>

<hr class="my-4">

<!-- ========== TOAST NOTIFICATIONS ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-bell"></i> 2. Toast Notifications</h2>

<p>Toast notifications for immediate user feedback.</p>

<h5 class="mt-4">JSON Example</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "toast": {
        "message": "Success!",
        "type": "success",         // success, danger, warning, primary
        "action": "show"           // show or hide
    }
}</code></pre>

<h5 class="mt-4">Default Support</h5>
<p>MilkActions also supports a simplified format:</p>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "msg": "Message here",
    "success": true                // true = success, false = danger
}</code></pre>

<div class="alert alert-warning mt-3">
    <strong>PHP Example:</strong>
    <pre class="mb-0 mt-2"><code>Response::json([
    'toast' => [
        'message' => 'Data saved successfully',
        'type' => 'success'
    ]
]);</code></pre>
</div>

<hr class="my-4">

<!-- ========== FORM MANAGEMENT ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-input-cursor"></i> 3. Form Management</h2>

<p>Complete form management: reset, set values, and display validation errors.</p>

<h5 class="mt-4">JSON Example</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "form": {
        "action": "reset",         // reset form
        "id": "myFormId",
        "fields": {                // set field values
            "field_name": "value",
            "email": "test@example.com"
        },
        "errors": {                // show validation errors
            "field_name": "Error message",
            "email": "Invalid email"
        }
    }
}</code></pre>

<h5 class="mt-4">Reset Form</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-php">Response::json([
    'form' => [
        'action' => 'reset',
        'id' => 'myFormId'
    ]
]);</code></pre>

<h5 class="mt-4">Validation Errors</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-php">Response::json([
    'success' => false,
    'form' => [
        'errors' => [
            'username' => 'Username is required',
            'email' => 'Invalid email format',
            'password' => 'Password must be at least 8 characters'
        ]
    ],
    'toast' => [
        'message' => 'Please fix the errors',
        'type' => 'danger'
    ]
]);</code></pre>

<hr class="my-4">

<!-- ========== ELEMENT MANIPULATION ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-code-square"></i> 4. Element Manipulation</h2>

<p>Complete DOM manipulation with support for single elements or multiple groups.</p>

<h5 class="mt-4">Single Element</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "element": {
        "selector": "#myElement",
        "action": "show",          // show, hide, remove, toggle
        "innerHTML": "&lt;p&gt;New content&lt;/p&gt;",
        "innerText": "Text only",
        "value": "Input value",
        "addClass": "highlight active",
        "removeClass": "old-class",
        "toggleClass": "active",
        "attributes": {
            "data-id": "123",
            "title": "My title"
        },
        "removeAttributes": ["disabled", "readonly"],
        "style": {
            "color": "red",
            "backgroundColor": "#f0f0f0"
        },
        "append": "&lt;div&gt;Append this&lt;/div&gt;",
        "prepend": "&lt;div&gt;Prepend this&lt;/div&gt;",
        "before": "&lt;div&gt;Insert before&lt;/div&gt;",
        "after": "&lt;div&gt;Insert after&lt;/div&gt;"
    }
}</code></pre>

<h5 class="mt-4">Multiple Elements</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "elements": [
        {
            "selector": "#element1",
            "innerHTML": "Content 1",
            "addClass": "active"
        },
        {
            "selector": "#element2",
            "innerHTML": "Content 2",
            "addClass": "highlight"
        }
    ]
}</code></pre>

<h5 class="mt-4">Available Properties</h5>

<table class="table table-bordered mt-3">
    <thead>
        <tr>
            <th>Property</th>
            <th>Type</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>selector</code></td>
            <td>string</td>
            <td>CSS selector (required)</td>
        </tr>
        <tr>
            <td><code>action</code></td>
            <td>string</td>
            <td>show, hide, remove, toggle</td>
        </tr>
        <tr>
            <td><code>innerHTML</code></td>
            <td>string</td>
            <td>Set HTML content</td>
        </tr>
        <tr>
            <td><code>innerText</code></td>
            <td>string</td>
            <td>Set text content (escapes HTML)</td>
        </tr>
        <tr>
            <td><code>value</code></td>
            <td>string</td>
            <td>Set input value</td>
        </tr>
        <tr>
            <td><code>addClass</code></td>
            <td>string/array</td>
            <td>Add CSS classes</td>
        </tr>
        <tr>
            <td><code>removeClass</code></td>
            <td>string/array</td>
            <td>Remove CSS classes</td>
        </tr>
        <tr>
            <td><code>toggleClass</code></td>
            <td>string/array</td>
            <td>Toggle CSS classes</td>
        </tr>
        <tr>
            <td><code>attributes</code></td>
            <td>object</td>
            <td>Set attributes</td>
        </tr>
        <tr>
            <td><code>removeAttributes</code></td>
            <td>array</td>
            <td>Remove attributes</td>
        </tr>
        <tr>
            <td><code>style</code></td>
            <td>object</td>
            <td>Set inline styles</td>
        </tr>
        <tr>
            <td><code>append</code></td>
            <td>string</td>
            <td>Append HTML at end</td>
        </tr>
        <tr>
            <td><code>prepend</code></td>
            <td>string</td>
            <td>Prepend HTML at start</td>
        </tr>
        <tr>
            <td><code>before</code></td>
            <td>string</td>
            <td>Insert HTML before element</td>
        </tr>
        <tr>
            <td><code>after</code></td>
            <td>string</td>
            <td>Insert HTML after element</td>
        </tr>
    </tbody>
</table>

<div class="alert alert-warning mt-3">
    <strong>PHP Example - Complex Manipulation:</strong>
    <pre class="mb-0 mt-2"><code>Response::json([
    'element' => [
        'selector' => '#productCard',
        'innerHTML' => '&lt;h3&gt;Product Name&lt;/h3&gt;&lt;p&gt;$99.99&lt;/p&gt;',
        'addClass' => 'featured highlight',
        'removeClass' => 'out-of-stock',
        'attributes' => [
            'data-product-id' => '12345',
            'data-price' => '99.99'
        ],
        'style' => [
            'border' => '2px solid gold'
        ]
    ]
]);</code></pre>
</div>

<hr class="my-4">

<!-- ========== SCROLL ACTIONS ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-arrow-up-circle"></i> 5. Scroll Actions</h2>

<p>Control page scrolling to top or specific elements.</p>

<h5 class="mt-4">Scroll to Top</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "scroll": {
        "to": "top",               // scroll to top
        "behavior": "smooth"       // smooth or auto
    }
}</code></pre>

<h5 class="mt-4">Scroll to Element</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "scroll": {
        "selector": "#targetElement",
        "behavior": "smooth",      // smooth or auto
        "block": "center"          // start, center, end, nearest
    }
}</code></pre>

<div class="alert alert-warning mt-3">
    <strong>PHP Example:</strong>
    <pre class="mb-0 mt-2"><code>Response::json([
    'scroll' => [
        'selector' => '#errorSection',
        'behavior' => 'smooth',
        'block' => 'start'
    ]
]);</code></pre>
</div>

<hr class="my-4">

<!-- ========== JAVASCRIPT HOOKS ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-link-45deg"></i> 6. JavaScript Hooks</h2>

<p>Execute custom JavaScript functions registered with <code>registerHook()</code> directly from server responses.</p>

<h5 class="mt-4">Single Hook</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "hook": {
        "name": "my_custom_hook",
        "args": ["arg1", "arg2", "arg3"],
        "debug": true              // Optional: log to console
    }
}</code></pre>

<h5 class="mt-4">Multiple Hooks</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "hooks": [
        {
            "name": "hook_one",
            "args": ["data"]
        },
        {
            "name": "hook_two",
            "args": [123, "test"]
        }
    ]
}</code></pre>

<h5 class="mt-4">Registering Hooks (JavaScript)</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-javascript">// Register a hook in your JavaScript
registerHook('my_custom_hook', function(arg1, arg2, arg3) {
    console.log('Hook called with:', arg1, arg2, arg3);
    // Your custom logic here
    return 'result';
});</code></pre>

<h5 class="mt-4">Hook Chaining</h5>
<p>Multiple callbacks can be registered for the same hook name. They execute in order, with each receiving the result of the previous one.</p>

<pre class="pre-scrollable border p-3 bg-light"><code class="language-javascript">registerHook('process_data', function(data) {
    data.step1 = true;
    return data;
});

registerHook('process_data', function(data) {
    data.step2 = true;
    return data;
});</code></pre>

<div class="alert alert-warning mt-3">
    <strong>PHP Example with Hook:</strong>
    <pre class="mb-0 mt-2"><code>Response::json([
    'hook' => [
        'name' => 'update_dashboard',
        'args' => [
            ['users' => 150, 'posts' => 523, 'comments' => 1247]
        ]
    ],
    'toast' => [
        'message' => 'Dashboard updated',
        'type' => 'success'
    ]
]);</code></pre>
</div>

<hr class="my-4">

<!-- ========== OTHER ACTIONS ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-grid-3x3-gap"></i> Other Actions</h2>

<h4 class="mt-4">Title Update</h4>
<p>Update a page title by ID. Replaces the inner HTML of the title container and re-initializes all JavaScript (fetch links, forms, filters, etc.) via <code>updateContainer()</code>.</p>
<p>The title must have an ID set via <code>TitleBuilder::setId('myTitleId')</code>. Use <code>renderInner()</code> to generate only the inner content.</p>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "title": {
        "id": "myTitleId",
        "html": "&lt;div class='row'&gt;...&lt;/div&gt;"
    }
}</code></pre>

<div class="alert alert-warning mt-3">
    <strong>PHP Example:</strong>
    <pre class="mb-0 mt-2"><code>// Initial render (in home action)
$titleBuilder = TitleBuilder::create('My Page Title')
    ->setId('myTitleId')
    ->addButton('Refresh', '?page=myModule&amp;action=refresh', 'primary', '', 'get');
echo $titleBuilder;

// AJAX update (in refresh action)
$newTitle = TitleBuilder::create('Updated Title')
    ->setId('myTitleId')
    ->addButton('Refresh Again', '?page=myModule&amp;action=refresh', 'success', '', 'get');

Response::json([
    'success' => true,
    'title' => [
        'id' => 'myTitleId',
        'html' => $newTitle->renderInner(),
    ]
]);</code></pre>
</div>

<h4 class="mt-4">Reload list (Table, list, calendar)</h4>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "list": {
        "id": "myListId",
        "action": "reload"
    }
}</code></pre>

<h4 class="mt-4">Redirect</h4>
<p>Optional delay in milliseconds with <code>redirect_delay</code>.</p>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "redirect": "/path/to/page",
    "redirect_delay": 1500
}</code></pre>

<h4 class="mt-4">Window Reload</h4>
<p>Reload the current page after a delay in milliseconds.</p>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "window_reload": 1000
}</code></pre>

<h4 class="mt-4">HTML Replacement</h4>
<p><strong>Note:</strong> Replaces the container that initiated the request.</p>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "html": "&lt;div&gt;New HTML&lt;/div&gt;"
}</code></pre>

<h4 class="mt-4">Offcanvas</h4>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-json">{
    "offcanvas_end": {
        "size": "xl",              // 'xl', 'l', or default
        "action": "show",
        "title": "Edit Item",
        "body": "&lt;form&gt;...&lt;/form&gt;"
    }
}</code></pre>

<hr class="my-4">

<!-- ========== COMBINED EXAMPLE ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-stack"></i> Combined Example</h2>

<p>Execute multiple actions in a single response:</p>

<pre class="pre-scrollable border p-3 bg-light"><code class="language-html">Response::json([
    'success' => true,

    // Update multiple elements
    'elements' => [
        [
            'selector' => '#counter',
            'innerHTML' => '&lt;strong&gt;5&lt;/strong&gt;'
        ],
        [
            'selector' => '#status',
            'addClass' => 'badge-success',
            'removeClass' => 'badge-warning'
        ]
    ],

    // Show modal
    'modal' => [
        'size' => 'lg',
        'title' => 'Operation Complete',
        'body' => '&lt;p&gt;All items have been processed.&lt;/p&gt;',
        'footer' => '&lt;button class="btn btn-primary" data-bs-dismiss="modal"&gt;OK&lt;/button&gt;'
    ],

    // Show toast
    'toast' => [
        'message' => 'Successfully updated!',
        'type' => 'success'
    ],

    // Scroll to top
    'scroll' => [
        'to' => 'top'
    ],

    // Reload table
    'table' => [
        'id' => 'dataTable',
        'action' => 'reload'
    ]
]);</code></pre>

<hr class="my-4">

<!-- ========== INTEGRATION ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-puzzle"></i> Integration</h2>

<h5 class="mt-4">Manual Fetch Call</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-javascript">fetch('?page=myModule&amp;action=myAction')
    .then(response => response.json())
    .then(data => {
        jsonAction(data);  // Process MilkActions response
    });</code></pre>

<h5 class="mt-4">With FormData</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-javascript">const formData = new FormData(myForm);
fetch('?page=myModule&amp;action=submit', {
    method: 'POST',
    body: formData
})
    .then(response => response.json())
    .then(data => {
        jsonAction(data);
    });</code></pre>

<hr class="my-4">

<!-- ========== BEST PRACTICES ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-check2-circle"></i> Best Practices</h2>

<ul class="list-group list-group-flush">
    <li class="list-group-item">
        <strong>1. Always set success:</strong> Include <code>"success": true</code> or <code>"success": false</code> in responses
    </li>
    <li class="list-group-item">
        <strong>2. User feedback:</strong> Always provide toast notification for user actions
    </li>
    <li class="list-group-item">
        <strong>3. Combine actions:</strong> Use multiple actions in one response when appropriate
    </li>
    <li class="list-group-item">
        <strong>4. Error handling:</strong> Return <code>"success": false</code> with appropriate error messages
    </li>
    <li class="list-group-item">
        <strong>5. Validate selectors:</strong> Ensure DOM selectors exist before referencing them
    </li>
</ul>

<h5 class="mt-4">Error Handling Pattern</h5>
<pre class="pre-scrollable border p-3 bg-light"><code class="language-php">try {
    // Your operation
    Response::json([
        'success' => true,
        'toast' => ['message' => 'Success!', 'type' => 'success']
    ]);
} catch (Exception $e) {
    Response::json([
        'success' => false,
        'toast' => ['message' => $e->getMessage(), 'type' => 'danger']
    ]);
}</code></pre>

<hr class="my-4">

<!-- ========== ARCHITECTURE ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-diagram-3"></i> Architecture</h2>

<h5 class="mt-4">Files</h5>
<ul>
    <li><code>ajax-handler.js</code> - Contains <code>jsonAction()</code> and <code>milkActionsProcessElement()</code> functions</li>
    <li><code>theme.js</code> - Contains <code>Modal</code>, <code>Toasts</code>, <code>Offcanvas_end</code> classes</li>
    <li><code>MilkActionsModule.php</code> - Test module with examples</li>
    <li><code>test_page.php</code> - Interactive test interface</li>
</ul>

<h5 class="mt-4">Key Functions</h5>
<ul>
    <li><code>jsonAction(data, container)</code> - Main processor for MilkActions responses</li>
    <li><code>milkActionsProcessElement(elementData)</code> - Process single element manipulation</li>
    <li><code>window.modal</code> - Global Modal instance</li>
    <li><code>window.toasts</code> - Global Toasts instance</li>
    <li><code>window.offcanvasEnd</code> - Global Offcanvas instance</li>
</ul>

<hr class="my-4">

<!-- ========== QUICK REFERENCE ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-table"></i> Quick Reference</h2>

<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Action</th>
            <th>Purpose</th>
            <th>Example Keys</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>modal</code></td>
            <td>Control modal dialogs</td>
            <td><code>modal.title</code>, <code>modal.body</code>, <code>modal.action</code></td>
        </tr>
        <tr>
            <td><code>offcanvas_end</code></td>
            <td>Control offcanvas panel</td>
            <td><code>offcanvas_end.action</code>, <code>offcanvas_end.size</code></td>
        </tr>
        <tr>
            <td><code>toast</code></td>
            <td>Show notifications</td>
            <td><code>toast.message</code>, <code>toast.type</code></td>
        </tr>
        <tr>
            <td><code>form</code></td>
            <td>Manage forms</td>
            <td><code>form.action</code>, <code>form.fields</code>, <code>form.errors</code></td>
        </tr>
        <tr>
            <td><code>element</code></td>
            <td>Single element manipulation</td>
            <td><code>element.selector</code>, <code>element.innerHTML</code></td>
        </tr>
        <tr>
            <td><code>elements</code></td>
            <td>Multiple elements</td>
            <td>Array of element objects</td>
        </tr>
        <tr>
            <td><code>scroll</code></td>
            <td>Control scrolling</td>
            <td><code>scroll.to</code>, <code>scroll.selector</code></td>
        </tr>
        <tr>
            <td><code>title</code></td>
            <td>Update page title</td>
            <td><code>title.id</code>, <code>title.html</code></td>
        </tr>
        <tr>
            <td><code>table</code></td>
            <td>Table operations</td>
            <td><code>table.id</code>, <code>table.action</code></td>
        </tr>
        <tr>
            <td><code>hook</code></td>
            <td>Call JavaScript hook</td>
            <td><code>hook.name</code>, <code>hook.args</code></td>
        </tr>
        <tr>
            <td><code>hooks</code></td>
            <td>Call multiple hooks</td>
            <td>Array of hook objects</td>
        </tr>
        <tr>
            <td><code>redirect</code></td>
            <td>Navigate to page</td>
            <td><code>redirect</code>, <code>redirect_delay</code></td>
        </tr>
        <tr>
            <td><code>window_reload</code></td>
            <td>Reload current page</td>
            <td>Delay in milliseconds</td>
        </tr>
        <tr>
            <td><code>html</code></td>
            <td>Replace container</td>
            <td>HTML string</td>
        </tr>
    </tbody>
</table>

<hr class="my-4">

<!-- ========== TESTING ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-play-btn"></i> Testing</h2>

<div class="alert alert-success">
    <i class="bi bi-info-circle"></i>
    <strong>Test Page:</strong> Visit the test page to try all features:
    <br>
    <a href="?page=milkActions" class="btn btn-success btn-sm mt-2">
        <i class="bi bi-box-arrow-up-right"></i> Open MilkActions Test Page
    </a>
</div>

<p class="mt-3">The test page includes interactive examples for:</p>
<ul>
    <li>All modal sizes and states</li>
    <li>Toast notifications (success, error, warning, info)</li>
    <li>Form management</li>
    <li>Element manipulation</li>
    <li>Scroll actions</li>
    <li>JavaScript hooks</li>
    <li>Combined operations</li>
</ul>

<hr class="my-4">

<!-- ========== BROWSER COMPATIBILITY ========== -->
<h2 class="mt-5 mb-3"><i class="bi bi-browser-chrome"></i> Browser Compatibility</h2>

<p>MilkActions uses modern JavaScript features:</p>
<ul>
    <li>Fetch API</li>
    <li>Promises</li>
    <li>ES6 Classes</li>
    <li>Template Literals</li>
</ul>

<p class="mt-3"><strong>Supported browsers:</strong></p>
<ul>
    <li>Chrome 60+</li>
    <li>Firefox 55+</li>
    <li>Safari 11+</li>
    <li>Edge 79+</li>
</ul>

<hr class="my-4">

<div class="alert alert-info mt-5">
    <strong>Created by:</strong> MilkAdmin Team<br>
    <strong>Version:</strong> 1.0.0<br>
    <strong>Last Updated:</strong> 2025-01-22
</div>

</div>
