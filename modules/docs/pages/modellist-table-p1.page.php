<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Build a base
 * @category Dynamic Table
 * @order 10
 * @tags ModelList, ListStructure, PageInfo, dynamic-table, pagination, sorting, filtering, query, database, table-management, PHP-classes, fluent-interface 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Dynamic Table System Documentation</h1>

    <p>This system helps manage table by simplifying pagination, sorting, and filtering.</p>

    <h2>System Overview</h2>
    <p>The dynamic table system is based on three main classes:</p>
    <ul>
        <li><strong>ModelList</strong>: Manages the database connection and generates queries based on request parameters.</li>
        <li><strong>ListStructure</strong>: Manages the column structure of a table.</li>
        <li><strong>PageInfo</strong>: Manages pagination and display information.</li>
    </ul>

    <h2>1. ModelList Class</h2>
    <p>The ModelList class helps manage the display of HTML tables from a MySQL table. It allows you to define the column structure and manage data sorting and pagination.</p>

    <h3>Public Functions of ModelList</h3>

    <h4>__construct($table, $table_id = null)</h4>
    <p>Class constructor. Initializes the table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Creates a ModelList instance for the specified table
$model = new \MilkCore\ModelList('#__dynamic_example');

// Creates an instance with a custom ID
$model = new \MilkCore\ModelList('#__dynamic_example', 'my-table-id');
    </code></pre>

    <h4>set_list_structure($list_structure)</h4>
    <p>Sets the column structure of the table. Accepts an array or a ListStructure instance.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Usage with an array
$model->set_list_structure([
    'id' => ['type' => 'text', 'label' => 'ID', 'primary' => true],
    'title' => ['type' => 'text', 'label' => 'Title']
]);

// Usage with a ListStructure instance
$listStructure = new \MilkCore\ListStructure();
$listStructure->set_column('id', 'ID', 'text', true, true);
$listStructure->set_column('title', 'Title', 'text', true, false);
$model->set_list_structure($listStructure);
    </code></pre>

    <h4>get_list_structure($rows, $primary_key)</h4>
    <p>Returns a ListStructure instance with the table's column structure. If not set, it is automatically generated based on the table fields.</p>
    <p>It is used to generate the table structure from the database table. If $rows is not set, it is automatically generated based on the table fields.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$row_info = $model->get_list_structure($rows, 'id');
// Now $row_info is a ListStructure instance that can be modified
$row_info->set_column('status', 'Status', 'select', false, false, ['0' => 'Draft', '1' => 'Active']);
    </code></pre>

    <h4>set_no_order()</h4>
    <p>Disables sorting for all table columns.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model->set_no_order();
    </code></pre>

    <h4>set_limit($limit)</h4>
    <p>Sets the row limit per page.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model->set_limit(20);
    </code></pre>

    <h4>set_order($order_field, $order_dir = 'desc')</h4>
    <p>Sets the default sorting order for the table. This defines which field and direction will be used for sorting when no user sorting is applied.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Set default sorting by creation date, newest first
$model->set_order('created_at', 'desc');

// Set default sorting by title, alphabetical order
$model->set_order('title', 'asc');

// Set default sorting by ID, descending order (default direction)
$model->set_order('id');
    </code></pre>

    <h4>get_table_structure()</h4>
    <p>Returns the MySQL table structure.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$table_structure = $model->get_table_structure();
// Now $table_structure contains all the information about the table fields
    </code></pre>

    <h4>set_primary_key($primary_key)</h4>
    <p>Manually sets the primary key of the table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model->set_primary_key('custom_id');
    </code></pre>

    <h4>query_from_request($request = null)</h4>
    <p>Creates a query based on the request parameters (sorting, pagination).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Standard usage with $_REQUEST
$query = $model->query_from_request();

// Usage with custom parameters
$query = $model->query_from_request([
    'order_field' => 'id',
    'order_dir' => 'desc',
    'page' => 1,
    'limit' => 20
]);

// Executing the query
$rows = Get::db()->get_results(...$query->get());
$total = Get::db()->get_var(...$query->get_total());
    </code></pre>

    <h4>get_page_info($total)</h4>
    <p>Returns a PageInfo instance with pagination information.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Get the total number of records
$total = Get::db()->get_var(...$query->get_total());

// Get pagination information
$page_info = $model->get_page_info($total);

// Now $page_info is a PageInfo instance that can be customized
$page_info->set_pagination(true);
$page_info->set_ajax(true);
$page_info->set_id('my-table-id');
    </code></pre>

    <h4>get_data_chart($data, $structure)</h4>
    <p>Prepares data for display in a chart.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query = $model->query_from_request();
$rows = Get::db()->get_results(...$query->get());

// Defining the chart structure
$chartStructure = [
    'month' => ['label' => 'Month', 'axis' => 'x'],
    'sales' => ['label' => 'Sales', 'type' => 'bar'],
    'profit' => ['label' => 'Profit', 'type' => 'line', 'borderColor' => '#A02A4D']
];

// Preparing data for the chart
$chartData = $model->get_data_chart($rows, $chartStructure);
    </code></pre>

    <h2>2. ListStructure Class</h2>
    <p>The ListStructure class manages the column structure of a table. It implements the ArrayAccess, Iterator, and Countable interfaces to allow accessing properties as an array and iterating over them.</p>

    <h3>Public Functions of ListStructure</h3>

    <h4>__construct(array $structure = [])</h4>
    <p>Constructor that can accept an initial structure array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Creates an empty structure
$listStructure = new \MilkCore\ListStructure();

// Creates with an initial structure
$listStructure = new \MilkCore\ListStructure([
    'id' => ['type' => 'text', 'label' => 'ID', 'primary' => true],
    'title' => ['type' => 'text', 'label' => 'Title']
]);
    </code></pre>

    <h4>set_column($db_name, $label, $type = 'text', $order = true, $primary = false, $options = [], $attributes_title = [], $attributes_data = [])</h4>
    <p>Sets or adds a column to the structure. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Adding basic columns
$listStructure->set_column('id', 'ID', 'text', true, true)
              ->set_column('title', 'Title', 'text', true, false);

// Column with 'select' type and options
$listStructure->set_column(
    'status',                               // Database field name
    'Status',                               // Displayed label
    'select',                               // Type
    true,                                   // Sortable
    false,                                  // Is not a primary key
    ['0' => 'Draft', '1' => 'Active'],      // Options for select
    ['class' => 'bg-success'],              // Header attributes
    ['class' => 'bg-danger']                // Data cell attributes
);

// Column of type 'action' with action definitions
$listStructure->set_column(
    'action',
    'Actions',
    'action',
    false,
    false,
    ['view' => 'View', 'edit' => 'Edit', 'delete' => 'Delete']
);
    </code></pre>

    <h4>get_column($db_name)</h4>
    <p>Gets a column from the structure.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$columnInfo = $listStructure->get_column('status');
// $columnInfo contains all the column properties
    </code></pre>

    <h4>delete_columns($db_names)</h4>
    <p>Deletes a column from the structure. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->delete_columns(['action', 'unnecessary_field']);
    </code></pre>

    <h4>delete_column($db_name)</h4>
    <p>Deletes a column from the structure. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->delete_column('action')
              ->delete_column('unnecessary_field');
    </code></pre>

    <h4>hide_columns($db_names)</h4>
    <p>Sets a column as hidden. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->hide_columns(['action', 'unnecessary_field']);
    </code></pre>

    <h4>hide_column($db_name)</h4>
    <p>Sets a column as hidden. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->hide_column('action')
              ->hide_column('unnecessary_field');
    </code></pre>

    <h4>set_action($options = [], $label = 'Action')</h4>
    <p>Sets a column of type action. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->set_action(['edit' => 'Edit', 'delete' => 'Delete'], 'Actions');
    </code></pre>

    <h4>set_label($db_name, $label)</h4>
    <p>Sets the label of a column. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->set_label('id', 'User ID')
              ->set_label('title', 'Article Title');
    </code></pre>

    <h4>set_type($db_name, $type)</h4>
    <p>Sets the type of a column. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->set_type('status', 'select')
              ->set_type('created_at', 'date');
    </code></pre>

    <h4>set_order($db_name, $orderable)</h4>
    <p>Sets whether the column is sortable. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->set_order('status', false)
              ->set_order('title', true);
    </code></pre>

    <h4>set_primary($db_name)</h4>
    <p>Sets a column as the primary key. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->set_primary('id');
    </code></pre>

    <h4>set_options($db_name, $options)</h4>
    <p>Sets the options for a select type column. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->set_options('status', [
    '0' => 'Draft', 
    '1' => 'In review',
    '2' => 'Published'
]);
    </code></pre>

    <h4>add_attribute_title($db_name, $attr_name, $attr_value)</h4>
    <p>Adds a single HTML attribute to a column's title. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->add_attribute_title('status', 'class', 'bg-primary')
              ->add_attribute_title('status', 'data-filter', 'status');
    </code></pre>

    <h4>add_attribute_data($db_name, $attr_name, $attr_value)</h4>
    <p>Adds a single HTML attribute to a column's rows. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->add_attribute_data('status', 'class', 'text-center')
              ->add_attribute_data('title', 'data-toggle', 'tooltip');
    </code></pre>

    <h4>get_attributes_title($db_name)</h4>
    <p>Gets the HTML attributes of a column's title.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$attributes = $listStructure->get_attributes_title('status');
// $attributes contains all the HTML attributes of the header
    </code></pre>

    <h4>get_attributes_data($db_name)</h4>
    <p>Gets the HTML attributes of a column's cells.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$attributes = $listStructure->get_attributes_data('status');
// $attributes contains all the HTML attributes of the cells
    </code></pre>

    <h4>disable_all_order()</h4>
    <p>Disables sorting for all columns. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->disable_all_order();
    </code></pre>

    <h4>enable_all_order()</h4>
    <p>Enables sorting for all columns. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->enable_all_order();
    </code></pre>

    <h4>to_array()</h4>
    <p>Converts the structure to an array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$array = $listStructure->to_array();
// $array contains all the column definitions as an array
    </code></pre>

    <h4>map(callable $callback)</h4>
    <p>Applies a callback function to each element of the structure. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Example: converts all labels to uppercase
$listStructure->map(function($row) {
    $row['label'] = strtoupper($row['label']);
    return $row;
});
    </code></pre>

    <h2>3. PageInfo Class</h2>
    <p>The PageInfo class manages page information for the table. It implements the ArrayAccess, Iterator, and Countable interfaces to allow accessing properties as an array and iterating over them.</p>

    <h3>Public Functions of PageInfo</h3>

    <h4>__construct(array $config = [])</h4>
    <p>Constructor that can accept an initial configuration array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Creates with default configuration
$pageInfo = new \MilkCore\PageInfo();

// Creates with custom configuration
$pageInfo = new \MilkCore\PageInfo([
    'id' => 'my-table',
    'limit' => 20,
    'pagination' => true,
    'ajax' => true
]);
    </code></pre>

    <h4>set_id($id)</h4>
    <p>Sets the table ID. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_id('my-custom-table');
    </code></pre>

    <h4>set_page($page)</h4>
    <p>Sets the current page. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_page('example_page');
    </code></pre>

    <h4>set_action($action)</h4>
    <p>Sets the action. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_action('custom_action');
    </code></pre>

    <h4>set_limit($limit)</h4>
    <p>Sets the row limit per page. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_limit(20);
    </code></pre>

    <h4>set_limit_start($limitStart)</h4>
    <p>Sets the starting index for the limit. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_limit_start(40);
    </code></pre>

    <h4>set_order_field($field)</h4>
    <p>Sets the sorting field. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_order_field('created_at');
    </code></pre>

    <h4>set_order_dir($dir)</h4>
    <p>Sets the sorting direction. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_order_dir('desc');
    </code></pre>

    <h4>set_total_record($total)</h4>
    <p>Sets the total number of records. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_total_record(150);
    </code></pre>

    <h4>set_footer($enabled)</h4>
    <p>Enables/disables the footer. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_footer(true);
    </code></pre>

    <h4>set_ajax($enabled)</h4>
    <p>Enables/disables ajax. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_ajax(true);
    </code></pre>

    <h4>set_pagination($enabled)</h4>
    <p>Abilita/disabilita la paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_pagination(true);
    </code></pre>

    <h4>set_bulk_actions($actions)</h4>
    <p>Imposta le azioni bulk. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_bulk_actions([
    'delete' => 'Elimina selezionati',
    'activate' => 'Attiva selezionati',
    'deactivate' => 'Disattiva selezionati'
]);
    </code></pre>

    <h4>add_bulk_action($key, $label)</h4>
    <p>Aggiunge un'azione bulk. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->add_bulk_action('export', 'Esporta selezionati');
    </code></pre>

    <h4>set_auto_scroll($enabled)</h4>
    <p>Abilita/disabilita lo scrolling automatico. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_auto_scroll(false);
    </code></pre>

    <h4>set_pag_total_show($enabled)</h4>
    <p>Abilita/disabilita la visualizzazione del totale nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_pag_total_show(true);
    </code></pre>

    <h4>set_pag_number_show($enabled)</h4>
    <p>Abilita/disabilita la visualizzazione dei numeri di pagina nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_pag_number_show(true);
    </code></pre>

    <h4>set_pag_goto_show($enabled)</h4>
    <p>Abilita/disabilita la visualizzazione del selettore "vai alla pagina" nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_pag_goto_show(true);
    </code></pre>

    <h4>set_pag_el_per_page_show($enabled)</h4>
    <p>Abilita/disabilita la visualizzazione del selettore "elementi per pagina" nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_pag_el_per_page_show(true);
    </code></pre>

    <h4>set_pagination_limit($limit)</h4>
    <p>Imposta il limite di pagine da mostrare nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_pagination_limit(10);
    </code></pre>

    <h4>set_input_hidden($html)</h4>
    <p>Imposta il codice html da aggiungere alla fine dei campi hidden della form. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_input_hidden('<input type="hidden" name="table" value="' . _r($table_name) . '"><input type="hidden" name="primary_key" value="' . _r($primary_key) . '">');
    </code></pre>

    <h4>set_table_attrs($key, $attrs)</h4>
    <p>Imposta gli attributi di una tabella. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->set_table_attrs('table', ['class' => 'table table-hover js-table']);
    </code></pre>

    <h4>to_array()</h4>
    <p>Converte le informazioni di pagina in un array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$array = $pageInfo->to_array();
// $array contiene tutte le informazioni di paginazione come array
    </code></pre>

    <h2>Complete examples</h2>

    <h3>Basic table creation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model = new \MilkCore\ModelList('#__dynamic_example');

// Query construction based on request parameters
$query = $model->query_from_request();

// Data retrieval
$rows = Get::db()->get_results(...$query->get());

// Total count retrieval
$total = Get::db()->get_var(...$query->get_total());

// Pagination configuration
$page_info = $model->get_page_info($total);

// Table HTML generation
$table_html = Get::theme_plugin('table', [
    'info' => $model->get_list_structure(), 
    'rows' => $rows, 
    'page_info' => $page_info
]); 

// Output of the table
echo $table_html;
    </code></pre>

    <h3>Table with footer and customizations</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model = new \MilkCore\ModelList('#__dynamic_example');
$query = $model->query_from_request();
$rows = Get::db()->get_results(...$query->get());
$total = Get::db()->get_var(...$query->get_total());

// Pagination configuration
$page_info = $model->get_page_info($total);
$page_info->set_id('my-table-id');
$page_info->set_pagination(false);

// Enable the footer
$page_info->set_footer(true);

// Add line as footer
$rows[] = (object)['id' => '', 'title' => 'Totale', 'status' => '99999'];

// Table customization
$table_attrs = [
    'tfoot' => ['class' => 'table-footer-gray'], 
    'tfoot.td.title' => ['class' => 'text-end']
];

// Table HTML generation
$table_html = Get::theme_plugin('table', [
    'info' => $model->get_list_structure(), 
    'rows' => $rows, 
    'page_info' => $page_info,
    'table_attrs' => $table_attrs
]); 

// Output of the table
echo $table_html;
    </code></pre>

    <h3>Table with custom structure and filters</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model = new \MilkCore\ModelList('#__dynamic_example_2');

// Customizing the search filter
$model->add_filter('search', function($query, $search) use ($model) {
    if ($search == 'draft') {
        $query->where('`status` = 0');
    } else if ($search == 'active') {
        $query->where('`status` = 1');
    } else {
        // Cerca in tutte le colonne ad eccezione di status
        $list_structure = $model->get_table_structure();
        foreach ($list_structure as $field => $_) {
            if ($field == 'status') continue;
            $query->where('`'.$field.'` LIKE ? ', ['%'.$search.'%'], 'OR');
        }
    }
});

$query = $model->query_from_request();

$rows = Get::db()->get_results(...$query->get());
$rows = array_map(function($row) {
    $row->content = substr($row->content, 0, 200) . '...';
    return $row;
}, $rows);

// Customizing the table structure
$row_info = $model->get_list_structure();
$row_info->set_column(
    'status',                               // Nome campo
    'Status',                               // Etichetta
    'select',                               // Tipo
    false,                                  // Non ordinabile
    false,                                  // Non è chiave primaria
    ['0' => 'Draft', '1' => 'Active'],      // Opzioni per select
    ['class' => 'bg-success', 'data-customfilter' => 'status'], // Attributi intestazione
    ['class' => 'bg-danger']                // Attributi dati
)->set_column(
    'action',                               // Nome campo
    'Action',                               // Etichetta
    'action',                               // Tipo
    false,                                  // Non ordinabile
    false,                                  // Non è chiave primaria
    ['view' => 'View']                      // Definizione azioni
);

// Change all labels to uppercase
$row_info->map(function($row) {
    $row['label'] = strtoupper($row['label']);
    return $row;
});

// Total record count
$total = Get::db()->get_var(...$query->get_total());

// Pagination configuration
$page_info = $model->get_page_info($total);
$page_info->set_id('my-custom-table');

// Table customization
$table_attrs = [
    'thead' => ['class' => 'table-header-yellow'], 
    'th.title' => ['class' => 'th-title']
];

// Table HTML generation
$table_html = Get::theme_plugin('table', [
    'info' => $row_info, 
    'rows' => $rows, 
    'page_info' => $page_info,
    'table_attrs' => $table_attrs
]); 

echo $table_html;
    </code></pre>

    <h3>Multiple tables on the same page</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
function table_list() {
    // First table
    $id1 = _raz('table-dynamic-example');
    if ((($_REQUEST['page-output'] ?? '') == 'json' && $_REQUEST['table_id'] == $id1) || ($_REQUEST['page-output'] ?? '') == '') {
        $model = new \MilkCore\ModelList('#__dynamic_example');
        $query = $model->query_from_request();
        $rows1 = Get::db()->get_results(...$query->get());
        $total1 = Get::db()->get_var(...$query->get_total());
        $page_info1 = $model->get_page_info($total1);
        $page_info1->set_id($id1);
        $page_info1->set_pagination(false);
        $page_info1->set_footer(true);
        
        $table_attrs1 = [
            'tfoot' => ['class' => 'table-footer-gray'], 
            'tfoot.td.title' => ['class' => 'text-end']
        ];
        
        $rows1[] = (object)['id' => '', 'title' => 'Total', 'status' => '99999'];
        
        $table_html1 = Get::theme_plugin('table', [
            'info' => $model->get_list_structure($rows1, 'id'), 
            'rows' => $rows1, 
            'page_info' => $page_info1, 
            'table_attrs' => $table_attrs1
        ]);
    }
   
    // Second table
    $id2 = _raz('table-dynamic-example-2');
    if ((($_REQUEST['page-output'] ?? '') == 'json' && $_REQUEST['table_id'] == $id2) || ($_REQUEST['page-output'] ?? '') == '') {
        $model2 = new \MilkCore\ModelList('#__dynamic_example_2');
        
        // Customizing the search filter
        $model2->add_filter('search', function($query, $search) use ($model2) {
            if ($search == 'draft') {
                $query->where('`status` = 0');
            } else if ($search == 'active') {
                $query->where('`status` = 1');
            } else {
                // Cerca in tutte le colonne tranne status
                $list_structure = $model2->get_table_structure();
                foreach ($list_structure as $field => $_) {
                    if ($field == 'status') continue;
                    $query->where('`'.$field.'` LIKE ? ', ['%'.$search.'%'], 'OR');
                }
            }
        });

        $query2 = $model2->query_from_request();
        $rows2 = Get::db()->get_results(...$query2->get());
        
        // Modify a column of the array
        $rows2 = array_map(function($row) {
            $row->content = substr($row->content, 0, 200) . '...';
            return $row;
        }, $rows2);
        
        // Customizing the table structure
        $row_info = $model2->get_list_structure();
        $row_info->set_column(
            'status', 
            'Status', 
            'select', 
            false, 
            false, 
            ['0' => 'Draft', '1' => 'Active'], 
            ['class' => 'bg-success', 'data-customfilter' => 'status'], 
            ['class' => 'bg-danger']
        )->set_column(
            'action', 
            'Action', 
            'action', 
            false, 
            false, 
            [$id2.'-view' => 'View']
        );
        
        $row_info->map(function($row) {
            $row['label'] = strtoupper($row['label']);
            return $row;
        });
        
        $total2 = Get::db()->get_var(...$query2->get_total());
        $page_info2 = $model2->get_page_info($total2);
        $page_info2->set_id($id2);
       
        // Table customization
        $table_attrs2 = [
            'thead' => ['class' => 'table-header-yellow'], 
            'th.title' => ['class' => 'th-title']
        ];
        
        // Table HTML generation
        $table_html2 = Get::theme_plugin('table', [
            'info' => $row_info, 
            'rows' => $rows2, 
            'page_info' => $page_info2, 
            'table_attrs' => $table_attrs2
        ]);  
    }

    // Output management
    if (($_REQUEST['page-output'] ?? '') == 'json') {
        // Update one of the two tables
        if ($_REQUEST['table_id'] == $id2) {
            Get::theme_page('json', '', json_encode([
                'html' => $table_html2, 
                'success' => 'true', 
                'msg' => ''
            ]));
        } else if ($_REQUEST['table_id'] == $id1) {
            Get::theme_page('json', '', json_encode([
                'html' => $table_html1, 
                'success' => 'true', 
                'msg' => ''
            ]));
        }
    } else {
        // Complete page rendering with both tables
        Get::theme_page('default', __DIR__ . '/table.page.php', [
            'table_html1' => $table_html1, 
            'table_html2' => $table_html2,
            'table_id2' => $id2,
            'row_info' => $row_info,
            'page_info' => $page_info2
        ]);
    }
}
    </code></pre>
</div>