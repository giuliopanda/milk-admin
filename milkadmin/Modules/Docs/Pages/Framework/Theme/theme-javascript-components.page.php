<?php
namespace Modules\Docs\Pages;
/**
 * @title  Javascript components
 * @guide framework
 * @order 50
 * @tags JavaScript-components, sortable-lists, AJAX-handling, fetch-API, permission-handling, drag-drop, interactive-components, javascript, dom, elements, eI, eIs, dom-manipulation, events, toggle, hide-show, elHide, elShow, elRemove, toggleEl, sortable, ItoSortableList, fetch, ajax, permissions, error-handling, forms, checkbox, select, dataset, animation, styles, classes, appendChild, createElement, querySelector, getElementById, event-listener, callback, vanilla-js, framework, utility, helper-functions, json, response, toast, notifications, handlers, hooks, components, attributes, css, html, dynamic, interactive, getComponent, get_component, getComponentname, registerHook, callHook, toggleEl, elRemove, elShow, elHide
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Theme JavaScript</h1>
    <h2 class="mt-4"> ItoSortableList </h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">const myList = document.getElementById('mylst');
const myPaginator = new ItoSortableList(myList, {
    handleSelector: '.drag-handle',
    onUpdate: (newOrder) => {
        console.log('New order:', newOrder.map(el => el.textContent.trim()));
    }
});</code></pre>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Sortable List Example</h5>
            <div class="mb-4 d-flex gap-2">
                <button id="addButton" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Add element
                </button>
                <button id="removeButton" class="btn btn-danger">
                <i class="bi bi-trash me-2"></i>Remove last
                </button>
            </div>

            <div id="myList" class="list-group">
                <div class="list-group-item d-flex align-items-center justify-content-between sortable-item">
                <span>First element</span>
                <button class="btn btn-light drag-handle">
                    <i class="bi bi-arrows-move"></i>
                </button>
                </div>
                <div class="list-group-item d-flex align-items-center justify-content-between sortable-item">
                <span>Second element</span>
                <button class="btn btn-light drag-handle">
                    <i class="bi bi-arrows-move"></i>
                </button>
                </div>
                <div class="list-group-item d-flex align-items-center justify-content-between sortable-item">
                <span>Third element</span>
                <button class="btn btn-light drag-handle">
                    <i class="bi bi-arrows-move"></i>
                </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const myContainer = document.getElementById('myList');
    var sortable = '';
    document.addEventListener('DOMContentLoaded', () => {        

        sortable = new ItoSortableList(myContainer, {
        handleSelector: '.drag-handle',
        onUpdate: (newOrder) => {
            console.log('New order:', newOrder.map(el => el.textContent.trim()));
        }
        });
    });

// Counter to generate progressive numbers for new elements
let counter = myContainer.children.length + 1;

// Add button handler
document.getElementById('addButton').addEventListener('click', () => {
  const newItemContent = `
    <span>Element ${counter}</span>
    <button class="btn btn-light drag-handle">
      <i class="bi bi-arrows-move"></i>
    </button>
  `;
  
  const newItem = document.createElement('div');
  newItem.className = 'list-group-item d-flex align-items-center justify-content-between sortable-item';
  newItem.innerHTML = newItemContent;
  
  sortable.makeDraggable(newItem);
  myContainer.appendChild(newItem);
  counter++;
});

// Remove button handler
document.getElementById('removeButton').addEventListener('click', () => {
  const items = myContainer.children;
  if (items.length > 0) {
    items[items.length - 1].remove();
  }
});
</script>

<h2 class="mt-4">Fetch and Response Management with Permissions</h2>

<h3>1. Client Side (JavaScript)</h3>
<p>The system uses a custom version of <code>fetch</code> that automatically handles JSON responses and permission denied cases through <code>ajax-handler.js</code>.</p>

<h4>Fetch Call Example</h4>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">// Example fetch to save data
fetch(milk_url + '?page=reports&action=json-related-tables-save', {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
}).then(response => response.json())
  .then(data => {
      // Use the centralized handler to manage permission errors and other responses
      if (!window.handleAjaxResponse(data)) {
          return; // Stop processing if there's an error or permissions denied
      }
      
      // Success handling
      if (data.success) {
          window.toasts.show('Operation completed successfully', 'success');
      }
  }).catch(error => {
      console.error('Error during fetch:', error);
  });</code></pre>

<h3>2. Centralized Management (ajax-handler.js)</h3>
<p>The <code>ajax-handler.js</code> file overwrites the native <code>fetch</code> function to add automatic permission handling:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">// Centralized AJAX response handling in ajax-handler.js
window.handleAjaxResponse = function(response) {
    if (!response) return;

    if (response.permission_denied === true) {
        window.toasts.show(`Permission denied: ${response.msg}`, 'danger');
        return false;
    }

    if (!response.success) {
        window.toasts.show(response.msg || 'An error occurred', 'danger');
        return false;
    }

    return true;
};</code></pre>

<h3>3. Server Side (PHP)</h3>
<p>In the PHP router file (e.g., <code>reports.router.php</code>), permission denied handling is implemented through the <code>json_permission_denied</code> method:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function jsonPermissionDenied( $custom_message = ''): void {
    http_response_code(403); // Set HTTP 403 Forbidden status code
    $message = $custom_message ?: "You don't have permissions for this action";
    $response = [
        'success' => false,
        'msg' => $message,
        'permission_denied' => true,
        'code' => 403
    ];
    
    Response::json($response);
    exit;
}</code></pre>

<h3>4. Controller Usage Example</h3>
<p>In the router, when a permission issue occurs in an action:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function actionJsonRelatedTablesSave() {
   
    // Permission check
    Permissions::checkJson('my_class.edit');
    
    header('Content-Type: application/json');
    
    // Rest of the code to handle the action...
}</code></pre>

<h3>5. Complete Flow</h3>
<ol>
    <li>Client makes a fetch request</li>
    <li>PHP router checks permissions</li>
    <li>If permissions are denied, <code>jsonPermissionDenied()</code> is called</li>
    <li>JSON response is intercepted by <code>ajax-handler.js</code></li>
    <li>An error message is shown to the user via toast</li>
</ol>

<div class="alert alert-info">
<strong>Note:</strong> This system ensures uniform permission error handling throughout the application, improving both security and user experience.
</div>

<h2 class="mt-4"> Other functions </h2>
<h4 class="mt-3">getFormData(queryString)</h4>
<p>Utility function to transform a query string into a FormData object</p>
<h4 class="mt-3">getComponentname(id)</h4>
<p>Return a component name by DOM element Id</p>
<h4 class="mt-3">getComponent(id)</h4>
<p>Find a component by id</p>
<h4 class="mt-3">toggleEl(el, el_form, compare_value)</h4>
<p>Show or hide an element</p>
<h4>elHide(el, fn)</h4>
<p>Hide an element with fade animation</p>
<h4>elShow(el, fn)</h4>
<p>Show an element with fade animation</p>
<h4 class="mt-3">callHook / registerHook</h4>
<p>Call or register a hook</p>
<h4 class="mt-3">removeIsInvalid</h4>
<p>Function to remove the error message from a field</p>
<h4 class="mt-3">__(key, params = {})</h4>
<p>Translation function</p>

</div>