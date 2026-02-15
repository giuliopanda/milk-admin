<?php
namespace Modules\Docs\Pages;
/**
 * @title Table
 * @guide developer
 * @order 10
 * @tags TableBuilder, fluent-interface, field-first, method-chaining, query-builder, table-management, columns, actions, styling, PHP-classes, simplified-API
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>TableBuilder Class Documentation</h1>

    <p>The TableBuilder class provides a fluent, field-first interface for creating and managing dynamic tables. It uses a modern chaining pattern where you configure each field individually, making your code more readable and maintainable.</p>

    <h2>System Overview</h2>
    <p>TableBuilder acts as a wrapper that combines:</p>
    <ul>
        <li><strong>ModelList</strong>: Database connection and query management</li>
        <li><strong>ListStructure</strong>: Column structure and configuration</li>
        <li><strong>PageInfo</strong>: Pagination and display information</li>
    </ul>
    <p>It provides a single, chainable API for building tables with less code and better readability.</p>

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
                <td><code>select(array|string $columns)</code></td>
                <td>Select specific columns</td>
            </tr>
            <tr>
                <td><code>where(string $condition, array $params = [], string $operator = 'AND')</code></td>
                <td>Add WHERE condition</td>
            </tr>
            <tr>
                <td><code>whereIn(string $field, array $values)</code></td>
                <td>WHERE IN clause</td>
            </tr>
            <tr>
                <td><code>whereLike(string $field, string $value, string $position = 'both')</code></td>
                <td>LIKE search condition</td>
            </tr>
            <tr>
                <td><code>whereBetween(string $field, $min, $max)</code></td>
                <td>BETWEEN condition</td>
            </tr>
            <tr>
                <td><code>join(string $table, string $condition, string $type = 'INNER')</code></td>
                <td>JOIN tables</td>
            </tr>
            <tr>
                <td><code>leftJoin(string $table, string $condition)</code></td>
                <td>LEFT JOIN tables</td>
            </tr>
            <tr>
                <td><code>rightJoin(string $table, string $condition)</code></td>
                <td>RIGHT JOIN tables</td>
            </tr>
            <tr>
                <td><code>groupBy(string $field)</code></td>
                <td>GROUP BY clause</td>
            </tr>
            <tr>
                <td><code>having(string $condition, array $params = [])</code></td>
                <td>HAVING clause</td>
            </tr>
            <tr>
                <td><code>orderBy(string $field, string $direction = 'ASC')</code></td>
                <td>Set ordering</td>
            </tr>
            <tr>
                <td><code>limit(int $limit)</code></td>
                <td>Set row limit</td>
            </tr>
            <tr>
                <td><code>queryCustomCallback(callable $callback)</code></td>
                <td>Custom query logic</td>
            </tr>
            <tr>
                <td rowspan="15">Field Configuration<br>(Field-First)</td>
                <td><code>field(string $key)</code></td>
                <td>Start configuring a field</td>
            </tr>
            <tr>
                <td><code>label(string $label)</code></td>
                <td>Set field label</td>
            </tr>
            <tr>
                <td><code>type(string $type)</code></td>
                <td>Set field type</td>
            </tr>
            <tr>
                <td><code>options(array $options)</code></td>
                <td>Set select options</td>
            </tr>
            <tr>
                <td><code>fn(callable $fn)</code></td>
                <td>Custom formatter function</td>
            </tr>
            <tr>
                <td><code>showIf(string $expression, mixed $elseValue = '')</code></td>
                <td>Conditionally print field value (evaluated before <code>fn()</code>)</td>
            </tr>
            <tr>
                <td><code>link(string $link, array $options = [])</code></td>
                <td>Convert to clickable link</td>
            </tr>
            <tr>
                <td><code>file(array $options = [])</code></td>
                <td>Display as file download links</td>
            </tr>
            <tr>
                <td><code>image(array $options = [])</code></td>
                <td>Display as image thumbnails</td>
            </tr>
            <tr>
                <td><code>truncate(int $length, string $suffix = '...')</code></td>
                <td>Truncate text with suffix</td>
            </tr>
            <tr>
                <td><code>hide()</code></td>
                <td>Hide field</td>
            </tr>
            <tr>
                <td><code>noSort()</code></td>
                <td>Disable sorting</td>
            </tr>
            <tr>
                <td><code>sortBy(string $realField)</code></td>
                <td>Map to real database field</td>
            </tr>
            <tr>
                <td><code>class(string $classes)</code></td>
                <td>Set CSS classes (see Styling docs)</td>
            </tr>
            <tr>
                <td><code>moveBefore(string $fieldName)</code></td>
                <td>Move current field before another field</td>
            </tr>
            <tr>
                <td rowspan="3">Column Management</td>
                <td><code>reorderColumns(array $order)</code></td>
                <td>Reorder all columns by array of column names</td>
            </tr>
            <tr>
                <td><code>hideColumns(array $keys)</code></td>
                <td>Hide multiple columns at once</td>
            </tr>
            <tr>
                <td><code>resetFields()</code></td>
                <td>Hide all existing columns from the model</td>
            </tr>
            <tr>
                <td rowspan="5">Actions</td>
                <td><code>setPage(string $page)</code></td>
                <td>Set page name for action links</td>
            </tr>
            <tr>
                <td><code>setDefaultActions(array $customActions = [])</code></td>
                <td>Auto-create Edit/Delete actions</td>
            </tr>
            <tr>
                <td><code>addAction(string $key, array $config)</code></td>
                <td>Add a single row action</td>
            </tr>
            <tr>
                <td><code>setActions(array $actions)</code></td>
                <td>Configure custom row actions</td>
            </tr>
            <tr>
                <td><code>setBulkActions(array $actions)</code></td>
                <td>Configure bulk actions</td>
            </tr>
            <tr>
                <td rowspan="5">Output</td>
                <td><code>getData()</code></td>
                <td>Get complete data array</td>
            </tr>
            <tr>
                <td><code>getTable()</code></td>
                <td>Get HTML table</td>
            </tr>
            <tr>
                <td><code>getResponse()</code></td>
                <td>Get HTML + additional data (handles JSON automatically)</td>
            </tr>
            <tr>
                <td><code>render()</code></td>
                <td>Get HTML table directly</td>
            </tr>
            <tr>
                <td><code>getFunctionsResults()</code></td>
                <td>Get action callback results</td>
            </tr>
            <tr>
                <td rowspan="3">Display Controls</td>
                <td><code>setSmallText(bool $enabled = true)</code></td>
                <td>Reduce text size for table, bulk row and pagination controls</td>
            </tr>
            <tr>
                <td><code>setShowHeader(bool $show)</code></td>
                <td>Show or hide table header rendering (<code>&lt;thead&gt;</code>)</td>
            </tr>
            <tr>
                <td><code>setPagination(bool $enabled)</code></td>
                <td>Force pagination visibility on/off</td>
            </tr>
        </tbody>
    </table>

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
    // Field-first pattern: configure title field
    ->field('title')
        ->link('?page='.$this->page.'&action=edit&id=%id%');

$response = $tableBuilder->getResponse();
Response::render('view.php', $response);

// VIEW (view.php)
// &lt;?php echo $table_html; ?&gt;  ← Just this! Don't wrap it!</code></pre>

    <h2>Query Building Methods</h2>

    <p>TableBuilder supports all standard query operations. Here are the most common:</p>

    <div class="alert alert-info">
        <h5><i class="bi bi-gear"></i> Default Pagination Limit</h5>
        <p>You can set a global default for the number of rows per page by defining <code>$conf['page_info_limit']</code> in your <code>milkadmin_local/config.php</code> file:</p>
        <pre class="mb-2"><code class="language-php">$conf['page_info_limit'] = 50;  // Default rows per page</code></pre>
        <p class="mb-0">This will apply to all tables unless overridden with the <code>limit()</code> method. See <a href="?page=docs&action=Developer/Advanced/milkadmin-local" class="alert-link">Milkadmin Local Configuration</a> for more details.</p>
    </div>

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
        <p class="mb-0"><strong>📘 For comprehensive query documentation:</strong> See <a href="?page=docs&action=Developer/Model/abstract-model-queries" class="alert-link">Abstract Model - Query Methods</a></p>
    </div>

    <h2>Field-First Pattern</h2>

    <p>The field-first pattern provides a clean, readable way to configure each table column. Instead of calling methods with the field name as a parameter, you first select the field with <code>field()</code> and then chain configuration methods.</p>

    <div class="alert alert-info">
        <h5><i class="bi bi-lightbulb"></i> Why Field-First?</h5>
        <ul class="mb-0">
            <li><strong>Better Readability</strong>: All configurations for a field are grouped together</li>
            <li><strong>Clearer Intent</strong>: Easy to see what each field does at a glance</li>
            <li><strong>IDE Support</strong>: Better autocomplete and method suggestions</li>
            <li><strong>Consistent</strong>: Matches modern fluent API patterns</li>
        </ul>
    </div>

    <h3>Basic Field Configuration</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    // Configure each field with method chaining
    ->field('id')
        ->label('ID')
        ->hide()  // Hide this field

    ->field('title')
        ->label('Article Title')
        ->link('?page=posts&action=edit&id=%id%')
        ->truncate(80)

    ->field('status')
        ->label('Status')
        ->type('select')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived'
        ])

    ->field('created_at')
        ->label('Publication Date')
        ->type('datetime')

    ->getTable();</code></pre>

    <h3>Available Field Methods</h3>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>label(string $label)</code></td>
                <td>Set display label for the field</td>
                <td><code>->field('created_at')->label('Published')</code></td>
            </tr>
            <tr>
                <td><code>type(string $type)</code></td>
                <td>Set field type (text, select, date, html, etc.)</td>
                <td><code>->field('status')->type('select')</code></td>
            </tr>
            <tr>
                <td><code>options(array $options)</code></td>
                <td>Set options for select fields</td>
                <td><code>->field('status')->options(['active' => 'Active'])</code></td>
            </tr>
            <tr>
                <td><code>fn(callable $fn)</code></td>
                <td>Custom formatter function</td>
                <td><code>->field('name')->fn(fn($row) => strtoupper($row['name']))</code></td>
            </tr>
            <tr>
                <td><code>showIf(string $expression, mixed $elseValue = '')</code></td>
                <td>Conditionally print the value using an expression (runs before <code>fn()</code>)</td>
                <td><code>->field('name')->showIf('[STATUS] == "active"', '-')</code></td>
            </tr>
            <tr>
                <td><code>hide()</code></td>
                <td>Hide field from display</td>
                <td><code>->field('password')->hide()</code></td>
            </tr>
            <tr>
                <td><code>noSort()</code></td>
                <td>Disable sorting for this field</td>
                <td><code>->field('actions')->noSort()</code></td>
            </tr>
            <tr>
                <td><code>sortBy(string $real_field)</code></td>
                <td>Map virtual field to database field for sorting</td>
                <td><code>->field('doctor_name')->sortBy('doctor.name')</code></td>
            </tr>
        </tbody>
    </table>

    <h3>Conditional Rendering - showIf() method</h3>
    <p>
        Use <code>showIf()</code> to decide if a cell should be printed or replaced with a fallback.
        The condition is evaluated using the same mini-language of <code>App\\ExpressionParser</code>.
    </p>
    <div class="alert alert-info">
        <ul class="mb-0">
            <li><strong>Runs first</strong>: if the condition is false, the column formatter (<code>fn()</code>) is <strong>not executed</strong></li>
            <li><strong>Row parameters</strong>: use <code>[FIELD]</code> to access row values (case-insensitive: <code>[STATUS]</code>, <code>[status]</code>)</li>
            <li><strong>Dot notation</strong>: supports keys like <code>[user.name]</code> when the value is available/extractable</li>
            <li><strong>Fallback</strong>: the 2nd argument is the value to display when the condition is false (default: empty string)</li>
        </ul>
    </div>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'users_table')
    ->field('NAME')
        ->type('html')
        ->fn(function($rowModel) {
            // executed ONLY when showIf() is true
            return '&lt;strong&gt;' . $rowModel->NAME . '&lt;/strong&gt;';
        })
        ->showIf('[STATUS] == "active"', '&lt;span class="text-muted"&gt;Hidden&lt;/span&gt;');
</code></pre>

    <p class="mb-2">
        <strong>Optional:</strong> the fallback can also be a callable (same signature used by <code>fn()</code>),
        useful for dynamic placeholders.
    </p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->field('NAME')
    ->fn(fn($rowModel) => 'CUSTOM-' . $rowModel->ID)
    ->showIf('[STATUS] == "active"', fn($rowModel) => 'N/A (#' . $rowModel->ID . ')');
</code></pre>

    <h2>Display Formatting Methods</h2>

    <h3>Links - link() method</h3>
    <p>The <code>link()</code> method converts a field to a clickable link with placeholder support.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    // Basic link
    ->field('title')
        ->link('?page=posts&action=edit&id=%id%')

    // Link with options
    ->field('author')
        ->link('/profile/%author_id%', [
            'target' => '_blank',
            'class' => 'text-primary fw-bold'
        ])

    // Multiple placeholders
    ->field('full_name')
        ->link('/user/%id%?tab=profile&ref=%category%')

    // Link with fetch (AJAX loading)
    ->field('lessons')
        ->link('?page=courses&action=lessons&entity_id=%id%', [
            'data-fetch' => 'post'
        ])

    ->getTable();

// Supported placeholders:
// %id% - Primary key or 'id' field
// %field_name% - Any column value (e.g., %title%, %status%, %created_at%)

// Available options:
// 'target' => '_blank' | '_self' | '_parent' | '_top'
// 'class' => 'css-classes-here'
// 'data-fetch' => 'post' | 'get' - Enable AJAX loading
    </code></pre>
    <p class="mb-2"><strong>Note:</strong> When <code>activeFetch()</code> is enabled, links are treated as fetch links by default. To force a normal navigation, pass <code>['data-fetch' => false]</code>. If you have a normal table, you can opt-in a single link to fetch with <code>['data-fetch' =&gt; 'get']</code> or <code>['data-fetch' =&gt; 'post']</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// activeFetch() table, force normal link
->field('socio.NOME')
    ->link('?page=soci&action=edit&id=%MATRIC_SOCIO%', ['data-fetch' => false])

// normal table, opt-in fetch for a single link
->field('socio.NOME')
    ->link('?page=soci&action=edit&id=%MATRIC_SOCIO%', ['data-fetch' => 'get'])
    </code></pre>

    <h3>Files - file() method</h3>
    <p>The <code>file()</code> method converts array fields containing file data into download links. <strong>Automatically applied</strong> when model has <code>type=array</code> and <code>form-type=file</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'documents_table')
    // Basic usage
    ->field('attachments')
        ->file()

    // With custom options
    ->field('files')
        ->file([
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
    </code></pre>

    <h3>Images - image() method</h3>
    <p>The <code>image()</code> method displays image thumbnails. <strong>Automatically applied</strong> when model has <code>type=array</code> and <code>form-type=image</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'products_table')
    // Basic usage
    ->field('photos')
        ->image()

    // With custom options
    ->field('gallery')
        ->image([
            'size' => 80,              // Thumbnail size in pixels
            'class' => 'rounded',      // CSS classes
            'lightbox' => true,        // Clickable links
            'max_images' => 3          // Limit displayed images
        ])

    ->getTable();

// Expected data format:
// $row->photos = [
//     ['url' => 'media/photo1.jpg', 'name' => 'Product Image 1'],
//     ['url' => 'media/photo2.jpg', 'name' => 'Product Image 2']
// ];

// Available options:
// 'size' => 50                    // Width/height in pixels
// 'class' => ''                   // CSS classes
// 'lightbox' => false             // Enable clickable links
// 'max_images' => null            // Show "+N" for remaining
    </code></pre>

    <h3>Text Truncation - truncate() method</h3>
    <p>The <code>truncate()</code> method limits text length, adding a suffix when exceeded.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    // Basic truncation
    ->field('description')
        ->truncate(100)

    // Custom suffix
    ->field('title')
        ->truncate(50, '…')

    ->field('content')
        ->truncate(200, ' [read more]')

    ->getTable();

// Features:
// - UTF-8 safe (uses mb_substr and mb_strlen)
// - Applied after all formatting
// - Only truncates strings exceeding the specified length
// - Can be combined with other methods
    </code></pre>

    <h2>Complete Example</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    // Query configuration
    ->where('status != ?', ['deleted'])
    ->orderBy('created_at', 'DESC')
    ->limit(20)

    // Field configurations with field-first pattern
    ->field('id')
        ->label('ID')
        ->hide()

    ->field('title')
        ->label('Article Title')
        ->link('?page=posts&action=edit&id=%id%')
        ->truncate(80)

    ->field('author_name')
        ->label('Author')
        ->fn(function($row) {
            return $row['first_name'] . ' ' . $row['last_name'];
        })

    ->field('category')
        ->label('Category')
        ->type('select')
        ->options([
            'tech' => 'Technology',
            'news' => 'News',
            'blog' => 'Blog'
        ])

    ->field('status')
        ->label('Status')

    ->field('photos')
        ->label('Images')
        ->image(['size' => 60, 'max_images' => 2])

    ->field('created_at')
        ->label('Publication Date')
        ->type('datetime')

    // Actions
    ->setPage('posts')
    ->setDefaultActions()

    ->getTable();</code></pre>

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

    <p><strong>See: <a href="?page=docs&action=row-actions">Row Actions Documentation</a></strong></p>

    <h3>Bulk Actions</h3>
    <p>For operations on multiple rows at once, use bulk actions.</p>
    <p><strong>See: <a href="?page=docs&action=bulk-actions">Bulk Actions Documentation</a></strong></p>

    <h2>Table Styling</h2>
    <p>TableBuilder provides comprehensive styling options including field-specific styling with conditional classes.</p>
    <p><strong>See: <a href="?page=docs&action=styling">Table Styling Documentation</a></strong></p>

    <h2>Display Controls</h2>
    <p>Use display controls to tune table rendering behavior without changing data/query logic.</p>

    <h3>Smaller Table Text - setSmallText()</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    ->setSmallText()         // Equivalent to ->setSmallText(true)
    ->getTable();</code></pre>

    <h3>Hide Table Header - setShowHeader()</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    ->setShowHeader(false)   // Do not render &lt;thead&gt;
    ->getTable();</code></pre>

    <div class="alert alert-warning">
        <p class="mb-1"><strong>When header is hidden:</strong></p>
        <ul class="mb-0">
            <li>Column sorting UI in header is not available</li>
            <li>The header "select all" checkbox is not rendered</li>
            <li>Row checkboxes and bulk actions continue to work normally</li>
        </ul>
    </div>

    <h2>Output Methods</h2>

    <h3>Understanding render() vs getResponse()</h3>

    <div class="alert alert-info">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> When to use each method</h5>
        <p><strong><code>render()</code> or <code>getTable()</code></strong> - Use when you have NO row actions or bulk actions with callbacks:</p>
        <pre class="mb-2"><code class="language-php">// Simple table - no actions or only link actions
$html = TableBuilder::create($model, 'table_id')
    ->field('title')->link('?page=posts&action=edit&id=%id%')
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

    <h2>Key Features Summary</h2>
    <ul>
        <li><strong>Field-First Pattern</strong>: Configure each field with clear, grouped method chains</li>
        <li><strong>Fluent Interface</strong>: Method chaining for readable code</li>
        <li><strong>Action Functions</strong>: Execute custom logic on selected rows with return values for theme integration</li>
        <li><strong>Advanced Queries</strong>: Support for JOINs, WHERE conditions, and custom callbacks</li>
        <li><strong>Flexible Display</strong>: Links, images, files, truncation, custom formatters</li>
        <li><strong>Styling Options</strong>: Comprehensive table and field-specific styling with conditional classes</li>
    </ul>
</div>
