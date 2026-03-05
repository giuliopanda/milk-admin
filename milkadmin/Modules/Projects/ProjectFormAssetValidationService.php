<?php

namespace Modules\Projects;

use App\Config;
use App\Get;

!defined('MILK_DIR') && die();

class ProjectFormAssetValidationService
{
    public const MAX_FORM_TABLE_SEGMENT_LENGTH = 32;
    public const MAX_MODULE_TABLE_PREFIX_LENGTH = 20;
    public const MAX_TABLE_NAME_LENGTH = 64;

    public static function normalizeFormNameForCreation(string $rawValue): string
    {
        $value = self::toAscii($rawValue);
        $value = preg_replace('/[^A-Za-z0-9]+/', ' ', $value);
        $value = trim((string) preg_replace('/\s+/', ' ', (string) $value));
        if ($value === '') {
            return '';
        }

        $tokens = explode(' ', $value);
        $formName = '';
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            $formName .= ucfirst(strtolower($token));
        }

        if ($formName === '') {
            return '';
        }

        if (preg_match('/^[0-9]/', $formName) === 1) {
            $formName = 'Form' . $formName;
        }

        return $formName;
    }

    public static function isStrictFormName(string $formName): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9]*$/', trim($formName)) === 1;
    }

    public static function buildModelClassName(string $formName): string
    {
        return trim($formName) . 'Model';
    }

    public static function isStrictModelClassName(string $className): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9]*$/', trim($className)) === 1;
    }

    public static function isStrictTableName(string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '' || strlen($tableName) > self::MAX_TABLE_NAME_LENGTH) {
            return false;
        }

        return preg_match('/^[a-z0-9_]+$/', $tableName) === 1;
    }

    public static function buildTableName(string $moduleName, string $formName, string $dbType): string
    {
        $dbPrefix = self::toTableSegment(self::resolveDbPrefix($dbType), 32);
        $modulePrefix = self::toTableSegment($moduleName, self::MAX_MODULE_TABLE_PREFIX_LENGTH);
        $formSegment = self::toTableSegment($formName, self::MAX_FORM_TABLE_SEGMENT_LENGTH);

        if ($modulePrefix === '') {
            $modulePrefix = 'module';
        }
        if ($formSegment === '') {
            return '';
        }

        $tableName = self::joinTableSegments([$dbPrefix, $modulePrefix, $formSegment]);
        if (strlen($tableName) <= self::MAX_TABLE_NAME_LENGTH) {
            return $tableName;
        }

        $fixedPrefix = self::joinTableSegments([$dbPrefix, $modulePrefix]);
        $remaining = self::MAX_TABLE_NAME_LENGTH - strlen($fixedPrefix) - 1; // separator before form segment
        if ($remaining < 1) {
            return '';
        }
        $formSegment = substr($formSegment, 0, min(self::MAX_FORM_TABLE_SEGMENT_LENGTH, $remaining));
        $tableName = self::joinTableSegments([$dbPrefix, $modulePrefix, $formSegment]);

        if (strlen($tableName) <= self::MAX_TABLE_NAME_LENGTH) {
            return $tableName;
        }

        $fixedForModule = self::joinTableSegments([$dbPrefix, $formSegment]);
        $remainingForModule = self::MAX_TABLE_NAME_LENGTH - strlen($fixedForModule) - 1;
        if ($remainingForModule < 1) {
            return '';
        }
        $modulePrefix = substr($modulePrefix, 0, min(self::MAX_MODULE_TABLE_PREFIX_LENGTH, $remainingForModule));

        return self::joinTableSegments([$dbPrefix, $modulePrefix, $formSegment]);
    }

    /**
     * Validate names and detect collisions for a brand-new form asset.
     *
     * @param array<string,bool> $reservedClassNames
     * @param array<string,bool> $reservedTableNames
     * @return array{
     *   success:bool,
     *   error:string,
     *   form_name:string,
     *   model_class_name:string,
     *   model_fqcn:string,
     *   model_file_path:string,
     *   table_name:string
     * }
     */
    public static function validateNewFormAsset(
        string $formName,
        string $moduleName,
        string $dbType,
        string $modelDir,
        string $modelNamespace,
        array &$reservedClassNames,
        array &$reservedTableNames
    ): array {
        $cleanFormName = trim($formName);
        if (!self::isStrictFormName($cleanFormName)) {
            return self::validationError("Invalid form name '{$cleanFormName}'. Use only letters/numbers and start with a letter.");
        }

        $modelClassName = self::buildModelClassName($cleanFormName);
        if (!self::isStrictModelClassName($modelClassName)) {
            return self::validationError("Invalid model class name '{$modelClassName}'.");
        }

        $classKey = strtolower($modelClassName);
        if (isset($reservedClassNames[$classKey])) {
            return self::validationError("Model class '{$modelClassName}' is duplicated in the current request.");
        }

        $modelFilePath = rtrim($modelDir, '/\\') . '/' . $modelClassName . '.php';
        if (is_file($modelFilePath)) {
            return self::validationError("Model class '{$modelClassName}' already exists.");
        }

        $modelNamespace = trim($modelNamespace, '\\');
        $modelFqcn = $modelNamespace !== '' ? ($modelNamespace . '\\' . $modelClassName) : $modelClassName;
        if (class_exists($modelFqcn, false)) {
            return self::validationError("Model class '{$modelFqcn}' is already loaded.");
        }

        $tableName = self::buildTableName($moduleName, $cleanFormName, $dbType);
        if (!self::isStrictTableName($tableName)) {
            return self::validationError("Unable to generate a valid table name for form '{$cleanFormName}'.");
        }

        $tableKey = strtolower($tableName);
        if (isset($reservedTableNames[$tableKey])) {
            return self::validationError("Table '{$tableName}' is duplicated in the current request.");
        }

        $tableCheck = self::tableExists($tableName, $dbType);
        if (!$tableCheck['known']) {
            return self::validationError("Unable to verify table '{$tableName}' on connection '{$dbType}'.");
        }
        if ($tableCheck['exists']) {
            return self::validationError("Table '{$tableName}' already exists.");
        }

        $reservedClassNames[$classKey] = true;
        $reservedTableNames[$tableKey] = true;

        return [
            'success' => true,
            'error' => '',
            'form_name' => $cleanFormName,
            'model_class_name' => $modelClassName,
            'model_fqcn' => $modelFqcn,
            'model_file_path' => $modelFilePath,
            'table_name' => $tableName,
        ];
    }

    public static function tableExistsOnConnection(string $tableName, string $dbType): ?bool
    {
        $result = self::tableExists($tableName, $dbType);
        if (!$result['known']) {
            return null;
        }

        return (bool) $result['exists'];
    }

    /**
     * @return array{known:bool, exists:bool}
     */
    private static function tableExists(string $tableName, string $dbType): array
    {
        $db = self::resolveDbConnection($dbType);
        if (!is_object($db)) {
            return ['known' => false, 'exists' => false];
        }

        try {
            $schema = Get::schema($tableName, $db);
            if (is_object($schema) && method_exists($schema, 'exists')) {
                return ['known' => true, 'exists' => (bool) $schema->exists()];
            }
            // If schema adapter is not available, treat as "table not found".
            return ['known' => true, 'exists' => false];
        } catch (\Throwable $e) {
            // exists() failed -> report table missing as requested.
            return ['known' => true, 'exists' => false];
        }
    }

    private static function resolveDbPrefix(string $dbType): string
    {
        $connectionName = self::normalizeConnectionName($dbType);

        $suffix = '';
        if (preg_match('/^db([0-9]+)$/', $connectionName, $matches) === 1) {
            $suffix = (string) ($matches[1] ?? '');
        }

        $prefixKey = 'prefix' . $suffix;
        $configPrefix = trim((string) Config::get($prefixKey, ''));
        if ($configPrefix !== '') {
            return $configPrefix;
        }

        $db = self::resolveDbConnection($connectionName);
        if (is_object($db) && property_exists($db, 'prefix')) {
            return trim((string) $db->prefix);
        }

        return '';
    }

    private static function resolveDbConnection(string $dbType): object|null
    {
        $connectionName = self::normalizeConnectionName($dbType);

        try {
            if ($connectionName === 'db2') {
                return Get::db2();
            }
            if ($connectionName === 'db') {
                return Get::db();
            }
            if (method_exists(Get::class, 'dbConnection')) {
                return Get::dbConnection($connectionName);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private static function normalizeConnectionName(string $dbType): string
    {
        $normalized = strtolower(trim($dbType));
        return $normalized !== '' ? $normalized : 'db';
    }

    private static function toTableSegment(string $value, int $maxLength): string
    {
        $value = self::toAscii($value);
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value);
        $value = preg_replace('/[^A-Za-z0-9]+/', '_', (string) $value);
        $value = preg_replace('/_+/', '_', (string) $value);
        $value = strtolower(trim((string) $value, '_'));
        if ($value === '') {
            return '';
        }

        return substr($value, 0, max(1, $maxLength));
    }

    private static function joinTableSegments(array $segments): string
    {
        $clean = [];
        foreach ($segments as $segment) {
            $segment = trim((string) $segment, '_');
            if ($segment === '') {
                continue;
            }
            $clean[] = $segment;
        }

        return implode('_', $clean);
    }

    private static function validationError(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'form_name' => '',
            'model_class_name' => '',
            'model_fqcn' => '',
            'model_file_path' => '',
            'table_name' => '',
        ];
    }

    private static function toAscii(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        return $value;
    }
}
