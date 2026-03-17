<?php
namespace Extensions\Projects\Classes\Renderers;

use App\Abstracts\AbstractModule;
use App\Response;
use App\Route;
use Builders\SearchBuilder;
use Builders\TableBuilder;
use Extensions\Projects\Classes\Module\{
    ActionContextRegistry,
    BreadcrumbManager,
    DisplayModeHelper,
    FkChainResolver,
    SelectedMenuSidebarHelper,
    ShowIfEvaluator,
    UrlBuilder
};

!defined('MILK_DIR') && die();

/**
 * Renders the auto-generated list page for a manifest form.
 *
 * Acts as the main orchestrator: resolves the action context, delegates
 * response building to ListResponseBuilder and presentation/delete
 * concerns to ListPresenterHelper.
 *
 * Public methods in this renderer are active integration points used by
 * the Projects rendering pipeline and table actions.
 */
class AutoListRenderer
{
    protected AbstractModule $module;
    protected ActionContextRegistry $registry;
    protected FkChainResolver $fkResolver;
    protected BreadcrumbManager $breadcrumbManager;
    protected ShowIfEvaluator $showIfEvaluator;

    /** @var ListResponseBuilder Builds the list response payload. */
    protected ListResponseBuilder $responseBuilder;

    /** @var ListPresenterHelper Handles title rendering, delete, and normalization. */
    protected ListPresenterHelper $presenterHelper;

    public function __construct(
        AbstractModule $module,
        ActionContextRegistry $registry,
        FkChainResolver $fkResolver,
        BreadcrumbManager $breadcrumbManager,
        ShowIfEvaluator $showIfEvaluator
    ) {
        $this->module = $module;
        $this->registry = $registry;
        $this->fkResolver = $fkResolver;
        $this->breadcrumbManager = $breadcrumbManager;
        $this->showIfEvaluator = $showIfEvaluator;

        // Initialize collaborators.
        $this->responseBuilder = new ListResponseBuilder(
            $module,
            $registry,
            $fkResolver,
            $showIfEvaluator
        );
        // Wire this instance's actionDeleteRow as the delete callback so
        // TableBuilder action closures route back here for full compatibility.
        $this->responseBuilder->setDeleteCallback([$this, 'actionDeleteRow']);
        $this->responseBuilder->setRestoreCallback([$this, 'actionRestoreRow']);
        $this->responseBuilder->setHardDeleteCallback([$this, 'actionHardDeleteRow']);

        $this->presenterHelper = new ListPresenterHelper();
    }

    /**
     * Render the list page: resolve context, build response, and output.
     *
     * Handles JSON/AJAX responses, offcanvas/modal display modes,
     * breadcrumb application, and full-page rendering.
     *
     * @return void
     */
    public function render(): void
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null) {
            $this->renderError('No form context available for this action.');
            return;
        }
        if (!empty($context['error'])) {
            $this->renderError((string) $context['error']);
            return;
        }

        $options = [];
        $requestedTableId = UrlBuilder::normalizeListId((string) ($_REQUEST['table_id'] ?? ''));
        if ($requestedTableId !== '') {
            $options['table_id'] = $requestedTableId;
        }

        $result = $this->buildListResponse($context, $options);
        if (!empty($result['error'])) {
            $this->renderError((string) $result['error']);
            return;
        }

        $redirect = (string) ($result['redirect'] ?? '');
        if ($redirect !== '') {
            Route::redirect($redirect);
        }

        $response = is_array($result['response'] ?? null) ? $result['response'] : [];
        $tableId = (string) ($result['table_id'] ?? '');
        $listDisplay = (string) ($result['list_display'] ?? 'page');
        $isTableRequest = trim((string) ($_REQUEST['table_id'] ?? '')) !== '';

        // Apply breadcrumbs for full page loads (not AJAX table refreshes).
        if (!$isTableRequest && !Response::isJson()) {
            $this->breadcrumbManager->apply($context, $this->module->getPage(), 'list');
        }

        // For non-page display modes (offcanvas, modal), respond with title+body.
        if ($listDisplay !== 'page' && !$isTableRequest && Response::isJson()) {
            [$fetchTitle, $fetchBody] = $this->presenterHelper->buildFetchTitleAndBody($response, $tableId);
            DisplayModeHelper::respond($listDisplay, $fetchTitle, $fetchBody);
            return;
        }

        if (!$isTableRequest && !Response::isJson()) {
            SelectedMenuSidebarHelper::applyToResponse($response, $this->module);
        }

        Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
    }

    /**
     * Build the full list response payload without sending output.
     *
     * Delegates to ListResponseBuilder::build(). Exposed as a public entrypoint
     * for callers that need the list payload without rendering output.
     *
     * @param array $context  Resolved action context from ActionContextRegistry.
     * @param array $options  Optional overrides (table_id, chain_params, parent_id, etc.).
     * @return array{
     *   error: string,
     *   redirect: string,
     *   response: array,
     *   table_id: string,
     *   list_display: string
     * }
     */
    public function buildListResponse(array $context, array $options = []): array
    {
        return $this->responseBuilder->build($context, $options);
    }

    /**
     * Build and return only the configured TableBuilder for current request action.
     *
     * Returns null when context is invalid, would redirect, or lacks required params.
     */
    public function buildTableBuilder(array $options = []): ?TableBuilder
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null || !empty($context['error'])) {
            return null;
        }

        $requestedTableId = UrlBuilder::normalizeListId((string) ($_REQUEST['table_id'] ?? ''));
        if ($requestedTableId !== '' && !isset($options['table_id'])) {
            $options['table_id'] = $requestedTableId;
        }

        return $this->responseBuilder->buildTableBuilder($context, $options);
    }

    /**
     * Build and return only the configured SearchBuilder for current request action.
     *
     * Returns null when context is invalid, would redirect, or search filters are not available.
     */
    public function buildSearchBuilder(array $options = []): ?SearchBuilder
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null || !empty($context['error'])) {
            return null;
        }

        $requestedTableId = UrlBuilder::normalizeListId((string) ($_REQUEST['table_id'] ?? ''));
        if ($requestedTableId !== '' && !isset($options['table_id'])) {
            $options['table_id'] = $requestedTableId;
        }

        return $this->responseBuilder->buildSearchBuilder($context, $options);
    }

    /**
     * Handle the delete row action triggered by TableBuilder.
     *
     * Delegates to ListPresenterHelper::deleteRows(). This is the public delete
     * callback referenced by TableBuilder action configuration.
     *
     * @param mixed $records  Records payload from TableBuilder.
     * @param mixed $request  Request data from TableBuilder.
     * @return bool  True if deletion succeeded.
     */
    public function actionDeleteRow($records, $request): bool
    {
        return $this->presenterHelper->deleteRows(
            $records,
            $request,
            $this->registry,
            $this->fkResolver
        );
    }

    /**
     * Handle restore action for soft-deleted rows.
     *
     * @param mixed $records
     * @param mixed $request
     */
    public function actionRestoreRow($records, $request): bool
    {
        return $this->presenterHelper->restoreRows(
            $records,
            $request,
            $this->registry
        );
    }

    /**
     * Handle hard-delete action for already soft-deleted rows.
     *
     * @param mixed $records
     * @param mixed $request
     */
    public function actionHardDeleteRow($records, $request): bool
    {
        return $this->presenterHelper->hardDeleteRows(
            $records,
            $request,
            $this->registry,
            $this->fkResolver
        );
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Render a full error page with the Projects extension error template.
     *
     * @param string $message  Error message to display.
     * @return void
     */
    protected function renderError(string $message): void
    {
        $html = '<div class="container py-4">'
            . '<h1>Projects Extension Error</h1>'
            . '<p class="text-danger">' . _r($message) . '</p>'
            . '</div>';

        Response::themePage('default', $html);
    }
}
