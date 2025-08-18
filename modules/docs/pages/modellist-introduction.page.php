<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Introduction to ModelList
 * @category Dynamic Table
 * @order 5
 * @tags html table, ModelList, dynamic-table, introduction, basic-table, AbstractRouter, tabelle-dinamiche, gestione-flusso, base-tutorial
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
    <p>Here's how to create a basic table using ModelList directly without classes. Create a file inside modules called dynamic-table-example.controller.php</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Modules\DynamicTableExample;
use MilkCore\Route;
use MilkCore\Get;

Route::set(\'dynamic_table_example\', function() {
    // ModelList creation
    $model = new \MilkCore\ModelList(\'#__users\', \'table_users\');
    
    // Query building with parameters from request
    $query = $model->query_from_request();
    
    // Query execution
    $rows = Get::db()->get_results(...$query->get());
    $total = Get::db()->get_var(...$query->get_total());
  
    // Table generation
    $table_html = Get::theme_plugin(\'table\', [
        \'info\' => $model->get_list_structure($rows, \'id\'),
        \'rows\' => $rows,
        \'page_info\' => $page_info
    ]);
    if (($_REQUEST[\'page-output\'] ?? \'\') == \'json\') {
        Get::response_json([\'html\' => $table_html, \'success\' => \'true\', \'msg\'=>\'\']);
    } else {
        Get::theme_page(\'default\',\'\', $table_html);
    }
});'); ?></code></pre>

    <h2>Example with AbstractRouter</h2>
    <p>When using AbstractRouter, the process becomes even simpler:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
class UsersRouter extends \MilkCore\AbstractRouter {
    
    protected function action_home() {
        // The get_modellist_data method handles everything automatically
        $modellist_data = $this->get_modellist_data(\'table_users\');
        
        // Automatic table output with JSON/HTML handling
        $this->output_table_response(__DIR__.\'/views/list.page.php\', $modellist_data);
    }
}
?>'); ?></code></pre>

   
    <h2>Documentation Structure</h2>
    <p>The ModelList documentation is organized into several sections:</p>
    <ul>
        <li><strong><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/modellist-table-p1.page'); ?>">Dynamic Table System Documentation</a></strong>: Complete overview of the main classes</li>
        <li><strong><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/modellist-table-p2.page'); ?>">Automated Filters</a></strong>: How to implement filters and searches</li>
        <li><strong><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/modellist-table-p3.page'); ?>">Table Actions</a></strong>: Managing actions and CSV export</li>
    </ul>

</div>