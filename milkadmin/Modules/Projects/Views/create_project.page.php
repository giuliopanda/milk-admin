<?php
!defined('MILK_DIR') && die();

$page = (string) ($page ?? 'projects');
$createResult = is_array($create_result ?? null) ? $create_result : null;
$formData = is_array($form_data ?? null) ? $form_data : [];

$projectName = trim((string) ($formData['project_name'] ?? ''));
$projectDescription = trim((string) ($formData['project_description'] ?? ''));
$mainTableName = trim((string) ($formData['main_table_name'] ?? ''));
?>
<div class="container-fluid py-3 projects-module-shell">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Create New Project</h5>
            <a class="btn btn-sm btn-outline-secondary" href="?page=<?php _p($page); ?>">Back to projects list</a>
        </div>
        <div class="card-body">
            <?php if (is_array($createResult) && !empty($createResult['success'])): ?>
                <?php
                $moduleName = (string) ($createResult['module_name'] ?? '');
                $projectLabel = (string) ($createResult['project_name'] ?? $moduleName);
                $editUrl = '?page=' . rawurlencode($page) . '&action=edit&module=' . rawurlencode($moduleName);
                $mainFormEditUrl = '?page=' . rawurlencode($page)
                    . '&action=build-form-fields'
                    . '&module=' . rawurlencode($moduleName)
                    . '&ref=' . rawurlencode($moduleName . '.json');
                $enterUrl = '?page=' . rawurlencode((string) ($createResult['module_page'] ?? ''));
                ?>
                <div class="alert alert-success">
                    <strong><?php _p((string) ($createResult['msg'] ?? 'Project created.')); ?></strong>
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-primary" href="<?php _p($editUrl); ?>">Open Project</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?php _p($mainFormEditUrl); ?>">Edit Main Form</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?php _p($enterUrl); ?>">Enter Module</a>
                        <a class="btn btn-sm btn-outline-secondary" href="?page=<?php _p($page); ?>">Back to Projects</a>
                    </div>
                    <div class="mt-2 small text-muted">
                        Project: <strong><?php _p($projectLabel); ?></strong> |
                        Module: <code><?php _p($moduleName); ?></code>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (is_array($createResult) && empty($createResult['success']) && !empty($createResult['errors']) && is_array($createResult['errors'])): ?>
                <div class="alert alert-danger">
                    <?php foreach ($createResult['errors'] as $error): ?>
                        <div><?php _p((string) $error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?page=<?php _p($page); ?>&action=create-project-save" id="create-project-form">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="project-name">Project Name</label>
                        <input
                            id="project-name"
                            type="text"
                            name="project_name"
                            class="form-control"
                            required
                            value="<?php _p($projectName); ?>"
                        >
                        <div class="form-text">Used to generate module name, root form name, and default files.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="project-description">Description</label>
                        <textarea
                            id="project-description"
                            name="project_description"
                            class="form-control"
                            rows="3"
                            placeholder="Short manifest description"
                        ><?php _p($projectDescription); ?></textarea>
                    </div>
                </div>

                <hr class="my-4">

                <input type="hidden" name="main_table_source" value="new">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="main-table-name">Main Table Name</label>
                        <input
                            id="main-table-name"
                            type="text"
                            name="main_table_name"
                            class="form-control"
                            value="<?php _p($mainTableName); ?>"
                            placeholder="example_main_table"
                            required
                        >
                        <div class="form-text">Allowed: lowercase letters, numbers, underscore. Max 64 chars.</div>
                    </div>
                </div>

                <div class="mt-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Create Project</button>
                    <a class="btn btn-outline-secondary" href="?page=<?php _p($page); ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
