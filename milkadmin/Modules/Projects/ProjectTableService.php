<?php

namespace Modules\Projects;

use App\Get;

!defined('MILK_DIR') && die();

class ProjectTableService
{
    /**
     * Instantiate a newly created Model and call buildTable() to create the DB table.
     *
     * @return array{
     *   success: bool,
     *   error: string,
     *   table_name: string,
     *   table_existence_known: bool,
     *   table_existed_before: bool,
     *   row_count_before: ?int
     * }
     */
    public static function buildDatabaseTable(string $modelFilePath, string $modelFqcn): array
    {
        $tableName = '';
        $tableExistenceKnown = false;
        $tableExistedBefore = false;
        $rowCountBefore = null;

        try {
            if (!class_exists($modelFqcn, false)) {
                require_once $modelFilePath;
            }

            if (!class_exists($modelFqcn)) {
                return [
                    'success' => false,
                    'error' => "Class {$modelFqcn} not found after require",
                    'table_name' => '',
                    'table_existence_known' => false,
                    'table_existed_before' => false,
                    'row_count_before' => null,
                ];
            }

            $model = new $modelFqcn();
            if (method_exists($model, 'getTable')) {
                $tableName = trim((string) $model->getTable());
            }

            if ($tableName !== '') {
                $existsBefore = self::tableExistsForModel($model);
                if ($existsBefore !== null) {
                    $tableExistenceKnown = true;
                    $tableExistedBefore = $existsBefore;
                }

                if ($tableExistedBefore) {
                    $rowCountBefore = self::countRowsForModel($model);
                }
            }

            if (!method_exists($model, 'buildTable')) {
                return [
                    'success' => false,
                    'error' => "buildTable() not available on {$modelFqcn}",
                    'table_name' => $tableName,
                    'table_existence_known' => $tableExistenceKnown,
                    'table_existed_before' => $tableExistedBefore,
                    'row_count_before' => $rowCountBefore,
                ];
            }

            // Projects must never push field defaults to DB schema changes.
            self::stripDefaultsFromModelRules($model);
            // In Projects, avoid risky ALTER TABLE transitions on existing columns.
            self::preserveSafeSchemaTransitionsFromExistingColumns($model);

            $result = $model->buildTable();
            if (!$result) {
                $error = method_exists($model, 'getLastError') ? $model->getLastError() : 'unknown error';
                return [
                    'success' => false,
                    'error' => (string) $error,
                    'table_name' => $tableName,
                    'table_existence_known' => $tableExistenceKnown,
                    'table_existed_before' => $tableExistedBefore,
                    'row_count_before' => $rowCountBefore,
                ];
            }

            return [
                'success' => true,
                'error' => '',
                'table_name' => $tableName,
                'table_existence_known' => $tableExistenceKnown,
                'table_existed_before' => $tableExistedBefore,
                'row_count_before' => $rowCountBefore,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'table_name' => $tableName,
                'table_existence_known' => $tableExistenceKnown,
                'table_existed_before' => $tableExistedBefore,
                'row_count_before' => $rowCountBefore,
            ];
        }
    }

    /**
     * Remove DB defaults from model rules while keeping runtime/form defaults untouched.
     */
    private static function stripDefaultsFromModelRules(object $model): void
    {
        if (!method_exists($model, 'getRules') || !method_exists($model, 'setRules')) {
            return;
        }

        $rules = $model->getRules();
        if (!is_array($rules) || empty($rules)) {
            return;
        }

        $changed = false;
        foreach ($rules as $fieldName => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (($rule['default'] ?? null) !== null) {
                $rule['default'] = null;
                $rules[$fieldName] = $rule;
                $changed = true;
            }
        }

        if ($changed) {
            $model->setRules($rules);
        }
    }

    /**
     * Inhibit risky ALTER TABLE transitions on existing columns.
     *
     * Rules:
     * - block NULL -> NOT NULL
     * - allow NOT NULL -> NULL
     * - block VARCHAR length reduction
     * - block DECIMAL precision/scale reduction
     * - block SIGNED -> UNSIGNED on existing integer columns
     * - block cross-family type conversion (string/int/decimal/date-time, etc.)
     */
    private static function preserveSafeSchemaTransitionsFromExistingColumns(object $model): void
    {
        if (!method_exists($model, 'getRules') || !method_exists($model, 'setRules')) {
            return;
        }

        $existingColumns = self::getExistingColumnSchemaMap($model);
        if (empty($existingColumns)) {
            return;
        }

        $rules = $model->getRules();
        if (!is_array($rules) || empty($rules)) {
            return;
        }

        $changed = false;
        foreach ($rules as $fieldName => $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (($rule['sql'] ?? true) === false) {
                continue;
            }

            $name = strtolower(trim((string) $fieldName));
            if ($name === '' || !isset($existingColumns[$name])) {
                continue;
            }

            $current = $existingColumns[$name];

            // 1) Keep NULL columns nullable (avoid NULL -> NOT NULL failures).
            if (array_key_exists('nullable', $rule)) {
                $targetNullable = self::normalizeBool($rule['nullable'], true);
                $currentNullable = (bool) ($current['nullable'] ?? true);
                if ($currentNullable && !$targetNullable) {
                    $rule['nullable'] = true;
                    $changed = true;
                }
            }

            $target = self::resolveRuleTargetSchema($rule);
            $targetFamily = (string) ($target['family'] ?? 'unknown');
            $currentFamily = (string) ($current['family'] ?? 'unknown');

            if ($targetFamily === 'unknown' || $currentFamily === 'unknown') {
                $rules[$fieldName] = $rule;
                continue;
            }

            // 2) Block cross-family conversion on existing columns.
            if ($targetFamily !== $currentFamily) {
                self::applyExistingColumnToRule($rule, $current);
                $rules[$fieldName] = $rule;
                $changed = true;
                continue;
            }

            // 3) Same-family safeguards.
            if ($currentFamily === 'string') {
                $currentLength = self::normalizePositiveInt($current['length'] ?? null, 0);
                $targetLength = self::normalizePositiveInt($target['length'] ?? null, 0);

                if ($currentLength > 0 && $targetLength > 0 && $targetLength < $currentLength) {
                    $rule['length'] = $currentLength;
                    $changed = true;
                }

                $currentBase = (string) ($current['base_type'] ?? '');
                $targetBase = (string) ($target['base_type'] ?? '');
                if (self::isTextBaseType($currentBase) && in_array($targetBase, ['varchar', 'char'], true)) {
                    // Avoid text -> varchar conversion that may truncate existing data.
                    $rule['type'] = 'text';
                    $rule['db_type'] = self::normalizeTextDbType($currentBase);
                    unset($rule['length']);
                    $changed = true;
                }
            } elseif ($currentFamily === 'decimal') {
                $currentPrecision = self::normalizePositiveInt($current['precision'] ?? null, 0);
                $currentScale = self::normalizePositiveInt($current['scale'] ?? null, 0);
                $targetPrecision = self::normalizePositiveInt($target['precision'] ?? null, 0);
                $targetScale = self::normalizePositiveInt($target['scale'] ?? null, 0);

                if ($currentPrecision > 0 && $targetPrecision > 0 && $targetPrecision < $currentPrecision) {
                    $rule['length'] = $currentPrecision;
                    $changed = true;
                }
                if ($targetScale < $currentScale) {
                    $rule['precision'] = $currentScale;
                    $changed = true;
                }
            } elseif ($currentFamily === 'int') {
                $currentUnsigned = (bool) ($current['unsigned'] ?? false);
                $targetUnsigned = self::normalizeBool($rule['unsigned'] ?? false, false);
                if (!$currentUnsigned && $targetUnsigned) {
                    $rule['unsigned'] = false;
                    $changed = true;
                }
            }

            $rules[$fieldName] = $rule;
        }

        if ($changed) {
            $model->setRules($rules);
        }
    }

    /**
     * @return array<string,array{
     *   nullable:bool,
     *   type_raw:string,
     *   base_type:string,
     *   family:string,
     *   length:?int,
     *   precision:?int,
     *   scale:?int,
     *   unsigned:bool
     * }>
     */
    private static function getExistingColumnSchemaMap(object $model): array
    {
        if (!method_exists($model, 'getTable') || !method_exists($model, 'getDb')) {
            return [];
        }

        $tableName = trim((string) $model->getTable());
        if ($tableName === '') {
            return [];
        }

        $db = $model->getDb();
        if (!is_object($db) || !method_exists($db, 'describes')) {
            return [];
        }

        try {
            $describe = $db->describes($tableName, false);
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($describe)) {
            return [];
        }

        $struct = is_array($describe['struct'] ?? null) ? $describe['struct'] : [];
        if (empty($struct)) {
            return [];
        }

        $result = [];
        foreach ($struct as $fallbackName => $column) {
            $row = is_object($column) ? get_object_vars($column) : (is_array($column) ? $column : []);
            if (empty($row)) {
                continue;
            }

            $name = trim((string) ($row['Field'] ?? $row['field'] ?? $row['name'] ?? $fallbackName));
            if ($name === '') {
                continue;
            }

            $nullable = true;
            if (array_key_exists('notnull', $row)) {
                $nullable = ((int) $row['notnull']) === 0;
            } else {
                $nullRaw = strtoupper(trim((string) ($row['Null'] ?? $row['null'] ?? 'YES')));
                $nullable = $nullRaw !== 'NO';
            }

            $typeRaw = trim((string) ($row['Type'] ?? $row['type'] ?? ''));
            $typeMeta = self::parseTypeMetadata($typeRaw);
            $result[strtolower($name)] = [
                'nullable' => $nullable,
                'type_raw' => $typeRaw,
                'base_type' => (string) ($typeMeta['base_type'] ?? ''),
                'family' => (string) ($typeMeta['family'] ?? 'unknown'),
                'length' => array_key_exists('length', $typeMeta) ? $typeMeta['length'] : null,
                'precision' => array_key_exists('precision', $typeMeta) ? $typeMeta['precision'] : null,
                'scale' => array_key_exists('scale', $typeMeta) ? $typeMeta['scale'] : null,
                'unsigned' => (bool) ($typeMeta['unsigned'] ?? false),
            ];
        }

        return $result;
    }

    /**
     * @return array{
     *   base_type:string,
     *   family:string,
     *   length:?int,
     *   precision:?int,
     *   scale:?int,
     *   unsigned:bool
     * }
     */
    private static function parseTypeMetadata(string $rawType): array
    {
        $normalized = strtolower(trim($rawType));
        $unsigned = str_contains($normalized, ' unsigned');
        $clean = preg_replace('/\s+unsigned\b/i', '', $normalized);
        $clean = is_string($clean) ? preg_replace('/\s+zerofill\b/i', '', $clean) : '';
        $clean = trim(is_string($clean) ? $clean : '');

        $baseType = '';
        $paramsRaw = '';
        if (preg_match('/^([a-z0-9_]+)\s*(?:\(([^)]*)\))?/i', $clean, $match)) {
            $baseType = strtolower(trim((string) ($match[1] ?? '')));
            $paramsRaw = trim((string) ($match[2] ?? ''));
        }

        $parts = [];
        if ($paramsRaw !== '') {
            $parts = array_map(
                static fn (string $part): string => trim($part),
                explode(',', $paramsRaw)
            );
        }

        $length = null;
        $precision = null;
        $scale = null;
        if (isset($parts[0]) && is_numeric($parts[0])) {
            $first = (int) $parts[0];
            $length = $first > 0 ? $first : null;
        }
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $second = (int) $parts[1];
            $scale = $second >= 0 ? $second : null;
        }

        if (in_array($baseType, ['decimal', 'numeric', 'float', 'double', 'real'], true)) {
            $precision = $length;
            if ($scale === null) {
                $scale = 0;
            }
        }

        return [
            'base_type' => $baseType,
            'family' => self::inferSqlFamilyFromBaseType($baseType),
            'length' => $length,
            'precision' => $precision,
            'scale' => $scale,
            'unsigned' => $unsigned,
        ];
    }

    /**
     * @param array<string,mixed> $rule
     * @return array{
     *   family:string,
     *   base_type:string,
     *   length:?int,
     *   precision:?int,
     *   scale:?int
     * }
     */
    private static function resolveRuleTargetSchema(array $rule): array
    {
        $type = strtolower(trim((string) ($rule['type'] ?? '')));
        $options = is_array($rule['options'] ?? null) ? $rule['options'] : [];

        switch ($type) {
            case 'string':
                return [
                    'family' => 'string',
                    'base_type' => 'varchar',
                    'length' => self::normalizePositiveInt($rule['length'] ?? null, 255),
                    'precision' => null,
                    'scale' => null,
                ];
            case 'text':
                $dbType = strtolower(trim((string) ($rule['db_type'] ?? 'text')));
                $dbType = self::normalizeTextDbType($dbType);
                return [
                    'family' => 'string',
                    'base_type' => $dbType,
                    'length' => null,
                    'precision' => null,
                    'scale' => null,
                ];
            case 'list':
                if (self::listWouldUseIntegerColumn($options)) {
                    return [
                        'family' => 'int',
                        'base_type' => 'int',
                        'length' => null,
                        'precision' => null,
                        'scale' => null,
                    ];
                }
                return [
                    'family' => 'string',
                    'base_type' => 'varchar',
                    'length' => max(
                        self::resolveMaxOptionKeyLength($options),
                        self::normalizePositiveInt($rule['length'] ?? null, 0)
                    ),
                    'precision' => null,
                    'scale' => null,
                ];
            case 'enum':
                if (self::enumWouldUseIntegerColumn($options)) {
                    return [
                        'family' => 'int',
                        'base_type' => 'int',
                        'length' => null,
                        'precision' => null,
                        'scale' => null,
                    ];
                }
                return [
                    'family' => 'string',
                    'base_type' => 'varchar',
                    'length' => max(
                        self::resolveMaxOptionValueLength($options),
                        self::normalizePositiveInt($rule['length'] ?? null, 0)
                    ),
                    'precision' => null,
                    'scale' => null,
                ];
            case 'radio':
                return [
                    'family' => 'string',
                    'base_type' => 'varchar',
                    'length' => self::normalizePositiveInt($rule['length'] ?? null, 255),
                    'precision' => null,
                    'scale' => null,
                ];
            case 'array':
                return [
                    'family' => 'string',
                    'base_type' => 'text',
                    'length' => null,
                    'precision' => null,
                    'scale' => null,
                ];
            case 'int':
                return [
                    'family' => 'int',
                    'base_type' => 'int',
                    'length' => null,
                    'precision' => null,
                    'scale' => null,
                ];
            case 'tinyint':
            case 'bool':
                return [
                    'family' => 'int',
                    'base_type' => 'tinyint',
                    'length' => null,
                    'precision' => null,
                    'scale' => null,
                ];
            case 'float':
                return [
                    'family' => 'decimal',
                    'base_type' => 'decimal',
                    'length' => null,
                    'precision' => self::normalizePositiveInt($rule['length'] ?? null, 10),
                    'scale' => self::normalizePositiveInt($rule['precision'] ?? null, 2),
                ];
            case 'date':
            case 'datetime':
            case 'timestamp':
            case 'time':
                return [
                    'family' => 'temporal',
                    'base_type' => $type,
                    'length' => null,
                    'precision' => null,
                    'scale' => null,
                ];
            default:
                return [
                    'family' => 'unknown',
                    'base_type' => $type,
                    'length' => null,
                    'precision' => null,
                    'scale' => null,
                ];
        }
    }

    /**
     * @param array<string,mixed> $rule
     * @param array<string,mixed> $current
     */
    private static function applyExistingColumnToRule(array &$rule, array $current): void
    {
        $family = (string) ($current['family'] ?? 'unknown');
        $baseType = strtolower(trim((string) ($current['base_type'] ?? '')));

        if ($family === 'string') {
            if (self::isTextBaseType($baseType)) {
                $rule['type'] = 'text';
                $rule['db_type'] = self::normalizeTextDbType($baseType);
                unset($rule['length']);
            } else {
                $rule['type'] = 'string';
                $rule['length'] = self::normalizePositiveInt($current['length'] ?? null, 255);
                unset($rule['db_type']);
            }
            return;
        }

        if ($family === 'int') {
            if ($baseType === 'tinyint' && self::normalizePositiveInt($current['length'] ?? null, 0) === 1) {
                $rule['type'] = 'bool';
            } else {
                $rule['type'] = 'int';
            }
            $rule['unsigned'] = (bool) ($current['unsigned'] ?? false);
            unset($rule['length'], $rule['precision'], $rule['db_type']);
            return;
        }

        if ($family === 'decimal') {
            $rule['type'] = 'float';
            $rule['length'] = self::normalizePositiveInt($current['precision'] ?? null, 10);
            $rule['precision'] = self::normalizePositiveInt($current['scale'] ?? null, 0);
            unset($rule['db_type']);
            return;
        }

        if ($family === 'temporal') {
            if (in_array($baseType, ['date', 'time', 'timestamp', 'datetime'], true)) {
                $rule['type'] = $baseType;
            } else {
                $rule['type'] = 'datetime';
            }
            unset($rule['length'], $rule['precision'], $rule['db_type']);
        }
    }

    private static function inferSqlFamilyFromBaseType(string $baseType): string
    {
        if ($baseType === '') {
            return 'unknown';
        }
        if (in_array($baseType, ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json', 'enum', 'set'], true)) {
            return 'string';
        }
        if (in_array($baseType, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'year', 'bit', 'boolean', 'bool'], true)) {
            return 'int';
        }
        if (in_array($baseType, ['decimal', 'numeric', 'float', 'double', 'real'], true)) {
            return 'decimal';
        }
        if (in_array($baseType, ['date', 'datetime', 'timestamp', 'time'], true)) {
            return 'temporal';
        }

        return 'unknown';
    }

    private static function isTextBaseType(string $baseType): bool
    {
        $baseType = strtolower(trim($baseType));
        return in_array($baseType, ['tinytext', 'text', 'mediumtext', 'longtext', 'json', 'tinyblob', 'blob', 'mediumblob', 'longblob'], true);
    }

    private static function normalizeTextDbType(string $baseType): string
    {
        $baseType = strtolower(trim($baseType));
        if (!in_array($baseType, ['tinytext', 'text', 'mediumtext', 'longtext'], true)) {
            return 'text';
        }

        return $baseType;
    }

    /**
     * Keep parity with SchemaAndValidationTrait list() DB mapping.
     */
    private static function listWouldUseIntegerColumn(array $options): bool
    {
        if (empty($options)) {
            return false;
        }

        $isInt = true;
        $sequence = true;
        $previous = null;
        foreach ($options as $key => $unused) {
            if ($sequence) {
                if (!is_int($key)) {
                    $sequence = false;
                } elseif ($previous !== null && ($previous + 1) !== $key) {
                    $sequence = false;
                }
                $previous = is_int($key) ? $key : $previous;
            }

            if (!is_int($key)) {
                $isInt = false;
            }
        }

        return $isInt && $sequence;
    }

    /**
     * Keep parity with SchemaAndValidationTrait enum() DB mapping.
     */
    private static function enumWouldUseIntegerColumn(array $options): bool
    {
        if (empty($options)) {
            return false;
        }

        foreach ($options as $value) {
            if (!is_int($value)) {
                return false;
            }
        }

        return true;
    }

    private static function resolveMaxOptionKeyLength(array $options): int
    {
        $max = 0;
        foreach ($options as $key => $unused) {
            $max = max($max, strlen((string) $key));
        }
        return $max > 0 ? $max : 255;
    }

    private static function resolveMaxOptionValueLength(array $options): int
    {
        $max = 0;
        foreach ($options as $value) {
            $max = max($max, strlen((string) $value));
        }
        return $max > 0 ? $max : 255;
    }

    private static function normalizeBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_float($value)) {
            return ((int) $value) !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return $default;
            }
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    private static function normalizePositiveInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : $default;
        }
        if (is_float($value)) {
            $value = (int) $value;
            return $value > 0 ? $value : $default;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && is_numeric($trimmed)) {
                $parsed = (int) $trimmed;
                return $parsed > 0 ? $parsed : $default;
            }
        }

        return $default;
    }

    /**
     * @param array<int,array{
     *   model_fqcn?: string,
     *   model_file_path?: string,
     *   table_name?: string,
     *   table_existence_known_before?: bool,
     *   table_existed_before?: bool
     * }> $tableCandidates
     * @return array{
     *   dropped_tables: string[],
     *   warnings: string[]
     * }
     */
    public static function rollbackTables(array $tableCandidates): array
    {
        $result = [
            'dropped_tables' => [],
            'warnings' => [],
        ];

        foreach ($tableCandidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $tableName = trim((string) ($candidate['table_name'] ?? ''));
            if ($tableName === '') {
                continue;
            }

            $knownBefore = (bool) ($candidate['table_existence_known_before'] ?? false);
            $existedBefore = (bool) ($candidate['table_existed_before'] ?? true);
            if (!$knownBefore) {
                $result['warnings'][] = "Skipped table rollback for {$tableName}: table state before build is unknown.";
                continue;
            }
            if ($existedBefore) {
                continue;
            }

            $modelFqcn = trim((string) ($candidate['model_fqcn'] ?? ''));
            $modelFilePath = trim((string) ($candidate['model_file_path'] ?? ''));
            if ($modelFqcn === '') {
                $result['warnings'][] = "Skipped table rollback for {$tableName}: missing model class.";
                continue;
            }

            try {
                if (!class_exists($modelFqcn, false) && $modelFilePath !== '' && is_file($modelFilePath)) {
                    require_once $modelFilePath;
                }
                if (!class_exists($modelFqcn)) {
                    $result['warnings'][] = "Skipped table rollback for {$tableName}: class {$modelFqcn} not found.";
                    continue;
                }

                $model = new $modelFqcn();
                $rowCount = self::countRowsForModel($model);
                if ($rowCount === null) {
                    $result['warnings'][] = "Skipped table rollback for {$tableName}: unable to verify row count.";
                    continue;
                }
                if ($rowCount > 0) {
                    $result['warnings'][] = "Skipped table rollback for {$tableName}: table contains data.";
                    continue;
                }

                if (!method_exists($model, 'dropTable')) {
                    $result['warnings'][] = "Skipped table rollback for {$tableName}: dropTable() not available.";
                    continue;
                }
                if (!$model->dropTable()) {
                    $error = method_exists($model, 'getLastError') ? (string) $model->getLastError() : 'unknown error';
                    $result['warnings'][] = "Failed to drop table {$tableName}: {$error}";
                    continue;
                }
                $result['dropped_tables'][] = $tableName;
            } catch (\Throwable $e) {
                $result['warnings'][] = "Failed to rollback table {$tableName}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * @return bool|null True if table exists, false if missing, null if unknown.
     */
    private static function tableExistsForModel(object $model): ?bool
    {
        if (!method_exists($model, 'getTable') || !method_exists($model, 'getDb')) {
            return null;
        }

        $tableName = trim((string) $model->getTable());
        if ($tableName === '') {
            return null;
        }

        $db = $model->getDb();
        if (!is_object($db) || !method_exists($db, 'getType')) {
            return null;
        }

        $dbType = (string) $db->getType();
        if ($dbType === 'mysql' || $dbType === 'sqlite') {
            $schema = Get::schema($tableName, $db);
            if (is_object($schema) && method_exists($schema, 'exists')) {
                return (bool) $schema->exists();
            }
            return null;
        }

        if ($dbType === 'array' && property_exists($db, 'tables_list') && is_array($db->tables_list)) {
            $prefix = property_exists($db, 'prefix') ? (string) $db->prefix : '';
            $resolvedTableName = str_replace('#__', $prefix . '_', $tableName);
            return in_array($resolvedTableName, $db->tables_list, true);
        }

        return null;
    }

    /**
     * @return int|null Number of rows in model table, null when unavailable.
     */
    private static function countRowsForModel(object $model): ?int
    {
        if (!method_exists($model, 'getTable') || !method_exists($model, 'getDb')) {
            return null;
        }

        $tableName = trim((string) $model->getTable());
        if ($tableName === '') {
            return null;
        }

        $db = $model->getDb();
        if (!is_object($db) || !method_exists($db, 'query') || !method_exists($db, 'qn')) {
            return null;
        }

        try {
            $query = 'SELECT COUNT(*) AS cnt FROM ' . $db->qn($tableName);
            $queryResult = $db->query($query);
            if (!is_object($queryResult) || !method_exists($queryResult, 'fetch_assoc')) {
                return null;
            }

            $row = $queryResult->fetch_assoc();
            if (!is_array($row)) {
                return null;
            }

            if (array_key_exists('cnt', $row)) {
                return (int) $row['cnt'];
            }

            $firstValue = reset($row);
            return $firstValue === false && empty($row) ? null : (int) $firstValue;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
