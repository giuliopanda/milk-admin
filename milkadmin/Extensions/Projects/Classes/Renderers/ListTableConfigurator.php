<?php
namespace Extensions\Projects\Classes\Renderers;

use Builders\TableBuilder;
use Extensions\Projects\Classes\Module\{
    ActionContextRegistry,
    ShowIfEvaluator
};

!defined('MILK_DIR') && die();

/**
 * Orchestrates TableBuilder configuration for Projects list pages.
 *
 * This facade keeps orchestration in one place and delegates specialized work
 * to dedicated components:
 * - ActionsConfigurator: request action, URL params, sorting, soft-delete/FK
 *   filters, and row actions.
 * - FieldsConfigurator: visible columns, formatters, links, withCount columns,
 *   and schema-driven ordering.
 *
 * The class intentionally contains no business rules; it only coordinates the
 * order in which configurators are executed so output is deterministic.
 */
class ListTableConfigurator
{
    protected ActionsConfigurator $actionsConfigurator;
    protected LinkConfigurator $linkConfigurator;
    protected ChildCountConfigurator $childCountConfigurator;
    protected FieldsConfigurator $fieldsConfigurator;

    public function __construct(
        ActionContextRegistry $registry,
        ShowIfEvaluator $showIfEvaluator
    ) {
        // Actions and links are stateless helpers.
        $this->actionsConfigurator = new ActionsConfigurator();
        $this->linkConfigurator = new LinkConfigurator();

        // Child-count rendering needs context lookup and showIf evaluation.
        $this->childCountConfigurator = new ChildCountConfigurator($registry, $showIfEvaluator);

        // Field configuration composes link + child-count behavior.
        $this->fieldsConfigurator = new FieldsConfigurator(
            $this->linkConfigurator,
            $this->childCountConfigurator
        );
    }

    /**
     * Build and fully configure the TableBuilder for the current list context.
     *
     * Configuration order matters:
     * 1. create builder and base error message
     * 2. precompute edit/view action URLs with placeholders
     * 3. apply request/custom data, ordering, filters, and row actions
     * 4. configure visible fields and column-level behavior
     *
     * @param ListContextParams $p Resolved list context from ListResponseBuilder.
     * @return TableBuilder Ready-to-render table builder.
     */
    public function configure(ListContextParams $p): TableBuilder
    {
        $tableBuilder = TableBuilder::create($p->model, $p->tableId)
            ->extensions(['Projects'])
            ->setErrorMessage('Unable to load table data.');

        $editUrlWithPlaceholders = $this->actionsConfigurator->buildActionUrl($p, $p->editAction);
        $viewUrlWithPlaceholders = $this->actionsConfigurator->buildActionUrl($p, $p->viewAction);

        $this->actionsConfigurator->applyRequestAction($tableBuilder, $p);
        $this->actionsConfigurator->applyCustomData($tableBuilder, $p);
        $this->actionsConfigurator->applyDefaultOrdering($tableBuilder, $p);
        $this->actionsConfigurator->applySoftDeleteFilter($tableBuilder, $p);
        $this->actionsConfigurator->applyRowActions(
            $tableBuilder,
            $p,
            $editUrlWithPlaceholders,
            $viewUrlWithPlaceholders
        );
        $this->actionsConfigurator->applyFkChainFilters($tableBuilder, $p);

        $this->fieldsConfigurator->configure(
            $tableBuilder,
            $p,
            $editUrlWithPlaceholders,
            $viewUrlWithPlaceholders
        );

        return $tableBuilder;
    }

    /**
     * Register delete callback used by row actions.
     *
     * Signature expected by the action system: function($records, $request): bool
     */
    public function setDeleteCallback(callable $callback): void
    {
        $this->actionsConfigurator->setDeleteCallback($callback);
    }

    /**
     * Register restore callback used when soft-delete scope is "deleted".
     *
     * Signature expected by the action system: function($records, $request): bool
     */
    public function setRestoreCallback(callable $callback): void
    {
        $this->actionsConfigurator->setRestoreCallback($callback);
    }

    /**
     * Register permanent-delete callback used when soft-delete scope is "deleted".
     *
     * Signature expected by the action system: function($records, $request): bool
     */
    public function setHardDeleteCallback(callable $callback): void
    {
        $this->actionsConfigurator->setHardDeleteCallback($callback);
    }

    /**
     * Backward-compatible proxy for legacy action references.
     *
     * Delegates to ActionsConfigurator without adding behavior.
     */
    public function actionDeleteRowProxy($records, $request): bool
    {
        return $this->actionsConfigurator->actionDeleteRowProxy($records, $request);
    }

    /**
     * Backward-compatible proxy for legacy restore action references.
     *
     * Delegates to ActionsConfigurator without adding behavior.
     */
    public function actionRestoreRowProxy($records, $request): bool
    {
        return $this->actionsConfigurator->actionRestoreRowProxy($records, $request);
    }

    /**
     * Backward-compatible proxy for legacy hard-delete action references.
     *
     * Delegates to ActionsConfigurator without adding behavior.
     */
    public function actionHardDeleteRowProxy($records, $request): bool
    {
        return $this->actionsConfigurator->actionHardDeleteRowProxy($records, $request);
    }
}
