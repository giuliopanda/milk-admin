<?php
namespace Extensions\Projects\Classes\Module;

use App\Abstracts\AbstractModule;
use App\Abstracts\ModuleRuleBuilder;
use App\Get;
use Extensions\Projects\Classes\ProjectJsonStore;
use Extensions\Projects\Classes\ProjectManifestIndex;
use Extensions\Projects\Classes\ProjectManifestParser;
use Extensions\Projects\Classes\ProjectNaming;
use Extensions\Projects\Classes\SearchFiltersConfigParser;
use Extensions\Projects\Classes\View\ViewSchema;
use Extensions\Projects\Classes\View\ViewSchemaParser;

!defined('MILK_DIR') && die();

class ProjectManifestService
{
    protected const AUTO_SOFT_DELETE_SCOPE_FILTER = 'projects_soft_delete_scope';

    protected AbstractModule $module;
    protected ProjectModuleLocator $locator;
    protected ProjectPermissionService $permissionService;
    protected ?ActionContextRegistry $registry;

    /** @var callable(string):void */
    protected $manifestErrorReporter;

    /** @var callable|null */
    protected $listPageCallback = null;

    /** @var callable|null */
    protected $editPageCallback = null;

    /** @var callable|null */
    protected $deletePageCallback = null;

    /** @var callable|null */
    protected $deleteConfirmPageCallback = null;

    /** @var callable|null */
    protected $viewPageCallback = null;

    protected ?ViewSchema $viewSchema = null;

    /**
     * @param callable(string):void $manifestErrorReporter
     */
    public function __construct(
        AbstractModule $module,
        ProjectModuleLocator $locator,
        ProjectPermissionService $permissionService,
        ?ActionContextRegistry $registry,
        callable $manifestErrorReporter
    ) {
        $this->module = $module;
        $this->locator = $locator;
        $this->permissionService = $permissionService;
        $this->registry = $registry;
        $this->manifestErrorReporter = $manifestErrorReporter;
    }

    public function configure(ModuleRuleBuilder $ruleBuilder): void
    {
        $manifestPath = $this->locator->findManifestPath();
        if ($manifestPath === null) {
            return;
        }

        $store = ProjectJsonStore::for(dirname($manifestPath));
        $this->applyManifestMenuConfig($ruleBuilder, $store);

        $index = $store->manifestIndex();
        if (!$index instanceof ProjectManifestIndex) {
            $hasManifestMessages = $this->registerStoreFileMessages($store, 'manifest.json', 'Manifest');
            if (!$hasManifestMessages) {
                $this->reportManifestError("Manifest file is empty, unreadable, or invalid: {$manifestPath}");
            }
            return;
        }

        $moduleNamespace = $this->locator->resolveModuleNamespace();
        $additionalModels = [];
        foreach ($index->getNodes() as $formName => $node) {
            $modelClass = $this->locator->resolveModelClassForForm($moduleNamespace, $formName);
            if ($modelClass === null) {
                $ref = (string) ($node['ref'] ?? ($formName . '.json'));
                $modelShort = $formName . 'Model';
                $studlyShort = ProjectNaming::toStudlyCase($formName) . 'Model';
                $projectModelShort = 'Project\\Models\\' . $modelShort;
                $projectStudlyShort = 'Project\\Models\\' . $studlyShort;
                $this->reportManifestError(
                    "Missing model for form '{$ref}'. Expected '{$modelShort}' or '{$studlyShort}' in module namespace, or '{$projectModelShort}' or '{$projectStudlyShort}'."
                );
                continue;
            }

            $additionalModels[$formName] = $modelClass;
        }

        if ($additionalModels !== []) {
            $ruleBuilder->addModels($additionalModels);
        }

        $defaultModelClass = null;
        foreach ($index->getRootFormNames() as $rootFormName) {
            $defaultModelClass = $additionalModels[$rootFormName] ?? null;
            if ($defaultModelClass !== null) {
                break;
            }
        }
        if ($defaultModelClass === null) {
            $firstModel = reset($additionalModels);
            $defaultModelClass = is_string($firstModel) && $firstModel !== '' ? $firstModel : null;
        }

        if ($defaultModelClass !== null) {
            $ruleBuilder->model(new $defaultModelClass());
        }
    }

    public function bootstrap(
        callable $listPageCallback,
        callable $editPageCallback,
        callable $deletePageCallback,
        callable $deleteConfirmPageCallback,
        callable $viewPageCallback
    ): ?ViewSchema {
        $this->viewSchema = null;
        $this->listPageCallback = $listPageCallback;
        $this->editPageCallback = $editPageCallback;
        $this->deletePageCallback = $deletePageCallback;
        $this->deleteConfirmPageCallback = $deleteConfirmPageCallback;
        $this->viewPageCallback = $viewPageCallback;

        $manifestPath = $this->locator->findManifestPath();
        if ($manifestPath === null) {
            return null;
        }
        if (!$this->registry instanceof ActionContextRegistry) {
            return null;
        }

        $store = ProjectJsonStore::for(dirname($manifestPath));
        $this->registerFromManifest($store, $manifestPath, $this->locator->resolveModuleNamespace());
        return $this->viewSchema;
    }

    protected function registerFromManifest(ProjectJsonStore $store, string $manifestPath, string $moduleNamespace): void
    {
        $parser = new ProjectManifestParser();
        try {
            $manifest = $parser->parseFile($manifestPath);
        } catch (\Throwable $e) {
            $this->reportManifestError($e->getMessage());
            return;
        }

        $index = new ProjectManifestIndex($manifest);
        $formTitlesByName = $this->buildFormTitlesByName($index, $store);
        $manifestArray = $store->manifest();
        if (!is_array($manifestArray)) {
            $manifestArray = [];
        }

        $this->loadViewSchema($store, $manifestArray);

        $searchFiltersByForm = $this->loadSearchFiltersByForm($store);
        $isAdminUser = $this->isCurrentUserAdministrator();
        $projectStatus = $this->permissionService->normalizeProjectStatus(
            $manifestArray['projectStatus'] ?? ($manifestArray['project_status'] ?? 'development')
        );
        $projectAllowsDataMutation = $this->permissionService->projectStatusAllowsDataMutation($projectStatus);
        $manifestAdditionalPermissionKeys = $this->permissionService->resolveManifestAdditionalPermissionKeys($store);

        foreach ($index->getNodes() as $formName => $node) {
            $config = $this->buildFormRouteConfig(
                $formName,
                $node,
                $index,
                $moduleNamespace,
                $store,
                $formTitlesByName,
                $searchFiltersByForm,
                $isAdminUser,
                $projectStatus,
                $projectAllowsDataMutation,
                $manifestAdditionalPermissionKeys
            );

            $this->registerFormRoutes($config);
        }
    }

    protected function loadViewSchema(ProjectJsonStore $store, array $manifestArray): void
    {
        $this->viewSchema = null;
        $viewLayoutPath = $store->filePath('view_layout.json');
        if (is_file($viewLayoutPath)) {
            try {
                $viewParser = new ViewSchemaParser();
                $this->viewSchema = $viewParser->parseFile($viewLayoutPath);
                foreach ($viewParser->getWarnings() as $warning) {
                    $this->reportManifestError("View layout warning: {$warning}");
                }
            } catch (\Throwable $e) {
                $this->reportManifestError("View layout error: {$e->getMessage()}");
                $this->viewSchema = null;
            }
        }

        if ($this->viewSchema === null && isset($manifestArray['view_layout']) && is_array($manifestArray['view_layout'])) {
            try {
                $viewParser = new ViewSchemaParser();
                $this->viewSchema = $viewParser->parseArray($manifestArray['view_layout']);
                foreach ($viewParser->getWarnings() as $warning) {
                    $this->reportManifestError("View layout warning: {$warning}");
                }
            } catch (\Throwable $e) {
                $this->reportManifestError("View layout error: {$e->getMessage()}");
                $this->viewSchema = null;
            }
        }
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,string> $formTitlesByName
     * @param array<string,array<string,mixed>> $searchFiltersByForm
     * @param array<int,string> $manifestAdditionalPermissionKeys
     */
    protected function buildFormRouteConfig(
        string $formName,
        array $node,
        ProjectManifestIndex $index,
        string $moduleNamespace,
        ProjectJsonStore $store,
        array $formTitlesByName,
        array $searchFiltersByForm,
        bool $isAdminUser,
        string $projectStatus,
        bool $projectAllowsDataMutation,
        array $manifestAdditionalPermissionKeys
    ): FormRouteConfig {
        $config = FormRouteConfig::make($formName);

        $ref = (string) ($node['ref'] ?? ($formName . '.json'));
        $schemaPath = $store->filePath(basename($ref));
        $config->modelClass = $this->locator->resolveModelClassForForm($moduleNamespace, $formName);
        $config->formTitle = (string) ($formTitlesByName[$formName] ?? ProjectNaming::toTitle($formName));

        $errors = [];
        if (!is_file($schemaPath)) {
            $errors[] = "Form schema file not found: {$schemaPath}";
        }
        if ($config->modelClass === null) {
            $modelShort = $formName . 'Model';
            $studlyShort = ProjectNaming::toStudlyCase($formName) . 'Model';
            $errors[] = "Missing model for form '{$ref}'. Expected '{$modelShort}' or '{$studlyShort}' in module namespace, or 'Project\\Models\\{$modelShort}' or 'Project\\Models\\{$studlyShort}'.";
        }
        foreach ($errors as $error) {
            $this->reportManifestError($error);
        }
        $config->error = implode(' ', $errors);

        $config->parentFormName = $index->getParentFormName($formName);
        $config->parentFkField = $config->parentFormName
            ? ProjectNaming::foreignKeyFieldForParentForm($config->parentFormName)
            : '';
        $config->fkChainFields = $index->getFkChainFields($formName);
        $config->ancestorFormNames = $index->getAncestorFormNames($formName);
        $config->ancestorModelClasses = [];
        foreach ($config->ancestorFormNames as $ancestorFormName) {
            $ancestorModelClass = $this->locator->resolveModelClassForForm($moduleNamespace, $ancestorFormName);
            if ($ancestorModelClass !== null) {
                $config->ancestorModelClasses[$ancestorFormName] = $ancestorModelClass;
            }
        }

        $isRoot = $config->isRoot();
        $config->specialPermissions = $this->permissionService->buildContextSpecialPermissions(
            $formName,
            $node,
            $isRoot,
            $manifestAdditionalPermissionKeys
        );
        $config->projectStatus = $projectStatus;
        $config->projectAllowsDataMutation = $projectAllowsDataMutation;

        $config->maxRecords = (string) ($node['max_records'] ?? 'n');
        $config->showIf = (string) ($node['show_if'] ?? '');
        $config->showIfMessage = (string) ($node['show_if_message'] ?? '');
        $config->showSearch = (bool) ($node['show_search'] ?? false);
        $config->softDelete = (bool) ($node['soft_delete'] ?? false);
        $config->allowDeleteRecord = !array_key_exists('allow_delete_record', $node)
            ? true
            : (bool) $node['allow_delete_record'];
        $config->canManageDeleteRecords = $this->permissionService->canManageDeleteByConfiguredPermissions(
            $config->softDelete,
            $config->allowDeleteRecord,
            $config->specialPermissions
        );
        $config->allowEdit = !array_key_exists('allow_edit', $node) ? true : (bool) $node['allow_edit'];
        $config->allowView = !array_key_exists('allow_view', $node) ? true : (bool) $node['allow_view'];
        if (!$projectAllowsDataMutation) {
            $config->allowEdit = false;
            $config->allowDeleteRecord = false;
            $config->canManageDeleteRecords = false;
        }
        if (!$this->permissionService->hasConfiguredSpecialPermission($config->specialPermissions, 'edit')) {
            $config->allowEdit = false;
        }
        if (!$this->permissionService->hasConfiguredSpecialPermission($config->specialPermissions, 'view')) {
            $config->allowView = false;
        }
        $config->searchFilters = $isRoot
            ? $this->resolveSearchFiltersForForm($searchFiltersByForm, $formName)
            : [];
        $config->defaultOrderEnabled = (bool) ($node['default_order_enabled'] ?? false);
        $config->defaultOrderField = trim((string) ($node['default_order_field'] ?? ''));
        $config->defaultOrderDirection = $this->normalizeDefaultOrderDirection(
            (string) ($node['default_order_direction'] ?? 'asc')
        );
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $config->defaultOrderField) !== 1) {
            $config->defaultOrderEnabled = false;
            $config->defaultOrderField = '';
            $config->defaultOrderDirection = 'asc';
        }
        if (!$config->defaultOrderEnabled) {
            $config->defaultOrderField = '';
            $config->defaultOrderDirection = 'asc';
        }
        $config->childCountColumn = ProjectJsonStore::normalizeChildCountColumnMode(
            (string) ($node['child_count_column'] ?? '')
        );
        $config->softDeleteScopeFilter = false;
        if ($isRoot && $config->softDelete && $config->canManageDeleteRecords) {
            $config->searchFilters = $this->injectAutoSoftDeleteScopeFilter($config->searchFilters);
            $config->softDeleteScopeFilter = $this->hasSoftDeleteScopeFilter($config->searchFilters);
        }

        $allowRootViewAction = !$isRoot || $config->allowView;
        $config->viewActionEnabled = $isRoot
            ? ((bool) ($node['view_action'] ?? false) && $allowRootViewAction)
            : false;
        $config->viewDisplay = (string) ($node['view_display'] ?? 'page');
        $config->listDisplay = (string) ($node['list_display'] ?? 'page');
        $config->editDisplay = (string) ($node['edit_display'] ?? 'page');

        $config->childrenMetaByAlias = [];
        foreach ($index->getChildrenFormNames($formName) as $childName) {
            $childNode = $index->getNode($childName);
            if (!is_array($childNode)) {
                continue;
            }

            $childSoftDelete = (bool) ($childNode['soft_delete'] ?? false);
            $childAllowDeleteRecord = !array_key_exists('allow_delete_record', $childNode)
                ? true
                : (bool) $childNode['allow_delete_record'];
            $childAllowEdit = !array_key_exists('allow_edit', $childNode) ? true : (bool) $childNode['allow_edit'];
            $childAllowView = !array_key_exists('allow_view', $childNode) ? true : (bool) $childNode['allow_view'];
            $childSpecialPermissions = $this->permissionService->buildContextSpecialPermissions(
                $childName,
                $childNode,
                false,
                $manifestAdditionalPermissionKeys
            );
            $childCanManageDeleteRecords = $this->permissionService->canManageDeleteByConfiguredPermissions(
                $childSoftDelete,
                $childAllowDeleteRecord,
                $childSpecialPermissions
            );
            if (!$this->permissionService->hasConfiguredSpecialPermission($childSpecialPermissions, 'edit')) {
                $childAllowEdit = false;
            }
            if (!$this->permissionService->hasConfiguredSpecialPermission($childSpecialPermissions, 'view')) {
                $childAllowView = false;
            }
            if (!$projectAllowsDataMutation) {
                $childAllowDeleteRecord = false;
                $childAllowEdit = false;
                $childCanManageDeleteRecords = false;
            }

            $alias = ProjectNaming::withCountAliasForForm($childName);
            $config->childrenMetaByAlias[$alias] = [
                'form_name' => $childName,
                'form_title' => (string) ($formTitlesByName[$childName] ?? ProjectNaming::toTitle($childName)),
                'list_action' => ProjectNaming::toActionSlug($childName) . '-list',
                'edit_action' => ProjectNaming::toActionSlug($childName) . '-edit',
                'max_records' => (string) ($childNode['max_records'] ?? 'n'),
                'has_children' => !empty($index->getChildrenFormNames($childName)),
                'show_if' => (string) ($childNode['show_if'] ?? ''),
                'show_if_message' => (string) ($childNode['show_if_message'] ?? ''),
                'soft_delete' => $childSoftDelete,
                'allow_delete_record' => $childAllowDeleteRecord,
                'can_manage_delete_records' => $childCanManageDeleteRecords,
                'allow_edit' => $childAllowEdit,
                'allow_view' => $childAllowView,
                'view_action' => false,
                'view_display' => (string) ($childNode['view_display'] ?? 'page'),
                'list_display' => (string) ($childNode['list_display'] ?? 'page'),
                'edit_display' => (string) ($childNode['edit_display'] ?? 'page'),
                'child_count_column' => ProjectJsonStore::normalizeChildCountColumnMode(
                    (string) ($childNode['child_count_column'] ?? '')
                ),
                'project_status' => $projectStatus,
                'project_allows_data_mutation' => $projectAllowsDataMutation,
                'parent_fk_field' => ProjectNaming::foreignKeyFieldForParentForm($formName),
                'special_permissions' => $childSpecialPermissions,
            ];
        }

        $config->addToHome = $isRoot
            && $config->allowView
            && in_array($formName, $index->getRootFormNames(), true);
        return $config;
    }

    protected function applyManifestMenuConfig(ModuleRuleBuilder $ruleBuilder, ProjectJsonStore $store): void
    {
        $data = $store->manifest();
        if (!is_array($data)) {
            return;
        }

        $menu = trim((string) ($data['menu'] ?? ''));
        $menuIcon = trim((string) ($data['menuIcon'] ?? ''));
        $existingLinks = $ruleBuilder->getMenuLinks();
        $existingLink = is_array($existingLinks) && $existingLinks !== [] ? $existingLinks[0] : [];
        $label = $menu !== '' ? $menu : (string) ($existingLink['name'] ?? '');
        if ($label === '') {
            $label = trim((string) ($data['name'] ?? ''));
        }
        $icon = $menuIcon !== '' ? $menuIcon : (string) ($existingLink['icon'] ?? '');
        $order = (int) ($existingLink['order'] ?? 9100);

        if (($menu !== '' || $menuIcon !== '') && $label !== '') {
            $ruleBuilder->menuLinks([
                ['name' => $label, 'url' => '', 'icon' => $icon, 'order' => $order],
            ]);
        }

        $selectMenuConfig = $this->resolveManifestSelectMenuConfig($data, $label, $icon, $order);
        if ($selectMenuConfig !== null) {
            $ruleBuilder->selectMenu(
                $selectMenuConfig['menu'],
                $selectMenuConfig['label'],
                $selectMenuConfig['url'],
                $selectMenuConfig['icon'],
                $selectMenuConfig['order']
            );
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array{menu:string,label:string,url:string,icon:string,order:int}|null
     */
    protected function resolveManifestSelectMenuConfig(
        array $data,
        string $defaultLabel,
        string $defaultIcon,
        int $defaultOrder
    ): ?array {
        $rawSelectMenu = $data['selectMenu'] ?? ($data['selectedMenu'] ?? ($data['select_menu'] ?? null));
        if ($rawSelectMenu === null) {
            return null;
        }

        $menuName = '';
        $label = null;
        $url = '';
        $icon = null;
        $order = null;

        if (is_string($rawSelectMenu) || is_numeric($rawSelectMenu)) {
            $menuName = trim((string) $rawSelectMenu);
        } elseif (is_array($rawSelectMenu)) {
            $menuName = trim((string) ($rawSelectMenu['name'] ?? ($rawSelectMenu['menu'] ?? ($rawSelectMenu['group'] ?? ''))));
            if (array_key_exists('label', $rawSelectMenu) || array_key_exists('title', $rawSelectMenu)) {
                $label = trim((string) ($rawSelectMenu['label'] ?? ($rawSelectMenu['title'] ?? '')));
            }
            $url = trim((string) ($rawSelectMenu['url'] ?? ''));
            if (array_key_exists('icon', $rawSelectMenu)) {
                $icon = trim((string) ($rawSelectMenu['icon'] ?? ''));
            }
            if (array_key_exists('order', $rawSelectMenu) && is_numeric($rawSelectMenu['order'])) {
                $order = (int) $rawSelectMenu['order'];
            }
        }

        if ($menuName === '') {
            return null;
        }

        return [
            'menu' => $menuName,
            'label' => $label !== null ? $label : $defaultLabel,
            'url' => $url,
            'icon' => $icon !== null ? $icon : $defaultIcon,
            'order' => $order !== null ? $order : $defaultOrder,
        ];
    }

    protected function registerFormRoutes(FormRouteConfig $config): void
    {
        if (!$this->registry instanceof ActionContextRegistry) {
            return;
        }
        if (!is_callable($this->listPageCallback) || !is_callable($this->editPageCallback) || !is_callable($this->deletePageCallback) || !is_callable($this->deleteConfirmPageCallback)) {
            return;
        }

        $context = $config->toContext();
        $listAction = $config->listAction();
        $editAction = $config->editAction();
        $viewAction = $config->viewAction();
        $deleteAction = $config->deleteAction();
        $deleteConfirmAction = $config->deleteConfirmAction();

        $this->module->registerRequestAction($listAction, $this->listPageCallback);
        $this->module->registerRequestAction($editAction, $this->editPageCallback);
        $this->module->registerRequestAction($deleteAction, $this->deletePageCallback);
        $this->module->registerRequestAction($deleteConfirmAction, $this->deleteConfirmPageCallback);
        if ($viewAction !== '' && is_callable($this->viewPageCallback)) {
            $this->module->registerRequestAction($viewAction, $this->viewPageCallback);
        }

        $this->registry->register($listAction, $context);
        $this->registry->register($editAction, $context);
        $this->registry->register($deleteAction, $context);
        $this->registry->register($deleteConfirmAction, $context);
        if ($viewAction !== '') {
            $this->registry->register($viewAction, $context);
        }

        if ($config->addToHome) {
            $errorLabel = $config->error !== '' ? ' (error)' : '';
            $listFetchMethod = DisplayModeHelper::getFetchMethod($context['list_display']);
            $this->registry->setPrimaryFormLink([
                'label' => $config->resolvedFormTitle() . ' List' . $errorLabel,
                'action' => $listAction,
                'fetch' => $listFetchMethod,
            ]);
        }
    }

    /**
     * @return array<string,string>
     */
    protected function buildFormTitlesByName(ProjectManifestIndex $index, ProjectJsonStore $store): array
    {
        $titles = [];
        foreach ($index->getNodes() as $formName => $node) {
            if (!is_string($formName) || $formName === '') {
                continue;
            }
            $ref = (string) ($node['ref'] ?? ($formName . '.json'));
            $schemaFile = basename($ref);
            $schemaName = pathinfo($schemaFile, PATHINFO_FILENAME);
            $titles[$formName] = $this->resolveFormTitleFromStore($store, $schemaName, $formName);
        }

        return $titles;
    }

    protected function resolveFormTitleFromStore(ProjectJsonStore $store, string $schemaName, string $fallbackFormName): string
    {
        $fallback = ProjectNaming::toTitle($fallbackFormName);
        if ($fallback === '') {
            $fallback = $fallbackFormName;
        }
        $trimmedSchemaName = trim($schemaName);
        if ($trimmedSchemaName === '') {
            return $fallback;
        }

        return $store->schemaTitle($trimmedSchemaName, $fallback);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    protected function loadSearchFiltersByForm(ProjectJsonStore $store): array
    {
        $configByForm = [];
        $parser = new SearchFiltersConfigParser();

        $fileData = $store->searchFilters();
        $this->registerStoreFileMessages($store, 'search_filters.json', 'Search filters');
        if (is_array($fileData)) {
            try {
                $configByForm = $parser->parseArray($fileData);
                foreach ($parser->getWarnings() as $warning) {
                    $this->reportManifestError("Search filters warning: {$warning}");
                }
            } catch (\Throwable $e) {
                $this->reportManifestError("Search filters error: {$e->getMessage()}");
            }
        }

        return $configByForm;
    }

    /**
     * @param array<string,array<string,mixed>> $configByForm
     * @return array<string,mixed>
     */
    protected function resolveSearchFiltersForForm(array $configByForm, string $formName): array
    {
        $trimmedFormName = trim($formName);
        if ($trimmedFormName !== '' && isset($configByForm[$trimmedFormName]) && is_array($configByForm[$trimmedFormName])) {
            return $configByForm[$trimmedFormName];
        }

        $slug = ProjectNaming::toActionSlug($formName);
        if ($slug !== '' && isset($configByForm[$slug]) && is_array($configByForm[$slug])) {
            return $configByForm[$slug];
        }

        if (isset($configByForm['*']) && is_array($configByForm['*'])) {
            return $configByForm['*'];
        }

        return [];
    }

    protected function normalizeDefaultOrderDirection(string $direction): string
    {
        return strtolower(trim($direction)) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    protected function injectAutoSoftDeleteScopeFilter(array $config): array
    {
        if ($this->hasSoftDeleteScopeFilter($config)) {
            return $config;
        }

        $filters = is_array($config['filters'] ?? null) ? $config['filters'] : [];
        $filters[] = [
            'type' => 'action_list',
            'name' => self::AUTO_SOFT_DELETE_SCOPE_FILTER,
            'label' => 'Deleted records',
            'placeholder' => '',
            'layout' => 'inline',
            'class' => '',
            'input_type' => 'text',
            'options' => [
                'active' => 'Not deleted',
                'deleted' => 'Deleted only',
            ],
            'has_default' => true,
            'default' => 'active',
            'query' => [
                'operator' => 'equals',
                'fields' => ['deleted_at'],
            ],
        ];

        $config['filters'] = $filters;
        return $config;
    }

    /**
     * @param array<string,mixed> $config
     */
    protected function hasSoftDeleteScopeFilter(array $config): bool
    {
        $filters = is_array($config['filters'] ?? null) ? $config['filters'] : [];
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $name = strtolower(trim((string) ($filter['name'] ?? '')));
            if ($name === self::AUTO_SOFT_DELETE_SCOPE_FILTER) {
                return true;
            }
        }

        return false;
    }

    protected function isCurrentUserAdministrator(): bool
    {
        try {
            $auth = Get::make('Auth');
            if (is_object($auth) && method_exists($auth, 'getUser')) {
                $user = $auth->getUser();
                if (is_object($user) && property_exists($user, 'is_admin')) {
                    return ProjectJsonStore::normalizeBool($user->is_admin);
                }
            }
        } catch (\Throwable) {
            // Fallback below.
        }

        try {
            return \App\Permissions::check('_user.is_admin');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function registerStoreFileMessages(ProjectJsonStore $store, string $filename, string $label): bool
    {
        $hasMessages = false;
        foreach ($store->getWarnings() as $warning) {
            if (!str_contains($warning, $filename)) {
                continue;
            }
            $hasMessages = true;
            $this->reportManifestError("{$label} warning: {$warning}");
        }
        foreach ($store->getErrors() as $error) {
            if (!str_contains($error, $filename)) {
                continue;
            }
            $hasMessages = true;
            $this->reportManifestError("{$label} error: {$error}");
        }

        return $hasMessages;
    }

    protected function reportManifestError(string $message): void
    {
        call_user_func($this->manifestErrorReporter, $message);
    }
}
