<?php
namespace Extensions\Projects\Classes\Renderers;

!defined('MILK_DIR') && die();

/**
 * Value object holding all resolved parameters needed to build a list response.
 *
 * Populated once during the resolution phase of ListResponseBuilder::build()
 * and passed through to ListTableConfigurator and response assembly methods
 * so that no re-computation or parameter threading is needed.
 */
class ListContextParams
{
    // -- Model info --

    /** @var object The instantiated model. */
    public object $model;

    /** @var string Model class name (FQCN). */
    public string $modelClass;

    /** @var string Internal form name slug (e.g. 'invoice_line'). */
    public string $modelName;

    /** @var string Human-readable form title (e.g. 'Invoice Line'). */
    public string $modelTitle;

    /** @var string Primary key field name. */
    public string $primaryKey;

    // -- Module / page --

    /** @var string Module page identifier for URL building. */
    public string $modulePage;

    // -- Actions --

    /** @var string Edit action slug (empty if unavailable). */
    public string $editAction;

    /** @var string View action slug (empty if unavailable). */
    public string $viewAction;

    // -- Display modes --

    /** @var string List display mode ('page', 'offcanvas', 'modal'). */
    public string $listDisplay;

    /** @var string Edit display mode. */
    public string $editDisplay;

    /** @var string View display mode. */
    public string $viewDisplay;

    /** @var string|null Edit fetch method for AJAX, or null. */
    public ?string $editFetchMethod;

    /** @var string|null View fetch method for AJAX, or null. */
    public ?string $viewFetchMethod;

    // -- Hierarchy flags --

    /** @var bool Whether this form is the root form. */
    public bool $isRoot;

    /** @var string Max records value ('n' for unlimited, '1', '2', etc.). */
    public string $maxRecords;

    /** @var string Parent FK field name (empty for root forms). */
    public string $fkField;

    /** @var bool Whether this form has child forms. */
    public bool $hasChildren;

    /** @var bool Whether soft delete mode is enabled for this form. */
    public bool $softDeleteEnabled;

    /**
     * @var bool Whether the automatic soft-delete scope action_list filter
     *           is active for this list.
     */
    public bool $softDeleteScopeFilterEnabled;

    /** @var string Current soft-delete scope: 'active' or 'deleted'. */
    public string $softDeleteScope;

    /** @var bool Whether edit action is allowed for this form. */
    public bool $allowEditEnabled;

    /** @var bool Whether delete/restore operations are enabled by configuration. */
    public bool $allowDeleteRecordEnabled;

    /** @var bool Whether current user can manage delete/restore operations. */
    public bool $canManageDeleteRecords;

    /** @var bool Whether search should be shown. */
    public bool $showSearch;

    /** @var bool Whether to apply a default order when request has no explicit sorting. */
    public bool $defaultOrderEnabled;

    /** @var string Default order field name. */
    public string $defaultOrderField;

    /** @var string Default order direction ('asc' or 'desc'). */
    public string $defaultOrderDirection;

    /**
     * @var array<string,mixed> Normalized search filters config for this form.
     */
    public array $searchFilters;

    /**
     * @var array<string,int|float|string> Sanitized URL params declared in search_filters.
     */
    public array $urlFilterParams;

    /**
     * @var array<int,array{name:string,field:string,operator:string,value:int|float|string}>
     *      URL-driven SQL filters resolved from urlFilterParams.
     */
    public array $urlFilterWhereClauses;

    /** @var bool True when a required URL param is missing/invalid. */
    public bool $urlFilterRequiredFailed;

    // -- Table identification --

    /** @var string The resolved table DOM ID. */
    public string $tableId;

    /** @var string The reload list ID parameter key. */
    public string $reloadListIdParamKey;

    // -- Inline / embedded flags --

    /** @var bool Whether single-record inline mode is allowed. */
    public bool $allowSingleRecordInline;

    /** @var bool Whether this is an embedded view table. */
    public bool $isEmbeddedViewTable;

    /**
     * Whether root has a single entry view, hiding child shortcut columns
     * from the root list.
     */
    public bool $useSingleEntryRootView;

    /** @var string '' (default), 'hide', or 'show'. */
    public string $childCountColumn;

    /** @var bool Final decision for rendering direct-child count columns. */
    public bool $showChildCountColumns;

    /** @var string Project status (development|active|suspended|closed). */
    public string $projectStatus;

    /** @var bool When true, list table UI must be replaced by status alert. */
    public bool $projectBlocksTables;

    // -- FK chain --

    /** @var array<string,int> Resolved FK chain parameters (field => id). */
    public array $fkChainParams;

    /** @var int Requested root record ID. */
    public int $requestedRootId;

    /** @var int Direct parent record ID (0 for root forms). */
    public int $parentId;

    // -- Context passthrough --

    /** @var array The full raw context array from ActionContextRegistry. */
    public array $context;

    /** @var array The raw options array passed to build(). */
    public array $options;

    /**
     * @var array<string,array> Children metadata keyed by withCount alias.
     */
    public array $childrenMetaByAlias;
}
