<?php
namespace Extensions\Projects\Classes\View;

use App\Abstracts\AbstractModule;
use App\Hooks;
use App\Response;
use App\Route;
use Builders\TitleBuilder;
use Extensions\Projects\Classes\{ProjectJsonStore, ProjectNaming};
use Extensions\Projects\Classes\Module\{
    ActionContextRegistry,
    BreadcrumbManager,
    DisplayModeHelper,
    FkChainResolver,
    ModelRecordHelper,
    SelectedMenuSidebarHelper,
    ShowIfEvaluator,
    UrlBuilder
};

!defined('MILK_DIR') && die();

/**
 * Full-page renderer for a root record view driven by a ViewSchema (view_layout.json).
 *
 * Replaces the old AutoViewRenderer::buildChildTablesHtml() approach:
 *   - The layout is defined in JSON, not derived from the list renderer.
 *   - Each card / block is rendered independently via ViewBlockRenderer.
 *   - The edit renderer can target specific block IDs for AJAX reload.
 *
 * Lifecycle:
 *   1. Module registers the view action (unchanged).
 *   2. Module calls ViewPageRenderer::render() instead of AutoViewRenderer::render().
 *   3. ViewPageRenderer loads the root record, iterates over ViewSchema cards,
 *      and delegates each block to ViewBlockRenderer.
 */
class ViewPageRenderer
{
    protected ViewBlockRenderer $blockRenderer;

    public function __construct(
        protected AbstractModule $module,
        protected ActionContextRegistry $registry,
        protected FkChainResolver $fkResolver,
        protected BreadcrumbManager $breadcrumbManager,
        protected ShowIfEvaluator $showIfEvaluator
    ) {
        $this->blockRenderer = new ViewBlockRenderer(
            $this->registry,
            $this->fkResolver,
            $this->module->getPage()
        );
    }

    // ==================================================================
    // Main entry point
    // ==================================================================

    /**
     * Render the full view page for a root record.
     *
     * @param ViewSchema $schema  Parsed view_layout configuration.
     */
    public function render(ViewSchema $schema): void
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

        $viewAction = (string) ($context['view_action'] ?? '');
        if ($viewAction === '') {
            $this->renderError('View action is not enabled for this form.');
            return;
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            $this->renderError('Model class not found for this form.');
            return;
        }

        $id = _absint($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            $this->renderError('Missing record id for view action.');
            return;
        }

        $model = new $modelClass();
        $modelName = (string) ($context['form_name'] ?? 'Model');
        $modelTitle = trim((string) ($context['form_title'] ?? ''));
        if ($modelTitle === '') {
            $modelTitle = ProjectNaming::toTitle($modelName);
        }

        $modulePage = $this->module->getPage();
        $viewDisplay = DisplayModeHelper::normalize((string) ($context['view_display'] ?? 'page'));
        $isRoot = (bool) ($context['is_root'] ?? false);

        $record = $model->getByIdForEdit($id);
        if (!is_object($record) || $record->isEmpty()) {
            $this->renderError("Record #{$id} not found.");
            return;
        }

        // For root records, rootId = record id itself.
        $rootId = $isRoot ? $id : 0;
        $formattedData = $record->getFormattedData('array', false);
        if (!is_array($formattedData)) {
            $formattedData = [];
        }

        Hooks::run('projects.record.view.before-render', [
            'hook' => 'projects.record.view.before-render',
            'stage' => 'before_render',
            'page' => $modulePage,
            'context' => $context,
            'request' => $_REQUEST,
            'record_id' => $id,
            'root_id' => $rootId,
            'parent_id' => 0,
            'is_root' => $isRoot,
            'model_name' => $modelName,
            'model_title' => $modelTitle,
            'view_display' => $viewDisplay,
            'record' => $record,
            'formatted_data' => $formattedData,
        ]);

        // ------------------------------------------------------------------
        // Build body HTML from schema cards
        // ------------------------------------------------------------------
        $bodyHtml = '';
        foreach ($schema->cards as $card) {
            $bodyHtml .= $this->wrapCardContainer(
                $card->id,
                $id,
                $this->renderCard($card, $context, $id, $rootId)
            );
        }

        // ------------------------------------------------------------------
        // Title bar + delete button
        // ------------------------------------------------------------------
        $deleteConfirmAction = (string) ($context['delete_confirm_action'] ?? '');
        $listAction = (string) ($context['list_action'] ?? '');
        $chainParams = $this->fkResolver->getChainParams($context);
        $deleteParams = array_merge(['id' => $id], $chainParams);
        $canDelete = ProjectJsonStore::normalizeBool($context['can_manage_delete_records'] ?? false);

        $titleBtns = [];
        if ($listAction !== '') {
            $listUrl = Route::url(UrlBuilder::action($modulePage, $listAction, $chainParams));
            $titleBtns[] = [
                'label' => 'Back to Records',
                'link' => $listUrl,
                'color' => 'secondary',
            ];
        }
        if ($deleteConfirmAction !== '' && $canDelete) {
            $deleteUrl = Route::url(UrlBuilder::action($modulePage, $deleteConfirmAction, $deleteParams));
            $titleBtns[] = [
                'label' => 'Delete Entire Record',
                'link' => $deleteUrl,
                'color' => 'danger',
                'fetch' => 'post',
            ];
        }

        $title = 'Record ID: ' . $id;

        // ------------------------------------------------------------------
        // Respond: JSON (fetch) or full page
        // ------------------------------------------------------------------
        if ($viewDisplay !== 'page' && Response::isJson()) {
            $titleHtml = $this->buildFetchTitleHtml($title, $titleBtns);
            DisplayModeHelper::respond($viewDisplay, $titleHtml, $bodyHtml);
            return;
        }

        $this->breadcrumbManager->apply($context, $modulePage, 'view', $id, $rootId);

        $response = array_merge($this->module->getCommonData(), [
            'title' => $title,
            'title_btns' => $titleBtns,
            'search_html' => '',
            'html' => $bodyHtml,
            'table_id' => 'idView' . preg_replace('/[^a-zA-Z0-9]/', '', $modelName),
        ]);

        SelectedMenuSidebarHelper::applyToResponse($response, $this->module);
        Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
    }

    /**
     * Render a single card block (for AJAX partial reload).
     *
     * @param string $cardId  The card id from the schema.
     * @return string|null  HTML of the card, or null if card not found.
     */
    public function renderSingleCard(
        ViewSchema $schema,
        string $cardId,
        array $context,
        int $recordId,
        int $rootId
    ): ?string {
        foreach ($schema->cards as $card) {
            if ($card->id === $cardId) {
                return $this->renderCard($card, $context, $recordId, $rootId);
            }
        }
        return null;
    }

    public static function buildCardContainerId(string $cardId, int $recordId): string
    {
        $safeCard = preg_replace('/[^a-zA-Z0-9_-]/', '', $cardId);
        if (!is_string($safeCard) || $safeCard === '') {
            $safeCard = 'card';
        }
        return 'idViewCard' . $safeCard . max(0, $recordId);
    }

    // ==================================================================
    // Card rendering dispatch
    // ==================================================================

    protected function renderCard(
        ViewCardConfig $card,
        array $rootContext,
        int $recordId,
        int $rootId
    ): string {
        $html = $card->preHtml;

        if ($card->type === 'single-table' && $card->table !== null) {
            $html .= $this->renderSingleTableCard($card, $rootContext, $recordId, $rootId);
        } elseif ($card->type === 'group') {
            $html .= $this->renderGroupCard($card, $rootContext, $recordId, $rootId);
        }

        $html .= $card->postHtml;
        return $html;
    }

    protected function wrapCardContainer(string $cardId, int $recordId, string $cardHtml): string
    {
        $id = self::buildCardContainerId($cardId, $recordId);
        return '<div id="' . _r($id) . '" data-view-card-id="' . _r($cardId) . '">'
            . $cardHtml
            . '</div>';
    }

    protected function renderSingleTableCard(
        ViewCardConfig $card,
        array $rootContext,
        int $recordId,
        int $rootId
    ): string {
        $table = $card->table;
        if ($table === null) {
            return '';
        }

        $formContext = $this->resolveFormContext($table->name, $rootContext);
        if ($formContext === null) {
            return '<div class="card mb-3"><div class="card-body">'
                . '<p class="text-danger mb-0">Form context not found for &ldquo;' . _r($table->name) . '&rdquo;.</p>'
                . '</div></div>';
        }

        $isRootForm = (string) ($formContext['form_name'] ?? '') === (string) ($rootContext['form_name'] ?? '');

        switch ($table->displayAs) {
            case 'fields':
                if ($isRootForm) {
                    // Root form: render its own record fields.
                    return $this->blockRenderer->renderFields(
                        $formContext,
                        $recordId,
                        $rootId,
                        $table->title,
                        $table->icon
                    );
                }
                return $this->blockRenderer->renderFields(
                    $formContext,
                    $recordId,
                    $rootId,
                    $table->title,
                    $table->icon
                );

            case 'icon':
                $rowHtml = $this->blockRenderer->renderIcon($formContext, $recordId, $rootId);
                return $this->wrapGroupCard(
                    $table->title !== '' ? $table->title : ProjectNaming::toTitle($table->name),
                    $table->icon,
                    $rowHtml
                );

            case 'table':
                $rowHtml = $this->blockRenderer->renderTable($table, $formContext, $recordId, $rootId);
                return $this->wrapGroupCard(
                    $table->title !== '' ? $table->title : ProjectNaming::toTitle($table->name),
                    $table->icon,
                    $rowHtml
                );

            default:
                return '';
        }
    }

    protected function renderGroupCard(
        ViewCardConfig $card,
        array $rootContext,
        int $recordId,
        int $rootId
    ): string {
        $rowsHtml = '';

        foreach ($card->tables as $table) {
            $formContext = $this->resolveFormContext($table->name, $rootContext);
            if ($formContext === null) {
                $rowsHtml .= '<div class="row py-2 border-bottom"><div class="col-12">'
                    . '<p class="text-danger mb-0">Form context not found for &ldquo;' . _r($table->name) . '&rdquo;.</p>'
                    . '</div></div>';
                continue;
            }

            $rowsHtml .= $table->preHtml;

            switch ($table->displayAs) {
                case 'fields':
                    // Fields inside a group: unusual but supported.
                    // We render fields inline without a separate card wrapper.
                    $rowsHtml .= $this->renderInlineFieldsForGroup($formContext, $recordId, $rootId);
                    break;

                case 'icon':
                    $rowsHtml .= $this->blockRenderer->renderIcon($formContext, $recordId, $rootId);
                    break;

                case 'table':
                    $rowsHtml .= $this->blockRenderer->renderTable($table, $formContext, $recordId, $rootId);
                    break;
            }

            $rowsHtml .= $table->postHtml;
        }

        if ($rowsHtml === '') {
            return '';
        }

        return $this->wrapGroupCard($card->title, $card->icon, $rowsHtml);
    }

    // ==================================================================
    // Inline fields (for group cards containing a "fields" table)
    // ==================================================================

    protected function renderInlineFieldsForGroup(array $formContext, int $parentId, int $rootId): string
    {
        $modelClass = (string) ($formContext['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            return '<p class="text-danger mb-0">Model not found.</p>';
        }

        $model = new $modelClass();
        $fkField = (string) ($formContext['parent_fk_field'] ?? '');
        $isRoot = (bool) ($formContext['is_root'] ?? false);

        if ($isRoot) {
            $record = $model->getByIdForEdit($parentId);
        } else {
            if ($fkField === '') {
                return '<p class="text-danger mb-0">Missing FK field.</p>';
            }
            $record = ModelRecordHelper::findFirstByFk($model, $fkField, $parentId);
        }

        if (!is_object($record) || $record->isEmpty()) {
            return '<p class="text-body-secondary mb-0">No data.</p>';
        }

        $formattedData = $record->getFormattedData('array', false);
        if (!is_array($formattedData)) {
            $formattedData = [];
        }

        $viewRules = $model->getRules('view', true);
        if (empty($viewRules)) {
            $viewRules = $model->getRules();
        }
        $nestedCountAliases = $this->resolveNestedCountAliases($formContext);

        $html = '';
        foreach ($viewRules as $field => $rule) {
            if ($field === '___action') {
                continue;
            }
            if (isset($nestedCountAliases[$field])) {
                continue;
            }
            if ($this->isCustomHtmlFieldRule($rule)) {
                continue;
            }
            if (!array_key_exists($field, $formattedData)) {
                continue;
            }
            $label = (string) ($rule['label'] ?? $this->toLabel($field));
            $value = $this->formatValue($formattedData[$field]);
            $html .= '<div class="row py-2 border-bottom">'
                . '<div class="col-lg-3 fw-semibold">' . _r($label) . ':</div>'
                . '<div class="col-lg-9">' . $value . '</div>'
                . '</div>';
        }

        if ($html === '') {
            $html = '<p class="text-body-secondary mb-0">No view fields.</p>';
        }

        $editAction = (string) ($formContext['edit_action'] ?? '');
        if ($editAction !== '') {
            $recordId = _absint(ModelRecordHelper::extractFieldValue($record, (string) $model->getPrimaryKey()));
            if ($recordId > 0) {
                $editParams = array_merge(['id' => $recordId], $this->fkResolver->getChainParams($formContext));
                $editUrl = Route::url(UrlBuilder::action($this->module->getPage(), $editAction, $editParams));
                $editDisplay = DisplayModeHelper::getEditMode($formContext);
                $fetchAttr = DisplayModeHelper::buildFetchAttribute($editDisplay);
                $html .= '<div class="text-end pt-2">'
                    . '<a class="btn btn-sm btn-primary" href="' . _r($editUrl) . '"' . $fetchAttr . '>Edit</a>'
                    . '</div>';
            }
        }

        return $html;
    }

    // ==================================================================
    // Context resolution
    // ==================================================================

    /**
     * Resolve the ActionContextRegistry entry for a form name.
     *
     * Tries the list action first (canonical context), then edit action.
     */
    protected function resolveFormContext(string $formName, array $rootContext): ?array
    {
        // Check if formName matches the root context itself.
        if ((string) ($rootContext['form_name'] ?? '') === $formName) {
            return $rootContext;
        }

        // Try standard list action slug.
        $listAction = ProjectNaming::toActionSlug($formName) . '-list';
        $ctx = $this->registry->get($listAction);
        if (is_array($ctx)) {
            return $ctx;
        }

        // Try edit action slug.
        $editAction = ProjectNaming::toActionSlug($formName) . '-edit';
        $ctx = $this->registry->get($editAction);
        if (is_array($ctx)) {
            return $ctx;
        }

        return null;
    }

    // ==================================================================
    // HTML helpers
    // ==================================================================

    protected function wrapGroupCard(string $title, string $icon, string $bodyHtml): string
    {
        $headerHtml = '';
        if ($title !== '') {
            $headerHtml = '<div class="card-header"><h5 class="mb-0">';
            if ($icon !== '') {
                $headerHtml .= '<i class="' . _r($icon) . '"></i> ';
            }
            $headerHtml .= _r($title) . '</h5></div>';
        }

        return '<div class="card mt-4">'
            . $headerHtml
            . '<div class="card-body">' . $bodyHtml . '</div>'
            . '</div>';
    }

    /**
     * @param array<int,array<string,string>> $titleBtns
     */
    protected function buildFetchTitleHtml(string $title, array $titleBtns): string
    {
        $titleBuilder = TitleBuilder::create($title)->includeMessages(false);
        foreach ($titleBtns as $btn) {
            if (!is_array($btn)) {
                continue;
            }
            $label = (string) ($btn['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $link = (string) ($btn['link'] ?? '');
            if ($link === '') {
                continue;
            }
            $titleBuilder->addButton(
                $label,
                $link,
                (string) ($btn['color'] ?? 'primary'),
                '',
                isset($btn['fetch']) ? (string) $btn['fetch'] : null
            );
        }

        return $titleBuilder->render();
    }

    protected function renderError(string $message): void
    {
        $html = '<div class="container py-4">'
            . '<h1>Projects Extension Error</h1>'
            . '<p class="text-danger">' . _r($message) . '</p>'
            . '</div>';

        Response::themePage('default', $html);
    }

    protected function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '<span class="text-body-secondary">-</span>';
        }
        if (is_scalar($value)) {
            return _r((string) $value);
        }
        return _r((string) json_encode($value));
    }

    protected function toLabel(string $field): string
    {
        $label = preg_replace(['/([a-z])([A-Z])/', '/_+/', '/[-\s]+/'], ['\\1 \\2', ' ', ' '], $field);
        return ucfirst(trim((string) preg_replace('/\s+/', ' ', (string) $label)));
    }

    protected function isCustomHtmlFieldRule(mixed $rule): bool
    {
        if (!is_array($rule)) {
            return false;
        }

        $type = strtolower(trim((string) ($rule['type'] ?? '')));
        $formType = strtolower(trim((string) ($rule['form-type'] ?? '')));
        return $type === 'html' || $formType === 'html';
    }

    /**
     * Build a lookup of count aliases generated by withCount() for direct children.
     *
     * @return array<string,bool>
     */
    protected function resolveNestedCountAliases(array $formContext): array
    {
        $aliases = [];
        $childrenMetaByAlias = is_array($formContext['children_meta_by_alias'] ?? null)
            ? $formContext['children_meta_by_alias']
            : [];

        foreach ($childrenMetaByAlias as $alias => $meta) {
            if (is_string($alias) && $alias !== '') {
                $aliases[$alias] = true;
            }
            if (is_array($meta)) {
                $childFormName = trim((string) ($meta['form_name'] ?? ''));
                if ($childFormName !== '') {
                    $aliases[ProjectNaming::withCountAliasForForm($childFormName)] = true;
                }
            }
        }

        return $aliases;
    }
}
