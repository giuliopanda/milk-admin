<?php
namespace Modules\Docs\Pages;
/**
 * @title Row Actions
 * @guide developer
 * @order 15
 * @tags TableBuilder, row-actions, links, callbacks, conditional-visibility, filters, setActions, actionDelete, Link Actions, Callback Actions, confirm, class, target, filter, showIfFilter
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Row Actions Documentation</h1>

    <p>The <code>setActions()</code> method configures action buttons that appear for each row in your table. Actions can be simple navigation links or complex server-side operations with callbacks.</p>

    <h2>Action Types</h2>

    <h3>Link Actions</h3>
    <p>Link actions create clickable buttons that navigate to another page. Perfect for Edit, View, or Detail operations.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'table_id')
    ->setActions([
        'edit' => [
            'label' => 'Edit',
            'link' => '?page=posts&action=edit&id=%id%'
        ],
        'view' => [
            'label' => 'View Details',
            'link' => '?page=posts&action=view&id=%id%',
            'target' => '_blank',
        ]
    ]);</code></pre>

    <h3>Callback Actions</h3>
    <p>Callback actions execute server-side functions when clicked. Ideal for operations like Delete, Publish, or Archive.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'table_id')
    ->setActions([
        'delete' => [
            'label' => 'Delete',
            'action' => [$this, 'actionDelete'],
            'confirm' => 'Are you sure you want to delete this item?',
            'class' => 'link-action-danger'
        ],
        'publish' => [
            'label' => 'Publish Now',
            'action' => [$this, 'actionPublish'],
        ]
    ])
    ->getResponse();

// Action handler method
public function actionDelete($record, $request) {
    if ($record->delete($record->id)) {
        return ['success' => true, 'message' => 'Item deleted successfully'];
    }
    return ['success' => false, 'message' => 'Delete failed'];
}</code></pre>

    <h2>Configuration Parameters</h2>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Parameter</th>
                <th>Type</th>
                <th>Required</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>label</code></td>
                <td>string</td>
                <td>Yes</td>
                <td>Button text displayed to user</td>
            </tr>
            <tr>
                <td><code>link</code></td>
                <td>string</td>
                <td>No*</td>
                <td>URL pattern with placeholders (e.g., <code>%id%</code>, <code>%field_name%</code>)</td>
            </tr>
            <tr>
                <td><code>action</code></td>
                <td>callable</td>
                <td>No*</td>
                <td>Callback function: <code>[$this, 'methodName']</code></td>
            </tr>
            <tr>
                <td><code>class</code></td>
                <td>string</td>
                <td>No</td>
                <td>CSS classes for styling (Bootstrap classes supported) link-action-danger for danger link</td>
            </tr>
            <tr>
                <td><code>target</code></td>
                <td>string</td>
                <td>No</td>
                <td>Link target: <code>_blank</code></td>
            </tr>
            <tr>
                <td><code>confirm</code></td>
                <td>string</td>
                <td>No</td>
                <td>Confirmation message shown before action execution</td>
            </tr>
            <tr>
                <td><code>showIfFilter</code></td>
                <td>array</td>
                <td>No</td>
                <td>Conditional visibility based on active filters (see below)</td>
            </tr>
        </tbody>
    </table>

    <p><small>* Either <code>link</code> or <code>action</code> must be specified</small></p>

    <h2>URL Placeholders</h2>

    <p>Link actions support dynamic placeholders that are replaced with actual row data:</p>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Placeholder</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>%id%</code></td>
                <td>Primary key value</td>
                <td><code>?page=posts&id=%id%</code> → <code>?page=posts&id=123</code></td>
            </tr>
            <tr>
                <td><code>%field_name%</code></td>
                <td>Any column value</td>
                <td><code>/view/%slug%</code> → <code>/view/my-post-slug</code></td>
            </tr>
            <tr>
                <td><code>%primary%</code></td>
                <td>Explicit primary key reference</td>
                <td><code>?action=edit&pk=%primary%</code></td>
            </tr>
        </tbody>
    </table>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Examples with multiple placeholders
->setActions([
    'edit' => [
        'label' => 'Edit',
        'link' => '?page=%category%&action=edit&id=%id%'
    ],
    'preview' => [
        'label' => 'Preview',
        'link' => '/blog/%created_at%/%slug%',
        'target' => '_blank'
    ]
])</code></pre>

    <h2>Action Callbacks</h2>

    <h3>Callback Signature</h3>
    <p>Action callback functions receive two parameters:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function actionMethodName($record, $request) {
    // $record - Model instance of the selected row
    // $request - Full $_REQUEST array with all parameters

    // Perform your operation

    // Return array with status and optional data
    return ['success' => true, 'message' => 'Operation completed'];
}</code></pre>

    <h3>Return Values</h3>
    <p>Callback functions must return an associative array. Common keys:</p>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Key</th>
                <th>Type</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>success</code></td>
                <td>boolean</td>
                <td>Whether the operation succeeded</td>
            </tr>
            <tr>
                <td><code>message</code></td>
                <td>string</td>
                <td>User feedback message</td>
            </tr>
            <tr>
                <td><code>reload</code></td>
                <td>boolean</td>
                <td>Whether to reload the table (default: true)</td>
            </tr>
            <tr>
                <td><code>redirect</code></td>
                <td>string</td>
                <td>URL to redirect after completion</td>
            </tr>
            <tr>
                <td>Custom keys</td>
                <td>mixed</td>
                <td>Any additional data to pass to the view</td>
            </tr>
        </tbody>
    </table>

    <h3>Example Callbacks</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Simple delete action
public function actionDelete($record, $request) {
    if ($record->delete($record->id)) {
        return [
            'success' => true,
            'message' => 'Item deleted successfully'
        ];
    }
    return [
        'success' => false,
        'message' => 'Delete failed: ' . $record->getLastError()
    ];
}

// Status change action
public function actionPublish($record, $request) {
    $record->status = 'published';
    $record->published_at = date('Y-m-d H:i:s');

    if ($record->save()) {
        return [
            'success' => true,
            'message' => 'Post published successfully',
            'reload' => true
        ];
    }

    return [
        'success' => false,
        'message' => 'Publish failed',
        'errors' => $record->getErrors()
    ];
}

// Action with redirect
public function actionClone($record, $request) {
    $record->id = 0;

    if ($record->save()) {
        return [
            'success' => true,
            'message' => 'Item cloned successfully',
            'redirect' => '?page=items&action=edit&id=' . $record->id
        ];
    }

    return ['success' => false, 'message' => 'Clone failed'];
}</code></pre>

    <h2>Conditional Visibility with showIfFilter</h2>

    <p>The <code>showIfFilter</code> parameter creates context-aware interfaces by showing or hiding actions based on active table filters.</p>

    <h3>Basic Concept</h3>
    <p>Actions configured with <code>showIfFilter</code> are only visible when specific filter conditions are met. This keeps the UI clean and prevents users from performing inappropriate actions.</p>

    <h3>Syntax</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">'showIfFilter' => ['filter_name' => 'expected_value']</code></pre>

    <p><strong>Behavior:</strong></p>
    <ul>
        <li>Action is visible only when the specified filter is active</li>
        <li>Filter value must exactly match the expected value</li>
        <li>Currently supports single filter condition per action</li>
        <li>Works seamlessly with table filter changes (real-time update)</li>
    </ul>

    <h3>Practical Example: Status-Based Actions</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Setup filter in SearchBuilder
$searchBuilder = \Builders\SearchBuilder::create('idTablePosts')
    ->addActionList('status', 'Filter by Status:', [
        'active' => 'Active Posts',
        'deleted' => 'Deleted Posts'
    ], 'active');

// Configure table with conditional actions
$tableBuilder = \Builders\TableBuilder::create($postModel, 'idTablePosts')
    ->filter('status', function($query, $value) {
        if ($value === 'deleted') {
            $query->where('deleted_at IS NOT NULL');
        } else {
            $query->where('deleted_at IS NULL');
        }
    }, 'active')

    ->setActions([
        // Always visible
        'edit' => [
            'label' => 'Edit',
            'link' => '?page=posts&action=edit&id=%id%'
        ],

        // Only visible when viewing active posts
        'delete' => [
            'label' => 'Move to Trash',
            'action' => [$this, 'actionSoftDelete'],
            'confirm' => 'Move this post to trash?',
            'class' => 'link-action-warning',
            'showIfFilter' => ['status' => 'active']
        ],

        // Only visible when viewing deleted posts
        'restore' => [
            'label' => 'Restore',
            'action' => [$this, 'actionRestore'],
            'class' => 'link-action-success',
            'showIfFilter' => ['status' => 'deleted']
        ]
    ]);</code></pre>

    <div class="alert alert-info mt-3">
        <h5 class="alert-heading"><i class="bi bi-lightbulb"></i> How it works</h5>
        <p><strong>When viewing Active Posts:</strong> Users see "Edit" and "Move to Trash"</p>
        <p><strong>When viewing Deleted Posts:</strong> Users see "Edit" and "Restore"</p>
        <p class="mb-0"><strong>Filter Change:</strong> Actions update instantly when filter is changed</p>
    </div>

    <h3>Implementation of Action Handlers</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function actionSoftDelete($record, $request) {
    $record->deleted_at = date('Y-m-d H:i:s');

    if ($record->save()) {
        return ['success' => true, 'message' => 'Post moved to trash'];
    }
    return ['success' => false, 'message' => 'Failed to move to trash'];
}

public function actionRestore($record, $request) {
    $record->deleted_at = null;

    if ($record->save()) {
        return ['success' => true, 'message' => 'Post restored successfully'];
    }
    return ['success' => false, 'message' => 'Restore failed'];
}</code></pre>


    <h2>Default Actions Helper</h2>

    <p>For common CRUD operations, use <code>setDefaultActions()</code> to automatically generate Edit and Delete actions:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Simple default actions
$table = \Builders\TableBuilder::create($model, 'table_id')
    ->setDefaultActions();

// Generates:
// - Edit: links to ?page=current_page&action=edit&id=%id%
// - Delete: calls built-in delete handler with confirmation

// With custom page
$table = \Builders\TableBuilder::create($model, 'table_id')
    ->setPage('posts')
    ->setDefaultActions();

// Add additional actions
$table = \Builders\TableBuilder::create($model, 'table_id')
    ->setDefaultActions([
        'view' => [
            'label' => 'View',
            'link' => '?page=posts&action=view&id=%id%',
            'target' => '_blank'
        ]
    ]);</code></pre>

    <h2>Opening Offcanvas from Row Actions</h2>

    <p>You can open an offcanvas sidebar from a row action <strong>without writing any JavaScript</strong>. Simply return an <code>offcanvas_end</code> array in your action callback.</p>

    <div class="alert alert-success">
        <h5 class="alert-heading"><i class="bi bi-lightbulb"></i> No JavaScript Required!</h5>
        <p class="mb-0">The framework automatically handles opening the offcanvas when your callback returns the special <code>offcanvas_end</code> key. This works for both row actions and bulk actions.</p>
    </div>

    <h3>Basic Example: View Details in Offcanvas</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Controller
class TasksController extends AbstractController {

    #[RequestAction('home')]
    public function tasksList() {
        $response = TableBuilder::create($this->model, 'tasks_table')
            ->setActions([
                'view' => [
                    'label' => 'View Details',
                    'action' => [$this, 'actionViewDetails'],
                    'class' => 'btn btn-sm btn-info'
                ],
                'edit' => [
                    'label' => 'Edit',
                    'link' => '?page=tasks&action=edit&id=%id%'
                ]
            ])
            ->getResponse(); // IMPORTANT: Use getResponse() with action callbacks!

        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

    // Action callback - opens offcanvas automatically
    public function actionViewDetails($record, $request) {
        // Build HTML content for offcanvas
        $html = '
        <div class="card">
            <div class="card-body">
                <h5>' . _esc_html($record->title) . '</h5>
                <p><strong>Status:</strong> ' . $record->status . '</p>
                <p><strong>Priority:</strong> ' . $record->priority . '</p>
                <div class="mt-3">
                    <p>' . $record->description . '</p>
                </div>
            </div>
        </div>';

        // Return with offcanvas_end key
        return [
            'success' => true,
            'offcanvas_end' => [
                'title' => 'Task Details',
                'body' => $html,
                'size' => '' // Optional: 'xl' for extra large
            ]
        ];
    }
}</code></pre>

    <h3>Offcanvas Configuration Options</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Key</th>
                <th>Type</th>
                <th>Required</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>title</code></td>
                <td>string</td>
                <td>Yes</td>
                <td>Offcanvas header title</td>
            </tr>
            <tr>
                <td><code>body</code></td>
                <td>string (HTML)</td>
                <td>Yes</td>
                <td>HTML content to display in offcanvas body</td>
            </tr>
            <tr>
                <td><code>size</code></td>
                <td>string</td>
                <td>No</td>
                <td>Offcanvas width: <code>''</code> (default) or <code>'xl'</code> (extra large)</td>
            </tr>
        </tbody>
    </table>

    <h3>Advanced Example: Edit Form in Offcanvas</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Controller
public function actionQuickEdit($record, $request) {
    // Get record for editing
    $data = $this->model->getByIdForEdit($record->id);

    // Build form using FormBuilder
    $form = FormBuilder::create($this->model)
        ->addFieldsFromObject($data, 'edit')
        ->removeField('created_at')
        ->removeField('updated_at')
        ->setActions([
            'save' => [
                'label' => 'Save',
                'class' => 'btn btn-primary',
                'action' => FormBuilder::saveAction('?page='.$this->page)
            ],
            'cancel' => [
                'label' => 'Cancel',
                'type' => 'link',
                'class' => 'btn btn-secondary',
                'link' => 'javascript:window.offcanvasEnd.hide()'
            ]
        ])
        ->getForm();

    return [
        'success' => true,
        'offcanvas_end' => [
            'title' => 'Quick Edit: ' . $record->title,
            'body' => $form,
            'size' => 'xl' // Larger size for forms
        ]
    ];
}</code></pre>

    <h3>Combining with Other Actions</h3>
    <p>You can mix offcanvas actions with regular actions and links:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->setActions([
    'view' => [
        'label' => 'Quick View',
        'action' => [$this, 'actionViewInOffcanvas']
        // Opens offcanvas when callback returns offcanvas_end
    ],
    'edit' => [
        'label' => 'Full Edit',
        'link' => '?page=tasks&action=edit&id=%id%'
        // Regular page navigation
    ],
    'delete' => [
        'label' => 'Delete',
        'action' => [$this, 'actionDelete'],
        'confirm' => 'Are you sure?'
        // Regular callback with reload
    ]
])</code></pre>

    <div class="alert alert-info">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> How It Works</h5>
        <p>When a row action callback returns an array containing <code>offcanvas_end</code>, the framework:</p>
        <ol class="mb-0">
            <li>Automatically calls <code>window.offcanvasEnd.show()</code></li>
            <li>Sets the title using <code>window.offcanvasEnd.title()</code></li>
            <li>Sets the body HTML using <code>window.offcanvasEnd.body()</code></li>
            <li>Optionally sets the size using <code>window.offcanvasEnd.size()</code></li>
        </ol>
        <p class="mt-2 mb-0"><strong>No custom JavaScript needed!</strong> The table component handles everything automatically.</p>
    </div>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder Overview</a> - Main table builder documentation</li>
        <li><a href="?page=docs&action=Developer/Table/bulk-actions">Bulk Actions</a> - Working with multiple rows at once (includes more offcanvas examples)</li>
        <li><a href="?page=docs&action=Framework/Theme/theme-offcanvas">Offcanvas Component</a> - Manual offcanvas control with JavaScript</li>
        <li><a href="?page=docs&action=Developer/Table/search-builder">SearchBuilder</a> - Creating filters and search interfaces</li>
    </ul>
</div>
