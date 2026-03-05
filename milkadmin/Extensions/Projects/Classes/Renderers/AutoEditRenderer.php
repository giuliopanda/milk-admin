<?php
namespace Extensions\Projects\Classes\Renderers;

use App\Abstracts\AbstractModule;
use App\Response;
use App\Route;
use Builders\FormBuilder;
use Extensions\Projects\Classes\{ProjectJsonStore, ProjectNaming};
use Extensions\Projects\Classes\SearchUrlParamsResolver;
use Extensions\Projects\Classes\View\{ViewPageRenderer, ViewSchema};
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
 * Renders the auto-generated edit page for a manifest form.
 */
class AutoEditRenderer
{
    protected AbstractModule $module;
    protected ActionContextRegistry $registry;
    protected FkChainResolver $fkResolver;
    protected BreadcrumbManager $breadcrumbManager;
    protected ShowIfEvaluator $showIfEvaluator;
    protected ?ViewSchema $viewSchema = null;
    protected ?ViewPageRenderer $viewPageRenderer = null;

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
    }

    public function setViewSchema(ViewSchema $schema): void
    {
        $this->viewSchema = $schema;
    }

    /**
     * Build and return the auto-configured FormBuilder for current edit action.
     *
     * Returns null when context is invalid, required FK chain is missing, or a
     * redirect/error flow is required.
     */
    public function buildFormBuilder(array $options = []): ?FormBuilder
    {
        $state = $this->prepareFormBuilderState();
        if (!empty($state['error'])) {
            return null;
        }
        if (!empty($state['redirect'])) {
            return null;
        }

        $formBuilder = $state['form_builder'] ?? null;
        if (!$formBuilder instanceof FormBuilder) {
            return null;
        }

        $withStandardActions = (bool) ($options['with_standard_actions'] ?? false);
        if ($withStandardActions) {
            $includeDelete = array_key_exists('include_delete', $options)
                ? (bool) $options['include_delete']
                : true;
            $includeDelete = $includeDelete && $this->canDeleteForContext(
                is_array($state['context'] ?? null) ? $state['context'] : []
            );
            $cancelLink = $options['cancel_link'] ?? ($state['success_url'] ?? null);
            if (!is_string($cancelLink) || trim($cancelLink) === '') {
                $cancelLink = null;
            }
            $this->applyStandardActions($formBuilder, $includeDelete, $cancelLink);
        }

        return $formBuilder;
    }

    public function render(): void
    {
        // Verifica se c'è una richiesta restore
        if (isset($_REQUEST['restore']) && $_REQUEST['restore'] == '1') {
            // Processa il restore
            $this->processRestoreFromEdit();
            return;
        }
        
        // Verifica se c'è una richiesta hard delete
        if (isset($_REQUEST['hard_delete']) && $_REQUEST['hard_delete'] == '1') {
            // Processa l'hard delete
            $this->processHardDeleteFromEdit();
            return;
        }
        
        $state = $this->prepareFormBuilderState();
      
        if (!empty($state['error'])) {
            $this->renderErrorResponse((string) $state['error']);
            return;
        }

        $redirect = (string) ($state['redirect'] ?? '');
        if ($redirect !== '') {
            Route::redirect($redirect);
            return;
        }

        $context = is_array($state['context'] ?? null) ? $state['context'] : [];
        if (!$this->isDataMutationAllowedForContext($context)) {
            $this->renderErrorResponse($this->buildDataMutationBlockedMessage($context, 'edit'));
            return;
        }
        
        // verifica se ha i permessi dell'edit
        $allowEdit =  ($context['allow_edit'] == true);
        if (!$allowEdit) {
            $this->renderErrorResponse('You do not have permission to edit this record');
            return;
        }
     
        $modulePage = (string) ($state['module_page'] ?? $this->module->getPage());
        $requestedReloadListId = (string) ($state['requested_reload_list_id'] ?? '');
        $id = _absint($state['id'] ?? 0);
        $rootId = _absint($state['root_id'] ?? 0);
        $editDisplay = (string) ($state['edit_display'] ?? 'page');
        $modelName = (string) ($state['model_name'] ?? 'Model');
        $modelTitle = (string) ($state['model_title'] ?? $modelName);
        $isRoot = (bool) ($state['is_root'] ?? false);
        $maxRecords = (string) ($state['max_records'] ?? 'n');
        $hasChildren = (bool) ($state['has_children'] ?? false);
        $successUrl = (string) ($state['success_url'] ?? '?page=' . $modulePage);
        $formBuilder = $state['form_builder'] ?? null;
        if (!$formBuilder instanceof FormBuilder) {
            $this->renderErrorResponse('Failed to initialize form builder.');
            return;
        }

        $response = $this->module->getCommonData();
        $response['title'] = ($id > 0 ? 'Edit ' : 'New ') . $modelTitle;
        $response['title_btns'] = [];
        $includeDeleteAction = $this->canDeleteForContext($context);

        if ($editDisplay !== 'page' && Response::isJson()) {
            $formBuilder
                ->activeFetch()
                ->setTitle('New ' . $modelTitle, 'Edit ' . $modelTitle);

            $this->applyStandardActions($formBuilder, $includeDeleteAction, $successUrl);

            $reloadTableId = $requestedReloadListId !== ''
                ? $requestedReloadListId
                : $this->resolveAutomaticReloadTableId(
                    $context,
                    $modelName,
                    $isRoot,
                    $maxRecords,
                    $hasChildren,
                    $rootId
                );

            if ($reloadTableId !== '') {
                $formBuilder->dataListId($reloadTableId);
            }

            if ($editDisplay === 'offcanvas') {
                $formBuilder->asOffcanvas();
            } else {
                $formBuilder->asModal()->size('xl');
            }

            $responseJson = $formBuilder->getResponse();
            $responseJson = $this->applyViewLayoutReloadResponse($responseJson, $context, $id, $rootId, $requestedReloadListId);
            Response::json($responseJson);
            return;
        }

        $this->breadcrumbManager->apply($context, $modulePage, 'edit', $id, $rootId);

        $this->applyStandardActions($formBuilder, $includeDeleteAction, $successUrl);
        $response['form'] = $formBuilder->getForm();

        SelectedMenuSidebarHelper::applyToResponse($response, $this->module);
        Response::render(MILK_DIR . '/Theme/SharedViews/edit_page.php', $response);
    }

    /**
     * @return array{
     *   error:string,
     *   redirect:string,
     *   context?:array,
     *   module_page?:string,
     *   requested_reload_list_id?:string,
     *   id?:int,
     *   root_id?:int,
     *   edit_display?:string,
     *   model_name?:string,
     *   model_title?:string,
     *   is_root?:bool,
     *   max_records?:string,
     *   has_children?:bool,
     *   success_url?:string,
     *   form_builder?:FormBuilder
     * }
     */
    protected function prepareFormBuilderState(): array
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null) {
            return ['error' => 'No form context available for this action.', 'redirect' => ''];
        }
        if (!empty($context['error'])) {
            return ['error' => (string) $context['error'], 'redirect' => ''];
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            return ['error' => 'Model class not found for this form.', 'redirect' => ''];
        }

        $model = new $modelClass();
        $modelName = (string) ($context['form_name'] ?? 'Model');
        $modelTitle = trim((string) ($context['form_title'] ?? ''));
        if ($modelTitle === '') {
            $modelTitle = ProjectNaming::toTitle($modelName);
        }
        if ($modelTitle === '') {
            $modelTitle = $modelName;
        }

        $listAction = (string) ($context['list_action'] ?? '');
        $editAction = (string) ($context['edit_action'] ?? '');
        $modulePage = $this->module->getPage();
        $requestedReloadListId = UrlBuilder::getRequestedReloadListId();
        $primaryKey = (string) $model->getPrimaryKey();
        $postedData = is_array($_REQUEST['data'] ?? null) ? $_REQUEST['data'] : [];
        $postedPrimaryId = $primaryKey !== '' ? _absint($postedData[$primaryKey] ?? 0) : 0;
        $requestPrimaryId = _absint($_REQUEST['id'] ?? 0);
        $isPostRequest = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
        if ($isPostRequest && $postedPrimaryId > 0) {
            $id = $postedPrimaryId;
        } else {
            $id = $requestPrimaryId > 0 ? $requestPrimaryId : $postedPrimaryId;
        }
        $editDisplay = DisplayModeHelper::getEditMode($context);
        $searchFiltersConfig = is_array($context['search_filters'] ?? null) ? $context['search_filters'] : [];
        $resolvedUrlFilters = SearchUrlParamsResolver::resolveFromConfig($searchFiltersConfig);
        $urlParamValues = is_array($resolvedUrlFilters['params'] ?? null) ? $resolvedUrlFilters['params'] : [];

        $isRoot = (bool) ($context['is_root'] ?? false);
        $maxRecords = (string) ($context['max_records'] ?? 'n');
        $fkField = (string) ($context['parent_fk_field'] ?? '');
        $parentId = 0;
        $rootIdField = ProjectNaming::rootIdField();
        $rootId = 0;

        if (!$isRoot) {
            if ($fkField === '') {
                return ['error' => 'Missing parent FK convention for this form.', 'redirect' => ''];
            }

            $rules = $model->getRules();
            if (!isset($rules[$fkField])) {
                return [
                    'error' => "FK field '{$fkField}' is missing in model rules. Cannot edit child records without a link to its parent form.",
                    'redirect' => '',
                ];
            }
            if (!isset($rules[$rootIdField])) {
                return [
                    'error' => "Root field '{$rootIdField}' is missing in model rules. Cannot edit child records without closure root context.",
                    'redirect' => '',
                ];
            }

            $requestedParentId = _absint($_REQUEST[$fkField] ?? 0);
            $requestedRootId = $this->fkResolver->getRootIdFromRequest($context);
            $chainRootError = $this->fkResolver->validateAncestorRootConsistency($context, $requestedRootId);
            if ($chainRootError !== null) {
                return ['error' => $chainRootError, 'redirect' => ''];
            }

            // New records require the full chain.
            if ($id <= 0) {
                $missingFk = $this->fkResolver->findMissingChainField($context);
                if ($missingFk !== null) {
                    return ['error' => "Missing parent id. Provide '{$missingFk}' in the URL query string.", 'redirect' => ''];
                }
                $parentId = $requestedParentId;
                $rootId = $requestedRootId;
            } else {
                $existing = $model->getByIdForEdit($id);
                $recordParentId = 0;
                $recordRootId = 0;
                if (is_object($existing)) {
                    $recordParentId = _absint($existing->$fkField ?? 0);
                    $recordRootId = _absint($existing->$rootIdField ?? 0);
                } elseif (is_array($existing)) {
                    $recordParentId = _absint($existing[$fkField] ?? 0);
                    $recordRootId = _absint($existing[$rootIdField] ?? 0);
                }

                if ($recordParentId <= 0) {
                    return [
                        'error' => "Record #{$id} has no '{$fkField}' value. Cannot edit this child record without a valid parent link.",
                        'redirect' => '',
                    ];
                }
                if ($requestedParentId > 0 && $requestedParentId !== $recordParentId) {
                    return [
                        'error' => "Invalid parent id. URL '{$fkField}={$requestedParentId}' does not match record '{$fkField}={$recordParentId}'.",
                        'redirect' => '',
                    ];
                }
                if ($requestedRootId > 0 && $recordRootId > 0 && $requestedRootId !== $recordRootId) {
                    return [
                        'error' => "Invalid root id. URL chain root id '{$requestedRootId}' does not match record '{$rootIdField}={$recordRootId}'.",
                        'redirect' => '',
                    ];
                }

                $parentId = $requestedParentId > 0 ? $requestedParentId : $recordParentId;
                $rootId = $requestedRootId > 0 ? $requestedRootId : $recordRootId;
            }

            // First-level children: root id and parent id are the same value.
            if ($rootId <= 0 && $this->fkResolver->getRootFkField($context) === $fkField) {
                $rootId = $parentId;
            }

            $showIfError = $this->showIfEvaluator->validate($context, $parentId, $rootId);
            if ($showIfError !== null) {
                return ['error' => $showIfError, 'redirect' => ''];
            }

            // Enforce max_records for new records.
            if ($id <= 0 && $parentId > 0) {
                $finiteMaxRecords = UrlBuilder::getFiniteMaxRecords($maxRecords);
                if ($finiteMaxRecords > 0) {
                    $existingCount = ModelRecordHelper::countByFk($model, $fkField, $parentId);
                    if ($existingCount >= $finiteMaxRecords) {
                        if ($finiteMaxRecords === 1) {
                            $existingId = ModelRecordHelper::findFirstIdByFk($model, $fkField, $parentId);
                            if ($existingId > 0) {
                                $params = array_merge($this->fkResolver->getChainParams($context), $urlParamValues);
                                $params['id'] = $existingId;
                                return [
                                    'error' => '',
                                    'redirect' => UrlBuilder::action($modulePage, $editAction, $params),
                                ];
                            }
                        }
                        return [
                            'error' => "Maximum {$finiteMaxRecords} records reached for this parent context.",
                            'redirect' => '',
                        ];
                    }
                }
            }
        }
        // Enforce max_records for root forms on new records.
        if ($isRoot && $id <= 0) {
            $finiteMaxRecords = UrlBuilder::getFiniteMaxRecords($maxRecords);
            if ($finiteMaxRecords > 0) {
                $existingCount = ModelRecordHelper::countAll($model);
                if ($existingCount >= $finiteMaxRecords) {
                    if ($finiteMaxRecords === 1 && $editAction !== '') {
                        $existingId = ModelRecordHelper::findFirstId($model);
                        if ($existingId > 0) {
                            $params = $urlParamValues;
                            $params['id'] = $existingId;
                            return [
                                'error' => '',
                                'redirect' => UrlBuilder::action($modulePage, $editAction, $params),
                            ];
                        }
                    }
                    return [
                        'error' => "Maximum {$finiteMaxRecords} records reached for this form.",
                        'redirect' => '',
                    ];
                }
            }
        }

        // Success URL determination.
        $chainParams = array_merge($this->fkResolver->getChainParams($context), $urlParamValues);
        $hasChildren = !empty($context['children_meta_by_alias'] ?? []);
        $autoSuccess = $this->resolveAutomaticSuccessTarget(
            $context,
            $listAction,
            $chainParams,
            $rootId,
            $isRoot,
            $maxRecords,
            $hasChildren,
            $primaryKey
        );
        $successAction = (string) ($autoSuccess['action'] ?? $listAction);
        $successParams = is_array($autoSuccess['params'] ?? null) ? $autoSuccess['params'] : $chainParams;

        $successUrl = UrlBuilder::actionPreservePlaceholders($modulePage, $successAction, $successParams);
        $errorParams = array_merge(['id' => '%' . $primaryKey . '%'], $chainParams);
        $errorUrl = UrlBuilder::actionPreservePlaceholders($modulePage, $editAction, $errorParams);

        $formBuilder = FormBuilder::create($model, $modulePage, $successUrl, $errorUrl);
        $formBuilder->customData('root_id', $rootId);
        if ($requestedReloadListId !== '') {
            $formBuilder->customData(UrlBuilder::reloadListIdParamKey(), $requestedReloadListId);
        }
        foreach ($urlParamValues as $paramName => $paramValue) {
            $formBuilder->customData((string) $paramName, $paramValue);
        }

        if (!$isRoot) {
            foreach ($this->fkResolver->getChainParams($context) as $k => $v) {
                $formBuilder->customData((string) $k, $v);
            }

            $formBuilder->field($fkField)->value($parentId);
            if ($rootId > 0) {
                $formBuilder->field($rootIdField)->value($rootId);
            }
        }

        // Keep Projects FormBuilder extension active for all forms (root + child).
        // This allows extension-driven field rendering enrichments (e.g. download links).
        $formBuilder->extensions([
            'Projects' => [
                'fkField' => $isRoot ? '' : $fkField,
                'maxRecords' => $maxRecords,
                'rootField' => $rootIdField,
                'rootId' => $rootId,
            ],
        ]);

        $this->applySchemaContainers($formBuilder, $model, $context);

        return [
            'error' => '',
            'redirect' => '',
            'context' => $context,
            'module_page' => $modulePage,
            'requested_reload_list_id' => $requestedReloadListId,
            'id' => $id,
            'root_id' => $rootId,
            'edit_display' => $editDisplay,
            'model_name' => $modelName,
            'model_title' => $modelTitle,
            'is_root' => $isRoot,
            'max_records' => $maxRecords,
            'has_children' => $hasChildren,
            'success_url' => $successUrl,
            'form_builder' => $formBuilder,
        ];
    }

    protected function applyStandardActions(FormBuilder $formBuilder, bool $includeDelete, ?string $cancelLink): void
    {
        $formBuilder->addStandardActions($includeDelete, $cancelLink);
        
        $model = $formBuilder->getModel();
        if (!is_object($model) || !method_exists($model, 'getPrimaryKey')) {
            return;
        }
        $primaryKey = (string) $model->getPrimaryKey();
        if ($primaryKey === '') {
            return;
        }

        $context = $this->registry->resolveForCurrentRequest();
        if (!is_array($context)) {
            return;
        }

        // Check if current record is soft-deleted
        $isRecordSoftDeleted = $this->isCurrentRecordSoftDeleted($context);
        
        // Get configuration values
        $softDeleteEnabled = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($context, ['soft_delete', 'softDelete'], false)
        );
        $allowDeleteRecord = true;
        if (ProjectJsonStore::hasAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'])) {
            $allowDeleteRecord = ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'], false)
            );
        }

        // Add actions based on record state and configuration
        $actions = [];

        if ($isRecordSoftDeleted && $softDeleteEnabled) {
            // Record is already soft-deleted: show restore button
            $actions['restore'] = [
                'label' => 'Restore',
                'class' => 'btn btn-warning ms-2',
                'action' => function(FormBuilder $builder, array $request): array {
                    $context = $this->registry->resolveForCurrentRequest();
                    if (!is_array($context)) {
                        \App\MessagesHandler::addError('No form context available for restore action.');
                        return ['success' => false, 'message' => ''];
                    }

                    $projectsExtension = $this->module->getLoadedExtensions('Projects');
                    if (!is_object($projectsExtension) || !method_exists($projectsExtension, 'restore')) {
                        \App\MessagesHandler::addError('Projects restore handler is not available.');
                        return ['success' => false, 'message' => ''];
                    }

                    $request = is_array($request) ? $request : [];
                    $model = $builder->getModel();
                    if (is_object($model) && method_exists($model, 'getPrimaryKey')) {
                        $primaryKey = (string) $model->getPrimaryKey();
                        if ($primaryKey !== '' && !array_key_exists($primaryKey, $request)) {
                            $request[$primaryKey] = _absint($_REQUEST['id'] ?? 0);
                        }
                    }

                    // Use the restore method from ListPresenterHelper
                    $listHelper = new \Extensions\Projects\Classes\Renderers\ListPresenterHelper();
                    $ok = $listHelper->restoreRows(null, $request, $this->registry);
                    
                    // Reload model to reflect the restored state (no longer soft-deleted)
                    if ($ok) {
                        $model = $builder->getModel();
                        if (is_object($model) && method_exists($model, 'getPrimaryKey')) {
                            $primaryKey = (string) $model->getPrimaryKey();
                            $id = _absint($request[$primaryKey] ?? 0);
                            if ($id > 0) {
                                $reloadedModel = $model->getByIdForEdit($id);
                                if ($reloadedModel && !$reloadedModel->isEmpty()) {
                                    $builder->setData($reloadedModel);
                                }
                            }
                        }
                    }
                    
                    $isJsonResponse = \App\Response::isJson()
                        || strtolower(trim((string) ($_REQUEST['page-output'] ?? ''))) === 'json';
                    
                    // Force JSON response for modal/offcanvas modes
                    $editDisplay = DisplayModeHelper::getEditMode($context);
                    $forceJsonResponse = in_array($editDisplay, ['modal', 'offcanvas']);
                    $shouldReturnJson = $isJsonResponse || $forceJsonResponse;

                    if ($ok) {
                        $successMessage = trim(\App\MessagesHandler::successToString());
                        if ($successMessage === '') {
                            $successMessage = 'Restore successful';
                        }

                        $redirectSuccess = $this->resolveDeleteSuccessUrl($context, $builder);
                        if (!$shouldReturnJson && $redirectSuccess !== '') {
                            Route::redirectSuccess($redirectSuccess, $successMessage);
                        }

                        // Return success array to let FormBuilder set action_success
                        // The FormBuilder will then use getResponse() to build proper JSON
                        return ['success' => true, 'message' => ''];
                    }

                    $errorMessage = trim(\App\MessagesHandler::errorsToString());
                    if ($errorMessage === '') {
                        $errorMessage = 'Unable to restore item';
                    }

                    $redirectError = trim((string) ($builder->getUrlError() ?? ''));
                    if (!$shouldReturnJson && $redirectError !== '') {
                        Route::redirectError($redirectError, $errorMessage);
                    }

                    return ['success' => false, 'message' => $errorMessage];
                },
                'validate' => false,
                'confirm' => 'Are you sure you want to restore this item?',
                'showIf' => [$primaryKey, 'not_empty', 0],
            ];

            // Add hard delete button only if allowDeleteRecord is true
            if ($allowDeleteRecord) {
                $actions['hard_delete'] = [
                    'label' => 'Delete permanently',
                    'class' => 'btn btn-danger ms-2',
                    'action' => function(FormBuilder $builder, array $request): array {
                        $context = $this->registry->resolveForCurrentRequest();
                        if (!is_array($context)) {
                            \App\MessagesHandler::addError('No form context available for hard delete action.');
                            return ['success' => false, 'message' => ''];
                        }

                        $projectsExtension = $this->module->getLoadedExtensions('Projects');
                        if (!is_object($projectsExtension) || !method_exists($projectsExtension, 'hardDelete')) {
                            \App\MessagesHandler::addError('Projects hard delete handler is not available.');
                            return ['success' => false, 'message' => ''];
                        }

                        $request = is_array($request) ? $request : [];
                        $model = $builder->getModel();
                        if (is_object($model) && method_exists($model, 'getPrimaryKey')) {
                            $primaryKey = (string) $model->getPrimaryKey();
                            if ($primaryKey !== '' && !array_key_exists($primaryKey, $request)) {
                                $request[$primaryKey] = _absint($_REQUEST['id'] ?? 0);
                            }
                        }

                        // Use the hard delete method from ListPresenterHelper
                        $listHelper = new \Extensions\Projects\Classes\Renderers\ListPresenterHelper();
                        $ok = $listHelper->hardDeleteRows(null, $request, $this->registry, $this->fkResolver);
                        
                        $isJsonResponse = \App\Response::isJson()
                            || strtolower(trim((string) ($_REQUEST['page-output'] ?? ''))) === 'json';
                        
                        // Force JSON response for modal/offcanvas modes
                        $editDisplay = DisplayModeHelper::getEditMode($context);
                        $forceJsonResponse = in_array($editDisplay, ['modal', 'offcanvas']);
                        $shouldReturnJson = $isJsonResponse || $forceJsonResponse;

                        if ($ok) {
                            $successMessage = trim(\App\MessagesHandler::successToString());
                            if ($successMessage === '') {
                                $successMessage = 'Permanently deleted';
                            }

                            $redirectSuccess = $this->resolveDeleteSuccessUrl($context, $builder);
                            if (!$shouldReturnJson && $redirectSuccess !== '') {
                                Route::redirectSuccess($redirectSuccess, $successMessage);
                            }

                            // Return success array to let FormBuilder set action_success
                            return ['success' => true, 'message' => ''];
                        }

                        $errorMessage = trim(\App\MessagesHandler::errorsToString());
                        if ($errorMessage === '') {
                            $errorMessage = 'Unable to permanently delete item';
                        }

                        $redirectError = trim((string) ($builder->getUrlError() ?? ''));
                        if (!$shouldReturnJson && $redirectError !== '') {
                            Route::redirectError($redirectError, $errorMessage);
                        }

                        return ['success' => false, 'message' => $errorMessage];
                    },
                    'validate' => false,
                    'confirm' => 'Are you sure you want to permanently delete this item? This action cannot be undone.',
                    'showIf' => [$primaryKey, 'not_empty', 0],
                ];
            }
        } elseif ($includeDelete && !$isRecordSoftDeleted) {
            // Record is not deleted and delete is allowed: show normal delete button
            $actions['delete'] = [
                'label' => 'Delete',
                'class' => 'btn btn-danger ms-2',
                'action' => function(FormBuilder $builder, array $request): array {
                    $context = $this->registry->resolveForCurrentRequest();
                    if (!is_array($context)) {
                        \App\MessagesHandler::addError('No form context available for delete action.');
                        return ['success' => false, 'message' => ''];
                    }

                    if ($this->isDeleteDisabledByConfig($context)) {
                        \App\MessagesHandler::addError('You cannot remove this record: deletion is disabled in the form configuration.');
                        return ['success' => false, 'message' => ''];
                    }

                    $projectsExtension = $this->module->getLoadedExtensions('Projects');
                    if (!is_object($projectsExtension) || !method_exists($projectsExtension, 'delete')) {
                        \App\MessagesHandler::addError('Projects delete handler is not available.');
                        return ['success' => false, 'message' => ''];
                    }

                    $request = is_array($request) ? $request : [];
                    $model = $builder->getModel();
                    if (is_object($model) && method_exists($model, 'getPrimaryKey')) {
                        $primaryKey = (string) $model->getPrimaryKey();
                        if ($primaryKey !== '' && !array_key_exists($primaryKey, $request)) {
                            $request[$primaryKey] = _absint($_REQUEST['id'] ?? 0);
                        }
                    }

                    $ok = (bool) $projectsExtension->delete(null, $request);
                    
                    // Reload model to reflect the deleted state (now soft-deleted)
                    if ($ok) {
                        $model = $builder->getModel();
                        if (is_object($model) && method_exists($model, 'getPrimaryKey')) {
                            $primaryKey = (string) $model->getPrimaryKey();
                            $id = _absint($request[$primaryKey] ?? 0);
                            if ($id > 0) {
                                $reloadedModel = $model->getByIdForEdit($id);
                                if ($reloadedModel && !$reloadedModel->isEmpty()) {
                                    $builder->setData($reloadedModel);
                                }
                            }
                        }
                    }
                    
                    $isJsonResponse = \App\Response::isJson()
                        || strtolower(trim((string) ($_REQUEST['page-output'] ?? ''))) === 'json';
                    
                    // Force JSON response for modal/offcanvas modes
                    $editDisplay = DisplayModeHelper::getEditMode($context);
                    $forceJsonResponse = in_array($editDisplay, ['modal', 'offcanvas']);
                    $shouldReturnJson = $isJsonResponse || $forceJsonResponse;

                    if ($ok) {
                        $successMessage = trim(\App\MessagesHandler::successToString());
                        if ($successMessage === '') {
                            $successMessage = 'Delete successful';
                        }

                        $redirectSuccess = $this->resolveDeleteSuccessUrl($context, $builder);
                        if (!$shouldReturnJson && $redirectSuccess !== '') {
                            Route::redirectSuccess($redirectSuccess, $successMessage);
                        }

                        // Return success array to let FormBuilder set action_success
                        return ['success' => true, 'message' => ''];
                    }

                    $errorMessage = trim(\App\MessagesHandler::errorsToString());
                    if ($errorMessage === '') {
                        $errorMessage = 'Unable to delete item';
                    }

                    $redirectError = trim((string) ($builder->getUrlError() ?? ''));
                    if (!$shouldReturnJson && $redirectError !== '') {
                        Route::redirectError($redirectError, $errorMessage);
                    }

                    return ['success' => false, 'message' => $errorMessage];
                },
                'validate' => false,
                'confirm' => $softDeleteEnabled
                    ? 'Are you sure you want to move this item to trash?'
                    : 'Are you sure you want to delete this item?',
                'showIf' => [$primaryKey, 'not_empty', 0],
            ];
        }

        if (!empty($actions)) {
            $formBuilder->addActions($actions);
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function isDeleteDisabledByConfig(array $context): bool
    {
        $softDeleteEnabled = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($context, ['soft_delete', 'softDelete'], false)
        );

        $allowDeleteRecord = true;
        if (ProjectJsonStore::hasAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'])) {
            $allowDeleteRecord = ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'], false)
            );
        }

        return !$softDeleteEnabled && !$allowDeleteRecord;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function canDeleteForContext(array $context): bool
    {
        if (empty($context)) {
            return false;
        }
        if ($this->isDeleteDisabledByConfig($context)) {
            return false;
        }

        // Check if record is already soft-deleted and handle accordingly
        $isRecordSoftDeleted = $this->isCurrentRecordSoftDeleted($context);
        if ($isRecordSoftDeleted) {
            // For soft-deleted records, delete button should be hidden
            // Only restore and potentially hard delete should be shown
            return false;
        }

        if (ProjectJsonStore::hasAliasedKey($context, ['can_manage_delete_records', 'canManageDeleteRecords'])) {
            return ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($context, ['can_manage_delete_records', 'canManageDeleteRecords'], false)
            );
        }

        if (ProjectJsonStore::hasAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'])) {
            return ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'], false)
            );
        }

        return true;
    }

    /**
     * Process restore request from edit page
     */
    protected function processRestoreFromEdit(): void
    {
        $context = $this->registry->resolveForCurrentRequest();
        if (!is_array($context)) {
            \App\MessagesHandler::addError('No form context available for restore action.');
            $this->redirectToList();
            return;
        }
        if (!$this->isDataMutationAllowedForContext($context)) {
            \App\MessagesHandler::addError($this->buildDataMutationBlockedMessage($context, 'restore'));
            $this->redirectToList();
            return;
        }

        // Check if we should return JSON (offcanvas/modal modes)
        $editDisplay = DisplayModeHelper::getEditMode($context);
        $isJsonResponse = \App\Response::isJson()
            || strtolower(trim((string) ($_REQUEST['page-output'] ?? ''))) === 'json';
        $forceJsonResponse = in_array($editDisplay, ['modal', 'offcanvas']);
        $shouldReturnJson = $isJsonResponse || $forceJsonResponse;

        // Prepara la richiesta per il restore
        $request = [];
        $data = $_REQUEST['data'] ?? [];
        if (is_array($data)) {
            $request = array_merge($request, $data);
        }
        
        // Aggiunge l'ID del record
        $id = _absint($_REQUEST['id'] ?? 0);
        if ($id > 0) {
            $modelClass = (string) ($context['model_class'] ?? '');
            if ($modelClass !== '' && class_exists($modelClass)) {
                $model = new $modelClass();
                $primaryKey = (string) $model->getPrimaryKey();
                if ($primaryKey !== '') {
                    $request[$primaryKey] = $id;
                }
            }
        }

        // Usa il ListPresenterHelper per fare il restore
        $listHelper = new \Extensions\Projects\Classes\Renderers\ListPresenterHelper();
        $ok = $listHelper->restoreRows(null, $request, $this->registry);

        // Note: restoreRows already adds success/error messages, so we don't add them here
        // If modal/offcanvas, return JSON instead of redirect
        if ($shouldReturnJson) {
            $this->renderJsonResponseAfterAction($context, $id, 'restore', $ok);
            return;
        }

        $this->redirectToList();
    }

    /**
     * Process hard delete request from edit page
     */
    protected function processHardDeleteFromEdit(): void
    {
        $context = $this->registry->resolveForCurrentRequest();
        if (!is_array($context)) {
            \App\MessagesHandler::addError('No form context available for hard delete action.');
            $this->redirectToList();
            return;
        }
        if (!$this->isDataMutationAllowedForContext($context)) {
            \App\MessagesHandler::addError($this->buildDataMutationBlockedMessage($context, 'hard delete'));
            $this->redirectToList();
            return;
        }

        // Check if we should return JSON (offcanvas/modal modes)
        $editDisplay = DisplayModeHelper::getEditMode($context);
        $isJsonResponse = \App\Response::isJson()
            || strtolower(trim((string) ($_REQUEST['page-output'] ?? ''))) === 'json';
        $forceJsonResponse = in_array($editDisplay, ['modal', 'offcanvas']);
        $shouldReturnJson = $isJsonResponse || $forceJsonResponse;

        // Verifica se allowDeleteRecord è true dal manifest
        $allowDeleteRecord = true;
        if (ProjectJsonStore::hasAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'])) {
            $allowDeleteRecord = ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'], false)
            );
        }

        if (!$allowDeleteRecord) {
            \App\MessagesHandler::addError('Hard delete is not allowed in this configuration.');
            if ($shouldReturnJson) {
                $id = _absint($_REQUEST['id'] ?? 0);
                $this->renderJsonResponseAfterAction($context, $id, 'hard_delete', false);
                return;
            }
            $this->redirectToList();
            return;
        }

        // Prepara la richiesta per l'hard delete
        $request = [];
        $data = $_REQUEST['data'] ?? [];
        if (is_array($data)) {
            $request = array_merge($request, $data);
        }
        
        // Aggiunge l'ID del record
        $id = _absint($_REQUEST['id'] ?? 0);
        if ($id > 0) {
            $modelClass = (string) ($context['model_class'] ?? '');
            if ($modelClass !== '' && class_exists($modelClass)) {
                $model = new $modelClass();
                $primaryKey = (string) $model->getPrimaryKey();
                if ($primaryKey !== '') {
                    $request[$primaryKey] = $id;
                }
            }
        }

        // Usa il ListPresenterHelper per fare l'hard delete
        $listHelper = new \Extensions\Projects\Classes\Renderers\ListPresenterHelper();
        $ok = $listHelper->hardDeleteRows(null, $request, $this->registry, $this->fkResolver);

        // Note: hardDeleteRows already adds success/error messages via RecordTreeDeleter
        // If modal/offcanvas, return JSON instead of redirect
        if ($shouldReturnJson) {
            $this->renderJsonResponseAfterAction($context, $id, 'hard_delete', $ok);
            return;
        }

        $this->redirectToList();
    }

    /**
     * Redirect back to list page
     */
    protected function redirectToList(): void
    {
        $page = $_REQUEST['page'] ?? '';
        $reloadListId = $_REQUEST['reload_list_id'] ?? '';
        
        $url = '?page=' . urlencode($page);
        $listAction = str_replace('-edit', '-list', $_REQUEST['action'] ?? '');
        if ($listAction !== '') {
            $url .= '&action=' . urlencode($listAction);
        }
        if ($reloadListId !== '') {
            $url .= '&reload_list_id=' . urlencode($reloadListId);
        }
        
        \App\Route::redirect($url);
    }

    /**
     * Check if the current record being edited is already soft-deleted
     *
     * @param array<string,mixed> $context
     */
    protected function isCurrentRecordSoftDeleted(array $context): bool
    {
        $id = _absint($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            return false; // New record, not deleted
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            return false;
        }

        try {
            $model = new $modelClass();
            $primaryKey = (string) $model->getPrimaryKey();
            if ($primaryKey === '') {
                return false;
            }

            // Get the record without soft delete filters
            $record = $model->getByIdForEdit($id);
            if (!$record) {
                return false;
            }

            // Check if deleted_at and deleted_by are both set
            $deletedAt = null;
            $deletedBy = null;

            if (is_object($record)) {
                $deletedAt = $record->deleted_at ?? null;
                $deletedBy = $record->deleted_by ?? null;
            } elseif (is_array($record)) {
                $deletedAt = $record['deleted_at'] ?? null;
                $deletedBy = $record['deleted_by'] ?? null;
            }

            // Record is considered soft-deleted if both fields are non-empty
            $hasDeletedAt = $this->hasNonEmptyDateValue($deletedAt);
            $hasDeletedBy = $this->hasNonEmptyDateValue($deletedBy);

            return $hasDeletedAt && $hasDeletedBy;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if a value represents a non-empty date value
     */
    protected function hasNonEmptyDateValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value !== '';
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return trim((string) $value) !== '';
            }
            return true;
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return trim((string) $value) !== '';
    }

    /**
     * Resolve the post-delete redirect URL for edit actions.
     * Always prefer returning to the list action for the current context.
     *
     * @param array<string,mixed> $context
     */
    protected function resolveDeleteSuccessUrl(array $context, FormBuilder $builder): string
    {
        $modulePage = trim((string) $this->module->getPage());
        $listAction = trim((string) ($context['list_action'] ?? ''));
        if ($modulePage !== '' && $listAction !== '') {
            $chainParams = $this->fkResolver->getChainParams($context);

            $searchFiltersConfig = is_array($context['search_filters'] ?? null) ? $context['search_filters'] : [];
            $resolvedUrlFilters = SearchUrlParamsResolver::resolveFromConfig($searchFiltersConfig);
            $urlParamValues = is_array($resolvedUrlFilters['params'] ?? null) ? $resolvedUrlFilters['params'] : [];

            $params = array_merge($chainParams, $urlParamValues);
            return UrlBuilder::action($modulePage, $listAction, $params);
        }

        return trim((string) ($builder->getUrlSuccess() ?? ''));
    }

    protected function applySchemaContainers(FormBuilder $formBuilder, object $model, array $context): void
    {
        $schemaPath = $this->resolveSchemaPathForContext($model, $context);
        if ($schemaPath === '') {
            return;
        }

        $schema = $this->loadSchemaFromPath($schemaPath);
        if (!is_array($schema)) {
            return;
        }

        $modelSection = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $rawContainers = is_array($modelSection['containers'] ?? null) ? $modelSection['containers'] : [];
        if (empty($rawContainers)) {
            return;
        }

        $existingFields = [];
        foreach (array_keys($formBuilder->getFields()) as $fieldName) {
            if (!is_string($fieldName) || $fieldName === '') {
                continue;
            }
            $existingFields[strtolower($fieldName)] = $fieldName;
        }

        $assignedFields = [];
        foreach ($rawContainers as $rawContainer) {
            if (!is_array($rawContainer)) {
                continue;
            }

            $id = trim((string) ($rawContainer['id'] ?? ''));
            if ($id === '' || preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $id) !== 1) {
                continue;
            }

            $containerFields = [];
            $seen = [];
            $rawFields = is_array($rawContainer['fields'] ?? null) ? $rawContainer['fields'] : [];
            foreach ($rawFields as $fieldEntry) {
                if (is_array($fieldEntry)) {
                    $group = [];
                    foreach ($fieldEntry as $groupEntry) {
                        if (is_array($groupEntry)) {
                            continue;
                        }
                        $fieldName = trim((string) $groupEntry);
                        if ($fieldName === '') {
                            continue;
                        }
                        $fieldLower = strtolower($fieldName);
                        if (isset($seen[$fieldLower]) || isset($assignedFields[$fieldLower])) {
                            continue;
                        }
                        if (!isset($existingFields[$fieldLower])) {
                            continue;
                        }
                        $seen[$fieldLower] = true;
                        $assignedFields[$fieldLower] = true;
                        $group[] = $existingFields[$fieldLower];
                    }

                    if (count($group) === 1) {
                        $containerFields[] = $group[0];
                    } elseif (!empty($group)) {
                        $containerFields[] = $group;
                    }
                    continue;
                }

                $fieldName = trim((string) $fieldEntry);
                if ($fieldName === '') {
                    continue;
                }
                $fieldLower = strtolower($fieldName);
                if (isset($seen[$fieldLower]) || isset($assignedFields[$fieldLower])) {
                    continue;
                }
                if (!isset($existingFields[$fieldLower])) {
                    continue;
                }
                $seen[$fieldLower] = true;
                $assignedFields[$fieldLower] = true;
                $containerFields[] = $existingFields[$fieldLower];
            }

            if (empty($containerFields)) {
                continue;
            }

            $positionBefore = trim((string) ($rawContainer['position_before'] ?? ($rawContainer['positionBefore'] ?? '')));
            if ($positionBefore !== '' && !isset($existingFields[strtolower($positionBefore)])) {
                $positionBefore = '';
            } elseif ($positionBefore !== '') {
                $positionBefore = $existingFields[strtolower($positionBefore)];
            }

            $title = trim((string) ($rawContainer['title'] ?? ''));
            $cols = $this->normalizeContainerColsForRender($rawContainer['cols'] ?? count($containerFields), count($containerFields));
            $attributes = $this->normalizeContainerAttributesForRender($rawContainer['attributes'] ?? []);

            try {
                $formBuilder->addContainer($id, $containerFields, $cols, $positionBefore, $title, $attributes);
            } catch (\Throwable) {
                // Ignore invalid container definitions and keep default field rendering.
                continue;
            }
        }
    }

    protected function loadSchemaFromPath(string $schemaPath): array
    {
        $projectDir = dirname($schemaPath);
        $schemaName = pathinfo($schemaPath, PATHINFO_FILENAME);
        if ($projectDir === '' || $schemaName === '') {
            return [];
        }

        $store = ProjectJsonStore::for($projectDir);
        $schema = $store->schema($schemaName);
        return is_array($schema) ? $schema : [];
    }

    protected function resolveSchemaPathForContext(object $model, array $context): string
    {
        $formName = trim((string) ($context['form_name'] ?? ''));
        if ($formName === '') {
            return '';
        }

        try {
            $reflection = new \ReflectionClass($model);
            $modelFilePath = (string) $reflection->getFileName();
        } catch (\Throwable) {
            return '';
        }

        if ($modelFilePath === '') {
            return '';
        }

        $moduleDir = $this->resolveModuleDirFromPath($modelFilePath);
        if ($moduleDir === '') {
            return '';
        }

        $jsonNames = [$formName . '.json'];
        $studlyFormName = ProjectNaming::toStudlyCase($formName);
        if ($studlyFormName !== '' && strcasecmp($studlyFormName, $formName) !== 0) {
            $jsonNames[] = $studlyFormName . '.json';
        }
        $jsonNames = array_values(array_unique($jsonNames));

        $folders = ['Project', 'Projects'];
        foreach ($folders as $folder) {
            foreach ($jsonNames as $jsonName) {
                $path = rtrim($moduleDir, '/\\') . '/' . $folder . '/' . $jsonName;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return '';
    }

    protected function resolveModuleDirFromPath(string $modelFilePath): string
    {
        $normalized = str_replace('\\', '/', $modelFilePath);
        if (preg_match('~^(.*?/Modules/[^/]+)(?:/.*)?$~', $normalized, $matches) === 1) {
            return (string) ($matches[1] ?? '');
        }
        return dirname($modelFilePath);
    }

    /**
     * @return int|array<int,int>
     */
    protected function normalizeContainerColsForRender(mixed $value, int $fieldCount): int|array
    {
        $fieldCount = $fieldCount > 0 ? $fieldCount : 1;

        if (is_array($value)) {
            $cols = [];
            foreach ($value as $colRaw) {
                if (!is_numeric($colRaw)) {
                    continue;
                }
                $col = (int) $colRaw;
                if ($col < 1) {
                    continue;
                }
                $cols[] = min(12, $col);
            }
            if (!empty($cols)) {
                return $cols;
            }
        }

        $cols = is_numeric($value) ? (int) $value : $fieldCount;
        if ($cols < 1) {
            $cols = $fieldCount;
        }

        return min(12, $cols);
    }

    /**
     * @return array<string,mixed>
     */
    protected function normalizeContainerAttributesForRender(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $attributes = [];
        foreach ($value as $key => $attrValue) {
            $name = trim((string) $key);
            if ($name === '') {
                continue;
            }
            if (
                is_string($attrValue)
                || is_int($attrValue)
                || is_float($attrValue)
                || is_bool($attrValue)
            ) {
                $attributes[$name] = $attrValue;
            }
        }

        return $attributes;
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string,int> $chainParams
     * @return array{action:string,params:array}
     */
    protected function resolveAutomaticSuccessTarget(
        array $context,
        string $listAction,
        array $chainParams,
        int $rootId,
        bool $isRoot,
        string $maxRecords,
        bool $hasChildren,
        string $primaryKey
    ): array {
        // Root forms with view action enabled should go back to the single-record view after save.
        $rootViewAction = (string) ($context['view_action'] ?? '');
        if ($isRoot && $rootViewAction !== '' && $primaryKey !== '') {
            $params = ['id' => '%' . $primaryKey . '%'];
            return [
                'action' => $rootViewAction,
                'params' => $params,
            ];
        }

        if ($this->isFirstLevelManagedByRootView($context)) {
            $rootViewTarget = $this->resolveRootViewTarget($context, $chainParams, $rootId);
            if (is_array($rootViewTarget)) {
                return [
                    'action' => (string) ($rootViewTarget['action'] ?? $listAction),
                    'params' => is_array($rootViewTarget['params'] ?? null) ? $rootViewTarget['params'] : $chainParams,
                ];
            }
        }

        // Single-record leaf forms do not have a meaningful standalone list:
        // return to parent list (or root view if parent is managed there).
        if (!$isRoot && $maxRecords === '1' && !$hasChildren) {
            $parentFormName = (string) ($context['parent_form_name'] ?? '');
            if ($parentFormName !== '') {
                if ($this->isParentManagedByRootView($context, $parentFormName)) {
                    $rootViewTarget = $this->resolveRootViewTarget($context, $chainParams, $rootId);
                    if (is_array($rootViewTarget)) {
                        return [
                            'action' => (string) ($rootViewTarget['action'] ?? $listAction),
                            'params' => is_array($rootViewTarget['params'] ?? null) ? $rootViewTarget['params'] : $chainParams,
                        ];
                    }
                }

                return [
                    'action' => ProjectNaming::toActionSlug($parentFormName) . '-list',
                    'params' => $this->fkResolver->getChainParamsForParent($context),
                ];
            }
        }

        return [
            'action' => $listAction,
            'params' => $chainParams,
        ];
    }

    protected function resolveAutomaticReloadTableId(
        array $context,
        string $modelName,
        bool $isRoot,
        string $maxRecords,
        bool $hasChildren,
        int $rootId
    ): string {
        $formName = (string) ($context['form_name'] ?? '');
        if ($formName === '') {
            return '';
        }

        // First-level forms managed by root view must reload their embedded table.
        if ($this->isFirstLevelManagedByRootView($context)) {
            $rootFormName = $this->getRootFormName($context);
            if ($rootFormName !== '' && $rootId > 0) {
                return UrlBuilder::buildViewChildTableId($rootFormName, $formName, $rootId);
            }
        }

        // Regular forms reload their own list.
        if ($maxRecords !== '1' || $hasChildren || $isRoot) {
            return $this->buildDefaultTableId($modelName);
        }

        // Single-record leaf child reloads parent list.
        $parentFormName = (string) ($context['parent_form_name'] ?? '');
        if ($parentFormName === '') {
            return '';
        }

        return $this->resolveParentListTableId($context, $parentFormName, $rootId);
    }

    protected function resolveParentListTableId(array $context, string $parentFormName, int $rootId): string
    {
        $parentFormName = trim($parentFormName);
        if ($parentFormName === '') {
            return '';
        }

        if ($this->isParentManagedByRootView($context, $parentFormName)) {
            $rootFormName = $this->getRootFormName($context);
            if ($rootFormName !== '' && $rootId > 0) {
                return UrlBuilder::buildViewChildTableId($rootFormName, $parentFormName, $rootId);
            }
        }

        return $this->buildDefaultTableId($parentFormName);
    }

    protected function buildDefaultTableId(string $formName): string
    {
        return 'idTable' . preg_replace('/[^a-zA-Z0-9]/', '', $formName);
    }

    protected function getRootFormName(array $context): string
    {
        $ancestorFormNames = is_array($context['ancestor_form_names'] ?? null) ? $context['ancestor_form_names'] : [];
        if (!empty($ancestorFormNames)) {
            return (string) ($ancestorFormNames[0] ?? '');
        }
        return (bool) ($context['is_root'] ?? false) ? (string) ($context['form_name'] ?? '') : '';
    }

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

    protected function isFirstLevelManagedByRootView(array $context): bool
    {
        $ancestorFormNames = is_array($context['ancestor_form_names'] ?? null) ? $context['ancestor_form_names'] : [];
        return count($ancestorFormNames) === 1 && $this->resolveRootViewAction($context) !== '';
    }

    protected function isParentManagedByRootView(array $context, string $parentFormName): bool
    {
        if ($this->resolveRootViewAction($context) === '') {
            return false;
        }
        $ancestorFormNames = is_array($context['ancestor_form_names'] ?? null) ? $context['ancestor_form_names'] : [];
        if (count($ancestorFormNames) !== 2) {
            return false;
        }
        return (string) ($ancestorFormNames[1] ?? '') === $parentFormName;
    }

    /**
     * @param array<string,int> $chainParams
     * @return array{action:string,params:array}|null
     */
    protected function resolveRootViewTarget(array $context, array $chainParams, int $resolvedRootId): ?array
    {
        $rootViewAction = $this->resolveRootViewAction($context);
        if ($rootViewAction === '') {
            return null;
        }

        $rootFkField = $this->fkResolver->getRootFkField($context);
        $rootId = $resolvedRootId > 0 ? $resolvedRootId : _absint($chainParams[$rootFkField] ?? 0);
        if ($rootId <= 0) {
            return null;
        }

        return [
            'action' => $rootViewAction,
            'params' => ['id' => $rootId],
        ];
    }

    protected function applyViewLayoutReloadResponse(
        array $response,
        array $context,
        int $recordId,
        int $rootId,
        string $requestedReloadListId
    ): array
    {
        if ($this->viewSchema === null) {
            return $response;
        }
        if ($requestedReloadListId !== '') {
            return $response;
        }
        if (!(bool) ($response['success'] ?? false)) {
            return $response;
        }
        if (trim((string) ($response['executed_action'] ?? '')) === '') {
            return $response;
        }

        $rootFormName = $this->getRootFormName($context);
        if ($rootFormName === '') {
            return $response;
        }

        $rootRecordId = (bool) ($context['is_root'] ?? false) ? $recordId : $rootId;
        if ($rootRecordId <= 0) {
            return $response;
        }

        $rootListAction = ProjectNaming::toActionSlug($rootFormName) . '-list';
        $rootContext = $this->registry->get($rootListAction);
        if (!is_array($rootContext)) {
            return $response;
        }
        // View-layout partial reload is valid only when root single-record view is enabled.
        // If root has no view action (viewSingleRecord disabled), keep normal list reload flow.
        $rootViewAction = trim((string) ($rootContext['view_action'] ?? ''));
        if ($rootViewAction === '') {
            return $response;
        }

        $cardId = $this->resolveTargetCardIdForContext($context);
        if ($cardId === '') {
            return $response;
        }

        $cardHtml = $this->getViewPageRenderer()->renderSingleCard(
            $this->viewSchema,
            $cardId,
            $rootContext,
            $rootRecordId,
            $rootRecordId
        );
        if (!is_string($cardHtml) || $cardHtml === '') {
            return $response;
        }

        $containerId = ViewPageRenderer::buildCardContainerId($cardId, $rootRecordId);
        $response['element'] = [
            'selector' => '#' . $containerId,
            'innerHTML' => $cardHtml,
        ];
        unset($response['list']);

        return $response;
    }

    protected function resolveTargetCardIdForContext(array $context): string
    {
        if ($this->viewSchema === null) {
            return '';
        }

        $candidates = [];

        $formName = trim((string) ($context['form_name'] ?? ''));
        if ($formName !== '') {
            $candidates[] = $formName;
        }

        $parentFormName = trim((string) ($context['parent_form_name'] ?? ''));
        if ($parentFormName !== '') {
            $candidates[] = $parentFormName;
        }

        $ancestorFormNames = is_array($context['ancestor_form_names'] ?? null) ? $context['ancestor_form_names'] : [];
        $ancestorFormNames = array_reverse($ancestorFormNames);
        foreach ($ancestorFormNames as $ancestorFormName) {
            $name = trim((string) $ancestorFormName);
            if ($name !== '') {
                $candidates[] = $name;
            }
        }

        $seen = [];
        foreach ($candidates as $candidate) {
            if (isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;
            $cardId = $this->resolveCardIdForFormName($candidate);
            if ($cardId !== '') {
                return $cardId;
            }
        }

        return '';
    }

    protected function resolveCardIdForFormName(string $formName): string
    {
        if ($this->viewSchema === null || $formName === '') {
            return '';
        }

        foreach ($this->viewSchema->cards as $card) {
            if ($card->type === 'single-table' && $card->table !== null) {
                if ((string) $card->table->name === $formName) {
                    return (string) $card->id;
                }
                continue;
            }

            if ($card->type === 'group') {
                foreach ($card->tables as $table) {
                    if ((string) $table->name === $formName) {
                        return (string) $card->id;
                    }
                }
            }
        }

        return '';
    }

    protected function getViewPageRenderer(): ViewPageRenderer
    {
        if ($this->viewPageRenderer === null) {
            $this->viewPageRenderer = new ViewPageRenderer(
                $this->module,
                $this->registry,
                $this->fkResolver,
                $this->breadcrumbManager,
                $this->showIfEvaluator
            );
        }
        return $this->viewPageRenderer;
    }

    protected function renderError(string $message): void
    {
        $html = '<div class="container py-4">'
            . '<h1>Projects Extension Error</h1>'
            . '<p class="text-danger">' . _r($message) . '</p>'
            . '</div>';

        Response::themePage('default', $html);
    }

    protected function renderErrorResponse(string $message): void
    {
        $isJsonResponse = Response::isJson()
            || strtolower(trim((string) ($_REQUEST['page-output'] ?? ''))) === 'json';

        if ($isJsonResponse) {
            Response::json([
                'success' => false,
                'msg' => $message,
            ]);
            return;
        }

        $this->renderError($message);
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function isDataMutationAllowedForContext(array $context): bool
    {
        return ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey(
                $context,
                ['project_allows_data_mutation', 'projectAllowsDataMutation'],
                true
            )
        );
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function buildDataMutationBlockedMessage(array $context, string $action): string
    {
        $status = strtolower(trim((string) ProjectJsonStore::resolveAliasedKey(
            $context,
            ['project_status', 'projectStatus'],
            'development'
        )));
        $status = match ($status) {
            'active', 'production', 'prod' => 'active',
            'suspended', 'suspend', 'paused', 'pause' => 'suspended',
            'closed', 'close' => 'closed',
            default => 'development',
        };

        $action = strtolower(trim($action));
        if ($action === '') {
            $action = 'modify records';
        }

        return 'Cannot ' . $action . ': project status is ' . ucfirst($status) . '.';
    }

    /**
     * Render JSON response after restore/hard delete action in modal/offcanvas mode.
     * Returns proper JSON structure with list reload and offcanvas/modal hide.
     *
     * @param array $context The form context
     * @param int $id The record ID
     * @param string $action The executed action (restore, hard_delete, etc.)
     * @param bool $success Whether the action succeeded
     */
    protected function renderJsonResponseAfterAction(array $context, int $id, string $action, bool $success): void
    {
        $editDisplay = DisplayModeHelper::getEditMode($context);
        $modelName = (string) ($context['form_name'] ?? 'Model');
        $modelTitle = trim((string) ($context['form_title'] ?? ''));
        if ($modelTitle === '') {
            $modelTitle = ProjectNaming::toTitle($modelName);
        }
        if ($modelTitle === '') {
            $modelTitle = $modelName;
        }

        // Build base response
        $response = [
            'executed_action' => $action,
            'success' => $success
        ];

        // Add message
        if ($success) {
            $msg = \App\MessagesHandler::getSuccesses();
        } else {
            $msg = \App\MessagesHandler::getErrors(true);
        }
        if (is_array($msg) && count($msg) > 0) {
            $response['msg'] = implode("\n<br>", $msg);
        }

        // Add list reload - resolve the correct list ID
        $requestedReloadListId = trim((string) ($_REQUEST['reload_list_id'] ?? ''));
        $reloadTableId = $requestedReloadListId !== ''
            ? $requestedReloadListId
            : $this->resolveAutomaticReloadTableId(
                $context,
                $modelName,
                (bool) ($context['is_root'] ?? false),
                (string) ($context['max_records'] ?? 'n'),
                !empty($context['children_meta_by_alias'] ?? []),
                _absint($_REQUEST['root_id'] ?? 0)
            );

        if ($reloadTableId !== '') {
            $response['list'] = [
                'id' => $reloadTableId,
                'action' => 'reload'
            ];
        }

        // Build offcanvas or modal response
        $title = ($id > 0 ? 'Edit ' : 'New ') . $modelTitle;
        $formHtml = $this->buildFormHtmlAfterAction($context, $id);

        if ($editDisplay === 'offcanvas') {
            $response['offcanvas_end'] = [
                'title' => $title,
                'action' => 'hide',
                'body' => $formHtml
            ];
        } else {
            $response['modal'] = [
                'title' => $title,
                'action' => 'hide',
                'body' => $formHtml
            ];
        }

        Response::json($response);
    }

    /**
     * Build form HTML after action for JSON response.
     * Rebuilds the form with the restored/hard-deleted record state.
     *
     * @param array $context The form context
     * @param int $id The record ID
     * @return string The form HTML
     */
    protected function buildFormHtmlAfterAction(array $context, int $id): string
    {
        $state = $this->prepareFormBuilderState();
        if (!empty($state['error'])) {
            return '<div class="alert alert-danger">' . _r($state['error']) . '</div>';
        }
        if (!empty($state['redirect'])) {
            return '<div class="alert alert-info">' . _r('Redirecting...') . '</div>';
        }

        $formBuilder = $state['form_builder'] ?? null;
        if (!$formBuilder instanceof FormBuilder) {
            return '<div class="alert alert-danger">' . _r('Failed to initialize form builder.') . '</div>';
        }

        $editDisplay = (string) ($state['edit_display'] ?? 'page');
        $modelName = (string) ($state['model_name'] ?? 'Model');
        $modelTitle = (string) ($state['model_title'] ?? $modelName);
        $isRoot = (bool) ($state['is_root'] ?? false);
        $maxRecords = (string) ($state['max_records'] ?? 'n');
        $hasChildren = (bool) ($state['has_children'] ?? false);
        $rootId = _absint($state['root_id'] ?? 0);
        $requestedReloadListId = (string) ($state['requested_reload_list_id'] ?? '');

        // Configure form builder for JSON response
        $formBuilder->activeFetch()->setTitle('New ' . $modelTitle, 'Edit ' . $modelTitle);

        $reloadTableId = $requestedReloadListId !== ''
            ? $requestedReloadListId
            : $this->resolveAutomaticReloadTableId(
                $context,
                $modelName,
                $isRoot,
                $maxRecords,
                $hasChildren,
                $rootId
            );

        if ($reloadTableId !== '') {
            $formBuilder->dataListId($reloadTableId);
        }

        if ($editDisplay === 'offcanvas') {
            $formBuilder->asOffcanvas();
        } else {
            $formBuilder->asModal()->size('xl');
        }

        // Don't include delete actions in the rebuilt form after restore/hard delete
        // as the record state has changed
        $includeDelete = false;
        $cancelLink = null;
        $this->applyStandardActions($formBuilder, $includeDelete, $cancelLink);

        // Get the form HTML from the builder
        return $formBuilder->getForm();
    }
}
