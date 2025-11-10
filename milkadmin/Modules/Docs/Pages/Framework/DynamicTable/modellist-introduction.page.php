<?php
namespace Modules\Docs\Pages;
use App\Route;

/**
 * @title Introduction to ModelList
 * @guide framework
 * @order 5
 * @tags html table, ModelList, dynamic-table, introduction, basic-table, AbstractController, tabelle-dinamiche, gestione-flusso, base-tutorial
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Introduction to ModelList</h1>
    
    <p>ModelList is the core of the dynamic table management system in Milk Manager. It allows you to easily create tables with advanced features like pagination, sorting, and filters, without writing complex HTML code.</p>

    <h2>What is a ModelList?</h2>
    <p>A ModelList is a class that manages the entire flow of writing a dynamic table, from database query to final HTML generation. It facilitates the creation of professional administrative interfaces with just a few lines of code.</p>

    <h2>How the Flow Works</h2>
    <p>The ModelList system automatically handles the entire flow of a dynamic table:</p>
    <ol>
        <li><strong>Query Builder</strong>: Builds SQL queries based on request parameters</li>
        <li><strong>Column Structure</strong>: Defines how to display data</li>
        <li><strong>Pagination</strong>: Manages the division of results into pages</li>
        <li><strong>Sorting</strong>: Allows sorting by any column</li>
        <li><strong>Filters</strong>: Enables custom searches and filters</li>
        <li><strong>HTML Output</strong>: Generates the final HTML code of the table</li>
    </ol>

    <h2>Basic Example: Simple Table</h2>
    <p>Here's how to create a basic table using ModelList directly without classes. Create a file inside modules called dynamic-table-example.module.php</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Modules\DynamicTableExample;
Route::set(\'dynamic_table_example\', function() {
    // ModelList creation
    $model = new \App\Modellist\ModelList(\'#__users\', \'table_users\');
    
    // Query building with parameters from request
    $query = $model->queryFromRequest();
    
    // Query execution
    $rows = Get::db()->getResults(...$query->get());
    $total = Get::db()->getVar(...$query->getTotal());
  
    // Table generation
    $table_html = Get::themePlugin(\'table\', [
        \'info\' => $model->getListStructure($rows, \'id\'),
        \'rows\' => $rows,
        \'page_info\' => $page_info
    ]);
    if (($_REQUEST[\'page-output\'] ?? \'\') == \'json\') {
        Response::json([\'html\' => $table_html, \'success\' => \'true\', \'msg\'=>\'\']);
    } else {
        Response::themePage(\'default\',\'\', $table_html);
    }
});'); ?></code></pre>

  

    <h2>Documentation Structure</h2>
    <p>The ModelList documentation is organized into several sections:</p>
    <ul>
        <li><strong><a href="<?php echo Route::url('?page=docs&action=Framework/DynamicTable/modellist-table-p1'); ?>">Dynamic Table System Documentation</a></strong>: Complete overview of the main classes</li>
        <li><strong><a href="<?php echo Route::url('?page=docs&action=Framework/DynamicTable/modellist-table-p2'); ?>">Automated Filters</a></strong>: How to implement filters and searches</li>
        <li><strong><a href="<?php echo Route::url('?page=docs&action=Framework/DynamicTable/modellist-table-p3'); ?>">Table Actions</a></strong>: Managing actions and CSV export</li>
    </ul>

</div>