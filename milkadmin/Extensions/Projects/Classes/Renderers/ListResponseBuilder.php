<?php
namespace Extensions\Projects\Classes\Renderers;

use App\Abstracts\AbstractModule;
use App\Response;
use Extensions\Projects\Classes\{ProjectJsonStore, ProjectNaming};
use Extensions\Projects\Classes\SearchUrlParamsResolver;
use Extensions\Projects\Classes\Module\{
    ActionContextRegistry,
    DisplayModeHelper,
    FkChainResolver,
    ModelRecordHelper,
    ShowIfEvaluator,
    UrlBuilder
};

!defined('MILK_DIR') && die();

/**
 * Builds the full list response payload for a manifest form.
 *
 * Orchestrates context resolution, validation, and table configuration by
 * delegating to ListContextParams (value object) and ListTableConfigurator
 * (TableBuilder setup). Keeps the build() method readable as a sequence of
 * high-level steps.
 */
class ListResponseBuilder
{
    protected const SOFT_DELETE_SCOPE_FILTER_NAME = 'projects_soft_delete_scope';
    protected const SOFT_DELETE_SCOPE_ACTIVE = 'active';
    protected const SOFT_DELETE_SCOPE_DELETED = 'deleted';

    protected AbstractModule $module;
    protected ActionContextRegistry $registry;
    protected FkChainResolver $fkResolver;
    protected ShowIfEvaluator $showIfEvaluator;

    /** @var ListTableConfigurator Configures the TableBuilder. */
    protected ListTableConfigurator $tableConfigurator;

    /** @var ListSearchFiltersConfigurator Applies root search filters from JSON config. */
    protected ListSearchFiltersConfigurator $searchFiltersConfigurator;

    public function __construct(
        AbstractModule $module,
        ActionContextRegistry $registry,
        FkChainResolver $fkResolver,
        ShowIfEvaluator $showIfEvaluator
    ) {
        $this->module = $module;
        $this->registry = $registry;
        $this->fkResolver = $fkResolver;
        $this->showIfEvaluator = $showIfEvaluator;

        $this->tableConfigurator = new ListTableConfigurator($registry, $showIfEvaluator);
        $this->searchFiltersConfigurator = new ListSearchFiltersConfigurator();
    }

    /**
     * Set the callback used for delete row actions in TableBuilder.
     *
     * Forwarded to ListTableConfigurator so its action proxy can invoke
     * the real delete logic in AutoListRenderer.
     *
     * @param callable $callback  Callback matching signature ($records, $request): bool.
     * @return void
     */
    public function setDeleteCallback(callable $callback): void
    {
        $this->tableConfigurator->setDeleteCallback($callback);
    }

    /**
     * Set the callback used for restore row actions in TableBuilder.
     *
     * @param callable $callback Callback matching signature ($records, $request): bool.
     */
    public function setRestoreCallback(callable $callback): void
    {
        $this->tableConfigurator->setRestoreCallback($callback);
    }

    /**
     * Set the callback used for hard-delete row actions in TableBuilder.
     *
     * @param callable $callback Callback matching signature ($records, $request): bool.
     */
    public function setHardDeleteCallback(callable $callback): void
    {
        $this->tableConfigurator->setHardDeleteCallback($callback);
    }

    /**
     * Build the full list response payload without sending output.
     *
     * Resolves parameters, validates the context, handles redirects for
     * special cases, configures the table, and assembles the response.
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
    public function build(array $context, array $options = []): array
    {
        // Step 1: Resolve all parameters into a value object.
        $p = $this->resolveParams($context, $options);
        if ($p === null) {
            return $this->errorResult(
                !empty($context['error']) ? (string) $context['error'] : 'Model class not found for this form.'
            );
        }

        // Step 2: Validate non-root FK chain and parent constraints.
        $validationResult = $this->validateNonRootConstraints($p);
        if ($validationResult !== null) {
            return $validationResult;
        }

        // Step 3: Check for special-case redirects (root view, single-record child).
        $redirectResult = $this->resolveRedirects($p);
        if ($redirectResult !== null) {
            return $redirectResult;
        }

        // Step 3.5: Required URL params failed -> do not render filters/table.
        if ($p->urlFilterRequiredFailed) {
            return $this->buildUrlParamAccessDeniedResponse($p);
        }

        // Step 3.6: suspended/close projects show only status alert, no table/actions.
        if ($p->projectBlocksTables) {
            return $this->buildProjectStatusBlockedResponse($p);
        }

        // Step 4: Configure the TableBuilder with actions, fields, and filters.
        $tableBuilder = $this->tableConfigurator->configure($p);
        $searchPayload = $this->searchFiltersConfigurator->configure($tableBuilder, $p);
        // Step 5: Assemble the final response array.
        return $this->assembleResponse($p, $tableBuilder, $searchPayload);
    }

    /**
     * Build and return only the configured TableBuilder for the current list context.
     *
     * This does not render output and does not assemble the final response payload.
     * Returns null when context is invalid, a redirect would be required, or
     * mandatory URL filters are missing.
     */
    public function buildTableBuilder(array $context, array $options = []): ?\Builders\TableBuilder
    {
        $p = $this->resolveParams($context, $options);
        if ($p === null) {
            return null;
        }

        if ($this->validateNonRootConstraints($p) !== null) {
            return null;
        }

        if ($this->resolveRedirects($p) !== null) {
            return null;
        }

        if ($p->urlFilterRequiredFailed) {
            return null;
        }

        if ($p->projectBlocksTables) {
            return null;
        }

        $tableBuilder = $this->tableConfigurator->configure($p);
        $this->searchFiltersConfigurator->configure($tableBuilder, $p);

        return $tableBuilder;
    }

    /**
     * Build and return only the configured SearchBuilder for the current list context.
     *
     * Returns null when context is invalid, a redirect would be required, mandatory
     * URL filters are missing, or search filters are not defined/applicable.
     */
    public function buildSearchBuilder(array $context, array $options = []): ?\Builders\SearchBuilder
    {
        $p = $this->resolveParams($context, $options);
        if ($p === null) {
            return null;
        }

        if ($this->validateNonRootConstraints($p) !== null) {
            return null;
        }

        if ($this->resolveRedirects($p) !== null) {
            return null;
        }

        if ($p->urlFilterRequiredFailed) {
            return null;
        }

        if ($p->projectBlocksTables) {
            return null;
        }

        $tableBuilder = $this->tableConfigurator->configure($p);
        return $this->searchFiltersConfigurator->buildSearchBuilder($tableBuilder, $p);
    }

    // ------------------------------------------------------------------
    // Step 1: Parameter resolution
    // ------------------------------------------------------------------

    /**
     * Resolve all context and options into a ListContextParams value object.
     *
     * Returns null if context has errors or model class is invalid.
     *
     * @param array $context  Raw action context.
     * @param array $options  Build options.
     * @return ListContextParams|null  Populated params, or null on failure.
     */
    protected function resolveParams(array $context, array $options): ?ListContextParams
    {
        if (!empty($context['error'])) {
            return null;
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            return null;
        }

        $p = new ListContextParams();
        $p->context = $context;
        $p->options = $options;

        // Model info.
        $p->modelClass = $modelClass;
        $p->model = new $modelClass();
        $p->modelName = (string) ($context['form_name'] ?? 'Model');
        $p->modelTitle = trim((string) ($context['form_title'] ?? ''));
        if ($p->modelTitle === '') {
            $p->modelTitle = ProjectNaming::toTitle($p->modelName);
        }
        if ($p->modelTitle === '') {
            $p->modelTitle = $p->modelName;
        }
        $p->primaryKey = (string) $p->model->getPrimaryKey();
        $p->modulePage = $this->module->getPage();

        // Actions.
        $p->editAction = (string) ($context['edit_action'] ?? '');
        $p->viewAction = (string) ($context['view_action'] ?? '');

        // Display modes.
        $p->listDisplay = DisplayModeHelper::getListMode($context);
        $p->editDisplay = DisplayModeHelper::getEditMode($context);
        $p->viewDisplay = DisplayModeHelper::normalize((string) ($context['view_display'] ?? 'page'));
        $p->editFetchMethod = DisplayModeHelper::getFetchMethod($p->editDisplay);
        $p->viewFetchMethod = DisplayModeHelper::getFetchMethod($p->viewDisplay);

        // Hierarchy flags.
        $p->isRoot = (bool) ($context['is_root'] ?? false);
        $p->maxRecords = (string) ($context['max_records'] ?? 'n');
        $p->fkField = (string) ($context['parent_fk_field'] ?? '');
        $p->hasChildren = !empty($context['children_meta_by_alias'] ?? []);
        $p->softDeleteEnabled = $this->normalizeBool($context['soft_delete'] ?? false);
        $p->softDeleteScopeFilterEnabled = $this->normalizeBool($context['soft_delete_scope_filter'] ?? false);
        $p->softDeleteScope = self::SOFT_DELETE_SCOPE_ACTIVE;
        $p->allowDeleteRecordEnabled = !array_key_exists('allow_delete_record', $context)
            ? true
            : $this->normalizeBool($context['allow_delete_record']);
        if (array_key_exists('can_manage_delete_records', $context)) {
            $p->canManageDeleteRecords = $this->normalizeBool($context['can_manage_delete_records']);
        } else {
            $p->canManageDeleteRecords = $this->isCurrentUserAdministrator()
                || $p->softDeleteEnabled
                || $p->allowDeleteRecordEnabled;
        }
        $p->allowEditEnabled = !array_key_exists('allow_edit', $context)
            ? true
            : $this->normalizeBool($context['allow_edit']);
        $p->showSearch = (bool) ($context['show_search'] ?? false);
        $p->defaultOrderEnabled = $this->normalizeBool($context['default_order_enabled'] ?? false);
        $p->defaultOrderField = trim((string) ($context['default_order_field'] ?? ''));
        $p->defaultOrderDirection = $this->normalizeOrderDirection((string) ($context['default_order_direction'] ?? 'asc'));
        if ($p->defaultOrderField === '') {
            $p->defaultOrderEnabled = false;
            $p->defaultOrderDirection = 'asc';
        }
        $p->searchFilters = is_array($context['search_filters'] ?? null)
            ? $context['search_filters']
            : [];
        $resolvedUrlFilters = SearchUrlParamsResolver::resolveFromConfig($p->searchFilters);
        $p->urlFilterParams = is_array($resolvedUrlFilters['params'] ?? null)
            ? $resolvedUrlFilters['params']
            : [];
        $p->urlFilterWhereClauses = is_array($resolvedUrlFilters['filters'] ?? null)
            ? $resolvedUrlFilters['filters']
            : [];
        $p->urlFilterRequiredFailed = !empty($resolvedUrlFilters['required_failed']);
        $p->childrenMetaByAlias = is_array($context['children_meta_by_alias'] ?? null)
            ? $context['children_meta_by_alias']
            : [];
        $p->childCountColumn = ProjectJsonStore::normalizeChildCountColumnMode(
            ProjectJsonStore::resolveAliasedKey(
                $context,
                ['child_count_column', 'childCountColumn'],
                ''
            )
        );
        $p->projectStatus = $this->normalizeProjectStatus(
            ProjectJsonStore::resolveAliasedKey(
                $context,
                ['project_status', 'projectStatus'],
                'development'
            )
        );
        $p->projectBlocksTables = in_array($p->projectStatus, ['suspended', 'closed'], true);

        // Table identification.
        $defaultTableId = 'idTable' . preg_replace('/[^a-zA-Z0-9]/', '', $p->modelName);
        $p->tableId = UrlBuilder::normalizeListId((string) ($options['table_id'] ?? $defaultTableId));
        if ($p->tableId === '') {
            $p->tableId = $defaultTableId;
        }
        $p->reloadListIdParamKey = UrlBuilder::reloadListIdParamKey();

        // Inline / embedded flags.
        $allowRaw = $options['allow_single_record_inline']
            ?? ($_REQUEST['projects_allow_single_record_inline'] ?? false);
        $p->allowSingleRecordInline = $this->normalizeBool($allowRaw);
        $p->isEmbeddedViewTable = $this->normalizeBool(
            $options['embedded_view_table'] ?? ($_REQUEST['projects_embedded_view_table'] ?? false)
        );
        if (!$p->isRoot && $p->softDeleteEnabled && !$p->softDeleteScopeFilterEnabled) {
            $p->softDeleteScopeFilterEnabled = true;
        }
        if ($p->softDeleteScopeFilterEnabled) {
            $p->softDeleteScope = $this->resolveSoftDeleteScopeFromTableRequest($p->tableId);
        }

        $p->useSingleEntryRootView = $p->isRoot && !$p->isEmbeddedViewTable && $p->viewAction !== '';
        $p->showChildCountColumns = $this->resolveShowChildCountColumns($p);

        // FK chain.
        $chainParamsRaw = is_array($options['chain_params'] ?? null)
            ? $options['chain_params']
            : $this->fkResolver->getChainParams($context);
        $p->fkChainParams = $this->normalizeNumericParams($chainParamsRaw);

        $rootFkField = $this->fkResolver->getRootFkField($context);
        $p->requestedRootId = isset($options['root_id'])
            ? _absint($options['root_id'])
            : _absint($p->fkChainParams[$rootFkField] ?? 0);

        // Parent ID (resolved later for non-root in validateNonRootConstraints).
        $p->parentId = 0;

        return $p;
    }

    protected function resolveSoftDeleteScopeFromTableRequest(string $tableId): string
    {
        $tableId = trim($tableId);
        if ($tableId === '') {
            return self::SOFT_DELETE_SCOPE_ACTIVE;
        }

        $tableRequest = is_array($_REQUEST[$tableId] ?? null) ? $_REQUEST[$tableId] : [];
        $filtersJson = trim((string) ($tableRequest['filters'] ?? ''));
        if ($filtersJson === '') {
            return self::SOFT_DELETE_SCOPE_ACTIVE;
        }

        $filters = json_decode($filtersJson, true);
        if (!is_array($filters)) {
            return self::SOFT_DELETE_SCOPE_ACTIVE;
        }

        foreach ($filters as $filterEntry) {
            if (!is_string($filterEntry)) {
                continue;
            }
            $parts = explode(':', $filterEntry, 2);
            $name = strtolower(trim((string) ($parts[0] ?? '')));
            if ($name !== self::SOFT_DELETE_SCOPE_FILTER_NAME) {
                continue;
            }

            $value = strtolower(trim((string) ($parts[1] ?? '')));
            if ($value === self::SOFT_DELETE_SCOPE_DELETED) {
                return self::SOFT_DELETE_SCOPE_DELETED;
            }
            return self::SOFT_DELETE_SCOPE_ACTIVE;
        }

        return self::SOFT_DELETE_SCOPE_ACTIVE;
    }

    protected function isCurrentUserAdministrator(): bool
    {
        try {
            return \App\Permissions::check('_user.is_admin');
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------
    // Step 2: Non-root validation
    // ------------------------------------------------------------------

    /**
     * Validate constraints specific to non-root (child) forms.
     *
     * Checks FK field presence, chain completeness, ancestor root consistency,
     * showIf conditions, and FK field existence in model rules. Also resolves
     * the parent ID on the params object.
     *
     * @param ListContextParams $p  Resolved parameters (parentId is set here).
     * @return array|null  Error result array if validation fails, null if OK.
     */
    protected function validateNonRootConstraints(ListContextParams $p): ?array
    {
        if ($p->isRoot) {
            return null;
        }

        // FK field must exist for child forms.
        if ($p->fkField === '') {
            return $this->errorResult('Missing parent FK convention for this form.', $p);
        }

        // All required FK chain fields must be present in params.
        $missingFk = $this->findMissingChainFieldInParams($p->context, $p->fkChainParams);
        if ($missingFk !== null) {
            return $this->errorResult("Missing parent id. Provide '{$missingFk}' in the URL query string.", $p);
        }

        // Validate ancestor root consistency unless explicitly skipped.
        $skipValidation = (bool) ($p->options['skip_ancestor_root_validation'] ?? false);
        if (!$skipValidation) {
            $chainRootError = $this->fkResolver->validateAncestorRootConsistency($p->context, $p->requestedRootId);
            if ($chainRootError !== null) {
                return $this->errorResult($chainRootError, $p);
            }
        }

        // Resolve parent ID.
        $p->parentId = isset($p->options['parent_id'])
            ? _absint($p->options['parent_id'])
            : _absint($p->fkChainParams[$p->fkField] ?? 0);

        // Evaluate showIf conditions.
        $showIfError = $this->showIfEvaluator->validate($p->context, $p->parentId, $p->requestedRootId);
        if ($showIfError !== null) {
            return $this->errorResult($showIfError, $p);
        }

        // Verify FK field exists in model rules.
        $rules = $p->model->getRules();
        if (!isset($rules[$p->fkField])) {
            return $this->errorResult(
                "FK field '{$p->fkField}' is missing in model rules. Cannot list child records without a link to its parent form.",
                $p
            );
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Step 3: Redirect resolution
    // ------------------------------------------------------------------

    /**
     * Check for special-case redirects that bypass normal list rendering.
     *
     * Handles two scenarios:
     * - First-level child forms managed by root view → redirect to root view.
     * - Single-record child without children → redirect to edit form.
     *
     * @param ListContextParams $p  Resolved parameters.
     * @return array|null  Redirect result array, or null if no redirect needed.
     */
    protected function resolveRedirects(ListContextParams $p): ?array
    {
        if ($p->isRoot) {
            return null;
        }

        // First-level forms managed by root view.
        if (!$p->allowSingleRecordInline && $this->isFirstLevelManagedByRootView($p->context)) {
            $target = $this->resolveRootViewTarget(
                $p->context,
                $p->fkChainParams,
                $p->requestedRootId,
                $p->parentId,
                $p->urlFilterParams
            );
            if (is_array($target)) {
                return [
                    'error' => '',
                    'redirect' => UrlBuilder::action($p->modulePage, (string) $target['action'], (array) $target['params']),
                    'response' => [],
                    'table_id' => $p->tableId,
                    'list_display' => $p->listDisplay,
                ];
            }
        }

        // Single-record child without children: redirect to edit.
        if ($p->maxRecords === '1' && !$p->hasChildren && !$p->allowSingleRecordInline) {
            if ($p->editAction === '') {
                return $this->errorResult('Missing edit action for this form.', $p);
            }

            $params = array_merge($p->fkChainParams, $p->urlFilterParams);
            if ($p->tableId !== '') {
                $params[$p->reloadListIdParamKey] = $p->tableId;
            }
            return [
                'error' => '',
                'redirect' => UrlBuilder::action($p->modulePage, $p->editAction, $params),
                'response' => [],
                'table_id' => $p->tableId,
                'list_display' => $p->listDisplay,
            ];
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Step 5: Response assembly
    // ------------------------------------------------------------------

    /**
     * Assemble the final response array from the configured TableBuilder.
     *
     * Merges common data, sets title/heading, builds action buttons,
     * and includes AJAX title updates when needed.
     *
     * @param ListContextParams         $p             Resolved parameters.
     * @param \Builders\TableBuilder    $tableBuilder  Configured table builder.
     * @param array{html:string,field_count:int} $searchPayload Custom search/filter payload.
     * @return array  Complete list response payload.
     */
    protected function assembleResponse(
        ListContextParams $p,
        \Builders\TableBuilder $tableBuilder,
        array $searchPayload
    ): array
    {
        $includeCommonData = !isset($p->options['include_common_data']) || (bool) $p->options['include_common_data'];
        $response = $tableBuilder->getResponse();
        if ($includeCommonData) {
            $response = array_merge($this->module->getCommonData(), $response);
        }

        // Set title.
        $response['title'] = $p->isEmbeddedViewTable ? $p->modelTitle : ($p->modelTitle . ' List');

        // Resolve heading size.
        $response['title_heading_size'] = $this->resolveHeadingSize($p);
        $response['title_small_buttons'] = $p->isEmbeddedViewTable;

        // Build title action buttons (New / Add).
        $response['title_btns'] = $this->buildTitleButtons($p);
        $response['show_search'] = $p->showSearch;
        $searchHtml = trim((string) ($searchPayload['html'] ?? ''));
        $searchFieldCount = (int) ($searchPayload['field_count'] ?? 0);
        if (trim($searchHtml) !== '') {
            if ($searchFieldCount > 2) {
                $existingBottomContent = isset($response['bottom_content']) && is_string($response['bottom_content'])
                    ? trim($response['bottom_content'])
                    : '';
                $response['bottom_content'] = $existingBottomContent !== ''
                    ? $existingBottomContent . $searchHtml
                    : $searchHtml;
            } else {
                $response['search_html'] = $searchHtml;
            }
            $response['show_search'] = false;
        }

        // For AJAX table refresh, include updated title HTML.
        $this->applyAjaxTitleUpdate($response, $p);

        return [
            'error' => '',
            'redirect' => '',
            'response' => $response,
            'table_id' => $p->tableId,
            'list_display' => $p->listDisplay,
        ];
    }

    /**
     * Build a response that shows only a generic access denied alert.
     *
     * Used when one or more required URL params are missing/invalid.
     */
    protected function buildUrlParamAccessDeniedResponse(ListContextParams $p): array
    {
        $includeCommonData = !isset($p->options['include_common_data']) || (bool) $p->options['include_common_data'];
        $response = [];
        if ($includeCommonData) {
            $response = $this->module->getCommonData();
        }

        $response['table_id'] = $p->tableId;
        $response['title'] = $p->isEmbeddedViewTable ? $p->modelTitle : ($p->modelTitle . ' List');
        $response['title_heading_size'] = $this->resolveHeadingSize($p);
        $response['title_small_buttons'] = $p->isEmbeddedViewTable;
        $response['title_btns'] = [];
        $response['show_search'] = false;
        $response['search_html'] = '';
        $response['bottom_content'] = '';
        $response['html'] = '<div class="alert alert-danger mb-0" role="alert">You cannot access this content.</div>';

        $this->applyAjaxTitleUpdate($response, $p);

        return [
            'error' => '',
            'redirect' => '',
            'response' => $response,
            'table_id' => $p->tableId,
            'list_display' => $p->listDisplay,
        ];
    }

    protected function buildProjectStatusBlockedResponse(ListContextParams $p): array
    {
        $includeCommonData = !isset($p->options['include_common_data']) || (bool) $p->options['include_common_data'];
        $response = [];
        if ($includeCommonData) {
            $response = $this->module->getCommonData();
        }

        $response['table_id'] = $p->tableId;
        $response['title'] = $p->isEmbeddedViewTable ? $p->modelTitle : ($p->modelTitle . ' List');
        $response['title_heading_size'] = $this->resolveHeadingSize($p);
        $response['title_small_buttons'] = $p->isEmbeddedViewTable;
        $response['title_btns'] = [];
        $response['show_search'] = false;
        $response['search_html'] = '';
        $response['bottom_content'] = '';
        $response['html'] = '<div class="alert alert-warning mb-0" role="alert">'
            . $this->buildProjectStatusBlockedHtmlMessage($p->projectStatus)
            . '</div>';

        $this->applyAjaxTitleUpdate($response, $p);

        return [
            'error' => '',
            'redirect' => '',
            'response' => $response,
            'table_id' => $p->tableId,
            'list_display' => $p->listDisplay,
        ];
    }

    protected function buildProjectStatusBlockedHtmlMessage(string $status): string
    {
        $status = $this->normalizeProjectStatus($status);
        if ($status === 'suspended') {
            return '<strong>Project is suspended.</strong> The project will be back online soon.';
        }
        if ($status === 'closed') {
            return '<strong>Project is close.</strong>';
        }

        return '<strong>Project is unavailable.</strong>';
    }

    /**
     * Resolve the appropriate heading size for the list title.
     *
     * Uses h5 for embedded view tables, h2 otherwise (unless overridden).
     *
     * @param ListContextParams $p  Resolved parameters.
     * @return string  Normalized heading size ('h2'–'h5').
     */
    protected function resolveHeadingSize(ListContextParams $p): string
    {
        $titleHeadingSize = trim((string) ($p->options['title_heading_size'] ?? ''));
        if ($titleHeadingSize === '') {
            $isEmbeddedViewTableRequest = $p->allowSingleRecordInline
                && $p->tableId !== ''
                && strpos($p->tableId, 'idTableView') === 0
                && UrlBuilder::normalizeListId((string) ($_REQUEST['table_id'] ?? '')) === $p->tableId;
            if ($p->isEmbeddedViewTable || $isEmbeddedViewTableRequest) {
                $titleHeadingSize = 'h5';
            }
        }
        return $this->normalizeHeadingSize($titleHeadingSize !== '' ? $titleHeadingSize : 'h2');
    }

    /**
     * Build title action buttons (New / Add) based on max records constraints.
     *
     * @param ListContextParams $p  Resolved parameters.
     * @return array  List of button config arrays for the title bar.
     */
    protected function buildTitleButtons(ListContextParams $p): array
    {
        $titleBtns = [];

        $canCreateNew = true;
        $finiteMaxRecords = UrlBuilder::getFiniteMaxRecords($p->maxRecords);
        if ($finiteMaxRecords > 0) {
            if ($p->isRoot) {
                $existingCount = ModelRecordHelper::countAll($p->model);
                $canCreateNew = ($existingCount < $finiteMaxRecords);
            } elseif ($p->parentId > 0) {
                $existingCount = ModelRecordHelper::countByFk($p->model, $p->fkField, $p->parentId);
                $canCreateNew = ($existingCount < $finiteMaxRecords);
            }
        }
        if ($canCreateNew && $p->editAction !== '' && $p->allowEditEnabled) {
            $newParams = array_merge($p->fkChainParams, $p->urlFilterParams);
            if ($p->tableId !== '') {
                $newParams[$p->reloadListIdParamKey] = $p->tableId;
            }
            $newBtn = [
                'label' => $p->isEmbeddedViewTable ? 'Add' : ('Add New'),
                'link' => UrlBuilder::action($p->modulePage, $p->editAction, $newParams),
                'color' => 'primary',
                'small' => $p->isEmbeddedViewTable,
            ];
            DisplayModeHelper::applyToButton($newBtn, $p->editDisplay);
            $titleBtns[] = $newBtn;
        }

        return $titleBtns;
    }

    /**
     * Inject AJAX title update data into the response when this is a JSON table refresh.
     *
     * @param array             &$response  Response array (modified in place).
     * @param ListContextParams $p          Resolved parameters.
     * @return void
     */
    protected function applyAjaxTitleUpdate(array &$response, ListContextParams $p): void
    {
        $requestedTableId = UrlBuilder::normalizeListId((string) ($_REQUEST['table_id'] ?? ''));
        if (!Response::isJson() || $requestedTableId === '' || $requestedTableId !== $p->tableId) {
            return;
        }

        $presenterHelper = new ListPresenterHelper();
        $titleId = $presenterHelper->buildTitleId($p->tableId);
        if ($titleId !== '') {
            $response['titles'] = [[
                'id' => $titleId,
                'html' => $presenterHelper->buildTitleInnerHtml($response, $p->tableId),
            ]];
        }
    }

    // ------------------------------------------------------------------
    // Result helpers
    // ------------------------------------------------------------------

    /**
     * Build a standardized error result array.
     *
     * @param string                $error  Error message.
     * @param ListContextParams|null $p     Resolved params (for tableId/listDisplay), or null.
     * @return array  Error result with empty response.
     */
    protected function errorResult(string $error, ?ListContextParams $p = null): array
    {
        return [
            'error' => $error,
            'redirect' => '',
            'response' => [],
            'table_id' => $p !== null ? $p->tableId : '',
            'list_display' => $p !== null ? $p->listDisplay : 'page',
        ];
    }

    // ------------------------------------------------------------------
    // Parameter normalization helpers
    // ------------------------------------------------------------------

    /**
     * Normalize an associative array to contain only positive integer values.
     *
     * Filters out non-string keys, empty keys, and zero/negative values.
     *
     * @param array<string,mixed> $params  Raw parameter array.
     * @return array<string,int>  Cleaned array with only positive integers.
     */
    public function normalizeNumericParams(array $params): array
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $intValue = _absint($value);
            if ($intValue > 0) {
                $normalized[$key] = $intValue;
            }
        }
        return $normalized;
    }

    /**
     * Normalize a mixed value to boolean.
     *
     * Accepts bool, int (1 = true), and string ('1', 'true', 'yes', 'on').
     *
     * @param mixed $value  The value to normalize.
     * @return bool
     */
    public function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }

    public function normalizeOrderDirection(string $value): string
    {
        $value = strtolower(trim($value));
        return $value === 'desc' ? 'desc' : 'asc';
    }

    public function normalizeProjectStatus(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        return match ($status) {
            'active', 'production', 'prod' => 'active',
            'suspended', 'suspend', 'paused', 'pause' => 'suspended',
            'closed', 'close' => 'closed',
            default => 'development',
        };
    }

    protected function resolveShowChildCountColumns(ListContextParams $p): bool
    {
        if ($p->childCountColumn === 'hide') {
            return false;
        }
        if ($p->childCountColumn === 'show') {
            return true;
        }

        return !$p->useSingleEntryRootView;
    }

    /**
     * Normalize a heading size string to an allowed value.
     *
     * Accepts h2, h3, h4, h5. Defaults to 'h2' for invalid input.
     *
     * @param string $size  Raw heading size string.
     * @return string  Normalized heading size ('h2'–'h5').
     */
    public function normalizeHeadingSize(string $size): string
    {
        $normalized = strtolower(trim($size));
        if (in_array($normalized, ['h2', 'h3', 'h4', 'h5'], true)) {
            return $normalized;
        }
        return 'h2';
    }

    // ------------------------------------------------------------------
    // FK chain and root view helpers
    // ------------------------------------------------------------------

    /**
     * Find the first missing required FK chain field in the provided parameters.
     *
     * @param array              $context  Form context with chain field definitions.
     * @param array<string,int>  $params   FK chain parameters to validate.
     * @return string|null  The missing field name, or null if all are present.
     */
    protected function findMissingChainFieldInParams(array $context, array $params): ?string
    {
        foreach ($this->fkResolver->getChainFields($context) as $requiredFk) {
            $val = _absint($params[$requiredFk] ?? 0);
            if ($val <= 0) {
                return $requiredFk;
            }
        }
        return null;
    }

    /**
     * Get the root form name from the context's ancestor chain.
     *
     * @param array $context  Form context.
     * @return string  Root form name, or empty string if not determinable.
     */
    protected function getRootFormName(array $context): string
    {
        $ancestorFormNames = is_array($context['ancestor_form_names'] ?? null) ? $context['ancestor_form_names'] : [];
        if (!empty($ancestorFormNames)) {
            return (string) ($ancestorFormNames[0] ?? '');
        }
        return (bool) ($context['is_root'] ?? false) ? (string) ($context['form_name'] ?? '') : '';
    }

    /**
     * Resolve the view action for the root form in the ancestor chain.
     *
     * @param array $context  Form context.
     * @return string  Root view action slug, or empty string if unavailable.
     */
    protected function resolveRootViewAction(array $context): string
    {
        $rootFormName = $this->getRootFormName($context);
        if ($rootFormName === '') {
            return '';
        }
        $rootListAction = ProjectNaming::toActionSlug($rootFormName) . '-list';
        $rootContext = $this->registry->get($rootListAction);
        if (!is_array($rootContext)) {
            return '';
        }
        return (string) ($rootContext['view_action'] ?? '');
    }

    /**
     * Determine if this is a first-level child form managed by the root's view action.
     *
     * @param array $context  Form context.
     * @return bool  True if this form's list should redirect to root view.
     */
    protected function isFirstLevelManagedByRootView(array $context): bool
    {
        $ancestorFormNames = is_array($context['ancestor_form_names'] ?? null) ? $context['ancestor_form_names'] : [];
        return count($ancestorFormNames) === 1 && $this->resolveRootViewAction($context) !== '';
    }

    /**
     * Resolve the root view target action and params for redirect.
     *
     * @param array              $context         Form context.
     * @param array<string,int>  $fkChainParams   FK chain parameters.
     * @param int                $requestedRootId Requested root record ID.
     * @param int                $parentId        Direct parent record ID.
     * @param array<string,int|float|string> $urlFilterParams URL params to preserve in redirect.
     * @return array{action:string,params:array}|null  Target action info, or null if not resolvable.
     */
    protected function resolveRootViewTarget(
        array $context,
        array $fkChainParams,
        int $requestedRootId,
        int $parentId,
        array $urlFilterParams = []
    ): ?array {
        $rootViewAction = $this->resolveRootViewAction($context);
        if ($rootViewAction === '') {
            return null;
        }

        $rootFkField = $this->fkResolver->getRootFkField($context);
        $rootId = $requestedRootId > 0 ? $requestedRootId : _absint($fkChainParams[$rootFkField] ?? 0);
        if ($rootId <= 0 && $rootFkField !== '' && $rootFkField === (string) ($context['parent_fk_field'] ?? '')) {
            $rootId = $parentId;
        }
        if ($rootId <= 0) {
            return null;
        }

        return [
            'action' => $rootViewAction,
            'params' => array_merge(['id' => $rootId], $urlFilterParams),
        ];
    }
}
