<?php
namespace Extensions\Projects;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};
use App\Hooks;
use App\Logs;
use Builders\FormBuilder;
use Builders\SearchBuilder;
use Builders\TableBuilder;
use Extensions\Projects\Classes\Module\{
    ActionContextRegistry,
    BreadcrumbManager,
    FkChainResolver,
    ProjectDeleteService,
    ProjectDownloadService,
    ProjectInstallUpdateService,
    ProjectManifestService,
    ProjectModuleLocator,
    ProjectPermissionService,
    ProjectUninstallService,
    ShowIfEvaluator
};
use Extensions\Projects\Classes\Renderers\{
    AutoEditRenderer,
    AutoListRenderer,
    AutoViewRenderer
};
use Extensions\Projects\Classes\View\ViewSchema;

!defined('MILK_DIR') && die();

/**
 * Projects Module Extension (tree manifest).
 *
 * The public API stays on this class because AbstractModuleExtension and the
 * action registry expect these method names. Heavy logic is delegated to
 * dedicated services under Classes/Module.
 */
class Module extends AbstractModuleExtension
{
    protected const AUTO_SOFT_DELETE_SCOPE_FILTER = 'projects_soft_delete_scope';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_EDIT = 'main_table_edit';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_VIEW = 'main_table_view';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_SOFT_DELETE = 'main_table_soft_delete';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_HARD_DELETE = 'main_table_hard_delete';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_DELETE = 'main_table_delete';

    protected string $schemaFolder = 'Project';

    /** @var array<int,string> */
    protected array $manifestErrors = [];

    protected ?ActionContextRegistry $registry = null;
    protected ?BreadcrumbManager $breadcrumbManager = null;
    protected ?FkChainResolver $fkResolver = null;
    protected ?ShowIfEvaluator $showIfEvaluator = null;

    protected ?AutoListRenderer $listRenderer = null;
    protected ?AutoEditRenderer $editRenderer = null;
    protected ?AutoViewRenderer $viewRenderer = null;
    protected ?ViewSchema $viewSchema = null;

    protected ?ProjectModuleLocator $locator = null;
    protected ?ProjectPermissionService $permissionService = null;
    protected ?ProjectDeleteService $deleteService = null;
    protected ?ProjectDownloadService $downloadService = null;

    public function shellInstallModule(string $moduleName): bool
    {
        return $this->syncProjectRelatedTables(false);
    }

    public function shellUpdateModule(string $moduleName): bool
    {
        return $this->syncProjectRelatedTables(false);
    }

    public function shellUninstallModule(string $moduleName): bool
    {
        $manifestPath = $this->getLocator()->findManifestPath();
        if ($manifestPath === null || !is_file($manifestPath)) {
            return false;
        }

        $service = new ProjectUninstallService();
        return $service->uninstallProjectTables(
            $this->getLocator()->resolveModuleNamespace(),
            $this->getLocator()->resolveModuleDir(),
            $manifestPath,
            fn (string $moduleNamespace, string $formName): ?string => $this->getLocator()->resolveModelClassForForm($moduleNamespace, $formName)
        );
    }

    protected function syncProjectRelatedTables(bool $includeMain): bool
    {
        $manifestPath = $this->getLocator()->findManifestPath();
        if ($manifestPath === null || !is_file($manifestPath)) {
            return false;
        }

        $service = new ProjectInstallUpdateService();
        return $service->syncProjectTables(
            $this->getLocator()->resolveModuleNamespace(),
            $this->getLocator()->resolveModuleDir(),
            $manifestPath,
            fn (string $moduleNamespace, string $formName): ?string => $this->getLocator()->resolveModelClassForForm($moduleNamespace, $formName),
            $includeMain
        );
    }

    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        Hooks::set('projects.get-special-permissions', [$this, 'collectSpecialPermissionsForHook'], 10);
        Hooks::set('project_get_special_permissions', [$this, 'collectSpecialPermissionsForHook'], 10);
        $this->createManifestService()->configure($rule_builder);
    }

    public function bootstrap(): void
    {
        $this->registry = new ActionContextRegistry();
        $this->fkResolver = new FkChainResolver();
        $this->breadcrumbManager = new BreadcrumbManager($this->registry, $this->fkResolver);
        $this->showIfEvaluator = new ShowIfEvaluator();

        $this->manifestErrors = [];
        $this->viewSchema = null;
        $this->listRenderer = null;
        $this->editRenderer = null;
        $this->viewRenderer = null;
        $this->deleteService = null;
        $this->getPermissionService()->resetCache();

        $this->module->registerRequestAction('download-file', [$this, 'renderDownloadFilePage']);

        $this->viewSchema = $this->createManifestService($this->registry)->bootstrap(
            [$this, 'renderAutoListPage'],
            [$this, 'renderAutoEditPage'],
            [$this, 'renderAutoDeletePage'],
            [$this, 'renderAutoDeleteConfirmPage'],
            [$this, 'renderAutoViewPage']
        );
    }

    /**
     * @return array<int,array{label:string,action:string,fetch?:string|null}>
     */
    public function getPrimaryFormLink(): array
    {
        return $this->getRegistry()->getPrimaryFormLink();
    }

    /**
     * @return array<int,string>
     */
    public function getManifestErrors(): array
    {
        return $this->manifestErrors;
    }

    /**
     * @return array<int,array{
     *   permission:string,
     *   default:bool,
     *   block_title:string,
     *   block_name:string,
     *   row_name:string,
     *   row_order:int,
     *   ui_group:string,
     *   permission_label:string
     * }>
     */
    public function getAdditionalPermissionsWithDefault(): array
    {
        return $this->getPermissionService()->getAdditionalPermissionsWithDefault();
    }

    /**
     * @param array<int,array{permission:string,default:bool}>|mixed $permissions
     * @return array<int,array{permission:string,default:bool}>
     */
    public function collectSpecialPermissionsForHook(mixed $permissions = [], ?string $modulePage = null): array
    {
        return $this->getPermissionService()->collectSpecialPermissionsForHook($permissions, $modulePage);
    }

    public function checkSpecialPermission(string $permissionName): bool
    {
        $permissionName = trim($permissionName);
        if ($permissionName === '') {
            return true;
        }
        return Hooks::run('projects.check_special_permission', true, 'project.' . $permissionName);
    }

    public function renderAutoListPage(): void
    {
        $this->getListRenderer()->render();
    }

    public function getAutoListTableBuilder(array $options = []): ?TableBuilder
    {
        return $this->getListRenderer()->buildTableBuilder($options);
    }

    public function getAutoListSearchBuilder(array $options = []): ?SearchBuilder
    {
        return $this->getListRenderer()->buildSearchBuilder($options);
    }

    public function renderAutoEditPage(): void
    {
        $this->getEditRenderer()->render();
    }

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
        $this->getDeleteService()->renderAutoDeletePage();
    }

    public function renderAutoDeleteConfirmPage(): void
    {
        $this->getDeleteService()->renderAutoDeleteConfirmPage();
    }

    public function renderDownloadFilePage(): void
    {
        $this->getDownloadService()->renderDownloadFilePage();
    }

    public static function normalizeDownloadFilename(string $filename): string
    {
        return ProjectDownloadService::normalizeDownloadFilename($filename);
    }

    public static function buildDownloadTokenName(string $modulePage, string $filename): string
    {
        return ProjectDownloadService::buildDownloadTokenName($modulePage, $filename);
    }

    public function actionDeleteRow($records, $request): bool
    {
        return $this->getListRenderer()->actionDeleteRow($records, $request);
    }

    public function delete($records = null, array $request = []): bool
    {
        return $this->getDeleteService()->delete($records, $request);
    }

    protected function getListRenderer(): AutoListRenderer
    {
        if (!$this->listRenderer instanceof AutoListRenderer) {
            $this->listRenderer = new AutoListRenderer(
                $this->module,
                $this->getRegistry(),
                $this->getFkResolver(),
                $this->getBreadcrumbManager(),
                $this->getShowIfEvaluator()
            );
        }

        return $this->listRenderer;
    }

    protected function getEditRenderer(): AutoEditRenderer
    {
        if (!$this->editRenderer instanceof AutoEditRenderer) {
            $this->editRenderer = new AutoEditRenderer(
                $this->module,
                $this->getRegistry(),
                $this->getFkResolver(),
                $this->getBreadcrumbManager(),
                $this->getShowIfEvaluator()
            );

            if ($this->viewSchema instanceof ViewSchema) {
                $this->editRenderer->setViewSchema($this->viewSchema);
            }
        }

        return $this->editRenderer;
    }

    protected function getViewRenderer(): AutoViewRenderer
    {
        if (!$this->viewRenderer instanceof AutoViewRenderer) {
            $this->viewRenderer = new AutoViewRenderer(
                $this->module,
                $this->getListRenderer(),
                $this->getRegistry(),
                $this->getFkResolver(),
                $this->getBreadcrumbManager(),
                $this->getShowIfEvaluator()
            );

            if ($this->viewSchema instanceof ViewSchema) {
                $this->viewRenderer->setViewSchema($this->viewSchema);
            }
        }

        return $this->viewRenderer;
    }

    protected function getRegistry(): ActionContextRegistry
    {
        if (!$this->registry instanceof ActionContextRegistry) {
            $this->registry = new ActionContextRegistry();
        }

        return $this->registry;
    }

    protected function getFkResolver(): FkChainResolver
    {
        if (!$this->fkResolver instanceof FkChainResolver) {
            $this->fkResolver = new FkChainResolver();
        }

        return $this->fkResolver;
    }

    protected function getBreadcrumbManager(): BreadcrumbManager
    {
        if (!$this->breadcrumbManager instanceof BreadcrumbManager) {
            $this->breadcrumbManager = new BreadcrumbManager($this->getRegistry(), $this->getFkResolver());
        }

        return $this->breadcrumbManager;
    }

    protected function getShowIfEvaluator(): ShowIfEvaluator
    {
        if (!$this->showIfEvaluator instanceof ShowIfEvaluator) {
            $this->showIfEvaluator = new ShowIfEvaluator();
        }

        return $this->showIfEvaluator;
    }

    protected function getLocator(): ProjectModuleLocator
    {
        if (!$this->locator instanceof ProjectModuleLocator) {
            $this->locator = new ProjectModuleLocator($this->module, $this->schemaFolder);
        }

        return $this->locator;
    }

    protected function getPermissionService(): ProjectPermissionService
    {
        if (!$this->permissionService instanceof ProjectPermissionService) {
            $this->permissionService = new ProjectPermissionService(
                $this->getLocator(),
                fn (string $permissionName): bool => $this->checkSpecialPermission($permissionName),
                fn (): string => (string) $this->module->getPage()
            );
        }

        return $this->permissionService;
    }

    protected function getDeleteService(): ProjectDeleteService
    {
        if (!$this->deleteService instanceof ProjectDeleteService) {
            $this->deleteService = new ProjectDeleteService(
                $this->module,
                $this->getRegistry(),
                $this->getFkResolver(),
                $this->getPermissionService()
            );
        }

        return $this->deleteService;
    }

    protected function getDownloadService(): ProjectDownloadService
    {
        if (!$this->downloadService instanceof ProjectDownloadService) {
            $this->downloadService = new ProjectDownloadService($this->module);
        }

        return $this->downloadService;
    }

    protected function createManifestService(?ActionContextRegistry $registry = null): ProjectManifestService
    {
        return new ProjectManifestService(
            $this->module,
            $this->getLocator(),
            $this->getPermissionService(),
            $registry,
            function (string $message): void {
                $this->registerManifestError($message);
            }
        );
    }

    protected function registerManifestError(string $message): void
    {
        $this->manifestErrors[] = $message;
        Logs::set('SYSTEM', "Projects manifest: {$message}", 'ERROR');
    }
}
