<?php
!defined('MILK_DIR') && die();

$page = (string) ($page ?? 'projects');
$project = is_array($project ?? null) ? $project : null;
$editor = is_array($editor ?? null) ? $editor : [];

$moduleName = (string) ($project['module_name'] ?? '');
$resolvedRef = trim((string) ($editor['resolved_ref'] ?? ''));
$error = trim((string) ($editor['error'] ?? ''));
$canEdit = !empty($editor['can_edit']);
$existingTableLocked = !empty($editor['existing_table_locked']);
$fields = is_array($editor['fields'] ?? null) ? $editor['fields'] : [];
$containers = is_array($editor['containers'] ?? null) ? $editor['containers'] : [];

$formName = trim((string) ($editor['form_name'] ?? ''));
if ($formName === '' && $resolvedRef !== '') {
    $formName = trim((string) pathinfo($resolvedRef, PATHINFO_FILENAME));
}

$fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($fieldsJson)) {
    $fieldsJson = '[]';
}
$containersJson = json_encode($containers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($containersJson)) {
    $containersJson = '[]';
}
$sectionTitle = 'Build Form Fields';
if ($formName !== '') {
    $sectionTitle .= ' - ' . $formName;
}
$backToProjectEditUrl = '?page=' . rawurlencode($page)
    . '&action=edit&module=' . rawurlencode($moduleName);
$titleActions = [];
if ($canEdit) {
    $titleActions[] = [
        'label' => 'Save',
        'url' => '#',
        'class' => 'btn btn-success',
        'id' => 'project-form-fields-save-preview',
    ];
}
$projectEditContext = \Modules\Projects\ProjectEditContextService::buildBoxData($project, $sectionTitle, $page, [
    'hide_default_back' => true,
    'title_actions' => $titleActions,
    'right_actions' => [
        [
            'label' => 'Back to Project Edit',
            'url' => $backToProjectEditUrl,
            'class' => 'btn btn-outline-secondary',
            'id' => '',
        ],
    ],
]);

$saveFieldsDraftUrl = '?page=' . rawurlencode($page)
    . '&action=save-form-fields-draft'
    . '&module=' . rawurlencode($moduleName)
    . '&ref=' . rawurlencode($resolvedRef);
$relationModelsUrl = '?page=' . rawurlencode($page)
    . '&action=relation-models'
    . '&module=' . rawurlencode($moduleName);
$relationModelFieldsUrl = '?page=' . rawurlencode($page)
    . '&action=relation-model-fields'
    . '&module=' . rawurlencode($moduleName);
$modulePage = trim((string) ($project['module_page'] ?? ''));
?>
<div id="project-build-form-fields-page" class="container-fluid py-4 projects-module-shell"
     data-can-edit="<?php echo $canEdit ? '1' : '0'; ?>"
     data-existing-table-locked="<?php echo $existingTableLocked ? '1' : '0'; ?>"
     data-module="<?php _p($moduleName); ?>"
     data-ref="<?php _p($resolvedRef); ?>"
     data-save-fields-draft-url="<?php _p($saveFieldsDraftUrl); ?>"
     data-module-page="<?php _p($modulePage); ?>"
     data-relation-models-url="<?php _p($relationModelsUrl); ?>"
     data-relation-model-fields-url="<?php _p($relationModelFieldsUrl); ?>">
    <?php $project_edit_context = $projectEditContext; ?>
    <?php include __DIR__ . '/partials/project_edit_context_box.php'; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php _p($error); ?>
        </div>
    <?php elseif (!$canEdit): ?>
        <div class="alert alert-warning" role="alert">
            You cannot edit this table.
        </div>
    <?php else: ?>
        <?php if ($existingTableLocked): ?>
            <div class="alert alert-warning" role="alert">
                existingTable=true: you can edit existing fields, but the DB schema will not be updated.
            </div>
        <?php endif; ?>
        <script id="project-form-fields-data" type="application/json"><?php echo $fieldsJson; ?></script>
        <script id="project-form-containers-data" type="application/json"><?php echo $containersJson; ?></script>

        <div class="card projects-page-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Model Fields</strong>
                <small class="text-muted">Drag rows to reorder</small>
            </div>
            <div class="card-body">
                <div id="project-form-fields-list" class="list-group"></div>
                <div id="project-form-fields-empty" class="alert alert-info d-none mt-3 mb-0">
                    No fields found in the model RuleBuilder.
                </div>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <button id="project-form-add-field" type="button" class="btn btn-primary<?php echo $existingTableLocked ? ' d-none' : ''; ?>"<?php echo $existingTableLocked ? ' disabled aria-disabled="true"' : ''; ?>>+ Add field</button>
                    <button id="project-form-add-container" type="button" class="btn btn-outline-primary">+ Add container</button>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/partials/field_builder_modal.php'; ?>
        <?php include __DIR__ . '/partials/container_builder_modal.php'; ?>
    <?php endif; ?>
</div>
