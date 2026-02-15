<?php
namespace App;

use App\{MessagesHandler, ExpressionParser};

!defined('MILK_DIR') && die();

/**
 * ModelValidator - Validazione dei dati di un model basata sulle regole
 */
class ModelValidator
{
    /**
     * @var array
     */
    protected array $rules;

    /**
     * @param array $rules Regole del model
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Valida un singolo record
     *
     * @param array $data
     * @return bool
     */
    public function validate(array $data): bool
    {
        $rules = $this->rules;
        if (empty($rules)) {
            return true;
        }

        $parameters = $data;
        foreach ($rules as $field_name => $_) {
            if ($field_name === '___action') {
                continue;
            }
            if (!array_key_exists($field_name, $parameters)) {
                $parameters[$field_name] = null;
            }
        }

        $parse_datetime_value = static function ($value): ?int {
            if ($value instanceof \DateTimeInterface) {
                return $value->getTimestamp();
            }
            if (!is_scalar($value)) {
                return null;
            }
            $timestamp = strtotime((string) $value);
            if ($timestamp === false) {
                return null;
            }
            return $timestamp;
        };

        $parse_time_value = static function ($value): ?int {
            if ($value instanceof \DateTimeInterface) {
                $hours = (int) $value->format('H');
                $minutes = (int) $value->format('i');
                $seconds = (int) $value->format('s');
                return ($hours * 3600) + ($minutes * 60) + $seconds;
            }
            if (!is_scalar($value)) {
                return null;
            }
            $time_string = trim((string) $value);
            if ($time_string === '') {
                return null;
            }
            if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $time_string, $matches)) {
                $hours = (int) $matches[1];
                $minutes = (int) $matches[2];
                $seconds = isset($matches[3]) ? (int) $matches[3] : 0;
                return ($hours * 3600) + ($minutes * 60) + $seconds;
            }
            $timestamp = strtotime($time_string);
            if ($timestamp === false) {
                return null;
            }
            return ((int) date('H', $timestamp) * 3600) + ((int) date('i', $timestamp) * 60) + (int) date('s', $timestamp);
        };

        $resolve_field_label = static function (string $field, array $rules): string {
            return $rules[$field]['label'] ?? $field;
        };

        foreach ($rules as $field_name => $rule) {
            if ($field_name === '___action') {
                continue;
            }

            $form_type = $rule['form-type'] ?? null;
            if ($form_type === 'datetime-local') {
                $form_type = 'datetime';
            }
            $rule_type = $rule['type'] ?? null;
            $type  = $form_type ?? $rule_type ?? null;
            $value = $data[$field_name] ?? null;
            $form_params = $rule['form-params'] ?? [];

            $required = (bool) ($rule['form-params']['required'] ?? false);
            $required_expr = $rule['required_expr'] ?? null;
            if (is_string($required_expr) && trim($required_expr) !== '') {
                try {
                    $parser = new ExpressionParser();
                    $parser->setParameters($parameters);
                    $required_result = $parser->execute($required_expr);
                    $required = (bool) $required_result;
                } catch (\Throwable $e) {
                    MessagesHandler::addError(
                        'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid',
                        $field_name
                    );
                    continue;
                }
            }
            $nullable = (bool) ($rule['nullable'] ?? ($rule['form-params']['nullable'] ?? false));

            $is_missing_for_required = ($value === null || $value === '' || $value === []);
            $is_empty_for_nullable   = ($value === null || $value === '');

            if ($required && $is_missing_for_required) {
                MessagesHandler::addError(
                    'the field <b>' . ($rule['label'] ?? $field_name) . '</b> is required',
                    $field_name
                );
                continue;
            }

            if ($nullable && $is_empty_for_nullable) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $field_has_error = false;
            $skip_expression = false;
            $add_error = static function (string $message) use (&$field_has_error, $field_name): void {
                MessagesHandler::addError($message, $field_name);
                $field_has_error = true;
            };

            $handled = false;

            if ($type === 'email') {
                $handled = true;
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $add_error('Invalid Email');
                }
            } elseif ($type === 'url') {
                $handled = true;
                if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                    $add_error('Invalid Url');
                }
            } else {
                $numeric_rule_types = ['id', 'int', 'tinyint', 'float'];
                $numeric_form_types = ['number', 'range'];
                $is_numeric_type = in_array($rule_type, $numeric_rule_types, true) || in_array($form_type, $numeric_form_types, true);
                if ($is_numeric_type) {
                    $handled = true;
                    if (($rule['primary'] === true && ($value === null || $value === ''))) {
                        $handled = true;
                        $skip_expression = true;
                    } else {
                        if (!is_scalar($value) || !is_numeric($value)) {
                            $add_error(
                                'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid. Must be numeric'
                            );
                        } elseif (in_array($rule_type, ['id', 'int', 'tinyint'], true) && filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $add_error(
                                'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid. Must be an integer'
                            );
                        } elseif ($rule_type === 'float' && filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                            $add_error(
                                'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid. Must be a float'
                            );
                        }

                        if (!$field_has_error) {
                            $numeric_value = (float) $value;
                            if (($rule['unsigned'] ?? false) && $numeric_value < 0) {
                                $add_error(
                                    'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be greater than or equal to 0'
                                );
                            }
                        }

                        if (!$field_has_error) {
                            $min = $form_params['min'] ?? null;
                            $min_label = $min;
                            if ($min !== null && $min !== '' && !is_numeric($min) && is_string($min) && array_key_exists($min, $data)) {
                                $min_label = $resolve_field_label($min, $rules);
                                $min = $data[$min] ?? null;
                            }
                            if (($min === null || $min === '') && isset($rule['min_field']) && is_string($rule['min_field']) && array_key_exists($rule['min_field'], $data)) {
                                $min_label = $resolve_field_label($rule['min_field'], $rules);
                                $min = $data[$rule['min_field']] ?? null;
                            }
                            if ($min !== null && $min !== '' && is_numeric($min) && $numeric_value < (float) $min) {
                                $add_error(
                                    'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be greater than or equal to ' . $min_label
                                );
                            }

                            if (!$field_has_error) {
                                $max = $form_params['max'] ?? null;
                                $max_label = $max;
                                if ($max !== null && $max !== '' && !is_numeric($max) && is_string($max) && array_key_exists($max, $data)) {
                                    $max_label = $resolve_field_label($max, $rules);
                                    $max = $data[$max] ?? null;
                                }
                                if (($max === null || $max === '') && isset($rule['max_field']) && is_string($rule['max_field']) && array_key_exists($rule['max_field'], $data)) {
                                    $max_label = $resolve_field_label($rule['max_field'], $rules);
                                    $max = $data[$rule['max_field']] ?? null;
                                }
                                if ($max !== null && $max !== '' && is_numeric($max) && $numeric_value > (float) $max) {
                                    $add_error(
                                        'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be less than or equal to ' . $max_label
                                    );
                                }
                            }

                            if (!$field_has_error) {
                                $step = $form_params['step'] ?? null;
                                if ($step !== null && $step !== '' && $step !== 'any' && is_numeric($step)) {
                                    $step_value = (float) $step;
                                    if ($step_value > 0) {
                                        $base = ($min !== null && $min !== '' && is_numeric($min)) ? (float) $min : 0.0;
                                        $remainder = fmod($numeric_value - $base, $step_value);
                                        $epsilon = 1.0E-9;
                                        if (abs($remainder) > $epsilon && abs($remainder - $step_value) > $epsilon) {
                                            $add_error(
                                                'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be a multiple of ' . $step
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                } elseif ($type === 'bool') {
                    $handled = true;
                    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($bool === null) {
                        $add_error('Invalid Boolean');
                    }
                } elseif ($type === 'datetime' || $type === 'date' || $type === 'time') {
                    $handled = true;
                    $parsed_value = ($type === 'time') ? $parse_time_value($value) : $parse_datetime_value($value);
                    if ($parsed_value === null) {
                        $add_error('Invalid Date');
                    } else {
                        $min = $form_params['min'] ?? null;
                        $min_label = $min;
                        $min_value = $min;
                        if ($min !== null && $min !== '' && is_string($min) && array_key_exists($min, $data)) {
                            $min_label = $resolve_field_label($min, $rules);
                            $min_value = $data[$min] ?? null;
                        }
                        if (($min_value === null || $min_value === '') && isset($rule['min_field']) && is_string($rule['min_field']) && array_key_exists($rule['min_field'], $data)) {
                            $min_label = $resolve_field_label($rule['min_field'], $rules);
                            $min_value = $data[$rule['min_field']] ?? null;
                        }
                        if ($min_value !== null && $min_value !== '') {
                            $parsed_min = ($type === 'time') ? $parse_time_value($min_value) : $parse_datetime_value($min_value);
                            if ($parsed_min !== null && $parsed_value < $parsed_min) {
                                $add_error(
                                    'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be after or equal to ' . $min_label
                                );
                            }
                        }

                        if (!$field_has_error) {
                            $max = $form_params['max'] ?? null;
                            $max_label = $max;
                            $max_value = $max;
                            if ($max !== null && $max !== '' && is_string($max) && array_key_exists($max, $data)) {
                                $max_label = $resolve_field_label($max, $rules);
                                $max_value = $data[$max] ?? null;
                            }
                            if (($max_value === null || $max_value === '') && isset($rule['max_field']) && is_string($rule['max_field']) && array_key_exists($rule['max_field'], $data)) {
                                $max_label = $resolve_field_label($rule['max_field'], $rules);
                                $max_value = $data[$rule['max_field']] ?? null;
                            }
                            if ($max_value !== null && $max_value !== '') {
                                $parsed_max = ($type === 'time') ? $parse_time_value($max_value) : $parse_datetime_value($max_value);
                                if ($parsed_max !== null && $parsed_value > $parsed_max) {
                                    $add_error(
                                        'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be before or equal to ' . $max_label
                                    );
                                }
                            }
                        }
                    }
                } elseif ($type === 'enum') {
                    $handled = true;
                    if (!in_array($value, $rule['options'] ?? [], true)) {
                        $add_error(
                            'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid'
                        );
                    }
                } elseif ($type === 'list') {
                    $handled = true;
                    $key = is_scalar($value) ? (string) $value : '';
                    if (!array_key_exists($key, $rule['options'] ?? [])) {
                        $add_error('Invalid List');
                    }
                } elseif ($rule_type === 'string' || $rule_type === 'text') {
                    $handled = true;
                    $check_value = is_array($value) ? json_encode($value) : (string) $value;

                    $min_length = $form_params['minlength'] ?? ($form_params['min_length'] ?? null);
                    if (($min_length === null || $min_length === '') && isset($rule['min_length'])) {
                        $min_length = $rule['min_length'];
                    }
                    if (($min_length === null || $min_length === '') && isset($form_params['min'])) {
                        $min_length = $form_params['min'];
                    }
                    if ($min_length !== null && $min_length !== '' && is_numeric($min_length) && strlen($check_value) < (int) $min_length) {
                        $add_error(
                            'Field <b>' . ($rule['label'] ?? $field_name) . '</b> is too short. Min length is ' . (int) $min_length
                        );
                    }

                    if (!$field_has_error) {
                        $pattern = $form_params['pattern'] ?? ($rule['pattern'] ?? ($rule['regex'] ?? null));
                        if (is_string($pattern) && $pattern !== '') {
                            $regex = $pattern;
                            if ($pattern[0] !== '/' || strrpos($pattern, '/') === 0) {
                                $regex = '/' . str_replace('/', '\\/', $pattern) . '/';
                            }
                            if (@preg_match($regex, '') !== false && preg_match($regex, $check_value) !== 1) {
                                $add_error(
                                    'The field <b>' . ($rule['label'] ?? $field_name) . '</b> format is invalid'
                                );
                            }
                        }
                    }

                    if (!$field_has_error) {
                        $max_length = $form_params['maxlength'] ?? ($form_params['max_length'] ?? null);
                        if (($max_length === null || $max_length === '') && isset($rule['max_length'])) {
                            $max_length = $rule['max_length'];
                        }
                        if (($max_length === null || $max_length === '') && isset($form_params['max'])) {
                            $max_length = $form_params['max'];
                        }
                        if (($max_length === null || $max_length === '') && isset($rule['length'])) {
                            $max_length = $rule['length'];
                        }
                        if ($max_length !== null && $max_length !== '' && is_numeric($max_length) && strlen($check_value) > (int) $max_length) {
                            $add_error(
                                'Field <b>' . ($rule['label'] ?? $field_name) . '</b> is too long. Max length is ' . (int) $max_length
                            );
                        }
                    }
                }
            }

            if (!$field_has_error && !$skip_expression) {
                $this->validateExpression($rule, $field_name, $parameters);
            }

            if ($handled) {
                continue;
            }
        }

        return !MessagesHandler::hasErrors();
    }

    /**
     * Valida più record
     *
     * @param array $records
     * @return bool
     */
    public function validateRecords(array $records): bool
    {
        foreach ($records as $record) {
            $this->validate($record);
        }
        return !MessagesHandler::hasErrors();
    }

    /**
     * Valida un'espressione di validazione se presente nella regola
     *
     * @param array $rule
     * @param string $field_name
     * @param array $parameters
     * @return void
     */
    protected function validateExpression(array $rule, string $field_name, array $parameters): void
    {
        $expression = $rule['validate_expr'] ?? ($rule['validation_expr'] ?? ($rule['validation'] ?? null));
        if (!is_string($expression) || trim($expression) === '') {
            return;
        }

        try {
            $parser = new ExpressionParser();
            $parser->setParameters($parameters);
            $result = $parser->execute($expression);
        } catch (\Throwable $e) {
            $this->addExpressionError($rule, $field_name);
            return;
        }

        if ($result === true) {
            return;
        }

        if (is_string($result) && trim($result) !== '') {
            MessagesHandler::addError($result, $field_name);
            return;
        }

        $this->addExpressionError($rule, $field_name);
    }

    /**
     * @param array $rule
     * @param string $field_name
     * @return void
     */
    protected function addExpressionError(array $rule, string $field_name): void
    {
        $form_params = $rule['form-params'] ?? [];
        $message = $form_params['invalid-feedback'] ?? ($rule['validation_message'] ?? null);
        if (!is_string($message) || trim($message) === '') {
            $message = 'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid';
        }
        MessagesHandler::addError($message, $field_name);
    }
}
