<?php
namespace Modules\Db2tables;

use App\Abstracts\AbstractController;
use App\Attributes\RequestAction;
use App\{Get, Response, Config, Theme, Route, Token};

!defined('MILK_DIR') && die(); // Prevent direct access

class Db2tablesController extends AbstractController
{
    /**
     * Change the active database (db1 or db2)
     */
    #[RequestAction('change-database')]
    public function actionChangeDatabase() {
        // Get JSON data from request
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data);

        // Validate the database selection
        if (!isset($data->database) || !in_array($data->database, ['db1', 'db2'])) {
            Response::json([
                'success' => false,
                'error' => 'Invalid database selection'
            ]);
            return;
        }

        // Update session with the selected database
        $_SESSION['db2tables_db_selection'] = $data->database;

        // Return success response
        Response::json([
            'success' => true,
            'database' => $data->database
        ]);
    }

    /**
     * Save a single cell edit from the editable table
     *
     * Receives JSON data with primary keys, field name, and new value
     * Updates the database and returns success/error status
     */
    #[RequestAction('save-edit-single-cell')]
    public function actionSaveEditSingleCell() {
        // Get JSON data from request
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data);
        
        // Get the table name from the request
        $table = isset($_GET['table']) ? $_GET['table'] : '';
        if (empty($table) && isset($data->table)) {
            $table = $data->table;
        }
        
        // Get token if provided
        $token = isset($data->token) ? $data->token : null;
        
        // Use the table edit service to handle the cell update
        $result = Db2tablesTableEditServices::saveEditSingleCell($data, $table, $token);
        
        // Return the response as JSON
        Response::json($result);
    }
    /**
     * Search for specific values in a field
     */
    #[RequestAction('search_field_values')]
    public function actionSearchFieldValues() {
        $table = isset($_GET['table']) ? $_GET['table'] : '';
        $field = isset($_GET['field']) ? $_GET['field'] : '';
        $search = isset($_GET['search']) ? $_GET['search'] : '';

        if (empty($table) || empty($field) || empty($search)) {
            Response::json(['error' => 'Missing parameters']);
            return;
        }

        $db2 = Db2tablesServices::getDb();
        
        // Get primary key fields

        $ris = $db2->describes($table);
        $pkFields = $ris['keys'];
     
        // Get total non-null count for percentage calculation
        $totalQuery = "SELECT COUNT(*) as total FROM ".$db2->qn($table)." WHERE ".$db2->qn($field)." IS NOT NULL";
        $total = $db2->getRow($totalQuery)->total;

        // Build search query with counts and percentages
        $searchQuery = "SELECT 
            ".$db2->qn($field)." as value,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / ?, 2) as percentage
            FROM ".$db2->qn($table)."
            WHERE ".$db2->qn($field)." LIKE ? AND ".$db2->qn($field)." IS NOT NULL
            GROUP BY ".$db2->qn($field)."
            ORDER BY count DESC, ".$db2->qn($field)." ASC
            LIMIT 50";
        
        $results = $db2->getResults($searchQuery, [$total, '%' . $search . '%']);

        Response::json([
            'success' => true,
            'data' => $results,
            'primary_keys' => $pkFields
        ]);
    }

    /**
     * Get field details for the specified table and field
     */
    #[RequestAction('get_field_details')]
    public function actionGetFieldDetails() {
        $table = isset($_GET['table']) ? $_GET['table'] : '';
        $field = isset($_GET['field']) ? $_GET['field'] : '';
        
        // Use the service class to get field details
        $result = Db2tablesServices::getFieldDetails($table, $field);
        
        // Return the response as JSON
        Response::json($result);
    }

    /**
     * Handle import data form submission
     *
     * Processes the import form data and imports CSV data into a new table
     */
    #[RequestAction('import_csv_data')]
    public function actionImportCsvData() {
        
        // Check if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::json([
                'success' => false,
                'error' => 'Invalid request method'
            ]);
            return;
        }
        
        // Get form data
        $import_type = isset($_POST['importType']) ? $_POST['importType'] : '';
        $new_table_name = isset($_POST['newTableName']) ? $_POST['newTableName'] : '';
        $has_headers = isset($_POST['skipFirstRow']) ? (bool)$_POST['skipFirstRow'] : false;
        
        // Get primary key options
        $primary_key_type = isset($_POST['primaryKeyType']) ? $_POST['primaryKeyType'] : 'auto_increment';
        $primary_key_field = isset($_POST['primaryKeyField']) ? $_POST['primaryKeyField'] : '';
        $unique_field_type = isset($_POST['uniqueFieldType']) ? $_POST['uniqueFieldType'] : 'unique';
        
        // Get target table for existing table import
        $target_table = isset($_POST['targetTable']) ? $_POST['targetTable'] : '';
        $truncate_before_import = isset($_POST['truncateBeforeImport']) ? (bool)$_POST['truncateBeforeImport'] : false;
        
        // Get field mappings
        $field_mappings = isset($_POST['field_map']) ? $_POST['field_map'] : [];
        
        if ($import_type === 'existing') {
            if (empty($target_table)) {
                Response::json([
                    'success' => false,
                    'error' => 'Target table name is required for existing table import'
                ]);
            }
            
            // Verify target table exists
            $db2 = Db2tablesServices::getDb();
            $exists = $db2->getTables();
            
            if (!in_array($target_table, $exists)) {
                Response::json([
                    'success' => false,
                    'error' => 'Target table does not exist'
                ]);
            }
            
            // Set the table name for import
            $new_table_name = $target_table;
        } else {
            // For now, we only handle new table imports
            if ($import_type !== 'new') {
                Response::json([
                    'success' => false,
                    'error' => 'Only new table import is currently supported'
                ]);
            }
        }
        
        // Se il nome della tabella è vuoto, usa il nome del file
        if (empty($new_table_name) && isset($_FILES['importFile']['name'])) {
            $file_name = pathinfo($_FILES['importFile']['name'], PATHINFO_FILENAME);
            $new_table_name = $file_name;
        } elseif (empty($new_table_name)) {
            $new_table_name = 'imported_table_' . date('Ymd_His');
        }
        
        // Trasforma il nome della tabella in un formato valido (alphanumeric e underscore)
        $original_name = $new_table_name;

        $new_table_name = Db2tablesImportServices::sanitizeTableName($new_table_name); // Converti in minuscolo

        // Check if file was uploaded
        if (!isset($_FILES['importFile']) || $_FILES['importFile']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'No file uploaded';
            if (isset($_FILES['importFile']) && $_FILES['importFile']['error'] !== UPLOAD_ERR_OK) {
                switch ($_FILES['importFile']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = 'The uploaded file exceeds the maximum file size';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = 'The file was only partially uploaded';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = 'No file was uploaded';
                        break;
                    default:
                        $error_message = 'Unknown upload error';
                }
            }
            
            Response::json([
                'success' => false,
                'error' => $error_message
            ]);
            return;
        }
        
        // Initialize import service
        $importService = new Db2tablesImport();
        
        // Handle existing table import
        if ($import_type === 'existing') {
            // If truncate is requested, truncate the table first
            if ($truncate_before_import) {
                $db2->query("TRUNCATE TABLE `{$target_table}`");
            }
            
            // Import data into existing table
            $result = $importService->importCsvToExistingTable(
                $_FILES['importFile']['tmp_name'],
                $target_table,
                $field_mappings,
                $has_headers,
                $truncate_before_import
            );
            
            Response::json($result);
            return;
        }
        
        // For new table import, validate primary key options
        if ($primary_key_type === 'existing_field' && empty($primary_key_field)) {
            Response::json([
                'success' => false,
                'error' => 'Please select a field to use as primary key'
            ]);
            return;
        }
        
        // Prepara un messaggio se il nome della tabella è stato modificato
        $name_changed_message = '';
        if ($original_name !== $new_table_name) {
            $name_changed_message = "Il nome della tabella è stato modificato da '{$original_name}' a '{$new_table_name}' per rispettare le convenzioni SQL.";
        }
        // Get CSV parsing options
        $csv_separator = isset($_POST['csvSeparator']) ? $_POST['csvSeparator'] : ',';
        $csv_enclosure = isset($_POST['csvEnclosure']) && $_POST['csvEnclosure'] !== '' ? $_POST['csvEnclosure'] : '"';
        $csv_escape = isset($_POST['csvEscape']) && $_POST['csvEscape'] !== '' ? $_POST['csvEscape'] : '\\';
        // Import CSV to new table
        $result = $importService->importCsvToNewTable($new_table_name, $_FILES['importFile'], $has_headers, $this->model, $primary_key_type, $primary_key_field, $unique_field_type, $csv_separator, $csv_enclosure, $csv_escape);
        
        // Aggiungi il messaggio sul cambio del nome se necessario
        if ($result['success'] && !empty($name_changed_message)) {
            $result['message'] = $name_changed_message . ' ' . $result['message'];
        }
        
        // Return the response as JSON
        Response::json($result);
    }

    /**
     * Create a new table with a primary key field
     */
    #[RequestAction('create-table')]
    public function actionCreateTable() {
        // Get JSON data from request
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data);
        
        // Validate required data
        if (!$data || !isset($data->table_name) || !isset($data->primary_key)) {
            Response::json([
                'success' => false,
                'error' => 'Table name and primary key are required'
            ]);
            return;
        }
        
        $table_name = trim($data->table_name);
        $primary_key = trim($data->primary_key);
        
        // Validate table name and primary key (alphanumeric and underscores only)
        if (!preg_match('/^[a-z0-9_]+$/', $table_name)) {
            Response::json([
                'success' => false,
                'error' => 'Table name can only contain lowercase letters, numbers, and underscores'
            ]);
            return;
        }
        
        if (!preg_match('/^[a-z0-9_]+$/', $primary_key)) {
            Response::json([
                'success' => false,
                'error' => 'Primary key field name can only contain lowercase letters, numbers, and underscores'
            ]);
            return;
        }
        
        // Check if table already exists
        $db2 = Db2tablesServices::getDb();
        $tables = $db2->getTables();
        $table_name = Config::get('prefix')."_".str_replace(["#_", Config::get('prefix')."_"], '', $table_name);

        if (in_array($table_name, $tables)) {
            Response::json([
                'success' => false,
                'error' => 'A table with this name already exists'
            ]);
            return;
        }

        $schema = Get::schema($table_name, $db2);
        $schema->id($primary_key);

        if (!$schema->create()) {
            Response::json([
                'success' => false,
                'error' => 'Failed to create table: ' . $schema->last_error
            ]);
            return;
        }
        
        // Return success response with the table name
        Response::json([
            'success' => true,
            'table_name' => $table_name,
            'message' => 'Table created successfully'
        ]);
    }

    #[RequestAction('home')]
    protected function actionHome() {
        // Generate the sidebar with tables and views
        $sidebarHtml = $this->generateTablesSidebar();
        
        // Get database information from service
        $db_info = Db2tablesServices::getDatabaseInfo();
        
        // Get all tables and views for the export modal
        $tables_and_views = $this->model->getAllTablesAndViews();
        
        // Prepare variables for the layout view
        $viewData = [
            'sidebarHtml' => $sidebarHtml,
            'db_type' => $db_info['db_type'],
            'db_version' => $db_info['db_version'],
            'db_size' => $db_info['db_size'],
            'tables_count' => $db_info['tables_count'],
            'tables_and_views' => $tables_and_views,
            'model' => $this->model
        ];
        
        // Load the theme using a dedicated layout view
        Response::themePage('default', __DIR__ . '/Views/db2tables-home.page.php', $viewData);
    }

    #[RequestAction('import_csv_page')]
    protected function actionImportCsvPage() {
        Theme::set('javascript', Route::url().'/Modules/Db2tables/Assets/import-csv.js');
        // Generate the sidebar with tables and views
        $sidebarHtml = $this->generateTablesSidebar();
        
        // Get database information from service
        $db_info = Db2tablesServices::getDatabaseInfo();
        
        // Get all tables and views for the export modal
        $tables_and_views = $this->model->getAllTablesAndViews();
        
        // Prepare variables for the layout view
        $viewData = [
            'sidebarHtml' => $sidebarHtml,
            'tables_and_views' => $tables_and_views,
            'model' => $this->model
        ];
        
        // Load the theme using a dedicated layout view
        Response::themePage('default', __DIR__ . '/Views/db2tables-import-csv.page.php', $viewData);
    }

    #[RequestAction('view-table')]
    protected function actionViewTable() {
        // ini_set('display_errors', 1);
        // error_reporting(E_ALL);

        // Get the table name from the request
        $table_name = isset($_REQUEST['table']) ? $_REQUEST['table'] : '';
        $db2 = Db2tablesServices::getDb();
        
        if (empty($table_name)) {
            Route::redirect('?page=db2tables');
            return;
        }

        // Get all tables and views
        $all_tables_and_views = $this->model->getAllTablesAndViews();
        
        // Check if the requested table exists
        $table_exists = false;
        foreach ($all_tables_and_views as $item) {
            if ($item['name'] === $table_name) {
                $table_exists = true;
                break;
            }
        }
        
        // If the table doesn't exist, redirect to the main page with an error message
        if (!$table_exists) {
            // Redirect with error message
            Route::redirectError('?page=db2tables', "The table or view '$table_name' does not exist in the database.");
            return;
        }

        // Generate the sidebar with tables and views
        $sidebarHtml = $this->generateTablesSidebar($table_name);

        // Check if the current table is a view
        $is_view = false;
        $view_definition = null;
        foreach ($all_tables_and_views as $item) {
            if ($item['name'] === $table_name && $item['type'] === 'view') {
                $is_view = true;
                $view_definition = Db2tablesServices::getDb()->getViewDefinition($table_name);
                break;
            }
        }

        // For initial page load, prepare the table model
        $structure = $this->model->getTableStructure($table_name);
        // Handle JSON requests for table data (AJAX pagination)
        if (($_REQUEST['page-output'] ?? '') == 'json' && isset($_REQUEST['table_id'])) {
            $table_id = $_REQUEST['table_id'];
            
            // Determine which table to load based on the table_id
            if ($table_id === 'tableEditId') {
                $table_html = Db2tablesServices::getHtmlEditTable($table_name);
            } else {
                $table_html = Db2tablesServices::getHtmlDataTable($table_name);
            }
            
            Response::themePage('json', '', json_encode([
                'html' => $table_html, 
                'success' => 'true', 
                'msg' => ''
            ]));
            return;
        }

        $table_structure = $this->model->getTableStructure($table_name);
        $primary_keys = [];
        foreach ($table_structure as $field_name => $field_info) {
            if ($field_info->Key === 'PRI') {
                $primary_keys[] = $field_name;
            }
        }
        
        $table_html1 = null;
        $table_html2 = null;
        // If there's no single primary key, return an alert
        if (count($primary_keys) !== 1) {
            $table_html1 = Db2tablesServices::getHtmlDataTable($table_name);
        } else {
            $table_html2 = Db2tablesServices::getHtmlEditTable($table_name);
        }

        // Load the table view content
        ob_start();
        require __DIR__ . '/Views/table_view.page.php';
        $pageContent = ob_get_clean();
        
        // Prepare variables for the layout view
        $viewData = [
            'sidebarHtml' => $sidebarHtml,
            'pageContent' => $pageContent
        ];
        
        // Load the theme using a dedicated layout view
        Response::themePage('default', __DIR__ . '/Views/db2tables-layout.page.php', $viewData);
    }
    
    /**
     * Display a single record in an offcanvas view
     *
     * @return void
     */
    #[RequestAction('single_offcanvas_view')]
    public function actionSingleOffcanvasView() {
        // Get the table name and record ID from the request
        $table_name = isset($_POST['table']) ? $_POST['table'] : '';
        $record_id = isset($_POST['id']) ? $_POST['id'] : '';
        
        // Use the service class to get the record view
        $result = Db2tablesTableEditServices::getSingleRecordView($table_name, $record_id);
        
        // Return the response as JSON
        Response::json($result);
    }
    
    /**
     * Display a form to edit a record in an offcanvas view
     * If record_id is 0 or not provided, it will create a new record form
     *
     * @return void
     */
    #[RequestAction('edit_offcanvas_view')]
    public function actionEditOffcanvasView() {
        // Get the table name and record ID from the request
        $table_name = isset($_POST['table']) ? $_POST['table'] : '';
        $record_id = isset($_POST['id']) ? $_POST['id'] : '0';
        
        // Use the service class to get the edit form
        $result = Db2tablesTableEditServices::getEditRecordForm($table_name, $record_id);
        
        // Return the response as JSON
        Response::json($result);
    }
    
    /**
     * Save changes to a record
     *
     * @return void
     */
    #[RequestAction('save_record')]
    public function actionSaveRecord() {
        // Use the service class to save the record
        $result = Db2tablesTableEditServices::saveRecord($_POST);
        
        // Return the response as JSON
        Response::json($result);
    }
    
    /**
     * Delete a record from a table
     *
     * @return void
     */
    #[RequestAction('delete_record')]
    public function actionDeleteRecord() {
        // Get the table name and record ID from the request
        $table_name = isset($_POST['table']) ? $_POST['table'] : '';
        $record_id = isset($_POST['id']) ? $_POST['id'] : '';
        
        // Use the service class to delete the record
        $result = Db2tablesTableEditServices::deleteRecord($table_name, $record_id);
        
        // Return the response as JSON
        Response::json($result);
    }
    
    /**
     * Export table data to CSV
     */
    #[RequestAction('export-csv')]
    protected function actionExportCsv() {
        $table_name = isset($_GET['table']) ? $_GET['table'] : '';
        $query = isset($_GET['query']) ? $_GET['query'] : '';
        
        // Use the export service to handle CSV export
        $export_service = new Db2tablesExportServices();
        $export_service->exportCsv($table_name, $query, $this->model);
    }
    
    /**
     * Export query results as SQL INSERT statements
     */
    #[RequestAction('export-sql')]
    public function actionExportSql() {
        // Get JSON data from request
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data);
        
        // Validate required data
        if (!$data || !isset($data->query) || !isset($data->table)) {
            Response::json([
                'success' => false,
                'error' => 'Query and table are required'
            ]);
            return;
        }
        
        $table_name = $data->table;
        $query = $data->query;

        // Use the export service to handle SQL export
        $export_service = new Db2tablesExportServices();
        $result = $export_service->exportSql($table_name, $query,$this->model);
        
        // Return the response as JSON
        Response::json($result);
    }
    
    /**
     * Execute a custom SQL query
     */
    #[RequestAction('execute-query')]
    public function actionExecuteQuery() {
        // Get JSON data from request
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data);
        
        // Validate required data
        if (!$data || !isset($data->query)) {
            Response::json([
                'error' => 'Query is required'
            ]);
            return;
        }
        
        // Check if we have a specific queryId (for pagination updates)
        $queryId = isset($data->queryId) ? $data->queryId : null;
        $rowsPerPage = isset($data->rowsPerPage) ? (int)$data->rowsPerPage : null;
        $page = isset($data->page) ? (int)$data->page : 0;
        
        // Use the query service to handle query execution
        $query_service = new Db2tablesQueryServices();
        $result = $query_service->executeQuery($data->query, $queryId, $rowsPerPage, $page);
        
        // Return the response as JSON
        Response::json($result);
    }

    /**
     * Handle view definition updates
     *
     * Receives view name and SQL definition, validates the SQL, and updates the view if valid
     * Allows changing the view name if the new name doesn't already exist
     */
    #[RequestAction('edit-view-definition')]
    public function actionEditViewDefinition() {
        // Get JSON data from request
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data);
        
        // Validate required data
        if (!$data || !isset($data->viewName) || !isset($data->originalViewName) || 
            !isset($data->viewDefinition) || !isset($data->token)) {
            Response::json([
                'success' => false,
                'error' => 'Missing required data'
            ]);
            return;
        }
        
        $view_name = $data->viewName;
        $original_view_name = $data->originalViewName;
        $view_definition = $data->viewDefinition;
        $token = $data->token;
        
        // Use the structure service to handle view definition updates
       
        $result = Db2tablesStructureServices::updateViewDefinition($view_name, $original_view_name, $view_definition, $token);
        
        // Return the response as JSON
        Response::json($result);
    }
    
    /**
     * Preview table structure changes
     *
     * Analyzes the submitted form data and returns the SQL statements that would be executed
     * without actually making any changes to the database
     */
    #[RequestAction('preview_table_structure')]
    public function actionPreviewTableStructure() {
        // Check if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::json([
                'success' => false,
                'error' => 'Invalid request method'
            ]);
            return;
        }
        
        // Get the table name and token
        $table_name = isset($_POST['table_name']) ? $_POST['table_name'] : '';
        $structure_token = isset($_POST['structure_token']) ? $_POST['structure_token'] : '';
        
        // Validate token
        if (!Token::checkValue($structure_token, 'editStructure' . $table_name)) {
            Response::json([
                'success' => false,
                'error' => 'Invalid security token'
            ]);
            return;
        }
        
        // Get fields data
        $fields = isset($_POST['fields']) ? $_POST['fields'] : [];
        
        if (empty($table_name) || empty($fields)) {
            Response::json([
                'success' => false,
                'error' => 'Missing required data'
            ]);
            return;
        }
        
        // Use the structure service to preview table structure updates
        $result = Db2tablesStructureServices::previewTableStructure($table_name, $fields);
        
        // Return the response as JSON
        Response::json($result);
    }
    
    /**
     * Save table structure changes
     *
     * Handles modifications to the table structure including field additions, modifications, and deletions
     */
    #[RequestAction('save_table_structure')]
    public function actionSaveTableStructure() {
        // Check if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::json([
                'success' => false,
                'error' => 'Invalid request method'
            ]);
            return;
        }
        
        // Get the table name and token
        $table_name = isset($_POST['table_name']) ? $_POST['table_name'] : '';
        $structure_token = isset($_POST['structure_token']) ? $_POST['structure_token'] : '';
        
        // Validate token
        if (!Token::checkValue($structure_token, 'editStructure' . $table_name)) {
            Response::json([
                'success' => false,
                'error' => 'Invalid security token'
            ]);
            return;
        }
        
        // Get fields data
        $fields = isset($_POST['fields']) ? $_POST['fields'] : [];
        
        if (empty($table_name) || empty($fields)) {
            Response::json([
                'success' => false,
                'error' => 'Missing required data'
            ]);
            return;
        }
        
        // Use the structure service to handle table structure updates
        $result = Db2tablesStructureServices::updateTableStructure($table_name, $fields);
        
        // Return the response as JSON
        Response::json($result);
    }

    /**
     * Handle export data form submission
     *
     * Processes the export form data and exports selected tables in the chosen format
     */
    #[RequestAction('export_data')]
    public function actionExportData() {
        // Check if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::json([
                'success' => false,
                'error' => 'Invalid request method'
            ]);
            return;
        }
        
        // Get form data
        $tables = isset($_POST['tables']) ? $_POST['tables'] : [];
        $exportFormat = isset($_POST['exportFormat']) ? $_POST['exportFormat'] : 'csv';
        $includeHeaders = isset($_POST['includeHeaders']) ? (bool)$_POST['includeHeaders'] : false;
        $includeStructure = isset($_POST['includeStructure']) ? (bool)$_POST['includeStructure'] : false;
        
        if (empty($tables)) {
            Response::json([
                'success' => false,
                'error' => 'No tables selected for export'
            ]);
            return;
        }
        
        // Initialize export service
        $exportService = new Db2tablesExportServices();
        
        // Export based on format
        if ($exportFormat === 'csv') {
            $result = $exportService->exportTablesCsv($tables, $includeHeaders, $this->model);
        } else { // SQL format
            $result = $exportService->exportTablesSql($tables, $includeStructure, $this->model);
        }
        
        // Return the response as JSON
        Response::json($result);
    }
    
    /**
     * Get fields for a specific table
     *
     * Returns a list of field names for the specified table
     */
    #[RequestAction('get_table_fields')]
    public function actionGetTableFields() {
        // Get the table name from the request
        $table = isset($_GET['table']) ? $_GET['table'] : '';

        if (empty($table)) {
            Response::json([
                'success' => false,
                'error' => 'Table name is required'
            ]);
            return;
        }

        // Get the table structure
        $structure = $this->model->getTableStructure($table);

        if (empty($structure)) {
            Response::json([
                'success' => false,
                'error' => 'Table not found or has no structure'
            ]);
            return;
        }

        // Extract field names
        $fields = array_map(function($field) {
            return $field->Field;
        }, $structure);

        // Return the field names as JSON
        Response::json([
            'success' => true,
            'fields' => $fields
        ]);
    }

    /**
     * Load the structure tab content for a table
     *
     * This action is called via AJAX when the structure tab is clicked
     */
    #[RequestAction('load_structure_tab')]
    public function actionLoadStructureTab() {
        // Get the table name from the request
        $table_name = isset($_GET['table']) ? $_GET['table'] : '';
        
        if (empty($table_name)) {
            Response::json([
                'success' => false,
                'error' => 'Table name is required'
            ]);
            return;
        }

        // Get the table structure
        $structure = $this->model->getTableStructure($table_name);
        
        if (empty($structure)) {
            Response::json([
                'success' => false,
                'error' => 'Table not found or has no structure'
            ]);
            return;
        }
        
        // Prepare data for the view
        $viewData = [
            'table_name' => $table_name,
            'structure' => $structure
        ];

        $tables_and_views = $this->model->getAllTablesAndViews();
        $is_view = false;
        foreach ($tables_and_views as $item) {
            if ($item['name'] === $table_name && $item['type'] === 'view') {
                $is_view = true;
                break;
            }
        }
        
        // Capture the output of the view file
        if (!$is_view) {
            ob_start();
            include __DIR__ . '/Views/table_structure.view.php';
            $html = ob_get_clean();
        } else {
            $html = '';
        }
        
        // Return the HTML as JSON
        Response::json([
            'success' => true,
            'html' => $html
        ]);
    }
    
    private function generateTablesSidebar($active_table = '') {
        // Get all tables and views
        $tables_and_views = $this->model->getAllTablesAndViews();
        
        // Group by type
        $tables = array_filter($tables_and_views, function($item) {
            return $item['type'] === 'table';
        });
        
        $views = array_filter($tables_and_views, function($item) {
            return $item['type'] === 'view';
        });
        
        // Prepare data for the view
        $viewData = [
            'tables' => $tables,
            'views' => $views,
            'active_table' => $active_table
        ];
        
        // Capture the output of the view file
        ob_start();
        include __DIR__ . '/Views/sidebar.view.php';
        $sidebar = ob_get_clean();
        
        return $sidebar;
    }

    #[RequestAction('truncate_table')]
    public function actionTruncateTable() {
        $this->serviceJsonTokenCheck();
        $table = isset($_REQUEST['table']) ? $_REQUEST['table'] : '';
        $result = Db2tablesStructureServices::truncateTable($table);
        Response::json($result);
    }   

    #[RequestAction('drop_table')]
    public function actionDropTable() {
        $this->serviceJsonTokenCheck();

        $table = isset($_POST['table']) ? $_POST['table'] : '';
        $is_view = isset($_POST['is_view']) ? filter_var($_POST['is_view'], FILTER_VALIDATE_BOOLEAN) : false;
        $result = Db2tablesStructureServices::dropTable($table, $is_view);
        Response::json($result);
    }

    #[RequestAction('rename_table')]
    public function actionRenameTable() {
        $this->serviceJsonTokenCheck();
        $table = isset($_POST['table']) ? $_POST['table'] : '';
        $new_name = isset($_POST['new_name']) ? $_POST['new_name'] : '';
        $result = Db2tablesStructureServices::renameTable($table, $new_name);
        Response::json($result);
    }

    private function serviceJsonTokenCheck() {
        $token = isset($_POST['token']) ? $_POST['token'] :
                 (isset($_GET['token']) ? $_GET['token'] : '');

        if (!Token::checkValue($token, 'db2tables' )) {
            Response::json([
                'success' => false,
                'error' => 'Invalid security token'
            ]);
            return;
        }
    }
}
