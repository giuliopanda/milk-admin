<?php
namespace Extensions\Projects\Classes\Module;

!defined('MILK_DIR') && die();

/**
 * Static helpers for working with model records.
 *
 * Provides field extraction, efficient counting (using SQL COUNT),
 * and row normalisation utilities.
 */
class ModelRecordHelper
{
    /**
     * Count all records for the model.
     *
     * Uses `total()` (COUNT(*)) and avoids loading row collections in memory.
     */
    public static function countAll(object $model): int
    {
        $modelClass = get_class($model);
        try {
            $lookup = new $modelClass();
            return $lookup->total();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Find first record id for the model.
     */
    public static function findFirstId(object $model): int
    {
        $pk = method_exists($model, 'getPrimaryKey') ? (string) $model->getPrimaryKey() : 'id';
        $modelClass = get_class($model);

        try {
            $lookup = new $modelClass();
            $row = $lookup->getRow();

            if (is_object($row)) {
                return _absint($row->$pk ?? 0);
            }
            if (is_array($row)) {
                return _absint($row[$pk] ?? 0);
            }
        } catch (\Throwable) {
            return 0;
        }

        return 0;
    }

    /**
     * Count records matching a FK value.
     *
     * Uses the model's `total()` method (which does COUNT(*) via SQL)
     * instead of loading all records into memory.
     */
    public static function countByFk(object $model, string $fkField, int $parentId): int
    {
        $fkField = trim($fkField);
        if ($fkField === '' || $parentId <= 0 || !self::isSafeSqlIdentifier($fkField)) {
            return 0;
        }

        $modelClass = get_class($model);
        try {
            $lookup = new $modelClass();
            // Use total() which does SELECT COUNT(*) — much more efficient
            return $lookup->where($fkField . ' = ?', [$parentId])->total();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Find the first record id matching a FK value.
     */
    public static function findFirstIdByFk(object $model, string $fkField, int $parentId): int
    {
        $fkField = trim($fkField);
        if ($fkField === '' || $parentId <= 0 || !self::isSafeSqlIdentifier($fkField)) {
            return 0;
        }

        $pk = method_exists($model, 'getPrimaryKey') ? (string) $model->getPrimaryKey() : 'id';
        $modelClass = get_class($model);

        try {
            $lookup = new $modelClass();
            $row = $lookup->where($fkField . ' = ?', [$parentId])->getRow();

            if (is_object($row)) {
                return _absint($row->$pk ?? 0);
            }
            if (is_array($row)) {
                return _absint($row[$pk] ?? 0);
            }
        } catch (\Throwable) {
            return 0;
        }

        return 0;
    }

    /**
     * Find first record matching a FK value.
     *
     * @return mixed|null
     */
    public static function findFirstByFk(object $model, string $fkField, int $parentId): mixed
    {
        $fkField = trim($fkField);
        if ($fkField === '' || $parentId <= 0 || !self::isSafeSqlIdentifier($fkField)) {
            return null;
        }

        $modelClass = get_class($model);
        try {
            $lookup = new $modelClass();
            return $lookup->where($fkField . ' = ?', [$parentId])->getRow();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Find all records matching a FK value.
     *
     * @return array<int,mixed>
     */
    public static function findAllByFk(object $model, string $fkField, int $parentId): array
    {
        $fkField = trim($fkField);
        if ($fkField === '' || $parentId <= 0 || !self::isSafeSqlIdentifier($fkField)) {
            return [];
        }

        $modelClass = get_class($model);
        try {
            $lookup = new $modelClass();
            $records = $lookup->where($fkField . ' = ?', [$parentId])->getResults();
            if (is_object($records) && method_exists($records, 'isEmpty') && $records->isEmpty()) {
                return [];
            }
            return self::extractRawRows($records);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Normalize model/list records into flat raw rows array.
     *
     * @return array<int,mixed>
     */
    public static function extractRawRows(mixed $records): array
    {
        if ($records === null) {
            return [];
        }

        if (is_object($records) && method_exists($records, 'getRawData')) {
            $raw = $records->getRawData('array', true);
            if (is_array($raw)) {
                return $raw;
            }
        }

        if (is_array($records)) {
            if (empty($records)) {
                return [];
            }
            $isAssoc = array_keys($records) !== range(0, count($records) - 1);
            if ($isAssoc) {
                return [$records];
            }
            return $records;
        }

        return [$records];
    }

    /**
     * Extract a single field value from a record (array or object).
     * Case-insensitive fallback for property/key names.
     */
    public static function extractFieldValue(mixed $record, string $field): mixed
    {
        if ($field === '') {
            return null;
        }

        if (is_array($record)) {
            if (array_key_exists($field, $record)) {
                return $record[$field];
            }
            foreach ($record as $k => $v) {
                if (is_string($k) && strcasecmp($k, $field) === 0) {
                    return $v;
                }
            }
            return null;
        }

        if (is_object($record)) {
            if (isset($record->$field) || property_exists($record, $field)) {
                return $record->$field;
            }
            foreach (get_object_vars($record) as $k => $v) {
                if (strcasecmp((string) $k, $field) === 0) {
                    return $v;
                }
            }
        }

        return null;
    }

    /**
     * Check if a string is a safe SQL identifier (alphanumeric + underscore).
     */
    public static function isSafeSqlIdentifier(string $value): bool
    {
        return $value !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $value) === 1;
    }
}
