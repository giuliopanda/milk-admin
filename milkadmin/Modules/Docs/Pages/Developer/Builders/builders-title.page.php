<?php
namespace Modules\Docs\Pages;
/**
 * @title Title
 * @guide developer
 * @order 10
 * @tags TitleBuilder, fluent-interface, method-chaining, page-headers, responsive-design, buttons, search, PHP-classes, simplified-API
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>TitleBuilder Class Documentation</h1>

    <p>The TitleBuilder class provides a fluent interface for creating consistent page headers with titles, buttons, search functionality, and descriptions. It generates responsive layouts that work seamlessly across desktop and mobile devices.</p>

    <h2>System Overview</h2>
    <p>TitleBuilder simplifies the creation of page headers by providing:</p>
    <ul>
        <li><strong>Responsive Layout</strong>: Automatic mobile-friendly layouts using Bootstrap grid</li>
        <li><strong>Button Management</strong>: Easy addition of action buttons with various styles</li>
        <li><strong>Search Integration</strong>: Built-in search functionality with table filtering</li>
        <li><strong>Message Handling</strong>: Optional integration with MessagesHandler</li>
        <li><strong>Custom Content</strong>: Flexible right-side content areas</li>
    </ul>
    <p>It replaces the need to manually create complex responsive header layouts and ensures consistency across your application.</p>

    <h2>Basic Usage</h2>

    <h3>Constructor and Factory Method</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Standard constructor
$title = new \Builders\TitleBuilder('Page Title');

// Factory method (recommended)
$title = \Builders\TitleBuilder::create('Page Title');

// Empty constructor, set title later
$title = \Builders\TitleBuilder::fill()->title('My Page');
    </code></pre>

    <h3>Simple Title Creation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Basic title with description
echo (new \Builders\TitleBuilder('Posts Management'))
    ->description('Manage your blog posts and articles')
    ->render();

// With a single button
echo \Builders\TitleBuilder::create('Users')
    ->addButton('Add New User', '?page=users&action=add', 'primary')
    ->render();
    </code></pre>

    <h2>Button Management</h2>

    <h3>Adding Single Buttons</h3>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$title = \Builders\TitleBuilder::create('Posts')
    // Link button
    ->addButton('Add New', '?page=posts&action=add', 'primary')
    
    // Button with custom CSS class
    ->addButton('Import', '?page=posts&action=import', 'secondary', 'btn-lg')

    // Create a link that will be called in ajax. The response must be in json format.
    ->addFetchButton('Add New', '?page=posts&action=add', 'primary', '', 'get')
    or
    ->addFetchButton('Add New', '?page=posts&action=add', 'primary', '', 'post')
    
    // Click button with JavaScript
    ->addClickButton('Export All', 'exportPosts()', 'success')
    
    ->render();
    </code></pre>
    <p>For information on links transformed into 'ajax' see <a href="?page=docs&action=Framework/Theme/theme-javascript-fetch-link">javascript-fetch-link</a></p>

    <h3>Adding Multiple Buttons</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$title = \Builders\TitleBuilder::create('Dashboard')
    ->addButtons([
        [
            'title' => 'Add New Post',
            'link' => '?page=posts&action=add',
            'color' => 'primary'
        ],
        [
            'title' => 'Settings',
            'link' => '?page=settings',
            'color' => 'secondary',
            'class' => 'btn-outline-secondary'
        ],
        [
            'title' => 'Refresh Data',
            'click' => 'refreshDashboard()',
            'color' => 'info'
        ]
    ])
    ->render();
    </code></pre>

    <h2>Search Functionality</h2>

    <h3>Basic Search Integration</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Simple search that integrates with TableBuilder
$title = \Builders\TitleBuilder::create('Posts')
    ->addButton('Add New', '?page=posts&action=add', 'primary')
    ->addSearch('posts_table', 'Search posts...', 'Search')
    ->render();

// The search will automatically work with TableBuilder:
$table = \Builders\TableBuilder::create($model, 'posts_table')
    ->filterLike('search', 'title')  // This matches the search above
    ->getTable();
    </code></pre>

    <h3>Custom Search HTML</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Override with custom search HTML
$custom_search = '<?php echo htmlentities('<div class="input-group">
    <input type="text" class="form-control js-milk-filter-onchange" 
           data-filter-id="posts_table" data-filter-type="search" 
           placeholder="Type to search...">
    <button class="btn btn-outline-secondary" type="button">
        <i class="bi bi-search"></i>
    </button>
</div>'); ?>';

$title = \Builders\TitleBuilder::create('Posts')
    ->setSearchHtml($custom_search)
    ->render();

// The custom HTML will be properly rendered using _ph() internally
echo $title;
    </code></pre>

    <h2>Layout and Responsive Design</h2>

    <h3>Desktop vs Mobile Layout</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// This creates a responsive layout:
// Desktop: Title + Buttons (left) | Search (right)
// Mobile:  Title + Buttons (full width)
//          Search (full width, next row)

$title = \Builders\TitleBuilder::create('Products')
    ->addButton('Add Product', '?page=products&action=add', 'primary')
    ->addButton('Categories', '?page=categories', 'secondary')
    ->addSearch('products_table', 'Search products...', 'Search')
    ->description('Manage your product inventory')
    ->render();
    </code></pre>

    <h3>Custom Right Content</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Add custom content to the right area
$custom_content = '<?php echo htmlentities('
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                data-bs-toggle="dropdown">
            Actions
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="?action=export">Export Data</a></li>
            <li><a class="dropdown-item" href="?action=import">Import Data</a></li>
        </ul>
    </div>'); ?>';

$title = \Builders\TitleBuilder::create('Reports')
    ->addRightContent($custom_content)
    ->render();
    </code></pre>

    <h2>Message Integration</h2>

    <h3>Controlling Message Display</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Messages are included by default
$title = \Builders\TitleBuilder::create('Users')
    ->description('Manage system users')
    ->render();
// This will include MessagesHandler::displayMessages()

// Disable messages if you want to show them elsewhere
$title = \Builders\TitleBuilder::create('Settings')
    ->includeMessages(false)
    ->render();

// Show messages manually later
\App\MessagesHandler::displayMessages();
    </code></pre>

    <h2>Method Chaining and Fluent Interface</h2>

    <h3>Clearing and Resetting</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$title = \Builders\TitleBuilder::create('Dynamic Title')
    ->addButton('Button 1', '#', 'primary')
    ->addButton('Button 2', '#', 'secondary')
    ->addSearch('table1', 'Search...')
    
    // Clear individual elements
    ->clearButtons()     // Remove all buttons
    ->clearSearch()      // Remove search
    ->clearRight()       // Remove right content
    
    // Add new content
    ->addButton('New Button', '?page=new', 'success')
    ->render();
    </code></pre>

    <h2>Complete Examples</h2>

    <h3>Blog Management Page</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Complete blog management header
$title = \Builders\TitleBuilder::create('Blog Posts')
    ->addButtons([
        ['title' => 'New Post', 'link' => '?page=posts&action=add', 'color' => 'primary'],
        ['title' => 'Categories', 'link' => '?page=categories', 'color' => 'secondary'],
        ['title' => 'Bulk Import', 'click' => 'showImportModal()', 'color' => 'info']
    ])
    ->addSearch('posts_table', 'Search posts by title or content...', 'Search')
    ->description('Create, edit and manage your blog posts. Use the search to find specific posts or filter by category.')
    ->render();

echo $title;

// Matching table with filters
$table = \Builders\TableBuilder::create($posts_model, 'posts_table')
    ->filterLike('search', 'title', 'both')
    ->filterLike('search', 'content', 'both', 'OR')
    ->getTable();

echo $table;
    </code></pre>

    <h3>User Management with Advanced Actions</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Advanced user management header
$stats_html = '<?php echo htmlentities('
    <div class="d-flex align-items-center text-body-secondary small">
        <span class="me-3">
            <i class="bi bi-people-fill text-primary"></i> 
            '); ?>' . $user_count . '<?php echo htmlentities(' users
        </span>
        <span>
            <i class="bi bi-person-check-fill text-success"></i> 
            '); ?>' . $active_count . '<?php echo htmlentities(' active
        </span>
    </div>'); ?>';

$actions_dropdown = '<?php echo htmlentities('
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle btn-sm" 
                type="button" data-bs-toggle="dropdown">
            <i class="bi bi-gear"></i> Tools
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="?action=export">
                <i class="bi bi-download"></i> Export Users
            </a></li>
            <li><a class="dropdown-item" href="?action=import">
                <i class="bi bi-upload"></i> Import Users
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#" onclick="cleanupUsers()">
                <i class="bi bi-trash"></i> Cleanup Inactive
            </a></li>
        </ul>
    </div>'); ?>';

$title = \Builders\TitleBuilder::create('User Management')
    ->addButton('Add New User', '?page=users&action=add', 'primary')
    ->addRightContent($stats_html . '<?php echo htmlentities('<div class="ms-3">'); ?>' . $actions_dropdown . '<?php echo htmlentities('</div>'); ?>')
    ->description('Manage system users, roles and permissions. Search by name, email or username.')
    ->render();

echo $title;
    </code></pre>

    <h3>E-commerce Product Management</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// E-commerce product management
$quick_actions = '<?php echo htmlentities('
    <div class="btn-group" role="group">
        <button type="button" class="btn btn-outline-info btn-sm" onclick="syncInventory()">
            <i class="bi bi-arrow-clockwise"></i> Sync
        </button>
        <button type="button" class="btn btn-outline-warning btn-sm" onclick="showBulkEdit()">
            <i class="bi bi-pencil-square"></i> Bulk Edit
        </button>
        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportProducts()">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export
        </button>
    </div>'); ?>';

$title = \Builders\TitleBuilder::create('Products')
    ->addButtons([
        [
            'title' => '<?php echo htmlentities('<i class="bi bi-plus-circle"></i> Add Product'); ?>',
            'link' => '?page=products&action=add',
            'color' => 'primary'
        ],
        [
            'title' => '<?php echo htmlentities('<i class="bi bi-tags"></i> Categories'); ?>',
            'link' => '?page=categories',
            'color' => 'secondary'
        ]
    ])
    ->addSearch('products_table', 'Search by name, SKU or description...', 'Search')
    ->addRightContent($quick_actions)
    ->description('Manage your product catalog. Add new products, organize categories, and track inventory levels.')
    ->render();

echo $title;

// Enhanced table with multiple search filters
$table = \Builders\TableBuilder::create($products_model, 'products_table')
    ->filterLike('search', 'name', 'both')
    ->filterLike('search', 'sku', 'both', 'OR')
    ->filterLike('search', 'description', 'both', 'OR')
    ->setActions([
        'edit' => [
            'label' => '<?php echo htmlentities('<i class="bi bi-pencil"></i>'); ?>',
            'link' => '?page=products&action=edit&id=%id%',
            'class' => 'btn btn-sm btn-outline-primary'
        ],
        'view' => [
            'label' => '<?php echo htmlentities('<i class="bi bi-eye"></i>'); ?>',
            'link' => '/products/{slug}',
            'target' => '_blank',
            'class' => 'btn btn-sm btn-outline-secondary'
        ]
    ])
    ->getTable();

echo $table;
    </code></pre>

    <h3>Simple Edit Page Header</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Simple edit page (like in test-modellist.module.php)
$title = _absint($_REQUEST['id'] ?? 0) > 0 ? 'Edit Post' : 'Add Post';

echo \Builders\TitleBuilder::create($title)
    ->description('Fill in the form below to create or update a blog post.')
    ->addButton('Cancel', '?page=posts', 'secondary')
    ->render();

// Then your form follows...
    </code></pre>

    <h2>Integration with TableBuilder</h2>

    <h3>Perfect Integration Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// This shows how TitleBuilder and TableBuilder work together
class PostsModule {
    public function listPosts() {
        // Title with search
        $title = \Builders\TitleBuilder::create('Posts')
            ->addButton('Add New', '?page=posts&action=add', 'primary')
            ->addSearch('posts_table', 'Search posts...', 'Search')
            ->description('Manage your blog posts')
            ->render();
        
        // Table that responds to the search
        $table = \Builders\TableBuilder::create($this->model, 'posts_table')
            ->filterLike('search', 'title')  // Matches the search filter-type
            ->filterEquals('status', 'status')
            ->setActions([
                'edit' => ['label' => 'Edit', 'link' => '?page=posts&action=edit&id=%id%']
            ])
            ->getHtml();
        
        // Render the page
        echo $title;
        echo $table['html'];
    }
}
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
                <td rowspan="2">Title</td>
                <td><code>title(string $title)</code></td>
                <td>Set the main title text</td>
            </tr>
            <tr>
                <td><code>description(string $text)</code></td>
                <td>Add description below title</td>
            </tr>
            <tr>
                <td rowspan="4">Buttons</td>
                <td><code>addButton()</code></td>
                <td>Add link button</td>
            </tr>
            <tr>
                <td><code>addClickButton()</code></td>
                <td>Add JavaScript click button</td>
            </tr>
            <tr>
                <td><code>addButtons(array)</code></td>
                <td>Add multiple buttons at once</td>
            </tr>
            <tr>
                <td><code>clearButtons()</code></td>
                <td>Remove all buttons</td>
            </tr>
            <tr>
                <td rowspan="4">Search & Content</td>
                <td><code>addSearch()</code></td>
                <td>Add table search functionality</td>
            </tr>
            <tr>
                <td><code>setSearchHtml()</code></td>
                <td>Set custom search HTML</td>
            </tr>
            <tr>
                <td><code>addRightContent()</code></td>
                <td>Add custom right-side content</td>
            </tr>
            <tr>
                <td><code>clearSearch()</code></td>
                <td>Remove search functionality</td>
            </tr>
            <tr>
                <td rowspan="1">Messages</td>
                <td><code>includeMessages(bool)</code></td>
                <td>Control MessagesHandler inclusion</td>
            </tr>
            <tr>
                <td rowspan="3">Output</td>
                <td><code>render()</code></td>
                <td>Generate and return HTML</td>
            </tr>
            <tr>
                <td><code>getHtml()</code></td>
                <td>Alias for render()</td>
            </tr>
            <tr>
                <td><code>__toString()</code></td>
                <td>Auto-render when used as string</td>
            </tr>
            <tr>
                <td rowspan="1">Factory</td>
                <td><code>create(string $title)</code></td>
                <td>Static factory method</td>
            </tr>
        </tbody>
    </table>

    <h2>Best Practices</h2>
    
    <h3>Responsive Design</h3>
    <ul>
        <li>The TitleBuilder automatically handles mobile layouts - search moves to full-width second row</li>
        <li>Buttons wrap gracefully when there are many of them</li>
        <li>Use <code>mb-2</code> class on buttons for proper spacing when wrapped</li>
    </ul>

    <h3>Search Integration</h3>
    <ul>
        <li>Always match the <code>data-filter-id</code> in TitleBuilder with TableBuilder's table ID</li>
        <li>Use descriptive placeholder text in search inputs</li>
        <li>Consider using <code>filter_like</code> with multiple fields for comprehensive search</li>
    </ul>

    <h3>Button Organization</h3>
    <ul>
        <li>Primary actions should use 'primary' color</li>
        <li>Secondary actions should use 'secondary' or 'outline-*' variants</li>
        <li>Destructive actions should use 'danger' color</li>
        <li>Group related actions together when adding multiple buttons</li>
    </ul>

    <h2>Next Steps</h2>
    <p>Now that you understand TitleBuilder, you can explore:</p>
    <ul>
        <li><strong>TableBuilder</strong>: Create data tables that integrate with TitleBuilder search</li>
        <li><strong>SearchBuilder</strong>: Advanced search forms with multiple filters</li>
        <li><strong>Form Builders</strong>: Create consistent form layouts</li>
        <li><strong>Theme Integration</strong>: Customize the appearance across your application</li>
    </ul>
</div>