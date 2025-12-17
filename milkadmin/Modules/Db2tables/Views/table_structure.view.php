<?php
namespace Modules\Db2tables\Views;

use App\Token;
use Modules\Db2tables\Db2tablesStructureServices;

// Ensure this file is not accessed directly
!defined('MILK_DIR') && die();

// Extract variables from viewData if available
if (isset($viewData)) {
    extract($viewData);
}


?>
<div class="mt-3">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><?php _pt('Table Structure'); ?></h5>
        </div>
        <div class="card-body">
            <form id="tableStructureForm" method="post">
                <input type="hidden" name="table_name" value="<?php echo htmlspecialchars($table_name); ?>">
                <input type="hidden" name="structure_token" value="<?php echo Token::get('editStructure'.$table_name); ?>">
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="structureTable">
                        <thead class="table-light">
                            <tr>
                                <th>Field Name</th>
                                <th>Type</th>
                                <th>Length/Values</th>
                                <th>Default</th>
                                <th>Null</th>
                                <th>Index</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="structureTableBody">
                            <?php foreach ($structure as $index => $field): ?>
                            <tr data-field-row="<?php echo $index; ?>">
                                <td>
                                    <input type="text" class="form-control" name="fields[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($field->Field); ?>" required>
                                    <input type="hidden" name="fields[<?php echo $index; ?>][original_name]" value="<?php echo htmlspecialchars($field->Field); ?>">
                                </td>
                                <td>
                                    <select class="form-select field-type" name="fields[<?php echo $index; ?>][type]" data-row="<?php echo $index; ?>">
                                        <?php 
                                        $types = Db2tablesStructureServices::getFieldTypes();
                                        $current_type = preg_replace('/\(.*\)/', '', $field->Type);
                                        foreach ($types as $type): 
                                            $selected = (strtoupper($current_type) === $type) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $type; ?>" <?php echo $selected; ?>><?php echo $type; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <?php 
                                    $length = '';
                                    if (preg_match('/\((.*?)\)/', $field->Type, $matches)) {
                                        $length = $matches[1];
                                    }
                                    ?>
                                    <input type="text" class="form-control field-length" name="fields[<?php echo $index; ?>][length]" value="<?php echo htmlspecialchars($length); ?>">
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="fields[<?php echo $index; ?>][default]" value="<?php echo htmlspecialchars($field->Default ?? ''); ?>">
                                </td>
                                <td>
                                    <select class="form-select" name="fields[<?php echo $index; ?>][null]">
                                        <option value="NOT NULL" <?php echo ($field->Null === 'NO') ? 'selected' : ''; ?>>NOT NULL</option>
                                        <option value="NULL" <?php echo ($field->Null === 'YES') ? 'selected' : ''; ?>>NULL</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select field-index" name="fields[<?php echo $index; ?>][index]" data-row="<?php echo $index; ?>">
                                        <option value="">None</option>
                                        <option value="PRIMARY" <?php echo ($field->Key === 'PRI' && strpos($field->Extra, 'auto_increment') === false) ? 'selected' : ''; ?>>PRIMARY</option>
                                        <option value="PRIMARY_AI" <?php echo ($field->Key === 'PRI' && strpos($field->Extra, 'auto_increment') !== false) ? 'selected' : ''; ?>>PRIMARY + AUTO_INCREMENT</option>
                                        <option value="UNIQUE" <?php echo ($field->Key === 'UNI') ? 'selected' : ''; ?>>UNIQUE</option>
                                        <option value="INDEX" <?php echo ($field->Key === 'MUL') ? 'selected' : ''; ?>>INDEX</option>
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger delete-field" data-field="<?php echo htmlspecialchars($field->Field); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <button type="button" id="addFieldBtn" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Field
                    </button>
                    <div>
                        <?php
                        $db2 = \Modules\Db2tables\Db2tablesServices::getDb();
                        $is_sqlite = ($db2->type === 'sqlite');
                        ?>
                        <?php if (!$is_sqlite): ?>
                        <button type="button" id="previewChangesBtn" class="btn btn-outline-primary me-2">
                            <i class="bi bi-eye"></i> Preview Changes
                        </button>
                        <button type="button" id="commitBtn" class="btn btn-success" style="display: none;">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                        <?php else: ?>
                        <!-- SQLite: Direct save without preview since ALTER TABLE has limitations -->
                        <button type="button" id="commitBtn" class="btn btn-success">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_sqlite): ?>
                <div class="mt-4" id="changesPreviewSection" style="display: none;">
                    <h5>Changes Preview</h5>
                    <div class="alert alert-info" id="noChangesAlert" style="display: none;">
                        No changes detected in the table structure.
                    </div>
                    <div id="changesPreviewContent">
                        <div class="mb-3" id="addedFieldsSection" style="display: none;">
                            <h6>Fields to Add</h6>
                            <ul class="list-group" id="addedFieldsList"></ul>
                        </div>
                        <div class="mb-3" id="modifiedFieldsSection" style="display: none;">
                            <h6>Fields to Modify</h6>
                            <ul class="list-group" id="modifiedFieldsList"></ul>
                        </div>
                        <div class="mb-3" id="renamedFieldsSection" style="display: none;">
                            <h6>Fields to Rename</h6>
                            <ul class="list-group" id="renamedFieldsList"></ul>
                        </div>
                        <div class="mb-3" id="droppedFieldsSection" style="display: none;">
                            <h6>Fields to Drop</h6>
                            <ul class="list-group" id="droppedFieldsList"></ul>
                        </div>
                        <div class="mb-3" id="indexChangesSection" style="display: none;">
                            <h6>Index Changes</h6>
                            <ul class="list-group" id="indexChangesList"></ul>
                        </div>
                        <div class="mb-3" id="sqlPreviewSection" style="display: none;">
                            <h6>SQL to Execute</h6>
                            <pre class="bg-light p-3 border rounded" id="sqlPreview"></pre>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
