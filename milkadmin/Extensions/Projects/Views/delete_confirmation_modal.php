<?php
/**
 * Delete confirmation modal body template.
 *
 * @var string $deleteUrl   Action URL for the delete form.
 * @var int    $id          Record ID to delete.
 * @var string $formTitle   Human-readable form title.
 * @var string $tokenName   CSRF token field name.
 * @var string $tokenValue  CSRF token value.
 * @var bool   $softDeleteEnabled Whether delete action is soft delete.
 */
!defined('MILK_DIR') && die();
?>
<form class="js-needs-validation" data-ajax-submit="true" novalidate method="post" action="<?php echo _r($deleteUrl); ?>">
    <input type="hidden" name="page-output" value="json">
    <input type="hidden" name="projects_delete_confirmation" value="1">
    <input type="hidden" name="projects_delete_id" value="<?php echo (int) $id; ?>">
    <input type="hidden" name="projects_delete_token_name" value="<?php echo _r($tokenName); ?>">
    <input type="hidden" name="projects_delete_token" value="<?php echo _r($tokenValue); ?>">
    <?php if (!empty($softDeleteEnabled)): ?>
        <p class="mb-3">Move <strong><?php echo _r($formTitle); ?></strong> record <strong>#<?php echo (int) $id; ?></strong> to trash?</p>
    <?php else: ?>
        <p class="mb-3">Delete <strong><?php echo _r($formTitle); ?></strong> record <strong>#<?php echo (int) $id; ?></strong> and all related child records?</p>
    <?php endif; ?>
    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <?php if (!empty($softDeleteEnabled)): ?>
            <button type="submit" class="btn btn-danger">Move to Trash</button>
        <?php else: ?>
            <button type="submit" class="btn btn-danger">Delete Entire Record</button>
        <?php endif; ?>
    </div>
</form>
