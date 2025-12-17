<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * Trait for copying model rules to another RuleBuilder
 * Used primarily for creating audit tables with the same schema
 */
trait CopyRulesTrait
{
    /**
     * Copy all field rules from this model to a destination RuleBuilder
     *
     * @param \App\Abstracts\RuleBuilder $destination_rule The destination RuleBuilder
     * @return void
     */
    public function copyRules($destination_rule): void
    {
        $rules = $this->getRules();

        foreach ($rules as $field_name => $field_rule) {
            $type = $field_rule['type'] ?? 'string';

            // Create field based on type
            switch ($type) {
                case 'id':
                    $destination_rule->id($field_name);
                    break;

                case 'string':
                    $length = $field_rule['length'] ?? 255;
                    $destination_rule->string($field_name, $length);
                    break;

                case 'text':
                    $destination_rule->text($field_name);
                    break;

                case 'int':
                    $destination_rule->int($field_name);
                    break;

                case 'float':
                case 'decimal':
                    $length = $field_rule['length'] ?? 10;
                    $precision = $field_rule['precision'] ?? 2;
                    $destination_rule->decimal($field_name, $length, $precision);
                    break;

                case 'bool':
                case 'boolean':
                    $destination_rule->boolean($field_name);
                    break;

                case 'date':
                    $destination_rule->date($field_name);
                    break;

                case 'datetime':
                    $destination_rule->datetime($field_name);
                    if (isset($field_rule['timezone_conversion']) && $field_rule['timezone_conversion'] === false) {
                        $destination_rule->noTimezoneConversion();
                    }
                    break;

                case 'time':
                    $destination_rule->time($field_name);
                    break;

                case 'list':
                    $options = $field_rule['options'] ?? [];
                    $destination_rule->list($field_name, $options);
                    break;

                case 'enum':
                    $options = $field_rule['options'] ?? [];
                    $destination_rule->enum($field_name, $options);
                    break;

                case 'array':
                    $destination_rule->array($field_name);
                    break;

                default:
                    // For unknown types, use generic field method
                    $destination_rule->field($field_name, $type);
                    break;
            }

            // Apply additional properties
            if (isset($field_rule['nullable'])) {
                $destination_rule->nullable($field_rule['nullable']);
            }

            if (isset($field_rule['default'])) {
                $destination_rule->default($field_rule['default']);
            }

            if (isset($field_rule['label'])) {
                $destination_rule->label($field_rule['label']);
            }

            if (isset($field_rule['index']) && $field_rule['index']) {
                $destination_rule->index();
            }

            if (isset($field_rule['unique']) && $field_rule['unique']) {
                $destination_rule->unique();
            }

            // Copy form-related properties
            if (isset($field_rule['form-type'])) {
                $destination_rule->formType($field_rule['form-type']);
            }

            if (isset($field_rule['form-label'])) {
                $destination_rule->formLabel($field_rule['form-label']);
            }

            if (isset($field_rule['form-params'])) {
                // Copy form-params but remove validation rules like 'required'
                $form_params = $field_rule['form-params'];
                unset($form_params['required']); // No required validation in audit table
                if (!empty($form_params)) {
                    $destination_rule->formParams($form_params);
                }
            }

            // Copy custom properties (those starting with underscore)
            foreach ($field_rule as $prop_key => $prop_value) {
                if (strpos($prop_key, '_') === 0) {
                    // Custom property like _is_title_field, _auto_created_at, etc.
                    // Use reflection to access protected rules array
                    $reflection = new \ReflectionClass($destination_rule);
                    $rulesProperty = $reflection->getProperty('rules');
                    $rules_array = $rulesProperty->getValue($destination_rule);
                    $rules_array[$field_name][$prop_key] = $prop_value;
                    $rulesProperty->setValue($destination_rule, $rules_array);
                }
            }

            // Copy visibility properties
            if (isset($field_rule['list']) && !$field_rule['list']) {
                $destination_rule->hideFromList();
            }

            if (isset($field_rule['edit']) && !$field_rule['edit']) {
                $destination_rule->hideFromEdit();
            }

            if (isset($field_rule['view']) && !$field_rule['view']) {
                $destination_rule->hideFromView();
            }

            if (isset($field_rule['sql']) && !$field_rule['sql']) {
                $destination_rule->excludeFromDatabase();
            }
        }
    }
}
