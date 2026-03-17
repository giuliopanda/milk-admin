<?php
!defined('MILK_DIR') && die();

$projectEditContext = is_array($project_edit_context ?? null) ? $project_edit_context : [];
if (empty($projectEditContext['show'])) {
    return;
}

$section = trim((string) ($projectEditContext['section'] ?? 'Project'));
$hasProject = !empty($projectEditContext['has_project']);
$projectName = trim((string) ($projectEditContext['project_name'] ?? ''));
$moduleName = trim((string) ($projectEditContext['module_name'] ?? ''));
$titleActions = is_array($projectEditContext['title_actions'] ?? null) ? $projectEditContext['title_actions'] : [];
$rightActions = is_array($projectEditContext['right_actions'] ?? null) ? $projectEditContext['right_actions'] : [];
?>
<div class="card mb-3 projects-edit-context-box">
    <div class="card-body py-3">
        <div class="projects-edit-context-header">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h3 class="projects-edit-context-title"><?php _p($section); ?></h3>
                <?php foreach ($titleActions as $titleAction): ?>
                    <?php
                        $titleActionId = trim((string) ($titleAction['id'] ?? ''));
                        $titleActionClass = trim((string) ($titleAction['class'] ?? 'btn btn-outline-secondary'));
                        if ($titleActionClass === '') {
                            $titleActionClass = 'btn btn-outline-secondary';
                        }
                    ?>
                    <a<?php if ($titleActionId !== ''): ?> id="<?php _p($titleActionId); ?>"<?php endif; ?>
                       class="<?php _p($titleActionClass); ?>"
                       href="<?php _p((string) ($titleAction['url'] ?? '#')); ?>">
                        <?php _p((string) ($titleAction['label'] ?? 'Action')); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <?php foreach ($rightActions as $rightAction): ?>
                    <?php
                        $rightActionId = trim((string) ($rightAction['id'] ?? ''));
                        $rightActionClass = trim((string) ($rightAction['class'] ?? 'btn btn-outline-secondary'));
                        if ($rightActionClass === '') {
                            $rightActionClass = 'btn btn-outline-secondary';
                        }
                    ?>
                    <a<?php if ($rightActionId !== ''): ?> id="<?php _p($rightActionId); ?>"<?php endif; ?>
                       class="<?php _p($rightActionClass); ?>"
                       href="<?php _p((string) ($rightAction['url'] ?? '#')); ?>">
                        <?php _p((string) ($rightAction['label'] ?? 'Action')); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($hasProject): ?>
            <div class="projects-edit-context-project mt-2">
                Editing project:
                <strong><?php _p($projectName); ?></strong>
                <?php if ($moduleName !== ''): ?>
                    <span class="ms-2 text-muted">(<code><?php _p($moduleName); ?></code>)</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="projects-edit-context-project mt-2 text-muted">
                Project not found or invalid module parameter.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php \App\MessagesHandler::displayMessages(); ?>
