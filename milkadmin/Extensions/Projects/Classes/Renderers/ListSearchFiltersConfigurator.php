<?php
namespace Extensions\Projects\Classes\Renderers;

use Builders\SearchBuilder;
use Builders\TableBuilder;

!defined('MILK_DIR') && die();

/**
 * Apply root-list search filters from normalized project configuration.
 *
 * Responsibilities:
 * - Register filter callbacks on TableBuilder.
 * - Render matching SearchBuilder controls.
 * - Keep behavior limited to root list pages only.
 */
class ListSearchFiltersConfigurator
{
    protected const SOFT_DELETE_SCOPE_FILTER_NAME = 'projects_soft_delete_scope';
    protected const SOFT_DELETE_SCOPE_ACTIVE = 'active';
    protected const SOFT_DELETE_SCOPE_DELETED = 'deleted';
    /** @var array<string,bool> */
    protected array $relationshipSearchCompatibilityCache = [];

    /**
     * Configure table filters and return SearchBuilder payload.
     *
     * @return array{html:string,field_count:int}
     */
    public function configure(TableBuilder $tableBuilder, ListContextParams $p): array
    {
        $filters = $this->resolveFiltersForContext($p);
        $searchBuilder = $this->buildSearchBuilder($tableBuilder, $p);
        if ($searchBuilder === null) {
            return ['html' => '', 'field_count' => 0];
        }

        return [
            'html' => $searchBuilder->render(),
            'field_count' => $this->countVisualFields($filters),
        ];
    }

    /**
     * Build and return SearchBuilder configured from root list filters.
     *
     * Returns null when search filters are not applicable for current context.
     */
    public function buildSearchBuilder(TableBuilder $tableBuilder, ListContextParams $p): ?SearchBuilder
    {
        $isRootListContext = $p->isRoot && !$p->isEmbeddedViewTable;
        if (!$isRootListContext && !$this->shouldRenderChildSoftDeleteFilter($p)) {
            return null;
        }

        if ($isRootListContext) {
            $this->applyUrlParamFilters($tableBuilder, $p);
        }

        $config = $isRootListContext
            ? (is_array($p->searchFilters ?? null) ? $p->searchFilters : [])
            : [];
        $filters = $this->resolveFiltersForContext($p);
        if (empty($filters)) {
            return null;
        }

        // 1) Register SQL filters on TableBuilder.
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $this->registerTableFilter($tableBuilder, $filter, $p);
        }

        // 2) Build UI from active/default filter values.
        $activeFilters = $tableBuilder->getFilters();
        $searchBuilder = SearchBuilder::create($p->tableId);
        if ($isRootListContext) {
            $this->applySearchBuilderConfig($searchBuilder, $config);
        } else {
            // Child soft-delete scope action-list is self-executing via action-list JS.
            $searchBuilder->setSearchMode('submit', false);
        }

        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $this->appendSearchField($searchBuilder, $filter, $activeFilters);
        }
        if ($isRootListContext) {
            $this->ensureSubmitButtons($searchBuilder, $filters);
        }

        return $searchBuilder;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function resolveFiltersForContext(ListContextParams $p): array
    {
        if ($p->isRoot && !$p->isEmbeddedViewTable) {
            $config = is_array($p->searchFilters ?? null) ? $p->searchFilters : [];
            return is_array($config['filters'] ?? null) ? $config['filters'] : [];
        }

        if ($this->shouldRenderChildSoftDeleteFilter($p)) {
            return [$this->buildSoftDeleteScopeFilterDefinition()];
        }

        return [];
    }

    protected function shouldRenderChildSoftDeleteFilter(ListContextParams $p): bool
    {
        return !$p->isRoot
            && $p->softDeleteEnabled
            && $p->softDeleteScopeFilterEnabled
            && $p->canManageDeleteRecords;
    }

    /**
     * @return array<string,mixed>
     */
    protected function buildSoftDeleteScopeFilterDefinition(): array
    {
        return [
            'type' => 'action_list',
            'name' => self::SOFT_DELETE_SCOPE_FILTER_NAME,
            'label' => 'Deleted records',
            'placeholder' => '',
            'layout' => 'inline',
            'class' => '',
            'input_type' => 'text',
            'options' => [
                self::SOFT_DELETE_SCOPE_ACTIVE => 'Not deleted',
                self::SOFT_DELETE_SCOPE_DELETED => 'Deleted only',
            ],
            'has_default' => true,
            'default' => self::SOFT_DELETE_SCOPE_ACTIVE,
            'query' => [
                'operator' => 'equals',
                'fields' => ['deleted_at'],
            ],
        ];
    }

    protected function applyUrlParamFilters(TableBuilder $tableBuilder, ListContextParams $p): void
    {
        if ($p->urlFilterRequiredFailed) {
            $tableBuilder->where('1 = 0', []);
            return;
        }

        if (empty($p->urlFilterWhereClauses)) {
            return;
        }

        $db = $tableBuilder->getModel()->getDb();
        if (!is_object($db)) {
            return;
        }

        foreach ($p->urlFilterWhereClauses as $clause) {
            if (!is_array($clause)) {
                continue;
            }

            $field = trim((string) ($clause['field'] ?? ''));
            $operator = trim((string) ($clause['operator'] ?? 'equals'));
            $valueRaw = $clause['value'] ?? '';
            $value = is_scalar($valueRaw) ? trim((string) $valueRaw) : '';

            if ($field === '' || $operator === '' || $value === '') {
                continue;
            }

            $condition = $this->buildWhereCondition($db, [$field], $operator, $value);
            if ($condition['sql'] === '' || empty($condition['params'])) {
                continue;
            }
            $tableBuilder->where($condition['sql'], $condition['params']);
        }
    }

    /**
     * @param array<string,mixed> $config
     */
    protected function applySearchBuilderConfig(SearchBuilder $searchBuilder, array $config): void
    {
        // Project search filters are always submit-driven to avoid onchange execution.
        $searchBuilder->setSearchMode('submit', false);

        $wrapperClass = trim((string) ($config['wrapper_class'] ?? ''));
        if ($wrapperClass !== '') {
            $searchBuilder->setWrapperClass($wrapperClass);
        }

        $formClasses = trim((string) ($config['form_classes'] ?? ''));
        if ($formClasses !== '') {
            $searchBuilder->setFormClasses($formClasses);
        }

        $containerClasses = trim((string) ($config['container_classes'] ?? ''));
        if ($containerClasses !== '') {
            $searchBuilder->setContainerClasses($containerClasses);
        }
    }

    /**
     * Ensure that submit-driven search always exposes Search/Clear actions exactly once.
     *
     * @param array<int,mixed> $filters
     */
    protected function ensureSubmitButtons(SearchBuilder $searchBuilder, array $filters): void
    {
        $hasSearchButton = false;
        $hasClearButton = false;

        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $type = strtolower(trim((string) ($filter['type'] ?? '')));
            if ($type === 'search_button') {
                $hasSearchButton = true;
            } elseif ($type === 'clear_button') {
                $hasClearButton = true;
            }

            if ($hasSearchButton && $hasClearButton) {
                return;
            }
        }

        if (!$hasSearchButton) {
            $searchBuilder->searchButton();
        }
        if (!$hasClearButton) {
            $searchBuilder->clearButton();
        }
    }

    /**
     * @param array<string,mixed> $filter
     * @param array<string,mixed> $activeFilters
     */
    protected function appendSearchField(SearchBuilder $searchBuilder, array $filter, array $activeFilters): void
    {
        $type = (string) ($filter['type'] ?? '');
        $name = (string) ($filter['name'] ?? '');
        $label = (string) ($filter['label'] ?? '');
        $placeholder = (string) ($filter['placeholder'] ?? '');

        $value = $this->resolveFilterValue($filter, $activeFilters);

        switch ($type) {
            case 'search':
            case 'search_all':
                if ($name === '') {
                    return;
                }
                $searchBuilder->search($name)
                    ->value($value);
                break;

            case 'select':
                if ($name === '') {
                    return;
                }
                $searchBuilder->select($name)
                    ->options(is_array($filter['options'] ?? null) ? $filter['options'] : [])
                    ->selected((string) $value);
                break;

            case 'action_list':
                if ($name === '') {
                    return;
                }
                $searchBuilder->actionList($name)
                    ->options(is_array($filter['options'] ?? null) ? $filter['options'] : [])
                    ->selected((string) $value);
                break;

            case 'input':
                if ($name === '') {
                    return;
                }
                $inputType = trim((string) ($filter['input_type'] ?? 'text'));
                if ($inputType === '') {
                    $inputType = 'text';
                }
                $searchBuilder->input($inputType, $name)
                    ->value($value);
                break;

            case 'search_button':
                $searchBuilder->searchButton();
                break;

            case 'clear_button':
                $searchBuilder->clearButton();
                break;

            case 'newline':
                $searchBuilder->newline();
                return;

            default:
                return;
        }

        if ($label !== '') {
            $searchBuilder->label($label);
        }
        if ($placeholder !== '') {
            $searchBuilder->placeholder($placeholder);
        }
    }

    /**
     * @param array<string,mixed> $filter
     * @param array<string,mixed> $activeFilters
     */
    protected function resolveFilterValue(array $filter, array $activeFilters): mixed
    {
        $name = (string) ($filter['name'] ?? '');
        if ($name !== '' && array_key_exists($name, $activeFilters)) {
            return $activeFilters[$name];
        }
        if (!empty($filter['has_default'])) {
            return $filter['default'] ?? '';
        }
        return '';
    }

    /**
     * @param array<string,mixed> $filter
     */
    protected function registerTableFilter(TableBuilder $tableBuilder, array $filter, ListContextParams $p): void
    {
        $type = (string) ($filter['type'] ?? '');
        if (in_array($type, ['search_button', 'clear_button', 'newline'], true)) {
            return;
        }

        $name = trim((string) ($filter['name'] ?? ''));
        if ($name === '') {
            return;
        }

        if ($this->isSoftDeleteScopeFilter($filter, $p)) {
            $this->registerSoftDeleteScopeFilter($tableBuilder, $filter);
            return;
        }

        $query = is_array($filter['query'] ?? null) ? $filter['query'] : [];
        $operator = trim((string) ($query['operator'] ?? ''));
        $fields = is_array($query['fields'] ?? null) ? $query['fields'] : [];
        $fields = array_values(array_filter($fields, static function ($field): bool {
            return is_string($field) && $field !== '';
        }));

        $model = $tableBuilder->getModel();
        $db = $model->getDb();
        if (!is_object($db)) {
            return;
        }

        if (in_array(strtolower($type), ['search', 'search_all'], true)) {
            $operator = 'like';
            $fields = $this->resolveSearchAllFields($model);
        }

        if ($operator === '' || empty($fields)) {
            return;
        }

        $callback = function ($queryBuilder, $value) use ($db, $model, $fields, $operator): void {
            $normalizedValue = is_scalar($value) ? trim((string) $value) : '';
            if ($normalizedValue === '') {
                return;
            }

            $condition = $this->buildWhereCondition($db, $fields, $operator, $normalizedValue, $model);
            if ($condition['sql'] === '' || empty($condition['params'])) {
                return;
            }

            $queryBuilder->where($condition['sql'], $condition['params']);
        };

        if (!empty($filter['has_default'])) {
            $tableBuilder->filter($name, $callback, $filter['default'] ?? null);
            return;
        }

        $tableBuilder->filter($name, $callback);
    }

    /**
     * @param array<string,mixed> $filter
     */
    protected function isSoftDeleteScopeFilter(array $filter, ListContextParams $p): bool
    {
        if (!$p->softDeleteEnabled || !$p->softDeleteScopeFilterEnabled) {
            return false;
        }

        $type = strtolower(trim((string) ($filter['type'] ?? '')));
        if ($type !== 'action_list') {
            return false;
        }

        $name = strtolower(trim((string) ($filter['name'] ?? '')));
        return $name === self::SOFT_DELETE_SCOPE_FILTER_NAME;
    }

    /**
     * @param array<string,mixed> $filter
     */
    protected function registerSoftDeleteScopeFilter(TableBuilder $tableBuilder, array $filter): void
    {
        $model = $tableBuilder->getModel();
        $rules = $model->getRules();
        if (!isset($rules['deleted_at'])) {
            return;
        }

        $db = $model->getDb();
        if (!is_object($db)) {
            return;
        }

        $name = trim((string) ($filter['name'] ?? self::SOFT_DELETE_SCOPE_FILTER_NAME));
        if ($name === '') {
            $name = self::SOFT_DELETE_SCOPE_FILTER_NAME;
        }

        $tableBuilder->customData('projects_soft_delete', '1');

        $defaultValue = self::SOFT_DELETE_SCOPE_ACTIVE;
        if (!empty($filter['has_default'])) {
            $defaultValue = $this->normalizeSoftDeleteScopeValue((string) ($filter['default'] ?? self::SOFT_DELETE_SCOPE_ACTIVE));
        }

        $callback = function ($queryBuilder, $value) use ($db, $model): void {
            $scope = $this->normalizeSoftDeleteScopeValue((string) (is_scalar($value) ? $value : ''));
            $isDeletedScope = ($scope === self::SOFT_DELETE_SCOPE_DELETED);
            $condition = $this->buildDeletedAtScopeCondition($db, $model, $isDeletedScope);
            if ($condition === '') {
                return;
            }

            $queryBuilder->where($condition);
        };

        $tableBuilder->filter($name, $callback, $defaultValue);
    }

    protected function normalizeSoftDeleteScopeValue(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === self::SOFT_DELETE_SCOPE_DELETED) {
            return self::SOFT_DELETE_SCOPE_DELETED;
        }
        return self::SOFT_DELETE_SCOPE_ACTIVE;
    }

    protected function buildDeletedAtScopeCondition(object $db, object $model, bool $deleted): string
    {
        $condition = $deleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';

        if (!method_exists($model, 'getTable')) {
            return $condition;
        }

        $tableName = trim((string) $model->getTable());
        if ($tableName === '' || !method_exists($db, 'qn')) {
            return $condition;
        }

        return $db->qn($tableName) . '.' . $db->qn('deleted_at') . ($deleted ? ' IS NOT NULL' : ' IS NULL');
    }

    /**
     * @param array<int,string> $fields
     * @return array{sql:string,params:array<int,string>}
     */
    protected function buildWhereCondition(
        object $db,
        array $fields,
        string $operator,
        string $value,
        ?object $model = null
    ): array
    {
        $betweenRange = null;
        if ($operator === 'between') {
            $betweenRange = $this->parseBetweenRange($value);
            if ($betweenRange === null) {
                return ['sql' => '', 'params' => []];
            }
        }

        $relationshipsByAlias = $this->resolveRelationshipsByAlias($model);
        $mainTable = $this->resolveModelTable($model);

        $sqlParts = [];
        $params = [];
        foreach ($fields as $field) {
            $field = trim((string) $field);
            if ($field === '') {
                continue;
            }

            $relationToken = $this->parseRelationFieldToken($field);
            if ($relationToken !== null) {
                if ($mainTable === '') {
                    continue;
                }
                $relationAlias = $relationToken['alias'];
                if (!isset($relationshipsByAlias[$relationAlias])) {
                    continue;
                }

                $relationship = $relationshipsByAlias[$relationAlias];
                $relatedField = $relationToken['field'];
                $relatedQualifiedField = $relationship['related_table'] . '.' . $relatedField;
                $relatedCondition = $this->buildSingleFieldCondition(
                    $db,
                    $relatedQualifiedField,
                    $operator,
                    $value,
                    $betweenRange
                );
                if ($relatedCondition['sql'] === '' || empty($relatedCondition['params'])) {
                    continue;
                }

                $existsSql = $this->buildRelationshipExistsSql($db, $mainTable, $relationship, $relatedCondition['sql']);
                if ($existsSql === '') {
                    continue;
                }

                $sqlParts[] = $existsSql;
                $params = array_merge($params, $relatedCondition['params']);
                continue;
            }

            $condition = $this->buildSingleFieldCondition($db, $field, $operator, $value, $betweenRange);
            if ($condition['sql'] === '' || empty($condition['params'])) {
                continue;
            }
            $sqlParts[] = $condition['sql'];
            $params = array_merge($params, $condition['params']);
        }

        if (empty($sqlParts)) {
            return ['sql' => '', 'params' => []];
        }

        if (count($sqlParts) === 1) {
            return [
                'sql' => $sqlParts[0],
                'params' => $params,
            ];
        }

        return [
            'sql' => '(' . implode(' OR ', $sqlParts) . ')',
            'params' => $params,
        ];
    }

    /**
     * @return array{sql:string,params:array<int,string>}
     */
    protected function buildSingleFieldCondition(
        object $db,
        string $field,
        string $operator,
        string $value,
        ?array $betweenRange
    ): array {
        $quoted = method_exists($db, 'qn') ? (string) $db->qn($field) : $field;
        if ($quoted === '') {
            return ['sql' => '', 'params' => []];
        }

        if ($operator === 'equals') {
            return ['sql' => $quoted . ' = ?', 'params' => [$value]];
        }

        if ($operator === 'greater_than') {
            return ['sql' => $quoted . ' > ?', 'params' => [$value]];
        }

        if ($operator === 'greater_or_equal') {
            return ['sql' => $quoted . ' >= ?', 'params' => [$value]];
        }

        if ($operator === 'less_than') {
            return ['sql' => $quoted . ' < ?', 'params' => [$value]];
        }

        if ($operator === 'less_or_equal') {
            return ['sql' => $quoted . ' <= ?', 'params' => [$value]];
        }

        if ($operator === 'between') {
            if (!is_array($betweenRange) || count($betweenRange) !== 2) {
                return ['sql' => '', 'params' => []];
            }
            return ['sql' => $quoted . ' BETWEEN ? AND ?', 'params' => [(string) $betweenRange[0], (string) $betweenRange[1]]];
        }

        $preparedValue = $value;
        if ($operator === 'like') {
            $preparedValue = '%' . $value . '%';
        } elseif ($operator === 'starts_with') {
            $preparedValue = $value . '%';
        } elseif ($operator === 'ends_with') {
            $preparedValue = '%' . $value;
        }

        return ['sql' => $quoted . ' LIKE ?', 'params' => [$preparedValue]];
    }

    /**
     * @return array{alias:string,field:string}|null
     */
    protected function parseRelationFieldToken(string $field): ?array
    {
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)$/', $field, $matches) !== 1) {
            return null;
        }

        return [
            'alias' => (string) $matches[1],
            'field' => (string) $matches[2],
        ];
    }

    /**
     * @return array<string,array{
     *   type:string,
     *   related_table:string,
     *   foreign_key:string,
     *   local_key:string,
     *   related_key:string
     * }>
     */
    protected function resolveRelationshipsByAlias(?object $model): array
    {
        if (!is_object($model) || !method_exists($model, 'getRules')) {
            return [];
        }

        $rules = $model->getRules();
        if (!is_array($rules)) {
            return [];
        }

        $relationships = [];
        foreach ($rules as $ruleRaw) {
            $rule = is_array($ruleRaw) ? $ruleRaw : [];
            $relationship = is_array($rule['relationship'] ?? null) ? $rule['relationship'] : [];
            if (empty($relationship)) {
                continue;
            }

            $alias = trim((string) ($relationship['alias'] ?? ''));
            if (!$this->isSafeIdentifier($alias) || isset($relationships[$alias])) {
                continue;
            }

            $type = strtolower(trim((string) ($relationship['type'] ?? '')));
            if (!in_array($type, ['belongsto', 'hasone', 'hasmany'], true)) {
                continue;
            }

            $relatedModelClass = trim((string) ($relationship['related_model'] ?? ''));
            if ($relatedModelClass === '' || !class_exists($relatedModelClass)) {
                continue;
            }

            try {
                $relatedModel = new $relatedModelClass();
            } catch (\Throwable) {
                continue;
            }

            if (!$this->isRelationshipSearchCompatible($model, $relatedModelClass, $relatedModel)) {
                continue;
            }

            $relatedTable = $this->resolveModelTable($relatedModel);
            if ($relatedTable === '') {
                continue;
            }

            $foreignKey = trim((string) ($relationship['foreign_key'] ?? ''));
            $localKey = trim((string) ($relationship['local_key'] ?? ''));
            $relatedKey = trim((string) ($relationship['related_key'] ?? ''));

            if ($type === 'belongsto') {
                if (!$this->isSafeIdentifier($foreignKey) || !$this->isSafeIdentifier($relatedKey)) {
                    continue;
                }
                $relationships[$alias] = [
                    'type' => $type,
                    'related_table' => $relatedTable,
                    'foreign_key' => $foreignKey,
                    'local_key' => '',
                    'related_key' => $relatedKey,
                ];
                continue;
            }

            if (!$this->isSafeIdentifier($foreignKey) || !$this->isSafeIdentifier($localKey)) {
                continue;
            }

            $relationships[$alias] = [
                'type' => $type,
                'related_table' => $relatedTable,
                'foreign_key' => $foreignKey,
                'local_key' => $localKey,
                'related_key' => '',
            ];
        }

        return $relationships;
    }

    /**
     * @return array<int,string>
     */
    protected function resolveSearchAllFields(object $model): array
    {
        if (!method_exists($model, 'getRules')) {
            return [];
        }

        $rules = $model->getRules();
        if (!is_array($rules) || empty($rules)) {
            return [];
        }

        $fields = [];
        $seen = [];
        foreach ($rules as $fieldName => $ruleRaw) {
            $name = trim((string) $fieldName);
            if (!$this->isSafeIdentifier($name)) {
                continue;
            }

            $rule = is_array($ruleRaw) ? $ruleRaw : [];
            $isVirtual = $this->normalizeBool($rule['virtual'] ?? false);
            $isSqlEnabled = !array_key_exists('sql', $rule) || $this->normalizeBool($rule['sql']);
            $isWithCount = !empty($rule['withCount']);
            if ($isVirtual || !$isSqlEnabled || $isWithCount) {
                continue;
            }

            $fieldKey = strtolower($name);
            if (!isset($seen[$fieldKey])) {
                $seen[$fieldKey] = true;
                $fields[] = $name;
            }

            $relationship = is_array($rule['relationship'] ?? null) ? $rule['relationship'] : [];
            if (empty($relationship)) {
                continue;
            }

            $relationshipType = strtolower(trim((string) ($relationship['type'] ?? '')));
            if (!in_array($relationshipType, ['belongsto', 'hasone', 'hasmany'], true)) {
                continue;
            }

            $alias = trim((string) ($relationship['alias'] ?? ''));
            if (!$this->isSafeIdentifier($alias)) {
                continue;
            }

            $relatedModelClass = trim((string) ($relationship['related_model'] ?? ''));
            if ($relatedModelClass === '' || !class_exists($relatedModelClass)) {
                continue;
            }

            try {
                $relatedModel = new $relatedModelClass();
            } catch (\Throwable) {
                continue;
            }

            if (!$this->isRelationshipSearchCompatible($model, $relatedModelClass, $relatedModel)) {
                continue;
            }

            if (!method_exists($relatedModel, 'getRules')) {
                continue;
            }

            $relatedRules = $relatedModel->getRules();
            if (!is_array($relatedRules) || empty($relatedRules)) {
                continue;
            }

            foreach ($relatedRules as $relatedFieldName => $relatedRuleRaw) {
                $relatedName = trim((string) $relatedFieldName);
                if (!$this->isSafeIdentifier($relatedName)) {
                    continue;
                }

                $relatedRule = is_array($relatedRuleRaw) ? $relatedRuleRaw : [];
                $relatedIsVirtual = $this->normalizeBool($relatedRule['virtual'] ?? false);
                $relatedIsSqlEnabled = !array_key_exists('sql', $relatedRule) || $this->normalizeBool($relatedRule['sql']);
                $relatedIsWithCount = !empty($relatedRule['withCount']);
                if ($relatedIsVirtual || !$relatedIsSqlEnabled || $relatedIsWithCount) {
                    continue;
                }

                $queryField = $alias . '.' . $relatedName;
                $queryFieldKey = strtolower($queryField);
                if (isset($seen[$queryFieldKey])) {
                    continue;
                }
                $seen[$queryFieldKey] = true;
                $fields[] = $queryField;
            }
        }

        return $fields;
    }

    /**
     * Returns false when relationship search would require querying a different db connection type.
     */
    protected function isRelationshipSearchCompatible(
        object $model,
        string $relatedModelClass,
        ?object $relatedModel = null
    ): bool {
        $mainDbType = '';
        if (method_exists($model, 'getDbType')) {
            $mainDbType = strtolower(trim((string) $model->getDbType()));
        }

        $cacheKey = $mainDbType . '|' . $relatedModelClass;
        if (array_key_exists($cacheKey, $this->relationshipSearchCompatibilityCache)) {
            return $this->relationshipSearchCompatibilityCache[$cacheKey];
        }

        if ($relatedModel === null) {
            if (!class_exists($relatedModelClass)) {
                $this->relationshipSearchCompatibilityCache[$cacheKey] = false;
                return false;
            }

            try {
                $relatedModel = new $relatedModelClass();
            } catch (\Throwable) {
                $this->relationshipSearchCompatibilityCache[$cacheKey] = false;
                return false;
            }
        }

        $relatedDbType = '';
        if (method_exists($relatedModel, 'getDbType')) {
            $relatedDbType = strtolower(trim((string) $relatedModel->getDbType()));
        }

        if ($mainDbType === '' || $relatedDbType === '') {
            $this->relationshipSearchCompatibilityCache[$cacheKey] = true;
            return true;
        }

        $isCompatible = ($mainDbType === $relatedDbType);
        $this->relationshipSearchCompatibilityCache[$cacheKey] = $isCompatible;
        return $isCompatible;
    }

    protected function resolveModelTable(?object $model): string
    {
        if (!is_object($model) || !method_exists($model, 'getTable')) {
            return '';
        }

        return trim((string) $model->getTable());
    }

    /**
     * @param array{
     *   type:string,
     *   related_table:string,
     *   foreign_key:string,
     *   local_key:string,
     *   related_key:string
     * } $relationship
     */
    protected function buildRelationshipExistsSql(object $db, string $mainTable, array $relationship, string $condition): string
    {
        $relatedTable = trim((string) ($relationship['related_table'] ?? ''));
        $relationshipType = strtolower(trim((string) ($relationship['type'] ?? '')));
        if ($relatedTable === '' || $mainTable === '' || $condition === '') {
            return '';
        }

        if ($relationshipType === 'hasone' || $relationshipType === 'hasmany') {
            $foreignKey = trim((string) ($relationship['foreign_key'] ?? ''));
            $localKey = trim((string) ($relationship['local_key'] ?? ''));
            if (!$this->isSafeIdentifier($foreignKey) || !$this->isSafeIdentifier($localKey)) {
                return '';
            }

            return sprintf(
                'EXISTS (SELECT 1 FROM %s WHERE %s.%s = %s.%s AND (%s))',
                $db->qn($relatedTable),
                $db->qn($relatedTable),
                $db->qn($foreignKey),
                $db->qn($mainTable),
                $db->qn($localKey),
                $condition
            );
        }

        if ($relationshipType === 'belongsto') {
            $foreignKey = trim((string) ($relationship['foreign_key'] ?? ''));
            $relatedKey = trim((string) ($relationship['related_key'] ?? ''));
            if (!$this->isSafeIdentifier($foreignKey) || !$this->isSafeIdentifier($relatedKey)) {
                return '';
            }

            return sprintf(
                'EXISTS (SELECT 1 FROM %s WHERE %s.%s = %s.%s AND (%s))',
                $db->qn($relatedTable),
                $db->qn($relatedTable),
                $db->qn($relatedKey),
                $db->qn($mainTable),
                $db->qn($foreignKey),
                $condition
            );
        }

        return '';
    }

    protected function isSafeIdentifier(string $value): bool
    {
        return $value !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    protected function normalizeBool(mixed $value): bool
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

    /**
     * Parse a between range from a single input string.
     *
     * Supported formats:
     * - "10,20"
     * - "10..20"
     * - "10 - 20"
     *
     * @return array{0:string,1:string}|null
     */
    protected function parseBetweenRange(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $min = '';
        $max = '';

        if (strpos($value, '..') !== false) {
            [$min, $max] = array_pad(explode('..', $value, 2), 2, '');
        } elseif (preg_match('/^\s*([^,]+)\s*,\s*([^,]+)\s*$/', $value, $matches) === 1) {
            $min = (string) $matches[1];
            $max = (string) $matches[2];
        } elseif (preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)\s*-\s*([0-9]+(?:\.[0-9]+)?)\s*$/', $value, $matches) === 1) {
            $min = (string) $matches[1];
            $max = (string) $matches[2];
        }

        $min = trim($min);
        $max = trim($max);
        if ($min === '' || $max === '') {
            return null;
        }

        return [$min, $max];
    }

    /**
     * Count actual filter input fields (excluding action buttons).
     *
     * @param array<int,mixed> $filters
     */
    protected function countVisualFields(array $filters): int
    {
        $count = 0;
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $type = (string) ($filter['type'] ?? '');
            if (in_array($type, ['search_button', 'clear_button', 'newline'], true)) {
                continue;
            }
            $count++;
        }
        return $count;
    }
}
