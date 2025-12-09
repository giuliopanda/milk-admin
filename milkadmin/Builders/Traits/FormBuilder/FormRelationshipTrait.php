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

        // Aggiungi attributi dal rule
        if (isset($fieldRule['form-params'])) {
            foreach ($fieldRule['form-params'] as $param => $value) {
                $fieldConfig[$param] = $value;
            }
        }

        // Gestisci campi con opzioni (select, list, ecc.)
        if (isset($fieldRule['options'])) {
            $fieldConfig['options'] = $fieldRule['options'];
        }

        // Aggiungi validazioni comuni
        if (isset($fieldRule['nullable']) && !$fieldRule['nullable']) {
            $fieldConfig['required'] = true;
        }
        if (isset($fieldRule['length'])) {
            $fieldConfig['maxlength'] = $fieldRule['length'];
        }

        // STEP 6.5: Se è la prima volta che aggiungiamo un campo da questa relazione hasOne
        // aggiungi campi hidden per tutti i campi required che non sono già stati aggiunti
        if (!isset($this->addedRelationships[$relationship_alias])) {
            $this->addedRelationships[$relationship_alias] = true;

            // Solo per hasOne (non belongsTo) aggiungiamo i campi hidden
            if ($relation['type'] === 'hasOne') {
                $this->addHiddenRequiredFields($relationship_alias, $relatedRules, $relatedObject, $position_before);
            }
        }

        // STEP 7: Inserisci il campo nella posizione corretta
        if (!empty($position_before) && isset($this->fields[$position_before])) {
            // Inserisci prima del campo specificato
            $newFields = [];
            foreach ($this->fields as $key => $value) {
                if ($key === $position_before) {
                    $newFields[$formFieldName] = $fieldConfig;
                }
                $newFields[$key] = $value;
            }
            $this->fields = $newFields;
        } else {
            // Aggiungi in fondo
            $this->fields[$formFieldName] = $fieldConfig;
        }

        return $this;
    }

    /**
     * Aggiunge campi hidden per tutti i campi required della relazione hasOne
     * che non sono già stati aggiunti manualmente
     *
     * @param string $relationship_alias Alias della relazione (es. 'badge')
     * @param array $relatedRules Rules del modello correlato
     * @param mixed $relatedObject Oggetto correlato con i valori correnti
     * @param string $position_before Posizione dove inserire i campi
     */
    private function addHiddenRequiredFields(string $relationship_alias, array $relatedRules, $relatedObject, string $position_before): void {

        foreach ($relatedRules as $fieldName => $fieldRule) {
            // Salta campi speciali
            if ($fieldName === 'id' || strpos($fieldName, '___') === 0) {
                continue;
            }

            // Salta campi che sono già stati aggiunti esplicitamente
            $formFieldName = "{$relationship_alias}[{$fieldName}]";
            if (isset($this->fields[$formFieldName])) {
                continue;
            }

            // Salta campi non required (nullable o con default)
            $isRequired = isset($fieldRule['form-params']['required']) && $fieldRule['form-params']['required'];
            $hasDefault = isset($fieldRule['default']) && $fieldRule['default'] !== null && $fieldRule['default'] !== '';

            if (!$isRequired && !$hasDefault) {
                continue;
            }

            // Estrai il valore corrente
            $currentValue = '';
            if (is_array($relatedObject)) {
                $currentValue = $relatedObject[$fieldName] ?? ($fieldRule['default'] ?? '');
            } elseif (is_object($relatedObject)) {
                $currentValue = $relatedObject->$fieldName ?? ($fieldRule['default'] ?? '');
            } else {
                $currentValue = $fieldRule['default'] ?? '';
            }

            // Crea campo hidden
            $hiddenFieldConfig = [
                'name' => $formFieldName,
                'label' => '', // Hidden fields non hanno label visibile
                'type' => 'hidden',
                'row_value' => $currentValue,
                'relationship_context' => [
                    'alias' => $relationship_alias,
                    'field' => $fieldName,
                    'auto_added' => true // Flag per indicare che è stato aggiunto automaticamente
                ]
            ];

            // Aggiungi il campo hidden
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
