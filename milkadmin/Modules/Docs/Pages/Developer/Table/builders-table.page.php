<?php
namespace Modules\Docs\Pages;
/**
 * @title Table
 * @guide developer
 * @order 10
 * @tags TableBuilder, fluent-interface, method-chaining, query-builder, table-management, columns, actions, styling, PHP-classes, simplified-API
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>TableBuilder Class Documentation</h1>

    <p>The TableBuilder class provides a fluent interface for creating and managing dynamic tables, simplifying the process compared to using ModelList, ListStructure, and PageInfo directly.</p>

    <h2>System Overview</h2>
    <p>TableBuilder acts as a wrapper that combines:</p>
    <ul>
        <li><strong>ModelList</strong>: Database connection and query management</li>
        <li><strong>ListStructure</strong>: Column structure and configuration</li>
        <li><strong>PageInfo</strong>: Pagination and display information</li>
    </ul>
    <p>It provides a single, chainable API for building tables with less code and better readability.</p>

    <h2>Basic Usage</h2>

    <div class="alert alert-warning mb-4">
        <p><strong>DO NOT wrap the table output in a div with the table ID.</strong> The TableBuilder already includes a wrapper with the correct ID. Adding another div will create duplicate IDs and break filters!</p>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <strong class="text-danger">WRONG in VIEW:</strong>
                <pre class="bg-light p-2 mt-1"><code>&lt;div id="posts_table"&gt;
    &lt;?php echo $table_html; ?&gt;
&lt;/div&gt;</code></pre>
            </div>
            <div class="col-md-6">
                <strong class="text-success">CORRECT in VIEW:</strong>
                <pre class="bg-light p-2 mt-1"><code>&lt;?php echo $table_html; ?&gt;</code></pre>
            </div>
        </div>
        <p class="mb-0 mt-2"><small><strong>Why?</strong> The TableBuilder's <code>getResponse()['html']</code> already contains the full table HTML with the proper wrapper and ID. Just echo it directly!</small></p>
    </div>

    <h3>Constructor and Factory Method</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'table_id');</code></pre>

    <h3>Simple Table Creation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// CONTROLLER
$model = new \Models\PostModel();

$tableBuilder = \Builders\TableBuilder::create($model, 'posts_table')
    ->limit(20)
    ->orderBy('created_at', 'desc')
    ->setDefaultActions()
    ->asLink('title', '?page='.$this->page.'&action=edit&id=%id%');

$response = $tableBuilder->getResponse();
Response::render('view.php', $response);

// VIEW (view.php)
// &lt;?php echo $table_html; ?&gt;  ← Just this! Don't wrap it!</code></pre>

    <h2>Query Building Methods</h2>

    <p>TableBuilder supports all standard query operations. Here are the most common:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    // Basic operations
    ->select(['id', 'title', 'status', 'created_at'])
    ->where('status = ?', ['published'])
    ->orderBy('created_at', 'desc')
    ->limit(50)

    // Advanced operations
    ->whereIn('status', ['active', 'pending'])
    ->whereLike('title', 'search_term', 'both')  // LIKE '%search_term%'
    ->whereBetween('created_at', '2024-01-01', '2024-12-31')
    ->leftJoin('categories', 'posts.category_id = categories.id')

    ->getTable();</code></pre>

    <div class="alert alert-info">
        <p class="mb-0"><strong>📘 For comprehensive query documentation:</strong> See <a href="?page=docs&action=Developer/AbstractsClass/abstract-model-queries" class="alert-link">Abstract Model - Query Methods</a></p>
    </div>

    <h2>Column Management</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    // Add custom column with callback
    ->column('excerpt', 'Excerpt', 'html', [], function($row) {
        return substr($row->content, 0, 100) . '...';
    })

    // Make column clickable
    ->asLink('title', '?page=posts&action=edit&id=%id%')

    // Modify column labels and types
    ->setLabel('created_at', 'Publication Date')
    ->setType('status', 'select')
    ->setOptions('status', ['draft' => 'Draft', 'published' => 'Published'])

    // Show/hide columns
    ->hideColumn('password')
    ->showOnlyColumns(['id', 'title', 'status', 'created_at'])

    // Reorder columns
    ->reorderColumns(['id', 'title', 'status', 'created_at'])

    ->getTable();
    </code></pre>

    <h3>Link Columns (asLink method)</h3>
    <p>The <code>asLink()</code> method provides a simple way to convert columns to clickable links, replacing the verbose <code>setFn()</code> approach.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Old verbose way (before asLink)
$table = \Builders\TableBuilder::create($model, 'posts_table')
    ->setFn('title', function($row, $key) {
        return '<a href="?page=posts&action=edit&id=' . $row->id . '">' . $row->title . '</a>';
    });

// New simplified way with asLink()
$table = \Builders\TableBuilder::create($model, 'posts_table')
    // Basic link - automatically sets column type to 'html'
    ->asLink('title', '?page=posts&action=edit&id=%id%')

    // Link with additional options
    ->asLink('author', '/profile/%author_id%', [
        'target' => '_blank',
        'class' => 'text-primary fw-bold'
    ])

    // Multiple placeholders supported
    ->asLink('full_name', '/user/%id%?tab=profile&ref=%category%')

    ->getTable();

// Supported placeholder patterns:
// %id% - Primary key or 'id' field
// %field_name% - Any column value (e.g., %title%, %status%, %created_at%)
// %author_id% - Nested field access

// Available options:
// 'target' => '_blank' | '_self' | '_parent' | '_top'
// 'class' => 'css-classes-here'
    </code></pre>

    <h3>File Columns (asFile method)</h3>
    <p>The <code>asFile()</code> method converts array columns containing file data into download links. <strong>Automatically applied</strong> when model has <code>type=array</code> and <code>form-type=file</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Basic usage - converts file array to download links
$table = \Builders\TableBuilder::create($model, 'documents_table')
    ->asFile('attachments')
    ->getTable();

// With custom options
$table = \Builders\TableBuilder::create($model, 'reports_table')
    ->asFile('files', [
        'class' => 'btn btn-link text-primary',
        'target' => '_blank'
    ])
    ->getTable();

// Expected data format:
// $row->attachments = [
//     ['url' => 'media/file1.pdf', 'name' => 'Document.pdf'],
//     ['url' => 'media/file2.docx', 'name' => 'Report.docx']
// ];

// Available options:
// 'class' => 'custom-css-class'  // Default: 'js-file-download'
// 'target' => '_blank' | '_self'  // Default: '_blank'

// Auto-detection: If your model defines a column with:
// 'type' => 'array',
// 'form-type' => 'file'
// TableBuilder automatically applies asFile() without explicit call
    </code></pre>

    <h3>Image Columns (asImage method)</h3>
    <p>The <code>asImage()</code> method displays image thumbnails for array columns containing image data. <strong>Automatically applied</strong> when model has <code>type=array</code> and <code>form-type=image</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Basic usage - shows image thumbnails
$table = \Builders\TableBuilder::create($model, 'products_table')
    ->asImage('photos')
    ->getTable();

// With custom options
$table = \Builders\TableBuilder::create($model, 'gallery_table')
    ->asImage('images', [
        'size' => 80,              // Thumbnail size in pixels (default: 50)
        'class' => 'rounded',      // Additional CSS classes
        'lightbox' => true,        // Wrap in clickable link (default: false)
        'max_images' => 3          // Limit displayed images, show "+N" badge
    ])
    ->getTable();

// Expected data format:
// $row->photos = [
//     ['url' => 'media/photo1.jpg', 'name' => 'Product Image 1'],
//     ['url' => 'media/photo2.jpg', 'name' => 'Product Image 2']
// ];

// Available options:
// 'size' => 50                    // Thumbnail width/height in pixels
// 'class' => ''                   // Additional CSS classes for images
// 'lightbox' => false             // Enable clickable links to full images
// 'max_images' => null            // Limit number shown (shows "+N" for remaining)

// Auto-detection: If your model defines a column with:
// 'type' => 'array',
// 'form-type' => 'image'
// TableBuilder automatically applies asImage() without explicit call

// Example with max_images:
// If column has 5 images and max_images=3, displays:
// [img1] [img2] [img3] [+2]
    </code></pre>

    <h3>Sort Mapping</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Map virtual column sorting to actual database field
$table = \Builders\TableBuilder::create($model, 'orders_table')
    ->column('customer_name', 'Customer', 'text', [], function($row) {
        return $row->first_name . ' ' . $row->last_name;
    })
    
    // When user clicks to sort by 'customer_name', actually sort by 'first_name'
    ->mapSort('customer_name', 'first_name')
    
    ->getTable();
    </code></pre>

    <h2>Row Actions</h2>

    <p>Row actions are buttons that appear for each table row, allowing users to perform operations like Edit, Delete, View, or custom actions.</p>

    <h3>Quick Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    ->setActions([
        'edit' => [
            'label' => 'Edit',
            'link' => '?page=posts&action=edit&id=%id%'
        ],
        'delete' => [
            'label' => 'Delete',
            'action' => [$this, 'actionDelete'],
            'confirm' => 'Are you sure?',
            'class' => 'btn-danger'
        ]
    ]);</code></pre>

    <h3>Default Actions Helper</h3>
    <p>Use <code>setDefaultActions()</code> to automatically generate Edit and Delete actions:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    ->setDefaultActions();  // Auto-generates Edit and Delete actions</code></pre>

    <h3>Comprehensive Documentation</h3>
    <p>For detailed information about row actions including:</p>
    <ul>
        <li>Link actions vs callback actions</li>
        <li>URL placeholders and dynamic parameters</li>
        <li>Action callbacks and return values</li>
        <li>Conditional visibility with <code>showIfFilter</code></li>
        <li>Best practices and complete examples</li>
    </ul>
    <p><strong>See: <a href="?page=docs&action=row-actions">Row Actions Documentation</a></strong></p>

    <h3>Bulk Actions</h3>
    <p>For operations on multiple rows at once, use bulk actions.</p>
    <p><strong>See: <a href="?page=docs&action=bulk-actions">Bulk Actions Documentation</a></strong></p>

    <h2>Table Styling</h2>
    <p>TableBuilder provides comprehensive styling options for customizing table appearance.</p>
    <p><strong>See: <a href="?page=docs&action=styling">Table Styling Documentation</a></strong></p>

    <h2>Output Methods</h2>

    <h3>Understanding render() vs getResponse()</h3>

    <div class="alert alert-info">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> When to use each method</h5>
        <p><strong><code>render()</code> or <code>getTable()</code></strong> - Use when you have NO row actions or bulk actions with callbacks:</p>
        <pre class="mb-2"><code class="language-php">// Simple table - no actions or only link actions
$html = TableBuilder::create($model, 'table_id')
    ->asLink('title', '?page=posts&action=edit&id=%id%')
    ->render();

Response::render($view, ['html' => $html]);</code></pre>

        <p class="mt-3"><strong><code>getResponse()</code></strong> - <span class="badge bg-danger">REQUIRED</span> when you have row actions or bulk actions with callback functions:</p>
        <pre class="mb-0"><code class="language-php">// Table with action callbacks - MUST use getResponse()
$response = TableBuilder::create($model, 'table_id')
    ->setActions([
        'delete' => [
            'label' => 'Delete',
            'action' => [$this, 'actionDelete'], // Callback function
            'confirm' => 'Are you sure?'
        ]
    ])
    ->getResponse(); // Returns ['html' => '...', ...callback results]

// Pass entire $response array to Response::render()
Response::render($view, $response);</code></pre>
    </div>

    <div class="alert alert-success">
        <h5 class="alert-heading"><i class="bi bi-lightbulb"></i> AJAX Handling</h5>
        <p class="mb-0"><strong>Response::render() automatically handles AJAX requests.</strong> When the table makes AJAX calls for sorting, pagination, or filtering, Response::render() detects this and returns JSON automatically. No manual checks needed!</p>
    </div>

    <div class="alert alert-danger">
        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Common Mistake</h5>
        <pre><code class="language-php">// ❌ WRONG - Don't concatenate with render() when you have callbacks
$response['html'] = $titleHtml . $tableBuilder->render();
Response::render($view, $response);

// ✅ CORRECT - Use getResponse() and add other data separately
$response = $tableBuilder->getResponse();
$response['title_html'] = $titleHtml;
Response::render($view, $response);</code></pre>
        <p class="mb-0"><strong>Why?</strong> <code>getResponse()</code> returns <code>['html' => '...', ...action_results]</code>. The action results are needed for AJAX/JSON handling and table reloading after callbacks execute.</p>
    </div>

    <h3>All Available Output Methods</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$table = \Builders\TableBuilder::create($model, 'posts_table')
    ->limit(20)
    ->orderBy('created_at', 'desc');

// 1. Get complete data array (advanced usage)
$data = $table->getData();
// Returns: ['rows' => [...], 'info' => ListStructure, 'page_info' => PageInfo]

// 2. Get HTML table only (no actions with callbacks)
$html = $table->render();
echo $html;

// 3. Alternative syntax for render()
$html = $table->getTable();
echo $html;

// 4. Get response array (required for action callbacks)
$response = $table->getResponse();
// Returns: ['html' => '...', ...additional data from actions]

// 5. Get only function results from actions
$results = $table->getFunctionsResults();
    </code></pre>

    <h2>Method Reference Summary</h2>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Category</th>
                <th>Method</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td rowspan="12">Query Building</td>
                <td><code>select()</code></td>
                <td>Select specific columns</td>
            </tr>
            <tr>
                <td><code>where()</code></td>
                <td>Add WHERE condition</td>
            </tr>
            <tr>
                <td><code>whereIn()</code></td>
                <td>WHERE IN clause</td>
            </tr>
            <tr>
                <td><code>whereLike()</code></td>
                <td>LIKE search condition</td>
            </tr>
            <tr>
                <td><code>whereBetween()</code></td>
                <td>BETWEEN condition</td>
            </tr>
            <tr>
                <td><code>join()</code></td>
                <td>JOIN tables</td>
            </tr>
            <tr>
                <td><code>leftJoin()</code></td>
                <td>LEFT JOIN tables</td>
            </tr>
            <tr>
                <td><code>groupBy()</code></td>
                <td>GROUP BY clause</td>
            </tr>
            <tr>
                <td><code>having()</code></td>
                <td>HAVING clause</td>
            </tr>
            <tr>
                <td><code>orderBy()</code></td>
                <td>Set ordering</td>
            </tr>
            <tr>
                <td><code>limit()</code></td>
                <td>Set row limit</td>
            </tr>
            <tr>
                <td><code>queryCustomCallback()</code></td>
                <td>Custom query logic</td>
            </tr>
            <tr>
                <td rowspan="10">Column Management</td>
                <td><code>column()</code></td>
                <td>Add/modify column</td>
            </tr>
            <tr>
                <td><code>setLabel()</code></td>
                <td>Set column label</td>
            </tr>
            <tr>
                <td><code>setType()</code></td>
                <td>Set column type</td>
            </tr>
            <tr>
                <td><code>setOptions()</code></td>
                <td>Set select options</td>
            </tr>
            <tr>
                <td><code>hideColumn()</code></td>
                <td>Hide column</td>
            </tr>
            <tr>
                <td><code>deleteColumn()</code></td>
                <td>Remove column</td>
            </tr>
            <tr>
                <td><code>disableSort()</code></td>
                <td>Disable sorting</td>
            </tr>
            <tr>
                <td><code>mapSort()</code></td>
                <td>Map virtual to real field</td>
            </tr>
            <tr>
                <td><code>reorderColumns()</code></td>
                <td>Change column order</td>
            </tr>
            <tr>
                <td><code>showOnlyColumns()</code></td>
                <td>Show specific columns only</td>
            </tr>
            <tr>
                <td><code>asLink()</code></td>
                <td>Convert column to clickable link</td>
            </tr>
            <tr>
                <td><code>asFile()</code></td>
                <td>Convert array column to file download links (auto-applied for form-type=file)</td>
            </tr>
            <tr>
                <td><code>asImage()</code></td>
                <td>Convert array column to image thumbnails (auto-applied for form-type=image)</td>
            </tr>
            <tr>
                <td rowspan="4">Actions</td>
                <td><code>setPage()</code></td>
                <td>Set page name for action links</td>
            </tr>
            <tr>
                <td><code>setDefaultActions()</code></td>
                <td>Auto-create Edit/Delete actions</td>
            </tr>
            <tr>
                <td><code>setActions()</code></td>
                <td>Configure custom row actions</td>
            </tr>
            <tr>
                <td><code>setBulkActions()</code></td>
                <td>Configure bulk actions</td>
            </tr>
            <tr>
                <td rowspan="4">Output</td>
                <td><code>getData()</code></td>
                <td>Get complete data array</td>
            </tr>
            <tr>
                <td><code>getTable()</code></td>
                <td>Get HTML table</td>
            </tr>
            <tr>
                <td><code>getResponse()</code></td>
                <td>Get HTML + additional data. This solution allows the Response to handle both the html version and json.</td>
            </tr>
            <tr>
                <td><code>render()</code></td>
                <td>Get HTML table directly</td>
            </tr>
            <tr>
                <td><code>getFunctionsResults()</code></td>
                <td>Get action results</td>
            </tr>
        </tbody>
    </table>

    <h2>Key Features Summary</h2>
    <ul>
        <li><strong>Fluent Interface</strong>: Method chaining for readable code</li>
        <li><strong>Action Functions</strong>: Execute custom logic on selected rows with return values for theme integration</li>
        <li><strong>Advanced Queries</strong>: Support for JOINs, WHERE conditions, and custom callbacks</li>
        <li><strong>Column Management</strong>: Hide, reorder, and customize column display</li>
        <li><strong>Styling Options</strong>: Comprehensive table and row styling capabilities</li>
    </ul>
</div>