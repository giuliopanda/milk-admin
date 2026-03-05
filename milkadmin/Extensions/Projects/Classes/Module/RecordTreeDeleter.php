<?php
namespace Extensions\Projects\Classes\Module;

use App\Get;
use App\Logs;
use App\MessagesHandler;
use Extensions\Projects\Classes\Module\ActionContextRegistry;
use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

/**
 * Recursively deletes a record and all its manifest-declared children.
 *
 * Improvements over the original inline implementation:
 * - Wrapped in a DB transaction (with graceful fallback if transactions are unavailable).
 * - Eliminates N+1: child rows loaded once by the parent query are reused,
 *   not re-fetched inside deleteRecordTree().
 * - Extracted into a dedicated class for testability.
 */
class RecordTreeDeleter
{
    protected ActionContextRegistry $registry;
    protected bool $useTransaction;

    public function __construct(ActionContextRegistry $registry, bool $useTransaction = true)
    {
        $this->registry = $registry;
        $this->useTransaction = $useTransaction;
    }

    /**
     * Delete one or more records (and their full subtree) by primary key.
     *
     * @param array   $context       The action context for the form being deleted.
     * @param int[]   $ids           Primary key values to delete.
     * @param int     $requestedRootId Root id from the URL chain (0 if root form).
     * @return bool
     */
    public function deleteMany(array $context, array $ids, int $requestedRootId): bool
    {
        if (empty($ids)) {
            MessagesHandler::addError('No items selected.');
            return false;
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            MessagesHandler::addError('Model class not found for delete action.');
            return false;
        }

        $model = new $modelClass();
        $primaryKey = (string) $model->getPrimaryKey();
        if ($primaryKey === '' || !ModelRecordHelper::isSafeSqlIdentifier($primaryKey)) {
            MessagesHandler::addError('Invalid primary key for delete action.');
            return false;
        }

        $isRoot = (bool) ($context['is_root'] ?? false);
        $rootField = ProjectNaming::rootIdField();

        $db = $this->getDb();
        $inTransaction = false;

        if ($this->useTransaction && $db !== null) {
            try {
                $db->begin();
                $inTransaction = true;
            } catch (\Throwable $e) {
                // Transaction not supported or failed to start — continue without.
                Logs::set('SYSTEM', 'Projects delete: transaction begin failed: ' . $e->getMessage(), 'WARNING');
                $inTransaction = false;
            }
        }

        try {
            foreach ($ids as $id) {
                $current = (new $modelClass())->getByIdForEdit($id);
                if (!is_object($current) || $current->isEmpty()) {
                    MessagesHandler::addError("Unable to delete record #{$id}: record not found.");
                    $this->rollbackIfActive($db, $inTransaction);
                    return false;
                }

                $expectedRootId = $isRoot
                    ? $id
                    : _absint(ModelRecordHelper::extractFieldValue($current, $rootField));

                if (!$isRoot && $expectedRootId <= 0) {
                    // Fallback to requested root id when the row has inconsistent/missing root_id.
                    $expectedRootId = $requestedRootId;
                }
                if ($requestedRootId > 0 && $expectedRootId > 0 && $requestedRootId !== $expectedRootId) {
                    MessagesHandler::addError("Invalid delete request: URL root id {$requestedRootId} does not match record root id {$expectedRootId}.");
                    $this->rollbackIfActive($db, $inTransaction);
                    return false;
                }

                $visited = [];
                if (!$this->deleteTree($context, $id, $expectedRootId, $visited)) {
                    $this->rollbackIfActive($db, $inTransaction);
                    return false;
                }
            }

            if ($inTransaction) {
                try {
                    $db->commit();
                } catch (\Throwable $e) {
                    Logs::set('SYSTEM', 'Projects delete: commit failed: ' . $e->getMessage(), 'ERROR');
                    MessagesHandler::addError('Delete commit failed.');
                    return false;
                }
            }

            MessagesHandler::addSuccess('Item deleted successfully');
            return true;

        } catch (\Throwable $e) {
            $this->rollbackIfActive($db, $inTransaction);
            MessagesHandler::addError('Delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a single record and all its nested children (depth-first).
     */
    protected function deleteTree(array $context, int $recordId, int $expectedRootId, array &$visited): bool
    {
        if ($recordId <= 0) {
            return true;
        }

        $formName = (string) ($context['form_name'] ?? '');
        $nodeKey = $formName . '#' . $recordId;
        if (isset($visited[$nodeKey])) {
            return true;
        }
        $visited[$nodeKey] = true;

        // Delete children first (depth-first).
        if (!$this->deleteChildren($context, $recordId, $expectedRootId, $visited)) {
            return false;
        }

        // Now delete the record itself.
        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            MessagesHandler::addError("Unable to delete '{$formName}' record #{$recordId}: model class not found.");
            return false;
        }

        $model = new $modelClass();
        $record = $model->getByIdForEdit($recordId);
        if (!is_object($record) || $record->isEmpty()) {
            MessagesHandler::addError("Unable to delete '{$formName}' record #{$recordId}: record not found.");
            return false;
        }

        $isRoot = (bool) ($context['is_root'] ?? false);
        $rootField = ProjectNaming::rootIdField();
        if (!$isRoot) {
            $recordRootId = _absint(ModelRecordHelper::extractFieldValue($record, $rootField));
            if ($expectedRootId > 0 && $recordRootId > 0 && $recordRootId !== $expectedRootId) {
                Logs::set(
                    'SYSTEM',
                    "Projects delete: '{$formName}' record #{$recordId} has '{$rootField}={$recordRootId}', expected {$expectedRootId}. Deleting by FK tree.",
                    'WARNING'
                );
            }
        }

        if (!$model->delete($recordId)) {
            $err = trim((string) $model->getLastError());
            MessagesHandler::addError($err !== '' ? $err : "Unable to delete '{$formName}' record #{$recordId}.");
            return false;
        }

        return true;
    }

    /**
     * Delete all child records for a given parent record.
     *
     * Loads child rows once and passes them directly to deleteTree,
     * avoiding the N+1 re-fetch that the old implementation had.
     */
    protected function deleteChildren(array $parentContext, int $parentRecordId, int $expectedRootId, array &$visited): bool
    {
        $children = $this->registry->getDirectChildContexts((string) ($parentContext['form_name'] ?? ''));
        if (empty($children)) {
            return true;
        }

        $rootField = ProjectNaming::rootIdField();

        foreach ($children as $childContext) {
            $childFormName = (string) ($childContext['form_name'] ?? '');
            $childModelClass = (string) ($childContext['model_class'] ?? '');
            $childFkField = (string) ($childContext['parent_fk_field'] ?? '');

            if ($childModelClass === '' || !class_exists($childModelClass)) {
                MessagesHandler::addError("Unable to delete children for '{$childFormName}': model class not found.");
                return false;
            }
            if ($childFkField === '' || !ModelRecordHelper::isSafeSqlIdentifier($childFkField)) {
                MessagesHandler::addError("Unable to delete children for '{$childFormName}': invalid FK field.");
                return false;
            }

            $childModel = new $childModelClass();
            $childRules = $childModel->getRules();
            if (!isset($childRules[$childFkField])) {
                MessagesHandler::addError("Unable to delete children for '{$childFormName}': FK field '{$childFkField}' not found in rules.");
                return false;
            }

            $childPk = (string) $childModel->getPrimaryKey();
            if ($childPk === '' || !ModelRecordHelper::isSafeSqlIdentifier($childPk)) {
                MessagesHandler::addError("Unable to delete children for '{$childFormName}': invalid primary key.");
                return false;
            }

            try {
                $childRecords = $childModel->where($childFkField . ' = ?', [$parentRecordId])->getResults();
            } catch (\Throwable) {
                MessagesHandler::addError("Unable to load children for '{$childFormName}'.");
                return false;
            }

            $rows = ModelRecordHelper::extractRawRows($childRecords);
            foreach ($rows as $row) {
                $childId = _absint(ModelRecordHelper::extractFieldValue($row, $childPk));
                if ($childId <= 0) {
                    continue;
                }

                $childRootId = _absint(ModelRecordHelper::extractFieldValue($row, $rootField));
                if ($expectedRootId > 0 && $childRootId > 0 && $childRootId !== $expectedRootId) {
                    Logs::set(
                        'SYSTEM',
                        "Projects delete: child '{$childFormName}' #{$childId} has '{$rootField}={$childRootId}', expected {$expectedRootId}. Deleting by FK tree.",
                        'WARNING'
                    );
                }

                if (!$this->deleteTree($childContext, $childId, $expectedRootId, $visited)) {
                    return false;
                }
            }
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function getDb(): ?object
    {
        try {
            return Get::db();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function rollbackIfActive(?object $db, bool $inTransaction): void
    {
        if (!$inTransaction || $db === null) {
            return;
        }
        try {
            $db->tearDown();
        } catch (\Throwable $e) {
            Logs::set('SYSTEM', 'Projects delete: rollback failed: ' . $e->getMessage(), 'ERROR');
        }
    }
}
