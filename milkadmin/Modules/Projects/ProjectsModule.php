<?php
namespace Modules\Projects;

use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use App\Route;
use App\Response;

!defined('MILK_DIR') && die();

class ProjectsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('projects')
            ->title('Projects')
            ->menu('Projects', '', 'bi bi-folder2-open', 9095)
            ->setCss('/Assets/projects-build-forms.css')
            ->setCss('/Assets/projects-build-form-fields-modal.css')
            ->setJs('/Assets/projects-build-forms.js')
            ->setJs('/Assets/projects-build-form-fields.js')
            ->setJs('/Assets/projects-build-form-fields-modal.js')
            ->setJs('/Assets/projects-build-container-modal.js')
            ->setJs('/Assets/projects-build-forms-integrity.js')
            ->setJs('/Assets/projects-edit-filters-search.js')
            ->access('authorized')
            ->version('1.0.0');
    }

    #[RequestAction('home')]
    public function home(): void
    {
        Response::themePage('default', __DIR__ . '/Views/home.page.php', [
            'page' => $this->page,
            'projects' => ProjectCatalogService::discoverProjects($this->page),
        ]);
    }

    #[RequestAction('create-project')]
    public function createProject(): void
    {
        Response::themePage('default', __DIR__ . '/Views/create_project.page.php', [
            'page' => $this->page,
            'create_result' => null,
            'form_data' => [
                'project_name' => '',
                'project_description' => '',
                'main_table_source' => 'new',
                'main_table_name' => '',
            ],
        ]);
    }

    #[RequestAction('create-project-save')]
    public function createProjectSave(): void
    {
        $postData = is_array($_POST ?? null) ? $_POST : [];
        $result = ProjectCreationService::createProject($postData, $this->page);
        if (!empty($result['success'])) {
            $moduleName = trim((string) ($result['module_name'] ?? ''));
            $target = '?page=' . rawurlencode($this->page) . '&action=edit&module=' . rawurlencode($moduleName);
            Route::redirectSuccess($target, (string) ($result['msg'] ?? 'Project created.'));
            return;
        }

        Response::themePage('default', __DIR__ . '/Views/create_project.page.php', [
            'page' => $this->page,
            'create_result' => $result,
            'form_data' => [
                'project_name' => trim((string) ($postData['project_name'] ?? '')),
                'project_description' => trim((string) ($postData['project_description'] ?? '')),
                'main_table_source' => 'new',
                'main_table_name' => trim((string) ($postData['main_table_name'] ?? '')),
            ],
        ]);
    }

    #[RequestAction('edit')]
    public function edit(): void
    {
        $project = $this->getRequestedProject();
        $editAccess = $this->getProjectEditAccess($project);
        Response::themePage('default', __DIR__ . '/Views/edit.page.php', [
            'page' => $this->page,
            'project' => $project,
            'edit_access' => $editAccess,
        ]);
    }

    #[RequestAction('edit-module-configuration')]
    public function editModuleConfiguration(): void
    {
        $project = $this->getRequestedProject();

        Response::themePage('default', __DIR__ . '/Views/edit_module_configuration.page.php', [
            'page' => $this->page,
            'project' => $project,
            'module_menu_options' => $this->getModuleMenuOptions(),
        ]);
    }

    #[RequestAction('edit-record-view')]
    public function editRecordView(): void
    {
        $project = $this->getRequestedProject();
        $moduleName = trim((string) ($project['module_name'] ?? ($_GET['module'] ?? '')));
        $redirectUrl = '?page=' . rawurlencode($this->page);
        if ($moduleName !== '') {
            $redirectUrl .= '&action=edit&module=' . rawurlencode($moduleName);
        }
        Route::redirectError($redirectUrl, 'Edit Record View is disabled in Projects Lite.');
    }

    #[RequestAction('edit-filters-search')]
    public function editFiltersSearch(): void
    {
        $project = $this->getRequestedProject();
        $editAccess = $this->getProjectEditAccess($project);
        $searchEditor = !empty($editAccess['can_edit'])
            ? ProjectSearchFiltersService::buildPageData($project)
            : [];
        Response::themePage('default', __DIR__ . '/Views/edit_filters_search.page.php', [
            'page' => $this->page,
            'project' => $project,
            'edit_access' => $editAccess,
            'search_editor' => $searchEditor,
        ]);
    }

    #[RequestAction('save-filters-search')]
    public function saveFiltersSearch(): void
    {
        $jsonData = file_get_contents('php://input');
        Response::json(ProjectSearchFiltersSaveService::saveFromRawInput($jsonData, $this->page));
    }

    #[RequestAction('save-filters-search-success')]
    public function saveFiltersSearchSuccess(): void
    {
        $moduleName = trim((string) ($_GET['module'] ?? ''));
        $redirectUrl = '?page=' . rawurlencode($this->page);
        if ($moduleName !== '') {
            $redirectUrl .= '&action=edit&module=' . rawurlencode($moduleName);
        }

        Route::redirectSuccess($redirectUrl, 'Search filters saved successfully.');
    }

    #[RequestAction('save-record-view-layout')]
    public function saveRecordViewLayout(): void
    {
        Response::json([
            'success' => false,
            'msg' => 'Edit Record View is disabled in Projects Lite.',
        ]);
    }

    #[RequestAction('save-project-settings')]
    public function saveProjectSettings(): void
    {
        $project = $this->getRequestedProject();
        $moduleName = trim((string) ($project['module_name'] ?? ''));
        $postData = is_array($_POST ?? null) ? $_POST : [];
        $returnAction = trim((string) ($postData['return_action'] ?? 'edit-module-configuration'));
        if (!in_array($returnAction, ['edit', 'edit-module-configuration'], true)) {
            $returnAction = 'edit-module-configuration';
        }

        $redirectUrl = '?page=' . rawurlencode($this->page);
        if ($moduleName !== '') {
            $redirectUrl .= '&action=' . rawurlencode($returnAction) . '&module=' . rawurlencode($moduleName);
        }

        $result = ProjectSettingsService::saveProjectSettings($project, $postData);
        if (!empty($result['success'])) {
            Route::redirectSuccess($redirectUrl, (string) ($result['msg'] ?? 'Project settings updated.'));
            return;
        }

        Route::redirectError($redirectUrl, (string) ($result['msg'] ?? 'Unable to update project settings.'));
    }

    #[RequestAction('build-forms')]
    public function buildForms(): void
    {
        $project = $this->getRequestedProject();
        $moduleName = trim((string) ($project['module_name'] ?? ($_GET['module'] ?? '')));
        $redirectUrl = '?page=' . rawurlencode($this->page);
        if ($moduleName !== '') {
            $redirectUrl .= '&action=edit&module=' . rawurlencode($moduleName);
        }

        Route::redirect($redirectUrl);
    }

    #[RequestAction('build-form-fields')]
    public function buildFormFields(): void
    {
        $project = $this->getRequestedProject();
        $ref = trim((string) ($_GET['ref'] ?? ''));
        $draftToken = trim((string) ($_GET['draft'] ?? ''));
        $pageData = ProjectFormEditorService::buildPageData($project, $ref);
        $pageData = ProjectFormFieldsDraftService::applyDraftToEditorPayload($pageData, $project, $draftToken);

        Response::themePage('default', __DIR__ . '/Views/build_form_fields.page.php', [
            'page' => $this->page,
            'project' => $project,
            'editor' => $pageData,
        ]);
    }

    #[RequestAction('relation-models')]
    public function relationModels(): void
    {
        $project = $this->getRequestedProject();
        if (!is_array($project)) {
            Response::json([
                'success' => false,
                'msg' => 'Project not found.',
                'models' => [],
            ]);
            return;
        }

        $models = [];
        $allModels = \App\Abstracts\AbstractModule::getAllModels();
        foreach ($allModels as $modulePage => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $className = trim((string) ($entry['class'] ?? ''));
            if ($className === '') {
                continue;
            }
            $shortName = trim((string) ($entry['shortName'] ?? ''));
            if ($shortName === '') {
                $shortName = trim((string) basename(str_replace('\\', '/', $className)));
            }
            $namespace = trim((string) ($entry['namespace'] ?? ''));
            $modulePage = trim((string) $modulePage);
            if ($modulePage === '') {
                continue;
            }
            $instance = $entry['instance'] ?? null;
            if (!is_object($instance) && $className !== '' && class_exists($className)) {
                try {
                    $instance = new $className();
                } catch (\Throwable $e) {
                    $instance = null;
                }
            }
            if (!is_object($instance) || !method_exists($instance, 'getRules')) {
                continue;
            }

            $models[] = [
                'value' => $className,
                'label' => $shortName . ' (' . $modulePage . ')',
                'module' => $modulePage,
                'namespace' => $namespace,
                'short_name' => $shortName,
            ];
        }

        usort($models, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        Response::json([
            'success' => true,
            'models' => $models,
        ]);
    }

    #[RequestAction('relation-model-fields')]
    public function relationModelFields(): void
    {
        $project = $this->getRequestedProject();
        if (!is_array($project)) {
            Response::json([
                'success' => false,
                'msg' => 'Project not found.',
                'fields' => [],
            ]);
            return;
        }

        $modelClass = trim((string) ($_GET['model'] ?? ''));
        if ($modelClass === '') {
            Response::json([
                'success' => false,
                'msg' => 'Invalid model class.',
                'fields' => [],
            ]);
            return;
        }

        $selectedEntry = null;
        $allModels = \App\Abstracts\AbstractModule::getAllModels();
        foreach ($allModels as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryClass = trim((string) ($entry['class'] ?? ''));
            if ($entryClass === '' || strcasecmp($entryClass, $modelClass) !== 0) {
                continue;
            }
            $selectedEntry = $entry;
            break;
        }

        if (!is_array($selectedEntry)) {
            Response::json([
                'success' => false,
                'msg' => 'Model not found.',
                'fields' => [],
            ]);
            return;
        }

        $instance = $selectedEntry['instance'] ?? null;
        if (!is_object($instance) && class_exists($modelClass)) {
            try {
                $instance = new $modelClass();
            } catch (\Throwable $e) {
                $instance = null;
            }
        }
        if (!is_object($instance) || !method_exists($instance, 'getRules')) {
            Response::json([
                'success' => false,
                'msg' => 'Model instance is not available.',
                'fields' => [],
            ]);
            return;
        }

        $rules = $instance->getRules();
        if (!is_array($rules)) {
            $rules = [];
        }

        $fields = [];
        foreach ($rules as $fieldName => $rule) {
            $fieldName = trim((string) $fieldName);
            if ($fieldName === '') {
                continue;
            }
            if (is_array($rule) && array_key_exists('sql', $rule) && $rule['sql'] === false) {
                continue;
            }
            $fields[] = $fieldName;
        }

        $primaryField = '';
        if (method_exists($instance, 'getPrimaryKey')) {
            $primaryField = trim((string) $instance->getPrimaryKey());
        }
        if ($primaryField === '' && in_array('id', $fields, true)) {
            $primaryField = 'id';
        }

        Response::json([
            'success' => true,
            'fields' => $fields,
            'primary_field' => $primaryField,
        ]);
    }

    #[RequestAction('build-form-config')]
    public function buildFormConfig(): void
    {
        $project = $this->getRequestedProject();
        $ref = trim((string) ($_GET['ref'] ?? ''));
        $config = ProjectFormConfigService::buildPageData($project, $ref);

        Response::themePage('default', __DIR__ . '/Views/build_form_config.page.php', [
            'page' => $this->page,
            'project' => $project,
            'config' => $config,
        ]);
    }

    #[RequestAction('save-form-config')]
    public function saveFormConfig(): void
    {
        $project = $this->getRequestedProject();
        $postData = is_array($_POST ?? null) ? $_POST : [];
        $requestedRef = trim((string) ($postData['ref'] ?? ($_REQUEST['ref'] ?? '')));
        $result = ProjectFormConfigService::saveConfig($project, $requestedRef, $postData);

        $moduleName = trim((string) ($project['module_name'] ?? ''));
        $resolvedRef = trim((string) ($result['resolved_ref'] ?? ''));
        $targetRef = $resolvedRef !== '' ? $resolvedRef : basename($requestedRef);

        $redirectUrl = '?page=' . rawurlencode($this->page);
        if ($moduleName !== '') {
            $redirectUrl .= '&action=build-form-config&module=' . rawurlencode($moduleName);
            if ($targetRef !== '') {
                $redirectUrl .= '&ref=' . rawurlencode($targetRef);
            }
        }

        if (!empty($result['success'])) {
            Route::redirectSuccess($redirectUrl, (string) ($result['msg'] ?? 'Saved.'));
            return;
        }

        Route::redirectError($redirectUrl, (string) ($result['msg'] ?? 'Unable to save.'));
    }

    #[RequestAction('build-main-form-config')]
    public function buildMainFormConfig(): void
    {
        $project = $this->getRequestedProject();
        $config = ProjectMainFormConfigService::buildPageData($project);

        Response::themePage('default', __DIR__ . '/Views/build_main_form_config.page.php', [
            'page' => $this->page,
            'project' => $project,
            'config' => $config,
        ]);
    }

    #[RequestAction('save-main-form-config')]
    public function saveMainFormConfig(): void
    {
        $project = $this->getRequestedProject();
        $postData = is_array($_POST ?? null) ? $_POST : [];
        $result = ProjectMainFormConfigService::saveConfig($project, $postData);

        $moduleName = trim((string) ($project['module_name'] ?? ''));
        $redirectUrl = '?page=' . rawurlencode($this->page);
        if ($moduleName !== '') {
            $redirectUrl .= '&action=build-main-form-config&module=' . rawurlencode($moduleName);
        }

        if (!empty($result['success'])) {
            Route::redirectSuccess($redirectUrl, (string) ($result['msg'] ?? 'Saved.'));
            return;
        }

        Route::redirectError($redirectUrl, (string) ($result['msg'] ?? 'Unable to save.'));
    }

    #[RequestAction('save-form-fields-draft')]
    public function saveFormFieldsDraft(): void
    {
        $jsonData = file_get_contents('php://input');
        Response::json(ProjectFormFieldsDraftService::saveDraftFromRawInput($jsonData, $this->page));
    }

    #[RequestAction('review-form-fields-draft')]
    public function reviewFormFieldsDraft(): void
    {
        $project = $this->getRequestedProject();
        $ref = trim((string) ($_GET['ref'] ?? ''));
        $draftToken = trim((string) ($_GET['draft'] ?? ''));
        $review = ProjectFormFieldsDraftService::buildReviewPageData($project, $ref, $draftToken, $this->page);

        Response::themePage('default', __DIR__ . '/Views/review_form_fields_draft.page.php', [
            'page' => $this->page,
            'project' => $project,
            'review' => $review,
        ]);
    }

    #[RequestAction('accept-form-fields-draft')]
    public function acceptFormFieldsDraft(): void
    {
        $project = $this->getRequestedProject();
        $ref = trim((string) ($_REQUEST['ref'] ?? ''));
        $draftToken = trim((string) ($_REQUEST['draft'] ?? ''));
        $result = ProjectFormFieldsDraftService::acceptDraft($project, $ref, $draftToken, $this->page);

        $redirectUrl = trim((string) ($result['redirect_url'] ?? ''));
        if ($redirectUrl === '') {
            $moduleName = trim((string) ($project['module_name'] ?? ''));
            $redirectUrl = '?page=' . rawurlencode($this->page) . '&action=edit&module=' . rawurlencode($moduleName);
        }

        if (!empty($result['success'])) {
            Route::redirectSuccess($redirectUrl, (string) ($result['msg'] ?? 'Saved.'));
            return;
        }

        Route::redirectError($redirectUrl, (string) ($result['msg'] ?? 'Unable to save.'));
    }

    #[RequestAction('save-forms-tree')]
    public function saveFormsTree(): void
    {
        $jsonData = file_get_contents('php://input');
        Response::json(ProjectFormsService::saveFormsTreeFromRawInput($jsonData, $this->page));
    }

    #[RequestAction('delete-form-table')]
    public function deleteFormTable(): void
    {
        $jsonData = file_get_contents('php://input');
        Response::json(ProjectFormsService::deleteFormTableFromRawInput($jsonData, $this->page));
    }

    /**
     * @return array<int, array{page:string,title:string}>
     */
    private function getModuleMenuOptions(): array
    {
        $optionsByPage = [];
        $instances = AbstractModule::getAllInstances();
        foreach ($instances as $instance) {
            if (!is_object($instance) || !method_exists($instance, 'getPage')) {
                continue;
            }

            $modulePage = trim((string) $instance->getPage());
            if ($modulePage === '') {
                continue;
            }

            $moduleTitle = method_exists($instance, 'getTitle')
                ? trim((string) $instance->getTitle())
                : '';

            if ($moduleTitle === '') {
                $moduleTitle = ucfirst($modulePage);
            }

            $optionsByPage[$modulePage] = [
                'page' => $modulePage,
                'title' => $moduleTitle,
            ];
        }

        $options = array_values($optionsByPage);
        usort($options, static function (array $a, array $b): int {
            $titleCompare = strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            if ($titleCompare !== 0) {
                return $titleCompare;
            }

            return strcasecmp((string) ($a['page'] ?? ''), (string) ($b['page'] ?? ''));
        });

        return $options;
    }

    private function getRequestedModuleName(): string
    {
        $moduleName = trim((string) ($_GET['module'] ?? ''));
        if ($moduleName === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $moduleName) !== 1) {
            return '';
        }

        return $moduleName;
    }

    private function getRequestedProject(): ?array
    {
        $moduleName = $this->getRequestedModuleName();
        if ($moduleName === '') {
            return null;
        }

        return ProjectCatalogService::findProjectByModuleName($moduleName, $this->page);
    }

    /**
     * @param array<string,mixed>|null $project
     * @return array{
     *   can_edit:bool,
     *   message:string,
     *   inaccessible_paths:array<int,array{path:string,missing_permissions:string}>
     * }
     */
    private function getProjectEditAccess(?array $project): array
    {
        return ProjectEditAccessService::evaluate($project);
    }
}
