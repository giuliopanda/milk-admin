<?php
namespace Modules\Docs\Pages;
/**
 * @title Bulk Actions
 * @guide developer
 * @order 20
 * @tags TableBuilder, bulk-actions, checkboxes, batch-operations, fetch, ajax, offcanvas
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Bulk Actions Documentation</h1>

    <p>The <code>setBulkActions()</code> method enables checkboxes in tables and allows executing actions on multiple selected rows. All operations are handled via fetch without page reload.</p>

    <h2>Basic Usage</h2>

    <h3>Enabling Checkboxes</h3>
    <p>Simply calling <code>setBulkActions()</code> automatically adds checkboxes to the table. When rows are selected, the bulk action dropdown appears.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'table_id')
    ->setBulkActions([
        'action_name' => [
            'label' => 'Action Label',
            'action' => [$this, 'methodName']
        ]
    ])
    ->getResponse();</code></pre>

    <h2>Action Modes</h2>

    <h3>Standard Mode (Default)</h3>
    <p>The action function is called once for each selected record individually. The table is automatically reloaded after execution.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">'soft_delete' => [
    'label' => 'Soft Delete',
    'action' => [$this, 'actionSoftDelete']
    // mode defaults to 'single'
    // updateTable defaults to true
]

public function actionSoftDelete($record, $request) {
    $record->deleted_at = date('Y-m-d H:i:s');
    if ($record->save()) {
        return ['success' => true, 'message' => 'Soft deleted successfully'];
    }
    return ['success' => false, 'message' => 'Soft delete failed'];
}</code></pre>

    <h3>Batch Mode</h3>
    <p>Set <code>'mode' => 'batch'</code> to receive all selected records at once in a single call. Useful for comparisons or operations requiring all records together.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">'confronto' => [
    'label' => 'Comparison',
    'action' => [$this, 'actionComparison'],
    'mode' => 'batch',          // Receives all records at once
    'updateTable' => false       // Don't reload the table
]

public function actionComparison($records, $request) {
    $records->setFormatted(); // Format records data
    $fields = ['title', 'category', 'created_at', 'updated_at', 'deleted_at', 'content'];
    $rows = '';

    foreach ($fields as $field) {
        $rows .= '<tr><th>' . $field . '</th>';
        foreach ($records as $record) {
            $rows .= '<td>' . ($record->$field ?? '-') . '</td>';
        }
        $rows .= '</tr>';
    }

    return [
        'success' => true,
        'offcanvas_end' => [
            'title' => 'Confronto',
            'body' => '<table class="table table-bordered"><tbody>' . $rows . '</tbody></table>',
            'size' => 'xl'
        ]
    ];
}</code></pre>

    <h2>Configuration Parameters</h2>

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
                <td><code>'single'</code> = one call per record<br><code>'batch'</code> = one call with all records</td>
            </tr>
            <tr>
                <td><code>updateTable</code></td>
                <td>bool</td>
                <td>true</td>
                <td>Whether to reload the table after execution</td>
            </tr>
            <tr>
                <td><code>showIfFilter</code></td>
                <td>array</td>
                <td>null</td>
                <td>Conditional visibility based on active filters (see below)</td>
            </tr>
        </tbody>
    </table>

    <h2>Return Values</h2>

    <p>Action functions must return an array. Common return parameters:</p>

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
                <td>Operation success status</td>
            </tr>
            <tr>
                <td><code>message</code></td>
                <td>string</td>
                <td>Feedback message to display</td>
            </tr>
            <tr>
                <td><code>reload</code></td>
                <td>bool</td>
                <td>Force table reload (overrides <code>updateTable</code>)</td>
            </tr>
            <tr>
                <td><code>offcanvas_end</code></td>
                <td>array</td>
                <td>Opens offcanvas panel with content (see below)</td>
            </tr>
            <tr>
                <td><code>redirect</code></td>
                <td>string</td>
                <td>URL to redirect to</td>
            </tr>
        </tbody>
    </table>

    <h3>Offcanvas Response</h3>
    <p>Display results in a sliding panel from the right side:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">return [
    'success' => true,
    'offcanvas_end' => [
        'title' => 'Panel Title',
        'body' => '<div>HTML content here</div>',
        'size' => 'xl'  // 'sm', 'md', 'lg', 'xl'
    ]
];</code></pre>

    <h3>Modal Response</h3>
    <p>Display results in a centered modal dialog:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">return [
    'success' => true,
    'modal' => [
        'title' => 'Modal Title',
        'body' => '<div>Modal content here</div>',
        'footer' => '<button class="btn btn-primary">OK</button>' // Optional
    ]
];</code></pre>

    <h2>Conditional Visibility with showIfFilter</h2>

    <p>The <code>showIfFilter</code> parameter allows you to conditionally display bulk actions based on the currently active table filters. This creates a context-aware interface that only shows relevant actions.</p>

    <h3>Basic Concept</h3>
    <p>When a user applies filters to a table, you can configure bulk actions to appear or disappear based on those filter values. This is particularly useful for implementing soft-delete patterns or status-based operations.</p>

    <h3>Syntax</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">'showIfFilter' => ['filter_name' => 'filter_value']</code></pre>

    <p><strong>Important:</strong> The action will only be visible when:</p>
    <ul>
        <li>The specified filter is active</li>
        <li>The filter value exactly matches the specified value</li>
        <li>Currently supports single filter condition (one key-value pair)</li>
    </ul>

    <h3>Practical Example: Soft Delete Pattern</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Create a status filter with default value 'active'
$searchBuilder = \Builders\SearchBuilder::create('idTablePosts')
    ->addActionList('status', 'Status:', [
        'active' => 'Active Posts',
        'deleted' => 'Deleted Posts'
    ], 'active')
    ->addSearch();

$tableBuilder = \Builders\TableBuilder::create($postModel, 'idTablePosts')
    // Define the filter behavior
    ->filter('status', function($query, $value) {
        if ($value === 'deleted') {
            $query->where('deleted_at IS NOT NULL');
        } else {
            $query->where('deleted_at IS NULL');
        }
    }, 'active')  // Default filter value

    // Configure bulk actions with conditional visibility
    ->setBulkActions([
        'soft_delete' => [
            'label' => 'Move to Trash',
            'action' => [$this, 'actionSoftDelete'],
            'showIfFilter' => ['status' => 'active']  // Only when viewing active posts
        ],
        'hard_delete' => [
            'label' => 'Delete Permanently',
            'action' => [$this, 'actionHardDelete'],
            'showIfFilter' => ['status' => 'deleted']  // Only when viewing deleted posts
        ],
        'restore' => [
            'label' => 'Restore',
            'action' => [$this, 'actionRestore'],
            'showIfFilter' => ['status' => 'deleted']  // Only when viewing deleted posts
        ]
    ]);</code></pre>

    <h3>How It Works</h3>
    <ol>
        <li><strong>When filter is 'active':</strong> Users see only the "Move to Trash" bulk action</li>
        <li><strong>When filter is 'deleted':</strong> Users see "Delete Permanently" and "Restore" bulk actions</li>
        <li><strong>Dynamic UI:</strong> The dropdown automatically updates when filters change</li>
    </ol>

    <h3>Action Handler Implementation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function actionSoftDelete($record, $request) {
    $record->deleted_at = date('Y-m-d H:i:s');
    if ($record->save()) {
        return ['success' => true, 'message' => 'Moved to trash successfully'];
    }
    return ['success' => false, 'message' => 'Failed to move to trash'];
}

public function actionHardDelete($record, $request) {
    // Only allow hard delete if already soft deleted
    if ($record->deleted_at !== null) {
        if ($record->delete($record->id)) {
            return ['success' => true, 'message' => 'Permanently deleted'];
        }
    }
    return ['success' => false, 'message' => 'Cannot delete active records'];
}

public function actionRestore($record, $request) {
    $record->deleted_at = null;
    if ($record->save()) {
        return ['success' => true, 'message' => 'Restored successfully'];
    }
    return ['success' => false, 'message' => 'Restore failed'];
}</code></pre>

    <h3>Use Cases</h3>
    <ul>
        <li><strong>Soft Delete Systems:</strong> Different actions for active vs. deleted items</li>
        <li><strong>Status Workflows:</strong> Status-specific bulk operations (draft → publish, pending → approve)</li>
        <li><strong>Category Management:</strong> Category-specific batch operations</li>
        <li><strong>Permission-Based Actions:</strong> Combined with filters, show actions only for specific item states</li>
    </ul>

    <h3>Best Practices</h3>
    <ul>
        <li>Always set a default filter value to ensure predictable initial state</li>
        <li>Keep filter values simple and descriptive ('active', 'deleted', 'published')</li>
        <li>Provide clear action labels that indicate what will happen ("Move to Trash" vs. "Delete")</li>
        <li>Implement proper validation in action handlers to prevent misuse</li>
        <li>Use confirmation messages for destructive operations</li>
    </ul>

    <h2>JavaScript Hooks for Custom Actions</h2>

    <p>Use JavaScript hooks to intercept bulk actions and implement custom client-side behavior before the server request. This is useful for confirmations, custom downloads, redirects, or preventing default behavior.</p>

    <h3>Hook Syntax</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">registerHook('table-action-{action_name}', function(ids, elclick, form, sendform) {
    // Custom JavaScript logic

    return true;   // Proceed with fetch request and table update
    return false;  // Cancel request, no table update
});</code></pre>

    <h3>Hook Parameters</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Parameter</th>
                <th>Type</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>ids</code></td>
                <td>array|string</td>
                <td>Selected record IDs (array for bulk, string for single action)</td>
            </tr>
            <tr>
                <td><code>elclick</code></td>
                <td>HTMLElement</td>
                <td>The clicked action button element</td>
            </tr>
            <tr>
                <td><code>form</code></td>
                <td>HTMLFormElement</td>
                <td>The table form element</td>
            </tr>
            <tr>
                <td><code>sendform</code></td>
                <td>boolean</td>
                <td>Always <code>true</code> (legacy parameter)</td>
            </tr>
        </tbody>
    </table>

    <h3>Return Values</h3>
    <ul>
        <li><strong>true</strong>: Proceed with fetch request to server and update table</li>
        <li><strong>false</strong>: Cancel the request, table will not be updated</li>
    </ul>

    <h3>Example: Confirmation Dialog</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// Simple confirmation before delete
registerHook('table-action-delete', function(ids, elclick, form, sendform) {
    return confirm('Are you sure you want to delete this item? This action cannot be undone.');
});</code></pre>

    <h3>Example: Custom File Download</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// Trigger download without table refresh
registerHook('table-action-download', function(ids, elclick, form, sendform) {
    // Create temporary form for file download
    const downloadForm = document.createElement('form');
    downloadForm.method = 'POST';
    downloadForm.action = milk_url;
    downloadForm.style.display = 'none';

    // Add form fields
    const fields = {
        'ids': ids,
        'action': 'download-data',
        'page': 'posts'
    };

    Object.keys(fields).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        downloadForm.appendChild(input);
    });

    // Submit to trigger download
    document.body.appendChild(downloadForm);
    downloadForm.submit();
    document.body.removeChild(downloadForm);

    return false; // Don't send default fetch request
});</code></pre>

    <h3>Example: Custom Redirect</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// Redirect to custom edit page instead of default action
registerHook('table-action-edit-project', function(id, elclick, form, sendform) {
    window.location.href = milk_url + '?page=projects&action=edit&id=' + id;
    return false; // Prevent default table update
});</code></pre>

    <h3>Example: Custom UI with Offcanvas</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// Open offcanvas with custom form
registerHook('table-action-edit', function(id, elclick, form, sendform) {
    window.offcanvasEnd.show();
    window.offcanvasEnd.loading_show();

    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', 'edit-form');
    formData.append('page', 'auth');
    formData.append('page-output', 'json');

    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        window.offcanvasEnd.loading_hide();
        window.offcanvasEnd.body(data.html);
        window.offcanvasEnd.title(data.title);
    });

    return false; // Don't update table
});</code></pre>

    <h3>Best Practices</h3>
    <ul>
        <li>Always return <code>true</code> or <code>false</code> explicitly to control default behavior</li>
        <li>Use descriptive action names that match your PHP action configuration</li>
        <li>Handle errors gracefully with try-catch blocks for async operations</li>
        <li>Return <code>false</code> for actions that don't need table refresh (downloads, redirects)</li>
        <li>Return <code>true</code> for actions that modify data and require table update</li>
        <li>Use <code>confirm()</code> for destructive operations to prevent accidental deletions</li>
    </ul>

    <h2>Complete Example</h2>

    <h3>Module Implementation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\{Response, MessagesHandler};

class BulkActionsModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('bulkActions')
             ->title('Bulk Actions')
             ->menu('Test Table 2')
             ->access('public');
    }

    #[RequestAction('home')]
    public function home() {
        $response = [
            'page' => $this->page,
            'title' => $this->title,
            'link_action_edit' => 'edit',
            'table_id' => 'idTableTestTable2'
        ];

        $postModel = new \Modules\Posts\PostsModel();

        // Create table with checkboxes and bulk actions
        $tableBuilder = \Builders\TableBuilder::create($postModel, 'idTableTestTable2')
            ->where('deleted_at IS NULL')
            // Set bulk actions (these will show checkboxes automatically)
            ->setBulkActions([
                'soft_delete' => [
                    'label' => 'Soft Delete',
                    'action' => [$this, 'actionSoftDelete']
                ],
                'remove' => [
                    'label' => 'Remove',
                    'action' => [$this, 'actionRemove']],
                'restore' => [
                    'label' => 'Restore',
                    'action' => [$this, 'actionRestore']],
                'confronto' => [
                    'label' => 'Comparison',
                    'action' => [$this, 'actionComparison'],
                    'mode' => 'batch', // passa tutti i record selezionati in una volta sola
                    'updateTable' => false // non aggiorna la tabella
                ]
            ])
            // Make title clickable
            ->field('title')->Link('?page=' . $this->page . '&action=edit&id=%id%');

        // Use getResponse() to merge table data with response
        $response = [...$response, ...$tableBuilder->getResponse()];

        Response::render(MILK_DIR . '/Modules/Posts/Views/list_page.php', $response);
    }

    public function actionRemove($record, $request) {
        if ($record->deleted_at != null) {
            return $record->delete($record->id);
        }
        return false;
    }

    public function actionSoftDelete($record, $request) {
        $record->deleted_at = date('Y-m-d H:i:s');
        if ($record->save()) {
            return ['success' => true, 'message' => 'Soft deleted successfully'];
        }
        return ['success' => false, 'message' => 'Soft delete failed'];
    }

     public function actionRestore($record, $request) {
        $record->deleted_at = null;
        if ($record->save()) {
            return ['success' => true, 'message' => 'Restored successfully'];
        }
        return ['success' => false, 'message' => 'Restore failed'];
    }

    public function actionComparison($records, $request) {
        $records->setFormatted();
        $fields = ['title', 'category', 'created_at', 'updated_at', 'deleted_at', 'content'];
        $rows = '';
        foreach ($fields as $field) {
            $rows .= '<tr><th>' . $field . '</th>';
            foreach ($records as $record) {
                $rows .= '<td>' . ($record->$field ?? '-') . '</td>';
            }
            $rows .= '</tr>';
        }

        return [
            'success' => true,
            'offcanvas_end' => [
                'title' => 'Confronto',
                'body' => '<table class="table table-bordered"><tbody>' . $rows . '</tbody></table>',
                'size' => 'xl'
            ]
        ];
    }
}</code></pre>

    <h3>Model (PostsModel)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use App\Attributes\{Validate};
use App\Abstracts\AbstractModel;

class PostsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__posts')
            ->id()
            ->title()
            ->text('content')->formType('editor')
            ->created_at()
            ->timestamp('deleted_at')->nullable()
            ->datetime('updated_at')->hideFromEdit()->saveValue(date('Y-m-d H:i:s'));
    }
}</code></pre>

</div>
