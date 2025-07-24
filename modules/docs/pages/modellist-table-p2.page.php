<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Add query filters
 * @category Dynamic Table
 * @order 20
 * @tags table filters, JavaScript, AJAX, filter-management, search, status-filters, frontend-backend, dynamic-filtering, API-methods, user-interface, automated-filters
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Table Filter System</h1>
    
    <p>After seeing how to create tables and modify them, let's now see how to manage search filters. Let's start with a simple complete example:</p>
   
    
    <h3>Search Input with Automatic Update.</h3>
    <p>To make a field a search filter you need to add the class <code>js-milk-filter-onchange</code> and the data attributes <code>data-filter-id</code> where you insert the table id and <code>data-filter-type</code> to specify the filter type. The search filter is preset so it will search across the entire table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;div class="d-inline-flex" style="width: auto;">
    &lt;?php \MilkCore\Form::input('text', 'search', 'Search', '', ['floating' => false, 'class' => 'js-milk-filter-onclick', 'data-filter-id' => 'table_posts', 'data-filter-type' => 'search', 'label-attrs-class' => 'p-0 pt-2 me-2']); ?>
&lt;/div&gt;</code></pre>


    <h3>Select with Automatic Update</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;div class="card"&gt;
    &lt;div class="card-header"&gt;
        &lt;!-- Select that updates automatically --&gt;
        &lt;?php Form::select('filter_status', 'Status', [
            '' =&gt; 'All', 'active' =&gt; 'Active', 'suspended' =&gt; 'Suspended', 'trash' =&gt; 'Trash'
        ], '', [
            'data-filter-id' =&gt; 'table_id', 
            'data-filter-type' =&gt; 'status',  
            'class' =&gt; 'js-milk-filter-onchange'
        ]); ?&gt;
    &lt;/div&gt;
    
   
    // your table here
 
&lt;/div&gt;</code></pre>

    <h3>Form with Search Button</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;div class="card"&gt;
    &lt;div class="card-header"&gt;
        &lt;div class="row g-3"&gt;
            &lt;div class="col-md-4"&gt;
                &lt;!-- Input that collects the value but doesn't execute automatically --&gt;
                &lt;input type="text" 
                       class="form-control js-milk-filter" 
                       data-filter-id="table_file_logs" 
                       data-filter-type="search" 
                       placeholder="Search in logs..."&gt;
            &lt;/div&gt;
            &lt;div class="col-md-3"&gt;
                &lt;!-- Select for action --&gt;
                &lt;?php Form::select('filter_action', 'Action', $actions, '', [
                    'data-filter-id' =&gt; 'table_file_logs', 
                    'data-filter-type' =&gt; 'action',  
                    'class' =&gt; 'js-milk-filter'
                ]); ?&gt;
            &lt;/div&gt;
            &lt;div class="col-md-2"&gt;
                &lt;!-- Button that executes all filters --&gt;
                &lt;div class="btn btn-primary js-milk-filter-onclick" 
                     data-filter-id="table_file_logs"&gt;Search&lt;/div&gt;
            &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
    
    // your table here
&lt;/div&gt;</code></pre>

    <h3>Action List (Button Filters)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;div class="card"&gt;
    &lt;div class="card-header"&gt;
        &lt;!-- Action list with automatic update --&gt;
        &lt;?php Form::action_list('filter_action', 'Filter by action', $actions, '', [], [
            'data-filter-id' =&gt; 'table_file_logs', 
            'data-filter-type' =&gt; 'action',  
            'class' =&gt; 'js-milk-filter-onchange'
        ]); ?&gt;
    &lt;/div&gt;
    
    // your table here
&lt;/div&gt;</code></pre>


<h2 class="mt-4">Available CSS Classes</h2>
    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>CSS Class</th>
                <th>Behavior</th>
                <th>When to Use</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>js-milk-filter-onchange</code></td>
                <td>Makes the field a filter that updates automatically on change</td>
                <td>Search inputs, selects, checkboxes, radios</td>
            </tr>
            <tr>
                <td><code>js-milk-filter</code></td>
                <td>Makes the field a filter. This is not executed automatically, so you need a button to execute it with the <code>js-milk-filter-onclick</code> class</td>
                <td>When you want to manually control execution</td>
            </tr>
            <tr>
                <td><code>js-milk-filter-onclick</code></td>
                <td>Filter executed on click</td>
                <td>Search buttons, filter application buttons</td>
            </tr>
        </tbody>
    </table>

    <h2 class="mt-4">Required Data Attributes</h2>
    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Attribute</th>
                <th>Required</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>data-filter-id</code></td>
                <td>✅ Always</td>
                <td>ID of the table to filter</td>
                <td><code>data-filter-id="userList"</code></td>
            </tr>
            <tr>
                <td><code>data-filter-type</code></td>
                <td>✅ For onchange/onclick</td>
                <td>Filter type</td>
                <td><code>data-filter-type="search"</code></td>
            </tr>
        </tbody>
    </table>

    <h2 class="mt-4">Frontend HTML Examples</h2>

    <h3>Search Input with Automatic Update</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;div class="card"&gt;
    &lt;div class="card-header"&gt;
        &lt;!-- Search input that updates automatically --&gt;
        &lt;input type="text" 
               class="form-control js-milk-filter-onchange" 
               data-filter-id="userList" 
               data-filter-type="search" 
               placeholder="Search users..."&gt;
    &lt;/div&gt;
    
    &lt;div class="card-body"&gt;
        &lt;div id="userList" class="js-table-container"&gt;
            &lt;!-- Table loaded here --&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>




    <h2 class="mt-4">Backend PHP</h2>
    
    <h3>Method 1: Router Class Extends AbstractRouter</h3>
    <p>You can use the <code>get_modellist_data</code> method to get table data and pass it to the template. This method accepts a callback as the second argument that allows you to configure filters.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Usage with get_modellist_data
$modellist_data = $this-&gt;get_modellist_data($table_id, function($model_list) {
    // Filter for action
    $model_list-&gt;add_filter('action', function($query, $action) {
        if (!empty($action)) {
            $query-&gt;where('action = ?', [$action]);
        }
    });
    
    // Filter for search
    $model_list-&gt;add_filter('search', function($query, $search) {
        if (!empty($search)) {
            $query-&gt;where('(title LIKE ? OR description LIKE ?)', ["%{$search}%", "%{$search}%"]);
        }
    });
});
?&gt;</code></pre>

    <h3>Method 2: With Standard ModelList</h3>
    <p>Outside of classes that extend AbstractRouter, you can use the standard ModelList to configure filters.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Complete model configuration
$model = new \MilkCore\ModelList('#__users', 'userList');

// Filter for search
$model-&gt;add_filter('search', function($query, $search) use ($model) {
    if (!empty($search)) {
        $query-&gt;where('`username` LIKE ? OR `email` LIKE ?', ['%'.$search.'%', '%'.$search.'%']);
    }
});

// Filter for status
$model-&gt;add_filter('status', function($query, $status) use ($model) {
    $model-&gt;page_info['filter_status'] = $status;
    switch ($status) {
        case 'active':
            $query-&gt;where('`status` = 1');
            break;
        case 'suspended':
            $query-&gt;where('`status` = 0');
            break;
        case 'trash':
            $query-&gt;where('`status` = -1');
            break;
    }
});

// Table generation
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

    <h2 class="mt-4">6. JavaScript API Methods (Advanced)</h2>
    
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
                <td>Adds filter manually</td>
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
                <td>Registers a filter</td>
                <td>$model->add_filter('status', function($q, $v) {...})</td>
            </tr>
            <tr>
                <td>query_from_request()</td>
                <td>Creates query with filters</td>
                <td>$query = $model->query_from_request()</td>
            </tr>
        </tbody>
    </table>

    <h2 class="mt-4">Complete Example</h2>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Complete controller with new system
function user_management() {
    // Preparing actions for select
    $actions = [
        '' =&gt; 'All actions',
        'login' =&gt; 'Login',
        'logout' =&gt; 'Logout', 
        'create' =&gt; 'Creation',
        'update' =&gt; 'Update'
    ];
    
    // Model setup
    $model = new \MilkCore\ModelList('#__users', 'userList');
    
    // Filters
    $model-&gt;add_filter('search', function($query, $search) {
        if (!empty($search)) {
            $query-&gt;where('(username LIKE ? OR email LIKE ? OR name LIKE ?)', 
                ["%{$search}%", "%{$search}%", "%{$search}%"]);
        }
    });
    
    $model-&gt;add_filter('status', function($query, $status) {
        switch ($status) {
            case 'active':
                $query-&gt;where('status = 1');
                break;
            case 'suspended':
                $query-&gt;where('status = 0');
                break;
            case 'trash':
                $query-&gt;where('status = -1');
                break;
        }
    });
    
    $model-&gt;add_filter('action', function($query, $action) {
        if (!empty($action)) {
            $query-&gt;where('last_action = ?', [$action]);
        }
    });
    
    // Output generation
    if (($_REQUEST['page-output'] ?? '') == 'json') {
        // AJAX output
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
        
        echo json_encode(['success' =&gt; true, 'html' =&gt; $table_html]);
        return;
    }
    
    // Initial HTML output
    echo '&lt;div class="card"&gt;
        &lt;div class="card-header"&gt;
            &lt;div class="row g-3"&gt;
                &lt;div class="col-md-4"&gt;
                    &lt;input type="text" 
                           class="form-control js-milk-filter-onchange" 
                           data-filter-id="userList" 
                           data-filter-type="search" 
                           placeholder="Search users..."&gt;
                &lt;/div&gt;
                &lt;div class="col-md-3"&gt;';
    
    Form::select('filter_status', 'Status', [
        '' =&gt; 'All',
        'active' =&gt; 'Active',
        'suspended' =&gt; 'Suspended', 
        'trash' =&gt; 'Trash'
    ], '', [
        'floating' =&gt; false,
        'data-filter-id' =&gt; 'userList',
        'data-filter-type' =&gt; 'status',
        'class' =&gt; 'js-milk-filter-onchange'
    ]);
    
    echo '      &lt;/div&gt;
                &lt;div class="col-md-3"&gt;';
    
    Form::select('filter_action', 'Action', $actions, '', [
        'floating' =&gt; false,
        'data-filter-id' =&gt; 'userList', 
        'data-filter-type' =&gt; 'action',
        'class' =&gt; 'js-milk-filter-onchange'
    ]);
    
    echo '      &lt;/div&gt;
            &lt;/div&gt;
        &lt;/div&gt;
        &lt;div class="card-body"&gt;
            &lt;div id="userList" class="js-table-container"&gt;';
    
    // Initial table loading
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
    
    echo '      &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;';
}
?&gt;</code></pre>


<h2 class="mt-4">JavaScript </h2>
<p>The system automatically handles JavaScript, however if you want to create a completely custom filter, I provide below the old tutorial on using filters: <a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/modellist-table-pold.page'); ?>">Old Tutorial</a>
</p>
</div>