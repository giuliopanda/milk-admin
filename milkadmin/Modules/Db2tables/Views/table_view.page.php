<?php
namespace Modules\Db2tables\Views;

use App\{Token, Route};

// Ensure this file is not accessed directly
!defined('MILK_DIR') && die();

// Extract variables from viewData if available
if (isset($viewData)) {
    extract($viewData);
}

// Set default values if not set
if (!isset($table_name)) $table_name = '';
if (!isset($structure)) $structure = [];
if (!isset($table_id)) $table_id = '';
if (!isset($total_rows)) $total_rows = 0;
?>

<script>
// Tutte le tabelle del database
window.allTables = <?php 
    $all_tables = $this->model->getAllTablesAndViews();
    $table_names = array_map(function($t) { return $t['name']; }, $all_tables);
    $table_names = array_values($table_names);
    echo json_encode($table_names);
?>;

// Struttura di tutti i campi per ogni tabella
window.allFields = <?php
    $fields_data = [];
    foreach($all_tables as $table) {
        $table_structure = $this->model->getTableStructure($table['name']);
        $fields_data[$table['name']] = array_map(function($field) {
            return $field->Field;
        }, $table_structure);
        $fields_data[$table['name']] = array_values($fields_data[$table['name']]);
    }
    echo json_encode($fields_data);
?>;
</script>

<div id="tokenContainer" data-token="<?php echo Token::get('db2tables'); ?>"></div>
<div class="container-fluid p-0">
    <div class="d-flex align-items-center mb-3">
        <button type="button" class="btn btn-outline-secondary me-3" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <h2 class="mb-0">Table: <?php echo htmlspecialchars($table_name); ?></h2>
    </div>
    
    <ul class="nav nav-tabs" id="tableTabs" role="tablist">
    <?php if ($table_html1 != null) : ?>    
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="data-tab" data-bs-toggle="tab" data-bs-target="#data" type="button" role="tab" aria-controls="data" aria-selected="true">Data</button>
        </li>
        <?php endif; ?>
        <?php if ($table_html2 != null) : ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab" aria-controls="edit" aria-selected="true">Edit Data</button>
        </li>
        <?php endif; ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="fields-tab" data-bs-toggle="tab" data-bs-target="#fields" type="button" role="tab" aria-controls="fields" aria-selected="false">Fields</button>
        </li>
        
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="structure-tab" data-bs-toggle="tab" data-bs-target="#structure" type="button" role="tab" aria-controls="structure" aria-selected="false">Structure</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="query-tab" data-bs-toggle="tab" data-bs-target="#query" type="button" role="tab" aria-controls="query" aria-selected="false">Query</button>
        </li>
    </ul>
    
    <div class="tab-content" id="tableTabsContent">
        <div class="tab-pane fade" id="fields" role="tabpanel" aria-labelledby="fields-tab">
            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="list-group">
                        <?php if (!empty($structure)): ?>
                            <?php foreach ($structure as $field): ?>
                                <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center field-item" data-fieldname="<?php echo htmlspecialchars($field->Field); ?>">
                                    <?php echo htmlspecialchars($field->Field); ?>
                                    <small class="text-body-secondary"><?php echo htmlspecialchars($field->Type); ?></small>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Field Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-4 fw-bold">Type:</div>
                                <div class="col-8" id="field-type">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4 fw-bold">Nullable:</div>
                                <div class="col-8" id="field-null">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4 fw-bold">Key:</div>
                                <div class="col-8" id="field-key">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4 fw-bold">Default:</div>
                                <div class="col-8" id="field-default">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4 fw-bold">Extra:</div>
                                <div class="col-8" id="field-extra">-</div>
                            </div>
                            <div id="field-stats" class="mt-4" style="display: none;">
                                <h6 class="mb-3">Statistics</h6>

                                <!-- Field statistics summary -->
                                <div id="field-summary" class="alert alert-info mb-4" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Distinct Count:</strong> <span id="distinct-count">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Null Count:</strong> <span id="null-count">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Total Rows:</strong> <span id="total-rows">-</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Top values table -->
                                <div id="top-values-stats" style="display: none;">
                                    <h6 class="text-body-secondary mb-2">Value Distribution</h6>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="text-body-secondary">Top 50 Values</div>
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control form-control-sm" id="value-search" placeholder="Search value..." aria-label="Search values">
                                            <button class="btn btn-outline-secondary btn-sm" type="button" id="search-button">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Value</th>
                                                    <th>Count</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody id="top-values-body"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Numeric statistics table -->
                                <div id="numeric-stats" style="display: none;">
                                    <h6 class="text-body-secondary mb-2">Numeric Statistics</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <tbody>
                                                <tr>
                                                    <td>Mean</td>
                                                    <td id="stat-mean">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Standard Deviation</td>
                                                    <td id="stat-stddev">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Minimum</td>
                                                    <td id="stat-min">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Maximum</td>
                                                    <td id="stat-max">-</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($table_html1 != null) : ?>
            <div class="tab-pane fade show active" id="data" role="tabpanel" aria-labelledby="data-tab">
                <div class="mt-3">
                    <?php if (!(isset($is_view) && $is_view)) : ?>
                        <div class="alert alert-info fs-7">You cannot edit automatically this table because it does not have a single primary key field. You can edit it writing a query sql.</div>
                    <?php endif; ?>
                    
                    <div class="d-flex mb-3">
                        <input class="form-control ms-2 d-inline-block" type="search" placeholder="Search" aria-label="Search" spellcheck="false" data-ms-editor="true" id="searchTable" style="width:200px">
                        <span class="btn btn-outline-primary" onClick="table_data_search()">Search</span>
                    </div>
                    <div class="table-responsive">
                        <?php echo $table_html1; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($table_html2 != null) : ?>
            <div class="tab-pane fade show active" id="edit" role="tabpanel" aria-labelledby="edit-tab">
                <input type="hidden" id="editTableName" value="<?php echo $table_name; ?>">
                <input type="hidden" id="editToken" value="<?php echo Token::get('editTable'.$table_name); ?>">
                <div class="my-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-outline-primary" id="addNewBtn">
                            <i class="bi bi-plus-circle"></i> Add new
                        </button>
                        <div class="d-flex">
                            <input class="form-control ms-2 d-inline-block" type="search" placeholder="Search" aria-label="Search" spellcheck="false" data-ms-editor="true" id="searchEditTable" style="width:200px">
                            <span class="btn btn-outline-primary" onClick="table_edit_search()">Search</span>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <?php echo $table_html2; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="tab-pane fade" id="structure" role="tabpanel" aria-labelledby="structure-tab">
            <div id="structureTabContent"></div>

            <?php if (isset($is_view) && $is_view): ?>
                <form id="viewDefinitionForm">
                    <div class="db2tables-main-box">
                        <div class="db2tables-main-box-background"></div>
                        <div class="db2tables-main-box-title">View Configuration</div>
                        <div class="db2tables-main-box-row">
                            <label class="db2tables-main-box-label" for="viewName">Name:</label>
                            <input type="text" class="db2tables-main-box-input" id="viewName" name="viewName" value="<?php echo htmlspecialchars($table_name); ?>">
                            <input type="hidden" id="originalViewName" value="<?php echo htmlspecialchars($table_name); ?>">
                        </div>
                        <div class="db2tables-main-box-row">
                            <label class="db2tables-main-box-label" for="viewDefinition">SQL:</label>
                            <textarea class="form-control" id="viewDefinition" name="viewDefinition" rows="5" style="z-index: 1; position: relative;"><?php echo htmlspecialchars($view_definition ?? ''); ?></textarea>
                        </div>
                        <div style="text-align: right; position: relative; z-index: 1; margin-top: 1rem;">
                            <button type="button" id="saveViewDefinitionBtn" class="btn btn-primary" data-token="<?php echo Token::get('editView'.$table_name); ?>">
                                <i class="bi bi-save"></i> Save View Definition
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Table Management</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!$is_view): ?>
                        <button type="button" class="btn btn-warning" onclick="truncateTable('<?php echo htmlspecialchars($table_name); ?>')">
                            <i class="bi bi-trash3"></i> Empty Table
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-danger" onclick="dropTable('<?php echo htmlspecialchars($table_name); ?>', <?php echo $is_view ? 'true' : 'false'; ?>)">
                            <i class="bi bi-x-circle"></i> <?php echo $is_view ? 'Drop View' : 'Drop Table'; ?>
                        </button>
                        <?php if (!$is_view): ?>
                        <button type="button" class="btn btn-primary" onclick="renameTable('<?php echo htmlspecialchars($table_name); ?>')">
                            <i class="bi bi-pencil-square"></i> Rename <?php echo $is_view ? 'View' : 'Table'; ?>
                        </button>
                        <?php endif; ?>                    </div>
                </div>
            </div>
        </div>
        
        <div class="tab-pane fade" id="query" role="tabpanel" aria-labelledby="query-tab">
            <div class="mt-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">SQL Query Editor</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-9 mb-3 position-relative">
                                <textarea class="form-control" id="sqlQuery" rows="5" placeholder="Enter your SQL query here...">SELECT * FROM `<?php echo htmlspecialchars($table_name); ?>`</textarea>
                            </div>
                            <div class="col-md-3 d-flex flex-column gap-2">
                                <button class="btn btn-primary" id="executeQuery" data-token="<?php echo Token::get('executeQuery'); ?>">
                                    <i class="bi bi-play-fill"></i> Execute Query
                                </button>
                                <div class="btn-group" role="group" id="exportBtns">
                                    <button type="button" class="btn btn-outline-primary dropdown-toggle"  data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-download"></i> Export
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="<?php echo Route::url('?page=db2tables&action=export-csv&table=' . urlencode($table_name)); ?>" id="exportCsvBtn"><i class="bi bi-filetype-csv"></i> Download CSV</a></li>
                                        <li><a class="dropdown-item" href="#" id="exportSqlBtn"><i class="bi bi-filetype-sql"></i> Download SQL</a></li>
                                    </ul>
                                </div>
                                <div id="exportBtnsDownloading" style="display: none;">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    <span class="sr-only">downloading...</span>
                                </div>
                                <button class="btn btn-outline-secondary" id="createViewBtn">
                                    <i class="bi bi-table"></i> Create View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="queryResults" class="mt-3">
                    <!-- Query results will be displayed here -->
                </div>
            </div>
        </div>
    </div>
</div>

