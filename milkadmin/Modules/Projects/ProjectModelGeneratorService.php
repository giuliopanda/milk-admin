<?php

namespace Modules\Projects;

use App\Get;

!defined('MILK_DIR') && die();

class ProjectModelGeneratorService
{
    /**
     * @return array{
     *   success:bool,
     *   content:string,
     *   primary_key:string,
     *   field_count:int,
     *   error:string
     * }
     */
    public static function generateFromExistingTable(
        string $moduleName,
        string $rootFormName,
        string $tableName,
        string $dbName = 'db2'
    ): array {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return self::errorResult('Missing table name.');
        }

        $db = self::resolveDatabaseConnection($dbName);
        if (!is_object($db) || !method_exists($db, 'describes')) {
            return self::errorResult("Database connection '{$dbName}' does not support table introspection.");
        }

        $describedTableName = $tableName;
        [$describe, $describeError] = self::tryDescribe($db, $tableName);

        if (!is_array($describe) || empty($describe)) {
            $prefixedCandidate = self::withPhysicalPrefix($tableName, $dbName);
            if ($prefixedCandidate !== '' && strcasecmp($prefixedCandidate, $tableName) !== 0) {
                [$prefixedDescribe] = self::tryDescribe($db, $prefixedCandidate);
                if (is_array($prefixedDescribe) && !empty($prefixedDescribe)) {
                    $describe = $prefixedDescribe;
                    $describedTableName = $prefixedCandidate;
                }
            }
        }

        if (!is_array($describe) || empty($describe)) {
            $message = "Unable to read table structure for '{$tableName}'.";
            if ($describeError !== '') {
                $message .= ' ' . $describeError;
            }
            return self::errorResult($message);
        }

        return self::buildFromDescribe($moduleName, $rootFormName, $describedTableName, $dbName, $describe);
    }

    /**
     * @param array<string,mixed> $describe
     * @return array{
     *   success:bool,
     *   content:string,
     *   primary_key:string,
     *   field_count:int,
     *   error:string
     * }
     */
    public static function buildFromDescribe(
        string $moduleName,
        string $rootFormName,
        string $tableName,
        string $dbName,
        array $describe
    ): array {
        $columns = self::normalizeColumns($describe);
        if (empty($columns)) {
            return self::errorResult("Table '{$tableName}' has no readable columns.");
        }

        $primaryKey = self::resolvePrimaryKey($describe, $columns);
        if ($primaryKey === '') {
            $primaryKey = (string) ($columns[0]['name'] ?? 'id');
        }

        $fieldLines = [];
        foreach ($columns as $column) {
            $fieldName = (string) ($column['name'] ?? '');
            if ($fieldName === '' || strcasecmp($fieldName, $primaryKey) === 0) {
                continue;
            }
            $fieldLines[] = self::buildFieldRuleChain($column);
        }

        $content = self::renderModelPhp(
            $moduleName,
            $rootFormName,
            $tableName,
            $dbName,
            $primaryKey,
            $fieldLines
        );

        return [
            'success' => true,
            'content' => $content,
            'primary_key' => $primaryKey,
            'field_count' => count($fieldLines),
            'error' => '',
        ];
    }

    private static function resolveDatabaseConnection(string $dbName): mixed
    {
        $dbName = trim($dbName);
        if ($dbName === '' || strcasecmp($dbName, 'db2') === 0) {
            return Get::db2();
        }

        if (method_exists(Get::class, 'dbConnection')) {
            try {
                return Get::dbConnection($dbName);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $describe
     * @return array<int,array{
     *   name:string,
     *   type:string,
     *   nullable:bool,
     *   default:mixed,
     *   key:string,
     *   extra:string
     * }>
     */
    private static function normalizeColumns(array $describe): array
    {
        $struct = is_array($describe['struct'] ?? null) ? $describe['struct'] : [];
        if (empty($struct)) {
            $fields = is_array($describe['fields'] ?? null) ? $describe['fields'] : [];
            foreach ($fields as $fieldName => $fieldType) {
                $struct[] = (object) [
                    'Field' => (string) $fieldName,
                    'Type' => (string) $fieldType,
                    'Null' => 'YES',
                    'Key' => '',
                    'Default' => null,
                    'Extra' => '',
                ];
            }
        }

        $result = [];
        foreach ($struct as $item) {
            $row = is_object($item) ? get_object_vars($item) : (is_array($item) ? $item : []);
            if (empty($row)) {
                continue;
            }

            $name = trim((string) ($row['Field'] ?? $row['field'] ?? $row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = trim((string) ($row['Type'] ?? $row['type'] ?? 'string'));
            $nullRaw = strtoupper(trim((string) ($row['Null'] ?? $row['null'] ?? 'YES')));
            $nullable = $nullRaw !== 'NO';
            if (array_key_exists('notnull', $row)) {
                $nullable = ((int) $row['notnull']) === 0;
            }

            $key = strtoupper(trim((string) ($row['Key'] ?? $row['key'] ?? '')));
            if ($key === '' && array_key_exists('pk', $row) && (int) $row['pk'] > 0) {
                $key = 'PRI';
            }

            $result[] = [
                'name' => $name,
                'type' => $type !== '' ? $type : 'string',
                'nullable' => $nullable,
                'default' => $row['Default'] ?? $row['default'] ?? $row['dflt_value'] ?? null,
                'key' => $key,
                'extra' => strtolower(trim((string) ($row['Extra'] ?? $row['extra'] ?? ''))),
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $describe
     * @param array<int,array{name:string,key:string,extra:string}> $columns
     */
    private static function resolvePrimaryKey(array $describe, array $columns): string
    {
        $keys = is_array($describe['keys'] ?? null) ? $describe['keys'] : [];
        foreach ($keys as $key) {
            $name = trim((string) $key);
            if ($name !== '') {
                return $name;
            }
        }

        foreach ($columns as $column) {
            if (strtoupper((string) ($column['key'] ?? '')) === 'PRI') {
                return (string) ($column['name'] ?? '');
            }
        }

        foreach ($columns as $column) {
            if (str_contains((string) ($column['extra'] ?? ''), 'auto_increment')) {
                return (string) ($column['name'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param array{name:string,type:string,nullable:bool,default:mixed,key:string,extra:string} $column
     */
    private static function buildFieldRuleChain(array $column): string
    {
        $fieldName = (string) $column['name'];
        $map = self::mapColumnTypeToRule($fieldName, (string) $column['type']);

        $lines = [
            '            ' . $map['line'],
        ];

        if ($column['nullable'] === false) {
            $lines[] = '                ->nullable(false)';
        }

        $defaultLiteral = self::buildDefaultLiteral($column['default'], (string) $map['kind']);
        if ($defaultLiteral !== null) {
            $lines[] = '                ->default(' . $defaultLiteral . ')';
        }

        if (self::isAuditSystemField($fieldName)) {
            $lines[] = '                ->hideFromEdit()';
        }

        return implode("\n", $lines);
    }

    private static function isAuditSystemField(string $fieldName): bool
    {
        $fieldName = strtolower(trim($fieldName));
        return in_array($fieldName, ['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at', 'deleted_by'], true);
    }

    /**
     * @return array{line:string,kind:string}
     */
    private static function mapColumnTypeToRule(string $fieldName, string $rawType): array
    {
        $type = self::parseType($rawType);
        $base = $type['base'];
        $params = $type['params'];
        $nameLiteral = self::toPhpStringLiteral($fieldName);

        switch ($base) {
            case 'tinyint':
                if (isset($params[0]) && (int) $params[0] === 1) {
                    return ['line' => "->boolean({$nameLiteral})", 'kind' => 'boolean'];
                }
                return ['line' => "->int({$nameLiteral})", 'kind' => 'int'];

            case 'int':
            case 'integer':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
            case 'year':
                return ['line' => "->int({$nameLiteral})", 'kind' => 'int'];

            case 'bool':
            case 'boolean':
            case 'bit':
                return ['line' => "->boolean({$nameLiteral})", 'kind' => 'boolean'];

            case 'decimal':
            case 'numeric':
                $length = self::parsePositiveInt($params[0] ?? null, 10);
                $precision = array_key_exists(1, $params)
                    ? self::parsePositiveInt($params[1], 0)
                    : 0;
                if ($length < $precision) {
                    $length = $precision + 1;
                }
                return ['line' => "->decimal({$nameLiteral}, {$length}, {$precision})", 'kind' => 'decimal'];

            case 'float':
            case 'double':
            case 'real':
                if (isset($params[0], $params[1])) {
                    $length = self::parsePositiveInt($params[0], 10);
                    $precision = self::parsePositiveInt($params[1], 2);
                } else {
                    $length = 10;
                    $precision = 2;
                }
                if ($length < $precision) {
                    $length = $precision + 1;
                }
                return ['line' => "->decimal({$nameLiteral}, {$length}, {$precision})", 'kind' => 'decimal'];

            case 'char':
            case 'varchar':
            case 'nvarchar':
            case 'nchar':
                $length = self::parsePositiveInt($params[0] ?? null, $base === 'char' ? 1 : 255);
                return ['line' => "->string({$nameLiteral}, {$length})", 'kind' => 'string'];

            case 'enum':
                $options = self::parseQuotedList($type['params_raw']);
                if (empty($options)) {
                    return ['line' => "->string({$nameLiteral}, 255)", 'kind' => 'string'];
                }
                return [
                    'line' => "->enum({$nameLiteral}, " . self::toPhpArrayLiteral($options) . ')',
                    'kind' => 'enum',
                ];

            case 'set':
                $options = self::parseQuotedList($type['params_raw']);
                if (empty($options)) {
                    return ['line' => "->string({$nameLiteral}, 255)", 'kind' => 'string'];
                }
                return [
                    'line' => "->list({$nameLiteral}, " . self::toPhpArrayLiteral($options) . ')',
                    'kind' => 'list',
                ];

            case 'json':
                return ['line' => "->array({$nameLiteral})", 'kind' => 'array'];

            case 'date':
                return ['line' => "->date({$nameLiteral})", 'kind' => 'date'];

            case 'datetime':
                return ['line' => "->datetime({$nameLiteral})", 'kind' => 'datetime'];

            case 'timestamp':
                return ['line' => "->timestamp({$nameLiteral})", 'kind' => 'timestamp'];

            case 'time':
                return ['line' => "->time({$nameLiteral})", 'kind' => 'time'];

            case 'tinytext':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            case 'blob':
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
            case 'binary':
            case 'varbinary':
                return ['line' => "->text({$nameLiteral})", 'kind' => 'text'];

            default:
                return ['line' => "->string({$nameLiteral}, 255)", 'kind' => 'string'];
        }
    }

    /**
     * @return array{base:string,params:array<int,string>,params_raw:string}
     */
    private static function parseType(string $rawType): array
    {
        $value = strtolower(trim($rawType));
        if ($value === '') {
            return ['base' => 'string', 'params' => [], 'params_raw' => ''];
        }

        if (str_starts_with($value, 'character varying')) {
            $value = 'varchar' . substr($value, strlen('character varying'));
        } elseif (str_starts_with($value, 'double precision')) {
            $value = 'double' . substr($value, strlen('double precision'));
        }

        $base = $value;
        $paramsRaw = '';
        $params = [];

        if (preg_match('/^([a-z0-9_]+)\s*(?:\((.*)\))?/i', $value, $matches) === 1) {
            $base = strtolower((string) ($matches[1] ?? 'string'));
            $paramsRaw = trim((string) ($matches[2] ?? ''));
            if ($paramsRaw !== '') {
                if (in_array($base, ['enum', 'set'], true)) {
                    $params = self::parseQuotedList($paramsRaw);
                } else {
                    $params = array_values(array_filter(array_map(
                        static fn($part): string => trim((string) $part),
                        explode(',', $paramsRaw)
                    ), static fn(string $part): bool => $part !== ''));
                }
            }
        }

        return [
            'base' => $base,
            'params' => $params,
            'params_raw' => $paramsRaw,
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function parseQuotedList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $items = str_getcsv($value, ',', "'", "\\");
        $result = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            $result[] = str_replace("\\'", "'", $item);
        }

        return $result;
    }

    private static function parsePositiveInt(mixed $value, int $fallback): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : $fallback;
        }

        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            $parsed = (int) $value;
            return $parsed > 0 ? $parsed : $fallback;
        }

        if (is_float($value)) {
            $parsed = (int) $value;
            return $parsed > 0 ? $parsed : $fallback;
        }

        return $fallback;
    }

    private static function buildDefaultLiteral(mixed $defaultValue, string $kind): ?string
    {
        if ($defaultValue === null) {
            return null;
        }

        if (is_string($defaultValue)) {
            $trimmed = trim($defaultValue);
            if (strcasecmp($trimmed, 'null') === 0) {
                return null;
            }
            if (preg_match('/^current_timestamp(?:\(\))?$/i', $trimmed) === 1) {
                return self::toPhpStringLiteral('CURRENT_TIMESTAMP');
            }
        }

        if ($kind === 'boolean') {
            $bool = self::parseBooleanLike($defaultValue);
            if ($bool !== null) {
                return $bool ? 'true' : 'false';
            }
        }

        if (in_array($kind, ['int'], true) && is_numeric($defaultValue)) {
            return (string) (int) $defaultValue;
        }

        if ($kind === 'decimal' && is_numeric($defaultValue)) {
            if (is_string($defaultValue)) {
                return trim($defaultValue);
            }
            return (string) $defaultValue;
        }

        return self::toPhpScalarLiteral($defaultValue);
    }

    private static function parseBooleanLike(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }
            if ($value === 0) {
                return false;
            }
            return null;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 't', 'yes', 'y', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'f', 'no', 'n', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * @param array<int,string> $fieldLines
     */
    private static function renderModelPhp(
        string $moduleName,
        string $rootFormName,
        string $tableName,
        string $dbName,
        string $primaryKey,
        array $fieldLines
    ): string {
        $namespace = 'Local\\Modules\\' . trim($moduleName, '\\');
        $className = trim($rootFormName) . 'Model';

        $tableLiteral = self::toPhpStringLiteral(self::withDbPrefixPlaceholder($tableName, $dbName));
        $dbLiteral = self::toPhpStringLiteral($dbName);
        $primaryLiteral = self::toPhpStringLiteral($primaryKey);
        $extensionsLiteral = self::toPhpArrayLiteral(['Projects']);

        $fieldBlock = '';
        if (!empty($fieldLines)) {
            $fieldBlock = "\n" . implode("\n", $fieldLines);
        }

        return <<<PHP
<?php
namespace {$namespace};

use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

class {$className} extends AbstractModel
{
    protected function configure(\$rule): void
    {
        \$rule->table({$tableLiteral})
            ->db({$dbLiteral})
            ->id({$primaryLiteral}){$fieldBlock}
            ->extensions({$extensionsLiteral});
    }
}
PHP;
    }

    private static function toPhpStringLiteral(string $value): string
    {
        return "'" . self::escapePhpSingleQuoted($value) . "'";
    }

    /**
     * @return array{0:array<string,mixed>|null,1:string}
     */
    private static function tryDescribe(object $db, string $tableName): array
    {
        try {
            $describe = $db->describes($tableName, true);
        } catch (\Throwable $e) {
            return [null, trim($e->getMessage())];
        }

        if (is_array($describe) && !empty($describe)) {
            return [$describe, ''];
        }

        $lastError = method_exists($db, 'getLastError') ? trim((string) $db->getLastError()) : '';
        return [null, $lastError];
    }

    private static function withDbPrefixPlaceholder(string $tableName, string $dbName): string
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return '';
        }

        $tableName = (string) preg_replace('/^#__/', '', $tableName);

        $db = self::resolveDatabaseConnection($dbName);
        if (is_object($db) && property_exists($db, 'prefix')) {
            $prefix = trim((string) $db->prefix);
            if ($prefix !== '') {
                $prefixToken = strtolower($prefix . '_');
                if (str_starts_with(strtolower($tableName), $prefixToken)) {
                    $tableName = (string) substr($tableName, strlen($prefix) + 1);
                }
            }
        }

        return '#__' . $tableName;
    }

    private static function withPhysicalPrefix(string $tableName, string $dbName): string
    {
        $tableName = trim((string) preg_replace('/^#__/', '', trim($tableName)));
        if ($tableName === '') {
            return '';
        }

        $db = self::resolveDatabaseConnection($dbName);
        if (!is_object($db) || !property_exists($db, 'prefix')) {
            return $tableName;
        }

        $prefix = trim((string) $db->prefix);
        if ($prefix === '') {
            return $tableName;
        }

        $prefixToken = strtolower($prefix . '_');
        if (str_starts_with(strtolower($tableName), $prefixToken)) {
            return $tableName;
        }

        return $prefix . '_' . $tableName;
    }

    private static function toPhpScalarLiteral(mixed $value): string
    {
        if (is_string($value)) {
            return self::toPhpStringLiteral($value);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value === null) {
            return 'null';
        }

        return self::toPhpStringLiteral((string) $value);
    }

    /**
     * @param array<int|string,mixed> $values
     */
    private static function toPhpArrayLiteral(array $values): string
    {
        $parts = [];
        foreach ($values as $key => $value) {
            $valueLiteral = self::toPhpScalarLiteral($value);
            if (is_int($key)) {
                $parts[] = $valueLiteral;
                continue;
            }
            $parts[] = self::toPhpStringLiteral((string) $key) . ' => ' . $valueLiteral;
        }

        return '[' . implode(', ', $parts) . ']';
    }

    private static function escapePhpSingleQuoted(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    /**
     * @return array{success:bool,content:string,primary_key:string,field_count:int,error:string}
     */
    private static function errorResult(string $error): array
    {
        return [
            'success' => false,
            'content' => '',
            'primary_key' => '',
            'field_count' => 0,
            'error' => trim($error),
        ];
    }
}
