<?php
!defined('MILK_DIR') && die();

$page = (string) ($page ?? 'projects');
$projects = is_array($projects ?? null) ? $projects : [];
?>
<div class="container-fluid py-3 projects-module-shell">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Projects</h5>
            <a class="btn btn-sm btn-primary" href="?page=<?php _p($page); ?>&action=create-project">Create New Project</a>
        </div>
        <div class="card-body">
            <?php if (empty($projects)): ?>
                <div class="alert alert-info mb-0">
                    No projects have been created yet. Create your first project.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 28%;">Project</th>
                                <th>Description</th>
                                <th style="width: 16%;">Created</th>
                                <th style="width: 14%;">Main Table Rows</th>
                                <th class="text-start" style="width: 210px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php _p((string) ($project['edit_url'] ?? '#')); ?>" class="text-decoration-none">
                                                <?php _p((string) ($project['project_name'] ?? '')); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?php _p((string) ($project['description'] ?? '')); ?></td>
                                    <td>
                                        <?php
                                        $createdAt = trim((string) ($project['created_at'] ?? ''));
                                        $createdByUsername = trim((string) ($project['created_by_username'] ?? ''));
                                        ?>
                                        <div><?php _p($createdAt !== '' ? $createdAt : '-'); ?></div>
                                        <div class="text-muted"><?php _p($createdByUsername !== '' ? $createdByUsername : '-'); ?></div>
                                    </td>
                                    <td><?php _p((string) (($project['main_table_row_count'] ?? null) !== null ? (int) $project['main_table_row_count'] : '-')); ?></td>
                                    <td class="text-start">
                                        <div class="d-inline-flex gap-1">
                                            <a class="btn btn-sm btn-primary" href="<?php _p((string) ($project['enter_url'] ?? '#')); ?>">
                                                Enter
                                            </a>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?php _p((string) ($project['edit_url'] ?? '#')); ?>">
                                                Edit
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
