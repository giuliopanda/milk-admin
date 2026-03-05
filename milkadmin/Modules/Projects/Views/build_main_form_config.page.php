<?php
!defined('MILK_DIR') && die();

$page = (string) ($page ?? 'projects');
$project = is_array($project ?? null) ? $project : null;
$config = is_array($config ?? null) ? $config : [];
$moduleName = trim((string) ($project['module_name'] ?? ''));
$editDisplay = (string) ($config['edit_display'] ?? 'page');
$softDelete = !empty($config['soft_delete']);
$supportsSoftDelete = !empty($config['supports_soft_delete']);
$allowDeleteRecord = !array_key_exists('allow_delete_record', $config) || !empty($config['allow_delete_record']);
$allowEdit = !array_key_exists('allow_edit', $config) || !empty($config['allow_edit']);
$showCreated = !empty($config['show_created']);
$showUpdated = !empty($config['show_updated']);
$defaultOrderEnabled = !empty($config['default_order_enabled']);
$defaultOrderField = trim((string) ($config['default_order_field'] ?? ''));
$defaultOrderDirection = strtolower(trim((string) ($config['default_order_direction'] ?? 'asc')));
if (!in_array($defaultOrderDirection, ['asc', 'desc'], true)) {
    $defaultOrderDirection = 'asc';
}
$defaultOrderFieldOptions = is_array($config['default_order_field_options'] ?? null)
    ? $config['default_order_field_options']
    : [];
$canEdit = !empty($config['can_edit']);
$error = trim((string) ($config['error'] ?? ''));

$sectionTitle = 'Main Table Config';

$backToProjectEditUrl = '?page=' . rawurlencode($page)
    . '&action=edit&module=' . rawurlencode($moduleName);

$saveUrl = '?page=' . rawurlencode($page)
    . '&action=save-main-form-config&module=' . rawurlencode($moduleName);

$projectEditContext = \Modules\Projects\ProjectEditContextService::buildBoxData($project, $sectionTitle, $page, [
    'hide_default_back' => true,
    'right_actions' => [
        [
            'label' => 'Back to Project Edit',
            'url' => $backToProjectEditUrl,
            'class' => 'btn btn-outline-secondary',
            'id' => '',
        ],
    ],
]);
?>
<div class="container-fluid py-4 projects-module-shell">
    <?php $project_edit_context = $projectEditContext; ?>
    <?php include __DIR__ . '/partials/project_edit_context_box.php'; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php _p($error); ?>
        </div>
    <?php elseif (!$canEdit): ?>
        <div class="alert alert-warning" role="alert">
            You cannot edit this main table configuration.
        </div>
    <?php else: ?>
        <form method="post" action="<?php _p($saveUrl); ?>" class="card projects-page-card">
            <div class="card-header">
                <strong>Main Table Configuration</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="project-main-form-config-allow-edit" class="form-label">Allow edit</label>
                        <select id="project-main-form-config-allow-edit" name="allow_edit" class="form-select">
                            <option value="1" <?php echo $allowEdit ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?php echo !$allowEdit ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6" id="project-main-form-config-edit-display-wrap"<?php echo !$allowEdit ? ' style="display:none;"' : ''; ?>>
                        <label for="project-main-form-config-edit-display" class="form-label">Type of edit</label>
                        <select id="project-main-form-config-edit-display" name="edit_display" class="form-select">
                            <option value="page" <?php echo $editDisplay === 'page' ? 'selected' : ''; ?>>New page (default)</option>
                            <option value="offcanvas" <?php echo $editDisplay === 'offcanvas' ? 'selected' : ''; ?>>Offcanvas</option>
                            <option value="modal" <?php echo $editDisplay === 'modal' ? 'selected' : ''; ?>>Modal</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <?php if ($supportsSoftDelete): ?>
                    <div class="col-12 col-md-4">
                        <label for="project-main-form-config-soft-delete" class="form-label">Soft delete</label>
                        <select id="project-main-form-config-soft-delete" name="soft_delete" class="form-select">
                            <option value="0" <?php echo !$softDelete ? 'selected' : ''; ?>>No</option>
                            <option value="1" <?php echo $softDelete ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="soft_delete" value="0">
                    <?php endif; ?>
                    <div class="col-12 col-md-4">
                        <label for="project-main-form-config-allow-delete-record" class="form-label">Allow delete record</label>
                        <select id="project-main-form-config-allow-delete-record" name="allow_delete_record" class="form-select">
                            <option value="1" <?php echo $allowDeleteRecord ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?php echo !$allowDeleteRecord ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-6">
                        <label for="project-main-form-config-show-created" class="form-label">Show created</label>
                        <select id="project-main-form-config-show-created" name="show_created" class="form-select">
                            <option value="1" <?php echo $showCreated ? 'selected' : ''; ?>>Show</option>
                            <option value="0" <?php echo !$showCreated ? 'selected' : ''; ?>>Hide</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="project-main-form-config-show-updated" class="form-label">Show updated</label>
                        <select id="project-main-form-config-show-updated" name="show_updated" class="form-select">
                            <option value="1" <?php echo $showUpdated ? 'selected' : ''; ?>>Show</option>
                            <option value="0" <?php echo !$showUpdated ? 'selected' : ''; ?>>Hide</option>
                        </select>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <input type="hidden" name="default_order_enabled" value="0">
                        <div class="form-check mt-2">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="project-main-form-config-default-order-enabled"
                                name="default_order_enabled"
                                value="1"
                                <?php echo $defaultOrderEnabled ? 'checked' : ''; ?>
                            >
                            <label class="form-check-label" for="project-main-form-config-default-order-enabled">
                                Manage default ordering
                            </label>
                        </div>
                    </div>
                    <div
                        id="project-main-form-config-default-order-field-wrap"
                        class="col-12 col-md-4"
                        style="<?php echo $defaultOrderEnabled ? '' : 'display:none;'; ?>"
                    >
                        <label for="project-main-form-config-default-order-field" class="form-label">Field name</label>
                        <select
                            id="project-main-form-config-default-order-field"
                            name="default_order_field"
                            class="form-select"
                            <?php echo $defaultOrderEnabled ? '' : 'disabled'; ?>
                        >
                            <option value="">Select field</option>
                            <?php foreach ($defaultOrderFieldOptions as $fieldValue => $fieldLabel): ?>
                                <?php
                                    $fieldValue = trim((string) $fieldValue);
                                    if ($fieldValue === '') {
                                        continue;
                                    }
                                    $fieldLabel = trim((string) $fieldLabel);
                                    if ($fieldLabel === '') {
                                        $fieldLabel = $fieldValue;
                                    }
                                ?>
                                <option value="<?php _p($fieldValue); ?>" <?php echo $defaultOrderField === $fieldValue ? 'selected' : ''; ?>>
                                    <?php _p($fieldLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div
                        id="project-main-form-config-default-order-direction-wrap"
                        class="col-12 col-md-4"
                        style="<?php echo $defaultOrderEnabled ? '' : 'display:none;'; ?>"
                    >
                        <label for="project-main-form-config-default-order-direction" class="form-label">Direction</label>
                        <select
                            id="project-main-form-config-default-order-direction"
                            name="default_order_direction"
                            class="form-select"
                            <?php echo $defaultOrderEnabled ? '' : 'disabled'; ?>
                        >
                            <option value="asc" <?php echo $defaultOrderDirection === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                            <option value="desc" <?php echo $defaultOrderDirection === 'desc' ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="<?php _p($backToProjectEditUrl); ?>" class="btn btn-outline-secondary">Back to Project Edit</a>
            </div>
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var allowEdit = document.getElementById('project-main-form-config-allow-edit');
            var editDisplayWrap = document.getElementById('project-main-form-config-edit-display-wrap');
            var defaultOrderEnabled = document.getElementById('project-main-form-config-default-order-enabled');
            var defaultOrderField = document.getElementById('project-main-form-config-default-order-field');
            var defaultOrderDirection = document.getElementById('project-main-form-config-default-order-direction');
            var defaultOrderFieldWrap = document.getElementById('project-main-form-config-default-order-field-wrap');
            var defaultOrderDirectionWrap = document.getElementById('project-main-form-config-default-order-direction-wrap');

            function refresh() {
                if (!allowEdit || !editDisplayWrap) return;
                var value = String(allowEdit.value || '').trim();
                editDisplayWrap.style.display = (value === '1') ? '' : 'none';
            }

            function refreshDefaultOrderFields() {
                if (!defaultOrderEnabled || !defaultOrderField || !defaultOrderDirection) return;
                var enabled = !!defaultOrderEnabled.checked;
                defaultOrderField.disabled = !enabled;
                defaultOrderDirection.disabled = !enabled;
                if (defaultOrderFieldWrap) {
                    defaultOrderFieldWrap.style.display = enabled ? '' : 'none';
                }
                if (defaultOrderDirectionWrap) {
                    defaultOrderDirectionWrap.style.display = enabled ? '' : 'none';
                }
            }

            allowEdit.addEventListener('change', refresh);
            if (defaultOrderEnabled) {
                defaultOrderEnabled.addEventListener('change', refreshDefaultOrderFields);
            }
            refresh();
            refreshDefaultOrderFields();
        });
        </script>
    <?php endif; ?>
</div>
