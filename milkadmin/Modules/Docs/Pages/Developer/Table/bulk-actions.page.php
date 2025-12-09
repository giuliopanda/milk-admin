<?php
namespace Modules\Docs\Pages;
/**
 * @title Bulk Actions
 * @guide developer
 * @order 20
 * @tags TableBuilder, bulk-actions, checkboxes, batch-operations, single-mode, batch-mode, showIfFilter
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Bulk Actions Documentation</h1>
    <p class="text-muted">Revision: 2025/12/02</p>
    <h2>Overview</h2>

    <div class="alert alert-info">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Actions vs Bulk Actions</h5>
        <ul class="mb-0">
            <li><strong>Row Actions:</strong> Actions column at the end of each table row for single record operations (edit, delete, view)</li>
            <li><strong>Bulk Actions:</strong> Actions that appear when selecting multiple rows via checkboxes (bulk delete, export, update status)</li>
            <li><strong>Checkboxes:</strong> Automatically appear when you define bulk actions using <code>setBulkActions()</code></li>
        </ul>
    </div>

    <h2>Basic Setup</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use App\Abstracts\AbstractController;
use Builders\TableBuilder;

class PostsController extends AbstractController {

    #[RequestAction('home')]
    public function postsList() {
        $response = ['page' => $this->page, 'title' => $this->title];

        $tableBuilder = TableBuilder::create($this->model, 'idTablePosts')
            ->setBulkActions([
                'delete' => [
                    'label' => 'Delete Selected',
                    'action' => [$this, 'actionBulkDelete']
                ]
            ]);

        $response = array_merge($response, $tableBuilder->getResponse());
        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

    public function actionBulkDelete($record, $request) {
        if ($record->delete($record->id)) {
            return ['success' => true, 'msg' => 'Deleted successfully'];
        }
        return ['success' => false, 'msg' => 'Delete failed'];
    }
}</code></pre>

    <div class="alert alert-warning">
        <strong>Important:</strong> Always use <code>getResponse()</code> instead of <code>render()</code> when using bulk actions or row actions with callbacks. This ensures action return values are properly merged with the table response.
    </div>

    <h2>Configuration Methods</h2>

    <div class="alert alert-info mb-3">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> addBulkAction() vs setBulkActions()</h5>
        <ul class="mb-0">
            <li><strong>addBulkAction($key, $action_data):</strong> Adds a single bulk action without modifying existing ones</li>
            <li><strong>setBulkActions($actions):</strong> Replaces all bulk actions with the provided set (uses addBulkAction() internally)</li>
        </ul>
    </div>

    <h3>setBulkActions() - Set All Actions</h3>
    <p>Replaces all bulk actions with a new set:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->setBulkActions([
    'delete' => [
        'label' => 'Delete',
        'action' => [$this, 'actionDelete']
    ],
    'export' => [
        'label' => 'Export',
        'action' => [$this, 'actionExport'],
        'mode' => 'batch',
        'updateTable' => false
    ]
])</code></pre>

    <h3>addBulkAction() - Add Single Action</h3>
    <p>Add one action at a time without replacing existing ones:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addBulkAction('archive', [
    'label' => 'Archive',
    'action' => [$this, 'actionArchive']
])

// Chain multiple actions
->addBulkAction('publish', ['label' => 'Publish', 'action' => [$this, 'actionPublish']])
->addBulkAction('unpublish', ['label' => 'Unpublish', 'action' => [$this, 'actionUnpublish']])</code></pre>

    <h3>getBulkActions() - Inspect Configured Actions</h3>
    <p>Retrieve the list of all configured bulk actions:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$tableBuilder = TableBuilder::create($model, 'idTablePosts')
    ->addBulkAction('delete', ['label' => 'Delete', 'action' => [$this, 'actionDelete']])
    ->addBulkAction('export', ['label' => 'Export', 'action' => [$this, 'actionExport']]);

// Get all configured bulk actions
$bulkActions = $tableBuilder->getBulkActions();
// Returns: ['delete' => ['label' => 'Delete', 'action' => ...], 'export' => [...]]</code></pre>

    <h2>Configuration Options</h2>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
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
                <td>required</td>
                <td>Action name shown in dropdown</td>
            </tr>
            <tr>
                <td><code>action</code></td>
                <td>callable</td>
                <td>required</td>
                <td>Function to execute: <code>[$this, 'methodName']</code></td>
            </tr>
            <tr>
                <td><code>mode</code></td>
                <td>string</td>
                <td>'single'</td>
                <td><code>'single'</code> = called once per record<br><code>'batch'</code> = called once with all records</td>
            </tr>
            <tr>
                <td><code>updateTable</code></td>
                <td>bool</td>
                <td>true</td>
                <td>Whether to reload table after execution</td>
            </tr>
            <tr>
                <td><code>showIfFilter</code></td>
                <td>array</td>
                <td>null</td>
                <td>Conditional visibility based on active filters</td>
            </tr>
        </tbody>
    </table>

    <h2>Action Modes</h2>

    <h3>Single Mode (Default)</h3>
    <p>The action function is called <strong>once for each selected record</strong> individually:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">'delete' => [
    'label' => 'Delete',
    'action' => [$this, 'actionDelete']
    // mode defaults to 'single'
]

public function actionDelete($record, $request) {
    // Called once per selected record
    // $record is a single Model instance
    if ($record->delete($record->id)) {
        return ['success' => true, 'msg' => 'Deleted'];
    }
    return ['success' => false, 'msg' => 'Failed'];
}</code></pre>

    <h3>Batch Mode</h3>
    <p>The action function is called <strong>once with all selected records</strong> together:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">'export' => [
    'label' => 'Export',
    'action' => [$this, 'actionExport'],
    'mode' => 'batch',
    'updateTable' => false
]

public function actionExport($records, $request) {
    // Called once with all records
    // $records is a collection of Model instances
    $records->setFormatted();

    $csv = '';
    foreach ($records as $record) {
        $csv .= "{$record->id},{$record->title},{$record->status}\n";
    }

    return [
        'success' => true,
        'offcanvas_end' => [
            'title' => 'Export Results',
            'body' => '<pre>' . $csv . '</pre>'
        ]
    ];
}</code></pre>

    <h2>Action Method Signature</h2>

    <h3>Single Mode</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function actionMethodName($record, $request) {
    // $record - Single Model instance of selected row
    // $request - Full $_REQUEST array

    // Perform operation on $record

    // Return array with success and msg
    return ['success' => true, 'msg' => 'Operation completed'];
}</code></pre>

    <h3>Batch Mode</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function actionMethodName($records, $request) {
    // $records - Collection of Model instances
    // $request - Full $_REQUEST array

    // Perform operation on all $records

    // Return array with success and msg
    return ['success' => true, 'msg' => 'Batch operation completed'];
}</code></pre>

    <h2>Return Values</h2>

    <p>Action methods must return an associative array. Available keys:</p>

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
                <td>bool</td>
                <td>Whether the operation succeeded</td>
            </tr>
            <tr>
                <td><code>msg</code></td>
                <td>string</td>
                <td>Feedback message to display (note: <code>msg</code> not <code>message</code>)</td>
            </tr>
            <tr>
                <td><code>reload</code></td>
                <td>bool</td>
                <td>Force table reload (overrides <code>updateTable</code> setting)</td>
            </tr>
            <tr>
                <td><code>offcanvas_end</code></td>
                <td>array</td>
                <td>Opens offcanvas panel with content: <code>['title' => '...', 'body' => '...', 'size' => 'xl']</code></td>
            </tr>
            <tr>
                <td><code>modal</code></td>
                <td>array</td>
                <td>Opens modal dialog: <code>['title' => '...', 'body' => '...', 'footer' => '...']</code></td>
            </tr>
            <tr>
                <td><code>redirect</code></td>
                <td>string</td>
                <td>URL to redirect to after operation</td>
            </tr>
        </tbody>
    </table>

    <h2>Practical Examples</h2>

    <h3>Status Update</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->setBulkActions([
    'publish' => [
        'label' => 'Publish',
        'action' => [$this, 'actionPublish']
    ]
])

public function actionPublish($record, $request) {
    $record->status = 'published';
    $record->published_at = date('Y-m-d H:i:s');
    if ($record->save()) {
        return ['success' => true, 'msg' => 'Published'];
    }
    return ['success' => false, 'msg' => 'Publish failed'];
}</code></pre>

    <h3>Comparison (Batch Mode with Offcanvas)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->setBulkActions([
    'compare' => [
        'label' => 'Compare',
        'action' => [$this, 'actionCompare'],
        'mode' => 'batch',
        'updateTable' => false
    ]
])

public function actionCompare($records, $request) {
    $records->setFormatted();
    $fields = ['title', 'status', 'created_at'];

    $html = '<table class="table table-bordered"><tbody>';
    foreach ($fields as $field) {
        $html .= '<tr><th>' . $field . '</th>';
        foreach ($records as $record) {
            $html .= '<td>' . ($record->$field ?? '-') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    return [
        'success' => true,
        'offcanvas_end' => [
            'title' => 'Comparison',
            'body' => $html,
            'size' => 'xl'
        ]
    ];
}</code></pre>

    <h3>Using Built-in Delete Action</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// You can use the built-in actionDeleteRow method:
->setBulkActions([
    'delete' => [
        'label' => 'Delete',
        'action' => [$tableBuilder, 'actionDeleteRow']
    ]
])

// Or for default row actions with delete:
->setDefaultActions() // Automatically includes edit and delete actions</code></pre>

    <h2>Conditional Visibility with showIfFilter</h2>

    <p>Show or hide bulk actions based on active table filters. Perfect for soft-delete patterns or status-based workflows.</p>

    <h3>Example: Soft Delete Pattern</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Create status filter in SearchBuilder
$searchBuilder = \Builders\SearchBuilder::create('idTablePosts')
    ->addActionList('status', 'Status:', [
        'active' => 'Active',
        'deleted' => 'Deleted'
    ], 'active');

// Configure table with conditional bulk actions
$tableBuilder = TableBuilder::create($postModel, 'idTablePosts')
    ->filter('status', function($query, $value) {
        if ($value === 'deleted') {
            $query->where('deleted_at IS NOT NULL');
        } else {
            $query->where('deleted_at IS NULL');
        }
    }, 'active')

    ->setBulkActions([
        'soft_delete' => [
            'label' => 'Move to Trash',
            'action' => [$this, 'actionSoftDelete'],
            'showIfFilter' => ['status' => 'active']  // Only when viewing active
        ],
        'hard_delete' => [
            'label' => 'Delete Permanently',
            'action' => [$this, 'actionHardDelete'],
            'showIfFilter' => ['status' => 'deleted']  // Only when viewing deleted
        ],
        'restore' => [
            'label' => 'Restore',
            'action' => [$this, 'actionRestore'],
            'showIfFilter' => ['status' => 'deleted']  // Only when viewing deleted
        ]
    ]);

public function actionSoftDelete($record, $request) {
    $record->deleted_at = date('Y-m-d H:i:s');
    if ($record->save()) {
        return ['success' => true, 'msg' => 'Moved to trash'];
    }
    return ['success' => false, 'msg' => 'Failed'];
}

public function actionHardDelete($record, $request) {
    if ($record->deleted_at !== null) {
        if ($record->delete($record->id)) {
            return ['success' => true, 'msg' => 'Permanently deleted'];
        }
    }
    return ['success' => false, 'msg' => 'Cannot delete active records'];
}

public function actionRestore($record, $request) {
    $record->deleted_at = null;
    if ($record->save()) {
        return ['success' => true, 'msg' => 'Restored'];
    }
    return ['success' => false, 'msg' => 'Restore failed'];
}</code></pre>

    <div class="alert alert-info">
        <h5 class="alert-heading"><i class="bi bi-lightbulb"></i> How It Works</h5>
        <p><strong>When filter is 'active':</strong> Users see only "Move to Trash"</p>
        <p><strong>When filter is 'deleted':</strong> Users see "Delete Permanently" and "Restore"</p>
        <p class="mb-0"><strong>Dynamic UI:</strong> The dropdown updates automatically when filters change</p>
    </div>

    <h2>JavaScript Hooks</h2>

    <p>Intercept bulk actions on the client side for confirmations, custom downloads, or preventing default behavior:</p>

    <h3>Hook Syntax</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">registerHook('table-action-{action_name}', function(ids, elclick, form, sendform) {
    // ids: array of selected record IDs
    // elclick: clicked button element
    // form: table form element
    // sendform: always true (legacy)

    return true;   // Proceed with server request
    return false;  // Cancel request
});</code></pre>

    <h3>Example: Confirmation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">registerHook('table-action-delete', function(ids, elclick, form, sendform) {
    return confirm('Delete ' + ids.length + ' items? This cannot be undone.');
});</code></pre>

    <h3>Example: Custom Download</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">registerHook('table-action-download', function(ids, elclick, form, sendform) {
    // Create download form
    const downloadForm = document.createElement('form');
    downloadForm.method = 'POST';
    downloadForm.action = milk_url;
    downloadForm.style.display = 'none';

    const input = document.createElement('input');
    input.name = 'ids';
    input.value = ids.join(',');
    downloadForm.appendChild(input);

    document.body.appendChild(downloadForm);
    downloadForm.submit();
    document.body.removeChild(downloadForm);

    return false; // Don't send default request
});</code></pre>

    <h2>Working with Links</h2>

    <p>While bulk actions typically use callbacks, you can also create link-based actions with fetch:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Row action with link and fetch
->setActions([
    'restore' => [
        'label' => 'Restore',
        'link' => '?page=posts&action=restore&id=%id%',
        'fetch' => 'post'  // Send link as POST via fetch
    ]
])

// Or use activeFetch() to enable fetch mode for all links
->activeFetch()
->setActions([
    'restore' => [
        'label' => 'Restore',
        'link' => '?page=posts&action=restore&id=%id%'
        // fetch: 'post' is added automatically
    ]
])</code></pre>

    <h2>Best Practices</h2>

    <ul>
        <li><strong>Mode Selection:</strong> Use 'single' for operations on individual records, 'batch' for operations requiring all records at once</li>
        <li><strong>Table Updates:</strong> Set <code>updateTable => false</code> for actions that open offcanvas/modal or don't modify data</li>
        <li><strong>Validation:</strong> Always validate operations in action handlers to prevent misuse</li>
        <li><strong>Error Handling:</strong> Return detailed error messages in the <code>msg</code> field for better user feedback</li>
        <li><strong>Built-in Actions:</strong> Use <code>[$tableBuilder, 'actionDeleteRow']</code> for standard delete operations</li>
        <li><strong>Conditional Actions:</strong> Use <code>showIfFilter</code> to show/hide actions based on active filters (e.g., soft-delete patterns)</li>
    </ul>

    <h2>Common Pitfalls</h2>

    <div class="alert alert-danger">
        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Common Mistakes</h5>
        <ul class="mb-0">
            <li><strong>Using 'message' instead of 'msg':</strong> The return key is <code>'msg'</code> not <code>'message'</code></li>
            <li><strong>Using render() instead of getResponse():</strong> Action returns won't be merged with the table response</li>
            <li><strong>Confusing modes:</strong> 'single' mode receives <code>$record</code> (singular), 'batch' receives <code>$records</code> (collection)</li>
            <li><strong>Wrong addBulkAction() signature:</strong> Use <code>addBulkAction($key, $action_data)</code>, not <code>addBulkAction($action_data)</code></li>
        </ul>
    </div>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Table/row-actions">Row Actions</a> - Single record actions in the actions column</li>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder Overview</a> - Main table builder documentation</li>
        <li><a href="?page=docs&action=Developer/Table/search-filters">Search & Filters</a> - Creating filters for showIfFilter</li>
        <li><a href="?page=docs&action=Framework/Theme/theme-offcanvas">Offcanvas Component</a> - Offcanvas panel documentation</li>
    </ul>
</div>
