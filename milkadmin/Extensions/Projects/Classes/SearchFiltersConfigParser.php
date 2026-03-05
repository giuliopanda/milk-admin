<?php
namespace Extensions\Projects\Classes;

use App\Exceptions\FileException;
use App\File;

!defined('MILK_DIR') && die();

/**
 * Parse and normalize Project search filters configuration.
 *
 * Supported input shapes:
 * 1) Single form/default config:
 *    { "search_mode": "...", "filters": [ ... ] }
 * 2) Multi-form config:
 *    { "forms": { "FormName": { ... }, "*": { ... } } }
 *
 * Returned structure:
 *   array<string,array{
 *     search_mode:string,
 *     auto_buttons:bool,
 *     wrapper_class:string,
 *     form_classes:string,
 *     container_classes:string,
 *     url_params:array<int,array{
 *       name:string,
 *       field:string,
 *       operator:string,
 *       type:string,
 *       required:bool,
 *       max_length:int
 *     }>,
 *     filters:array<int,array<string,mixed>>
 *   }>
 */
class SearchFiltersConfigParser
{
    /** @var string[] */
    protected array $warnings = [];

    /**
     * @throws \RuntimeException
     * @return array<string,array<string,mixed>>
     */
    public function parseFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Search filters file not found: {$path}");
        }

        try {
            $json = File::getContents($path);
        } catch (FileException $e) {
            throw new \RuntimeException("Search filters file is empty: {$path}", 0, $e);
        }

        if (trim($json) === '') {
            throw new \RuntimeException("Search filters file is empty: {$path}");
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid JSON in search filters: {$e->getMessage()}");
        }

        if (!is_array($data)) {
            throw new \RuntimeException('Search filters configuration must be a JSON object.');
        }

        return $this->parseArray($data);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function parseArray(array $data): array
    {
        $this->warnings = [];
        $result = [];

        $forms = $data['forms'] ?? null;
        if (is_array($forms)) {
            foreach ($forms as $formName => $formConfigRaw) {
                if (!is_string($formName)) {
                    $this->warnings[] = 'Search filters entry with non-string form key skipped.';
                    continue;
                }

                $normalizedFormName = trim($formName);
                if ($normalizedFormName === '') {
                    $this->warnings[] = 'Search filters entry with empty form key skipped.';
                    continue;
                }

                if (!is_array($formConfigRaw)) {
                    $this->warnings[] = "Search filters for '{$normalizedFormName}' must be an object.";
                    continue;
                }

                $result[$normalizedFormName] = $this->parseFormConfig($formConfigRaw, $normalizedFormName);
            }

            return $result;
        }

        // Single/default configuration.
        $result['*'] = $this->parseFormConfig($data, '*');
        return $result;
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function parseFormConfig(array $data, string $label): array
    {
        $searchMode = $this->normalizeSearchMode(
            (string) ($data['search_mode'] ?? ($data['searchMode'] ?? 'submit'))
        );
        $autoButtons = $this->normalizeBool($data['auto_buttons'] ?? ($data['autoButtons'] ?? true));
        $wrapperClass = trim((string) ($data['wrapper_class'] ?? ($data['wrapperClass'] ?? '')));
        $formClasses = trim((string) ($data['form_classes'] ?? ($data['formClasses'] ?? '')));
        $containerClasses = trim((string) ($data['container_classes'] ?? ($data['containerClass'] ?? '')));
        $urlParams = $this->normalizeUrlParams($data['url_params'] ?? ($data['urlParams'] ?? []), $label);

        $filtersRaw = $data['filters'] ?? [];
        if (!is_array($filtersRaw)) {
            $this->warnings[] = "Search filters '{$label}' has non-array 'filters'; ignored.";
            $filtersRaw = [];
        }

        $filters = [];
        foreach ($filtersRaw as $index => $filterRaw) {
            if (!is_array($filterRaw)) {
                $this->warnings[] = "Search filters '{$label}' entry #{$index} is not an object; skipped.";
                continue;
            }

            $parsed = $this->parseFilterConfig($filterRaw, $label, (int) $index);
            if ($parsed !== null) {
                $filters[] = $parsed;
            }
        }

        return [
            'search_mode' => $searchMode,
            'auto_buttons' => $autoButtons,
            'wrapper_class' => $wrapperClass,
            'form_classes' => $formClasses,
            'container_classes' => $containerClasses,
            'url_params' => $urlParams,
            'filters' => $filters,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    protected function parseFilterConfig(array $data, string $label, int $index): ?array
    {
        $type = $this->normalizeFilterType((string) ($data['type'] ?? 'search_all'));
        if ($type === '') {
            $this->warnings[] = "Search filters '{$label}' entry #{$index} has unknown type; skipped.";
            return null;
        }

        if ($type === 'newline') {
            return [
                'type' => 'newline',
            ];
        }

        if (in_array($type, ['search_button', 'clear_button'], true)) {
            $defaultLabel = $type === 'search_button' ? 'Search' : 'Clear';
            $defaultClass = $type === 'search_button' ? 'btn btn-primary' : 'btn btn-secondary';

            return [
                'type' => $type,
                'name' => '',
                'label' => trim((string) ($data['label'] ?? $defaultLabel)),
                'placeholder' => '',
                'layout' => 'inline',
                'class' => trim((string) ($data['class'] ?? $defaultClass)),
                'input_type' => 'text',
                'options' => [],
                'has_default' => false,
                'default' => null,
                'query' => null,
            ];
        }

        $name = trim((string) ($data['name'] ?? ($data['filter_type'] ?? ($data['filter'] ?? ''))));
        if ($name === '') {
            $this->warnings[] = "Search filters '{$label}' entry #{$index} is missing 'name'; skipped.";
            return null;
        }

        $inputType = trim((string) ($data['input_type'] ?? ($data['inputType'] ?? 'text')));
        if ($inputType === '') {
            $inputType = 'text';
        }

        $hasDefault = array_key_exists('default', $data) || array_key_exists('default_value', $data);
        $defaultValue = $data['default'] ?? ($data['default_value'] ?? null);

        $queryRaw = is_array($data['query'] ?? null) ? $data['query'] : [];
        $operator = $this->normalizeOperator((string) ($queryRaw['operator'] ?? ($data['operator'] ?? '')));
        if ($operator === '') {
            $operator = in_array($type, ['select', 'action_list'], true) ? 'equals' : 'like';
        }
        if ($type === 'search_all') {
            $operator = 'like';
        }

        $fields = [];
        if ($type !== 'search_all') {
            $queryFields = $queryRaw['fields'] ?? ($data['fields'] ?? null);
            if (is_array($queryFields)) {
                foreach ($queryFields as $field) {
                    if (!is_string($field)) {
                        continue;
                    }
                    $field = trim($field);
                    if ($this->isSafeQueryFieldIdentifier($field)) {
                        $fields[] = $field;
                    }
                }
            }

            $singleField = trim((string) ($queryRaw['field'] ?? ($data['field'] ?? '')));
            if ($this->isSafeQueryFieldIdentifier($singleField)) {
                $fields[] = $singleField;
            }

            if (empty($fields) && $this->isSafeIdentifier($name)) {
                $fields[] = $name;
            }
        }

        $fields = array_values(array_unique($fields));
        if ($type !== 'search_all' && empty($fields)) {
            $this->warnings[] = "Search filters '{$label}' entry #{$index} has no valid query field; skipped.";
            return null;
        }

        return [
            'type' => $type,
            'name' => $name,
            'label' => trim((string) ($data['label'] ?? '')),
            'placeholder' => trim((string) ($data['placeholder'] ?? '')),
            'layout' => 'inline',
            'class' => '',
            'input_type' => $inputType,
            'options' => $this->normalizeOptions($data['options'] ?? []),
            'has_default' => $hasDefault,
            'default' => $defaultValue,
            'query' => [
                'operator' => $operator,
                'fields' => $fields,
            ],
        ];
    }

    protected function normalizeFilterType(string $value): string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'search', 'search_all', 'search-all', 'searchall' => 'search_all',
            'select', 'input', 'search_button', 'clear_button', 'newline' => $value,
            'action_list', 'actionlist', 'action-list' => 'action_list',
            default => '',
        };
    }

    protected function normalizeLayout(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['inline', 'stacked', 'full-width'], true)) {
            return $value;
        }
        return 'inline';
    }

    protected function normalizeOperator(string $value): string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'eq' => 'equals',
            'start' => 'starts_with',
            'end' => 'ends_with',
            'gt', '>' => 'greater_than',
            'gte', '>=' => 'greater_or_equal',
            'lt', '<' => 'less_than',
            'lte', '<=' => 'less_or_equal',
            'between' => 'between',
            'like', 'equals', 'starts_with', 'ends_with',
            'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal' => $value,
            default => '',
        };
    }

    protected function normalizeSearchMode(string $value): string
    {
        return 'submit';
    }

    /**
     * @return array<int,array{
     *   name:string,
     *   field:string,
     *   operator:string,
     *   type:string,
     *   required:bool,
     *   max_length:int
     * }>
     */
    protected function normalizeUrlParams(mixed $raw, string $label): array
    {
        if (!is_array($raw)) {
            if ($raw !== null && $raw !== '') {
                $this->warnings[] = "Search filters '{$label}' has non-array 'url_params'; ignored.";
            }
            return [];
        }

        $result = [];
        $seen = [];
        $isSequential = array_keys($raw) === range(0, count($raw) - 1);

        foreach ($raw as $key => $value) {
            $entry = is_array($value) ? $value : [];
            $name = '';

            if ($isSequential) {
                $name = trim((string) ($entry['name'] ?? ''));
            } else {
                $name = is_string($key) ? trim($key) : '';
            }

            if (!$this->isSafeUrlParamName($name)) {
                $this->warnings[] = "Search filters '{$label}' has invalid url param name '{$name}'; skipped.";
                continue;
            }

            $lowerName = strtolower($name);
            if (isset($seen[$lowerName])) {
                continue;
            }

            $field = trim((string) ($entry['field'] ?? $name));
            if (!$this->isSafeIdentifier($field)) {
                $this->warnings[] = "Search filters '{$label}' url param '{$name}' has invalid field; skipped.";
                continue;
            }

            $type = $this->normalizeUrlParamType((string) ($entry['type'] ?? 'string'));
            if ($type === '') {
                $this->warnings[] = "Search filters '{$label}' url param '{$name}' has unsupported type; skipped.";
                continue;
            }

            $operator = $this->normalizeOperator((string) ($entry['operator'] ?? 'equals'));
            if ($operator === '') {
                $operator = 'equals';
            }

            $maxLength = 255;
            if ($type === 'uuid') {
                $maxLength = 36;
            } elseif ($type === 'slug') {
                $maxLength = 128;
            } elseif ($type === 'bool') {
                $maxLength = 5;
            } elseif ($type === 'int' || $type === 'float') {
                $maxLength = 32;
            }

            if (isset($entry['max_length']) && is_scalar($entry['max_length'])) {
                $customLength = (int) $entry['max_length'];
                if ($customLength > 0 && $customLength <= 4096) {
                    $maxLength = $customLength;
                }
            }

            $result[] = [
                'name' => $name,
                'field' => $field,
                'operator' => $operator,
                'type' => $type,
                'required' => $this->normalizeBool($entry['required'] ?? false),
                'max_length' => $maxLength,
            ];
            $seen[$lowerName] = true;
        }

        return $result;
    }

    protected function normalizeUrlParamType(string $value): string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'int', 'integer' => 'int',
            'float', 'double', 'decimal' => 'float',
            'bool', 'boolean' => 'bool',
            'string', 'text' => 'string',
            'uuid' => 'uuid',
            'slug' => 'slug',
            default => '',
        };
    }

    /**
     * @return array<string,string>
     */
    protected function normalizeOptions(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        $isSequential = array_keys($raw) === range(0, count($raw) - 1);

        if ($isSequential) {
            foreach ($raw as $item) {
                if (is_array($item)) {
                    $value = isset($item['value']) ? (string) $item['value'] : '';
                    $label = isset($item['label']) ? (string) $item['label'] : $value;
                    $result[$value] = $label;
                    continue;
                }

                if (is_scalar($item)) {
                    $value = (string) $item;
                    $result[$value] = $value;
                }
            }

            return $result;
        }

        foreach ($raw as $k => $v) {
            $result[(string) $k] = is_scalar($v) ? (string) $v : '';
        }
        return $result;
    }

    protected function isSafeIdentifier(string $value): bool
    {
        return $value !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    protected function isSafeQueryFieldIdentifier(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if ($this->isSafeIdentifier($value)) {
            return true;
        }

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    protected function isSafeUrlParamName(string $value): bool
    {
        return $value !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $value) === 1;
    }

    protected function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return false;
        }
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
