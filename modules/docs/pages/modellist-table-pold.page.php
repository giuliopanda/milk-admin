<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Table part 2
 * @category hidden
 * @order 
 * @tags table-filters, JavaScript, AJAX, filter-management, search, status-filters, frontend-backend, dynamic-filtering, API-methods, user-interface, getComponent, set_page, reload, filter_add, filter_remove_start, filter_clear
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Table Filters System - Quick Guide</h1>
    <p>Questa è una vecchia guida. La nuova guida si trova <a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/modellist-table-p2.page'); ?>">qui</a></p>
    <p>System for managing dynamic filters in tables. Works with three levels: HTML for the interface, JavaScript for state management, PHP for backend processing.</p>

    <h2 class="mt-4">1. HTML Frontend</h2>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;div class="card"&gt;
    &lt;div class="card-header"&gt;
        &lt;!-- Status filters --&gt;
        Filter by status: 
        &lt;span class="btn btn-sm btn-outline-primary active" onclick="filterStatus('all')"&gt;All&lt;/span&gt;
        &lt;span class="btn btn-sm btn-outline-primary" onclick="filterStatus('active')"&gt;Active&lt;/span&gt;
        &lt;span class="btn btn-sm btn-outline-primary" onclick="filterStatus('suspended')"&gt;Suspended&lt;/span&gt;
        
        &lt;!-- Search field --&gt;
        &lt;div class="d-inline-flex ms-3"&gt;
            &lt;input class="form-control" type="search" id="searchUser" placeholder="Search..."&gt;
            &lt;button class="btn btn-primary" onclick="search()"&gt;Search&lt;/button&gt;
        &lt;/div&gt;
    &lt;/div&gt;
    
    &lt;div class="card-body"&gt;
        &lt;div id="userList" class="js-table-container"&gt;
            &lt;!-- Table loaded here --&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>

    <h2 class="mt-4">2. JavaScript Controller</h2>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Status filter
function filterStatus(type) {
    const table = getComponent('userList');
    table.filter_remove_start('status:');
    
    if (type !== 'all') {
        table.filter_add('status:' + type);
    }
    
    table.set_page(1);
    table.reload();
}

// Search
function search() {
    const table = getComponent('userList');
    const searchValue = document.getElementById('searchUser').value.trim();
    
    table.filter_remove_start('search:');
    
    if (searchValue !== '') {
        table.filter_add('search:' + searchValue);
    }
    
    table.set_page(1);
    table.reload();
}

// Advanced class for filter management
class FilterManager {
    constructor(tableId) {
        this.table = getComponent(tableId);
    }
    
    clearAllFilters() {
        this.table.filter_clear();
        document.getElementById('searchUser').value = '';
        // Reset UI elements
        this.table.set_page(1);
        this.table.reload();
    }
}</code></pre>

    <h2 class="mt-4">3. Backend PHP</h2>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Setup filters in the model
function setupFilters($model) {
    // Status filter
    $model-&gt;add_filter('status', function($query, $status) {
        switch($status) {
            case 'active':
                $query-&gt;where('status = 1');
                break;
            case 'suspended':
                $query-&gt;where('status = 2');
                break;
        }
    });

    // Search filter
    $model-&gt;add_filter('search', function($query, $search) {
        $query-&gt;where('name LIKE ?', ["%{$search}%"])
              -&gt;where('OR email LIKE ?', ["%{$search}%"]);
    });
}

// Usage in the page
$model = new \MilkCore\ModelList('#__users');
setupFilters($model);

$query = $model-&gt;query_from_request();
$rows = Get::db()-&gt;get_results(...$query-&gt;get());
$total = Get::db()-&gt;get_var(...$query-&gt;get_total());

$page_info = $model-&gt;get_page_info($total);
$page_info-&gt;set_id('userList')-&gt;set_ajax(true);

echo Get::theme_plugin('table', [
    'info' =&gt; $model-&gt;get_list_structure(),
    'rows' =&gt; $rows,
    'page_info' =&gt; $page_info
]);
?&gt;</code></pre>

    <h2 class="mt-4">4. Main API Methods</h2>
    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>JavaScript Method</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>filter_add(filter)</td>
                <td>Adds filter</td>
                <td>table.filter_add('status:active')</td>
            </tr>
            <tr>
                <td>filter_remove(filter)</td>
                <td>Removes specific filter</td>
                <td>table.filter_remove('status:active')</td>
            </tr>
            <tr>
                <td>filter_remove_start(prefix)</td>
                <td>Removes filters by prefix</td>
                <td>table.filter_remove_start('status:')</td>
            </tr>
            <tr>
                <td>filter_clear()</td>
                <td>Clears all filters</td>
                <td>table.filter_clear()</td>
            </tr>
            <tr>
                <td>reload()</td>
                <td>Reloads table</td>
                <td>table.reload()</td>
            </tr>
        </tbody>
    </table>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>PHP Method</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>add_filter($type, $callback)</td>
                <td>Registers filter</td>
                <td>$model->add_filter('status', function($q, $v) {...})</td>
            </tr>
            <tr>
                <td>query_from_request()</td>
                <td>Creates query with filters</td>
                <td>$query = $model->query_from_request()</td>
            </tr>
        </tbody>
    </table>

    <h2 class="mt-4">5. Complete Example</h2>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Complete controller
function user_table() {
    if (($_REQUEST['page-output'] ?? '') == 'json') {
        return processTableAjax();
    }
    
    $model = new \MilkCore\ModelList('#__users');
    
    // Setup filters
    $model-&gt;add_filter('status', function($query, $status) {
        $statusMap = ['active' =&gt; 1, 'suspended' =&gt; 2];
        if (isset($statusMap[$status])) {
            $query-&gt;where('status = ?', [$statusMap[$status]]);
        }
    });
    
    $model-&gt;add_filter('search', function($query, $search) {
        $query-&gt;where('(name LIKE ? OR email LIKE ?)', ["%{$search}%", "%{$search}%"]);
    });
    
    // Generate table
    $query = $model-&gt;query_from_request();
    $rows = Get::db()-&gt;get_results(...$query-&gt;get());
    $total = Get::db()-&gt;get_var(...$query-&gt;get_total());
    
    $page_info = $model-&gt;get_page_info($total);
    $page_info-&gt;set_id('userList')-&gt;set_ajax(true);
    
    $table_html = Get::theme_plugin('table', [
        'info' =&gt; $model-&gt;get_list_structure(),
        'rows' =&gt; $rows,
        'page_info' =&gt; $page_info
    ]);
    
    // Output
    if (($_REQUEST['page-output'] ?? '') == 'json') {
        echo json_encode(['success' =&gt; true, 'html' =&gt; $table_html]);
    } else {
        echo $table_html;
    }
}
?&gt;</code></pre>

    <h2 class="mt-4">6. Best Practices</h2>
    
    <div class="row">
        <div class="col-md-6">
            <h5>Do</h5>
            <ul>
                <li>Always reset to page 1 with new filters</li>
                <li>Validate input on both frontend and backend</li>
                <li>Use consistent "type:value" format</li>
                <li>Debouncing for search fields</li>
                <li>Sanitize filter values</li>
            </ul>
        </div>
        <div class="col-md-6">
            <h5>Don't</h5>
            <ul>
                <li>Don't trust user input</li>
                <li>Don't forget SQL escaping</li>
                <li>Don't ignore performance</li>
                <li>Don't hardcode filter logic</li>
                <li>Don't mix filter types</li>
            </ul>
        </div>
    </div>

    <div class="alert alert-info mt-4">
        <strong>Summary:</strong> The filter system allows you to add/remove filters dynamically through JavaScript, which are processed server-side to update the table via AJAX. Filters are stored as JSON arrays and can be freely combined.
    </div>

    <div class="alert alert-light">
        <strong>Useful links:</strong>
        <ul class="mb-0">
            <li><a href="<?php echo Route::url('?page=dynamic_table_example') ?>">Dynamic Table Examples</a></li>
            <li><a href="<?php echo Route::url('?page=table_documentation') ?>">Complete Documentation</a></li>
        </ul>
    </div>
</div>