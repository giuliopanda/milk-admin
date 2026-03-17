<?php
namespace Extensions\Projects\Classes\Module;

use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

/**
 * Value object holding all configuration needed to register a manifest form's routes.
 *
 * Replaces the 23 positional parameters of Module::registerFormRoutes() with a
 * typed, self-documenting object. Built once per form node during manifest
 * registration and passed to registerFormRoutes().
 *
 * Also serves as the source of truth for building the action context array
 * stored in ActionContextRegistry, via toContext().
 */
class FormRouteConfig
{
    // -- Identity --

    public string $formName;
    public string $formTitle;
    public ?string $modelClass;
    public string $error;

    // -- Hierarchy --

    public ?string $parentFormName;
    public string $parentFkField;
    /** @var array<int,string> */
    public array $fkChainFields;
    /** @var array<int,string> */
    public array $ancestorFormNames;
    /** @var array<string,string> formName => FQCN */
    public array $ancestorModelClasses;

    // -- Behaviour --

    public string $maxRecords;
    public string $showIf;
    public string $showIfMessage;
    public bool $showSearch;
    /** @var array<string,mixed> */
    public array $searchFilters;
    public bool $softDelete;
    public bool $allowDeleteRecord;
    public bool $canManageDeleteRecords;
    public bool $allowEdit;
    public bool $softDeleteScopeFilter;
    public bool $defaultOrderEnabled;
    public string $defaultOrderField;
    public string $defaultOrderDirection;
    /** @var string '' (default), 'hide', or 'show'. */
    public string $childCountColumn;
    /** @var string Project lifecycle status (development|active|suspended|closed). */
    public string $projectStatus;
    /** @var bool Whether edit/delete data mutations are allowed by project status. */
    public bool $projectAllowsDataMutation;

    // -- Display --

    public bool $viewActionEnabled;
    public string $viewDisplay;
    public string $listDisplay;
    public string $editDisplay;

    // -- Children --

    /** @var array<string,array<string,mixed>> alias => child meta */
    public array $childrenMetaByAlias;

    // -- Registration --

    public bool $addToHome;

    // ------------------------------------------------------------------
    // Factory
    // ------------------------------------------------------------------

    /**
     * Create a FormRouteConfig with sensible defaults.
     *
     * Only formName is required. Everything else has a safe default so
     * callers only need to set the properties they care about.
     */
    public static function make(string $formName): self
    {
        $config = new self();

        $config->formName           = $formName;
        $config->formTitle          = '';
        $config->modelClass         = null;
        $config->error              = '';

        $config->parentFormName     = null;
        $config->parentFkField      = '';
        $config->fkChainFields      = [];
        $config->ancestorFormNames  = [];
        $config->ancestorModelClasses = [];

        $config->maxRecords         = 'n';
        $config->showIf             = '';
        $config->showIfMessage      = '';
        $config->showSearch         = false;
        $config->searchFilters      = [];
        $config->softDelete         = false;
        $config->allowDeleteRecord  = true;
        $config->canManageDeleteRecords = true;
        $config->allowEdit          = true;
        $config->softDeleteScopeFilter = false;
        $config->defaultOrderEnabled = false;
        $config->defaultOrderField = '';
        $config->defaultOrderDirection = 'asc';
        $config->childCountColumn = '';
        $config->projectStatus = 'development';
        $config->projectAllowsDataMutation = true;

        $config->viewActionEnabled  = false;
        $config->viewDisplay        = 'page';
        $config->listDisplay        = 'page';
        $config->editDisplay        = 'page';

        $config->childrenMetaByAlias = [];

        $config->addToHome          = false;

        return $config;
    }

    // ------------------------------------------------------------------
    // Derived values
    // ------------------------------------------------------------------

    public function isRoot(): bool
    {
        return $this->parentFormName === null || $this->parentFormName === '';
    }

    public function actionPrefix(): string
    {
        return ProjectNaming::toActionSlug($this->formName);
    }

    public function listAction(): string
    {
        return $this->actionPrefix() . '-list';
    }

    public function editAction(): string
    {
        return $this->actionPrefix() . '-edit';
    }

    public function viewAction(): string
    {
        return $this->viewActionEnabled ? ($this->actionPrefix() . '-view') : '';
    }

    public function deleteAction(): string
    {
        return $this->actionPrefix() . '-delete';
    }

    public function deleteConfirmAction(): string
    {
        return $this->actionPrefix() . '-delete-confirm';
    }

    public function resolvedFormTitle(): string
    {
        $title = trim($this->formTitle);
        if ($title !== '') {
            return $title;
        }
        $title = ProjectNaming::toTitle($this->formName);
        return $title !== '' ? $title : $this->formName;
    }

    // ------------------------------------------------------------------
    // Context export
    // ------------------------------------------------------------------

    /**
     * Build the context array for ActionContextRegistry.
     *
     * This is the single source of truth: the array shape matches what
     * every downstream consumer (renderers, helpers) already expects.
     *
     * @return array<string,mixed>
     */
    public function toContext(): array
    {
        return [
            'form_name'              => $this->formName,
            'form_title'             => $this->resolvedFormTitle(),
            'model_class'            => $this->modelClass,
            'list_action'            => $this->listAction(),
            'edit_action'            => $this->editAction(),
            'view_action'            => $this->viewAction(),
            'delete_action'          => $this->deleteAction(),
            'delete_confirm_action'  => $this->deleteConfirmAction(),
            'error'                  => $this->error,
            'is_root'                => $this->isRoot(),
            'parent_form_name'       => $this->parentFormName,
            'parent_fk_field'        => $this->parentFkField,
            'fk_chain_fields'        => $this->fkChainFields,
            'show_if'                => trim($this->showIf),
            'show_if_message'        => trim($this->showIfMessage),
            'show_search'            => $this->showSearch,
            'search_filters'         => $this->searchFilters,
            'ancestor_form_names'    => $this->ancestorFormNames,
            'ancestor_model_classes' => $this->ancestorModelClasses,
            'children_meta_by_alias' => $this->childrenMetaByAlias,
            'max_records'            => $this->maxRecords,
            'view_display'           => DisplayModeHelper::normalize($this->viewDisplay),
            'list_display'           => DisplayModeHelper::normalize($this->listDisplay),
            'edit_display'           => DisplayModeHelper::normalize($this->editDisplay),
            'soft_delete'            => $this->softDelete,
            'allow_delete_record'    => $this->allowDeleteRecord,
            'can_manage_delete_records' => $this->canManageDeleteRecords,
            'allow_edit'             => $this->allowEdit,
            'soft_delete_scope_filter' => $this->softDeleteScopeFilter,
            'default_order_enabled' => $this->defaultOrderEnabled,
            'default_order_field' => $this->defaultOrderField,
            'default_order_direction' => $this->defaultOrderDirection,
            'child_count_column' => $this->childCountColumn,
            'project_status' => $this->projectStatus,
            'project_allows_data_mutation' => $this->projectAllowsDataMutation,
        ];
    }
}
