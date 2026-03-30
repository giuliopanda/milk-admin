<?php
namespace Extensions\Projects\Classes\Renderers;

use App\Route;
use Builders\TableBuilder;
use Extensions\Projects\Classes\Module\{
    ActionContextRegistry,
    DisplayModeHelper,
    ModelRecordHelper,
    ShowIfEvaluator,
    UrlBuilder
};

!defined('MILK_DIR') && die();

/**
 * Builds and renders child-count columns for table output.
 */
class ChildCountConfigurator
{
    protected ActionContextRegistry $registry;
    protected ShowIfEvaluator $showIfEvaluator;

    public function __construct(ActionContextRegistry $registry, ShowIfEvaluator $showIfEvaluator)
    {
        $this->registry = $registry;
        $this->showIfEvaluator = $showIfEvaluator;
    }

    /**
     * Configure a child count column in the TableBuilder.
     *
     * @param array $meta Child form metadata.
     */
    public function buildChildCountColumn(
        TableBuilder $tb,
        ListContextParams $p,
        string $field,
        array $meta
    ): void {
        $modulePage = $p->modulePage;
        $primaryKey = $p->primaryKey;
        $currentChainParams = $p->fkChainParams;
        $tableId = $p->tableId;

        $childListAction = (string) ($meta['list_action'] ?? '');
        $childEditAction = (string) ($meta['edit_action'] ?? '');
        $childMaxRecords = (string) ($meta['max_records'] ?? 'n');
        $childHasChildren = (bool) ($meta['has_children'] ?? false);
        $childShowIf = trim((string) ($meta['show_if'] ?? ''));
        $childShowIfMessage = trim((string) ($meta['show_if_message'] ?? ''));
        $childFkField = (string) ($meta['parent_fk_field'] ?? '');
        $childListDisplay = (string) ($meta['list_display'] ?? 'page');
        $childEditDisplay = (string) ($meta['edit_display'] ?? 'page');
        $childAllowView = !array_key_exists('allow_view', $meta) || (bool) $meta['allow_view'];
        $showIfEvaluator = $this->showIfEvaluator;
        $registry = $this->registry;

        $tb
            ->field((string) $field)
            ->type('html')
            ->fn(function ($result) use ($modulePage, $primaryKey, $field, $childFkField, $childListAction, $childEditAction, $childMaxRecords, $childHasChildren, $childShowIf, $childShowIfMessage, $childListDisplay, $childEditDisplay, $childAllowView, $showIfEvaluator, $registry, $currentChainParams, $tableId) {
                $row = $result->getRawData('array', false);
                $parentId = _absint($row[$primaryKey] ?? 0);
                $count = (int) ($row[$field] ?? 0);

                if ($parentId <= 0 || $childFkField === '') {
                    return (string) $count;
                }

                $baseParams = $currentChainParams;
                $baseParams[$childFkField] = $parentId;

                if ($childShowIf !== '') {
                    $showIfError = null;
                    $showIfAllowed = $showIfEvaluator->evaluate($childShowIf, $row, $showIfError);
                    if ($showIfError !== null) {
                        return '<span class="text-warning" title="Invalid showIf"><i class="bi bi-exclamation-triangle"></i></span>';
                    }
                    if (!$showIfAllowed) {
                        $blockedMessage = $childShowIfMessage !== '' ? $childShowIfMessage : 'Cannot create this form in current context.';
                        return '<span class="text-muted" title="' . _r($blockedMessage) . '"><i class="bi bi-slash-circle"></i></span>';
                    }
                }

                if ($childMaxRecords === '1') {
                    return $this->renderSingleRecordChildCell(
                        $modulePage, $primaryKey, $childFkField, $childListAction, $childEditAction,
                        $childHasChildren, $childListDisplay, $childEditDisplay, $childAllowView,
                        $registry, $baseParams, $tableId, $parentId, $count
                    );
                }

                $finiteChildMax = UrlBuilder::getFiniteMaxRecords($childMaxRecords);
                if ($finiteChildMax > 1 && $count >= $finiteChildMax) {
                    if (!$childAllowView) {
                        return '<span class="badge text-bg-dark">' . (int) $count . '/' . (int) $finiteChildMax . '</span>';
                    }
                    $href = UrlBuilder::action($modulePage, $childListAction, $baseParams);
                    $fetch = DisplayModeHelper::buildFetchAttribute($childListDisplay);
                    return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="Limit reached">'
                        . '<span class="badge text-bg-dark">' . (int) $count . '/' . (int) $finiteChildMax . '</span></a>';
                }

                return $this->renderMultiRecordChildCell(
                    $modulePage, $childListAction, $childEditAction,
                    $childListDisplay, $childEditDisplay, $childAllowView,
                    $baseParams, $tableId, $count
                );
            });
    }

    protected function renderSingleRecordChildCell(
        string $modulePage,
        string $primaryKey,
        string $childFkField,
        string $childListAction,
        string $childEditAction,
        bool $childHasChildren,
        string $childListDisplay,
        string $childEditDisplay,
        bool $childAllowView,
        ActionContextRegistry $registry,
        array $baseParams,
        string $tableId,
        int $parentId,
        int $count
    ): string {
        $targetAction = $childHasChildren ? $childListAction : $childEditAction;
        $targetDisplay = $childHasChildren ? $childListDisplay : $childEditDisplay;
        if ($targetAction === '') {
            $targetAction = $childEditAction !== '' ? $childEditAction : $childListAction;
            $targetDisplay = ($targetAction === $childEditAction) ? $childEditDisplay : $childListDisplay;
        }
        if ($targetAction === '') {
            return (string) $count;
        }
        if ($targetAction === $childListAction && !$childAllowView) {
            return '<span class="badge text-bg-secondary">' . (int) $count . '</span>';
        }

        $targetParams = $baseParams;
        if ($targetAction === $childEditAction) {
            if ($count > 0) {
                $existingId = $this->resolveChildExistingId($registry, $childListAction, $childFkField, $parentId);
                if ($existingId > 0) {
                    $targetParams['id'] = $existingId;
                }
            }
            if ($tableId !== '') {
                $targetParams[UrlBuilder::reloadListIdParamKey()] = $tableId;
            }
        }
        $href = UrlBuilder::action($modulePage, $targetAction, $targetParams);
        $fetch = DisplayModeHelper::buildFetchAttribute($targetDisplay);
        if ($count > 0) {
            return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="Open">'
                . '<i class="bi bi-check-circle-fill text-success"></i></a>';
        }
        $createTitle = $childHasChildren ? 'Open list' : 'Create';
        return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="' . $createTitle . '">'
            . '<i class="bi bi-plus-circle text-primary"></i></a>';
    }

    protected function renderMultiRecordChildCell(
        string $modulePage,
        string $childListAction,
        string $childEditAction,
        string $childListDisplay,
        string $childEditDisplay,
        bool $childAllowView,
        array $baseParams,
        string $tableId,
        int $count
    ): string {
        if ($count <= 0) {
            if ($childAllowView && $childListAction !== '' && $childListDisplay !== 'page') {
                $href = UrlBuilder::action($modulePage, $childListAction, $baseParams);
                $fetch = DisplayModeHelper::buildFetchAttribute($childListDisplay);
                return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="Open list">'
                    . '<span class="badge text-bg-secondary">0</span></a>';
            }
            if ($childEditAction === '') {
                return '<span class="badge text-bg-secondary">0</span>';
            }

            $targetParams = $baseParams;
            if ($tableId !== '') {
                $targetParams[UrlBuilder::reloadListIdParamKey()] = $tableId;
            }
            $href = UrlBuilder::action($modulePage, $childEditAction, $targetParams);
            $fetch = DisplayModeHelper::buildFetchAttribute($childEditDisplay);
            return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="Add">'
                . '<i class="bi bi-plus-circle text-primary"></i></a>';
        }

        if (!$childAllowView) {
            return '<span class="badge text-bg-secondary">' . (int) $count . '</span>';
        }
        $href = UrlBuilder::action($modulePage, $childListAction, $baseParams);
        $fetch = DisplayModeHelper::buildFetchAttribute($childListDisplay);
        return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="Open list">'
            . '<span class="badge text-bg-secondary">' . (int) $count . '</span></a>';
    }

    protected function resolveChildExistingId(
        ActionContextRegistry $registry,
        string $childListAction,
        string $childFkField,
        int $parentId
    ): int {
        if ($childListAction === '' || $childFkField === '' || $parentId <= 0) {
            return 0;
        }

        $childContext = $registry->get($childListAction);
        if (!is_array($childContext)) {
            return 0;
        }

        $childModelClass = (string) ($childContext['model_class'] ?? '');
        if ($childModelClass === '' || !class_exists($childModelClass)) {
            return 0;
        }

        try {
            $childModel = new $childModelClass();
            return ModelRecordHelper::findFirstIdByFk($childModel, $childFkField, $parentId);
        } catch (\Throwable) {
            return 0;
        }
    }
}
