<?php
namespace Modules\Docs\Pages;
/**
 * @title Search & Filters
 * @guide developer
 * @order 15
 * @tags SearchBuilder, filters, search, action-list, fluent-interface, table-integration, conditional-actions, showIfFilter
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Search & Filters Documentation</h1>

    <p>The <code>SearchBuilder</code> class provides a fluent interface for creating search bars and filter controls for tables. It integrates seamlessly with <code>TableBuilder</code> to enable dynamic filtering without page reloads.</p>

    <h2>Basic Concepts</h2>

    <h3>How Search & Filters Work</h3>
    <ul>
        <li><strong>SearchBuilder</strong>: Creates the UI for search inputs and filter controls</li>
        <li><strong>TableBuilder</strong>: Handles the actual data filtering using <code>filter()</code> method</li>
        <li><strong>Automatic Search</strong>: The default "search" filter is handled automatically by TableBuilder</li>
        <li><strong>Custom Filters</strong>: Other filters (status, category, etc.) require explicit filter definitions</li>
    </ul>

    <h3>Basic Search (Automatic)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// The search input is automatically handled by TableBuilder
resonse['title'] = \Builders\TitleBuilder::create('Posts')
    ->addSearch('posts_table', 'Search posts...', 'Search')
    ->render();

$tableBuilder = \Builders\TableBuilder::create($model, 'idTablePosts');
// No filter() needed for default search - it's automatic!

$response['search_html'] = $searchBuilder->render([], true);
$response = [...$response, ...$tableBuilder->getResponse()];</code></pre>

    <h3>Search with Custom Filters</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Create search with status filter
$searchBuilder = \Builders\SearchBuilder::create('idTablePosts')
    ->addActionList('status', 'Filter by:', [
        'active' => 'Active',
        'deleted' => 'Deleted'
    ], 'active')  // Default value: 'active'
    ->addSearch();

// Define how the status filter affects the query
$tableBuilder = \Builders\TableBuilder::create($model, 'idTablePosts')
    ->filter('status', function($query, $value) {
        if ($value === 'deleted') {
            $query->where('deleted_at IS NOT NULL');
        } else {
            $query->where('deleted_at IS NULL');
        }
    }, 'active');  // Default filter value

$response['search_html'] = $searchBuilder->render([], true);
$response = [...$response, ...$tableBuilder->getResponse()];</code></pre>

    <h2>SearchBuilder Methods</h2>

    <h3>addSearch() - Text Search Input</h3>
    <p>Adds a search input field with automatic onChange behavior. The default "search" filter is automatically processed by TableBuilder across all text fields.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Basic search
$searchBuilder->addSearch();

// Custom filter type
$searchBuilder->addSearch('custom_search', 'Search products');

// With additional options
$searchBuilder->addSearch('search', 'Search', [
    'placeholder' => 'Type to search...',
    'class' => 'custom-class'
]);</code></pre>

    <h3>addActionList() - Tab-Style Filter</h3>
    <p>Creates clickable tab-style filters. Perfect for status filters, categories, or any predefined options.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$searchBuilder->addActionList('status', 'Status:', [
    'all' => 'All',
    'active' => 'Active',
    'pending' => 'Pending',
    'archived' => 'Archived'
], 'active');  // Default selected value

// Without label
$searchBuilder->addActionList('category', '', [
    'news' => 'News',
    'blog' => 'Blog',
    'events' => 'Events'
], 'news');</code></pre>

    <h3>addSelect() - Dropdown Filter</h3>
    <p>Adds a select dropdown for filtering. Useful when you have many options.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$searchBuilder->addSelect('category', 'Category:', [
    '' => 'All Categories',
    'tech' => 'Technology',
    'design' => 'Design',
    'marketing' => 'Marketing'
], '');  // Default: empty (all)

// With additional options
$searchBuilder->addSelect('author', 'Author:', $authors, '', [
    'class' => 'custom-select-class'
]);</code></pre>

    <h3>addInput() - Generic Input Field</h3>
    <p>Adds any type of input field for custom filters.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Date filter
$searchBuilder->addInput('date', 'start_date', 'From:', '', [
    'placeholder' => 'Start date'
]);

// Number filter
$searchBuilder->addInput('number', 'min_price', 'Min Price:', '', [
    'placeholder' => '0',
    'min' => 0
]);</code></pre>

    <h2>TableBuilder Filter Integration</h2>

    <h3>The filter() Method</h3>
    <p>Define how each filter affects the database query. The first parameter is the filter name (must match SearchBuilder filter_type), the second is a callback function, and the third is the default value.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$tableBuilder = \Builders\TableBuilder::create($model, 'idTablePosts')
    // Filter by status
    ->filter('status', function($query, $value) {
        if ($value === 'deleted') {
            $query->where('deleted_at IS NOT NULL');
        } else if ($value === 'active') {
            $query->where('deleted_at IS NULL');
        }
        // If $value is 'all', don't add any condition
    }, 'active')

    // Filter by category
    ->filter('category', function($query, $value) {
        if (!empty($value)) {
            $query->where('category = ?', [$value]);
        }
    }, '')

    // Filter by date range
    ->filter('start_date', function($query, $value) {
        if (!empty($value)) {
            $query->where('created_at >= ?', [$value . ' 00:00:00']);
        }
    }, '');</code></pre>

    <h3>Important Notes</h3>
    <ul>
        <li><strong>Filter Name Matching</strong>: The filter name in <code>filter()</code> must exactly match the <code>filter_type</code> in SearchBuilder</li>
        <li><strong>Default Values</strong>: Both SearchBuilder and TableBuilder should use the same default value</li>
        <li><strong>Automatic Search</strong>: The "search" filter doesn't need a <code>filter()</code> definition - it's handled automatically</li>
        <li><strong>Empty Values</strong>: Always check if value is empty before applying filters to avoid errors</li>
    </ul>

        <div class="alert alert-warning mb-4">
        <p><strong>DO NOT wrap the table output in a div with the table ID.</strong> The TableBuilder already includes the correct ID internally. Wrapping it will break the filters!</p>
        <div class="row">
            <div class="col-md-6">
                <strong class="text-danger">WRONG:</strong>
                <pre class="bg-light p-2 mt-1"><code>&lt;div id="my-table"&gt;
    &lt;?php echo $table_html; ?&gt;
&lt;/div&gt;</code></pre>
            </div>
            <div class="col-md-6">
                <strong class="text-success">CORRECT:</strong>
                <pre class="bg-light p-2 mt-1"><code>&lt;?php echo $search_html; ?&gt;
&lt;?php echo $table_html; ?&gt;</code></pre>
            </div>
        </div>
        <p class="mb-0 mt-2"><small><strong>Why?</strong> The <code>$table_html</code> already contains a wrapper with the correct ID. Adding another div with the same ID creates duplicate IDs, breaking JavaScript filter functionality.</small></p>
    </div>

    <h2>Search Modes</h2>

    <h3>onChange Mode (Default)</h3>
    <p>Filters execute automatically when values change. Best for most use cases.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$searchBuilder = \Builders\SearchBuilder::create('idTable')
    ->setSearchMode('onchange')  // Default, can be omitted
    ->addActionList('status', 'Status:', [...])
    ->addSearch();</code></pre>

    <h3>Submit Mode</h3>
    <p>Filters execute only when user clicks a Search button. Useful for complex filter combinations.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$searchBuilder = \Builders\SearchBuilder::create('idTable')
    ->setSearchMode('submit', true)  // true = auto-add Search/Clear buttons
    ->addSelect('category', 'Category:', [...])
    ->addInput('date', 'start_date', 'From:', '')
    ->addInput('date', 'end_date', 'To:', '');
    // Search and Clear buttons are added automatically

// Or manually add buttons
$searchBuilder = \Builders\SearchBuilder::create('idTable')
    ->setSearchMode('submit', false)  // false = don't auto-add buttons
    ->addSelect('category', 'Category:', [...])
    ->addSearchButton('Apply Filters')
    ->addClearButton('Reset');</code></pre>

    <h2>Styling and Layout</h2>

    <h3>Wrapper and Container Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$searchBuilder = \Builders\SearchBuilder::create('idTable')
    ->setWrapperClass('d-flex align-items-center gap-3 flex-wrap')
    ->setContainerClasses('mb-3 p-3 bg-light rounded')
    ->addSearch();</code></pre>

    <h3>Label Positioning</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Inline labels (default) - inside the field
$searchBuilder->setLabelPosition('inline');

// No labels
$searchBuilder->setLabelPosition('none');

// Labels before fields
$searchBuilder->setLabelPosition('before');</code></pre>

    <h2>Conditional Actions with showIfFilter</h2>

    <p>One of the most powerful features is combining filters with conditional row actions and bulk actions. Actions can appear or disappear based on the active filter.</p>

    <h3>Complete Example: Soft Delete Pattern</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
public function home() {
    $response = [
        'page' => $this->page,
        'title' => $this->title,
        'table_id' => 'idTablePosts'
    ];

    $postModel = new \Modules\Posts\PostsModel();

    // 1. Create search builder with status filter
    $searchBuilder = \Builders\SearchBuilder::create('idTablePosts')
        ->addActionList('status', 'Filter by:', [
            'active' => 'Active Posts',
            'deleted' => 'Deleted Posts'
        ], 'active')  // Default: show active posts
        ->addSearch();

    // 2. Create table with filter integration
    $tableBuilder = \Builders\TableBuilder::create($postModel, 'idTablePosts')
        // Define filter behavior
        ->filter('status', function($query, $value) {
            if ($value === 'deleted') {
                $query->where('deleted_at IS NOT NULL');
            } else {
                $query->where('deleted_at IS NULL');
            }
        }, 'active')

        // 3. Bulk actions with conditional visibility
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
        ])

        // 4. Row actions with conditional visibility
        ->setActions([
            'edit' => [
                'label' => 'Edit',
                'link' => '?page=posts&action=edit&id=%id%'
            ],
            'hard_delete' => [
                'label' => 'Delete Permanently',
                'confirm' => 'Are you sure?',
                'class' => 'link-action-danger',
                'action' => [$this, 'actionHardDelete'],
                'showIfFilter' => ['status' => 'deleted']
            ],
            'restore' => [
                'label' => 'Restore',
                'action' => [$this, 'actionRestore'],
                'showIfFilter' => ['status' => 'deleted']
            ]
        ])
        ->asLink('title', '?page=' . $this->page . '&action=edit&id=%id%');

    // 5. Render search and table
    $response['search_html'] = $searchBuilder->render([], true);
    $response = [...$response, ...$tableBuilder->getResponse()];

    Response::render(MILK_DIR . '/Modules/Posts/Views/list_page.php', $response);
}

// VIEW FILE: list_page.php
// ✅ CORRECT IMPLEMENTATION:
// &lt;?php echo $search_html; ?&gt;
// &lt;?php echo $table_html; ?&gt;
//
// ❌ WRONG - DO NOT WRAP IN DIV WITH TABLE ID:
// &lt;div id="idTablePosts"&gt;&lt;?php echo $table_html; ?&gt;&lt;/div&gt;

// Action handlers
public function actionSoftDelete($record, $request) {
    $record->deleted_at = date('Y-m-d H:i:s');
    if ($record->save()) {
        return ['success' => true, 'message' => 'Moved to trash'];
    }
    return ['success' => false, 'message' => 'Failed to move to trash'];
}

public function actionHardDelete($record, $request) {
    // Only delete if already soft deleted
    if ($record->deleted_at !== null && $record->delete($record->id)) {
        return ['success' => true, 'message' => 'Permanently deleted'];
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

    <h3>How It Works</h3>
    <ol>
        <li><strong>User selects "Active Posts" filter:</strong>
            <ul>
                <li>Table shows only records where <code>deleted_at IS NULL</code></li>
                <li>Bulk actions: Only "Move to Trash" is visible</li>
                <li>Row actions: Only "Edit" is visible</li>
            </ul>
        </li>
        <li><strong>User switches to "Deleted Posts" filter:</strong>
            <ul>
                <li>Table shows only records where <code>deleted_at IS NOT NULL</code></li>
                <li>Bulk actions: "Delete Permanently" and "Restore" are visible</li>
                <li>Row actions: "Edit", "Delete Permanently", and "Restore" are visible</li>
            </ul>
        </li>
        <li><strong>Everything updates automatically via AJAX</strong> - no page reload!</li>
    </ol>

    <h2>Advanced Examples</h2>

    <h3>Multiple Filters with Categories</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get categories from model
$categories = $categoryModel->getAllAsOptions();

$searchBuilder = \Builders\SearchBuilder::create('idTableProducts')
    ->addActionList('status', 'Status:', [
        'all' => 'All',
        'published' => 'Published',
        'draft' => 'Draft'
    ], 'all')
    ->addSelect('category', 'Category:', array_merge(['' => 'All Categories'], $categories), '')
    ->addSearch();

$tableBuilder = \Builders\TableBuilder::create($productModel, 'idTableProducts')
    ->filter('status', function($query, $value) {
        if ($value !== 'all') {
            $query->where('status = ?', [$value]);
        }
    }, 'all')
    ->filter('category', function($query, $value) {
        if (!empty($value)) {
            $query->where('category_id = ?', [$value]);
        }
    }, '');</code></pre>

    <h3>Date Range Filters</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$searchBuilder = \Builders\SearchBuilder::create('idTableOrders')
    ->addInput('date', 'start_date', 'From:', '')
    ->addInput('date', 'end_date', 'To:', '')
    ->addSearch()
    ->setSearchMode('submit', true);  // Use submit mode for date ranges

$tableBuilder = \Builders\TableBuilder::create($orderModel, 'idTableOrders')
    ->filter('start_date', function($query, $value) {
        if (!empty($value)) {
            $query->where('created_at >= ?', [$value . ' 00:00:00']);
        }
    }, '')
    ->filter('end_date', function($query, $value) {
        if (!empty($value)) {
            $query->where('created_at <= ?', [$value . ' 23:59:59']);
        }
    }, '');</code></pre>

    <h3>Complex Filter Combinations</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$searchBuilder = \Builders\SearchBuilder::create('idTableUsers')
    ->addActionList('role', 'Role:', [
        'all' => 'All Roles',
        'admin' => 'Administrators',
        'editor' => 'Editors',
        'subscriber' => 'Subscribers'
    ], 'all')
    ->addSelect('status', 'Status:', [
        '' => 'All',
        'active' => 'Active',
        'inactive' => 'Inactive'
    ], '')
    ->addInput('date', 'registered_after', 'Registered After:', '')
    ->addSearch()
    ->setSearchMode('onchange');

$tableBuilder = \Builders\TableBuilder::create($userModel, 'idTableUsers')
    ->filter('role', function($query, $value) {
        if ($value !== 'all') {
            $query->where('role = ?', [$value]);
        }
    }, 'all')
    ->filter('status', function($query, $value) {
        if ($value === 'active') {
            $query->where('last_login > ?', [date('Y-m-d', strtotime('-30 days'))]);
        } else if ($value === 'inactive') {
            $query->where('last_login <= ? OR last_login IS NULL', [date('Y-m-d', strtotime('-30 days'))]);
        }
    }, '')
    ->filter('registered_after', function($query, $value) {
        if (!empty($value)) {
            $query->where('created_at >= ?', [$value]);
        }
    }, '');</code></pre>

    <h2>Method Reference</h2>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>create($table_id)</code></td>
                <td>string</td>
                <td>Static factory method to create SearchBuilder instance</td>
            </tr>
            <tr>
                <td><code>addSearch()</code></td>
                <td>filter_type, label, options</td>
                <td>Add text search input (automatically handled by TableBuilder)</td>
            </tr>
            <tr>
                <td><code>addActionList()</code></td>
                <td>filter_type, label, options, selected</td>
                <td>Add tab-style filter with clickable options</td>
            </tr>
            <tr>
                <td><code>addSelect()</code></td>
                <td>filter_type, label, options, selected</td>
                <td>Add dropdown select filter</td>
            </tr>
            <tr>
                <td><code>addInput()</code></td>
                <td>input_type, filter_type, label, value, options</td>
                <td>Add generic input field (date, number, email, etc.)</td>
            </tr>
            <tr>
                <td><code>addSearchButton()</code></td>
                <td>label, options</td>
                <td>Add manual search button (disables auto-execute)</td>
            </tr>
            <tr>
                <td><code>addClearButton()</code></td>
                <td>label, options</td>
                <td>Add button to reset all filters</td>
            </tr>
            <tr>
                <td><code>setSearchMode()</code></td>
                <td>mode, auto_buttons</td>
                <td>Set 'onchange' or 'submit' mode</td>
            </tr>
            <tr>
                <td><code>setWrapperClass()</code></td>
                <td>class</td>
                <td>Set CSS classes for field wrapper</td>
            </tr>
            <tr>
                <td><code>setContainerClasses()</code></td>
                <td>classes</td>
                <td>Set CSS classes for outer container</td>
            </tr>
            <tr>
                <td><code>setLabelPosition()</code></td>
                <td>position</td>
                <td>'inline', 'before', or 'none'</td>
            </tr>
            <tr>
                <td><code>render()</code></td>
                <td>container_options, return</td>
                <td>Render the search form HTML</td>
            </tr>
        </tbody>
    </table>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder Documentation</a> - Table creation and management</li>
        <li><a href="?page=docs&action=Developer/Table/row-actions">Row Actions Documentation</a> - Individual row actions with showIfFilter</li>
        <li><a href="?page=docs&action=Developer/Table/bulk-actions">Bulk Actions Documentation</a> - Batch operations with showIfFilter</li>
    </ul>
</div>
