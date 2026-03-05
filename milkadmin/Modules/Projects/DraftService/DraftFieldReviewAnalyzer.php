<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

class DraftFieldReviewAnalyzer
{
    private const MAX_DIFF_LINES = 5;
    private const MAX_LINE_LENGTH = 110;

    /**
     * @param array<string,mixed> $oldSchema
     * @param array<string,mixed> $newSchema
     * @return array{
     *   rows:array<int,array{
     *     name:string,
     *     status:string,
     *     status_label:string,
     *     before_lines:array<int,string>,
     *     after_lines:array<int,string>,
     *     changed_keys:array<int,string>,
     *     risk_level:string,
     *     risk_label:string,
     *     risk_note:string
     *   }>,
     *   summary:array{
     *     total:int,
     *     unchanged:int,
     *     modified:int,
     *     added:int,
     *     removed:int,
     *     warnings:int,
     *     dangers:int
     *   }
     * }
     */
    public static function build(array $oldSchema, array $newSchema): array
    {
        $old = self::extractFieldMap($oldSchema);
        $new = self::extractFieldMap($newSchema);

        $orderedKeys = [];
        foreach ($old['order'] as $key) {
            $orderedKeys[$key] = true;
        }
        foreach ($new['order'] as $key) {
            $orderedKeys[$key] = true;
        }

        $rows = [];
        $summary = [
            'total' => 0,
            'unchanged' => 0,
            'modified' => 0,
            'added' => 0,
            'removed' => 0,
            'warnings' => 0,
            'dangers' => 0,
        ];

        foreach (array_keys($orderedKeys) as $key) {
            $oldField = is_array($old['map'][$key] ?? null) ? $old['map'][$key] : null;
            $newField = is_array($new['map'][$key] ?? null) ? $new['map'][$key] : null;
            if (self::shouldHideFromReview($oldField, $newField)) {
                continue;
            }
            $row = self::buildRow($key, $oldField, $newField);
            $rows[] = $row;

            $summary['total']++;
            if (array_key_exists($row['status'], $summary)) {
                $summary[$row['status']]++;
            }
            if ($row['risk_level'] === 'warning') {
                $summary['warnings']++;
            } elseif ($row['risk_level'] === 'danger') {
                $summary['dangers']++;
            }
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * Hide technical fields from review table when they are builder-locked.
     *
     * @param array<string,mixed>|null $oldField
     * @param array<string,mixed>|null $newField
     */
    private static function shouldHideFromReview(?array $oldField, ?array $newField): bool
    {
        return (is_array($oldField) && DraftFieldUtils::isFieldBuilderLocked($oldField))
            || (is_array($newField) && DraftFieldUtils::isFieldBuilderLocked($newField));
    }

    /**
     * @param array<string,mixed> $schema
     * @return array{
     *   order:array<int,string>,
     *   map:array<string,array<string,mixed>>
     * }
     */
    private static function extractFieldMap(array $schema): array
    {
        $model = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $fields = is_array($model['fields'] ?? null) ? $model['fields'] : [];

        $order = [];
        $map = [];
        foreach ($fields as $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }

            $name = trim((string) ($fieldDef['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = strtolower($name);
            if (!array_key_exists($key, $map)) {
                $order[] = $key;
            }
            $map[$key] = $fieldDef;
        }

        return [
            'order' => $order,
            'map' => $map,
        ];
    }

    /**
     * @param array<string,mixed>|null $oldField
     * @param array<string,mixed>|null $newField
     * @return array{
     *   name:string,
     *   status:string,
     *   status_label:string,
     *   before_lines:array<int,string>,
     *   after_lines:array<int,string>,
     *   changed_keys:array<int,string>,
     *   risk_level:string,
     *   risk_label:string,
     *   risk_note:string
     * }
     */
    private static function buildRow(string $nameKey, ?array $oldField, ?array $newField): array
    {
        $resolvedName = trim((string) (($newField['name'] ?? '') ?: ($oldField['name'] ?? '')));
        if ($resolvedName === '') {
            $resolvedName = $nameKey;
        }

        $status = 'unchanged';
        $statusLabel = 'Unchanged';
        $changedKeys = [];

        if (is_array($oldField) && !is_array($newField)) {
            $status = 'removed';
            $statusLabel = 'Removed';
        } elseif (!is_array($oldField) && is_array($newField)) {
            $status = 'added';
            $statusLabel = 'New';
        } elseif (is_array($oldField) && is_array($newField)) {
            $changedKeys = self::collectChangedTopLevelKeys($oldField, $newField);
            if (!empty($changedKeys)) {
                $status = 'modified';
                $statusLabel = 'Modified';
            }
        }

        $risk = self::evaluateRisk($status, $oldField, $newField, $changedKeys);
        $diffLines = self::buildDiffLines($status, $oldField, $newField, $changedKeys);

        return [
            'name' => $resolvedName,
            'status' => $status,
            'status_label' => $statusLabel,
            'before_lines' => $diffLines['before_lines'],
            'after_lines' => $diffLines['after_lines'],
            'changed_keys' => $changedKeys,
            'risk_level' => $risk['level'],
            'risk_label' => $risk['label'],
            'risk_note' => $risk['note'],
        ];
    }

    /**
     * @param array<string,mixed> $oldField
     * @param array<string,mixed> $newField
     * @return array<int,string>
     */
    private static function collectChangedTopLevelKeys(array $oldField, array $newField): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($oldField), array_keys($newField))));
        sort($keys);
        $changed = [];

        foreach ($keys as $key) {
            if (!is_string($key) || $key === 'name') {
                continue;
            }
            $oldValue = self::normalizeForCompare($oldField[$key] ?? null);
            $newValue = self::normalizeForCompare($newField[$key] ?? null);
            if ($oldValue !== $newValue) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * @param array<string,mixed>|null $oldField
     * @param array<string,mixed>|null $newField
     * @param array<int,string> $changedKeys
     * @return array{level:string,label:string,note:string}
     */
    private static function evaluateRisk(string $status, ?array $oldField, ?array $newField, array $changedKeys): array
    {
        if ($status === 'removed') {
            return [
                'level' => 'danger',
                'label' => 'Potentially dangerous',
                'note' => 'Field removed: potential data loss for existing records.',
            ];
        }

        if ($status === 'added') {
            return [
                'level' => 'safe',
                'label' => 'OK',
                'note' => 'New field added. Usually safe for existing data.',
            ];
        }

        if ($status === 'unchanged') {
            return [
                'level' => 'safe',
                'label' => 'OK',
                'note' => 'No change detected.',
            ];
        }

        $reasons = [];
        $level = 'safe';

        $structuralKeys = ['method', 'dbType', 'length', 'precision', 'unsigned', 'excludeFromDatabase'];
        $structuralChanges = array_values(array_intersect($changedKeys, $structuralKeys));
        if (!empty($structuralChanges)) {
            $level = 'danger';
            $reasons[] = 'Database structure changed (' . implode(', ', $structuralChanges) . ').';
        }

        if (self::optionsChanged($oldField, $newField)) {
            if ($level !== 'danger') {
                $level = 'warning';
            }
            $reasons[] = 'Selectable values changed (select/radio/checkboxes).';
        }

        $oldRequired = DraftFieldUtils::normalizeBool($oldField['required'] ?? false);
        $newRequired = DraftFieldUtils::normalizeBool($newField['required'] ?? false);
        if (!$oldRequired && $newRequired) {
            if ($level === 'safe') {
                $level = 'warning';
            }
            $reasons[] = 'Field became required; existing rows may violate this constraint.';
        }

        if (empty($reasons)) {
            return [
                'level' => 'safe',
                'label' => 'OK',
                'note' => 'Configuration update without clear DB/data risk.',
            ];
        }

        return [
            'level' => $level,
            'label' => $level === 'danger' ? 'Potentially dangerous' : 'Needs attention',
            'note' => implode(' ', $reasons),
        ];
    }

    /**
     * @param array<string,mixed>|null $oldField
     * @param array<string,mixed>|null $newField
     */
    private static function optionsChanged(?array $oldField, ?array $newField): bool
    {
        if (!is_array($oldField) || !is_array($newField)) {
            return false;
        }

        $oldMethod = strtolower(trim((string) ($oldField['method'] ?? '')));
        $newMethod = strtolower(trim((string) ($newField['method'] ?? '')));
        $optionMethods = ['select', 'radio', 'checkboxes'];
        if (!in_array($oldMethod, $optionMethods, true) && !in_array($newMethod, $optionMethods, true)) {
            return false;
        }

        $oldOptions = self::normalizeForCompare($oldField['options'] ?? null);
        $newOptions = self::normalizeForCompare($newField['options'] ?? null);
        return $oldOptions !== $newOptions;
    }

    /**
     * @param array<string,mixed>|null $oldField
     * @param array<string,mixed>|null $newField
     * @param array<int,string> $changedKeys
     * @return array{before_lines:array<int,string>,after_lines:array<int,string>}
     */
    private static function buildDiffLines(string $status, ?array $oldField, ?array $newField, array $changedKeys): array
    {
        $before = [];
        $after = [];

        if ($status === 'modified' && is_array($oldField) && is_array($newField)) {
            foreach ($changedKeys as $key) {
                $lineKey = (string) $key;
                $before[] = self::toDiffLine($lineKey, $oldField[$lineKey] ?? null);
                $after[] = self::toDiffLine($lineKey, $newField[$lineKey] ?? null);
            }
        } elseif ($status === 'added' && is_array($newField)) {
            $before[] = '[not present]';
            $after = self::fieldSnapshotLines($newField);
        } elseif ($status === 'removed' && is_array($oldField)) {
            $before = self::fieldSnapshotLines($oldField);
            $after[] = '[deleted]';
        } else {
            $before[] = '[no changed lines]';
            $after[] = '[no changed lines]';
        }

        return [
            'before_lines' => self::compactLines($before),
            'after_lines' => self::compactLines($after),
        ];
    }

    /**
     * @param array<string,mixed> $field
     * @return array<int,string>
     */
    private static function fieldSnapshotLines(array $field): array
    {
        $lines = [];

        $priorityKeys = [
            'label',
            'method',
            'formType',
            'dbType',
            'length',
            'precision',
            'unsigned',
            'required',
            'excludeFromDatabase',
            'default',
            'options',
            'formParams',
            'calcExpr',
            'showInList',
            'showInEdit',
            'showInView',
            'builderLocked',
        ];
        foreach ($priorityKeys as $key) {
            if ($key === 'name') {
                continue;
            }
            if (array_key_exists($key, $field)) {
                $lines[] = self::toDiffLine($key, $field[$key]);
            }
        }

        $extraKeys = array_diff(array_keys($field), array_merge(['name'], $priorityKeys));
        sort($extraKeys);
        foreach ($extraKeys as $key) {
            if (!is_string($key)) {
                continue;
            }
            $lines[] = self::toDiffLine($key, $field[$key] ?? null);
        }

        if (empty($lines)) {
            $lines[] = '[empty]';
        }

        return $lines;
    }

    private static function toDiffLine(string $key, mixed $value): string
    {
        return $key . ': ' . self::valueToInlineText($value);
    }

    private static function valueToInlineText(mixed $value): string
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            $text = trim($value);
            return $text === '' ? "''" : $text;
        }
        if (!is_array($value)) {
            return gettype($value);
        }

        if (self::isAssoc($value)) {
            $parts = [];
            $keys = array_keys($value);
            sort($keys);
            $maxParts = 4;
            foreach ($keys as $idx => $key) {
                if (!is_string($key)) {
                    continue;
                }
                if ($idx >= $maxParts) {
                    $parts[] = '...';
                    break;
                }
                $parts[] = $key . '=' . self::valueToInlineText($value[$key]);
            }
            return '{' . implode(', ', $parts) . '}';
        }

        $parts = [];
        $maxItems = 5;
        foreach ($value as $idx => $item) {
            if ($idx >= $maxItems) {
                $parts[] = '...';
                break;
            }
            $parts[] = self::valueToInlineText($item);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param array<int,string> $lines
     * @return array<int,string>
     */
    private static function compactLines(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            $text = trim((string) $line);
            if ($text === '') {
                continue;
            }

            if (strlen($text) > self::MAX_LINE_LENGTH) {
                $text = substr($text, 0, self::MAX_LINE_LENGTH - 3) . '...';
            }
            $normalized[] = $text;
        }

        if (empty($normalized)) {
            return ['[none]'];
        }

        if (count($normalized) > self::MAX_DIFF_LINES) {
            $visibleCount = max(1, self::MAX_DIFF_LINES - 1);
            $hiddenCount = count($normalized) - $visibleCount;
            $normalized = array_slice($normalized, 0, $visibleCount);
            $normalized[] = '... (+' . $hiddenCount . ' more)';
        }

        return $normalized;
    }

    private static function normalizeForCompare(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        // Normalize numeric-string keys (e.g. "0", "1") to integers so
        // equivalent payloads from array/object JSON forms compare equal.
        $normalizedByKey = [];
        $allNumericKeys = true;
        foreach ($value as $key => $item) {
            $normalizedKey = $key;
            if (is_string($key) && ctype_digit($key)) {
                $normalizedKey = (int) $key;
            } elseif (!is_int($key)) {
                $allNumericKeys = false;
            }
            if (!is_int($normalizedKey)) {
                $allNumericKeys = false;
            }

            $normalizedByKey[$normalizedKey] = self::normalizeForCompare($item);
        }

        if ($allNumericKeys) {
            ksort($normalizedByKey, SORT_NUMERIC);
            if (array_keys($normalizedByKey) === range(0, count($normalizedByKey) - 1)) {
                return array_values($normalizedByKey);
            }
        }

        $normalized = [];
        $keys = array_keys($normalizedByKey);
        usort($keys, static function ($left, $right): int {
            return strnatcasecmp((string) $left, (string) $right);
        });
        foreach ($keys as $key) {
            $normalized[$key] = $normalizedByKey[$key];
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $value
     */
    private static function isAssoc(array $value): bool
    {
        if ([] === $value) {
            return false;
        }
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
