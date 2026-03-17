<?php
namespace Builders\Traits\FormBuilder;

/**
 * Trait FormRelationshipTrait
 *
 * Handles adding related fields from hasOne/belongsTo relationships to forms
 *
 * @package Builders\Traits
 */
trait FormRelationshipTrait {

    /**
     * Tracks which hasOne/belongsTo relationships have been added to the form
     * Structure: ['badge' => true, 'profile' => true]
     * @var array
     */
    private array $addedRelationships = [];

    /**
     * Add a single field from a related model (hasOne/belongsTo)
     *
     * @param string $field_path Relationship path in format "relation_alias.field_name" (e.g., "badge.badge_number")
     * @param string|null $label Optional custom label for the field
     * @param string $position_before Optional field name to insert before
     * @return self For method chaining
     *
     * @example ->addRelatedField('badge.badge_number', 'Badge Number')
     * @example ->addRelatedField('badge.issue_date', 'Issue Date', 'submit_button')
     */
    public function addRelatedField(string $field_path, ?string $label = null, string $position_before = ''): self {

        // STEP 1: Parse del nome "badge.badge_number"
        $parts = explode('.', $field_path);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("field_path must be in format 'relation.field', got: $field_path");
        }
        $relationship_alias = $parts[0];  // "badge"
        $field_name = $parts[1];          // "badge_number"

        // STEP 2: Trova record_object dai campi esistenti
        $record = null;
        foreach ($this->fields as $field_key => $field_data) {
            if (isset($field_data['record_object'])) {
                $record = $field_data['record_object'];
                break;
            }
        }

        if (!$record) {
            throw new \RuntimeException("record_object not found in FormBuilder fields");
        }

        // STEP 3: Carica il modello correlato per leggere i rules
        $rules = $record->getRules();
        $relation = null;
        foreach ($rules as $ruleKey => $ruleData) {
            if (isset($ruleData['relationship']) && $ruleData['relationship']['alias'] === $relationship_alias) {
                $relation = $ruleData['relationship'];
                break;
            }
        }

        if (!$relation) {
            throw new \InvalidArgumentException("Relationship '$relationship_alias' not found in model");
        }

        $relatedModelClass = $relation['related_model'];
        $relatedModel = new $relatedModelClass();
        $relatedRules = $relatedModel->getRules();

        // STEP 4: Estrai il rule del campo specifico
        if (!isset($relatedRules[$field_name])) {
            throw new \InvalidArgumentException("Field '$field_name' not found in related model '$relatedModelClass'");
        }

        $fieldRule = $relatedRules[$field_name];

        // STEP 5: Carica il valore corrente dal record (con ->with())
        $relatedObject = $record->$relationship_alias;

        if (is_array($relatedObject)) {
            $currentValue = $relatedObject[$field_name] ?? '';
        } elseif (is_object($relatedObject)) {
            $currentValue = $relatedObject->$field_name ?? '';
        } else {
            $currentValue = '';
        }

        // STEP 6: Crea il campo da aggiungere al form
        $formFieldName = "{$relationship_alias}[{$field_name}]";
        $finalLabel = $label ?? $fieldRule['label'] ?? ucfirst(str_replace('_', ' ', $field_name));

        // Costruisci la configurazione del campo basandoti sul tipo
        $fieldConfig = [
            'name' => $formFieldName,
            'label' => $finalLabel,
            'type' => $this->mapFieldType($fieldRule['type']),
            'row_value' => $currentValue,  // FormBuilder usa 'row_value', non 'value'
            'record_object' => $record,
            'relationship_context' => [
                'alias' => $relationship_alias,
                'field' => $field_name,
                'related_model' => $relatedModelClass
            ]
        ];

        // Add attributes from rule
        if (isset($fieldRule['form-params'])) {
            foreach ($fieldRule['form-params'] as $param => $value) {
                $fieldConfig[$param] = $value;
            }
        }

        // Handle fields with options (select, list, etc.)
        if (isset($fieldRule['options'])) {
            $fieldConfig['options'] = $fieldRule['options'];
        }

        // Add common validations
        if (isset($fieldRule['nullable']) && !$fieldRule['nullable']) {
            $fieldConfig['required'] = true;
        }
        if (isset($fieldRule['length'])) {
            $fieldConfig['maxlength'] = $fieldRule['length'];
        }

        // STEP 6.5: If it's the first time we add a field from this hasOne relationship
        // add hidden fields for all required fields that haven't been added yet
        if (!isset($this->addedRelationships[$relationship_alias])) {
            $this->addedRelationships[$relationship_alias] = true;

            // Only for hasOne (not belongsTo) we add hidden fields
            if ($relation['type'] === 'hasOne') {
                $this->addHiddenRequiredFields($relationship_alias, $relatedRules, $relatedObject, $position_before);
            }
        }

        // STEP 7: Insert the field in the correct position
        if (!empty($position_before) && isset($this->fields[$position_before])) {
            // Insert before the specified field
            $newFields = [];
            foreach ($this->fields as $key => $value) {
                if ($key === $position_before) {
                    $newFields[$formFieldName] = $fieldConfig;
                }
                $newFields[$key] = $value;
            }
            $this->fields = $newFields;
        } else {
            // Add at the bottom
            $this->fields[$formFieldName] = $fieldConfig;
        }

        return $this;
    }

    /**
     * Add hidden fields for all required fields of the hasOne relationship
     * that haven't been added manually
     *
     * @param string $relationship_alias Relationship alias (e.g., 'badge')
     * @param array $relatedRules Rules of the related model
     * @param mixed $relatedObject Related object with current values
     * @param string $position_before Position where to insert the fields
     */
    private function addHiddenRequiredFields(string $relationship_alias, array $relatedRules, $relatedObject, string $position_before): void {

        foreach ($relatedRules as $fieldName => $fieldRule) {
            // Skip special fields
            if ($fieldName === 'id' || strpos($fieldName, '___') === 0) {
                continue;
            }

            // Skip fields that have already been explicitly added
            $formFieldName = "{$relationship_alias}[{$fieldName}]";
            if (isset($this->fields[$formFieldName])) {
                continue;
            }

            // Skip non-required fields (nullable or with default)
            $isRequired = isset($fieldRule['form-params']['required']) && $fieldRule['form-params']['required'];
            $hasDefault = array_key_exists('default', $fieldRule) && $fieldRule['default'] !== null && $fieldRule['default'] !== '';

            if (!$isRequired && !$hasDefault) {
                continue;
            }

            // Extract the current value
            $currentValue = '';
            if (is_array($relatedObject)) {
                $currentValue = $relatedObject[$fieldName] ?? ($fieldRule['default'] ?? '');
            } elseif (is_object($relatedObject)) {
                $currentValue = $relatedObject->$fieldName ?? ($fieldRule['default'] ?? '');
            } else {
                $currentValue = $fieldRule['default'] ?? '';
            }

            // Create hidden field
            $hiddenFieldConfig = [
                'name' => $formFieldName,
                'label' => '', // Hidden fields don't have visible label
                'type' => 'hidden',
                'row_value' => $currentValue,
                'relationship_context' => [
                    'alias' => $relationship_alias,
                    'field' => $fieldName,
                    'auto_added' => true // Flag to indicate it was added automatically
                ]
            ];

            // Add the hidden field
            if (!empty($position_before) && isset($this->fields[$position_before])) {
                $newFields = [];
                foreach ($this->fields as $key => $value) {
                    if ($key === $position_before) {
                        $newFields[$formFieldName] = $hiddenFieldConfig;
                    }
                    $newFields[$key] = $value;
                }
                $this->fields = $newFields;
            } else {
                $this->fields[$formFieldName] = $hiddenFieldConfig;
            }
        }
    }

    /**
     * Mappa il tipo di campo del model al tipo di input HTML/form
     *
     * @param string $modelType Il tipo del campo nel model (string, int, date, ecc.)
     * @return string Il tipo di input HTML corrispondente
     */
    private function mapFieldType(string $modelType): string {
        $typeMap = [
            'string' => 'text',
            'text' => 'textarea',
            'int' => 'number',
            'integer' => 'number',
            'float' => 'number',
            'decimal' => 'number',
            'bool' => 'checkbox',
            'boolean' => 'checkbox',
            'date' => 'date',
            'datetime' => 'datetime-local',
            'time' => 'time',
            'list' => 'select',
            'array' => 'select',
            'email' => 'email',
            'url' => 'url',
            'tel' => 'tel',
        ];

        return $typeMap[$modelType] ?? 'text';
    }
}
