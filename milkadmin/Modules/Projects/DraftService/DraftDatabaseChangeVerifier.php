<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

use App\Get;

class DraftDatabaseChangeVerifier
{
    private const MAX_LINES = 5;
    private const MAX_LINE_LENGTH = 120;
    private const MAX_DISTINCT_VALUES = 2000;

    /**
     * @param array<string,mixed>|null $project
     * @param array<string,mixed> $oldSchema
     * @param array<string,mixed> $newSchema
     * @param array<int,array<string,mixed>> $fieldChanges
     * @return array{
     *   message:string,
     *   rows:array<string,array{
     *     level:string,
     *     label:string,
     *     note:string,
     *     lines:array<int,string>
     *   }>
     * }
     */
    public static function analyze(
        ?array $project,
        string $refBase,
        array $oldSchema,
        array $newSchema,
        array $fieldChanges
    ): array {
        $result = [
            'message' => '',
            'rows' => [],
        ];

        if (!is_array($project)) {
            $result['message'] = 'DB verification unavailable: project not found.';
            return $result;
        }

        if (DraftModelResolver::isExistingTableLockedForRef($project, $refBase)) {
            $result['message'] = 'DB verification skipped: manifest marks this form as existingTable (table update will be skipped).';
            return $result;
        }

        $modelInfo = DraftModelResolver::resolveModelInfoForRef($project, $refBase);
        if (empty($modelInfo['success'])) {
            $result['message'] = 'DB verification unavailable: model not found for this form.';
            return $result;
        }

        $modelFilePath = trim((string) ($modelInfo['model_file_path'] ?? ''));
        $metadata = self::readModelMetadataFromFile($modelFilePath);
        $tableName = trim((string) ($metadata['table_name'] ?? ''));
        $dbType = trim((string) ($metadata['db_type'] ?? ''));
        if ($tableName === '') {
            $result['message'] = 'DB verification unavailable: model table() is not configured.';
            return $result;
        }

        $db = self::resolveDbConnection($dbType);
        if (!is_object($db) || !method_exists($db, 'query') || !method_exists($db, 'qn') || !method_exists($db, 'getVar')) {
            $result['message'] = 'DB verification unavailable: cannot open DB connection.';
            return $result;
        }

        if (!self::tableExists($db, $tableName)) {
            $result['message'] = "DB verification unavailable: table '{$tableName}' not found.";
            return $result;
        }

        $columnMap = self::loadColumnMap($db, $tableName);
        $oldFieldMap = self::extractFieldMap($oldSchema);
        $newFieldMap = self::extractFieldMap($newSchema);

        foreach ($fieldChanges as $row) {
            if (!is_array($row)) {
                continue;
            }

            $fieldName = trim((string) ($row['name'] ?? ''));
            if ($fieldName === '') {
                continue;
            }
            $fieldKey = strtolower($fieldName);
            $status = trim((string) ($row['status'] ?? 'unchanged'));

            $oldField = is_array($oldFieldMap[$fieldKey] ?? null) ? $oldFieldMap[$fieldKey] : null;
            $newField = is_array($newFieldMap[$fieldKey] ?? null) ? $newFieldMap[$fieldKey] : null;
            $changedKeys = is_array($row['changed_keys'] ?? null) ? array_values(array_filter($row['changed_keys'], 'is_string')) : [];

            $result['rows'][$fieldKey] = self::analyzeSingleField(
                $db,
                $tableName,
                $columnMap,
                $fieldName,
                $status,
                $oldField,
                $newField,
                $changedKeys
            );
        }

        return $result;
    }

    /**
     * @param array<string,string> $columnMap
     * @param array<string,mixed>|null $oldField
     * @param array<string,mixed>|null $newField
     * @param array<int,string> $changedKeys
     * @return array{level:string,label:string,note:string,lines:array<int,string>}
     */
    private static function analyzeSingleField(
        object $db,
        string $tableName,
        array $columnMap,
        string $fieldName,
        string $status,
        ?array $oldField,
        ?array $newField,
        array $changedKeys
    ): array {
        if (self::shouldSkipDbCheck($status, $oldField, $newField)) {
            return [
                'level' => 'safe',
                'label' => 'OK',
                'note' => 'DB check skipped for non-persistent field (custom HTML or excluded from DB).',
                'lines' => [],
            ];
        }

        $columnName = self::resolveColumnName($columnMap, $fieldName);

        if ($status === 'removed') {
            if ($columnName === '') {
                return self::resultRow('warning', 'Needs attention', 'Column not found in DB table.', ['Column not found.']);
            }

            $nonEmptyCount = self::countNonEmptyRows($db, $tableName, $columnName);
            if ($nonEmptyCount === null) {
                return self::resultRow('warning', 'Needs attention', 'Unable to count existing values for removed field.', ['Count query failed.']);
            }
            if ($nonEmptyCount > 0) {
                return self::resultRow(
                    'danger',
                    'Potentially dangerous',
                    'Removing this field can drop existing data.',
                    ['Non-empty rows: ' . $nonEmptyCount]
                );
            }

            return self::resultRow('safe', 'OK', 'No non-empty values found for this removed field.', ['Non-empty rows: 0']);
        }

        if ($status === 'added') {
            if ($columnName === '') {
                return self::resultRow('safe', 'OK', 'New field: no existing DB values to migrate.', ['Column not yet present in DB.']);
            }

            $nonEmptyCount = self::countNonEmptyRows($db, $tableName, $columnName);
            if ($nonEmptyCount !== null && $nonEmptyCount > 0) {
                return self::resultRow(
                    'warning',
                    'Needs attention',
                    'Column already contains data before this schema change.',
                    ['Existing non-empty rows: ' . $nonEmptyCount]
                );
            }

            return self::resultRow('safe', 'OK', 'New field: no existing DB risk detected.', ['No pre-existing data found.']);
        }

        if ($status !== 'modified') {
            return self::resultRow('safe', 'OK', 'No DB check required for unchanged field.', ['No changed lines.']);
        }

        if ($columnName === '') {
            return self::resultRow(
                'warning',
                'Needs attention',
                'Column not found in DB table, so checks are partial.',
                ['Column not found: cannot validate existing data.']
            );
        }

        $issues = [];
        $level = 'safe';

        // 1) Field becomes non-persistent
        $oldExcluded = self::toBool($oldField['excludeFromDatabase'] ?? false);
        $newExcluded = self::toBool($newField['excludeFromDatabase'] ?? false);
        if (!$oldExcluded && $newExcluded) {
            $count = self::countNonEmptyRows($db, $tableName, $columnName);
            if ($count !== null && $count > 0) {
                $level = self::maxLevel($level, 'danger');
                $issues[] = 'Field excluded from DB with existing values: ' . $count . ' rows.';
            } else {
                $level = self::maxLevel($level, 'warning');
                $issues[] = 'Field now excluded from DB.';
            }
        }

        // 2) Options reduced
        $optionsCheck = self::checkOptionValueCompatibility($db, $tableName, $columnName, $oldField, $newField);
        if ($optionsCheck['enabled']) {
            if (($optionsCheck['affected_rows'] ?? 0) > 0) {
                $level = self::maxLevel($level, 'danger');
                $issues[] = 'Rows with removed option values: ' . (int) $optionsCheck['affected_rows'] . '.';
            } elseif (!empty($optionsCheck['note'])) {
                $level = self::maxLevel($level, 'safe');
                $issues[] = (string) $optionsCheck['note'];
            }
        }

        // 3) Type conversion to numeric
        $newMethod = strtolower(trim((string) ($newField['method'] ?? '')));
        if (in_array($newMethod, ['int', 'decimal', 'float', 'double'], true)) {
            $numericCheck = self::checkNumericCompatibility($db, $tableName, $columnName, $newMethod);
            if ($numericCheck['checked']) {
                $invalidRows = (int) ($numericCheck['invalid_rows'] ?? 0);
                if ($invalidRows > 0) {
                    $level = self::maxLevel($level, 'danger');
                    $issues[] = 'Non-numeric values found for target numeric type: ' . $invalidRows . '.';
                } elseif (!empty($numericCheck['note'])) {
                    $issues[] = (string) $numericCheck['note'];
                }
            }
        }

        // 4) Length shrinking
        $oldLength = self::toIntOrNull($oldField['length'] ?? null);
        $newLength = self::toIntOrNull($newField['length'] ?? null);
        if ($oldLength !== null && $newLength !== null && $newLength > 0 && $newLength < $oldLength) {
            $lengthCheck = self::checkLengthCompatibility($db, $tableName, $columnName, $newLength);
            if ($lengthCheck['checked']) {
                $tooLongRows = (int) ($lengthCheck['too_long_rows'] ?? 0);
                if ($tooLongRows > 0) {
                    $level = self::maxLevel($level, 'danger');
                    $issues[] = 'Values longer than new length (' . $newLength . '): ' . $tooLongRows . ' rows.';
                } elseif (!empty($lengthCheck['note'])) {
                    $issues[] = (string) $lengthCheck['note'];
                }
            }
        }

        // 5) Required changed to true
        $oldRequired = self::toBool($oldField['required'] ?? false);
        $newRequired = self::toBool($newField['required'] ?? false);
        if (!$oldRequired && $newRequired) {
            $emptyRows = self::countEmptyRows($db, $tableName, $columnName);
            if ($emptyRows !== null && $emptyRows > 0) {
                $level = self::maxLevel($level, 'warning');
                $issues[] = 'Field became required, but empty rows exist: ' . $emptyRows . '.';
            }
        }

        if (empty($issues)) {
            return self::resultRow('safe', 'OK', 'No risky DB values detected for this change.', ['No critical DB mismatch found.']);
        }

        $label = $level === 'danger' ? 'Potentially dangerous' : ($level === 'warning' ? 'Needs attention' : 'OK');
        $note = implode(' ', $issues);
        return self::resultRow($level, $label, $note, $issues);
    }

    private static function shouldSkipDbCheck(string $status, ?array $oldField, ?array $newField): bool
    {
        if ($status === 'removed') {
            return self::isNonPersistentField($oldField);
        }
        if ($status === 'added') {
            return self::isNonPersistentField($newField);
        }
        if ($status === 'modified') {
            return self::isNonPersistentField($oldField) || self::isNonPersistentField($newField);
        }

        return false;
    }

    private static function isNonPersistentField(?array $field): bool
    {
        if (!is_array($field)) {
            return false;
        }

        if (self::toBool($field['excludeFromDatabase'] ?? false)) {
            return true;
        }

        $method = strtolower(trim((string) ($field['method'] ?? '')));
        if ($method === 'html') {
            return true;
        }

        $formType = strtolower(trim((string) ($field['formType'] ?? '')));
        return $formType === 'html';
    }

    /**
     * @param array<string,mixed>|null $oldField
     * @param array<string,mixed>|null $newField
     * @return array{enabled:bool,affected_rows:int,note:string}
     */
    private static function checkOptionValueCompatibility(
        object $db,
        string $tableName,
        string $columnName,
        ?array $oldField,
        ?array $newField
    ): array {
        if (!is_array($oldField) || !is_array($newField)) {
            return ['enabled' => false, 'affected_rows' => 0, 'note' => ''];
        }

        $oldMethod = strtolower(trim((string) ($oldField['method'] ?? '')));
        $newMethod = strtolower(trim((string) ($newField['method'] ?? '')));
        $optionMethods = ['select', 'radio', 'checkboxes'];
        if (!in_array($oldMethod, $optionMethods, true) && !in_array($newMethod, $optionMethods, true)) {
            return ['enabled' => false, 'affected_rows' => 0, 'note' => ''];
        }

        $oldOptions = self::extractOptionValues($oldField);
        $newOptions = self::extractOptionValues($newField);
        if (empty($oldOptions) || empty($newOptions)) {
            return ['enabled' => false, 'affected_rows' => 0, 'note' => ''];
        }

        $removed = array_values(array_diff($oldOptions, $newOptions));
        if (empty($removed)) {
            return ['enabled' => true, 'affected_rows' => 0, 'note' => 'Option set changed, but no value was removed.'];
        }

        $distribution = self::fetchValueDistribution($db, $tableName, $columnName);
        if (!$distribution['ok']) {
            return ['enabled' => true, 'affected_rows' => 0, 'note' => 'Unable to inspect existing option values.'];
        }

        $removedMap = [];
        foreach ($removed as $value) {
            $removedMap[(string) $value] = true;
        }

        $methodForStoredValue = $newMethod !== '' ? $newMethod : $oldMethod;
        $affectedRows = 0;
        foreach ($distribution['values'] as $storedValue => $count) {
            $tokens = self::tokensFromStoredValue($storedValue, $methodForStoredValue);
            foreach ($tokens as $token) {
                if (isset($removedMap[$token])) {
                    $affectedRows += (int) $count;
                    break;
                }
            }
        }

        return [
            'enabled' => true,
            'affected_rows' => $affectedRows,
            'note' => $affectedRows === 0 ? 'No rows use removed option values.' : '',
        ];
    }

    /**
     * @return array{checked:bool,invalid_rows:int,note:string}
     */
    private static function checkNumericCompatibility(object $db, string $tableName, string $columnName, string $newMethod): array
    {
        $distribution = self::fetchValueDistribution($db, $tableName, $columnName);
        if (!$distribution['ok']) {
            return ['checked' => false, 'invalid_rows' => 0, 'note' => 'Cannot inspect numeric compatibility.'];
        }

        $invalidRows = 0;
        foreach ($distribution['values'] as $value => $count) {
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            $isValid = $newMethod === 'int'
                ? preg_match('/^-?[0-9]+$/', $text) === 1
                : preg_match('/^-?[0-9]+([.,][0-9]+)?$/', $text) === 1;

            if (!$isValid) {
                $invalidRows += (int) $count;
            }
        }

        return [
            'checked' => true,
            'invalid_rows' => $invalidRows,
            'note' => $invalidRows === 0 ? 'All existing non-empty values are numeric-compatible.' : '',
        ];
    }

    /**
     * @return array{checked:bool,too_long_rows:int,note:string}
     */
    private static function checkLengthCompatibility(object $db, string $tableName, string $columnName, int $maxLength): array
    {
        $distribution = self::fetchValueDistribution($db, $tableName, $columnName);
        if (!$distribution['ok']) {
            return ['checked' => false, 'too_long_rows' => 0, 'note' => 'Cannot inspect length compatibility.'];
        }

        $tooLongRows = 0;
        foreach ($distribution['values'] as $value => $count) {
            $text = (string) $value;
            if (mb_strlen($text) > $maxLength) {
                $tooLongRows += (int) $count;
            }
        }

        return [
            'checked' => true,
            'too_long_rows' => $tooLongRows,
            'note' => $tooLongRows === 0 ? 'No values exceed new length limit.' : '',
        ];
    }

    /**
     * @return array{ok:bool,values:array<string,int>}
     */
    private static function fetchValueDistribution(object $db, string $tableName, string $columnName): array
    {
        try {
            $tableExpr = $db->qn($tableName);
            $columnExpr = $db->qn($columnName);
            $sql = 'SELECT ' . $columnExpr . ' AS value, COUNT(*) AS cnt '
                . 'FROM ' . $tableExpr . ' '
                . 'WHERE ' . $columnExpr . " IS NOT NULL AND " . $columnExpr . " <> '' "
                . 'GROUP BY ' . $columnExpr . ' '
                . 'LIMIT ' . self::MAX_DISTINCT_VALUES;

            $queryResult = $db->query($sql);
            if (!is_object($queryResult) || !method_exists($queryResult, 'fetch_assoc')) {
                return ['ok' => false, 'values' => []];
            }

            $values = [];
            while ($row = $queryResult->fetch_assoc()) {
                if (!is_array($row)) {
                    continue;
                }
                $value = array_key_exists('value', $row) ? (string) ($row['value'] ?? '') : (string) (reset($row) ?: '');
                $cnt = array_key_exists('cnt', $row) ? (int) ($row['cnt'] ?? 0) : (int) (end($row) ?: 0);
                if ($cnt <= 0) {
                    continue;
                }
                $values[$value] = ($values[$value] ?? 0) + $cnt;
            }

            return ['ok' => true, 'values' => $values];
        } catch (\Throwable $e) {
            return ['ok' => false, 'values' => []];
        }
    }

    private static function countNonEmptyRows(object $db, string $tableName, string $columnName): ?int
    {
        try {
            $tableExpr = $db->qn($tableName);
            $columnExpr = $db->qn($columnName);
            $sql = 'SELECT COUNT(*) AS cnt FROM ' . $tableExpr
                . ' WHERE ' . $columnExpr . " IS NOT NULL AND " . $columnExpr . " <> ''";
            $raw = $db->getVar($sql);
            return is_numeric($raw) ? (int) $raw : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function countEmptyRows(object $db, string $tableName, string $columnName): ?int
    {
        try {
            $tableExpr = $db->qn($tableName);
            $columnExpr = $db->qn($columnName);
            $sql = 'SELECT COUNT(*) AS cnt FROM ' . $tableExpr
                . ' WHERE ' . $columnExpr . " IS NULL OR " . $columnExpr . " = ''";
            $raw = $db->getVar($sql);
            return is_numeric($raw) ? (int) $raw : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,string> $columnMap
     */
    private static function resolveColumnName(array $columnMap, string $fieldName): string
    {
        $key = strtolower(trim($fieldName));
        return trim((string) ($columnMap[$key] ?? ''));
    }

    /**
     * @return array<string,string> lowercase => actual column name
     */
    private static function loadColumnMap(object $db, string $tableName): array
    {
        if (!method_exists($db, 'describes')) {
            return [];
        }

        try {
            $describe = $db->describes($tableName, false);
            $struct = is_array($describe['struct'] ?? null) ? $describe['struct'] : [];
            $map = [];
            foreach ($struct as $columnName => $_meta) {
                $name = trim((string) $columnName);
                if ($name === '') {
                    continue;
                }
                $map[strtolower($name)] = $name;
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function tableExists(object $db, string $tableName): bool
    {
        try {
            $probe = $db->query('SELECT 1 FROM ' . $db->qn($tableName) . ' LIMIT 1');
            return is_object($probe);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $schema
     * @return array<string,array<string,mixed>>
     */
    private static function extractFieldMap(array $schema): array
    {
        $model = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $fields = is_array($model['fields'] ?? null) ? $model['fields'] : [];
        $map = [];
        foreach ($fields as $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }
            $name = trim((string) ($fieldDef['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $map[strtolower($name)] = $fieldDef;
        }
        return $map;
    }

    /**
     * @return array<int,string>
     */
    private static function extractOptionValues(array $fieldDef): array
    {
        $options = $fieldDef['options'] ?? null;
        if (!is_array($options)) {
            return [];
        }

        $result = [];
        $isAssoc = self::isAssoc($options);
        if ($isAssoc) {
            foreach (array_keys($options) as $value) {
                $key = trim((string) $value);
                if ($key !== '') {
                    $result[] = $key;
                }
            }
        } else {
            foreach ($options as $item) {
                if (is_array($item) && array_key_exists('value', $item)) {
                    $value = trim((string) ($item['value'] ?? ''));
                    if ($value !== '') {
                        $result[] = $value;
                    }
                } else {
                    $value = trim((string) $item);
                    if ($value !== '') {
                        $result[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @return array<int,string>
     */
    private static function tokensFromStoredValue(string $storedValue, string $method): array
    {
        $value = trim($storedValue);
        if ($value === '') {
            return [];
        }

        if ($method === 'checkboxes') {
            if ($value !== '' && $value[0] === '[') {
                try {
                    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $tokens = [];
                        foreach ($decoded as $item) {
                            $token = trim((string) $item);
                            if ($token !== '') {
                                $tokens[] = $token;
                            }
                        }
                        return array_values(array_unique($tokens));
                    }
                } catch (\Throwable $e) {
                    // fallthrough to delimited parsing
                }
            }

            $parts = preg_split('/[;,|]+/', $value) ?: [];
            $tokens = [];
            foreach ($parts as $part) {
                $token = trim((string) $part);
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
            return array_values(array_unique($tokens));
        }

        return [$value];
    }

    /**
     * @param array<int,string> $lines
     * @return array{level:string,label:string,note:string,lines:array<int,string>}
     */
    private static function resultRow(string $level, string $label, string $note, array $lines): array
    {
        return [
            'level' => $level,
            'label' => $label,
            'note' => $note,
            'lines' => self::compactLines($lines),
        ];
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

        if (count($normalized) > self::MAX_LINES) {
            $visibleCount = max(1, self::MAX_LINES - 1);
            $hidden = count($normalized) - $visibleCount;
            $normalized = array_slice($normalized, 0, $visibleCount);
            $normalized[] = '... (+' . $hidden . ' more)';
        }

        return $normalized;
    }

    private static function maxLevel(string $left, string $right): string
    {
        $score = ['safe' => 0, 'warning' => 1, 'danger' => 2, 'unknown' => 3];
        $leftScore = $score[$left] ?? 0;
        $rightScore = $score[$right] ?? 0;
        return $leftScore >= $rightScore ? $left : $right;
    }

    /**
     * @return array{table_name:string,db_type:string}
     */
    private static function readModelMetadataFromFile(string $modelPath): array
    {
        $content = @file_get_contents($modelPath);
        if (!is_string($content) || $content === '') {
            return ['table_name' => '', 'db_type' => ''];
        }

        $tableName = '';
        if (preg_match('/->table\\(\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $content, $matches) === 1) {
            $tableName = trim((string) ($matches[1] ?? ''));
        }

        $dbType = '';
        if (preg_match('/->db\\(\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $content, $matches) === 1) {
            $dbType = trim((string) ($matches[1] ?? ''));
        }

        return ['table_name' => $tableName, 'db_type' => $dbType !== '' ? $dbType : 'db'];
    }

    private static function resolveDbConnection(string $dbType): object|null
    {
        $name = strtolower(trim($dbType));
        if ($name === '') {
            $name = 'db';
        }

        try {
            if ($name === 'db2') {
                return Get::db2();
            }
            if ($name === 'db') {
                return Get::db();
            }
            if (method_exists(Get::class, 'dbConnection')) {
                return Get::dbConnection($name);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * @param array<mixed> $value
     */
    private static function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }
        return array_keys($value) !== range(0, count($value) - 1);
    }

    private static function toBool(mixed $value): bool
    {
        return DraftFieldUtils::normalizeBool($value);
    }

    private static function toIntOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return null;
    }
}
