<?php
namespace Extensions\Projects\Classes\View;

use App\Route;
use Extensions\Projects\Classes\ProjectNaming;
use Extensions\Projects\Classes\Module\{
    ActionContextRegistry,
    DisplayModeHelper,
    FkChainResolver,
    ModelRecordHelper,
    UrlBuilder
};

!defined('MILK_DIR') && die();

/**
 * Renders individual view blocks for a single form/table reference.
 *
 * Each block corresponds to one `tableConfig` entry in the view_layout JSON.
 * The renderer resolves the form context from the registry, loads records,
 * and produces the appropriate HTML based on `displayAs`.
 *
 * Supported display modes:
 *   - "fields"  : key-value rows (for single-record tables with many fields)
 *   - "icon"    : compact icon + title link (for single-record tables in groups)
 *   - "table"   : HTML table with optional nested-table columns (for multi-record)
 */
class ViewBlockRenderer
{
    public function __construct(
        protected ActionContextRegistry $registry,
        protected FkChainResolver $fkResolver,
        protected string $modulePage
    ) {}

    // ==================================================================
    // Public entry points
    // ==================================================================

    /**
     * Render a "fields" block: key-value detail rows for a single record.
     *
     * @param array       $formContext   ActionContextRegistry context for this form.
     * @param int         $parentId      Parent record ID (the root record being viewed).
     * @param int         $rootId        Root ID for FK chain.
     * @param string      $sectionTitle  Card header title.
     * @param string      $sectionIcon   Bootstrap icon class.
     * @return string HTML
     */
    public function renderFields(
        array $formContext,
        int $parentId,
        int $rootId,
        string $sectionTitle = '',
        string $sectionIcon = ''
    ): string {
        $modelClass = (string) ($formContext['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            return $this->errorHtml('Model class not found for form.');
        }

        $model = new $modelClass();
        $formName = (string) ($formContext['form_name'] ?? '');
        $fkField = (string) ($formContext['parent_fk_field'] ?? '');
        $isRoot = (bool) ($formContext['is_root'] ?? false);
        $editAction = (string) ($formContext['edit_action'] ?? '');

        // Resolve title.
        if ($sectionTitle === '') {
            $sectionTitle = trim((string) ($formContext['form_title'] ?? ''));
            if ($sectionTitle === '') {
                $sectionTitle = ProjectNaming::toTitle($formName);
            }
        }

        // For root form displayed as fields, the record is the parent itself.
        if ($isRoot) {
            $record = $model->getByIdForEdit($parentId);
            $chainParams = [];
        } else {
            if ($fkField === '') {
                return $this->errorHtml("Missing FK field for form '{$formName}'.");
            }
            $record = ModelRecordHelper::findFirstByFk($model, $fkField, $parentId);
            $chainParams = $this->buildChainParamsForChild($formContext, $parentId, $rootId);
        }

        if (!is_object($record) || $record->isEmpty()) {
            // No record: show "Add new" button.
            if (!$isRoot && $editAction !== '') {
                $addUrl = Route::url(UrlBuilder::action($this->modulePage, $editAction, $chainParams));
                $editDisplay = DisplayModeHelper::getEditMode($formContext);
                $fetchAttr = DisplayModeHelper::buildFetchAttribute($editDisplay);
                $addBtn = '<a class="btn btn-sm btn-outline-primary" href="' . _r($addUrl) . '"' . $fetchAttr . '>'
                    . '<i class="bi bi-plus-circle"></i> Add new</a>';
                return $this->wrapCard($sectionTitle, $sectionIcon, '<p class="mb-0">' . $addBtn . '</p>');
            }
            return $this->wrapCard($sectionTitle, $sectionIcon, '<p class="text-body-secondary mb-0">No data.</p>');
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

        $rowsHtml = '';
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

            $rowsHtml .= '<div class="row py-2 border-bottom">'
                . '<div class="col-lg-3 fw-semibold">' . _r($label) . ':</div>'
                . '<div class="col-lg-9">' . $value . '</div>'
                . '</div>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<p class="text-body-secondary mb-0">No view fields configured.</p>';
        }

        // Edit button for the fields section.
        $editBtnHtml = '';
        if ($editAction !== '') {
            $recordId = _absint(ModelRecordHelper::extractFieldValue($record, (string) $model->getPrimaryKey()));
            $editParams = array_merge(['id' => $recordId], $chainParams);
            $editUrl = Route::url(UrlBuilder::action($this->modulePage, $editAction, $editParams));
            $editDisplay = DisplayModeHelper::getEditMode($formContext);
            $fetchAttr = DisplayModeHelper::buildFetchAttribute($editDisplay);
            $editBtnHtml = '<a class="btn btn-sm btn-primary" href="' . _r($editUrl) . '"' . $fetchAttr . '>Edit</a>';
        }

        return $this->wrapCard($sectionTitle, $sectionIcon, $rowsHtml, $editBtnHtml);
    }

    /**
     * Render an "icon" block: compact row with check/plus icon + record title link.
     *
     * @return string HTML (a single row, NOT wrapped in a card — used inside group cards).
     */
    public function renderIcon(
        array $formContext,
        int $parentId,
        int $rootId
    ): string {
        $modelClass = (string) ($formContext['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            return $this->iconRowHtml(
                ProjectNaming::toTitle((string) ($formContext['form_name'] ?? '')),
                $this->errorHtml('Model not found.')
            );
        }

        $model = new $modelClass();
        $formName = (string) ($formContext['form_name'] ?? '');
        $formTitle = trim((string) ($formContext['form_title'] ?? ''));
        if ($formTitle === '') {
            $formTitle = ProjectNaming::toTitle($formName);
        }
        $isRoot = (bool) ($formContext['is_root'] ?? false);
        $fkField = (string) ($formContext['parent_fk_field'] ?? '');
        $editAction = (string) ($formContext['edit_action'] ?? '');
        $maxRecords = (string) ($formContext['max_records'] ?? 'n');
        $chainParams = $this->buildChainParamsForChild($formContext, $parentId, $rootId);
        $editDisplay = DisplayModeHelper::getEditMode($formContext);
        $fetchAttr = DisplayModeHelper::buildFetchAttribute($editDisplay);

        if ($isRoot) {
            $record = $model->getByIdForEdit($parentId);
            if (!is_object($record) || $record->isEmpty()) {
                return $this->iconRowHtml($formTitle, '<span class="text-body-secondary">-</span>');
            }

            $primaryKey = (string) $model->getPrimaryKey();
            $recordId = _absint(ModelRecordHelper::extractFieldValue($record, $primaryKey));
            $titleField = $this->detectTitleField($model);
            $recordTitle = $this->extractRecordTitle($record, $titleField, $recordId);

            if ($editAction === '') {
                return $this->iconRowHtml($formTitle, _r($recordTitle));
            }

            $editUrl = Route::url(UrlBuilder::action($this->modulePage, $editAction, ['id' => $recordId]));
            $innerHtml = '<div class="d-flex align-items-center justify-content-start gap-2">'
                . '<a class="text-decoration-none" href="' . _r($editUrl) . '"' . $fetchAttr . ' title="Open">'
                . '<i class="bi bi-check-circle-fill text-success"></i></a>'
                . '<a class="text-decoration-none" href="' . _r($editUrl) . '"' . $fetchAttr . '>'
                . _r($recordTitle) . '</a>'
                . '</div>';

            return $this->iconRowHtml($formTitle, $innerHtml);
        }

        if ($fkField === '') {
            return $this->iconRowHtml($formTitle, $this->errorHtml("Missing FK for '{$formName}'."));
        }

        // Load records for this child form under the given parent.
        $records = ModelRecordHelper::findAllByFk($model, $fkField, $parentId);
        $primaryKey = (string) $model->getPrimaryKey();
        $titleField = $this->detectTitleField($model);

        $innerHtml = '';

        if (is_array($records) && !empty($records)) {
            foreach ($records as $row) {
                $recordId = _absint(ModelRecordHelper::extractFieldValue($row, $primaryKey));
                $recordTitle = $this->extractRecordTitle($row, $titleField, $recordId);
                $editParams = array_merge(['id' => $recordId], $chainParams);
                $editUrl = Route::url(UrlBuilder::action($this->modulePage, $editAction, $editParams));

                $innerHtml .= '<div class="d-flex align-items-center justify-content-start gap-2">'
                    . '<a class="text-decoration-none" href="' . _r($editUrl) . '"' . $fetchAttr . ' title="Open">'
                    . '<i class="bi bi-check-circle-fill text-success"></i></a>'
                    . '<a class="text-decoration-none" href="' . _r($editUrl) . '"' . $fetchAttr . '>'
                    . _r($recordTitle) . '</a>'
                    . '</div>';
            }
        }

        // "Add new" button (respect max_records).
        $showAdd = true;
        if ($maxRecords !== 'n') {
            $finite = UrlBuilder::getFiniteMaxRecords($maxRecords);
            if ($finite > 0 && is_array($records) && count($records) >= $finite) {
                $showAdd = false;
            }
        }

        if ($showAdd && $editAction !== '') {
            $addUrl = Route::url(UrlBuilder::action($this->modulePage, $editAction, $chainParams));
            $innerHtml .= '<div class="d-flex align-items-center justify-content-start gap-2 pt-1">'
                . '<a class="text-decoration-none btn btn-outline-primary btn-sm" href="' . _r($addUrl) . '"' . $fetchAttr . ' title="Add">'
                . '<i class="bi bi-plus-circle"></i> Add new</a>'
                . '</div>';
        }

        if ($innerHtml === '') {
            $innerHtml = '<span class="text-body-secondary">-</span>';
        }

        return $this->iconRowHtml($formTitle, $innerHtml);
    }

    /**
     * Render a "table" block: HTML table with rows for each record.
     *
     * Nested child forms are resolved automatically from manifest contexts
     * (children_meta_by_alias), using the same cell behavior as standard lists.
     */
    public function renderTable(
        ViewTableConfig $tableConfig,
        array $formContext,
        int $parentId,
        int $rootId
    ): string {
        $modelClass = (string) ($formContext['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            return $this->errorHtml('Model not found.');
        }

        $model = new $modelClass();
        $formName = (string) ($formContext['form_name'] ?? '');
        $formTitle = trim($tableConfig->title !== '' ? $tableConfig->title : (string) ($formContext['form_title'] ?? ''));
        if ($formTitle === '') {
            $formTitle = ProjectNaming::toTitle($formName);
        }
        $isRoot = (bool) ($formContext['is_root'] ?? false);
        $fkField = (string) ($formContext['parent_fk_field'] ?? '');
        $editAction = (string) ($formContext['edit_action'] ?? '');
        $maxRecords = (string) ($formContext['max_records'] ?? 'n');
        $chainParams = $this->buildChainParamsForChild($formContext, $parentId, $rootId);

        if (!$isRoot && $fkField === '') {
            return $this->errorHtml("Missing FK for '{$formName}'.");
        }

        if ($isRoot) {
            $rootRecord = $model->getByIdForEdit($parentId);
            $records = (is_object($rootRecord) && !$rootRecord->isEmpty()) ? [$rootRecord] : [];
            $chainParams = [];
        } else {
            $records = ModelRecordHelper::findAllByFk($model, $fkField, $parentId);
        }
        $primaryKey = (string) $model->getPrimaryKey();

        // Determine visible columns from view rules.
        $viewRules = $model->getRules('view', true);
        if (empty($viewRules)) {
            $viewRules = $model->getRules();
        }
        $nestedCountAliases = $this->resolveNestedCountAliases($formContext);
        $columns = [];
        foreach ($viewRules as $field => $rule) {
            if ($field === '___action' || ($fkField !== '' && $field === $fkField)) {
                continue;
            }
            // Skip root_id field.
            if ($field === ProjectNaming::rootIdField()) {
                continue;
            }
            // Skip withCount aliases for nested children.
            if (isset($nestedCountAliases[$field])) {
                continue;
            }
            $columns[$field] = (string) ($rule['label'] ?? $this->toLabel($field));
        }

        // Resolve nested child contexts from manifest automatically.
        $nestedContexts = $this->resolveNestedTableContexts($formContext);

        // Build <thead>.
        $theadHtml = '<tr>';
        foreach ($columns as $label) {
            $theadHtml .= '<th scope="col">' . _r($label) . '</th>';
        }
        foreach ($nestedContexts as $nested) {
            $nestedTitle = trim((string) ($nested['context']['form_title'] ?? ''));
            if ($nestedTitle === '') {
                $nestedTitle = ProjectNaming::toTitle((string) ($nested['context']['form_name'] ?? ''));
            }
            $theadHtml .= '<th scope="col">' . _r($nestedTitle) . '</th>';
        }
        $theadHtml .= '</tr>';

        // Build <tbody>.
        $tbodyHtml = '';
        $editDisplay = DisplayModeHelper::getEditMode($formContext);
        $fetchAttr = DisplayModeHelper::buildFetchAttribute($editDisplay);

        if (is_array($records)) {
            foreach ($records as $row) {
                $recordId = _absint(ModelRecordHelper::extractFieldValue($row, $primaryKey));
                $formattedRow = is_object($row) && method_exists($row, 'getFormattedData')
                    ? $row->getFormattedData('array', false)
                    : (is_array($row) ? $row : []);
                if (!is_array($formattedRow)) {
                    $formattedRow = [];
                }

                $editParams = array_merge(['id' => $recordId], $chainParams);
                $editUrl = Route::url(UrlBuilder::action($this->modulePage, $editAction, $editParams));

                $tbodyHtml .= '<tr>';
                $firstCol = true;
                foreach ($columns as $field => $label) {
                    $val = $formattedRow[$field] ?? null;
                    $displayVal = $this->formatValue($val);
                    if ($firstCol && $editAction !== '') {
                        $displayVal = '<a class="text-decoration-none" href="' . _r($editUrl) . '"' . $fetchAttr . '>'
                            . $displayVal . '</a>';
                        $firstCol = false;
                    }
                    $tbodyHtml .= '<td>' . $displayVal . '</td>';
                }

                // Nested table columns.
                foreach ($nestedContexts as $nested) {
                    $tbodyHtml .= '<td>' . $this->renderNestedTableCell(
                        $nested['context'],
                        $formContext,
                        $recordId,
                        $rootId,
                        $parentId
                    ) . '</td>';
                }

                $tbodyHtml .= '</tr>';
            }
        }

        // "Add new" row / button.
        $addBtnHtml = '';
        $showAdd = true;
        if ($maxRecords !== 'n') {
            $finite = UrlBuilder::getFiniteMaxRecords($maxRecords);
            if ($finite > 0 && is_array($records) && count($records) >= $finite) {
                $showAdd = false;
            }
        }
        if ($showAdd && $editAction !== '') {
            $addUrl = Route::url(UrlBuilder::action($this->modulePage, $editAction, $chainParams));
            $addBtnHtml = '<div class="mt-2">'
                . '<a class="btn btn-sm btn-outline-primary" href="' . _r($addUrl) . '"' . $fetchAttr . '>'
                . '<i class="bi bi-plus-circle"></i> Add new</a></div>';
        }

        $tableHtml = '<div class="table-responsive">'
            . '<table class="table table-hover table-sm mb-0">'
            . '<thead>' . $theadHtml . '</thead>'
            . '<tbody class="table-group-divider">' . $tbodyHtml . '</tbody>'
            . '</table></div>'
            . $addBtnHtml;

        return $this->tableRowHtml($formTitle, $tableHtml, $tableConfig->hideSideTitle);
    }

    // ==================================================================
    // Nested table cell rendering
    // ==================================================================

    /**
     * Render a single cell for a nested table column.
     *
     * Behavior is aligned with AutoListRenderer child-count columns:
     * - single-record children: check/plus icon
     * - multi-record children : count badges (and add icon when empty)
     */
    protected function renderNestedTableCell(
        array $nestedContext,
        array $parentFormContext,
        int $parentRecordId,
        int $rootId,
        int $grandParentId
    ): string {
        $nestedModelClass = (string) ($nestedContext['model_class'] ?? '');
        if ($nestedModelClass === '' || !class_exists($nestedModelClass)) {
            return '<span class="text-body-secondary">-</span>';
        }

        $nestedModel = new $nestedModelClass();
        $nestedFkField = (string) ($nestedContext['parent_fk_field'] ?? '');
        $nestedEditAction = (string) ($nestedContext['edit_action'] ?? '');
        $nestedListAction = (string) ($nestedContext['list_action'] ?? '');
        $nestedMaxRecords = (string) ($nestedContext['max_records'] ?? 'n');
        $nestedHasChildren = !empty($nestedContext['children_meta_by_alias'] ?? []);
        $nestedListDisplay = DisplayModeHelper::getListMode($nestedContext);
        $nestedEditDisplay = DisplayModeHelper::getEditMode($nestedContext);

        if ($nestedFkField === '') {
            return '<span class="text-body-secondary">-</span>';
        }

        // Build chain params for the nested form.
        $nestedChainParams = $this->buildNestedChainParams(
            $nestedContext,
            $parentFormContext,
            $parentRecordId,
            $rootId,
            $grandParentId
        );

        $count = ModelRecordHelper::countByFk($nestedModel, $nestedFkField, $parentRecordId);
        // Single-record child:
        // - without children => edit
        // - with children    => list (max 1 row)
        if ($nestedMaxRecords === '1') {
            $targetAction = $nestedHasChildren ? $nestedListAction : $nestedEditAction;
            $targetDisplay = $nestedHasChildren ? $nestedListDisplay : $nestedEditDisplay;
            if ($targetAction === '') {
                $targetAction = $nestedEditAction !== '' ? $nestedEditAction : $nestedListAction;
                $targetDisplay = ($targetAction === $nestedEditAction) ? $nestedEditDisplay : $nestedListDisplay;
            }
            if ($targetAction === '') {
                return (string) $count;
            }

            $targetParams = $nestedChainParams;
            if ($targetAction === $nestedEditAction && $count > 0) {
                $existingId = ModelRecordHelper::findFirstIdByFk($nestedModel, $nestedFkField, $parentRecordId);
                if ($existingId > 0) {
                    $targetParams['id'] = $existingId;
                }
            }

            $href = UrlBuilder::action($this->modulePage, $targetAction, $targetParams);
            $fetch = DisplayModeHelper::buildFetchAttribute($targetDisplay);
            if ($count > 0) {
                return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="Open">'
                    . '<i class="bi bi-check-circle-fill text-success"></i></a>';
            }
            $createTitle = $nestedHasChildren ? 'Open list' : 'Add';
            return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="' . $createTitle . '">'
                . '<i class="bi bi-plus-circle text-primary"></i></a>';
        }

        $finiteChildMax = UrlBuilder::getFiniteMaxRecords($nestedMaxRecords);
        if ($finiteChildMax > 1 && $count >= $finiteChildMax && $nestedListAction !== '') {
            $href = UrlBuilder::action($this->modulePage, $nestedListAction, $nestedChainParams);
            $fetch = DisplayModeHelper::buildFetchAttribute($nestedListDisplay);
            return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="Limit reached">'
                . '<span class="badge text-bg-dark">' . (int) $count . '/' . (int) $finiteChildMax . '</span></a>';
        }

        // Multi-record child.
        if ($count <= 0) {
            if ($nestedListAction !== '' && $nestedListDisplay !== 'page') {
                $href = UrlBuilder::action($this->modulePage, $nestedListAction, $nestedChainParams);
                $fetch = DisplayModeHelper::buildFetchAttribute($nestedListDisplay);
                return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="Open list">'
                    . '<span class="badge text-bg-secondary">0</span></a>';
            }

            if ($nestedEditAction !== '') {
                $fetch = DisplayModeHelper::buildFetchAttribute($nestedEditDisplay);
                $addUrl = Route::url(UrlBuilder::action($this->modulePage, $nestedEditAction, $nestedChainParams));
                return '<a class="text-decoration-none" href="' . _r($addUrl) . '"' . $fetch . ' title="Add">'
                    . '<i class="bi bi-plus-circle text-primary"></i></a>';
            }

            return '<span class="text-body-secondary">-</span>';
        }

        if ($nestedListAction !== '') {
            $href = UrlBuilder::action($this->modulePage, $nestedListAction, $nestedChainParams);
            $fetch = DisplayModeHelper::buildFetchAttribute($nestedListDisplay);
            return '<a class="text-decoration-none" href="' . _r(Route::url($href)) . '"' . $fetch . ' title="Open list">'
                . '<span class="badge text-bg-secondary">' . (int) $count . '</span></a>';
        }

        if ($nestedEditAction !== '') {
            $existingId = ModelRecordHelper::findFirstIdByFk($nestedModel, $nestedFkField, $parentRecordId);
            $editParams = $nestedChainParams;
            if ($existingId > 0) {
                $editParams['id'] = $existingId;
            }
            $fetch = DisplayModeHelper::buildFetchAttribute($nestedEditDisplay);
            $editUrl = Route::url(UrlBuilder::action($this->modulePage, $nestedEditAction, $editParams));
            return '<a class="text-decoration-none" href="' . _r($editUrl) . '"' . $fetch . ' title="Open">'
                . '<i class="bi bi-check-circle-fill text-success"></i></a>';
        }

        return (string) $count;
    }

    // ==================================================================
    // Chain params helpers
    // ==================================================================

    /**
     * Build chain params for a direct child of the root record.
     *
     * @return array<string,int>
     */
    protected function buildChainParamsForChild(array $childContext, int $parentId, int $rootId): array
    {
        $params = [];
        foreach ($this->fkResolver->getChainFields($childContext) as $chainField) {
            $fkField = (string) ($childContext['parent_fk_field'] ?? '');
            if ($chainField === $fkField && $parentId > 0) {
                $params[$chainField] = $parentId;
                continue;
            }
            // For root FK field, use rootId.
            $rootFkField = $this->fkResolver->getRootFkField($childContext);
            if ($chainField === $rootFkField && $rootId > 0) {
                $params[$chainField] = $rootId;
            }
        }

        $rootFkField = $this->fkResolver->getRootFkField($childContext);
        if ($rootId > 0 && $rootFkField !== '' && !isset($params[$rootFkField])) {
            $params[$rootFkField] = $rootId;
        }

        return $params;
    }

    /**
     * Build chain params for a nested table (grandchild of root).
     *
     * @return array<string,int>
     */
    protected function buildNestedChainParams(
        array $nestedContext,
        array $parentFormContext,
        int $parentRecordId,
        int $rootId,
        int $grandParentId
    ): array {
        $params = [];

        foreach ($this->fkResolver->getChainFields($nestedContext) as $chainField) {
            $nestedFkField = (string) ($nestedContext['parent_fk_field'] ?? '');
            if ($chainField === $nestedFkField && $parentRecordId > 0) {
                $params[$chainField] = $parentRecordId;
                continue;
            }

            // Parent's FK field (link to root).
            $parentFkField = (string) ($parentFormContext['parent_fk_field'] ?? '');
            if ($chainField === $parentFkField && $grandParentId > 0) {
                $params[$chainField] = $grandParentId;
                continue;
            }

            // Root FK.
            $rootFkField = $this->fkResolver->getRootFkField($nestedContext);
            if ($chainField === $rootFkField && $rootId > 0) {
                $params[$chainField] = $rootId;
            }
        }

        $rootFkField = $this->fkResolver->getRootFkField($nestedContext);
        if ($rootId > 0 && $rootFkField !== '' && !isset($params[$rootFkField])) {
            $params[$rootFkField] = $rootId;
        }

        return $params;
    }

    // ==================================================================
    // Nested table context resolution
    // ==================================================================

    /**
     * Resolve nested child contexts automatically from manifest metadata.
     *
     * @return array<int, array{context: array}>
     */
    protected function resolveNestedTableContexts(array $parentFormContext): array
    {
        $results = [];
        $childrenMetaByAlias = is_array($parentFormContext['children_meta_by_alias'] ?? null)
            ? $parentFormContext['children_meta_by_alias']
            : [];

        foreach ($childrenMetaByAlias as $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $nestedListAction = (string) ($meta['list_action'] ?? '');
            if ($nestedListAction === '') {
                $nestedFormName = (string) ($meta['form_name'] ?? '');
                if ($nestedFormName === '') {
                    continue;
                }
                $nestedListAction = ProjectNaming::toActionSlug($nestedFormName) . '-list';
            }
            $nestedContext = $this->registry->get($nestedListAction);
            if (!is_array($nestedContext)) {
                continue;
            }
            $results[] = ['context' => $nestedContext];
        }
        return $results;
    }

    /**
     * Build a lookup of count aliases generated by withCount() for direct children.
     *
     * @return array<string,bool>
     */
    protected function resolveNestedCountAliases(array $parentFormContext): array
    {
        $aliases = [];
        $childrenMetaByAlias = is_array($parentFormContext['children_meta_by_alias'] ?? null)
            ? $parentFormContext['children_meta_by_alias']
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

    // ==================================================================
    // HTML helpers
    // ==================================================================

    protected function wrapCard(string $title, string $icon, string $bodyHtml, string $editBtnHtml = ''): string
    {
        $headerHtml = '<div class="d-flex align-items-center justify-content-between gap-2">'
            . '<div class="d-flex align-items-center gap-2">';

        if ($icon !== '') {
            $headerHtml .= '<i class="' . _r($icon) . '"></i> ';
        }
        $headerHtml .= '<h5 class="mb-0">' . _r($title) . '</h5>';

        if ($editBtnHtml !== '') {
            $headerHtml .= '<div>' . $editBtnHtml . '</div>';
        }

        $headerHtml .= '</div></div>';

        return '<div class="card mb-3">'
            . '<div class="card-header">' . $headerHtml . '</div>'
            . '<div class="card-body">' . $bodyHtml . '</div>'
            . '</div>';
    }

    protected function iconRowHtml(string $label, string $innerHtml, string $preHtml = '', string $postHtml = ''): string
    {
        return $preHtml
            . '<div class="row py-2 border-bottom">'
            . '<div class="col-lg-3 fw-semibold">' . _r($label) . ':</div>'
            . '<div class="col-lg-9">' . $innerHtml . '</div>'
            . '</div>'
            . $postHtml;
    }

    protected function tableRowHtml(string $title, string $tableHtml, bool $hideSideTitle = false): string
    {
        if ($hideSideTitle) {
            return '<div class="row py-2 border-bottom g-3">'
                . '<div class="col-12"><div class="py-2">' . $tableHtml . '</div></div>'
                . '</div>';
        }

        return '<div class="row py-2 border-bottom g-3">'
            . '<div class="col-lg-3 fw-semibold">'
            . '<div class="py-2"><h5 class="mb-0">' . _r($title) . '</h5></div>'
            . '</div>'
            . '<div class="col-lg-9"><div class="py-2">' . $tableHtml . '</div></div>'
            . '</div>';
    }

    protected function errorHtml(string $message): string
    {
        return '<p class="text-danger mb-0">' . _r($message) . '</p>';
    }

    // ==================================================================
    // Value formatting
    // ==================================================================

    protected function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '<span class="text-body-secondary">-</span>';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value)) {
            return _r((string) $value);
        }
        if (is_array($value)) {
            if ($value === []) {
                return '<span class="text-body-secondary">-</span>';
            }
            $flat = [];
            array_walk_recursive($value, function ($v) use (&$flat) {
                if (is_scalar($v) || $v === null) {
                    $flat[] = $v;
                }
            });
            return !empty($flat) ? _r(implode(', ', array_map('strval', $flat))) : _r(json_encode($value));
        }
        if ($value instanceof \DateTimeInterface) {
            return _r($value->format('Y-m-d H:i:s'));
        }
        if (is_object($value) && method_exists($value, '__toString')) {
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

    protected function detectTitleField(object $model): string
    {
        $rules = $model->getRules();
        $candidates = ['title', 'name', 'label', 'code', 'patient_code'];
        foreach ($candidates as $c) {
            if (isset($rules[$c])) {
                return $c;
            }
        }
        // Fallback: first string field.
        foreach ($rules as $field => $rule) {
            if ($field === '___action') {
                continue;
            }
            $method = (string) ($rule['method'] ?? '');
            if (in_array($method, ['string', 'text', 'varchar'], true)) {
                return $field;
            }
        }
        return '';
    }

    protected function extractRecordTitle(mixed $row, string $titleField, int $recordId): string
    {
        if ($titleField !== '') {
            $val = ModelRecordHelper::extractFieldValue($row, $titleField);
            if (is_scalar($val) && trim((string) $val) !== '') {
                return (string) $val;
            }
        }
        return '#' . $recordId;
    }
}
