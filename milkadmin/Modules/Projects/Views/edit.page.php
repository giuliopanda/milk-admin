<?php
!defined('MILK_DIR') && die();

$page = (string) ($page ?? 'projects');
$project = is_array($project ?? null) ? $project : null;
$manifest = is_array($project) && is_array($project['manifest_data'] ?? null) ? $project['manifest_data'] : [];
$editAccess = is_array($edit_access ?? null) ? $edit_access : [
    'can_edit' => true,
    'message' => '',
    'inaccessible_paths' => [],
];
$moduleIsEditable = $project !== null && !empty($editAccess['can_edit']);
$inaccessiblePaths = is_array($editAccess['inaccessible_paths'] ?? null) ? $editAccess['inaccessible_paths'] : [];
$moduleName = trim((string) ($project['module_name'] ?? ''));
$mainFormRef = trim((string) ($manifest['ref'] ?? ''));
if ($mainFormRef === '' && isset($manifest['forms'][0]['ref']) && is_string($manifest['forms'][0]['ref'])) {
    $mainFormRef = trim((string) $manifest['forms'][0]['ref']);
}
$mainFormRef = basename($mainFormRef);
if ($mainFormRef === '' && $moduleName !== '') {
    $mainFormRef = $moduleName . '.json';
}
$rightActions = [];
if ($project !== null) {
    $rightActions[] = [
        'label' => 'Enter Project',
        'url' => (string) ($project['enter_url'] ?? '#'),
        'class' => 'btn btn-sm btn-primary',
        'id' => '',
    ];
}
$rightActions[] = [
    'label' => 'Back to projects list',
    'url' => '?page=' . rawurlencode($page),
    'class' => 'btn btn-sm btn-primary',
    'id' => '',
];
$projectEditContext = \Modules\Projects\ProjectEditContextService::buildBoxData($project, 'Project Edit', $page, [
    'right_actions' => $rightActions,
]);
$editModuleConfigurationUrl = '?page=' . rawurlencode($page)
    . '&action=edit-module-configuration'
    . '&module=' . rawurlencode($moduleName);
$editFiltersSearchUrl = '?page=' . rawurlencode($page)
    . '&action=edit-filters-search'
    . '&module=' . rawurlencode($moduleName);
$editMainFormUrl = '?page=' . rawurlencode($page)
    . '&action=build-form-fields'
    . '&module=' . rawurlencode($moduleName);
if ($mainFormRef !== '') {
    $editMainFormUrl .= '&ref=' . rawurlencode($mainFormRef);
}
$mainFormConfigUrl = '?page=' . rawurlencode($page)
    . '&action=build-main-form-config'
    . '&module=' . rawurlencode($moduleName);
?>
<div class="container-fluid py-3 projects-module-shell">
    <?php $project_edit_context = $projectEditContext; ?>
    <?php include __DIR__ . '/partials/project_edit_context_box.php'; ?>

    <div class="card projects-page-card">
        <div class="card-body">
            <?php if ($project === null): ?>
                <div class="alert alert-warning mb-0">
                    Project not found or invalid <code>module</code> parameter.
                </div>
            <?php elseif (!$moduleIsEditable): ?>
                <div class="alert alert-info mb-0">
                    <?php _p((string) ($editAccess['message'] ?? 'This module is not editable because of project folder permissions.')); ?>
                    <div class="mt-2">Fix permissions and reload this page.</div>
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
            <?php else: ?>
               
                <div class="projects-edit-actions-boxes">
                    <div class="border rounded p-3">
                        <h5 class="h4 fw-bold mb-2">Module Configuration</h5>
                        <p class="text-muted mb-3">
                            Update core project settings such as title, description, and behavior options.
                            This helps control how the project is presented and how users enter records.
                        </p>
                        <a class="btn btn-primary" href="<?php _p($editModuleConfigurationUrl); ?>">
                            Edit Module Configuration
                        </a>
                    </div>

                    <hr class="my-3">

                    <div class="border rounded p-3">
                        <h5 class="h4 fw-bold mb-2">Build Forms</h5>
                        <p class="text-muted mb-3">
                            Manage the main form directly from here.
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-primary" href="<?php _p($editMainFormUrl); ?>">
                                Edit
                            </a>
                            <a class="btn btn-outline-secondary" href="<?php _p($mainFormConfigUrl); ?>">
                                Config
                            </a>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="border rounded p-3">
                        <h5 class="h4 fw-bold mb-2">Filters and Search</h5>
                        <p class="text-muted mb-3">
                            Configure filter and search behavior for the project list and related browsing views.
                            Use this page to define how users can quickly find and narrow records.
                        </p>
                        <a class="btn btn-primary" href="<?php _p($editFiltersSearchUrl); ?>">
                            Configure Filters and Search
                        </a>
                    </div>

                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
