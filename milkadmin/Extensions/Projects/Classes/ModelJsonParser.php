<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

use App\Abstracts\RuleBuilder;
use App\Get;
use App\Logs;

/**
 * ModelJsonParser - Parses JSON/Array into RuleBuilder
 *
 * Handles creation of new RuleBuilders and modification of existing ones.
 * Used internally by ModelSchemaSection.
 */
class ModelJsonParser
{
    /**
     * Last ignored fields during parse(), as map: field_name => reason
     * Reasons:
     * - exists_in_model: field was already defined in the model RuleBuilder before applying schema JSON
     *
     * @var array<string,string>
     */
    protected array $lastIgnoredFields = [];

    /**
     * Valid field creation methods
     */
    public const VALID_METHODS = [
        'id', 'primaryKey', 'string', 'title', 'text', 'int', 'datetime', 'date',
        'time', 'timestamp', 'decimal', 'email', 'tel', 'url', 'file', 'image',
        'boolean', 'checkbox', 'checkboxes', 'radio', 'list', 'select', 'enum',
        'array', 'created_at', 'updated_at', 'created_by', 'updated_by', 'field', 'html'
    ];

    /**
     * Configuration methods mapping: JSON key => method name
     */
    protected const CONFIG_MAP = [
        'label' => 'label',
        'default' => 'default',
        'formType' => 'formType',
        'formLabel' => 'formLabel',
        'error' => 'error',
        'calcExpr' => 'calcExpr',
        'step' => 'step',
        'min' => 'min',
        'max' => 'max',
        'accept' => 'accept',
        'uploadDir' => 'uploadDir',
        'sortable' => 'sortable',
        'downloadLink' => 'downloadLink',
        'saveValue' => 'saveValue',
        'requireIf' => 'requireIf',
        'maxFiles' => 'maxFiles',
        'maxSize' => 'maxSize',
        'formParams' => 'formParams',
        'properties' => 'properties',
    ];

    /**
     * Flag methods (called without arguments when value is true)
     */
    protected const FLAG_METHODS = [
        'required', 'unique', 'index', 'hideFromList', 'hideFromEdit',
        'hideFromView', 'hide', 'excludeFromDatabase', 'unsigned', 'noTimezoneConversion'
    ];

    protected $callableResolver = null;
    protected $modelClassResolver = null;

    /**
     * Set callable resolver for dynamic options
     */
    public function setCallableResolver(callable $resolver): self
    {
        $this->callableResolver = $resolver;
        return $this;
    }

    /**
     * Set model class resolver for relationship model names.
     */
    public function setModelClassResolver(callable $resolver): self
    {
        $this->modelClassResolver = $resolver;
        return $this;
    }

    /**
     * Parse array data into RuleBuilder
     *
     * @param array $data Schema data
     * @param RuleBuilder|null $rule Existing RuleBuilder to modify, or null to create new
     * @return RuleBuilder
     */
    public function parse(array $data, ?RuleBuilder $rule = null): RuleBuilder
    {
        $rule = $rule ?? new RuleBuilder();

        $this->lastIgnoredFields = [];
        $baselineFieldSet = $this->createBaselineFieldSet($rule);

        $this->applyGlobalSettings($rule, $data);
        $this->applyFields($rule, $data, $baselineFieldSet);
        $this->applyUploadSortableDefaults($rule);

        return $rule;
    }

    /**
     * Analyze ignored fields.
     *
     * This parser no longer ignores fields defined in model PHP; JSON config is
     * applied to existing fields via ChangeCurrentField() + config/relationships.
     *
     * @param array $data Schema data (unused, kept for signature compatibility)
     * @param RuleBuilder $rule Existing RuleBuilder (unused, kept for signature compatibility)
     * @return array<string,string>
     */
    public function analyzeIgnoredFields(array $data, RuleBuilder $rule): array
    {
        return [];
    }

    /**
     * Get ignored fields collected during the last parse().
     *
     * @return array<string,string> Map field_name => reason
     */
    public function getLastIgnoredFields(): array
    {
        return $this->lastIgnoredFields;
    }

    /**
     * Apply global settings (table, db, extensions, renames)
     */
    protected function applyGlobalSettings(RuleBuilder $rule, array $data): void
    {
        if (isset($data['table'])) {
            $rule->table($data['table']);
        }

        if (isset($data['db'])) {
            $rule->db($data['db']);
        }

        if (isset($data['extensions']) && is_array($data['extensions'])) {
            $rule->extensions($data['extensions']);
        }

        if (isset($data['rename_fields']) && is_array($data['rename_fields'])) {
            foreach ($data['rename_fields'] as $from => $to) {
                $rule->renameField($from, $to);
            }
        }

        if (isset($data['removePrimaryKeys']) && $data['removePrimaryKeys'] === true) {
            $rule->removePrimaryKeys();
        }
    }

    /**
     * Apply field definitions
     */
    protected function applyFields(RuleBuilder $rule, array $data, array $baselineFieldSet): void
    {
        if (!isset($data['fields']) || !is_array($data['fields'])) {
            return;
        }

        foreach ($data['fields'] as $fieldDef) {
            $this->applyFieldDefinition($rule, $fieldDef, $baselineFieldSet);
        }
    }

    /**
     * Apply a single field definition
     */
    protected function applyFieldDefinition(RuleBuilder $rule, array $fieldDef, array $baselineFieldSet): void
    {
        $name = $fieldDef['name'] ?? null;

        if ($name === null) {
            throw new \InvalidArgumentException("Field definition must have a 'name'");
        }

        // Merge strategy:
        // - If the field already exists in the model, keep it and only apply extra config/relationships.
        // - Recreate an existing field only when "replace": true is explicitly provided in JSON.
        // This allows keeping id()/primary key definitions inside the model configure() method.
        $existingRules = $rule->getRules();
        $fieldExists = isset($existingRules[$name]);
        $fieldExistsInModelBaseline = isset($baselineFieldSet[(string) $name]);
        $forceReplace = isset($fieldDef['replace']) && ($fieldDef['replace'] === true || $fieldDef['replace'] === 1 || $fieldDef['replace'] === '1');

        if ($fieldExists && !$forceReplace) {
            if ($fieldExistsInModelBaseline) {
                // Apply JSON to model-defined fields as if they were not pre-existing.
                $method = $fieldDef['method'] ?? 'string';
                $this->callFieldMethod($rule, $method, $name, $fieldDef);
            } else {
                $rule->ChangeCurrentField($name);
            }
        } else {
            $method = $fieldDef['method'] ?? 'string';
            $this->callFieldMethod($rule, $method, $name, $fieldDef);
        }

        // Keep explicit metadata toggles working also for false values
        // (ex: hideFromList=false, hideFromEdit=false).
        $this->applyModelFieldMetadataOverrides($rule, $fieldDef);

        $this->applyConfigMethods($rule, $fieldDef);
        $this->applyRelationships($rule, $fieldDef);
    }

    /**
     * Apply only UI-safe metadata overrides to fields defined in model PHP.
     * This intentionally avoids structural changes (type/db/constraints).
     */
    protected function applyModelFieldMetadataOverrides(RuleBuilder $rule, array $fieldDef): void
    {
        $name = $fieldDef['name'] ?? null;
        if (!is_string($name) || $name === '') {
            return;
        }

        $rules = $rule->getRules();
        if (!isset($rules[$name]) || !is_array($rules[$name])) {
            return;
        }

        $fieldRule = $rules[$name];

        if (array_key_exists('label', $fieldDef)) {
            $label = trim((string) $fieldDef['label']);
            if ($label !== '') {
                $fieldRule['label'] = $label;
            }
        }

        if (array_key_exists('hide', $fieldDef)) {
            $isHidden = $this->isTruthy($fieldDef['hide']);
            $fieldRule['list'] = !$isHidden;
            $fieldRule['edit'] = !$isHidden;
            $fieldRule['view'] = !$isHidden;
        }
        if (array_key_exists('hideFromList', $fieldDef)) {
            $fieldRule['list'] = !$this->isTruthy($fieldDef['hideFromList']);
        }
        if (array_key_exists('hideFromEdit', $fieldDef)) {
            $fieldRule['edit'] = !$this->isTruthy($fieldDef['hideFromEdit']);
        }
        if (array_key_exists('hideFromView', $fieldDef)) {
            $fieldRule['view'] = !$this->isTruthy($fieldDef['hideFromView']);
        }

        if (array_key_exists('formType', $fieldDef)) {
            $formType = trim((string) $fieldDef['formType']);
            if ($formType !== '') {
                $fieldRule['form-type'] = $formType;
            } else {
                unset($fieldRule['form-type']);
            }
        }
        if (array_key_exists('formLabel', $fieldDef)) {
            $formLabel = trim((string) $fieldDef['formLabel']);
            if ($formLabel !== '') {
                $fieldRule['form-label'] = $formLabel;
            } else {
                unset($fieldRule['form-label']);
            }
        }
        if (array_key_exists('formParams', $fieldDef) && is_array($fieldDef['formParams'])) {
            $fieldRule['form-params'] = $fieldDef['formParams'];
        }

        if (isset($fieldDef['listOptions']) && is_array($fieldDef['listOptions'])) {
            $fieldRule['list_options'] = $fieldDef['listOptions'];
        } elseif (array_key_exists('listOptions', $fieldDef)) {
            unset($fieldRule['list_options']);
        }
        if (isset($fieldDef['options']) || isset($fieldDef['optionsSource']) || isset($fieldDef['options_source'])) {
            $fieldRule['options'] = $this->resolveFieldOptions($fieldDef, $rule);
        }

        $rules[$name] = $fieldRule;
        $rule->setRules($rules);
        // setRules() resets current_field: restore it so subsequent config/relations
        // in the same parse cycle are applied to the intended field.
        $rule->ChangeCurrentField($name);
    }

    protected function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Build baseline field set from a RuleBuilder (keys of existing rules).
     *
     * @return array<string,bool>
     */
    protected function createBaselineFieldSet(RuleBuilder $rule): array
    {
        $baselineFieldSet = [];
        foreach (array_keys($rule->getRules()) as $fieldName) {
            $baselineFieldSet[(string) $fieldName] = true;
        }
        return $baselineFieldSet;
    }

    /**
     * Collect ignored fields based on baseline field set.
     *
     * @param array $data Schema data
     * @param array<string,bool> $baselineFieldSet
     * @return array<string,string> Map field_name => reason
     */
    protected function collectIgnoredFields(array $data, array $baselineFieldSet): array
    {
        $ignored = [];

        if (!isset($data['fields']) || !is_array($data['fields'])) {
            return $ignored;
        }

        foreach ($data['fields'] as $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }
            $name = $fieldDef['name'] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }
            if (isset($baselineFieldSet[$name])) {
                $ignored[$name] = 'exists_in_model';
            }
        }

        return $ignored;
    }

    /**
     * In Projects, upload fields have extension defaults:
     * - download-link is always enabled (for existing files/images in edit)
     * - sortable is enabled only when the field can contain multiple files
     * Rule for sortable:
     * - Enable sortable when max-files > 1
     * - Enable sortable when multiple is enabled and max-files is not explicitly limited to 1
     * - Disable sortable in all other upload cases
     */
    protected function applyUploadSortableDefaults(RuleBuilder $rule): void
    {
        $rules = $rule->getRules();

        foreach ($rules as $fieldName => $fieldRule) {
            $formType = strtolower(trim((string) ($fieldRule['form-type'] ?? '')));
            if (!in_array($formType, ['file', 'image'], true)) {
                continue;
            }

            $formParams = is_array($fieldRule['form-params'] ?? null) ? $fieldRule['form-params'] : [];
            $maxFiles = (int) ($formParams['max-files'] ?? 0);

            $multipleRaw = $formParams['multiple'] ?? null;
            $isMultiple = false;
            if ($multipleRaw !== null) {
                $isMultiple = ($multipleRaw === 'multiple') || $this->isTruthy($multipleRaw);
            }

            $enableSortable = ($maxFiles > 1) || ($maxFiles <= 0 && $isMultiple);

            // Projects default: always show download button for existing uploaded items.
            $formParams['download-link'] = true;

            if ($enableSortable) {
                $formParams['sortable'] = true;
            } else {
                unset($formParams['sortable']);
            }

            $fieldRule['form-params'] = $formParams;
            $rules[$fieldName] = $fieldRule;
        }

        $rule->setRules($rules);
    }

    /**
     * Call the field creation method
     */
    protected function callFieldMethod(RuleBuilder $rule, string $method, string $name, array $fieldDef): void
    {
        switch ($method) {
            case 'id':
                $rule->id($name);
                break;
            case 'primaryKey':
                $rule->primaryKey($name);
                break;
            case 'string':
                $rule->string($name, $fieldDef['length'] ?? 255);
                break;
            case 'title':
                $rule->title($name, $fieldDef['length'] ?? 255);
                break;
            case 'text':
                $rule->text($name);
                break;
            case 'int':
                $rule->int($name);
                break;
            case 'datetime':
                $rule->datetime($name);
                break;
            case 'date':
                $rule->date($name);
                break;
            case 'time':
                $rule->time($name);
                break;
            case 'timestamp':
                $rule->timestamp($name);
                break;
            case 'decimal':
                $rule->decimal($name, $fieldDef['length'] ?? 10, $fieldDef['precision'] ?? 2);
                break;
            case 'email':
                $rule->email($name);
                break;
            case 'tel':
                $rule->tel($name);
                break;
            case 'url':
                $rule->url($name);
                break;
            case 'file':
                $rule->file($name);
                break;
            case 'image':
                $rule->image($name);
                break;
            case 'boolean':
            case 'checkbox':
                $rule->boolean($name);
                break;
            case 'checkboxes':
                $rule->checkboxes($name, $this->resolveFieldOptions($fieldDef, $rule));
                break;
            case 'radio':
                $rule->radio($name, $this->resolveFieldOptions($fieldDef, $rule));
                break;
            case 'list':
            case 'select':
                $rule->list($name, $this->resolveFieldOptions($fieldDef, $rule));
                break;
            case 'enum':
                $rule->enum($name, $this->resolveFieldOptions($fieldDef, $rule));
                break;
            case 'array':
                $rule->array($name);
                break;
            case 'created_at':
                $rule->created_at($name);
                break;
            case 'updated_at':
                $rule->updated_at($name);
                break;
            case 'created_by':
                $rule->created_by($name);
                break;
            case 'updated_by':
                $rule->updated_by($name);
                break;
            case 'html':
                $rule->field($name, 'html')->excludeFromDatabase();
                if (array_key_exists('html', $fieldDef)) {
                    $rule->property('html', $fieldDef['html']);
                }
                break;
            case 'field':
            default:
                $rule->field($name, $fieldDef['type'] ?? 'string');
                break;
        }
    }

    /**
     * Apply configuration methods to current field
     */
    protected function applyConfigMethods(RuleBuilder $rule, array $fieldDef): void
    {
        $fieldMethod = strtolower(trim((string) ($fieldDef['method'] ?? '')));
        if (in_array($fieldMethod, ['radio', 'checkboxes'], true)) {
            $groupLabel = trim((string) ($fieldDef['label'] ?? ''));
            $formParams = is_array($fieldDef['formParams'] ?? null) ? $fieldDef['formParams'] : [];
            $existingGroupLabel = trim((string) ($formParams['label'] ?? ''));
            if ($existingGroupLabel === '' && $groupLabel !== '') {
                $formParams['label'] = $groupLabel;
                $fieldDef['formParams'] = $formParams;
            }
        }

        // Standard config methods
        foreach (self::CONFIG_MAP as $key => $method) {
            if (array_key_exists($key, $fieldDef)) {
                $rule->$method($fieldDef[$key]);
            }
        }

        // Flag methods
        foreach (self::FLAG_METHODS as $key) {
            if (isset($fieldDef[$key]) && ($fieldDef[$key] === true || $fieldDef[$key] === 1 || $fieldDef[$key] === '1')) {
                $rule->$key();
            }
        }

        // Special handling for nullable
        if (array_key_exists('nullable', $fieldDef)) {
            $rule->nullable((bool) $fieldDef['nullable']);
        } elseif (isset($fieldDef['required']) && ($fieldDef['required'] === true || $fieldDef['required'] === 1 || $fieldDef['required'] === '1')) {
            $rule->nullable(false);
        }
        if (($fieldDef['method'] ?? '') === 'text' && array_key_exists('dbType', $fieldDef)) {
            $dbType = strtolower(trim((string) $fieldDef['dbType']));
            if (in_array($dbType, ['tinytext', 'text', 'mediumtext', 'longtext'], true)) {
                $rule->property('db_type', $dbType);
            }
        }

        // Special handling for multiple
        if (isset($fieldDef['multiple'])) {
            $value = $fieldDef['multiple'];
            $rule->multiple(is_bool($value) ? $value : (int) $value);
        }

        // Special handling for validateExpr
        if (isset($fieldDef['validateExpr'])) {
            $expr = $fieldDef['validateExpr'];
            if (is_array($expr)) {
                $rule->validateExpr($expr[0], $expr[1] ?? null);
            } else {
                $rule->validateExpr($expr);
            }
        }

        // Special handling for options
        if (isset($fieldDef['options']) && !in_array($fieldDef['method'] ?? '', ['checkboxes', 'radio', 'list', 'select', 'enum'])) {
            $rule->options($this->resolveOptions($fieldDef['options']));
        }

        // Special handling for apiUrl
        if (isset($fieldDef['apiUrl'])) {
            $apiUrl = $fieldDef['apiUrl'];
            if (is_array($apiUrl)) {
                $rule->apiUrl($apiUrl['url'], $apiUrl['display_field'] ?? null);
            } else {
                $rule->apiUrl($apiUrl, $fieldDef['apiDisplayField'] ?? null);
            }
        }

        // Special handling for checkboxValues
        if (isset($fieldDef['checkboxValues'])) {
            $values = (array) $fieldDef['checkboxValues'];
            $rule->checkboxValues($values[0] ?? null, $values[1] ?? null);
        }

        // Special handling for property
        if (isset($fieldDef['property'])) {
            $prop = $fieldDef['property'];
            if (is_array($prop) && count($prop) >= 2) {
                $rule->property($prop[0], $prop[1]);
            }
        }

        // allowEmpty (select): store in rules so rendering can add empty option
        if (isset($fieldDef['allowEmpty']) && $this->isTruthy($fieldDef['allowEmpty'])) {
            $rule->property('allow_empty', true);
        }

        // List options configuration
        if (isset($fieldDef['listOptions']) && is_array($fieldDef['listOptions'])) {
            $rule->property('list_options', $fieldDef['listOptions']);
        }
    }

    /**
     * Apply relationship definitions
     */
    protected function applyRelationships(RuleBuilder $rule, array $fieldDef): void
    {
        // belongsTo
        if (isset($fieldDef['belongsTo'])) {
            $rel = $fieldDef['belongsTo'];
            $rule->belongsTo(
                $rel['alias'],
                $this->resolveModelClass($rel['related_model']),
                $rel['related_key'] ?? 'id'
            );
            $this->applyRelationshipWhere($rule, $rel);
        }

        // hasOne
        if (isset($fieldDef['hasOne'])) {
            $rel = $fieldDef['hasOne'];
            $rule->hasOne(
                $rel['alias'],
                $this->resolveModelClass($rel['related_model']),
                $rel['foreign_key'],
                $rel['onDelete'] ?? 'CASCADE',
                $rel['allowCascadeSave'] ?? false
            );
            $this->applyRelationshipWhere($rule, $rel);
        }

        // hasMany
        if (isset($fieldDef['hasMany'])) {
            $rel = $fieldDef['hasMany'];
            $rule->hasMany(
                $rel['alias'],
                $this->resolveModelClass($rel['related_model']),
                $rel['foreign_key'],
                $rel['onDelete'] ?? 'CASCADE',
                $rel['allowCascadeSave'] ?? false
            );
            $this->applyRelationshipWhere($rule, $rel);
        }

        // withCount
        if (isset($fieldDef['withCount'])) {
            $items = isset($fieldDef['withCount']['alias']) ? [$fieldDef['withCount']] : $fieldDef['withCount'];
            foreach ($items as $wc) {
                $rule->withCount(
                    $wc['alias'],
                    $this->resolveModelClass($wc['related_model']),
                    $wc['foreign_key']
                );
                $this->applyRelationshipWhere($rule, $wc);
            }
        }

        // hasMeta
        if (isset($fieldDef['hasMeta'])) {
            $items = isset($fieldDef['hasMeta']['alias']) ? [$fieldDef['hasMeta']] : $fieldDef['hasMeta'];
            foreach ($items as $hm) {
                $rule->hasMeta(
                    $hm['alias'],
                    $this->resolveModelClass($hm['related_model']),
                    $hm['foreign_key'] ?? null,
                    $hm['local_key'] ?? null,
                    $hm['meta_key_column'] ?? 'meta_key',
                    $hm['meta_value_column'] ?? 'meta_value',
                    $hm['meta_key_value'] ?? null
                );
                $this->applyRelationshipWhere($rule, $hm);
            }
        }
    }

    protected function applyRelationshipWhere(RuleBuilder $rule, array $rel): void
    {
        if (isset($rel['where'])) {
            $rule->where($rel['where']['condition'], $rel['where']['params'] ?? []);
        }
    }

    protected function resolveOptions($options): array
    {
        if (is_array($options)) {
            return $options;
        }

        if (is_string($options) && preg_match('/^\\\\?[\w\\\\]+::\w+\(\)$/', $options)) {
            if ($this->callableResolver !== null) {
                return ($this->callableResolver)($options);
            }

            $callable = rtrim($options, '()');
            if (is_callable($callable)) {
                return $callable();
            }
        }

        return [];
    }

    protected function resolveFieldOptions(array $fieldDef, RuleBuilder $rule): array
    {
        $manualOptions = $this->resolveOptions($fieldDef['options'] ?? []);
        $source = $this->extractOptionsSource($fieldDef);
        if (!is_array($source)) {
            return $this->sortOptionsByLabel($manualOptions);
        }

        $mode = strtolower(trim((string) ($source['mode'] ?? '')));
        if ($mode !== 'all') {
            return $this->sortOptionsByLabel($manualOptions);
        }

        $modelClass = trim((string) ($source['model'] ?? ''));
        $table = trim((string) ($source['table'] ?? ''));
        $valueField = trim((string) ($source['value_field'] ?? ''));
        $labelField = trim((string) ($source['label_field'] ?? ''));
        $where = trim((string) ($source['where'] ?? ''));
        if (($modelClass === '' && $table === '') || $valueField === '' || $labelField === '') {
            return $this->sortOptionsByLabel($manualOptions);
        }

        $tableOptions = [];
        if ($modelClass !== '') {
            $tableOptions = $this->loadOptionsFromModel($rule, $modelClass, $valueField, $labelField, $where);
        } elseif ($table !== '') {
            $tableOptions = $this->loadOptionsFromTable($rule, $table, $valueField, $labelField, $where);
        }
        $resolvedOptions = !empty($tableOptions) ? $tableOptions : $manualOptions;
        return $this->sortOptionsByLabel($resolvedOptions);
    }

    protected function extractOptionsSource(array $fieldDef): ?array
    {
        if (is_array($fieldDef['optionsSource'] ?? null)) {
            return $fieldDef['optionsSource'];
        }

        if (is_array($fieldDef['options_source'] ?? null)) {
            return $fieldDef['options_source'];
        }

        return null;
    }

    protected function loadOptionsFromTable(
        RuleBuilder $rule,
        string $table,
        string $valueField,
        string $labelField,
        string $where = '',
        string $dbTypeOverride = ''
    ): array {
        if (!$this->isSafeIdentifier($table) || !$this->isSafeIdentifier($valueField) || !$this->isSafeIdentifier($labelField)) {
            return [];
        }

        $dbType = strtolower(trim($dbTypeOverride));
        if ($dbType === '') {
            $dbType = strtolower(trim((string) $rule->getDbType()));
        }
        if ($dbType === '') {
            $dbType = 'db';
        } elseif ($dbType === 'arraydb') {
            $dbType = 'array';
        }

        try {
            $db = $dbType === 'db2' ? Get::db2() : Get::dbConnection($dbType);
        } catch (\Throwable) {
            return [];
        }
        if (!is_object($db) || !method_exists($db, 'qn') || !method_exists($db, 'getResults')) {
            return [];
        }

        try {
            $valueAlias = 'projects_opt_value';
            $labelAlias = 'projects_opt_label';
            $sql = 'SELECT '
                . $db->qn($valueField) . ' AS ' . $db->qn($valueAlias) . ', '
                . $db->qn($labelField) . ' AS ' . $db->qn($labelAlias)
                . ' FROM ' . $db->qn($table);
            if ($where !== '') {
                $sql .= ' WHERE ' . $where;
            }
            $sql .= ' ORDER BY ' . $db->qn($labelField) . ' ASC';

            $rows = $db->getResults($sql);
            if (!is_array($rows)) {
                return [];
            }

            $options = [];
            foreach ($rows as $row) {
                $rowArray = is_object($row) ? (array) $row : (is_array($row) ? $row : []);
                if (empty($rowArray)) {
                    continue;
                }

                $rawValue = $rowArray[$valueAlias] ?? null;
                if ($rawValue === null) {
                    continue;
                }

                $rawLabel = $rowArray[$labelAlias] ?? null;
                $optionKey = is_scalar($rawValue) ? (string) $rawValue : json_encode($rawValue);
                if (!is_string($optionKey) || $optionKey === '') {
                    continue;
                }

                $optionLabel = is_scalar($rawLabel) ? (string) $rawLabel : '';
                if ($optionLabel === '') {
                    $optionLabel = $optionKey;
                }

                $options[$optionKey] = $optionLabel;
            }

            return $options;
        } catch (\Throwable $e) {
            Logs::set('SYSTEM', "Projects extension: optionsSource query failed for table '{$table}' - {$e->getMessage()}", 'ERROR');
        }

        return [];
    }

    protected function loadOptionsFromModel(
        RuleBuilder $rule,
        string $modelClass,
        string $valueField,
        string $labelField,
        string $where = ''
    ): array {
        $resolvedModelClass = $this->resolveModelClass($modelClass);
        if ($resolvedModelClass === '') {
            return [];
        }
        if (!class_exists($resolvedModelClass)) {
            Logs::set(
                'SYSTEM',
                "Projects extension: optionsSource model '{$resolvedModelClass}' does not exist.",
                'ERROR'
            );
            return [];
        }

        static $resolvingModelClasses = [];
        if (($resolvingModelClasses[$resolvedModelClass] ?? 0) > 0) {
            $currentRuleTable = trim((string) $rule->getTable());
            $currentRuleDbType = strtolower(trim((string) $rule->getDbType()));

            Logs::set(
                'SYSTEM',
                "Projects extension: recursive optionsSource model resolution detected for '{$resolvedModelClass}'",
                'WARNING'
            );

            // Prevent infinite recursion when a model field optionsSource points to the same model
            // (or cyclical model chains). For direct self-model cases, the current RuleBuilder
            // table is the intended source and can be queried without instantiating the model again.
            if ($currentRuleTable !== '') {
                return $this->loadOptionsFromTable(
                    $rule,
                    $currentRuleTable,
                    $valueField,
                    $labelField,
                    $where,
                    $currentRuleDbType
                );
            }

            return [];
        }

        $resolvingModelClasses[$resolvedModelClass] = ($resolvingModelClasses[$resolvedModelClass] ?? 0) + 1;

        try {
            $model = new $resolvedModelClass();
        } catch (\Throwable $e) {
            Logs::set(
                'SYSTEM',
                "Projects extension: optionsSource model '{$resolvedModelClass}' cannot be instantiated - {$e->getMessage()}",
                'ERROR'
            );
            return [];
        } finally {
            $resolvingModelClasses[$resolvedModelClass] = ($resolvingModelClasses[$resolvedModelClass] ?? 1) - 1;
            if ($resolvingModelClasses[$resolvedModelClass] <= 0) {
                unset($resolvingModelClasses[$resolvedModelClass]);
            }
        }

        if (!is_object($model) || !method_exists($model, 'getTable')) {
            return [];
        }

        $table = trim((string) $model->getTable());
        if ($table === '') {
            return [];
        }

        $dbType = '';
        if (method_exists($model, 'getDbType')) {
            $dbType = strtolower(trim((string) $model->getDbType()));
        }

        return $this->loadOptionsFromTable($rule, $table, $valueField, $labelField, $where, $dbType);
    }

    protected function isSafeIdentifier(string $identifier): bool
    {
        $identifier = trim($identifier);
        if ($identifier === '' || str_contains($identifier, "\0")) {
            return false;
        }

        return preg_match('/^[A-Za-z_#][A-Za-z0-9_#\\-\\. ]*$/', $identifier) === 1;
    }

    protected function sortOptionsByLabel(array $options): array
    {
        uasort($options, static function ($left, $right): int {
            return strnatcasecmp((string) $left, (string) $right);
        });
        return $options;
    }

    /**
     * Resolve related model class names for relationship methods.
     */
    protected function resolveModelClass(string $className): string
    {
        $className = ltrim(trim($className), '\\');

        if ($className === '') {
            return $className;
        }

        if ($this->modelClassResolver !== null) {
            $resolved = ($this->modelClassResolver)($className);
            if (is_string($resolved) && trim($resolved) !== '') {
                return ltrim(trim($resolved), '\\');
            }
        }

        return $className;
    }
}
