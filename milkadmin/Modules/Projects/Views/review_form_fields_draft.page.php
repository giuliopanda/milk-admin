<?php
!defined('MILK_DIR') && die();

$page = (string) ($page ?? 'projects');
$project = is_array($project ?? null) ? $project : null;
$review = is_array($review ?? null) ? $review : [];

$canReview = !empty($review['can_review']);
$error = trim((string) ($review['error'] ?? ''));
$formName = trim((string) ($review['form_name'] ?? ''));
$moduleName = trim((string) ($review['module_name'] ?? ($project['module_name'] ?? '')));
$sectionTitle = 'Review Form: JSON Changes';
if ($moduleName !== '') {
    $sectionTitle .= ' - ' . $moduleName;
}

$oldJsonPretty = (string) ($review['old_json_pretty'] ?? '');
$newJsonPretty = (string) ($review['new_json_pretty'] ?? '');
$fieldChanges = is_array($review['field_changes'] ?? null) ? $review['field_changes'] : [];
$fieldChangesSummary = is_array($review['field_changes_summary'] ?? null) ? $review['field_changes_summary'] : [];
$dbCheckMessage = trim((string) ($review['db_check_message'] ?? ''));
$backToEditUrl = trim((string) ($review['back_to_edit_url'] ?? ''));
$ref = trim((string) ($review['ref'] ?? ''));
$draftToken = trim((string) ($review['draft_token'] ?? ''));
$acceptPostUrl = '?page=' . rawurlencode($page) . '&action=accept-form-fields-draft';
$structurePreviewStyle = 'max-height: 220px; overflow: auto; font-size: 0.72rem; line-height: 1.2;';
$isNoChangedLine = static function (string $line): bool {
    return preg_match('/^\[?\s*no changed lines\.?\s*\]?$/i', trim($line)) === 1;
};

$titleActions = [];
$rightActions = [];
if ($backToEditUrl !== '') {
    $rightActions[] = [
        'label' => 'Back to editor without saving',
        'url' => $backToEditUrl,
        'class' => 'btn btn-outline-secondary',
        'id' => '',
    ];
}
$projectEditContext = \Modules\Projects\ProjectEditContextService::buildBoxData($project, $sectionTitle, $page, [
    'hide_default_back' => true,
    'title_actions' => $titleActions,
    'right_actions' => $rightActions,
]);
?>
<style>
#review-form-fields-draft-page .projects-no-changed-line {
    color: #000 !important;
}
</style>
<div id="review-form-fields-draft-page" class="container-fluid py-4 projects-module-shell">
    <?php $project_edit_context = $projectEditContext; ?>
    <?php include __DIR__ . '/partials/project_edit_context_box.php'; ?>

    <?php if (!$canReview || $error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php _p($error !== '' ? $error : 'Unable to load draft preview.'); ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Review the JSON changes. Nothing is saved until you click <strong>Save</strong>.
        </div>
        <?php if ($acceptPostUrl !== '' && $moduleName !== '' && $ref !== '' && $draftToken !== ''): ?>
            <form method="post" action="<?php _p($acceptPostUrl); ?>" class="mb-3 js-accept-draft-form">
                <input type="hidden" name="page" value="<?php _p($page); ?>">
                <input type="hidden" name="action" value="accept-form-fields-draft">
                <input type="hidden" name="module" value="<?php _p($moduleName); ?>">
                <input type="hidden" name="ref" value="<?php _p($ref); ?>">
                <input type="hidden" name="draft" value="<?php _p($draftToken); ?>">
                <button type="submit" class="btn btn-success">Save</button>
            </form>
        <?php endif; ?>

        <div class="card projects-page-card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Field Change Analysis</strong>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-secondary">Total: <?php _p((string) ((int) ($fieldChangesSummary['total'] ?? count($fieldChanges)))); ?></span>
                    <span class="badge text-bg-success">Unchanged: <?php _p((string) ((int) ($fieldChangesSummary['unchanged'] ?? 0))); ?></span>
                    <span class="badge text-bg-primary">Modified: <?php _p((string) ((int) ($fieldChangesSummary['modified'] ?? 0))); ?></span>
                    <span class="badge text-bg-info">New: <?php _p((string) ((int) ($fieldChangesSummary['added'] ?? 0))); ?></span>
                    <span class="badge text-bg-danger">Removed: <?php _p((string) ((int) ($fieldChangesSummary['removed'] ?? 0))); ?></span>
                    <span class="badge text-bg-warning">Warnings: <?php _p((string) ((int) ($fieldChangesSummary['warnings'] ?? 0))); ?></span>
                    <span class="badge text-bg-danger">Dangers: <?php _p((string) ((int) ($fieldChangesSummary['dangers'] ?? 0))); ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($fieldChanges)): ?>
                    <div class="text-muted">No field-level changes detected.</div>
                <?php else: ?>
                    <?php if ($dbCheckMessage !== ''): ?>
                        <div class="alert alert-warning py-2 mb-3">
                            <?php _p($dbCheckMessage); ?>
                        </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="min-width: 220px;">Field</th>
                                <th style="min-width: 320px;">Before</th>
                                <th style="min-width: 320px;">After</th>
                                <th style="min-width: 280px;">Risk</th>
                                <th style="min-width: 320px;">DB Data Check</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($fieldChanges as $row): ?>
                                <?php
                                $fieldName = trim((string) ($row['name'] ?? ''));
                                $status = trim((string) ($row['status'] ?? 'unchanged'));
                                $statusLabel = trim((string) ($row['status_label'] ?? 'Unchanged'));
                                $beforeLines = is_array($row['before_lines'] ?? null) ? $row['before_lines'] : [];
                                $afterLines = is_array($row['after_lines'] ?? null) ? $row['after_lines'] : [];
                                if (empty($beforeLines)) {
                                    $beforeLines = [trim((string) ($row['before'] ?? '[not present]'))];
                                }
                                if (empty($afterLines)) {
                                    $afterLines = [trim((string) ($row['after'] ?? '[deleted]'))];
                                }
                                $riskLevel = trim((string) ($row['risk_level'] ?? 'safe'));
                                $riskLabel = trim((string) ($row['risk_label'] ?? 'OK'));
                                $riskNote = trim((string) ($row['risk_note'] ?? ''));
                                $dbCheckLevel = trim((string) ($row['db_check_level'] ?? 'unknown'));
                                $dbCheckLabel = trim((string) ($row['db_check_label'] ?? 'Not verified'));
                                $dbCheckNote = trim((string) ($row['db_check_note'] ?? ''));
                                $dbCheckLines = is_array($row['db_check_lines'] ?? null) ? $row['db_check_lines'] : [];
                                $changedKeys = is_array($row['changed_keys'] ?? null) ? $row['changed_keys'] : [];

                                $statusClass = match ($status) {
                                    'modified' => 'text-bg-primary',
                                    'added' => 'text-bg-info',
                                    'removed' => 'text-bg-danger',
                                    default => 'text-bg-success',
                                };
                                $riskClass = match ($riskLevel) {
                                    'danger' => 'text-bg-danger',
                                    'warning' => 'text-bg-warning',
                                    default => 'text-bg-success',
                                };
                                $dbCheckClass = match ($dbCheckLevel) {
                                    'danger' => 'text-bg-danger',
                                    'warning' => 'text-bg-warning',
                                    'safe' => 'text-bg-success',
                                    default => 'text-bg-secondary',
                                };
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php _p($fieldName !== '' ? $fieldName : '[unknown]'); ?></div>
                                        <span class="badge <?php _p($statusClass); ?>"><?php _p($statusLabel); ?></span>
                                        <?php if (!empty($changedKeys)): ?>
                                            <div class="small text-muted mt-1">
                                                Keys changed: <?php _p(implode(', ', array_map('strval', $changedKeys))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <ul class="small mb-0 ps-3">
                                            <?php foreach ($beforeLines as $line): ?>
                                                <?php $lineText = (string) $line; ?>
                                                <li><code class="<?php _p($isNoChangedLine($lineText) ? 'projects-no-changed-line' : ''); ?>"><?php _p($lineText); ?></code></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td>
                                        <ul class="small mb-0 ps-3">
                                            <?php foreach ($afterLines as $line): ?>
                                                <?php $lineText = (string) $line; ?>
                                                <li><code class="<?php _p($isNoChangedLine($lineText) ? 'projects-no-changed-line' : ''); ?>"><?php _p($lineText); ?></code></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td>
                                        <span class="badge <?php _p($riskClass); ?>"><?php _p($riskLabel !== '' ? $riskLabel : 'OK'); ?></span>
                                        <?php if ($riskNote !== ''): ?>
                                            <div class="small mt-2"><?php _p($riskNote); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php _p($dbCheckClass); ?>"><?php _p($dbCheckLabel !== '' ? $dbCheckLabel : 'Not verified'); ?></span>
                                        <?php if ($dbCheckNote !== ''): ?>
                                            <div class="small mt-2 mb-1"><?php _p($dbCheckNote); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($dbCheckLines)): ?>
                                            <ul class="small mb-0 ps-3">
                                                <?php foreach ($dbCheckLines as $dbLine): ?>
                                                    <?php $dbLineText = (string) $dbLine; ?>
                                                    <li><code class="<?php _p($isNoChangedLine($dbLineText) ? 'projects-no-changed-line' : ''); ?>"><?php _p($dbLineText); ?></code></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="card projects-page-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Current Structure</strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-copy-structure" data-copy-target="current-structure-code">Copy</button>
                    </div>
                    <div class="card-body">
                        <pre class="projects-json-preview mb-0" style="<?php _p($structurePreviewStyle); ?>"><code id="current-structure-code"><?php _p($oldJsonPretty); ?></code></pre>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card projects-page-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>New Structure</strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-copy-structure" data-copy-target="new-structure-code">Copy</button>
                    </div>
                    <div class="card-body">
                        <pre class="projects-json-preview mb-0" style="<?php _p($structurePreviewStyle); ?>"><code id="new-structure-code"><?php _p($newJsonPretty); ?></code></pre>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var acceptForms = document.querySelectorAll('.js-accept-draft-form');
    acceptForms.forEach(function (form) {
        form.addEventListener('submit', function () {
            var submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach(function (button) {
                button.disabled = true;
            });
        });
    });

    var buttons = document.querySelectorAll('.js-copy-structure');
    if (!buttons.length) return;

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = String(button.getAttribute('data-copy-target') || '').trim();
            if (!targetId) return;

            var targetNode = document.getElementById(targetId);
            if (!targetNode) return;

            var text = String(targetNode.textContent || '');
            if (!text) return;

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(text).then(function () {
                    if (window.toasts && typeof window.toasts.show === 'function') {
                        window.toasts.show('Structure copied.', 'success');
                    }
                }).catch(function () {});
                return;
            }

            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy');
                if (window.toasts && typeof window.toasts.show === 'function') {
                    window.toasts.show('Structure copied.', 'success');
                }
            } catch (e) {}
            document.body.removeChild(area);
        });
    });
});
</script>
