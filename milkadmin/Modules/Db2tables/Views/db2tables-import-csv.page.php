<?php
namespace Modules\Db2tables\Views;

use App\MessagesHandler;

/**
 * db2tables-import-csv.page.php
 */
// Ensure this file is not accessed directly
!defined('MILK_DIR') && die();

// Extract variables passed from the router
if (!isset($sidebarHtml)) $sidebarHtml = '<p>Error: Sidebar not generated.</p>';
if (!isset($pageContent)) $pageContent = '<p>Error: Page content not loaded.</p>';

?>

<div class="container-fluid">
    <div class="row">
        
        <!-- Sidebar Column -->
        <div class="col-md-4 col-lg-3 p-0" id="db2tSidebar">
            <?php echo $sidebarHtml; // Output the generated sidebar HTML ?>
        </div>

        <!-- Content Column -->
        <div class="col-md-8 col-lg-9 pt-3 px-4 bg-white" id="db2tContent">
            <?php MessagesHandler::displayMessages(); ?>
            
            <!-- Database Dashboard -->
            <div class="dashboard-container">
                <h5 class="modal-title" id="importModalLabel">Import Data</h5>

                    <form id="importForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="importFile" class="form-label">Select File</label>
                            <input class="form-control" type="file" id="importFile" name="importFile" accept=".csv">
                            <div class="form-text">Supported format: CSV</div>
                        </div>
                        
                        <div class="mb-3" id="importTypeSection">
                            <label for="importType" class="form-label">Import Type</label>
                            <select class="form-select" id="importType" name="importType">
                                <option value="new" selected>Create New Table</option>
                                <option value="existing">Import to Existing Table</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="existingTableSection">
                            <label for="targetTable" class="form-label">Target Table</label>
                            <select class="form-select" id="targetTable" name="targetTable">
                                <option value="">Select a table...</option>
                                <?php 
                                // Get tables from the model
                                $tables = $model->getTables();
                                foreach ($tables as $table) {
                                    echo '<option value="' . htmlspecialchars($table['name']) . '">' . htmlspecialchars($table['name']) . '</option>';
                                }
                                ?>
                            </select>

                            <!-- Field mapping container -->
                            <div id="fieldMappingContainer" class="mt-3"></div>
                        </div>
                        
                        <div class="mb-3 d-none" id="newTableSection">
                            <label for="newTableName" class="form-label">New Table Name</label>
                            <input type="text" class="form-control" id="newTableName" name="newTableName" placeholder="Enter table name">
                            <div class="form-text">Use only lowercase letters, numbers, and underscores</div>
                        </div>
                         
                        <div class="mb-3" id="importOptionsSection">
                            <label class="form-label">Import Options</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skipFirstRow" 
                                value="1"
                                name="skipFirstRow" checked>
                                <label class="form-check-label" for="skipFirstRow">
                                    First row contains headers
                                </label>
                            </div>
                        </div>
                        
                        <!-- CSV Options (hidden until file is uploaded) -->
                        <div class="mb-3 d-none" id="csvOptionsSection">
                            <div class="row fs-7">
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="form-label mb-0">CSV Options:</label>
                                        <div class="d-flex align-items-center">
                                            <label for="csvSeparator" class="form-label mb-0 me-1">Separator</label>
                                            <input type="text" class="form-control form-control-sm" id="csvSeparator" name="csvSeparator" maxlength="1" style="width: 40px;">
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <label for="csvEnclosure" class="form-label mb-0 me-1">Enclosure</label>
                                            <input type="text" class="form-control form-control-sm" id="csvEnclosure" name="csvEnclosure" maxlength="1" style="width: 40px;">
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <label for="csvEscape" class="form-label mb-0 me-1">Escape</label>
                                            <input type="text" class="form-control form-control-sm" id="csvEscape" name="csvEscape" maxlength="1" style="width: 40px;">
                                        </div>
                                        <small class="text-body-secondary">Detected automatically</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Primary Key Options (always visible for new tables) -->
                        <div class="mb-3 d-none" id="primaryKeyOptions">
                            <label class="form-label fw-bold">Primary Key Options</label>
                            <div class="card border-info mb-3">
                                <div class="card-body">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="primaryKeyType" id="autoIncrementId" value="auto_increment" checked>
                                        <label class="form-check-label fw-semibold" for="autoIncrementId">
                                            Create auto-increment ID field
                                        </label>
                                        <div class="form-text">A new auto-increment ID field will be added as the primary key</div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="primaryKeyType" id="useExistingField" value="existing_field">
                                        <label class="form-check-label fw-semibold" for="useExistingField">
                                            Use an existing field as unique identifier
                                        </label>
                                    </div>
                                    <div id="existingFieldSelector" class="mt-3 d-none">
                                        <label for="uniqueFieldType" class="form-label">Field type</label>
                                        <select class="form-select mb-3" id="uniqueFieldType" name="uniqueFieldType">
                                            <option value="unique">Add as UNIQUE constraint (ID will still be created as auto-increment primary key)</option>
                                            <option value="primary">Use as PRIMARY KEY (field must contain sequential numbers with no duplicates)</option>
                                        </select>
                                        
                                        <label for="primaryKeyField" class="form-label">Select field from CSV headers</label>
                                        <select class="form-select" id="primaryKeyField" name="primaryKeyField">
                                            <option value="">Upload a CSV file to see available fields</option>
                                        </select>
                                        <div class="form-text">This field will be used as a unique identifier for future updates</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="importError" class="alert alert-danger d-none"></div>
                    </form>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="importBtn">Import</button>
                </div>
            </div>
        </div>
    </div>                    
</div>
<br><br>                          