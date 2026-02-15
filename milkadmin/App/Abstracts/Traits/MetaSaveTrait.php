<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * MetaSaveTrait - Integrates meta saving into the model's save workflow
 *
 * This trait should be used alongside CrudOperationsTrait to automatically
 * save hasMeta fields when the main record is saved.
 */
trait MetaSaveTrait
{
    /**
     * Hook called after successful insert/update
     * Override this in your model if you need custom post-save logic
     *
     * CrudOperationsTrait passes:
     * - $after_save_data: array of saved records data
     * - $save_results: array of operations with ['id', 'action', 'result', ...]
     *
     * @param mixed $after_save_data Saved records data
     * @param mixed $save_results Save operations metadata
     * @return void
     */
    protected function afterSave(mixed $after_save_data, mixed $save_results): void
    {
        if (!method_exists($this, 'saveMeta')) {
            return;
        }

        $successful_ids = [];
        if (is_array($save_results)) {
            foreach ($save_results as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $action = $row['action'] ?? null;
                $ok = (bool)($row['result'] ?? false);
                $id = $row['id'] ?? null;

                if ($ok && in_array($action, ['insert', 'edit'], true) && $id !== null) {
                    $successful_ids[] = $id;
                }
            }
        }

        if (empty($successful_ids)) {
            return;
        }

        // If we can map dirty meta by index, preserve record-level mapping.
        if (method_exists($this, 'getAllDirtyMeta')) {
            $dirty_map = $this->getAllDirtyMeta();
            if (!empty($dirty_map)) {
                $dirty_indices = array_keys($dirty_map);
                sort($dirty_indices, SORT_NUMERIC);

                foreach ($dirty_indices as $offset => $dirty_index) {
                    $entity_id = $successful_ids[$offset] ?? null;
                    if ($entity_id === null) {
                        continue;
                    }
                    $this->saveMeta($entity_id, (int)$dirty_index);
                }
                return;
            }
        }

        // Backward fallback: apply current dirty context to each saved id.
        foreach ($successful_ids as $entity_id) {
            $this->saveMeta($entity_id);
        }
    }

    /**
     * Hook called before delete
     * Override this in your model if you need custom pre-delete logic
     *
     * @param mixed $entity_id The entity's ID to be deleted
     * @return bool Return false to cancel deletion
     */
    protected function beforeDelete(mixed $entity_id): bool
    {
        return true;
    }

    /**
     * Hook called after successful delete
     * Override this in your model if you need custom post-delete logic
     *
     * @param mixed $entity_ids Deleted entity ID or array of IDs
     * @return void
     */
    protected function afterDelete(mixed $entity_ids): void
    {
        if (!method_exists($this, 'deleteMeta')) {
            return;
        }

        $ids = is_array($entity_ids) ? $entity_ids : [$entity_ids];
        foreach ($ids as $entity_id) {
            if ($entity_id === null || $entity_id === '') {
                continue;
            }
            $this->deleteMeta($entity_id);
        }
    }
}
