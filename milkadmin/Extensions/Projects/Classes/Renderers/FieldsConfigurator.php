<?php
namespace Extensions\Projects\Classes\Renderers;

use App\ExpressionParser;
use Builders\TableBuilder;
use Extensions\Projects\Classes\{ProjectJsonStore, ProjectNaming};
use Extensions\Projects\Classes\Module\DisplayModeHelper;

!defined('MILK_DIR') && die();

/**
 * Configures visible fields and column rendering for table output.
 */
class FieldsConfigurator
{
    protected const SOFT_DELETE_SCOPE_DELETED = 'deleted';

    protected LinkConfigurator $linkConfigurator;
    protected ChildCountConfigurator $childCountConfigurator;
    /** @var array<string,bool> */
    protected array $relationshipSortCompatibilityCache = [];

    public function __construct(
        LinkConfigurator $linkConfigurator,
        ChildCountConfigurator $childCountConfigurator
    ) {
        $this->linkConfigurator = $linkConfigurator;
        $this->childCountConfigurator = $childCountConfigurator;
    }

    public function configure(TableBuilder $tb, ListContextParams $p, string $editUrl, string $viewUrl): void
    {
        $fields = $this->resolveVisibleFields($p);
        $modelRules = $p->model->getRules();
        $virtualCalculatedFields = $this->prepareVirtualCalculatedFields(
            $tb,
            $fields,
            $modelRules,
            $p->childrenMetaByAlias
        );

        // Rebuild visible fields explicitly to avoid keeping stale/default columns.
        $tb->resetFields();

        $this->applyFieldColumns(
            $tb,
            $p,
            $fields,
            $editUrl,
            $viewUrl,
            $modelRules,
            $virtualCalculatedFields
        );
    }

    /**
     * @return array<int,string>
     */
    protected function resolveVisibleFields(ListContextParams $p): array
    {
        $listRules = $p->model->getRules('list', true);
        if (empty($listRules)) {
            $listRules = $p->model->getRules();
        }

        $fields = array_keys($listRules);
        $primaryKey = (string) ($p->primaryKey ?? '');
        if ($primaryKey !== '') {
            $fields = array_values(array_filter(
                $fields,
                static fn($field): bool => (string) $field !== $primaryKey
            ));
        }

        $jsonFieldOrder = $this->resolveSchemaFieldOrderMap($p);
        if (!empty($jsonFieldOrder)) {
            $fields = $this->orderFieldsBySchemaMap($fields, $jsonFieldOrder);
        }

        // Append withCount columns for direct children using per-child visibility override.
        if (!empty($p->childrenMetaByAlias)) {
            $withCountRules = $p->model->getRules('withCount', true);
            foreach (array_keys((array) $withCountRules) as $wcField) {
                $meta = is_array($p->childrenMetaByAlias[$wcField] ?? null)
                    ? $p->childrenMetaByAlias[$wcField]
                    : [];
                if (!$this->shouldRenderChildCountColumn($p, $meta)) {
                    continue;
                }
                if (!in_array($wcField, $fields, true)) {
                    $fields[] = $wcField;
                }
            }
        }

        $fields = $this->stripDeletedAuditFields($fields);
        return $this->appendDeletedScopeAuditFields($fields, $p);
    }

    /**
     * @param array<int,string> $fields
     * @return array<int,string>
     */
    protected function stripDeletedAuditFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $fieldName = trim((string) $field);
            $fieldLower = strtolower($fieldName);
            if ($fieldName === '' || $fieldLower === 'deleted_at' || $fieldLower === 'deleted_by') {
                continue;
            }
            $result[] = $fieldName;
        }

        return array_values(array_unique($result));
    }

    /**
     * @param array<int,string> $fields
     * @return array<int,string>
     */
    protected function appendDeletedScopeAuditFields(array $fields, ListContextParams $p): array
    {
        if (!$this->isDeletedOnlyScope($p)) {
            return $fields;
        }

        $rules = $p->model->getRules();
        if (!is_array($rules) || empty($rules)) {
            return $fields;
        }

        $auditFields = [];
        foreach (['deleted_at', 'deleted_by'] as $fieldName) {
            if (array_key_exists($fieldName, $rules)) {
                $auditFields[] = $fieldName;
            }
        }
        if (empty($auditFields)) {
            return $fields;
        }

        $normalized = [];
        foreach ($fields as $field) {
            $fieldName = trim((string) $field);
            if ($fieldName === '' || in_array($fieldName, $auditFields, true)) {
                continue;
            }
            $normalized[] = $fieldName;
        }

        foreach ($auditFields as $auditField) {
            $normalized[] = $auditField;
        }

        return array_values(array_unique($normalized));
    }

    protected function isDeletedOnlyScope(ListContextParams $p): bool
    {
        return $p->softDeleteEnabled
            && $p->softDeleteScopeFilterEnabled
            && strtolower(trim((string) $p->softDeleteScope)) === self::SOFT_DELETE_SCOPE_DELETED;
    }

    /**
     * Resolve visibility for a single child count column.
     *
     * Default behavior comes from list context; child metadata can override with
     * `child_count_column = show|hide`.
     *
     * @param array<string,mixed> $meta
     */
    protected function shouldRenderChildCountColumn(ListContextParams $p, array $meta): bool
    {
        $mode = strtolower(trim((string) ($meta['child_count_column'] ?? '')));
        if ($mode === 'show') {
            return true;
        }
        if ($mode === 'hide') {
            return false;
        }

        return $p->showChildCountColumns;
    }

    protected function applyDeletedByUserNameFallbackFormatter(
        TableBuilder $tb,
        ListContextParams $p,
        string $fieldName,
        string $listFieldKey
    ): void {
        if (!$this->isDeletedOnlyScope($p)) {
            return;
        }

        if (strtolower(trim($fieldName)) !== 'deleted_by') {
            return;
        }

        // When list key differs, belongsTo mapping is active already.
        if (strtolower(trim($listFieldKey)) !== 'deleted_by') {
            return;
        }

        $tb->fn(static function ($result) {
            $deletedBy = null;

            if (is_object($result) && method_exists($result, 'getRawData')) {
                $row = $result->getRawData('array', false);
                if (is_array($row)) {
                    $deletedBy = $row['deleted_by'] ?? null;
                }
            }

            $userId = _absint($deletedBy);
            if ($userId <= 0) {
                return '';
            }

            static $usernamesById = [];
            if (isset($usernamesById[$userId])) {
                return $usernamesById[$userId];
            }

            $username = '';
            try {
                $username = trim((string) \App\Get::db()->getVar(
                    'SELECT username FROM `#__users` WHERE id = ? LIMIT 1',
                    [$userId]
                ));
            } catch (\Throwable) {
                $username = '';
            }

            if ($username === '') {
                $username = '#' . $userId;
            }

            $usernamesById[$userId] = $username;
            return $username;
        });
    }

    /**
     * @return array<string,int>
     */
    protected function resolveSchemaFieldOrderMap(ListContextParams $p): array
    {
        $schemaPath = $this->resolveSchemaPathForContext($p);
        if ($schemaPath === '') {
            return [];
        }

        $schema = $this->loadSchemaFromPath($schemaPath);

        $modelSection = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $fieldDefs = is_array($modelSection['fields'] ?? null) ? $modelSection['fields'] : [];
        if (empty($fieldDefs)) {
            return [];
        }

        $orderMap = [];
        $position = 0;
        foreach ($fieldDefs as $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }
            $name = strtolower(trim((string) ($fieldDef['name'] ?? '')));
            if ($name === '' || isset($orderMap[$name])) {
                continue;
            }
            $orderMap[$name] = $position;
            $position++;
        }

        return $orderMap;
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

    /**
     * @param array<int,string> $fields
     * @param array<string,int> $schemaOrderMap
     * @return array<int,string>
     */
    protected function orderFieldsBySchemaMap(array $fields, array $schemaOrderMap): array
    {
        if (empty($fields) || empty($schemaOrderMap)) {
            return $fields;
        }

        $orderedBySchema = [];
        $extras = [];
        foreach ($fields as $field) {
            $fieldName = trim((string) $field);
            if ($fieldName === '') {
                continue;
            }

            $fieldLower = strtolower($fieldName);
            if (isset($schemaOrderMap[$fieldLower])) {
                $orderedBySchema[(int) $schemaOrderMap[$fieldLower]] = $fieldName;
                continue;
            }

            $extras[] = $fieldName;
        }

        if (!empty($orderedBySchema)) {
            ksort($orderedBySchema, SORT_NUMERIC);
        }

        return array_values(array_merge(array_values($orderedBySchema), $extras));
    }

    protected function resolveSchemaPathForContext(ListContextParams $p): string
    {
        $formName = trim((string) ($p->context['form_name'] ?? $p->modelName ?? ''));
        if ($formName === '') {
            return '';
        }

        try {
            $reflection = new \ReflectionClass($p->model);
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

        foreach (['Project', 'Projects'] as $folder) {
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
            return (string) $matches[1];
        }
        return dirname($modelFilePath);
    }

    /**
     * @param array<int,string> $fields
     * @param array<string,mixed> $modelRules
     * @param array<string,string> $virtualCalculatedFields
     */
    protected function applyFieldColumns(
        TableBuilder $tb,
        ListContextParams $p,
        array $fields,
        string $editUrl,
        string $viewUrl,
        array $modelRules = [],
        array $virtualCalculatedFields = []
    ): void {
        if (empty($modelRules)) {
            $modelRules = $p->model->getRules();
        }
     
        $embeddedTitleField = $p->isEmbeddedViewTable ? $this->linkConfigurator->resolveModelTitleField($modelRules) : null;
        $embeddedTitleOnlyMode = $p->isEmbeddedViewTable
            && $embeddedTitleField !== null
            && count($fields) === 1
            && ((string) ($fields[0] ?? '') === (string) $embeddedTitleField);
        if ($embeddedTitleOnlyMode) {
            $tb->setShowHeader(false);
        }

        $linkField = $this->linkConfigurator->determineLinkField($p, $fields, $modelRules);
        $linkFieldRule = is_string($linkField) ? ($modelRules[$linkField] ?? []) : [];
        $linkFieldListKey = is_string($linkField)
            ? $this->linkConfigurator->resolveListFieldKey($linkField, is_array($linkFieldRule) ? $linkFieldRule : [])
            : null;

        [$rowLinkAction, $rowLinkUrl] = $this->linkConfigurator->resolveRowLinkTarget($p, $editUrl, $viewUrl);
        $rowLinkDisplay = $this->linkConfigurator->resolveRowLinkDisplay($p);
        $rowLinkFetchMethod = DisplayModeHelper::getFetchMethod($rowLinkDisplay);
        $orderedVisibleFields = [];

        foreach ($fields as $field) {
            if (isset($p->childrenMetaByAlias[$field])) {
                $childMeta = (array) $p->childrenMetaByAlias[$field];
                if (!$this->shouldRenderChildCountColumn($p, $childMeta)) {
                    continue;
                }
                $this->childCountConfigurator->buildChildCountColumn(
                    $tb,
                    $p,
                    (string) $field,
                    $childMeta
                );
                $orderedVisibleFields[] = (string) $field;
                continue;
            }

            $fieldName = (string) $field;
            $fieldRule = is_array($modelRules[$fieldName] ?? null) ? $modelRules[$fieldName] : [];
            $listFieldKey = $this->linkConfigurator->resolveListFieldKey($fieldName, $fieldRule);

            $tb->field($listFieldKey);
            $orderedVisibleFields[] = $listFieldKey;
            if ($this->shouldDisableSortForField($p, $fieldRule)) {
                $tb->noSort();
            }

            if ($listFieldKey !== $fieldName) {
                $label = trim((string) ($fieldRule['label'] ?? ''));
                if ($label !== '') {
                    $tb->label($label);
                }
            }

            if (isset($virtualCalculatedFields[$field])) {
                $this->applyVirtualCalculatedFieldFormatter(
                    $tb,
                    (string) $field,
                    (string) $virtualCalculatedFields[$field]
                );
            }

            $this->applyDeletedByUserNameFallbackFormatter(
                $tb,
                $p,
                $fieldName,
                $listFieldKey
            );

            $listOpts = is_array($fieldRule['list_options'] ?? null) ? $fieldRule['list_options'] : [];

            $linkCfg = is_array($listOpts['link'] ?? null) ? $listOpts['link'] : [];
            $fieldListLinkUrl = trim((string) ($linkCfg['url'] ?? ''));
            if ($p->allowEditEnabled) {
                if ($fieldListLinkUrl !== '') {
                    $linkHtmlOpts = [];
                    if (trim((string) ($linkCfg['target'] ?? '')) === 'new_window') {
                        $linkHtmlOpts['target'] = '_blank';
                    }
                    $tb->link($fieldListLinkUrl, $linkHtmlOpts);
                } elseif ($listFieldKey === $linkFieldListKey && $rowLinkAction !== '' && $p->primaryKey !== '') {
                    $linkOptions = [];
                    if ($rowLinkFetchMethod !== null) {
                        $linkOptions['data-fetch'] = $rowLinkFetchMethod;
                    }
                    $tb->link($rowLinkUrl, $linkOptions);
                }
            }

            if (!empty($listOpts['html'])) {
                $tb->type('html');
            }

            $truncate = (int) ($listOpts['truncate'] ?? 0);
            if ($truncate > 0) {
                $tb->truncate($truncate);
            }

            $changeValues = is_array($listOpts['changeValues'] ?? null) ? $listOpts['changeValues'] : [];
            if (!empty($changeValues)) {
                $tb->options($changeValues);
            }

            $formType = strtolower(trim((string) ($fieldRule['form-type'] ?? '')));
            $hasListLinkBehavior = $fieldListLinkUrl !== ''
                || ($listFieldKey === $linkFieldListKey && $rowLinkAction !== '' && $p->primaryKey !== '');
            if ($formType === 'image' && !$hasListLinkBehavior) {
                $tb->image([
                    'size' => 42,
                    'max_images' => 3,
                    'overlap' => 20,
                    'show_more_indicator' => false,
                ]);
            }
            if ($formType === 'file' && !$hasListLinkBehavior) {
                $tb->file([
                    'max_files' => 3,
                    'show_more_indicator' => true,
                    'display_mode' => 'text',
                    'strip_extension' => true,
                    'max_name_length' => 26,
                    'text_size' => '0.85rem',
                    'text_line_height' => '1.15',
                ]);
            }

            $relationship = is_array($fieldRule['relationship'] ?? null) ? $fieldRule['relationship'] : [];
            $relationType = strtolower(trim((string) ($relationship['type'] ?? '')));
            $relationAlias = trim((string) ($relationship['alias'] ?? ''));
            $relationFields = is_array($listOpts['relationFields'] ?? null)
                ? $listOpts['relationFields']
                : (is_array($listOpts['relation_fields'] ?? null) ? $listOpts['relation_fields'] : []);
            if ($relationType === 'belongsto' && $relationAlias !== '' && !empty($relationFields)) {
                $currentListKeyLower = strtolower($listFieldKey);
                foreach ($relationFields as $relationFieldEntry) {
                    $relationFieldName = '';
                    $customColumnLabel = '';
                    if (is_array($relationFieldEntry)) {
                        $relationFieldName = trim((string) ($relationFieldEntry['field'] ?? ($relationFieldEntry['name'] ?? '')));
                        $customColumnLabel = trim((string) ($relationFieldEntry['label'] ?? ''));
                    } else {
                        $relationFieldName = trim((string) $relationFieldEntry);
                    }
                    if ($relationFieldName === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $relationFieldName) !== 1) {
                        continue;
                    }

                    $extraListFieldKey = $relationAlias . '.' . $relationFieldName;
                    if (strtolower($extraListFieldKey) === $currentListKeyLower) {
                        continue;
                    }
                    if (in_array($extraListFieldKey, $orderedVisibleFields, true)) {
                        continue;
                    }

                    $tb->field($extraListFieldKey);
                    $orderedVisibleFields[] = $extraListFieldKey;
                    if ($this->shouldDisableSortForField($p, $fieldRule)) {
                        $tb->noSort();
                    }

                    if ($customColumnLabel !== '') {
                        $tb->label($customColumnLabel);
                    } else {
                        $baseLabel = trim((string) ($fieldRule['label'] ?? ''));
                        if ($baseLabel === '') {
                            $baseLabel = $fieldName;
                        }
                        $suffixLabel = ucwords(trim((string) preg_replace('/\s+/', ' ', str_replace('_', ' ', $relationFieldName))));
                        if ($suffixLabel === '') {
                            $suffixLabel = $relationFieldName;
                        }
                        $tb->label($baseLabel . ' · ' . $suffixLabel);
                    }
                }
            }
        }

        if (!empty($orderedVisibleFields)) {
            $tb->reorderColumns(array_values(array_unique($orderedVisibleFields)));
        }
    }

    /**
     * Disable sort when relationship-based ordering would require a cross-db JOIN.
     */
    protected function shouldDisableSortForField(ListContextParams $p, array $fieldRule): bool
    {
        $relationship = is_array($fieldRule['relationship'] ?? null) ? $fieldRule['relationship'] : [];
        if (empty($relationship)) {
            return false;
        }

        $relationType = strtolower(trim((string) ($relationship['type'] ?? '')));
        if (!in_array($relationType, ['belongsto', 'hasone', 'hasmany'], true)) {
            return false;
        }

        return !$this->isRelationshipSortCompatible($p, $relationship);
    }

    /**
     * Returns false when related model uses a different db connection type.
     *
     * orderHas() performs SQL JOIN on the same query connection; with different
     * db types (e.g. main model on db2 and relation model on db), sorting fails.
     */
    protected function isRelationshipSortCompatible(ListContextParams $p, array $relationship): bool
    {
        $relatedModelClass = trim((string) ($relationship['related_model'] ?? ''));
        if ($relatedModelClass === '') {
            return false;
        }

        $mainDbType = strtolower(trim((string) $p->model->getDbType()));
        $cacheKey = $mainDbType . '|' . $relatedModelClass;
        if (array_key_exists($cacheKey, $this->relationshipSortCompatibilityCache)) {
            return $this->relationshipSortCompatibilityCache[$cacheKey];
        }

        if (!class_exists($relatedModelClass)) {
            $this->relationshipSortCompatibilityCache[$cacheKey] = false;
            return false;
        }

        try {
            $relatedModel = new $relatedModelClass();
        } catch (\Throwable) {
            $this->relationshipSortCompatibilityCache[$cacheKey] = false;
            return false;
        }

        $relatedDbType = '';
        if (method_exists($relatedModel, 'getDbType')) {
            $relatedDbType = strtolower(trim((string) $relatedModel->getDbType()));
        }

        if ($mainDbType === '' || $relatedDbType === '') {
            $this->relationshipSortCompatibilityCache[$cacheKey] = true;
            return true;
        }

        $isCompatible = ($mainDbType === $relatedDbType);
        $this->relationshipSortCompatibilityCache[$cacheKey] = $isCompatible;
        return $isCompatible;
    }

    /**
     * @param array<int,string> $fields
     * @param array<string,mixed> $modelRules
     * @param array<string,mixed> $childrenMetaByAlias
     * @return array<string,string>
     */
    protected function prepareVirtualCalculatedFields(
        TableBuilder $tb,
        array $fields,
        array $modelRules,
        array $childrenMetaByAlias
    ): array {
        $virtualCalculatedFields = [];
        $virtualAliases = [];

        foreach ($fields as $field) {
            if (!is_string($field) || $field === '') {
                continue;
            }
            if (isset($childrenMetaByAlias[$field]) || str_contains($field, '.')) {
                continue;
            }

            $rule = $modelRules[$field] ?? null;
            if (!is_array($rule) || (($rule['sql'] ?? true) !== false)) {
                continue;
            }

            $calcExpr = trim((string) ($rule['calc_expr'] ?? ''));
            if ($calcExpr === '') {
                continue;
            }

            $virtualCalculatedFields[$field] = $calcExpr;
            $virtualAliases[] = $field;
        }

        if (!empty($virtualAliases)) {
            $tb->queryCustomCallback(function ($query, $db) use ($virtualAliases): void {
                if (!is_object($query) || !method_exists($query, 'select')) {
                    return;
                }

                if (method_exists($query, 'hasSelect') && !$query->hasSelect()) {
                    $query->select('*');
                }

                foreach ($virtualAliases as $alias) {
                    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) !== 1) {
                        continue;
                    }
                    $quotedAlias = (is_object($db) && method_exists($db, 'qn'))
                        ? $db->qn($alias)
                        : $alias;
                    $query->select('NULL AS ' . $quotedAlias);
                }
            });
        }

        return $virtualCalculatedFields;
    }

    protected function applyVirtualCalculatedFieldFormatter(
        TableBuilder $tb,
        string $field,
        string $expression
    ): void {
        $tb->fn(function ($result) use ($field, $expression) {
            $row = [];
            if (is_object($result) && method_exists($result, 'getRawData')) {
                $raw = $result->getRawData('array', false);
                if (is_array($raw)) {
                    $row = $raw;
                }
            }

            try {
                $parser = (new ExpressionParser())->useUntrustedMode();
                $parser->setParameters($row);
                return $parser->execute($expression);
            } catch (\Throwable) {
                return $row[$field] ?? '';
            }
        });
    }
}
