<?php
!defined('MILK_DIR') && die();

$page = (string) ($page ?? 'projects');
$project = is_array($project ?? null) ? $project : null;
$editAccess = is_array($edit_access ?? null) ? $edit_access : [
    'can_edit' => true,
    'message' => '',
    'inaccessible_paths' => [],
];
$moduleIsEditable = $project !== null && !empty($editAccess['can_edit']);
$inaccessiblePaths = is_array($editAccess['inaccessible_paths'] ?? null) ? $editAccess['inaccessible_paths'] : [];
$formsTree = is_array($forms_tree ?? null) ? $forms_tree : ['main' => [], 'children' => []];
$blockingError = trim((string) ($blocking_error ?? ''));
$formsTreeJson = json_encode($formsTree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($formsTreeJson)) {
    $formsTreeJson = '{"main":{},"children":[]}';
}
$moduleName = (string) ($project['module_name'] ?? '');
$rightActions = [];
if ($moduleName !== '') {
    $projectHomeUrl = '?page=' . rawurlencode($page)
        . '&action=edit'
        . '&module=' . rawurlencode($moduleName);
    $rightActions[] = [
        'label' => 'Project Home',
        'url' => $projectHomeUrl,
        'class' => 'btn btn-sm btn-outline-secondary',
        'id' => '',
    ];
}
$projectEditContext = \Modules\Projects\ProjectEditContextService::buildBoxData($project, 'Build Forms', $page, [
    'right_actions' => $rightActions,
]);
$saveUrl = '?page=' . rawurlencode($page) . '&action=save-forms-tree';
?>
<div id="project-build-forms-page" class="container-fluid py-4 projects-module-shell"
     data-module="<?php _p($moduleName); ?>"
     data-save-url="<?php _p($saveUrl); ?>">

    <div id="build-forms-loading-overlay" class="d-none">
        <div class="d-flex flex-column align-items-center justify-content-center h-100">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Saving...</span>
            </div>
            <span class="mt-2 text-muted">Saving...</span>
        </div>
    </div>

    <?php $project_edit_context = $projectEditContext; ?>
    <?php include __DIR__ . '/partials/project_edit_context_box.php'; ?>

    <?php if ($project === null): ?>
        <div class="alert alert-warning">
            Project not found or invalid <code>module</code> parameter.
        </div>
    <?php elseif (!$moduleIsEditable): ?>
        <div class="alert alert-info">
            <?php _p((string) ($editAccess['message'] ?? 'This module is not editable because of project folder permissions.')); ?>
            <?php if (!empty($inaccessiblePaths)): ?>
                <div class="mt-2">
                    <strong>Inaccessible project paths:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach (array_slice($inaccessiblePaths, 0, 12) as $issue): ?>
                            <?php
                            $path = trim((string) ($issue['path'] ?? ''));
                            $missingPermissions = trim((string) ($issue['missing_permissions'] ?? ''));
                            if ($path === '') {
                                continue;
                            }
                            ?>
                            <li>
                                <code><?php _p($path); ?></code>
                                <?php if ($missingPermissions !== ''): ?>
                                    <span class="text-muted">(missing <?php _p($missingPermissions); ?> permission)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($blockingError !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Critical integrity error.</strong><br>
            <?php _p($blockingError); ?>
        </div>
    <?php else: ?>
        <script id="project-forms-tree-data" type="application/json"><?php echo $formsTreeJson; ?></script>

        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0">Project Tables Configuration</h1>
                <p class="text-muted mb-0">
                    Main project table configuration.
                </p>
            </div>
        </div>

        <div class="card projects-page-card mb-4">
            <div class="card-header">
                <strong>Main Project Table</strong>
            </div>
            <div class="card-body">
                <div id="project-main-table-wrapper"></div>
            </div>
        </div>
    <?php endif; ?>
</div>
