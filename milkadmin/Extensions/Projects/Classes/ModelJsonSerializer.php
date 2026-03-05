<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

use App\Abstracts\RuleBuilder;

/**
 * ModelJsonSerializer - Serializes RuleBuilder to JSON/Array
 *
 * Converts RuleBuilder configurations back to portable JSON format.
 * Used internally by ModelSchemaSection.
 */
class ModelJsonSerializer
{
    protected const TYPE_METHOD_MAP = [
        'id' => 'id',
        'string' => 'string',
        'text' => 'text',
        'int' => 'int',
        'float' => 'decimal',
        'bool' => 'boolean',
        'date' => 'date',
        'datetime' => 'datetime',
        'time' => 'time',
        'timestamp' => 'timestamp',
        'list' => 'list',
        'enum' => 'enum',
        'radio' => 'radio',
        'array' => 'array',
    ];

    protected const FORM_TYPE_METHOD_MAP = [
        'email' => 'email',
        'tel' => 'tel',
        'url' => 'url',
        'file' => 'file',
        'image' => 'image',
        'checkboxes' => 'checkboxes',
    ];

    /**
     * Serialize RuleBuilder to array
     */
    public function serialize(RuleBuilder $rule): array
    {
        $data = $this->serializeGlobalSettings($rule);
        $data['fields'] = $this->serializeFields($rule);

        return $data;
    }

    protected function serializeGlobalSettings(RuleBuilder $rule): array
    {
        $data = [];

        if ($rule->getTable() !== null) {
            $data['table'] = $rule->getTable();
        }

        if ($rule->getDbType() !== 'db') {
            $data['db'] = $rule->getDbType();
        }

        if ($rule->getExtensions() !== null) {
            $data['extensions'] = $rule->getExtensions();
        }

        $renames = $rule->getRenameFields();
        if (!empty($renames)) {
            $data['rename_fields'] = $renames;
        }

        return $data;
    }

    protected function serializeFields(RuleBuilder $rule): array
    {
        $fields = [];
        $rules = $rule->getRules();

        foreach ($rules as $name => $fieldRule) {
            if (($fieldRule['virtual'] ?? false) === true) {
                continue;
            }
            $fields[] = $this->serializeField($name, $fieldRule);
        }

        return $fields;
    }

    protected function serializeField(string $name, array $fieldRule): array
    {
        $def = ['name' => $name];
        $def['method'] = $this->determineMethod($name, $fieldRule);

        $this->addTypeParams($def, $fieldRule);
        $this->addConfigParams($def, $fieldRule);
        $this->addRelationships($def, $fieldRule);

        return $def;
    }

    protected function determineMethod(string $name, array $fieldRule): string
    {
        $type = $fieldRule['type'] ?? 'string';

        if (($fieldRule['primary'] ?? false) && $type === 'id') return 'id';
        if ($fieldRule['_is_title_field'] ?? false) return 'title';
        if ($fieldRule['_auto_created_at'] ?? false) return 'created_at';
        if ($fieldRule['_auto_updated_at'] ?? false) return 'updated_at';
        if ($fieldRule['_auto_created_by'] ?? false) return 'created_by';
        if ($fieldRule['_auto_updated_by'] ?? false) return 'updated_by';
        if ($name === 'updated_at' && $type === 'datetime') return 'updated_at';
        if ($name === 'created_by' && $type === 'int') return 'created_by';
        if ($name === 'updated_by' && $type === 'int') return 'updated_by';

        $formType = $fieldRule['form-type'] ?? null;
        if ($formType && isset(self::FORM_TYPE_METHOD_MAP[$formType])) {
            return self::FORM_TYPE_METHOD_MAP[$formType];
        }

        return self::TYPE_METHOD_MAP[$type] ?? 'string';
    }

    protected function addTypeParams(array &$def, array $fieldRule): void
    {
        $method = $def['method'];

        if (in_array($method, ['string', 'title']) && isset($fieldRule['length'])) {
            $def['length'] = $fieldRule['length'];
        }

        if ($method === 'decimal') {
            if (isset($fieldRule['length'])) $def['length'] = $fieldRule['length'];
            if (isset($fieldRule['precision'])) $def['precision'] = $fieldRule['precision'];
        }

        if (in_array($method, ['list', 'select', 'enum', 'radio', 'checkboxes']) && isset($fieldRule['options'])) {
            $def['options'] = $fieldRule['options'];
        }
    }

    protected function addConfigParams(array &$def, array $fieldRule): void
    {
        // Label
        if (isset($fieldRule['label'])) {
            $autoLabel = $this->createLabel($def['name']);
            if ($fieldRule['label'] !== $autoLabel) {
                $def['label'] = $fieldRule['label'];
            }
        }

        // Simple values
        $this->addIfSet($def, $fieldRule, 'default');
        $this->addIfSet($def, $fieldRule, 'required_expr', 'requireIf');
        $this->addIfSet($def, $fieldRule, 'calc_expr', 'calcExpr');
        $this->addIfSet($def, $fieldRule, 'validate_expr', 'validateExpr');
        $this->addIfSet($def, $fieldRule, 'save_value', 'saveValue');

        // Nullable
        if (isset($fieldRule['nullable']) && $fieldRule['nullable'] === false) {
            $def['nullable'] = false;
        }

        // Boolean flags
        if (($fieldRule['unique'] ?? false) === true) $def['unique'] = true;
        if (($fieldRule['index'] ?? false) === true) $def['index'] = true;
        if (($fieldRule['unsigned'] ?? false) === true) $def['unsigned'] = true;

        // Visibility
        if (($fieldRule['list'] ?? true) === false) $def['hideFromList'] = true;
        if (($fieldRule['edit'] ?? true) === false) $def['hideFromEdit'] = true;
        if (($fieldRule['view'] ?? true) === false) $def['hideFromView'] = true;
        if (($fieldRule['sql'] ?? true) === false) $def['excludeFromDatabase'] = true;

        // Form type
        $formType = $fieldRule['form-type'] ?? null;
        if ($formType && !isset(self::FORM_TYPE_METHOD_MAP[$formType])) {
            $def['formType'] = $formType;
        }
        if (($def['method'] ?? '') === 'text') {
            $dbType = strtolower(trim((string) ($fieldRule['db_type'] ?? '')));
            if (in_array($dbType, ['tinytext', 'mediumtext', 'longtext'], true)) {
                $def['dbType'] = $dbType;
            }
        }

        $this->addIfSet($def, $fieldRule, 'form-label', 'formLabel');

        // Timezone
        if (($fieldRule['type'] ?? '') === 'datetime' && ($fieldRule['timezone_conversion'] ?? true) === false) {
            $def['noTimezoneConversion'] = true;
        }

        // Form params
        $this->addFormParams($def, $fieldRule);

        // Checkbox values
        if (isset($fieldRule['checkbox_checked'])) {
            $def['checkboxValues'] = [$fieldRule['checkbox_checked'], $fieldRule['checkbox_unchecked'] ?? null];
        }

        // List options
        if (isset($fieldRule['list_options']) && is_array($fieldRule['list_options'])) {
            $def['listOptions'] = $fieldRule['list_options'];
        }

        // API URL
        if (isset($fieldRule['api_url'])) {
            $def['apiUrl'] = ['url' => $fieldRule['api_url']];
            if (isset($fieldRule['api_display_field'])) {
                $def['apiUrl']['display_field'] = $fieldRule['api_display_field'];
            }
        }
    }

    protected function addFormParams(array &$def, array $fieldRule): void
    {
        $formParams = $fieldRule['form-params'] ?? [];
        if (empty($formParams)) return;

        $map = [
            'required' => 'required',
            'invalid-feedback' => 'error',
            'min' => 'min',
            'max' => 'max',
            'minlength' => 'min',
            'maxlength' => 'max',
            'step' => 'step',
            'multiple' => 'multiple',
            'max-files' => 'maxFiles',
            'accept' => 'accept',
            'max-size' => 'maxSize',
            'upload-dir' => 'uploadDir',
            'sortable' => 'sortable',
            'download-link' => 'downloadLink',
        ];

        $remaining = [];
        foreach ($formParams as $key => $value) {
            if (isset($map[$key])) {
                $defKey = $map[$key];
                $def[$defKey] = in_array($defKey, ['required', 'multiple', 'sortable', 'downloadLink']) ? true : $value;
            } else {
                $remaining[$key] = $value;
            }
        }

        if (!empty($remaining)) {
            $def['formParams'] = $remaining;
        }
    }

    protected function addRelationships(array &$def, array $fieldRule): void
    {
        if (isset($fieldRule['relationship'])) {
            $rel = $fieldRule['relationship'];
            $type = $rel['type'];

            $relDef = ['alias' => $rel['alias'], 'related_model' => $rel['related_model']];

            if ($type === 'belongsTo') {
                $relDef['related_key'] = $rel['related_key'] ?? 'id';
            } else {
                $relDef['foreign_key'] = $rel['foreign_key'];
                if (($rel['onDelete'] ?? 'CASCADE') !== 'CASCADE') $relDef['onDelete'] = $rel['onDelete'];
                if (($rel['allowCascadeSave'] ?? false) === true) $relDef['allowCascadeSave'] = true;
            }

            if (isset($rel['where'])) $relDef['where'] = $rel['where'];
            $def[$type] = $relDef;
        }

        if (isset($fieldRule['withCount']) && is_array($fieldRule['withCount'])) {
            $items = array_map(fn($wc) => $this->serializeWithCount($wc), $fieldRule['withCount']);
            $def['withCount'] = count($items) === 1 ? $items[0] : $items;
        }

        if (isset($fieldRule['hasMeta']) && is_array($fieldRule['hasMeta'])) {
            $items = array_map(fn($hm) => $this->serializeHasMeta($hm), $fieldRule['hasMeta']);
            $def['hasMeta'] = count($items) === 1 ? $items[0] : $items;
        }
    }

    protected function serializeWithCount(array $config): array
    {
        $result = ['alias' => $config['alias'], 'related_model' => $config['related_model'], 'foreign_key' => $config['foreign_key']];
        if (isset($config['where'])) $result['where'] = $config['where'];
        return $result;
    }

    protected function serializeHasMeta(array $config): array
    {
        $result = ['alias' => $config['alias'], 'related_model' => $config['related_model']];
        if (isset($config['foreign_key'])) $result['foreign_key'] = $config['foreign_key'];
        if (isset($config['local_key'])) $result['local_key'] = $config['local_key'];
        if (($config['meta_key_column'] ?? 'meta_key') !== 'meta_key') $result['meta_key_column'] = $config['meta_key_column'];
        if (($config['meta_value_column'] ?? 'meta_value') !== 'meta_value') $result['meta_value_column'] = $config['meta_value_column'];
        if (isset($config['meta_key_value']) && $config['meta_key_value'] !== $config['alias']) $result['meta_key_value'] = $config['meta_key_value'];
        if (isset($config['where'])) $result['where'] = $config['where'];
        return $result;
    }

    protected function addIfSet(array &$def, array $fieldRule, string $ruleKey, ?string $defKey = null): void
    {
        if (isset($fieldRule[$ruleKey]) && $fieldRule[$ruleKey] !== null) {
            $def[$defKey ?? $ruleKey] = $fieldRule[$ruleKey];
        }
    }

    protected function createLabel(string $fieldName): string
    {
        $label = preg_replace(['/([a-z])([A-Z])/', '/_+/', '/[-\s]+/'], ['\\1 \\2', ' ', ' '], $fieldName);
        return ucfirst(preg_replace('/\s+/', ' ', trim($label)));
    }
}
