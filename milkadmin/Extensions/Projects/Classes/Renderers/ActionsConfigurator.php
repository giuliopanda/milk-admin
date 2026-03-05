<?php
namespace Extensions\Projects\Classes\Renderers;

use Builders\TableBuilder;
use Extensions\Projects\Classes\Module\{ModelRecordHelper, UrlBuilder};

!defined('MILK_DIR') && die();

/**
 * Applies common table action and query configuration.
 *
 * This component is intentionally focused on builder-level actions and filters
 * so the output facade (table/list/calendar) can delegate shared behavior.
 */
class ActionsConfigurator
{
    protected const SOFT_DELETE_SCOPE_DELETED = 'deleted';

    /** @var callable|null Delete row callback wired from renderer. */
    protected $deleteCallback = null;
    /** @var callable|null Restore row callback wired from renderer. */
    protected $restoreCallback = null;
    /** @var callable|null Hard-delete row callback wired from renderer. */
    protected $hardDeleteCallback = null;

    public function setDeleteCallback(callable $callback): void
    {
        $this->deleteCallback = $callback;
    }

    public function setRestoreCallback(callable $callback): void
    {
        $this->restoreCallback = $callback;
    }

    public function setHardDeleteCallback(callable $callback): void
    {
        $this->hardDeleteCallback = $callback;
    }

    public function actionDeleteRowProxy($records, $request): bool
    {
        if ($this->deleteCallback !== null) {
            return call_user_func($this->deleteCallback, $records, $request);
        }
        return false;
    }

    public function actionRestoreRowProxy($records, $request): bool
    {
        if ($this->restoreCallback !== null) {
            return call_user_func($this->restoreCallback, $records, $request);
        }
        return false;
    }

    public function actionHardDeleteRowProxy($records, $request): bool
    {
        if ($this->hardDeleteCallback !== null) {
            return call_user_func($this->hardDeleteCallback, $records, $request);
        }
        return false;
    }

    public function applyRequestAction(TableBuilder $tb, ListContextParams $p): void
    {
        $requestAction = trim((string) ($p->options['request_action'] ?? ($_REQUEST['action'] ?? '')));
        if ($requestAction !== '') {
            $tb->setRequestAction($requestAction);
        }
    }

    public function applyCustomData(TableBuilder $tb, ListContextParams $p): void
    {
        $tb->customData('root_id', $p->requestedRootId);
        foreach ($p->urlFilterParams as $paramName => $paramValue) {
            $tb->customData((string) $paramName, $paramValue);
        }

        if ($p->allowSingleRecordInline) {
            $tb->customData('projects_allow_single_record_inline', '1');
        }
        if ($p->isEmbeddedViewTable) {
            $tb->customData('projects_embedded_view_table', '1');
            $tb->setSmallText();
        }
    }

    public function applyDefaultOrdering(TableBuilder $tb, ListContextParams $p): void
    {
        if (!$p->defaultOrderEnabled) {
            return;
        }

        $field = trim((string) $p->defaultOrderField);
        if (!$this->isSafeOrderField($field)) {
            return;
        }

        $direction = strtolower(trim((string) $p->defaultOrderDirection)) === 'desc' ? 'DESC' : 'ASC';
        $tb->orderBy($field, $direction);
    }

    protected function isSafeOrderField(string $field): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\.[A-Za-z_][A-Za-z0-9_]*)?$/', $field) === 1;
    }

    public function buildActionUrl(ListContextParams $p, string $action): string
    {
        if ($action === '') {
            return '';
        }
        $linkParams = array_merge(
            ['id' => '%' . $p->primaryKey . '%'],
            $p->fkChainParams,
            $p->urlFilterParams
        );
        if ($p->tableId !== '') {
            $linkParams[$p->reloadListIdParamKey] = $p->tableId;
        }
        return UrlBuilder::actionPreservePlaceholders($p->modulePage, $action, $linkParams);
    }

    public function applyRowActions(
        TableBuilder $tb,
        ListContextParams $p,
        string $editUrl,
        string $viewUrl
    ): void {
        if ($p->primaryKey === '' || $p->isEmbeddedViewTable) {
            return;
        }

        if (
            $p->softDeleteEnabled
            && $p->softDeleteScopeFilterEnabled
            && strtolower(trim((string) $p->softDeleteScope)) === self::SOFT_DELETE_SCOPE_DELETED
        ) {
            if (!$p->canManageDeleteRecords) {
                $tb->setActions([]);
                return;
            }

            $deletedScopeActions = [
                'restore' => [
                    'label' => 'Restore',
                    'validate' => false,
                    'class' => 'link-action',
                    'action' => [$this, 'actionRestoreRowProxy'],
                    'confirm' => 'Are you sure you want to restore this item?',
                ],
            ];
            if ($p->allowDeleteRecordEnabled) {
                $deletedScopeActions['hard_delete'] = [
                    'label' => 'Delete permanently',
                    'validate' => false,
                    'class' => 'link-action-danger',
                    'action' => [$this, 'actionHardDeleteRowProxy'],
                    'confirm' => 'Are you sure you want to permanently delete this item and all related child records?',
                ];
            }

            $tb->setActions($deletedScopeActions);
            return;
        }

        $hideEditDeleteOnDashboard = $p->isRoot && $p->viewAction !== '' && !$p->softDeleteEnabled;
        $deleteEnabledByConfig = $p->softDeleteEnabled || $p->allowDeleteRecordEnabled;
        $rowActions = [];

        if (!$hideEditDeleteOnDashboard && $p->canManageDeleteRecords && $deleteEnabledByConfig) {
            $rowActions['delete'] = [
                'label' => 'Delete',
                'validate' => false,
                'class' => 'link-action-danger',
                'action' => [$this, 'actionDeleteRowProxy'],
                'confirm' => $p->softDeleteEnabled
                    ? 'Are you sure you want to move this item to trash?'
                    : 'Are you sure you want to delete this item?',
            ];
        }

        if ($p->viewAction !== '') {
            $viewActionConfig = [
                'view' => [
                    'label' => 'View',
                    'link' => $viewUrl,
                ],
            ];
            if ($p->viewFetchMethod !== null) {
                $viewActionConfig['view']['fetch'] = $p->viewFetchMethod;
            }
            $rowActions = $viewActionConfig + $rowActions;
        }
       
        if ($p->editAction !== '' && !$hideEditDeleteOnDashboard && $p->allowEditEnabled) {
            $editActionConfig = [
                'edit' => [
                    'label' => 'Edit',
                    'link' => $editUrl,
                ],
            ];
            if ($p->editFetchMethod !== null) {
                $editActionConfig['edit']['fetch'] = $p->editFetchMethod;
            }
            $rowActions = $editActionConfig + $rowActions;
        }

        $tb->setActions($rowActions);
    }

    public function applySoftDeleteFilter(TableBuilder $tb, ListContextParams $p): void
    {
        if (!$p->softDeleteEnabled) {
            return;
        }

        $rules = $p->model->getRules();
        if (!is_array($rules) || !isset($rules['deleted_at'])) {
            return;
        }

        if ($p->softDeleteScopeFilterEnabled) {
            // Root lists with scope-filter are handled by ListSearchFiltersConfigurator callbacks.
            if ($p->isRoot) {
                return;
            }

            $deletedScope = strtolower(trim((string) $p->softDeleteScope)) === self::SOFT_DELETE_SCOPE_DELETED;
            $tb
                ->customData('projects_soft_delete', '1')
                ->where($this->buildDeletedAtScopeCondition($p, $deletedScope));
            return;
        }

        $condition = $this->buildDeletedAtScopeCondition($p, false);
        $tb
            ->customData('projects_soft_delete', '1')
            ->where($condition);
    }

    protected function buildDeletedAtScopeCondition(ListContextParams $p, bool $deletedScope): string
    {
        $suffix = $deletedScope ? ' IS NOT NULL' : ' IS NULL';
        $condition = 'deleted_at' . $suffix;

        if (!method_exists($p->model, 'getDb') || !method_exists($p->model, 'getTable')) {
            return $condition;
        }

        $db = $p->model->getDb();
        $tableName = trim((string) $p->model->getTable());
        if (!is_object($db) || !method_exists($db, 'qn') || $tableName === '') {
            return $condition;
        }

        return $db->qn($tableName) . '.' . $db->qn('deleted_at') . $suffix;
    }

    public function applyFkChainFilters(TableBuilder $tb, ListContextParams $p): void
    {
        if ($p->isRoot) {
            return;
        }
        foreach ($p->fkChainParams as $k => $v) {
            $tb->customData((string) $k, $v);
        }

        $fkField = $p->fkField;
        if (!ModelRecordHelper::isSafeSqlIdentifier($fkField)) {
            return;
        }

        $quotedFk = $fkField;
        if (method_exists($p->model, 'getDb')) {
            $db = $p->model->getDb();
            if (is_object($db) && method_exists($db, 'qn')) {
                $quotedFk = $db->qn($fkField);
            }
        }

        $tb
            ->customData('projects_fk_field', $fkField)
            ->customData('projects_parent_id', $p->parentId)
            ->where($quotedFk . ' = ?', [$p->parentId]);
    }
}
