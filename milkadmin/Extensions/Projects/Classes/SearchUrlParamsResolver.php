<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

/**
 * Resolve and sanitize allowed URL params declared in search_filters config.
 */
class SearchUrlParamsResolver
{
    /**
     * @param array<string,mixed> $searchFiltersConfig
     * @param array<string,mixed>|null $request
     * @return array{
     *   params:array<string,int|float|string>,
     *   filters:array<int,array{name:string,field:string,operator:string,value:int|float|string}>,
     *   required_failed:bool
     * }
     */
    public static function resolveFromConfig(array $searchFiltersConfig, ?array $request = null): array
    {
        $resolved = [
            'params' => [],
            'filters' => [],
            'required_failed' => false,
        ];

        $definitions = is_array($searchFiltersConfig['url_params'] ?? null)
            ? $searchFiltersConfig['url_params']
            : [];
        if (empty($definitions)) {
            return $resolved;
        }

        $source = is_array($request) ? $request : (is_array($_REQUEST ?? null) ? $_REQUEST : []);
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $name = trim((string) ($definition['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $required = self::normalizeBool($definition['required'] ?? false);
            if (!array_key_exists($name, $source)) {
                if ($required) {
                    $resolved['required_failed'] = true;
                }
                continue;
            }

            $sanitized = self::sanitizeValue($source[$name], $definition);
            if (!$sanitized['valid']) {
                if ($required) {
                    $resolved['required_failed'] = true;
                }
                continue;
            }

            $value = $sanitized['value'];
            $field = trim((string) ($definition['field'] ?? $name));
            if ($field === '') {
                $field = $name;
            }
            $operator = trim((string) ($definition['operator'] ?? 'equals'));
            if ($operator === '') {
                $operator = 'equals';
            }

            $resolved['params'][$name] = $value;
            $resolved['filters'][] = [
                'name' => $name,
                'field' => $field,
                'operator' => $operator,
                'value' => $value,
            ];
        }

        return $resolved;
    }

    /**
     * @param array<string,mixed> $definition
     * @return array{valid:bool,value:int|float|string}
     */
    protected static function sanitizeValue(mixed $raw, array $definition): array
    {
        if (is_array($raw) || is_object($raw)) {
            return ['valid' => false, 'value' => ''];
        }

        $type = strtolower(trim((string) ($definition['type'] ?? 'string')));
        $maxLength = (int) ($definition['max_length'] ?? 255);
        if ($maxLength <= 0 || $maxLength > 4096) {
            $maxLength = 255;
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return ['valid' => false, 'value' => ''];
        }

        if (strlen($text) > $maxLength) {
            return ['valid' => false, 'value' => ''];
        }

        if ($type === 'int') {
            if (preg_match('/^-?\d+$/', $text) !== 1) {
                return ['valid' => false, 'value' => ''];
            }
            return ['valid' => true, 'value' => (int) $text];
        }

        if ($type === 'float') {
            $float = filter_var($text, FILTER_VALIDATE_FLOAT);
            if ($float === false || !is_finite((float) $float)) {
                return ['valid' => false, 'value' => ''];
            }
            return ['valid' => true, 'value' => (float) $float];
        }

        if ($type === 'bool') {
            $normalized = strtolower($text);
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return ['valid' => true, 'value' => 1];
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return ['valid' => true, 'value' => 0];
            }
            return ['valid' => false, 'value' => ''];
        }

        if ($type === 'uuid') {
            if (preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[1-5][a-fA-F0-9]{3}-[89abAB][a-fA-F0-9]{3}-[a-fA-F0-9]{12}$/', $text) !== 1) {
                return ['valid' => false, 'value' => ''];
            }
            return ['valid' => true, 'value' => strtolower($text)];
        }

        if ($type === 'slug') {
            if (preg_match('/^[A-Za-z0-9_-]+$/', $text) !== 1) {
                return ['valid' => false, 'value' => ''];
            }
            return ['valid' => true, 'value' => $text];
        }

        $sanitized = str_replace("\0", '', $text);
        $sanitized = trim($sanitized);
        if ($sanitized === '') {
            return ['valid' => false, 'value' => ''];
        }
        if (strlen($sanitized) > $maxLength) {
            return ['valid' => false, 'value' => ''];
        }

        return ['valid' => true, 'value' => $sanitized];
    }

    protected static function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

