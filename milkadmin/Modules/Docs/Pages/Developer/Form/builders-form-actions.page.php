<?php
namespace Modules\Docs\Pages;

/**
 * @title Action Management
 * @guide developer
 * @order 42
 * @tags FormBuilder, actions, buttons, submit, link, custom-actions, showIf, conditional-buttons, delete, save, cancel, form-actions
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>FormBuilder - Action Management</h1>
    <p class="text-muted">Revision: 2025/11/21</p>
    <p>Manage form buttons with submit, link, custom callbacks and conditional visibility.</p>

    <h2>Methods</h2>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Method</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>addStandardActions($include_delete, $cancel_link)</code></td>
                <td>Adds pre-configured save/delete/cancel buttons</td>
            </tr>
            <tr>
                <td><code>setActions($actions)</code></td>
                <td>Replaces all existing actions</td>
            </tr>
            <tr>
                <td><code>addActions($actions)</code></td>
                <td>Adds actions without replacing existing ones</td>
            </tr>
        </tbody>
    </table>

    <h2>Action Parameters</h2>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Type</th>
                <th>Default</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>label</code></td>
                <td>string</td>
                <td>Capitalized key</td>
                <td>Button text</td>
            </tr>
            <tr>
                <td><code>type</code></td>
                <td>string</td>
                <td>'submit'</td>
                <td>'submit', 'button' or 'link'</td>
            </tr>
            <tr>
                <td><code>class</code></td>
                <td>string</td>
                <td>'btn btn-primary'</td>
                <td>CSS classes for styling</td>
            </tr>
            <tr>
                <td><code>action</code></td>
                <td>callable</td>
                <td>-</td>
                <td>Callback function: <code>function($fb, $request): array</code><br>Returns: <code>['success' => bool, 'message' => string]</code></td>
            </tr>
            <tr>
                <td><code>link</code></td>
                <td>string</td>
                <td>-</td>
                <td>URL for link-type buttons</td>
            </tr>
            <tr>
                <td><code>validate</code></td>
                <td>bool</td>
                <td>true</td>
                <td>Enable/disable HTML5 validation</td>
            </tr>
            <tr>
                <td><code>confirm</code></td>
                <td>string</td>
                <td>-</td>
                <td>Confirmation message before submit</td>
            </tr>
            <tr>
                <td><code>onclick</code></td>
                <td>string</td>
                <td>-</td>
                <td>Custom JavaScript onclick handler</td>
            </tr>
            <tr>
                <td><code>target</code></td>
                <td>string</td>
                <td>-</td>
                <td>Link target: _blank, _self, etc.</td>
            </tr>
            <tr>
                <td><code>attributes</code></td>
                <td>array</td>
                <td>[]</td>
                <td>Custom HTML attributes as key=>value pairs (e.g., ['data-id' => '123', 'title' => 'Click me'])</td>
            </tr>
            <tr>
                <td><code>showIf</code></td>
                <td>array</td>
                <td>-</td>
                <td>Conditional visibility: <code>[$field, $operator, $value]</code></td>
            </tr>
        </tbody>
    </table>

    <h2>showIf Operators</h2>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Operator</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>'empty'</code></td>
                <td>Field is empty, null, 0 or empty string</td>
                <td><code>['status', 'empty', 0]</code></td>
            </tr>
            <tr>
                <td><code>'not_empty'</code></td>
                <td>Field has a value</td>
                <td><code>['id', 'not_empty', 0]</code></td>
            </tr>
            <tr>
                <td><code>'='</code> or <code>'=='</code></td>
                <td>Equal to value</td>
                <td><code>['status', '=', 'draft']</code></td>
            </tr>
            <tr>
                <td><code>'!='</code> or <code>'&lt;&gt;'</code></td>
                <td>Not equal to value</td>
                <td><code>['status', '!=', 'published']</code></td>
            </tr>
            <tr>
                <td><code>'&gt;'</code>, <code>'&lt;'</code>, <code>'&gt;='</code>, <code>'&lt;='</code></td>
                <td>Comparison operators</td>
                <td><code>['price', '&gt;', 100]</code></td>
            </tr>
        </tbody>
    </table>

    <h2>Static Helpers</h2>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Helper</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>\Builders\FormBuilder::saveAction()</code></td>
                <td>Standard save operation with redirect</td>
            </tr>
            <tr>
                <td><code>\Builders\FormBuilder::deleteAction($url_success, $url_error)</code></td>
                <td>Delete operation with custom redirect URLs</td>
            </tr>
        </tbody>
    </table>

    <h2>Examples</h2>

    <h3>Standard Actions (Quick Setup)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Adds Save and Cancel buttons
->addStandardActions(false, '?page=posts')

// Adds Save, Delete and Cancel buttons
->addStandardActions(true, '?page=posts')</code></pre>

    <h3>Custom Actions</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->setActions([
    'save' => [
        'label' => 'Save',
        'class' => 'btn btn-primary',
        'action' => \Builders\FormBuilder::saveAction()
    ],
    'delete' => [
        'label' => 'Delete',
        'class' => 'btn btn-danger',
        'action' => \Builders\FormBuilder::deleteAction(),
        'validate' => false,
        'confirm' => 'Are you sure?',
        'showIf' => ['id', 'not_empty', 0]
    ],
    'cancel' => [
        'label' => 'Cancel',
        'type' => 'link',
        'class' => 'btn btn-secondary',
        'link' => '?page=posts'
    ]
])</code></pre>

    <h3>Custom Callback</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addActions([
    'publish' => [
        'label' => 'Publish',
        'class' => 'btn btn-success',
        'action' => function($fb, $request) {
            $result = $fb->save($request);
            if ($result['success']) {
                $fb->getModel()->update(['status' => 'published']);
            }
            return $result;
        },
        'showIf' => ['status', '=', 'draft']
    ]
])</code></pre>

    <h3>Link with Target</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addActions([
    'preview' => [
        'label' => 'Preview',
        'type' => 'link',
        'class' => 'btn btn-outline-primary',
        'link' => '?page=posts&action=view&id=' . $id,
        'target' => '_blank'
    ]
])</code></pre>

    <h3>Button with Custom Attributes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addActions([
    'export' => [
        'label' => 'Export',
        'type' => 'button',
        'class' => 'btn btn-info',
        'attributes' => [
            'data-action' => 'export',
            'data-format' => 'csv',
            'title' => 'Export to CSV'
        ]
    ]
])</code></pre>

</div>
