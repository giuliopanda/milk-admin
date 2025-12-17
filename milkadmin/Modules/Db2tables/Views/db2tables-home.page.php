<?php
namespace Modules\Db2tables\Views;

use App\{Get, MessagesHandler, Route};

// Ensure this file is not accessed directly
!defined('MILK_DIR') && die();

// Extract variables passed from the router
if (!isset($sidebarHtml)) $sidebarHtml = '<p>Error: Sidebar not generated.</p>';
if (!isset($pageContent)) $pageContent = '<p>Error: Page content not loaded.</p>';

// Database information variables from router
if (!isset($db_type)) $db_type = 'Unknown';
if (!isset($db_version)) $db_version = 'Unknown';
if (!isset($db_size)) $db_size = '0 B';
if (!isset($tables_count)) $tables_count = '0';
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
                <!-- Database Info Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="mb-3">Database Dashboard</h2>
                    </div>
                    
                    <!-- Database Info Cards -->
                    <div class="col-lg-3 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">Server Type</h5>
                                <p class="card-text fs-4"><?php echo $db_type; ?></p>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <small>Database Engine</small>
                                <i class="fas fa-database"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">Server Version</h5>
                                <p class="card-text fs-4"><?php echo $db_version; ?></p>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <small>Current Version</small>
                                <i class="fas fa-code-branch"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 mb-3">
                        <div class="card bg-warning text-dark h-100">
                            <div class="card-body">
                                <h5 class="card-title">Database Size</h5>
                                <p class="card-text fs-4"><?php echo $db_size; ?></p>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <small>Total Storage Used</small>
                                <i class="fas fa-hdd"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 mb-3">
                        <div class="card bg-info text-white h-100">
                            <?php if(\Modules\Db2tables\Db2tablesServices::getDb()->type == 'sqlite') : ?>
                                <div class="card-body">
                                    <h5 class="card-title">Path</h5>
                                    <p class="card-text fs-7" style="word-break: break-all;">
                                        <?php echo \Modules\Db2tables\Db2tablesServices::getDb()->dbname; ?>
                                    </p>
                                </div>  
                            <?php else : ?>
                            <div class="card-body">
                                <h5 class="card-title">Tables & Views</h5>
                                <p class="card-text fs-4"><?php echo $tables_count; ?></p>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <small>Total Objects</small>
                                <i class="fas fa-table"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="row mb-4">
                    <div class="col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Create Table</h5>
                                <p class="card-text">Create a new table with custom fields and properties</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTableModal">Create Table</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-export fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Export Data</h5>
                                <p class="card-text">Export table data to CSV or SQL format for backup or analysis.</p>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">Export Data</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-import fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Import Data</h5>
                                <p class="card-text">Import data from CSV or SQL files</p>
                                <a href="<?php echo Route::url('?page=db2tables&action=import_csv_page'); ?>" class="btn btn-warning">Import Data</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Default Content -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Select a table or view from the sidebar to explore its structure and data.
                </div>
            </div>
            
            <!-- Export Modal -->
            <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="exportModalLabel">Export Data</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="exportForm">
                                <div class="mb-3">
                                    <label class="form-label">Select Tables to Export</label>
                                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th width="50px">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="selectAllTables">
                                                        </div>
                                                    </th>
                                                    <th>Table Name</th>
                                                    <th>Type</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Tables and views should be passed from the router
                                                if (isset($tables_and_views) && is_array($tables_and_views)) :
                                                foreach ($tables_and_views as $item) :
                                                    $table_name = $item['name'];
                                                    $type = ucfirst($item['type']);
                                                  
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="tables[]" value="<?php echo htmlspecialchars($table_name); ?>">
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($table_name); ?></td>
                                                    <td><?php echo htmlspecialchars($type); ?></td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Export Format</label>
                                    <div class="d-flex">
                                        <div class="form-check me-4">
                                            <input class="form-check-input" type="radio" name="exportFormat" id="formatCSV" value="csv" checked>
                                            <label class="form-check-label" for="formatCSV">
                                                CSV Format
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="exportFormat" id="formatSQL" value="sql">
                                            <label class="form-check-label" for="formatSQL">
                                                SQL Format
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Options</label>
                                    <div class="form-check csv-option">
                                        <input class="form-check-input" type="checkbox" name="includeHeaders" id="includeHeaders" checked>
                                        <label class="form-check-label" for="includeHeaders">
                                            Include Headers (CSV only)
                                        </label>
                                    </div>
                                    <div class="form-check sql-option" style="display: none;">
                                        <input class="form-check-input" type="checkbox" name="includeStructure" id="includeStructure" checked>
                                        <label class="form-check-label" for="includeStructure">
                                            Include Structure (SQL only)
                                        </label>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success" id="exportBtn">Export</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Table Modal -->
            <div class="modal fade" id="createTableModal" tabindex="-1" aria-labelledby="createTableModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="createTableModalLabel">Create New Table</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="createTableForm">
                                <div class="mb-3">
                                    <label for="table_name" class="form-label">Table Name</label>
                                    <input type="text" class="form-control" id="table_name" name="table_name" required>
                                    <div class="form-text">Enter a name for the new table (lowercase, no spaces)</div>
                                </div>
                                <div class="mb-3">
                                    <label for="primary_key" class="form-label">Primary Key Field</label>
                                    <input type="text" class="form-control" id="primary_key" name="primary_key" value="id" required>
                                    <div class="form-text">Name of the primary key field (auto-increment)</div>
                                </div>
                                <div id="createTableError" class="alert alert-danger d-none"></div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="createTableBtn">Create Table</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

