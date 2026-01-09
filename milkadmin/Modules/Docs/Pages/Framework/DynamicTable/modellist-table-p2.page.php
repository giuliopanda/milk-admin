<?php
namespace Modules\Docs\Pages;
/**
 * @title Add query filters
 * @guide framework
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
    &lt;?php \App\Form::input('text', 'search', 'Search', '', ['floating' => false, 'class' => 'js-milk-filter-onchange', 'data-filter-id' => $table_id, 'data-filter-type' => 'search', 'label-attrs-class' => 'p-0 pt-2 me-2']); ?>
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
        &lt;?php Form::actionList('filter_action', 'Filter by action', $actions, '', [], [
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
            <tr>
                <td><code>js-milk-filter-clear</code></td>
                <td>Clear all filters and reload table</td>
                <td>Clear buttons that reset all filter fields</td>
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
    
    <h3>Method 1: Controller Class Extends AbstractController</h3>
    <p>You can use the <code>get_modellist_data</code> method to get table data and pass it to the template. This method accepts a callback as the second argument that allows you to configure filters.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Usage with get_modellist_data
$modellist_data = $this-&gt;getModellistData($table_id, function($model_list) {
    // Filter for action
    $model_list-&gt;addFilter('action', function($query, $action) {
        if (!empty($action)) {
            $query-&gt;where('action = ?', [$action]);
        }
    });
    
    // Filter for search
    $model_list-&gt;addFilter('search', function($query, $search) {
        if (!empty($search)) {
            $query-&gt;where('(title LIKE ? OR description LIKE ?)', ["%{$search}%", "%{$search}%"]);
        }
    });
});
?&gt;</code></pre>

    <p><code>get_modellist_data</code> can be used to modify the query</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Usage with get_modellist_data
$modellist_data = $this-&gt;getModellistData($table_id, null, function($query) {
    $query-&gt;select(['id', 'title']);
});
?&gt;</code></pre>
    <p>With this method the columns of list_structure extracted from the model object will be filtered with the columns selected in the query.</p>

    <h3>Method 2: Outside of AbstractController</h3>
    <p>Outside of classes that extend AbstractController, you can use the standard ModelList to configure filters.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Complete model configuration
$model = new \App\Modellist\ModelList('#__users', 'userList');

// Filter for search
$model-&gt;addFilter('search', function($query, $search) use ($model) {
    if (!empty($search)) {
        $query-&gt;where('`username` LIKE ? OR `email` LIKE ?', ['%'.$search.'%', '%'.$search.'%']);
    }
});

// Filter for status
$model-&gt;addFilter('status', function($query, $status) use ($model) {
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
$query = $model-&gt;queryFromRequest();
$rows = Get::db()-&gt;getResults(...$query-&gt;get());
$total = Get::db()-&gt;getVar(...$query-&gt;getTotal());

$page_info = $model-&gt;getPageInfo($total);
$page_info-&gt;setId('userList')-&gt;setAjax(true);

echo Get::themePlugin('table', [
    'info' =&gt; $model-&gt;getListStructure(),
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
                <td>addFilter($type, $callback)</td>
                <td>Registers a filter</td>
                <td>$model->addFilter('status', function($q, $v) {...})</td>
            </tr>
            <tr>
                <td>queryFromRequest()</td>
                <td>Creates query with filters</td>
                <td>$query = $model->queryFromRequest()</td>
            </tr>
        </tbody>
    </table>

    <h2 class="mt-4">SearchBuilder Class (Recommended Method)</h2>
    <p>The new <code>SearchBuilder</code> class provides a fluent interface for creating search forms with automatic filter integration:</p>
    
    <h3>Basic Usage</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Create SearchBuilder from TableBuilder
$search_builder = $table_builder->createSearchBuilder()
    ->search('search', 'Search Posts')
    ->select('status', 'Status', [
        '' => 'All Status',
        'published' => 'Published',
        'draft' => 'Draft',
        'trash' => 'Trash'
    ])
    ->actionList('category', 'Category', [
        '' => 'All',
        'news' => 'News', 
        'blog' => 'Blog',
        'tutorial' => 'Tutorial'
    ]);
    
$search_html = $search_builder->render();
?&gt;</code></pre>

    <h3>Layout Configuration</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
$search_builder = $table_builder->createSearchBuilder()
    ->search('search', 'Search Posts')
    ->select('status', 'Status', $options)
    
    // Label layout
    ->setLabelLayout('side')        // 'side' (labels beside fields) or 'top' (labels above fields)
    
    // Fields layout options:
    ->setFieldsLayout('columns', 3)    // Equal columns: 'columns' with number of columns
    ->setFieldsLayout('columns', null) // Auto columns: uses 'col' class for automatic sizing
    ->setFieldsLayout('columns', ['col-md-6', 'col-md-3', 'col-md-3']) // Custom column sizes
    ->setFieldsLayout('stacked')        // Vertical layout (stacked)
    
    // Search mode
    ->setSearchMode('onchange')     // 'onchange' (automatic) or 'submit' (manual with buttons)
    // ->setSearchMode('submit', true) // Submit mode with automatic Search/Clear buttons
    
    ->render([], true);
?&gt;</code></pre>

    <h3>Available SearchBuilder Methods</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Method</th>
                <th>Description</th>
                <th>Parameters</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>search($type, $label)</code></td>
                <td>Adds a text search input</td>
                <td>$type: filter type, $label: field label</td>
            </tr>
            <tr>
                <td><code>select($type, $label, $options, $selected)</code></td>
                <td>Adds a select dropdown</td>
                <td>$type: filter type, $label: field label, $options: array of options</td>
            </tr>
            <tr>
                <td><code>actionList($type, $label, $options, $selected)</code></td>
                <td>Adds an action list (button filters)</td>
                <td>$type: filter type, $label: field label, $options: array of options</td>
            </tr>
            <tr>
                <td><code>addInput($input_type, $type, $label, $value)</code></td>
                <td>Adds a generic input field</td>
                <td>$input_type: HTML input type, $type: filter type, $label: field label</td>
            </tr>
            <tr>
                <td><code>searchButton($label)</code></td>
                <td>Adds a search button (for submit mode)</td>
                <td>$label: button label</td>
            </tr>
            <tr>
                <td><code>addClearButton($label)</code></td>
                <td>Adds a clear button</td>
                <td>$label: button label</td>
            </tr>
            <tr>
                <td><code>setLabelLayout($layout)</code></td>
                <td>Sets label positioning</td>
                <td>'side' or 'top'</td>
            </tr>
            <tr>
                <td><code>setFieldsLayout($layout, $columns_config)</code></td>
                <td>Sets field arrangement</td>
                <td>'columns' or 'stacked', number/array/null for columns</td>
            </tr>
            <tr>
                <td><code>setSearchMode($mode, $auto_buttons)</code></td>
                <td>Sets search behavior</td>
                <td>'onchange' or 'submit', auto-add buttons</td>
            </tr>
        </tbody>
    </table>

    <h3>SearchBuilder Examples</h3>
    
    <h4>Example 1: Automatic Search (Default)</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
$search_builder = $table_builder->createSearchBuilder()
    ->search('search', 'Search')
    ->select('status', 'Status', ['active' => 'Active', 'inactive' => 'Inactive'])
    ->setLabelLayout('side')          // Labels beside fields
    ->setFieldsLayout('columns', 2)   // 2 columns layout
    ->setSearchMode('onchange');       // Automatic search on change

echo $search_builder->render([], true);
?&gt;</code></pre>
    
    <h4>Example 2: Manual Search with Buttons</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
$search_builder = $table_builder->createSearchBuilder()
    ->search('search', 'Search')
    ->select('category', 'Category', $categories)
    ->input('date', 'created_from', 'From Date')
    ->setLabelLayout('top')            // Labels above fields
    ->setFieldsLayout('columns', 4)    // 4 columns layout  
    ->setSearchMode('submit', true);   // Manual search with auto Search/Clear buttons

echo $search_builder->render([], true);
?&gt;</code></pre>
    
    <h4>Example 3: Custom Column Sizes</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
$search_builder = $table_builder->createSearchBuilder()
    ->search('search', 'Search Posts')
    ->select('status', 'Status', $statuses)
    ->actionList('category', 'Category', $categories)
    ->setLabelLayout('side')           // Labels beside fields (text-aligned right)
    ->setFieldsLayout('columns', [     // Custom column sizes
        'col-md-4',     // Search field (33%)
        'col-md-3',     // Status field (25%)  
        'col-md-3',     // Category field (25%)
        'col-md-1',     // Search button (8.3%)
        'col-md-1'      // Clear button (8.3%)
    ])
    ->setSearchMode('submit', true);   // Submit mode with auto buttons

echo $search_builder->render([], true);
?&gt;</code></pre>
    
    <h4>Example 4: Multi-Row Layout</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
$search_builder = $table_builder->createSearchBuilder()
    ->search('search', 'Search Posts')
    ->select('status', 'Status', $statuses)
    ->actionList('category', 'Category', $categories)
    ->setLabelLayout('side')           // Labels beside fields
    ->setFieldsLayout('columns', [     // Multi-row layout: array of arrays
        ['col-md-6', 'col-md-6'],        // Row 1: Search (50%) + Status (50%)
        ['col-md-4', 'col-md-4', 'col-md-4']  // Row 2: Category (33%) + Search btn (33%) + Clear btn (33%)
    ])
    ->setSearchMode('submit', true);   // Submit mode with auto buttons

echo $search_builder->render([], true);
?&gt;</code></pre>

    <h2 class="mt-4">Layout Options Summary</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Layout Type</th>
                <th>Configuration</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Equal Columns</td>
                <td><code>->setFieldsLayout('columns', 3)</code></td>
                <td>3 equal columns (col-md-4 each)</td>
            </tr>
            <tr>
                <td>Auto Columns</td>
                <td><code>->setFieldsLayout('columns', null)</code></td>
                <td>Automatic distribution using 'col' class</td>
            </tr>
            <tr>
                <td>Custom Single Row</td>
                <td><code>->setFieldsLayout('columns', ['col-md-6', 'col-md-3', 'col-md-3'])</code></td>
                <td>Custom sizes in one row</td>
            </tr>
            <tr>
                <td>Multi-Row</td>
                <td><code>->setFieldsLayout('columns', [['col-md-6', 'col-md-6'], ['col-md-4', 'col-md-4', 'col-md-4']])</code></td>
                <td>Multiple rows with custom sizes per row</td>
            </tr>
            <tr>
                <td>Stacked</td>
                <td><code>->setFieldsLayout('stacked')</code></td>
                <td>Vertical layout (one field per line)</td>
            </tr>
        </tbody>
    </table>

    <h2 class="mt-4">Manual Implementation (Legacy Method)</h2>
    <p>For backward compatibility or advanced use cases, you can still implement filters manually:</p>
    
    <h3>Complete Example</h3>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// Complete module with new system
function userManagement() {
    // Preparing actions for select
    $actions = [
        '' =&gt; 'All actions',
        'login' =&gt; 'Login',
        'logout' =&gt; 'Logout', 
        'create' =&gt; 'Creation',
        'update' =&gt; 'Update'
    ];
    
    // Model setup
    $model = new \App\Modellist\ModelList('#__users', 'userList');
    
    // Filters
    $model-&gt;addFilter('search', function($query, $search) {
        if (!empty($search)) {
            $query-&gt;where('(username LIKE ? OR email LIKE ? OR name LIKE ?)', 
                ["%{$search}%", "%{$search}%", "%{$search}%"]);
        }
    });
    
    $model-&gt;addFilter('status', function($query, $status) {
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
    
    $model-&gt;addFilter('action', function($query, $action) {
        if (!empty($action)) {
            $query-&gt;where('last_action = ?', [$action]);
        }
    });
    
    // Output generation
    if (($_REQUEST['page-output'] ?? '') == 'json') {
        // AJAX output
        $query = $model-&gt;queryFromRequest();
        $rows = Get::db()-&gt;getResults(...$query-&gt;get());
        $total = Get::db()-&gt;getVar(...$query-&gt;getTotal());
        
        $page_info = $model-&gt;getPageInfo($total);
        $page_info-&gt;setId('userList')-&gt;setAjax(true);
        
        $table_html = Get::themePlugin('table', [
            'info' =&gt; $model-&gt;getListStructure(),
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
    $query = $model-&gt;queryFromRequest();
    $rows = Get::db()-&gt;getResults(...$query-&gt;get());
    $total = Get::db()-&gt;getVar(...$query-&gt;getTotal());
    
    $page_info = $model-&gt;getPageInfo($total);
    $page_info-&gt;setId('userList')-&gt;setAjax(true);
    
    echo Get::themePlugin('table', [
        'info' =&gt; $model-&gt;getListStructure(),
        'rows' =&gt; $rows,
        'page_info' =&gt; $page_info
    ]);
    
    echo '      &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;';
}
?&gt;</code></pre>

<h2 class="mt-4">JavaScript </h2>
<p>The system automatically handles JavaScript, however if you want to create a completely custom filter, I provide below the old tutorial on using filters: <a href="<?php echo \App\Route::url('?page=docs&action=Framework/DynamicTable/modellist-table-pold'); ?>">Old Tutorial</a>
</p>
</div>