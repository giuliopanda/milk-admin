<?php
namespace Modules\Docs;

use App\Abstracts\AbstractController;
use App\Attributes\RequestAction;
use App\{Response, Route, Theme};

// Load DocsService which contains DocMetadata class
require_once __DIR__ . '/DocsService.php';

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Documentation Controller
 *
 * Handles routing and rendering for documentation pages across three guides
 */
class DocsController extends AbstractController
{
    private const ARRAYDB_TABLE_ACTION = ArrayDbDocsService::TABLE_ACTION;
    private const ARRAYDB_CHART_ACTION = ArrayDbDocsService::CHART_ACTION;

    /**
     * Default action - redirects to developer guide's home page
     */
    #[RequestAction('home')]
    public function actionHome()
    {
        // Redirect to developer guide by default
        Route::redirect("?page=docs&action=Developer/GettingStarted/introduction");
    }

    /**
     * Main route handler - displays documentation pages with filtered sidebar
     */
    public function handleRoutes()
    {
        if (!$this->access()) {
            $queryString = Route::getQueryString();
            Route::redirect('?page=deny&redirect=' . Route::urlsafeB64Encode($queryString));
            return;
        }

        // Get parameters (prefer POST action for JSON reloads)
        $action = str_replace('..', '', $_POST['action'] ?? $_GET['action'] ?? '');

        if ($action === self::ARRAYDB_TABLE_ACTION) {
            $this->handleArrayDbTable();
            return;
        }

        if ($action === self::ARRAYDB_CHART_ACTION) {
            $this->handleArrayDbChart();
            return;
        }

        // Redirect to home if no action specified
        if (empty($action)) {
            $this->actionHome();
            return;
        }

        // Extract guide from action path (e.g., "Framework/Core/api" -> "framework")
        $actionParts = explode('/', $action);
        $guide = !empty($actionParts[0]) ? strtolower($actionParts[0]) : 'developer';

        // Validate guide
        if (!in_array($guide, ['developer', 'framework', 'user'])) {
            $guide = 'developer';
        }

        // Build page path
        $pagePath = MILK_DIR . "/Modules/Docs/Pages/$action.page.php";

        if (!file_exists($pagePath)) {
            Response::themePage('404', '', 'Page not found: ' . $action);
            return;
        }

        // Load page content
        ob_start();
        require $pagePath;
        $contentHtml = ob_get_clean();


        // Get metadata
        $metadata = new DocMetadata($pagePath);

        // Generate sidebar filtered by guide
        $sidebar = DocsService::generateSidebar($guide);

        // Combine sidebar and content
        $combinedContent = '
        <div class="container-fluid px-0">
            <div class="row">
                <div class="col-md-3">
                    ' . $sidebar . '
                </div>
                <div class="col-md-9">
                    ' . $contentHtml . '
                </div>
            </div>
        </div>';

        // Set content
        Theme::set('content', $combinedContent);

     
        // Render
        Response::themePage('default');
    }

    private function handleArrayDbTable(): void
    {
        $tableId = $_REQUEST['table_id'] ?? ArrayDbDocsService::TABLE_ID;
        $response = ArrayDbDocsService::tableResponse($tableId);
        Response::htmlJson($response);
    }

    private function handleArrayDbChart(): void
    {
        $chartId = $_REQUEST['chart_id'] ?? ArrayDbDocsService::CHART_ID;
        $response = ArrayDbDocsService::chartResponse($chartId);
        Response::htmlJson($response);
    }
}
