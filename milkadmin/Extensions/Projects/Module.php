<?php
namespace Extensions\Projects;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};
use App\Get;
use App\Logs;
use App\MessagesHandler;
use App\Response;
use App\Route;
use App\Settings;
use App\Token;
use App\Hooks;
use Builders\FormBuilder;
use Builders\SearchBuilder;
use Builders\TableBuilder;
use Extensions\Projects\Classes\Module\FormRouteConfig;
use Extensions\Projects\Classes\Renderers\{
    AutoListRenderer,
    AutoEditRenderer,
    AutoViewRenderer

};
use Extensions\Projects\Classes\Module\{
    ActionContextRegistry,
    BreadcrumbManager,
    ProjectInstallUpdateService,
    ProjectUninstallService,
    RecordTreeDeleter,
    DisplayModeHelper,
    FkChainResolver,
    ModelRecordHelper,
    ShowIfEvaluator,
    UrlBuilder
};
use Extensions\Projects\Classes\{
    ProjectManifestIndex,
    ProjectJsonStore,
    SearchFiltersConfigParser
};

use Extensions\Projects\Classes\View\{
    ViewSchemaParser,
    ViewSchema
};

use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

/**
 * Projects Module Extension (tree manifest).
 *
 * - Reads <ModuleDir>/Project/manifest.json (forms tree).
 * - Auto-registers list/edit request actions for every form in the tree.
 * - Home links include only root forms.
 * - List pages show counts only for direct children of the current form.
 * - Nested navigation uses FK param chain (propagated in query string).
 *
 * Refactored: logic is split into dedicated classes under Classes/ and Renderers/.
 */
class Module extends AbstractModuleExtension
{
    protected const AUTO_SOFT_DELETE_SCOPE_FILTER = 'projects_soft_delete_scope';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_EDIT = 'main_table_edit';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_VIEW = 'main_table_view';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_DELETE = 'main_table_delete';

    protected string $schemaFolder = 'Project';
    protected array $manifestErrors = [];
    protected bool $specialPermissionManifestIdResolved = false;
    protected string $specialPermissionManifestId = '';
    /** @var array{block_title:string,block_name:string,row_name:string} */
    protected array $specialPermissionUiMeta = [
        'block_title' => '',
        'block_name' => 'Access Data',
        'row_name' => '',
    ];
    protected bool $specialPermissionUiMetaResolved = false;

    protected ActionContextRegistry $registry;
    protected BreadcrumbManager $breadcrumbManager;
    protected FkChainResolver $fkResolver;
    protected ShowIfEvaluator $showIfEvaluator;

    // Lazy-initialized renderers.
    protected ?AutoListRenderer $listRenderer = null;
    protected ?AutoEditRenderer $editRenderer = null;
    protected ?AutoViewRenderer $viewRenderer = null;

     // Parsed view layout schema (null = use legacy rendering).
    protected ?ViewSchema $viewSchema = null;

    /**
     * Custom install entrypoint delegated to dedicated service.
     * Creates/updates all child form tables defined in Project manifest.
     */
    public function shellInstallModule(string $moduleName): bool
    {
        return $this->syncProjectRelatedTables(false);
    }

    /**
     * Custom update entrypoint delegated to dedicated service.
     * Creates/updates all child form tables defined in Project manifest.
     */
    public function shellUpdateModule(string $moduleName): bool
    {
        return $this->syncProjectRelatedTables(false);
    }

    /**
     * Custom uninstall entrypoint delegated to dedicated service.
     *
     * @return bool true when uninstall is handled by this extension.
     */
    public function shellUninstallModule(string $moduleName): bool
    {
        $moduleReflection = new \ReflectionClass($this->module);
        $moduleDir = $this->resolveModuleDirFromPath((string) $moduleReflection->getFileName());
        $manifestPath = $this->findManifestPath($moduleDir);
        if ($manifestPath === null || !is_file($manifestPath)) {
            return false;
        }

        $service = new ProjectUninstallService();
        return $service->uninstallProjectTables(
            (string) $moduleReflection->getNamespaceName(),
            $moduleDir,
            $manifestPath,
            fn (string $moduleNamespace, string $formName): ?string => $this->resolveModelClassForForm($moduleNamespace, $formName)
        );
    }

    /**
     * Sync related project tables (children forms) during install/update.
     */
    protected function syncProjectRelatedTables(bool $includeMain): bool
    {
        $moduleReflection = new \ReflectionClass($this->module);
        $moduleDir = $this->resolveModuleDirFromPath((string) $moduleReflection->getFileName());
        $manifestPath = $this->findManifestPath($moduleDir);
        if ($manifestPath === null || !is_file($manifestPath)) {
            return false;
        }

        $service = new ProjectInstallUpdateService();
        return $service->syncProjectTables(
            (string) $moduleReflection->getNamespaceName(),
            $moduleDir,
            $manifestPath,
            fn (string $moduleNamespace, string $formName): ?string => $this->resolveModelClassForForm($moduleNamespace, $formName),
            $includeMain
        );
    }

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------

    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        Hooks::set('project_get_special_permissions', [$this, 'collectSpecialPermissionsForHook'], 10);

        $moduleReflection = new \ReflectionClass($this->module);
        $moduleNamespace = $moduleReflection->getNamespaceName();
        $moduleDir = $this->resolveModuleDirFromPath((string) $moduleReflection->getFileName());
        $manifestPath = $this->findManifestPath($moduleDir);

        if ($manifestPath === null) {
            return;
        }

        $projectDir = dirname($manifestPath);
        $store = ProjectJsonStore::for($projectDir);

        // Apply menu/menuIcon from raw manifest JSON.
        $this->applyManifestMenuConfig($rule_builder, $store);

        $index = $store->manifestIndex();
        if (!$index instanceof ProjectManifestIndex) {
            $hasManifestMessages = $this->registerStoreFileMessages($store, 'manifest.json', 'Manifest');
            if (!$hasManifestMessages) {
                $this->registerManifestError("Manifest file is empty, unreadable, or invalid: {$manifestPath}");
            }
            return;
        }

        // Register all project models.
        $additionalModels = [];
        foreach ($index->getNodes() as $formName => $node) {
            $modelClass = $this->resolveModelClassForForm($moduleNamespace, $formName);
            if ($modelClass === null) {
                $ref = (string) ($node['ref'] ?? ($formName . '.json'));
                $modelShort = $formName . 'Model';
                $studlyShort = ProjectNaming::toStudlyCase($formName) . 'Model';
                $projectModelShort = 'Project\\Models\\' . $modelShort;
                $projectStudlyShort = 'Project\\Models\\' . $studlyShort;
                $this->registerManifestError(
                    "Missing model for form '{$ref}'. Expected '{$modelShort}' or '{$studlyShort}' in module namespace, or '{$projectModelShort}' or '{$projectStudlyShort}'."
                );
                continue;
            }
            $additionalModels[$formName] = $modelClass;
        }

        if (!empty($additionalModels)) {
            $rule_builder->addModels($additionalModels);
        }

        // Pick default model (first root with a valid model).
        $defaultModelClass = null;
        foreach ($index->getRootFormNames() as $rootFormName) {
            $defaultModelClass = $additionalModels[$rootFormName] ?? null;
            if ($defaultModelClass !== null) {
                break;
            }
        }
        if ($defaultModelClass === null) {
            $defaultModelClass = reset($additionalModels) ?: null;
        }

        if ($defaultModelClass !== null) {
            $rule_builder->model(new $defaultModelClass());
        }
    }

    public function bootstrap(): void
    {
        $this->registry = new ActionContextRegistry();
        $this->fkResolver = new FkChainResolver();
        $this->breadcrumbManager = new BreadcrumbManager($this->registry, $this->fkResolver);
        $this->showIfEvaluator = new ShowIfEvaluator();
        $this->manifestErrors = [];
        $this->listRenderer = null;
        $this->editRenderer = null;
        $this->viewRenderer = null;
        $this->viewSchema = null;
        $this->specialPermissionManifestIdResolved = false;
        $this->specialPermissionManifestId = '';
        $this->specialPermissionUiMetaResolved = false;
        $this->specialPermissionUiMeta = [
            'block_title' => '',
            'block_name' => 'Access Data',
            'row_name' => '',
        ];

        // Global Projects action available for every module using this extension.
        $this->module->registerRequestAction('download-file', [$this, 'renderDownloadFilePage']);

        $moduleReflection = new \ReflectionClass($this->module);
        $moduleNamespace = $moduleReflection->getNamespaceName();
        $moduleDir = $this->resolveModuleDirFromPath((string) $moduleReflection->getFileName());
        $manifestPath = $this->findManifestPath($moduleDir);

        if ($manifestPath !== null) {
            $projectDir = dirname($manifestPath);
            $store = ProjectJsonStore::for($projectDir);
            $this->registerFromManifest($store, $manifestPath, $moduleNamespace);
            return;
        }
        return;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * @return array<int,array{label:string,action:string,fetch?:string|null}>
     */
    public function getPrimaryFormLink(): array
    {
        return $this->registry->getPrimaryFormLink();
    }

    public function getManifestErrors(): array
    {
        return $this->manifestErrors;
    }

    /**
     * Return additional project permissions for the current module,
     * including their default value when not explicitly set.
     *
     * @return array<int,array{permission:string,default:bool}>
     */
    public function getAdditionalPermissionsWithDefault(): array
    {
        $permissionKeys = [
            self::SPECIAL_PERMISSION_MAIN_TABLE_EDIT,
            self::SPECIAL_PERMISSION_MAIN_TABLE_VIEW,
            self::SPECIAL_PERMISSION_MAIN_TABLE_DELETE,
        ];
        $uiMeta = $this->resolveSpecialPermissionUiMeta();

        $permissions = [];
        foreach ($permissionKeys as $permissionKey) {
            $permissionName = $this->buildMainTableSpecialPermissionName($permissionKey);
            if ($permissionName === '') {
                continue;
            }

            $permissions[] = [
                'permission' => 'project.' . $permissionName,
                'default' => true,
                'block_title' => $uiMeta['block_title'],
                'block_name' => $uiMeta['block_name'],
                'row_name' => $uiMeta['row_name'],
            ];
        }

        return $permissions;
    }

    /**
     * Hook callback for:
     * Hooks::run('project_get_special_permissions', [], ?string $modulePage)
     *
     * @param array<int,array{permission:string,default:bool}>|mixed $permissions
     * @return array<int,array{permission:string,default:bool}>
     */
    public function collectSpecialPermissionsForHook(mixed $permissions = [], ?string $modulePage = null): array
    {
        $normalized = is_array($permissions) ? $permissions : [];
        $requestedModulePage = strtolower(trim((string) $modulePage));
        $currentModulePage = strtolower(trim((string) $this->module->getPage()));

        if ($requestedModulePage !== '' && $currentModulePage !== '' && $requestedModulePage !== $currentModulePage) {
            return $normalized;
        }

        $known = [];
        foreach ($normalized as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $permission = trim((string) ($entry['permission'] ?? ''));
            if ($permission !== '') {
                $known[$permission] = true;
            }
        }

        foreach ($this->getAdditionalPermissionsWithDefault() as $entry) {
            $permission = trim((string) ($entry['permission'] ?? ''));
            if ($permission === '' || isset($known[$permission])) {
                continue;
            }
            $normalized[] = $entry;
            $known[$permission] = true;
        }

        return $normalized;
    }

    /**
     * Special permission gateway for Projects extension.
     *
     * Permission string format must be: "group.permission_key" (exactly one dot).
     * Main table permissions used by this extension:
     * - "project.<manifest_id>.main_table_edit"
     * - "project.<manifest_id>.main_table_view"
     * - "project.<manifest_id>.main_table_delete"
     *
     * Rules:
     * - "group" is the manifest id (example: "reports_project").
     * - "permission_key" should be snake_case (example: "main_table_edit").
     */
    public function checkSpecialPermission(string $permissionName): bool
    {
        $permissionName = trim($permissionName);
        //die($permissionName);
        if ($permissionName === '') {
            return true;
        }
        return Hooks::run("project_check_special_permission", true, 'project.'.$permissionName);
    }

    public function renderAutoListPage(): void
    {
        $this->getListRenderer()->render();
    }

    /**
     * Build and return the auto-configured TableBuilder for current list action.
     *
     * Returns null if current request action has no valid list context.
     */
    public function getAutoListTableBuilder(array $options = []): ?TableBuilder
    {
        return $this->getListRenderer()->buildTableBuilder($options);
    }

    /**
     * Build and return the auto-configured SearchBuilder for current list action.
     *
     * Returns null if current request action has no valid list context
     * or search filters are not defined.
     */
    public function getAutoListSearchBuilder(array $options = []): ?SearchBuilder
    {
        return $this->getListRenderer()->buildSearchBuilder($options);
    }

    public function renderAutoEditPage(): void
    {
        $this->getEditRenderer()->render();
    }

    /**
     * Build and return the auto-configured FormBuilder for current edit action.
     *
     * Returns null if current request action has no valid edit context.
     */
    public function getAutoEditFormBuilder(array $options = []): ?FormBuilder
    {
        return $this->getEditRenderer()->buildFormBuilder($options);
    }

    public function renderAutoViewPage(): void
    {
        $this->getViewRenderer()->render();
    }

    public function renderAutoDeletePage(): void
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null) {
            MessagesHandler::addError('No form context available for delete action.');
            Route::redirect(['page' => $this->module->getPage()]);
        }
        if (!$this->canManageDeleteRecordsForContext($context)) {
            $msg = 'You are not allowed to delete or restore records.';
            MessagesHandler::addError($msg);
            if (Response::isJson()) {
                Response::json([
                    'success' => false,
                    'msg' => $msg,
                ]);
                return;
            }
            Route::redirect(['page' => $this->module->getPage()]);
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            MessagesHandler::addError('Model class not found for delete action.');
            Route::redirect(['page' => $this->module->getPage()]);
        }

        $model = new $modelClass();
        $primaryKey = (string) $model->getPrimaryKey();
        if ($primaryKey === '') {
            MessagesHandler::addError('Invalid primary key for delete action.');
            Route::redirect(['page' => $this->module->getPage()]);
        }

        $id = _absint($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            MessagesHandler::addError('Missing record id for delete action.');
            Route::redirect(['page' => $this->module->getPage()]);
        }

        if (!$this->isValidDeleteConfirmation($context, $id)) {
            $msg = MessagesHandler::errorsToString();
            if ($msg === '') {
                $msg = 'Delete confirmation required.';
            }
            if (Response::isJson()) {
                Response::json([
                    'success' => false,
                    'msg' => $msg,
                ]);
                return;
            }
            Route::redirect(['page' => $this->module->getPage()]);
        }

        $requestedRootId = (bool) ($context['is_root'] ?? false)
            ? $id
            : $this->fkResolver->getRootIdFromRequest($context);

        $ok = $this->executeDeleteForContext($context, [$id], $requestedRootId);
        $redirect = $this->buildDeleteRedirectUrl($context);

        if (Response::isJson()) {
            $msg = $ok ? MessagesHandler::successToString() : MessagesHandler::errorsToString();
            if (trim($msg) === '') {
                $msg = $ok ? 'Item deleted successfully' : 'Unable to delete item';
            }
            $response = [
                'success' => $ok,
                'msg' => $msg,
            ];
            if ($ok) {
                $response['modal'] = ['action' => 'hide'];
                $response['redirect'] = Route::url($redirect);
            }
            Response::json($response);
            return;
        }

        Route::redirect($redirect);
    }

    public function renderAutoDeleteConfirmPage(): void
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null) {
            Response::json(['success' => false, 'msg' => 'No form context available for delete action.']);
            return;
        }
        if (!$this->canManageDeleteRecordsForContext($context)) {
            Response::json(['success' => false, 'msg' => 'You are not allowed to delete or restore records.']);
            return;
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            Response::json(['success' => false, 'msg' => 'Model class not found for delete action.']);
            return;
        }

        $id = _absint($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['success' => false, 'msg' => 'Missing record id for delete action.']);
            return;
        }

        $model = new $modelClass();
        $record = $model->getByIdForEdit($id);
        if (!is_object($record) || $record->isEmpty()) {
            Response::json(['success' => false, 'msg' => "Record #{$id} not found."]);
            return;
        }

        $deleteAction = (string) ($context['delete_action'] ?? '');
        if ($deleteAction === '') {
            Response::json(['success' => false, 'msg' => 'Delete action is not enabled for this form.']);
            return;
        }

        $modulePage = $this->module->getPage();
        $chainParams = $this->fkResolver->getChainParams($context);
        $deleteParams = array_merge(['id' => $id], $chainParams);
        $deleteUrl = Route::url(UrlBuilder::action($modulePage, $deleteAction, $deleteParams));

        $tokenName = $this->buildDeleteConfirmationTokenName($context, $id);
        $tokenValue = Token::get($tokenName);
        $modalTitle = 'Confirm Delete';
        $modalBody = $this->buildDeleteConfirmationModalBody($context, $id, $deleteUrl, $tokenName, $tokenValue);

        Response::json([
            'success' => true,
            'modal' => [
                'title' => $modalTitle,
                'body' => $modalBody,
                'size' => 'sm',
                'action' => 'show',
            ],
        ]);
    }

    public function renderDownloadFilePage(): void
    {
        $modulePage = trim((string) $this->module->getPage());
        $requestFilename = (string) ($_REQUEST['filename'] ?? '');
        $tokenValue = (string) ($_REQUEST['token'] ?? '');

        $normalizedFilename = self::normalizeDownloadFilename($requestFilename);
        if ($normalizedFilename === '') {
            $this->respondDownloadError('Missing or invalid filename.', 400);
            return;
        }

        if ($tokenValue === '') {
            $this->respondDownloadError('Missing download token.', 400);
            return;
        }

        $tokenName = self::buildDownloadTokenName($modulePage, $normalizedFilename);
        if (!Token::checkValue($tokenValue, $tokenName)) {
            $tokenError = Token::$last_error !== '' ? Token::$last_error : 'invalid_token';
            $this->respondDownloadError('Invalid or expired download token (' . $tokenError . ').', 403);
            return;
        }

        $filePath = $this->resolveDownloadFilePath($normalizedFilename);
        if ($filePath === null) {
            $this->respondDownloadError('File not found.', 404);
            return;
        }

        $downloadName = basename($normalizedFilename);
        if ($downloadName === '' || $downloadName === '.' || $downloadName === '..') {
            $downloadName = basename($filePath);
        }
        $downloadName = str_replace(["\r", "\n", '"'], '', $downloadName);
        if ($downloadName === '') {
            $downloadName = 'download.bin';
        }

        $mimeType = $this->detectMimeType($filePath);
        $fileSize = @filesize($filePath);
        if ($fileSize === false) {
            $fileSize = 0;
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code(200);
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . (string) $fileSize);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($filePath);
        Settings::save();
        Get::closeConnections();
        exit;
    }

    public static function normalizeDownloadFilename(string $filename): string
    {
        $filename = rawurldecode(trim($filename));
        if ($filename === '') {
            return '';
        }

        $filename = str_replace("\0", '', $filename);
        $filename = str_replace('\\', '/', $filename);
        $filename = preg_replace('/\/+/', '/', $filename);
        $filename = ltrim((string) $filename, '/');

        $parts = explode('/', $filename);
        $safeParts = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            if (preg_match('/[\x00-\x1F\x7F]/', $part) === 1) {
                return '';
            }
            $safeParts[] = $part;
        }

        return implode('/', $safeParts);
    }

    public static function buildDownloadTokenName(string $modulePage, string $filename): string
    {
        $safeModulePage = strtolower(trim($modulePage));
        $safeModulePage = preg_replace('/[^a-z0-9_-]/', '', $safeModulePage);
        if ($safeModulePage === '') {
            $safeModulePage = 'module';
        }

        $normalizedFilename = self::normalizeDownloadFilename($filename);
        if ($normalizedFilename === '') {
            $normalizedFilename = 'file';
        }

        return 'projects_download_' . $safeModulePage . '_' . md5($normalizedFilename);
    }

    public function actionDeleteRow($records, $request): bool
    {
        return $this->getListRenderer()->actionDeleteRow($records, $request);
    }

    public function delete($records = null, array $request = []): bool
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null) {
            MessagesHandler::addError('No form context available for delete action.');
            return false;
        }
        if (!$this->isDataMutationAllowedForContext($context)) {
            MessagesHandler::addError($this->buildDataMutationBlockedMessage($context, 'delete'));
            return false;
        }
        if (!$this->canManageDeleteRecordsForContext($context)) {
            MessagesHandler::addError('You are not allowed to delete or restore records.');
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

        $ids = $this->resolveDeleteTargetIds($request, $records, $primaryKey);
        $requestedRootId = $this->fkResolver->getRootIdFromRequest($context);

        return $this->executeDeleteForContext($context, $ids, $requestedRootId);
    }

    /**
     * Execute delete operation according to form context:
     * - soft delete updates deleted_at/deleted_by
     * - hard delete removes record tree
     *
     * @param array<string,mixed> $context
     * @param array<int,int> $ids
     */
    protected function executeDeleteForContext(array $context, array $ids, int $requestedRootId): bool
    {
        if (!$this->isDataMutationAllowedForContext($context)) {
            MessagesHandler::addError($this->buildDataMutationBlockedMessage($context, 'delete'));
            return false;
        }
        if (!$this->canManageDeleteRecordsForContext($context)) {
            MessagesHandler::addError('You are not allowed to delete or restore records.');
            return false;
        }

        if (empty($ids)) {
            MessagesHandler::addError('No items selected.');
            return false;
        }
        if ($this->isDeleteDisabledByConfig($context)) {
            MessagesHandler::addError('You cannot remove this record: deletion is disabled in the form configuration.');
            return false;
        }

        $softDeleteEnabled = ProjectJsonStore::normalizeBool($context['soft_delete'] ?? false);
        if ($softDeleteEnabled) {
            return $this->softDeleteMany($context, $ids);
        }

        $deleter = new RecordTreeDeleter($this->registry);
        return $deleter->deleteMany($context, $ids, $requestedRootId);
    }

    /**
     * @param array<string,mixed> $context
     * @param array<int,int> $ids
     */
    protected function softDeleteMany(array $context, array $ids): bool
    {
        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            MessagesHandler::addError('Model class not found for delete action.');
            return false;
        }

        $model = new $modelClass();
        if (!method_exists($model, 'getRules') || !method_exists($model, 'getByIdForEdit')) {
            MessagesHandler::addError('Soft delete is not available for this model.');
            return false;
        }

        $rules = $model->getRules();
        if (!is_array($rules) || !isset($rules['deleted_at']) || !isset($rules['deleted_by'])) {
            MessagesHandler::addError("Soft delete requires 'deleted_at' and 'deleted_by' fields in model rules.");
            return false;
        }

        $deletedBy = null;
        try {
            $auth = Get::make('Auth');
            if (is_object($auth) && method_exists($auth, 'getUser')) {
                $user = $auth->getUser();
                if (is_object($user)) {
                    $uid = _absint($user->id ?? 0);
                    $deletedBy = $uid > 0 ? $uid : null;
                }
            }
        } catch (\Throwable) {
            $deletedBy = null;
        }

        $deletedAt = date('Y-m-d H:i:s');

        foreach ($ids as $id) {
            $id = _absint($id);
            if ($id <= 0) {
                continue;
            }

            $record = $model->getByIdForEdit($id);
            if (!is_object($record) || $record->isEmpty()) {
                MessagesHandler::addError("Record #{$id} not found.");
                return false;
            }

            $alreadyDeleted = $this->hasNonEmptyDateValue($record->deleted_at ?? null);
            if ($alreadyDeleted) {
                continue;
            }

            $record->deleted_at = $deletedAt;
            $record->deleted_by = $deletedBy;

            if (!method_exists($record, 'save') || !$record->save()) {
                $error = method_exists($record, 'getLastError')
                    ? trim((string) $record->getLastError())
                    : '';
                if ($error === '' && method_exists($model, 'getLastError')) {
                    $error = trim((string) $model->getLastError());
                }
                if ($error === '') {
                    $error = "Unable to soft delete record #{$id}.";
                }
                MessagesHandler::addError($error);
                return false;
            }
        }

        MessagesHandler::addSuccess('Item moved to trash successfully');
        return true;
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
        $status = $this->normalizeProjectStatus(
            ProjectJsonStore::resolveAliasedKey(
                $context,
                ['project_status', 'projectStatus'],
                'development'
            )
        );
        $statusLabel = ucfirst($status);
        $action = strtolower(trim($action));
        if ($action === '') {
            $action = 'modify records';
        }

        return "Cannot {$action}: project status is {$statusLabel}.";
    }

    protected function buildMainTableSpecialPermissionName(string $permissionKey): string
    {
        $manifestId = $this->resolveSpecialPermissionManifestId();
        $permissionKey = strtolower(trim($permissionKey));
        if ($manifestId === '' || $permissionKey === '') {
            return '';
        }

        return $manifestId . '.' . $permissionKey;
    }

    protected function hasMainTableSpecialPermission(string $permissionKey): bool
    {
        $permissionName = $this->buildMainTableSpecialPermissionName($permissionKey);
        if ($permissionName === '') {
            return true;
        }

        return $this->checkSpecialPermission(
            $permissionName
        );
    }

    protected function resolveSpecialPermissionManifestId(): string
    {
        if ($this->specialPermissionManifestIdResolved) {
            return $this->specialPermissionManifestId;
        }

        $this->specialPermissionManifestIdResolved = true;
        $manifestData = ProjectJsonStore::getCurrentManifestData((string) $this->module->getPage());
        if (!is_array($manifestData)) {
            $this->specialPermissionManifestId = '';
            return '';
        }

        $this->specialPermissionManifestId = strtolower(trim((string) ($manifestData['id'] ?? '')));
        return $this->specialPermissionManifestId;
    }

    /**
     * @return array{block_title:string,block_name:string,row_name:string}
     */
    protected function resolveSpecialPermissionUiMeta(): array
    {
        if ($this->specialPermissionUiMetaResolved) {
            return $this->specialPermissionUiMeta;
        }

        $this->specialPermissionUiMetaResolved = true;
        $blockTitle = '';
        $rowName = '';
        $blockName = 'Access Data';

        try {
            $moduleReflection = new \ReflectionClass($this->module);
            $moduleDir = $this->resolveModuleDirFromPath((string) $moduleReflection->getFileName());
            $manifestPath = $this->findManifestPath($moduleDir);
            if ($manifestPath !== null) {
                $store = ProjectJsonStore::for(dirname($manifestPath));
                $manifestData = $store->manifest();
                if (is_array($manifestData)) {
                    $blockTitle = trim((string) ($manifestData['name'] ?? ''));
                }

                $index = $store->manifestIndex();
                if ($index instanceof ProjectManifestIndex) {
                    $rootFormNames = $index->getRootFormNames();
                    $rootFormName = trim((string) ($rootFormNames[0] ?? ''));
                    if ($rootFormName !== '') {
                        $rowName = $store->schemaTitle($rootFormName, $rootFormName);
                    }
                }
            }
        } catch (\Throwable) {
            // Keep fallbacks
        }

        if ($blockTitle === '') {
            $blockTitle = trim((string) $this->module->getPage());
        }
        if ($rowName === '') {
            $rowName = 'Main Table';
        }

        $this->specialPermissionUiMeta = [
            'block_title' => $blockTitle,
            'block_name' => $blockName,
            'row_name' => $rowName,
        ];

        return $this->specialPermissionUiMeta;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function isRootContext(array $context): bool
    {
        return ProjectJsonStore::normalizeBool($context['is_root'] ?? false);
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function canManageDeleteRecordsForContext(array $context): bool
    {
        if (
            $this->isRootContext($context)
            && !$this->hasMainTableSpecialPermission(self::SPECIAL_PERMISSION_MAIN_TABLE_DELETE)
        ) {
            return false;
        }

        if (array_key_exists('can_manage_delete_records', $context)) {
            return ProjectJsonStore::normalizeBool($context['can_manage_delete_records']);
        }

        $softDeleteEnabled = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($context, ['soft_delete', 'softDelete'], false)
        );
        if ($softDeleteEnabled) {
            return true;
        }

        if (ProjectJsonStore::hasAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'])) {
            return ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'], true)
            );
        }

        return true;
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
            return true;
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

    protected function normalizeProjectStatus(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        return match ($status) {
            'active', 'production', 'prod' => 'active',
            'suspended', 'suspend', 'paused', 'pause' => 'suspended',
            'closed', 'close' => 'closed',
            default => 'development',
        };
    }

    protected function projectStatusAllowsDataMutation(string $status): bool
    {
        $status = $this->normalizeProjectStatus($status);
        return !in_array($status, ['suspended', 'closed'], true);
    }

    // ------------------------------------------------------------------
    // Manifest registration
    // ------------------------------------------------------------------

    protected function registerFromManifest(ProjectJsonStore $store, string $manifestPath, string $moduleNamespace): void
    {
        $parser = new \Extensions\Projects\Classes\ProjectManifestParser();

        try {
            $manifest = $parser->parseFile($manifestPath);
        } catch (\Throwable $e) {
            $this->registerManifestError($e->getMessage());
            return;
        }

        $index = new ProjectManifestIndex($manifest);
        $formTitlesByName = $this->buildFormTitlesByName($index, $store);
        $manifestArray = $store->manifest();
        if (!is_array($manifestArray)) {
            $manifestArray = [];
        }

        // Load view_layout.json if present.
        $this->viewSchema = null;
        $viewLayoutPath = $store->filePath('view_layout.json');
        if (is_file($viewLayoutPath)) {
            try {
                $viewParser = new ViewSchemaParser();
                $this->viewSchema = $viewParser->parseFile($viewLayoutPath);
                foreach ($viewParser->getWarnings() as $warning) {
                    $this->registerManifestError("View layout warning: {$warning}");
                }
            } catch (\Throwable $e) {
                $this->registerManifestError("View layout error: {$e->getMessage()}");
                $this->viewSchema = null;
            }
        }

        // Check embedded view_layout in manifest.json.
        if ($this->viewSchema === null && isset($manifestArray['view_layout']) && is_array($manifestArray['view_layout'])) {
            try {
                $viewParser = new ViewSchemaParser();
                $this->viewSchema = $viewParser->parseArray($manifestArray['view_layout']);
                foreach ($viewParser->getWarnings() as $warning) {
                    $this->registerManifestError("View layout warning: {$warning}");
                }
            } catch (\Throwable $e) {
                $this->registerManifestError("View layout error: {$e->getMessage()}");
                $this->viewSchema = null;
            }
        }

        $searchFiltersByForm = $this->loadSearchFiltersByForm($store);
        $isAdminUser = $this->isCurrentUserAdministrator();
        $projectStatus = $this->normalizeProjectStatus(
            $manifestArray['projectStatus'] ?? ($manifestArray['project_status'] ?? 'development')
        );
        $projectAllowsDataMutation = $this->projectStatusAllowsDataMutation($projectStatus);

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
                $projectAllowsDataMutation
            );

            $this->registerFormRoutes($config);
        }
    }

    /**
     * Build a FormRouteConfig for a single manifest node.
     *
     * Extracted from the old registerFromManifest loop body so
     * the loop stays clean and the config building is testable.
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
        bool $projectAllowsDataMutation
    ): FormRouteConfig {
        $config = FormRouteConfig::make($formName);

        // -- Identity --
        $ref = (string) ($node['ref'] ?? ($formName . '.json'));
        $schemaPath = $store->filePath(basename($ref));
        $config->modelClass = $this->resolveModelClassForForm($moduleNamespace, $formName);
        $config->formTitle  = (string) ($formTitlesByName[$formName] ?? ProjectNaming::toTitle($formName));

        // -- Errors --
        $errors = [];
        if (!is_file($schemaPath)) {
            $errors[] = "Form schema file not found: {$schemaPath}";
        }
        if ($config->modelClass === null) {
            $modelShort = $formName . 'Model';
            $studlyShort = ProjectNaming::toStudlyCase($formName) . 'Model';
            $errors[] = "Missing model for form '{$ref}'. Expected '{$modelShort}' or '{$studlyShort}' in module namespace, or 'Project\\Models\\{$modelShort}' or 'Project\\Models\\{$studlyShort}'.";
        }
        foreach ($errors as $err) {
            $this->registerManifestError($err);
        }
        $config->error = implode(' ', $errors);

        // -- Hierarchy --
        $config->parentFormName      = $index->getParentFormName($formName);
        $config->parentFkField       = $config->parentFormName
            ? ProjectNaming::foreignKeyFieldForParentForm($config->parentFormName)
            : '';
        $config->fkChainFields       = $index->getFkChainFields($formName);
        $config->ancestorFormNames   = $index->getAncestorFormNames($formName);
        $config->ancestorModelClasses = [];
        foreach ($config->ancestorFormNames as $ancestorFormName) {
            $ancestorModelClass = $this->resolveModelClassForForm($moduleNamespace, $ancestorFormName);
            if ($ancestorModelClass !== null) {
                $config->ancestorModelClasses[$ancestorFormName] = $ancestorModelClass;
            }
        }

        $isRoot = $config->isRoot();
        $config->projectStatus = $projectStatus;
        $config->projectAllowsDataMutation = $projectAllowsDataMutation;

        // -- Behaviour --
        $config->maxRecords    = (string) ($node['max_records'] ?? 'n');
        $config->showIf        = (string) ($node['show_if'] ?? '');
        $config->showIfMessage = (string) ($node['show_if_message'] ?? '');
        $config->showSearch    = (bool) ($node['show_search'] ?? false);
        $config->softDelete    = (bool) ($node['soft_delete'] ?? false);
        $config->allowDeleteRecord = !array_key_exists('allow_delete_record', $node)
            ? true
            : (bool) $node['allow_delete_record'];
        $config->canManageDeleteRecords = $isAdminUser || $config->softDelete || $config->allowDeleteRecord;
        $config->allowEdit     = !array_key_exists('allow_edit', $node) ? true : (bool) $node['allow_edit'];
        if (!$projectAllowsDataMutation) {
            $config->allowEdit = false;
            $config->allowDeleteRecord = false;
            $config->canManageDeleteRecords = false;
        }
        if ($isRoot && !$this->hasMainTableSpecialPermission(self::SPECIAL_PERMISSION_MAIN_TABLE_EDIT)) {
            $config->allowEdit = false;
        }
        if ($isRoot && !$this->hasMainTableSpecialPermission(self::SPECIAL_PERMISSION_MAIN_TABLE_DELETE)) {
            $config->allowDeleteRecord = false;
            $config->canManageDeleteRecords = false;
        }
        $config->searchFilters = $isRoot
            ? $this->resolveSearchFiltersForForm($searchFiltersByForm, $formName)
            : [];
        $config->defaultOrderEnabled = (bool) ($node['default_order_enabled'] ?? false);
        $config->defaultOrderField = trim((string) ($node['default_order_field'] ?? ''));
        $config->defaultOrderDirection = $this->normalizeDefaultOrderDirection(
            (string) ($node['default_order_direction'] ?? 'asc')
        );
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $config->defaultOrderField)) {
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

        // -- Display --
        $allowRootViewAction = !$isRoot
            || $this->hasMainTableSpecialPermission(self::SPECIAL_PERMISSION_MAIN_TABLE_VIEW);
        $config->viewActionEnabled = $isRoot
            ? ((bool) ($node['view_action'] ?? false) && $allowRootViewAction)
            : false;
        $config->viewDisplay       = (string) ($node['view_display'] ?? 'page');
        $config->listDisplay       = (string) ($node['list_display'] ?? 'page');
        $config->editDisplay       = (string) ($node['edit_display'] ?? 'page');

        // -- Children meta --
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
            $childCanManageDeleteRecords = $isAdminUser || $childSoftDelete || $childAllowDeleteRecord;
            if (!$projectAllowsDataMutation) {
                $childAllowDeleteRecord = false;
                $childAllowEdit = false;
                $childCanManageDeleteRecords = false;
            }
            $alias = ProjectNaming::withCountAliasForForm($childName);
            $config->childrenMetaByAlias[$alias] = [
                'form_name'       => $childName,
                'form_title'      => (string) ($formTitlesByName[$childName] ?? ProjectNaming::toTitle($childName)),
                'list_action'     => ProjectNaming::toActionSlug($childName) . '-list',
                'edit_action'     => ProjectNaming::toActionSlug($childName) . '-edit',
                'max_records'     => (string) ($childNode['max_records'] ?? 'n'),
                'has_children'    => !empty($index->getChildrenFormNames($childName)),
                'show_if'         => (string) ($childNode['show_if'] ?? ''),
                'show_if_message' => (string) ($childNode['show_if_message'] ?? ''),
                'soft_delete'     => $childSoftDelete,
                'allow_delete_record' => $childAllowDeleteRecord,
                'can_manage_delete_records' => $childCanManageDeleteRecords,
                'allow_edit'      => $childAllowEdit,
                'view_action'     => false,
                'view_display'    => (string) ($childNode['view_display'] ?? 'page'),
                'list_display'    => (string) ($childNode['list_display'] ?? 'page'),
                'edit_display'    => (string) ($childNode['edit_display'] ?? 'page'),
                'child_count_column' => ProjectJsonStore::normalizeChildCountColumnMode(
                    (string) ($childNode['child_count_column'] ?? '')
                ),
                'project_status' => $projectStatus,
                'project_allows_data_mutation' => $projectAllowsDataMutation,
                'parent_fk_field' => ProjectNaming::foreignKeyFieldForParentForm($formName),
            ];
        }

        // -- Registration --
        $config->addToHome = $isRoot && in_array($formName, $index->getRootFormNames(), true);

        return $config;
    }


    /**
     * Override module menu/selected-menu configuration based on manifest fields:
     *   - "menu": custom sidebar label (overrides default)
     *   - "menuIcon": sidebar icon class (e.g. "bi bi-eye-fill")
     *   - "selectMenu": selected menu group name or object config
     */
    protected function applyManifestMenuConfig(ModuleRuleBuilder $rule_builder, ProjectJsonStore $store): void
    {
        $data = $store->manifest();
        if (!is_array($data)) {
            return;
        }

        $menu = trim((string) ($data['menu'] ?? ''));
        $menuIcon = trim((string) ($data['menuIcon'] ?? ''));
        $existingLinks = $rule_builder->getMenuLinks();
        $existingLink = is_array($existingLinks) && !empty($existingLinks) ? $existingLinks[0] : [];
        $label = $menu !== '' ? $menu : (string) ($existingLink['name'] ?? '');
        if ($label === '') {
            $label = trim((string) ($data['name'] ?? ''));
        }
        $icon = $menuIcon !== '' ? $menuIcon : (string) ($existingLink['icon'] ?? '');
        $order = (int) ($existingLink['order'] ?? 9100);

        if (($menu !== '' || $menuIcon !== '') && $label !== '') {
            $rule_builder->menuLinks([
                ['name' => $label, 'url' => '', 'icon' => $icon, 'order' => $order],
            ]);
        }

        $selectMenuConfig = $this->resolveManifestSelectMenuConfig($data, $label, $icon, $order);
        if ($selectMenuConfig !== null) {
            $rule_builder->selectMenu(
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

        $resolvedLabel = $label !== null ? $label : $defaultLabel;
        $resolvedIcon = $icon !== null ? $icon : $defaultIcon;
        $resolvedOrder = $order !== null ? $order : $defaultOrder;

        return [
            'menu' => $menuName,
            'label' => $resolvedLabel,
            'url' => $url,
            'icon' => $resolvedIcon,
            'order' => $resolvedOrder,
        ];
    }

    protected function registerFormRoutes(FormRouteConfig $config): void
    {
        $context     = $config->toContext();
        $listAction  = $config->listAction();
        $editAction  = $config->editAction();
        $viewAction  = $config->viewAction();
        $deleteAction        = $config->deleteAction();
        $deleteConfirmAction = $config->deleteConfirmAction();

        $this->module->registerRequestAction($listAction, [$this, 'renderAutoListPage']);
        $this->module->registerRequestAction($editAction, [$this, 'renderAutoEditPage']);
        $this->module->registerRequestAction($deleteAction, [$this, 'renderAutoDeletePage']);
        $this->module->registerRequestAction($deleteConfirmAction, [$this, 'renderAutoDeleteConfirmPage']);
        if ($viewAction !== '') {
            $this->module->registerRequestAction($viewAction, [$this, 'renderAutoViewPage']);
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
                'label'  => $config->resolvedFormTitle() . ' List' . $errorLabel,
                'action' => $listAction,
                'fetch'  => $listFetchMethod,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function getListRenderer(): AutoListRenderer
    {
        if ($this->listRenderer === null) {
            $this->listRenderer = new AutoListRenderer(
                $this->module,
                $this->registry,
                $this->fkResolver,
                $this->breadcrumbManager,
                $this->showIfEvaluator
            );
        }
        return $this->listRenderer;
    }

    protected function getEditRenderer(): AutoEditRenderer
    {
        if ($this->editRenderer === null) {
            $this->editRenderer = new AutoEditRenderer(
                $this->module,
                $this->registry,
                $this->fkResolver,
                $this->breadcrumbManager,
                $this->showIfEvaluator
            );

            if ($this->viewSchema !== null) {
                $this->editRenderer->setViewSchema($this->viewSchema);
            }
        }
        return $this->editRenderer;
    }

   protected function getViewRenderer(): AutoViewRenderer
    {
        if ($this->viewRenderer === null) {
            $this->viewRenderer = new AutoViewRenderer(
                $this->module,
                $this->getListRenderer(),
                $this->registry,
                $this->fkResolver,
                $this->breadcrumbManager,
                $this->showIfEvaluator
            );

            // NEW: inject view schema if available.
            if ($this->viewSchema !== null) {
                $this->viewRenderer->setViewSchema($this->viewSchema);
            }
        }
        return $this->viewRenderer;
    }

    protected function resolveModelClassForForm(string $moduleNamespace, string $formName): ?string
    {
        $direct = $moduleNamespace . '\\' . $formName . 'Model';
        if (class_exists($direct)) {
            return $direct;
        }

        $studly = $moduleNamespace . '\\' . ProjectNaming::toStudlyCase($formName) . 'Model';
        if (class_exists($studly)) {
            return $studly;
        }

        $projectDirect = $moduleNamespace . '\\Project\\Models\\' . $formName . 'Model';
        if (class_exists($projectDirect)) {
            return $projectDirect;
        }

        $projectStudly = $moduleNamespace . '\\Project\\Models\\' . ProjectNaming::toStudlyCase($formName) . 'Model';
        if (class_exists($projectStudly)) {
            return $projectStudly;
        }

        return null;
    }

    protected function resolveModuleDirFromPath(string $moduleFilePath): string
    {
        $normalized = str_replace('\\', '/', $moduleFilePath);
        if (preg_match('~^(.*?/Modules/[^/]+)(?:/.*)?$~', $normalized, $matches) === 1) {
            return $matches[1];
        }
        return dirname($moduleFilePath);
    }

    protected function getSchemaFolderCandidates(): array
    {
        return [$this->schemaFolder];
    }

    protected function findManifestPath(string $moduleDir): ?string
    {
        foreach ($this->getSchemaFolderCandidates() as $folder) {
            $path = $moduleDir . '/' . $folder . '/manifest.json';
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    protected function findSchemaPath(string $moduleDir, string $schemaFile): ?string
    {
        foreach ($this->getSchemaFolderCandidates() as $folder) {
            $path = $moduleDir . '/' . $folder . '/' . $schemaFile;
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    protected function registerManifestError(string $message): void
    {
        $this->manifestErrors[] = $message;
        Logs::set('SYSTEM', "Projects manifest: {$message}", 'ERROR');
    }

    protected function registerStoreFileMessages(ProjectJsonStore $store, string $filename, string $label): bool
    {
        $hasMessages = false;
        foreach ($store->getWarnings() as $warning) {
            if (!str_contains($warning, $filename)) {
                continue;
            }
            $hasMessages = true;
            $this->registerManifestError("{$label} warning: {$warning}");
        }
        foreach ($store->getErrors() as $error) {
            if (!str_contains($error, $filename)) {
                continue;
            }
            $hasMessages = true;
            $this->registerManifestError("{$label} error: {$error}");
        }
        return $hasMessages;
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
        $schemaName = trim($schemaName);
        if ($schemaName === '') {
            return $fallback;
        }
        return $store->schemaTitle($schemaName, $fallback);
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
                    $this->registerManifestError("Search filters warning: {$warning}");
                }
            } catch (\Throwable $e) {
                $this->registerManifestError("Search filters error: {$e->getMessage()}");
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
        $formName = trim($formName);
        if ($formName !== '' && isset($configByForm[$formName]) && is_array($configByForm[$formName])) {
            return $configByForm[$formName];
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
        $direction = strtolower(trim($direction));
        return $direction === 'desc' ? 'desc' : 'asc';
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
            // Fallback to permissions check below.
        }

        try {
            return \App\Permissions::check('_user.is_admin');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resolve IDs targeted by delete action.
     *
     * @return array<int,int>
     */
    protected function resolveDeleteTargetIds(array $request, mixed $records, string $primaryKey): array
    {
        $ids = [];
        $tableIds = $request['table_ids'] ?? [];

        if (is_string($tableIds)) {
            $parts = str_contains($tableIds, ',') ? explode(',', $tableIds) : [$tableIds];
            foreach ($parts as $part) {
                $id = _absint($part);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } elseif (is_array($tableIds)) {
            foreach ($tableIds as $part) {
                $id = _absint($part);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        if (!empty($ids)) {
            return array_values(array_unique($ids));
        }

        // Edit form actions usually pass payload under $_REQUEST['data'].
        $requestCandidates = [$request];
        if (is_array($request['data'] ?? null)) {
            $requestCandidates[] = $request['data'];
        }

        foreach ($requestCandidates as $candidate) {
            foreach ([$primaryKey, 'id'] as $key) {
                if (!array_key_exists($key, $candidate)) {
                    continue;
                }
                $id = _absint($candidate[$key]);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        if (!empty($ids)) {
            return array_values(array_unique($ids));
        }

        foreach (ModelRecordHelper::extractRawRows($records) as $row) {
            $id = _absint(ModelRecordHelper::extractFieldValue($row, $primaryKey));
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    protected function buildDeleteRedirectUrl(array $context): string
    {
        $modulePage = $this->module->getPage();
        $listAction = (string) ($context['list_action'] ?? '');
        $isRoot = (bool) ($context['is_root'] ?? false);

        if ($listAction === '') {
            return '?page=' . rawurlencode($modulePage);
        }
        if ($isRoot) {
            return UrlBuilder::action($modulePage, $listAction);
        }

        $params = $this->fkResolver->getChainParamsForParent($context);
        return UrlBuilder::action($modulePage, $listAction, $params);
    }

    protected function isValidDeleteConfirmation(array $context, int $id): bool
    {
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($requestMethod !== 'POST') {
            MessagesHandler::addError('Delete confirmation required.');
            return false;
        }

        $confirmed = in_array(
            strtolower(trim((string) ($_REQUEST['projects_delete_confirmation'] ?? ''))),
            ['1', 'true', 'yes', 'on'],
            true
        );
        if (!$confirmed) {
            MessagesHandler::addError('Delete confirmation required.');
            return false;
        }

        $postedId = _absint($_REQUEST['projects_delete_id'] ?? 0);
        if ($postedId <= 0 || $postedId !== $id) {
            MessagesHandler::addError('Invalid delete request id.');
            return false;
        }

        $tokenValue = (string) ($_REQUEST['projects_delete_token'] ?? '');
        if ($tokenValue === '') {
            MessagesHandler::addError('Missing delete confirmation token.');
            return false;
        }

        $expectedTokenName = $this->buildDeleteConfirmationTokenName($context, $id);
        if (!Token::checkValue($tokenValue, $expectedTokenName)) {
            $tokenError = Token::$last_error !== '' ? Token::$last_error : 'invalid_token';
            MessagesHandler::addError('Invalid or expired delete token (' . $tokenError . ').');
            return false;
        }

        return true;
    }

    protected function buildDeleteConfirmationTokenName(array $context, int $id): string
    {
        $formName = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower((string) ($context['form_name'] ?? 'record')));
        if ($formName === '') {
            $formName = 'record';
        }
        return 'projects_delete_' . $formName . '_' . max(0, $id);
    }

    protected function buildDeleteConfirmationModalBody(
        array $context,
        int $id,
        string $deleteUrl,
        string $tokenName,
        string $tokenValue
    ): string {
        $formTitle = trim((string) ($context['form_title'] ?? ''));
        if ($formTitle === '') {
            $formTitle = ProjectNaming::toTitle((string) ($context['form_name'] ?? 'Record'));
        }
        if ($formTitle === '') {
            $formTitle = 'Record';
        }
        $softDeleteEnabled = ProjectJsonStore::normalizeBool($context['soft_delete'] ?? false);

        ob_start();
        include __DIR__ . '/Views/delete_confirmation_modal.php';
        return ob_get_clean();
    }

    protected function resolveDownloadFilePath(string $normalizedFilename): ?string
    {
        $normalizedFilename = self::normalizeDownloadFilename($normalizedFilename);
        if ($normalizedFilename === '') {
            return null;
        }

        $candidateRelPaths = [$normalizedFilename];
        if (strpos($normalizedFilename, 'media/') !== 0) {
            $candidateRelPaths[] = 'media/' . $normalizedFilename;
        }
        if (strpos($normalizedFilename, 'temp/') !== 0) {
            $candidateRelPaths[] = 'temp/' . $normalizedFilename;
        }

        $candidateRelPaths = array_values(array_unique($candidateRelPaths));
        foreach ($candidateRelPaths as $relativePath) {
            $fullPath = rtrim((string) LOCAL_DIR, '/\\') . '/' . ltrim($relativePath, '/');
            $realPath = realpath($fullPath);
            if ($realPath === false) {
                continue;
            }
            if (!$this->isLocalPath($realPath)) {
                continue;
            }
            if (!is_file($realPath) || !is_readable($realPath)) {
                continue;
            }
            return $realPath;
        }

        return null;
    }

    protected function isLocalPath(string $path): bool
    {
        $localDir = realpath((string) LOCAL_DIR);
        if ($localDir === false) {
            return false;
        }

        $normalizedLocalDir = rtrim(str_replace('\\', '/', $localDir), '/') . '/';
        $normalizedPath = str_replace('\\', '/', $path);

        return str_starts_with($normalizedPath, $normalizedLocalDir);
    }

    protected function detectMimeType(string $filePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        return 'application/octet-stream';
    }

    protected function respondDownloadError(string $message, int $statusCode): void
    {
        if (Response::isJson()) {
            http_response_code($statusCode);
            Response::json([
                'success' => false,
                'msg' => $message,
                'status' => $statusCode,
            ]);
            return;
        }

        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        Settings::save();
        Get::closeConnections();
        exit;
    }
}
