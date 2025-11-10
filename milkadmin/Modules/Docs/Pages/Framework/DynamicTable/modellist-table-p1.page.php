<?php
namespace Modules\Docs\Pages;
/**
 * @title Build a base
 * @guide framework
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
$model = new \App\Modellist\ModelList('#__dynamic_example');

// Creates an instance with a custom ID
$model = new \App\Modellist\ModelList('#__dynamic_example', 'my-table-id');
    </code></pre>

    <h4>setListStructure($list_structure)</h4>
    <p>Sets the column structure of the table. Accepts an array or a ListStructure instance.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Usage with an array
$model->setListStructure([
    'id' => ['type' => 'text', 'label' => 'ID', 'primary' => true],
    'title' => ['type' => 'text', 'label' => 'Title']
]);

// Usage with a ListStructure instance
$listStructure = new App\Modellist\ListStructure();
$listStructure->setColumn('id', 'ID', 'text', true, true);
$listStructure->setColumn('title', 'Title', 'text', true, false);
$model->setListStructure($listStructure);
    </code></pre>

    <h4>getListStructure($columns, $primary_key)</h4>
    <p>Returns a ListStructure instance with the table's column structure. If not set, it is automatically generated based on the table fields.</p>
    <p>It is used to generate the table structure from the database table. If $rows is not set, it is automatically generated based on the table fields.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$row_info = $model->getListStructure($columns, 'id');
// Now $row_info is a ListStructure instance that can be modified
$row_info->setColumn('status', 'Status', 'select', false, false, ['0' => 'Draft', '1' => 'Active']);
    </code></pre>

    <h4>setNoOrder()</h4>
    <p>Disables sorting for all table columns.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model->setNoOrder();
    </code></pre>

    <h4>setLimit($limit)</h4>
    <p>Sets the row limit per page.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model->setLimit(20);
    </code></pre>

    <h4>setOrder($order_field, $order_dir = 'desc')</h4>
    <p>Sets the default sorting order for the table. This defines which field and direction will be used for sorting when no user sorting is applied.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Set default sorting by creation date, newest first
$model->setOrder('created_at', 'desc');

// Set default sorting by title, alphabetical order
$model->setOrder('title', 'asc');

// Set default sorting by ID, descending order (default direction)
$model->setOrder('id');
    </code></pre>

    <h4>reorderColumns($db_names)</h4>
    <p>Reorders the columns of the table. This method supports method chaining.</p>
    <p>$db_names can be a string or an array of strings. If it is a string it will insert the column at the first position, otherwise if it is an array it will reorder the array based on the specified order.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->reorderColumns(['id', 'title']);
    </code></pre>

    <h4>getTableStructure()</h4>
    <p>Returns the MySQL table structure.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$table_structure = $model->getTableStructure();
// Now $table_structure contains all the information about the table fields
    </code></pre>

    <h4>setPrimaryKey($primary_key)</h4>
    <p>Manually sets the primary key of the table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model->setPrimaryKey('custom_id');
    </code></pre>

    <h4>queryFromRequest($request = null)</h4>
    <p>Creates a query based on the request parameters (sorting, pagination).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Standard usage with $_REQUEST
$query = $model->queryFromRequest();

// Usage with custom parameters
$query = $model->queryFromRequest([
    'order_field' => 'id',
    'order_dir' => 'desc',
    'page' => 1,
    'limit' => 20
]);

// Executing the query
$rows = Get::db()->getResults(...$query->get());
$total = Get::db()->getVar(...$query->getTotal());
    </code></pre>

    <h4>getPageInfo($total)</h4>
    <p>Returns a PageInfo instance with pagination information.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Get the total number of records
$total = Get::db()->getVar(...$query->getTotal());

// Get pagination information
$page_info = $model->getPageInfo($total);

// Now $page_info is a PageInfo instance that can be customized
$page_info->setPagination(true);
$page_info->setAjax(true);
$page_info->setId('my-table-id');
    </code></pre>

    <h4>getDataChart($data, $structure)</h4>
    <p>Prepares data for display in a chart.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query = $model->queryFromRequest();
$rows = Get::db()->getResults(...$query->get());

// Defining the chart structure
$chartStructure = [
    'month' => ['label' => 'Month', 'axis' => 'x'],
    'sales' => ['label' => 'Sales', 'type' => 'bar'],
    'profit' => ['label' => 'Profit', 'type' => 'line', 'borderColor' => '#A02A4D']
];

// Preparing data for the chart
$chartData = $model->getDataChart($rows, $chartStructure);
    </code></pre>

    <h2>2. ListStructure Class</h2>
    <p>The ListStructure class manages the column structure of a table. It implements the ArrayAccess, Iterator, and Countable interfaces to allow accessing properties as an array and iterating over them.</p>

    <h3>Public Functions of ListStructure</h3>

    <h4>__construct(array $structure = [])</h4>
    <p>Constructor that can accept an initial structure array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Creates an empty structure
$listStructure = new App\Modellist\ListStructure();

// Creates with an initial structure
$listStructure = new App\Modellist\ListStructure([
    'id' => ['type' => 'text', 'label' => 'ID', 'primary' => true],
    'title' => ['type' => 'text', 'label' => 'Title']
]);
    </code></pre>

    <h4>setColumn($db_name, $label, $type = 'text', $order = true, $primary = false, $options = [], $attributes_title = [], $attributes_data = [])</h4>
    <p>Sets or adds a column to the structure. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Adding basic columns
$listStructure->setColumn('id', 'ID', 'text', true, true)
              ->setColumn('title', 'Title', 'text', true, false);

// Column with 'select' type and options
$listStructure->setColumn(
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
$listStructure->setColumn(
    'action',
    'Actions',
    'action',
    false,
    false,
    ['view' => 'View', 'edit' => 'Edit', 'delete' => 'Delete']
);
    </code></pre>

    <h4>getColumn($db_name)</h4>
    <p>Gets a column from the structure.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$columnInfo = $listStructure->getColumn('status');
// $columnInfo contains all the column properties
    </code></pre>

    <h4>deleteColumns($db_names)</h4>
    <p>Deletes a column from the structure. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->deleteColumns(['action', 'unnecessary_field']);
    </code></pre>

    <h4>deleteColumn($db_name)</h4>
    <p>Deletes a column from the structure. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->deleteColumn('action')
              ->deleteColumn('unnecessary_field');
    </code></pre>

    <h4>hideColumns($db_names)</h4>
    <p>Sets a column as hidden. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->hideColumns(['action', 'unnecessary_field']);
    </code></pre>

    <h4>hideColumn($db_name)</h4>
    <p>Sets a column as hidden. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->hideColumn('action')
              ->hideColumn('unnecessary_field');
    </code></pre>

    <h4>setAction($options = [], $label = 'Action')</h4>
    <p>Sets a column of type action. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->setAction(['edit' => 'Edit', 'delete' => 'Delete'], 'Actions');
    </code></pre>

    <h4>setLabel($db_name, $label)</h4>
    <p>Sets the label of a column. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->setLabel('id', 'User ID')
              ->setLabel('title', 'Article Title');
    </code></pre>

    <h4>setType($db_name, $type)</h4>
    <p>Sets the type of a column. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->setType('status', 'select')
              ->setType('created_at', 'date');
    </code></pre>

    <h4>setOrder($db_name, $orderable)</h4>
    <p>Sets whether the column is sortable. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->setOrder('status', false)
              ->setOrder('title', true);
    </code></pre>

    <h4>setPrimary($db_name)</h4>
    <p>Sets a column as the primary key. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->setPrimary('id');
    </code></pre>

    <h4>setOptions($db_name, $options)</h4>
    <p>Sets the options for a select type column. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->setOptions('status', [
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

    <h4>getAttributesTitle($db_name)</h4>
    <p>Gets the HTML attributes of a column's title.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$attributes = $listStructure->getAttributesTitle('status');
// $attributes contains all the HTML attributes of the header
    </code></pre>

    <h4>getAttributesData($db_name)</h4>
    <p>Gets the HTML attributes of a column's cells.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$attributes = $listStructure->getAttributesData('status');
// $attributes contains all the HTML attributes of the cells
    </code></pre>

    <h4>disableAllOrder()</h4>
    <p>Disables sorting for all columns. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->disableAllOrder();
    </code></pre>

    <h4>enableAllOrder()</h4>
    <p>Enables sorting for all columns. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$listStructure->enableAllOrder();
    </code></pre>

    <h4>toArray()</h4>
    <p>Converts the structure to an array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$array = $listStructure->toArray();
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
$pageInfo = new \App\PageInfo();

// Creates with custom configuration
$pageInfo = new \App\PageInfo([
    'id' => 'my-table',
    'limit' => 20,
    'pagination' => true,
    'ajax' => true
]);
    </code></pre>

    <h4>setId($id)</h4>
    <p>Sets the table ID. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setId('my-custom-table');
    </code></pre>

    <h4>setPage($page)</h4>
    <p>Sets the current page. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setPage('example_page');
    </code></pre>

    <h4>setAction($action)</h4>
    <p>Sets the action. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setAction('custom_action');
    </code></pre>

    <h4>setLimit($limit)</h4>
    <p>Sets the row limit per page. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setLimit(20);
    </code></pre>

    <h4>setLimitStart($limitStart)</h4>
    <p>Sets the starting index for the limit. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setLimitStart(40);
    </code></pre>

    <h4>setOrderField($field)</h4>
    <p>Sets the sorting field. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setOrderField('created_at');
    </code></pre>

    <h4>setOrderDir($dir)</h4>
    <p>Sets the sorting direction. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setOrderDir('desc');
    </code></pre>

    <h4>setTotalRecord($total)</h4>
    <p>Sets the total number of records. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setTotalRecord(150);
    </code></pre>

    <h4>setFooter($enabled)</h4>
    <p>Enables/disables the footer. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setFooter(true);
    </code></pre>

    <h4>setAjax($enabled)</h4>
    <p>Enables/disables ajax. This method supports method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setAjax(true);
    </code></pre>

    <h4>setPagination($enabled)</h4>
    <p>Abilita/disabilita la paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setPagination(true);
    </code></pre>

    <h4>setBulkActions($actions)</h4>
    <p>Imposta le azioni bulk. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setBulkActions([
    'delete' => 'Elimina selezionati',
    'activate' => 'Attiva selezionati',
    'deactivate' => 'Disattiva selezionati'
]);
    </code></pre>

    <h4>addBulkAction($key, $label)</h4>
    <p>Aggiunge un'azione bulk. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->addBulkAction('export', 'Esporta selezionati');
    </code></pre>

    <h4>setAutoScroll($enabled)</h4>
    <p>Abilita/disabilita lo scrolling automatico. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setAutoScroll(false);
    </code></pre>

    <h4>setPagTotalShow($enabled)</h4>
    <p>Abilita/disabilita la visualizzazione del totale nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setPagTotalShow(true);
    </code></pre>

    <h4>setPagNumberShow($enabled)</h4>
    <p>Abilita/disabilita la visualizzazione dei numeri di pagina nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setPagNumberShow(true);
    </code></pre>

    <h4>setPagGotoShow($enabled)</h4>
    <p>Abilita/disabilita la visualizzazione del selettore "vai alla pagina" nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setPagGotoShow(true);
    </code></pre>

    <h4>setPagElPerPageShow($enabled)</h4>
    <p>Abilita/disabilita la visualizzazione del selettore "elementi per pagina" nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setPagElPerPageShow(true);
    </code></pre>

    <h4>setPaginationLimit($limit)</h4>
    <p>Imposta il limite di pagine da mostrare nella paginazione. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setPaginationLimit(10);
    </code></pre>

    <h4>setInputHidden($html)</h4>
    <p>Imposta il codice html da aggiungere alla fine dei campi hidden della form. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setInputHidden('<input type="hidden" name="table" value="' . _r($table_name) . '"><input type="hidden" name="primary_key" value="' . _r($primary_key) . '">');
    </code></pre>

    <h4>setTableAttrs($key, $attrs)</h4>
    <p>Imposta gli attributi di una tabella. Questo metodo supporta il method chaining.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$pageInfo->setTableAttrs('table', ['class' => 'table table-hover js-table']);
    </code></pre>

    <h4>toArray()</h4>
    <p>Converte le informazioni di pagina in un array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$array = $pageInfo->toArray();
// $array contiene tutte le informazioni di paginazione come array
    </code></pre>

    <h2>Complete examples</h2>

    <h3>Basic table creation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model = new \App\Modellist\ModelList('#__dynamic_example');

// Query construction based on request parameters
$query = $model->queryFromRequest();

// Data retrieval
$rows = Get::db()->getResults(...$query->get());

// Total count retrieval
$total = Get::db()->getVar(...$query->getTotal());

// Pagination configuration
$page_info = $model->getPageInfo($total);

// Table HTML generation
$table_html = Get::themePlugin('table', [
    'info' => $model->getListStructure(), 
    'rows' => $rows, 
    'page_info' => $page_info
]); 

// Output of the table
echo $table_html;
    </code></pre>

    <h3>Table with footer and customizations</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model = new \App\Modellist\ModelList('#__dynamic_example');
$query = $model->queryFromRequest();
$rows = Get::db()->getResults(...$query->get());
$total = Get::db()->getVar(...$query->getTotal());

// Pagination configuration
$page_info = $model->getPageInfo($total);
$page_info->setId('my-table-id');
$page_info->setPagination(false);

// Enable the footer
$page_info->setFooter(true);

// Add line as footer
$rows[] = (object)['id' => '', 'title' => 'Totale', 'status' => '99999'];

// Table customization
$table_attrs = [
    'tfoot' => ['class' => 'table-footer-gray'], 
    'tfoot.td.title' => ['class' => 'text-end']
];

// Table HTML generation
$table_html = Get::themePlugin('table', [
    'info' => $model->getListStructure(), 
    'rows' => $rows, 
    'page_info' => $page_info,
    'table_attrs' => $table_attrs
]); 

// Output of the table
echo $table_html;
    </code></pre>

    <h3>Table with custom structure and filters</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$model = new \App\Modellist\ModelList('#__dynamic_example_2');

// Customizing the search filter
$model->addFilter('search', function($query, $search) use ($model) {
    if ($search == 'draft') {
        $query->where('`status` = 0');
    } else if ($search == 'active') {
        $query->where('`status` = 1');
    } else {
        // Cerca in tutte le colonne ad eccezione di status
        $list_structure = $model->getTableStructure();
        foreach ($list_structure as $field => $_) {
            if ($field == 'status') continue;
            $query->where('`'.$field.'` LIKE ? ', ['%'.$search.'%'], 'OR');
        }
    }
});

$query = $model->queryFromRequest();

$rows = Get::db()->getResults(...$query->get());
$rows = array_map(function($row) {
    $row->content = substr($row->content, 0, 200) . '...';
    return $row;
}, $rows);

// Customizing the table structure
$row_info = $model->getListStructure();
$row_info->setColumn(
    'status',                               // Nome campo
    'Status',                               // Etichetta
    'select',                               // Tipo
    false,                                  // Non ordinabile
    false,                                  // Non è chiave primaria
    ['0' => 'Draft', '1' => 'Active'],      // Opzioni per select
    ['class' => 'bg-success', 'data-customfilter' => 'status'], // Attributi intestazione
    ['class' => 'bg-danger']                // Attributi dati
)->setColumn(
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
$total = Get::db()->getVar(...$query->getTotal());

// Pagination configuration
$page_info = $model->getPageInfo($total);
$page_info->setId('my-custom-table');

// Table customization
$table_attrs = [
    'thead' => ['class' => 'table-header-yellow'], 
    'th.title' => ['class' => 'th-title']
];

// Table HTML generation
$table_html = Get::themePlugin('table', [
    'info' => $row_info, 
    'rows' => $rows, 
    'page_info' => $page_info,
    'table_attrs' => $table_attrs
]); 

echo $table_html;
    </code></pre>

    <h3>Multiple tables on the same page</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
function tableList() {
    // First table
    $id1 = _raz('table-dynamic-example');
    if ((($_REQUEST['page-output'] ?? '') == 'json' && $_REQUEST['table_id'] == $id1) || ($_REQUEST['page-output'] ?? '') == '') {
        $model = new \App\Modellist\ModelList('#__dynamic_example');
        $query = $model->queryFromRequest();
        $rows1 = Get::db()->getResults(...$query->get());
        // Calculate the query columns before executing another query (total)
        $columns = $this->model->getQueryColumns();
        $total1 = Get::db()->getVar(...$query->getTotal());
        $page_info1 = $model->getPageInfo($total1);
        $page_info1->setId($id1);
        $page_info1->setPagination(false);
        $page_info1->setFooter(true);
        
        $table_attrs1 = [
            'tfoot' => ['class' => 'table-footer-gray'], 
            'tfoot.td.title' => ['class' => 'text-end']
        ];
        
        $rows1[] = (object)['id' => '', 'title' => 'Total', 'status' => '99999'];
        
        $table_html1 = Get::themePlugin('table', [
            'info' => $model->getListStructure(array_keys($rows1[0]), 'id'), 
            'rows' => $rows1, 
            'page_info' => $page_info1, 
            'table_attrs' => $table_attrs1
        ]);
    }
   
    // Second table
    $id2 = _raz('table-dynamic-example-2');
    if ((($_REQUEST['page-output'] ?? '') == 'json' && $_REQUEST['table_id'] == $id2) || ($_REQUEST['page-output'] ?? '') == '') {
        $model2 = new \App\Modellist\ModelList('#__dynamic_example_2');
        
        // Customizing the search filter
        $model2->addFilter('search', function($query, $search) use ($model2) {
            if ($search == 'draft') {
                $query->where('`status` = 0');
            } else if ($search == 'active') {
                $query->where('`status` = 1');
            } else {
                // Cerca in tutte le colonne tranne status
                $list_structure = $model2->getTableStructure();
                foreach ($list_structure as $field => $_) {
                    if ($field == 'status') continue;
                    $query->where('`'.$field.'` LIKE ? ', ['%'.$search.'%'], 'OR');
                }
            }
        });

        $query2 = $model2->queryFromRequest();
        $rows2 = Get::db()->getResults(...$query2->get());
        
        // Modify a column of the array
        $rows2 = array_map(function($row) {
            $row->content = substr($row->content, 0, 200) . '...';
            return $row;
        }, $rows2);
        
        // Customizing the table structure
        $row_info = $model2->getListStructure();
        $row_info->setColumn(
            'status', 
            'Status', 
            'select', 
            false, 
            false, 
            ['0' => 'Draft', '1' => 'Active'], 
            ['class' => 'bg-success', 'data-customfilter' => 'status'], 
            ['class' => 'bg-danger']
        )->setColumn(
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
        
        $total2 = Get::db()->getVar(...$query2->getTotal());
        $page_info2 = $model2->getPageInfo($total2);
        $page_info2->setId($id2);
       
        // Table customization
        $table_attrs2 = [
            'thead' => ['class' => 'table-header-yellow'], 
            'th.title' => ['class' => 'th-title']
        ];
        
        // Table HTML generation
        $table_html2 = Get::themePlugin('table', [
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
            Response::themePage('json', '', json_encode([
                'html' => $table_html2, 
                'success' => 'true', 
                'msg' => ''
            ]));
        } else if ($_REQUEST['table_id'] == $id1) {
            Response::themePage('json', '', json_encode([
                'html' => $table_html1, 
                'success' => 'true', 
                'msg' => ''
            ]));
        }
    } else {
        // Complete page rendering with both tables
        Response::themePage('default', __DIR__ . '/table.page.php', [
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