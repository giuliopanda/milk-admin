<?php
namespace App\Database;

use App\ArrayQuery\ArrayEngine;
use App\Exceptions\DatabaseException;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Array-based database adapter using ArrayQuery parser/executor.
 */
class ArrayDb
{
    public string $last_error = '';
    public bool $error = false;
    public string $last_query = '';
    public string $prefix = '';
    public string $type = 'array';
    public array $tables_list = [];
    public array $fields_list = [];
    public array $query_columns = [];
    public int $affected_rows = 0;

    /**
     * @var ArrayEngine|null
     */
    private ?ArrayEngine $database = null;

    /**
     * @var int|null
     */
    private ?int $last_insert_id = null;

    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    /**
     * Connects the adapter to an ArrayEngine or raw table data.
     *
     * @param ArrayEngine|array|null $data
     * @param array<string, string> $autoIncrementColumns
     * @return bool
     */
    public function connect($data = null, array $autoIncrementColumns = []): bool
    {
        if ($data instanceof ArrayEngine) {
            $this->database = $data;
        } else {
            $this->database = new ArrayEngine();
            if (is_array($data)) {
                foreach ($data as $tableName => $tableData) {
                    $rows = $tableData;
                    $autoIncrement = $autoIncrementColumns[$tableName] ?? null;

                    if (is_array($tableData)
                        && (array_key_exists('rows', $tableData) || array_key_exists('data', $tableData))
                    ) {
                        $rows = $tableData['rows'] ?? $tableData['data'] ?? [];
                        $autoIncrement = $tableData['auto_increment'] ?? $autoIncrement;
                    }

                    $this->database->addTable((string) $tableName, $rows, $autoIncrement);
                }
            }
        }

        $this->tables_list = array_keys($this->database->getAllTables());
        return true;
    }

    public function setWherePushdownEnabled(bool $enabled): void
    {
        if ($this->database === null) {
            $this->database = new ArrayEngine();
        }
        $this->database->setWherePushdownEnabled($enabled);
    }

    /**
     * Add a table to the in-memory database.
     *
     * @param string $tableName
     * @param array $data
     * @param string|null $autoIncrementColumn
     * @return self
     */
    public function addTable(string $tableName, array $data = [], ?string $autoIncrementColumn = null): self
    {
        if ($this->database === null) {
            $this->database = new ArrayEngine();
        }

        $this->database->addTable($tableName, $data, $autoIncrementColumn);
        $this->tables_list = array_keys($this->database->getAllTables());

        return $this;
    }

    /**
     * Executes an SQL query on the in-memory database.
     *
     * @param string $sql
     * @param array|null $params
     * @return ArrayResult|bool
     */
    public function query(string $sql, array|null $params = null): ArrayResult|bool
    {
        if (!$this->checkConnection()) {
            $this->error = true;
            $this->last_error = 'No connection';
            throw new DatabaseException(
                'No connection',
                'array',
                ['query' => $sql, 'params' => $params]
            );
        }

        $sql = $this->sqlPrefix($sql);
        $this->last_query = $sql;
        $this->error = false;
        $this->last_error = '';
        $this->affected_rows = 0;
        $this->query_columns = [];

        $statement = strtoupper((string) preg_replace('/\s+.*/', '', ltrim($sql)));

        try {
            $result = $this->database->query($sql, $params);
        } catch (\Throwable $e) {
            $this->error = true;
            $this->last_error = $e->getMessage();
            throw new DatabaseException(
                $this->last_error,
                'array',
                ['query' => $sql, 'params' => $params],
                0,
                $e
            );
        }

        if ($statement === 'SELECT') {
            $array_result = new ArrayResult($result);
            $this->query_columns = $array_result->get_fields();
            return $array_result;
        }

        if (is_array($result)) {
            if (isset($result['inserted'])) {
                $this->affected_rows = (int) $result['inserted'];
                $this->setLastInsertIdFromTable($result['table'] ?? null);
                return true;
            }
            if (isset($result['updated'])) {
                $this->affected_rows = (int) $result['updated'];
                return true;
            }
            if (isset($result['deleted'])) {
                $this->affected_rows = (int) $result['deleted'];
                return true;
            }
        }

        return true;
    }

    /**
     * Get the SQL query string with parameters replaced (for debug purposes only).
     */
    public function toSql(string $query, array|null $params = null): string
    {
        if (!is_array($params) || count($params) === 0) {
            return $query;
        }

        $quoted_values = array_map(function ($param) {
            if (is_null($param)) {
                return 'NULL';
            }
            if (is_bool($param)) {
                return $param ? '1' : '0';
            }
            if (is_int($param) || is_float($param)) {
                return $param;
            }
            if (is_string($param)) {
                return "'" . addslashes($param) . "'";
            }
            return "'" . addslashes(serialize($param)) . "'";
        }, $params);

        $index = 0;
        return preg_replace_callback('/\?/', function () use ($quoted_values, &$index) {
            return $quoted_values[$index++] ?? '?';
        }, $query);
    }

    /**
     * Executes a query and returns a generator for iterating through results.
     */
    public function yield(string $sql, $params = null): ?\Generator
    {
        if (!$this->checkConnection()) {
            return null;
        }

        $query_result = $this->query($sql, $params);
        if ($query_result === false) {
            return null;
        }

        while ($row = $query_result->fetch_object()) {
            yield $row;
        }
    }

    /**
     * Returns the columns of the last query.
     */
    public function getQueryColumns(): array
    {
        return $this->query_columns;
    }

    /**
     * Executes a query for non-buffered results from a table.
     */
    public function nonBufferedQuery(string $table, bool $assoc = true): ?\Generator
    {
        if (!$this->checkConnection()) {
            return null;
        }

        $result = $this->query("SELECT * FROM " . $this->qn($table));
        if ($result === false) {
            return null;
        }

        while ($row = $result->fetch_array()) {
            if ($assoc) {
                yield $row;
            } else {
                yield array_values($row);
            }
        }
    }

    public function getResults(string $sql, $params = null): ?array
    {
        if (!$result = $this->query($sql, $params)) {
            return null;
        }
        $data = [];
        while ($row = $result->fetch_object()) {
            $data[] = $row;
        }
        return $data;
    }

    public function getRow(string $sql, $params = null, int $offset = 0): ?object
    {
        if (!$result = $this->query($sql, $params)) {
            return null;
        }
        if ($result->num_rows() <= $offset) {
            return null;
        }
        $k = 0;
        while ($row = $result->fetch_object()) {
            if ($k === $offset) {
                return $row;
            }
            $k++;
        }
        return null;
    }

    public function getVar(string $sql, $params = null, int $offset = 0): ?string
    {
        if (!$result = $this->query($sql, $params)) {
            return null;
        }
        $k = 0;
        while ($row = $result->fetch_array()) {
            if ($k === $offset) {
                return (string) array_shift($row);
            }
            $k++;
        }
        return null;
    }

    public function insert(string $table, array $data): bool|int
    {
        $field = [];
        $values = [];
        $bind_params = [];

        foreach ($data as $key => $val) {
            $field[] = $this->qn($key);
            $values[] = '?';
            $bind_params[] = $val;
        }

        if (count($values) === 0) {
            $query = "INSERT INTO " . $this->qn($table) . " () VALUES ()";
            $this->query($query);
        } else {
            $query = "INSERT INTO " . $this->qn($table) . " (" . implode(", ", $field) . " ) VALUES (" . implode(", ", $values) . ")";
            $this->query($query, $bind_params);
        }

        return $this->error ? false : ($this->last_insert_id ?? 0);
    }

    public function update(string $table, array $data, array $where, int $limit = 0): bool
    {
        $field = [];
        $values = [];
        $bind_params = [];

        foreach ($data as $key => $val) {
            $field[] = $this->qn($key) . " = ?";
            $bind_params[] = $val;
        }

        foreach ($where as $key => $val) {
            $values[] = $this->qn($key) . " = ?";
            $bind_params[] = $val;
        }

        if (count($field) > 0 && count($values) > 0) {
            $limit_clause = $limit > 0 ? " LIMIT " . $limit : "";
            $query = "UPDATE " . $this->qn($table) . " SET " . implode(", ", $field) . " WHERE " . implode(" AND ", $values) . $limit_clause;
            $this->query($query, $bind_params);
            return !$this->error;
        }

        $this->error = true;
        $this->last_error = 'Invalid update parameters';
        return false;
    }

    public function delete(string $table, array $where): bool
    {
        $values = [];
        $bind_params = [];

        foreach ($where as $key => $val) {
            $values[] = $this->qn($key) . " = ?";
            $bind_params[] = $val;
        }

        if (count($values) > 0) {
            $query = "DELETE FROM " . $this->qn($table) . " WHERE " . implode(" AND ", $values);
            $this->query($query, $bind_params);
            return !$this->error;
        }

        $this->error = true;
        $this->last_error = 'Invalid delete parameters';
        return false;
    }

    public function dropTable($table): bool
    {
        if (!$this->checkConnection()) {
            return false;
        }
        $this->database->removeTable($table);
        $this->tables_list = array_keys($this->database->getAllTables());
        return true;
    }

    public function dropView($view): bool
    {
        return false;
    }

    public function renameTable($table_name, $new_name): bool
    {
        if (!$this->checkConnection()) {
            return false;
        }

        try {
            $data = $this->database->getTable($table_name);
        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            return false;
        }

        $autoIncrement = $this->database->getAutoIncrementColumn($table_name);
        $this->database->addTable($new_name, $data, $autoIncrement);
        $this->database->removeTable($table_name);
        $this->tables_list = array_keys($this->database->getAllTables());
        return true;
    }

    public function truncateTable($table_name): bool
    {
        if (!$this->checkConnection()) {
            return false;
        }
        $this->database->setTable($table_name, []);
        return true;
    }

    public function multiQuery(string $sql)
    {
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        if (empty($statements)) {
            return false;
        }

        foreach ($statements as $statement) {
            $this->query($statement);
            if ($this->error) {
                return false;
            }
        }

        return true;
    }

    public function insertId(): ?int
    {
        return $this->last_insert_id;
    }

    public function save(string $table, array $data, array $where)
    {
        if (!$this->checkConnection()) {
            return false;
        }

        $values = [];
        $bind_params = [];

        foreach ($where as $key => $val) {
            if ($val == 0) {
                continue;
            }
            $values[] = $this->qn($key) . " = ?";
            $bind_params[] = $val;
        }

        if (count($bind_params) > 0) {
            $exists = $this->getVar(
                'SELECT count(*) as tot FROM ' . $this->qn($table) . ' WHERE ' . implode(" AND ", $values),
                $bind_params
            );
            if ((int) $exists === 1) {
                return $this->update($table, $data, $where, 1);
            }
            if ((int) $exists === 0) {
                return $this->insert($table, $data);
            }
            $this->error = true;
            $this->last_error = "Error update is not possible because there are more than one record";
            return false;
        }

        return $this->insert($table, $data);
    }

    public function getTables(bool $cache = true): array
    {
        if (!$this->checkConnection()) {
            return [];
        }
        if ($cache && !empty($this->tables_list)) {
            return $this->tables_list;
        }
        $this->tables_list = array_keys($this->database->getAllTables());
        return $this->tables_list;
    }

    public function getViews(bool $cache = true): array
    {
        return [];
    }

    public function getViewDefinition(string $view_name): ?string
    {
        return null;
    }

    public function getColumns(string $table_name, bool $force_reload = false): array
    {
        if (!$this->checkConnection()) {
            return [];
        }

        $table_name = $this->sqlPrefix($table_name);
        $table = [];

        try {
            $table = $this->database->getTable($table_name);
        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            return [];
        }

        if (empty($table)) {
            return [];
        }

        $firstRow = $table[0];
        $columns = [];
        $autoIncrement = $this->database->getAutoIncrementColumn($table_name);

        foreach (array_keys($firstRow) as $name) {
            $column_obj = new \stdClass();
            $column_obj->Field = $name;
            $column_obj->Type = $this->inferFieldType($firstRow[$name] ?? null);
            $column_obj->Null = 'YES';
            $column_obj->Key = ($autoIncrement !== null && $name === $autoIncrement) ? 'PRI' : '';
            $column_obj->Default = null;
            $column_obj->Extra = ($autoIncrement !== null && $name === $autoIncrement) ? 'auto_increment' : '';
            $columns[] = $column_obj;
        }

        return $columns;
    }

    public function describes(string $table_name, bool $cache = true): array
    {
        if (!$this->checkConnection()) {
            return [];
        }

        if ($cache && array_key_exists($table_name, $this->fields_list) != false) {
            return $this->fields_list[$table_name];
        }

        $columns = $this->getColumns($table_name);
        if (empty($columns)) {
            return [];
        }

        $fields = [];
        $struct = [];
        $primary = [];
        $autoIncrement = $this->database->getAutoIncrementColumn($table_name);

        foreach ($columns as $column) {
            $fields[$column->Field] = $column->Type;
            if ($autoIncrement !== null && $column->Field === $autoIncrement) {
                $primary[] = $column->Field;
                $column->Key = 'PRI';
                $column->Extra = 'auto_increment';
            }
            $struct[$column->Field] = $column;
        }

        $this->fields_list[$table_name] = ['fields' => $fields, 'keys' => $primary, 'struct' => $struct];
        return $this->fields_list[$table_name];
    }

    public function showCreateTable(string $table_name): array
    {
        if (!$this->checkConnection()) {
            return ['type' => '', 'sql' => ''];
        }

        $columns = $this->describes($table_name, false);
        if (empty($columns)) {
            return ['type' => '', 'sql' => ''];
        }

        $fieldSql = [];
        foreach ($columns['fields'] as $field => $type) {
            $fieldSql[] = $field . ' ' . $type;
        }

        $sql = "CREATE TABLE " . $table_name . " (" . implode(", ", $fieldSql) . ")";
        return ['type' => 'table', 'sql' => $sql];
    }

    public function affectedRows(): int
    {
        return $this->affected_rows;
    }

    public function setFieldsList(string $tableName, array $fields, array $primaryKey): void
    {
        $this->fields_list[$tableName] = ['fields' => $fields, 'keys' => $primaryKey];
    }

    public function lastQuery(): string
    {
        return $this->last_query;
    }

    public function qn(string $val): string
    {
        $val = $this->sqlPrefix($val);

        if (preg_match('/^(.+?)\s+AS\s+(.+)$/i', $val, $matches)) {
            return $this->qnSafe(trim($matches[1])) . ' AS ' . $this->qnSafe(trim($matches[2]));
        }

        if (strpos($val, '.') !== false) {
            $parts = explode('.', $val, 2);
            return $this->qnSafe($parts[0]) . '.' . $this->qnSafe($parts[1]);
        }

        return $this->qnSafe($val);
    }

    public function quote(string $val): string
    {
        if (is_null($val) || strtolower((string) $val) === 'null') {
            return 'NULL';
        }
        if (is_int($val) || is_float($val)) {
            return (string) $val;
        }
        return "'" . addslashes((string) $val) . "'";
    }

    private function qnSafe(string $name): string
    {
        $name = trim($name);
        if (preg_match('/^`(.*)`$/', $name, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^"(.*)"$/', $name, $matches)) {
            return $matches[1];
        }

        return $name;
    }

    private function sqlPrefix(string $query): string
    {
        return str_replace("#__", $this->prefix . "_", $query);
    }

    public function checkConnection(): bool
    {
        if ($this->database === null) {
            $this->error = true;
            $this->last_error = "Database not connected";
            return false;
        }
        $this->error = false;
        $this->last_error = '';
        return true;
    }

    public function getLastError(): string
    {
        return $this->last_error;
    }

    public function hasError(): bool
    {
        return ($this->last_error !== '');
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function begin(): void
    {
    }

    public function commit(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function close(): void
    {
    }

    private function setLastInsertIdFromTable(?string $tableName): void
    {
        if ($tableName === null || $this->database === null) {
            $this->last_insert_id = null;
            return;
        }

        $autoIncrementColumn = $this->database->getAutoIncrementColumn($tableName);
        if ($autoIncrementColumn === null) {
            $this->last_insert_id = null;
            return;
        }

        $table = $this->database->getTable($tableName);
        $max = null;
        foreach ($table as $row) {
            if (isset($row[$autoIncrementColumn])) {
                $value = (int) $row[$autoIncrementColumn];
                if ($max === null || $value > $max) {
                    $max = $value;
                }
            }
        }
        $this->last_insert_id = $max;
    }

    private function inferFieldType($value): string
    {
        if (is_int($value)) {
            return 'int';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_bool($value)) {
            return 'tinyint(1)';
        }
        return 'text';
    }
}
