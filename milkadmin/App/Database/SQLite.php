<?php
namespace App\Database;

use App\Logs;
use App\Exceptions\DatabaseException;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * SQLite Database Management Class
 * 
 * This class provides a comprehensive interface for interacting with SQLite databases.
 * It handles connections, queries, and database structure operations with a focus on
 * security through prepared statements and error handling.
 * 
 * @example
 * ```php
 * // Connect to database
 * $db = milkCore\Get::get('db');
 * 
 * // Run a simple query
 * $results = $db->query("SELECT * FROM users WHERE active = 1");
 * 
 * // Run a prepared statement
 * $user = $db->query(
 *     "SELECT * FROM users WHERE id = ?", 
 *     [123]
 * );
 * 
 * // Insert data
 * $db->insert('users', [
 *     'username' => 'john_doe',
 *     'email' => 'john@example.com',
 *     'created_at' => date('Y-m-d H:i:s')
 * ]);
 * 
 * // Using table prefix
 * $results = $db->query("SELECT * FROM #__users"); // Will be converted to prefix_users
 * ```
 * @package     App
 */
class SQLite
{
    /**
     * Last error message from database operations
     * 
     * @var string
     */
    public string $last_error = '';
    
    /**
     * Error flag indicating if the last query resulted in an error
     * 
     * @var bool
     */
    public $error = false;
    
    /**
     * The last SQL query that was executed
     * 
     * @var string
     */
    public $last_query = "";
    
    /**
     * Table prefix used for all database operations
     * 
     * @var string
     */
    public $prefix = '';

    /**
     * SQLite3 connection object
     * 
     * @var \SQLite3|null
     */
    private $sqlite = null;

    public $type = 'sqlite';
    
    /**
     * List of tables in the database
     * 
     * @var array
     */
    public $tables_list = array();
    
    /**
     * Name of the current database file
     * 
     * @var string
     */
    public $dbname = '';
    
    /**
     * List of fields for each table
     * 
     * @var array
     */
    var $fields_list = array();

    /**
     * List of fields selected in the query
     * 
     * @var array
     */
    var $query_columns = array();
    
    /**
     * Number of rows affected by the last query
     * 
     * @var int
     */
    var $affected_rows = 0;
    
    /**
     * List of views in the database
     * 
     * @var array
     */
    private $views_list = array();
    
    /**
     * List of columns for each table (cache)
     * 
     * @var null|array
     */
    private $show_columns_list = null;
    
    /**
     * Constructor that initializes the connection with an optional table prefix
     *
     * @param string $prefix Optional prefix for database tables
     */
    function __construct($prefix = '') {
        $this->prefix = $prefix;
    }
   
    /**
     * Executes an SQL query with optional parameters
     * 
     * This method supports prepared statements when parameters are provided.
     * Errors can be checked with $this->error after execution.
     * 
     * @param string $sql The SQL query to execute
     * @param array|null $params Optional parameters for prepared statements
     * @return SQLiteResult|false SQLiteResult object or false on error In Insert/Update/Delete returns true/false.
     * 
     * @example
     * ```php
     * // Simple query
     * $result = $db->query("SELECT * FROM #__users");
     * 
     * // Prepared statement
     * $result = $db->query("SELECT * FROM #__users WHERE id = ?", [1]);
     * 
     * // Multiple parameters
     * $result = $db->query(
     *     "SELECT * FROM #__users WHERE username = ? AND status = ?", 
     *     ['john', 'active']
     * );
     * 
     * // JOIN example
     * $result = $db->query(
     *     "SELECT u.*, p.name FROM #__users u JOIN #__profiles p ON u.id = p.user_id WHERE u.id = ?",
     *     [1]
     * );
     * ```
     */
    public function query(string $sql, array|null $params = null): SQLiteResult|bool {
        if(!$this->checkConnection()) {
            $this->error = true;
            $this->last_error = 'No connection';
            throw new DatabaseException(
                'No connection',
                'sqlite',
                ['query' => $sql, 'params' => $params]
            );
        }
        $sql = $this->sqlPrefix($sql);
        $this->last_query = $sql;
        $this->error = false;
        $this->last_error = '';
        $this->affected_rows = 0;
        $this->query_columns = [];

        $sql_to_log = $sql;
        
        try {
            $stmt = $this->sqlite->prepare($sql);

            if ($stmt === false) {
                $errorMsg = $this->sqlite->lastErrorMsg();
                Logs::set('DATABASE', "SQLITE Prepare: '".$sql."' error:". $errorMsg, 'ERROR');
                $this->error = true;
                $this->last_error = $errorMsg;

                throw new DatabaseException(
                    $this->last_error,
                    'sqlite',
                    ['query' => $sql, 'params' => $params]
                );
            }

            // Bind parameters for SQLite
            if (is_array($params) && count($params) > 0) {
                $i = 1;
                foreach ($params as $val) {
                    if (is_null($val)) {
                        $stmt->bindValue($i, $val, SQLITE3_NULL);
                    } else if (is_bool($val)) {
                        $stmt->bindValue($i, $val ? 1 : 0, SQLITE3_INTEGER);
                    } else if (is_int($val)) {
                        $stmt->bindValue($i, $val, SQLITE3_INTEGER);
                    } else if (is_double($val)) {
                        $stmt->bindValue($i, $val, SQLITE3_FLOAT);
                    } else {
                        $stmt->bindValue($i, $val, SQLITE3_TEXT);
                    }
                    $i++;
                }
            }


            $sql_to_log = $this->toSql($sql, $params);
            // Execute the statement
            $result = $stmt->execute();

            // Check for errors immediately after execute
            if ($result === false || $this->sqlite->lastErrorCode() != 0) {
                $errorMsg = $this->sqlite->lastErrorMsg();
                Logs::set('DATABASE', "SQLITE Query execute failed: '".$sql_to_log."' error: ". $errorMsg, 'ERROR');
                $this->error = true;
                $this->last_error = $errorMsg;

                throw new DatabaseException(
                    $this->last_error,
                    'sqlite',
                    ['query' => $sql, 'params' => $params]
                );
            }

            if ($result->numColumns() > 0) {
                // Query di tipo SELECT → ha colonne
                $ris = new SQLiteResult($result);

                // Ricava i nomi delle colonne
                for ($i = 0; $i < $result->numColumns(); $i++) {
                    $this->query_columns[] = $result->columnName($i);
                }
            } else {
                // Query di tipo INSERT/UPDATE/DELETE → nessun result set
                $this->affected_rows = $this->sqlite->changes();
                $result->finalize();
                $ris = true;
            }

            Logs::set('DATABASE', 'SQLITE::Query', $sql_to_log);

            return $ris;

        } catch (\Exception $e) {
            // Re-throw come DatabaseException se non lo è già
            if (!($e instanceof DatabaseException)) {
                $errorMsg = $e->getMessage();
                Logs::set('DATABASE', "SQLITE Exception: '".$sql_to_log."' error:". $errorMsg, 'ERROR');
                $this->error = true;
                $this->last_error = $errorMsg;

                throw new DatabaseException(
                    $this->last_error,
                    'sqlite',
                    ['query' => $sql, 'params' => $params]
                );
            }
            throw $e;
        }
    }

    /**
     * Debug prepared query - For debugging purposes only, NOT for execution
     */
    public function toSql($query, $params) {
        $values = array();
        if ($params == null) {
            return $query;
        }
        foreach ($params as $value) {
            if (is_string($value)) {
                $values[] = "'" . str_replace("'", "''", $value) . "'";
            } elseif (is_null($value)) {
                $values[] = 'NULL';
            } elseif (is_bool($value)) {
                $values[] = $value ? '1' : '0';
            } elseif (is_int($value) || is_float($value)) {
                $values[] = $value;
            } else {
                $values[] = "'" . str_replace("'", "''", strval($value)) . "'";
            }
        }
        
        $index = 0;
        $query = preg_replace_callback('/\?/', function($match) use ($values, &$index) {
            return isset($values[$index]) ? $values[$index++] : '?';
        }, $query);
        
        return $query;
    }

     /**
     * Executes a query and returns a generator for iterating through results
     * 
     * Useful for processing large result sets without loading all data into memory.
     * 
     * @param string $sql The SQL query to execute
     * @param array|null $params Optional parameters for prepared statements
     * @return \Generator|null Generator for iterating through results or null on error
     * 
     * @example
     * ```php
     * // Process large result sets one row at a time
     * foreach ($db->yield("SELECT * FROM #__large_table") as $row) {
     *     echo $row->column_name;
     * }
     * ```
     */
    public function yield(string $sql,  $params = null): ?\Generator {
        if(!$this->checkConnection()) {  
            return null;
        }
        
        $query_result = $this->query($sql, $params);
        
        if ($query_result === false) return null;
          
        while($row = $query_result->fetch_array(SQLITE3_ASSOC)) {
            yield (object)$row;
        }
    }

    /**
     * Returns the columns of the last query
     * 
     * @return array List of column names
     */
    public function getQueryColumns(): array {
        return $this->query_columns;
    }

    /**
     * Executes a query for non-buffered results from a table
     * 
     * Cannot use prepared statements with this method.
     * Useful for processing very large tables with minimal memory usage.
     * 
     * @param string $table The table name
     * @param bool $assoc Whether to return associative arrays (true) or indexed arrays (false)
     * @return \Generator|null Generator for iterating through results or null on error
     * 
     * @example
     * ```php
     * // Process all records from a large table as associative arrays
     * foreach ($db->nonBufferedQuery("large_table") as $key => $row) {
     *     echo $row['column_name'];
     * }
     * ```
     */
    public function nonBufferedQuery(string $table, bool $assoc = true): ?\Generator {
        if(!$this->checkConnection()) {  
            return null;
        }
        $table = str_replace(";","", $table);
        $sql = 'SELECT * FROM `'. $this->sqlPrefix($table).'`;';
        $this->last_query = $sql;
        $this->error = false;
        $this->last_error = '';

        try {
            $result = $this->sqlite->query($sql);
            $key = 0;
            
            if ($assoc == false) {
                while ($row = $result->fetchArray(SQLITE3_NUM)) {
                    yield $key => $row;
                    $key++;
                }
            } else {
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    yield $key => $row;
                    $key++;
                }
            }
        } catch (\Exception $e) {
            Logs::set('DATABASE', "SQLITE Query: '".$sql."' error:". $this->sqlite->lastErrorMsg(), 'ERROR');
            $this->error = true;
            $this->last_error = $this->sqlite->lastErrorMsg();
            return null;
        };
    }

    /**
     * Executes a SELECT query and returns all results as an array of objects
     * 
     * @param string $sql The SQL query to execute
     * @param array|null $params Optional parameters for prepared statements
     * @return array|null Array of objects containing query results or null on error
     * 
     * @example
     * ```php
     * // Get all active users
     * $users = $db->getResults("SELECT * FROM #__users WHERE status = ?", ['active']);
     * 
     * // Process results
     * foreach ($users as $user) {
     *     echo $user->username;
     * }
     * ```
     */
    public function getResults(string $sql,  $params = null) 
    {
        if (!$result = $this->query($sql, $params)) {
            return null;
        }
        $data = array();
        while($row = $result->fetch_array(SQLITE3_ASSOC))
        {
            $data[] = (object)$row;
        }
        return $data;
    }

    /**
     * Executes a SELECT query and returns a single row as an object
     * 
     * @param string $sql The SQL query to execute
     * @param array|null $params Optional parameters for prepared statements
     * @param int $offset Row offset (default 0)
     * @return object|null Object containing the row data or null if not found or on error
     * 
     * @example
     * ```php
     * // Get a user by ID
     * $user = $db->getRow("SELECT * FROM #__users WHERE id = ?", [1]);
     * echo $user->username;
     * 
     * // Get the second row from results
     * $user = $db->getRow("SELECT * FROM #__users ORDER BY id", null, 1);
     * ```
     */
    public function getRow(string $sql,  $params = null, int $offset = 0): ?object
    {
        if (!$result = $this->query($sql, $params)) {
            return null;
        }
       
        $k = 0;
        while($row = $result->fetch_array(SQLITE3_ASSOC)) {
            if ($k == $offset) {
                return (object)$row;
            }
            $k++;
        }
        return null;
    }

    /**
     * Executes a SELECT query and returns a single value from the first row
     * 
     * @param string $sql The SQL query to execute
     * @param array|null $params Optional parameters for prepared statements
     * @param int $offset Row offset (default 0)
     * @return string|null The first column value or null if not found or on error
     * 
     * @example
     * ```php
     * // Count users
     * $count = $db->getVar("SELECT COUNT(*) FROM #__users");
     * 
     * // Get a username by ID
     * $username = $db->getVar("SELECT username FROM #__users WHERE id = ?", [1]);
     * ```
     */
    public function getVar(string $sql,  $params = null, int $offset = 0): ?string
    {

        if (!$result = $this->query($sql, $params)) {
            return null;
        }
        $k = 0;
        if ($result->num_rows() > $offset) {            
            while($row = $result->fetch_array(SQLITE3_NUM))
            {
                if ($k == $offset) {
                    return (string)reset($row);
                }
                $k++;
            }
        }
        return null;
    }

    /**
     * Returns a list of tables in the database
     * 
     * @param bool $cache Whether to use cached results (default true)
     * @return array List of table names
     * 
     */
    public function getTables(bool $cache = true): array {
        if(!$this->checkConnection()) {  
            return [];
        }
        $ris = array();
        if ($cache && $this->tables_list != false) {
            return $this->tables_list;
        }
        $tables = $this->sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        while($row = $tables->fetchArray(SQLITE3_ASSOC))
        {
            $ris[] = $row['name'];
        }
        $this->tables_list = $ris;
        return $ris;
    }
    
   /**
     * Returns a list of views in the database
     * 
     * @param bool $cache Whether to use cached results (default true)
     * @return array List of view names
     * 
     */
    public function getViews(bool $cache = true): array {
        if(!$this->checkConnection()) {  
            return [];
        }
        $ris = array();
        if ($cache && isset($this->views_list) && $this->views_list != false) {
            return $this->views_list;
        }
        $views = $this->sqlite->query("SELECT name FROM sqlite_master WHERE type='view'");
        while($row = $views->fetchArray(SQLITE3_ASSOC)) {
            $ris[] = $row['name'];
        }
        $this->views_list = $ris;
        return $ris;
    }

      /**
     * Returns the SQL definition of a view
     * 
     * @param string $view_name The name of the view
     * @return string|null The SQL definition of the view or null if not found
     * 
     */
    public function getViewDefinition(string $view_name): ?string {
        if(!$this->checkConnection()) {  
            return null;
        }
        
        $query = "SELECT sql FROM sqlite_master WHERE type='view' AND name = ?"; 
        
        $stmt = $this->sqlite->prepare($query);
        $stmt->bindValue(1, $view_name, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row['sql'];
        }
        
        return null;
    }

     /**
     * Returns a list of columns for a table (uses cache)
     * 
     * Similar to SHOW COLUMNS but with caching capability
     * 
     * @param string $table_name The table name
     * @param bool $force_reload Whether to force refresh the cache (default false)
     * @return array Column information for the table
     * 
     * @example
     * ```php
     * // Get columns for users table
     * $columns = $db->getColumns('users');
     * 
     * // Force refresh the cache
     * $columns = $db->getColumns('users', true);
     * ```
     */
    public function getColumns(string $table_name, bool $force_reload = false): array {
        if(!$this->checkConnection()) {  
            return [];
        }
        if ($force_reload || $this->show_columns_list == null) {
            $this->preloadShowColumns();
        }
        $table_name = $this->sqlPrefix($table_name);
        if (array_key_exists($table_name, $this->show_columns_list) != false) {
            return $this->show_columns_list[$table_name];
        }
        return [];
    }

     /**
     * Returns an array with table fields and primary keys
     * 
     * @param string $tableName The table name
     * @param bool $cache Whether to use cached results (default true)
     * @return array Associative array with 'fields' and 'keys' elements 
     * ['fields', 'keys', 'struct'];
     * 
     * @example
     * ```php
     * // Get table structure
     * $structure = $db->describes('users');
     * 
     * // Access field types
     * $idType = $structure['fields']['id']; // e.g., "INTEGER"
     * 
     * // Get primary keys
     * $primaryKeys = $structure['keys']; // e.g., ["id"]
     * ```
     */
    public function describes(string $tableName, bool $cache = true): array {
        if(!$this->checkConnection()) {
            return [];
        }
        $ris = array();
        if ($cache && array_key_exists($tableName, $this->fields_list) != false) {
            return $this->fields_list[$tableName];
        }

        $tableName = $this->sqlPrefix($tableName);
        $fields = $this->query('PRAGMA table_info('.$this->qn($tableName).')');
        $primary = [];

        if ($fields === false) {
            $this->last_error = "Error in query 'PRAGMA table_info(".$this->qn($tableName).")' ".$this->sqlite->lastErrorMsg();
            return [];
        }

        // Get the CREATE TABLE statement to check for AUTOINCREMENT
        $create_table_info = $this->showCreateTable($tableName);
        $create_sql = $create_table_info['sql'] ?? '';

        $complete_strcut = [];
        while($row = $fields->fetch_array(SQLITE3_ASSOC))
        {
            $ris[$row['name']] = $row['type'];
            if ($row['pk'] == 1) {
                $primary[] = $row['name'];
            }

            // Determine Extra field (auto_increment, etc.)
            $extra = '';

            // Check if this is a primary key with AUTOINCREMENT
            if ($row['pk'] == 1) {
                // In SQLite, INTEGER PRIMARY KEY is automatically auto-increment
                // But we need to check if AUTOINCREMENT is explicitly set in the schema
                if (stripos($create_sql, 'AUTOINCREMENT') !== false) {
                    // AUTOINCREMENT is explicitly declared
                    $extra = 'auto_increment';
                } elseif (strtoupper($row['type']) === 'INTEGER') {
                    // INTEGER PRIMARY KEY is implicitly auto-increment in SQLite
                    $extra = 'auto_increment';
                }
            }

            // Mappa i campi SQLite per seguire la struttura MySQL
            $mysql_structure = [
                'Field' => $row['name'],                    // name -> Field
                'Type' => $row['type'],                     // type -> Type
                'Null' => $row['notnull'] == 0 ? 'YES' : 'NO',  // notnull -> Null (invertito)
                'Key' => $row['pk'] == 1 ? 'PRI' : '',      // pk -> Key
                'Default' => $row['dflt_value'],            // dflt_value -> Default
                'Extra' => $extra                           // auto_increment, etc.
            ];

            $complete_strcut[$row['name']] = (object)$mysql_structure;
        }
        $this->fields_list[$tableName] = ['fields' => $ris, 'keys' => $primary, 'struct' => $complete_strcut];
        return $this->fields_list[$tableName];
    }

    /**
     * @return array [type, sql]
     */
    public function showCreateTable(string $table_name): array {
        $query = "SELECT sql, type FROM sqlite_master WHERE type='table' AND name=?";
        $stmt = $this->sqlite->prepare($query);
        $stmt->bindValue(1, $table_name, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) {
            return $this->showCreateTableFromView($table_name);
        }
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row === false) {
           return $this->showCreateTableFromView($table_name);
        }
        return ['type'=>'table', 'sql'=>$row['sql']];
    }
    /**
     * @return [type, sql]
     */
    private function showCreateTableFromView(string $table_name): array {
        $query = "SELECT sql FROM sqlite_master WHERE type='view' AND name=?";
        $stmt = $this->sqlite->prepare($query);
        $stmt->bindValue(1, $table_name, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) {
            return ['type'=>'', 'sql'=>''];
        }
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row === false) {
            return ['type'=>'', 'sql'=>''];
        }
        $structure_query = "PRAGMA table_info(".$table_name.")";
        $structure_result = $this->sqlite->query($structure_query);
        if ($structure_result === false) {
            return ['', ''];
        }
        // Poi costruisci una CREATE TABLE più dettagliata
        $createSQL = "CREATE TABLE ".$this->qn($table_name)." (\n";
        $cols = [];

        while ( $col = $structure_result->fetchArray(SQLITE3_ASSOC)) {
            $definition = "    " . $this->qn($col['name']) . " " . 
            ($col['type'] ?: 'TEXT'); // SQLite è flessibile sui tipi
            if ($col['notnull']) $definition .= " NOT NULL";
            if ($col['dflt_value']) $definition .= " DEFAULT " . $col['dflt_value'];
            if ($col['pk']) $definition .= " PRIMARY KEY";
            
            $cols[] = $definition;
        }
        $createSQL .= implode(",\n", $cols) . "\n);";

        return ['type'=>'view', 'sql'=>$createSQL];
    }

    /**
     * Returns the number of affected rows from the last query
     * 
     * @return int Number of affected rows
     */
    public function affectedRows(): int {
        return $this->sqlite->changes();
    }

    /**
     * Sets field definitions for a table to avoid database queries
     * 
     * Used as a cache mechanism for table structure
     * 
     * @param string $tableName The table name
     * @param array $fields Associative array of field names and types
     * @param array $primaryKey Array of primary key field names
     * 
     */
    public function setFieldsList(string $tableName, array $fields, array $primaryKey): void {
        $this->fields_list[$tableName] = array('fields'=>$fields, 'keys'=>$primaryKey);
    }

      /**
     * Inserts a record into a table
     * 
     * @param string $table The table name
     * @param array $data Associative array of column names and values
     * @return bool|int Insert ID on success, false on failure
     * 
     * @example
     * ```php
     * // Insert a new user
     * $userId = $db->insert('users', [
     *     'username' => 'john_doe',
     *     'email' => 'john@example.com',
     *     'created_at' => date('Y-m-d H:i:s')
     * ]);
     * 
     * if ($userId) {
     *     echo "User created with ID: $userId";
     * } else {
     *     echo "Error: " . $db->last_error;
     * }
     * ```
     */
    public function insert(string $table, array $data)
    {
        if(!$this->checkConnection()) {
            return false;
        }
        $field = [];
        $values = [];
        $bind_params = [];
        foreach ($data as $key=>$val) {
            $field[] = $this->qn($key);
            $values[] = '?';
            $bind_params[] = $val;
        }

        // Handle empty insert (for tables with only auto-increment fields)
        if (count($values) === 0) {
            // SQLite supports: INSERT INTO table DEFAULT VALUES
            $query = "INSERT INTO ".$this->qn($table)." DEFAULT VALUES;";
            $query = $this->sqlPrefix($query);
            $this->query($query);

            if (!$this->error) {
                return $this->sqlite->lastInsertRowID();
            } else {
                // If the database doesn't support empty inserts, set a clear error message
                if (empty($this->last_error)) {
                    $this->last_error = "Cannot insert empty record. Table may not support empty inserts.";
                }
                return false;
            }
        }

        // Normal insert with data
        if (count($values) > 0) {
            $query = "INSERT INTO ".$this->qn($table)." (".implode(", ", $field)." ) VALUES (".implode(", ", $values).");";
            $query = $this->sqlPrefix($query);
            $this->query($query, $bind_params);
            if (!$this->error) {
                return $this->sqlite->lastInsertRowID();
            } else {
                return false;
            }
        }

        return false;
    }

    /** 
     * Deletes records from a table based on conditions
     * 
     * The where parameter is an associative array with column names as keys and values to match.
     * Only supports equality conditions.
     * 
     * @param string $table The table name
     * @param array $where Associative array of conditions (column => value)
     * @return bool True on success, false on failure
     * 
     * @example
     * ```php
     * // Delete a user by ID
     * $success = $db->delete('users', ['id' => 123]);
     * 
     * // Delete with multiple conditions
     * $success = $db->delete('users', [
     *     'status' => 'inactive',
     *     'last_login' => '2020-01-01'
     * ]);
     * ```
     */
    public function delete(string $table, array $where): bool {
        if(!$this->checkConnection()) {  
            return false;
        }
        $values = array();
        $bind_params = [];
        foreach ($where as $key => $val) {
            $values[] = $this->qn($key)." = ?";
            $bind_params[] = $val;
        }
        if (count($values) > 0) {
            $query = "DELETE FROM ".$this->qn($table)." WHERE ".implode(" AND ", $values).";";
            $query = $this->sqlPrefix($query);
            $this->query($query, $bind_params);
            return !$this->error;
        } else {
            $this->error = true;
        }
        return false;
    }

    /**
     * Drops a table if it exists
     * 
     * @param string $table The table name
     * @return bool True on success, false on failure
     * 
     */
    public function dropTable($table): bool {
        if(!$this->checkConnection()) return false;
        $this->query("DROP TABLE IF EXISTS ".$this->qn($table).";");
        return !$this->error;
    }

    public function dropView($view) {
        if(!$this->checkConnection()) return false;
        $this->query("DROP VIEW IF EXISTS ".$this->qn($view).";");
        return !$this->error;
    }
    /**
     * Renames a table
     * 
     * @param string $table_name The table name
     * @param string $new_name The new table name
     * @return bool True on success, false on failure
     * 
     */
    public function renameTable($table_name, $new_name) {
        if(!$this->checkConnection()) return false;
        $this->query("ALTER TABLE ".$this->qn($table_name) ." RENAME TO ". $this->qn($new_name)); 
        return !$this->error;
    }

    /**
     * Truncates a table
     * 
     * @param string $table_name The table name
     * @return bool True on success, false on failure
     * 
     */
    public function truncateTable($table_name) {
        if(!$this->checkConnection()) return false;
        if ($table_name == '') {
            $this->error = true;
            $this->last_error = 'Table name is required';
            return false;
        }
        $this->query("DELETE FROM ".$this->qn($table_name)); 
        if (!$this->error) {
            $this->query("DELETE FROM sqlite_sequence WHERE name='".$this->qn($table_name)."'");
        }
        return !$this->error;
    }

   /**
     * Executes multiple SQL statements separated by semicolons
     * 
     * This is asynchronous and doesn't wait for server response
     * 
     * @param string $sql Multiple SQL statements separated by semicolons
     * @return bool True on success, false on failure
     * 
     * @example
     * ```php
     * // Execute multiple statements
     * $db->multiQuery("
     *     CREATE TABLE temp (id INT);
     *     INSERT INTO temp VALUES (1), (2), (3);
     *     SELECT * FROM temp;
     * ");
     * ```
     */
    public function multiQuery(string $sql) {
        if(!$this->checkConnection()) {  
            return false;
        }
        $sql = $this->sqlPrefix($sql);
        Logs::set('DATABASE', 'SQLITE::multi_query'. $sql);
        
        // SQLite doesn't support multi_query natively, so we need to split and execute separately
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $result = $this->sqlite->exec($statement);
                if ($result === false) {
                    $this->error = true;
                    $this->last_error = $this->sqlite->lastErrorMsg();
                    return false;
                }
            }
        }
        return true;
    }

     /**
     * Updates records in a table based on conditions
     * 
     * @param string $table The table name
     * @param array $data Associative array of column names and new values
     * @param array $where Associative array of conditions (column => value)
     * @param int $limit NOT SUPPORTED IN SQLITE!!!
     * @return bool True on success, false on failure
     * 
     * @example
     * ```php
     * // Update a user by ID
     * $success = $db->update(
     *     'users',
     *     ['username' => 'new_username', 'email' => 'new@example.com'],
     *     ['id' => 123]
     * );
     */
    public function update(string $table, array $data, array $where, int $limit = 0): bool {
        if(!$this->checkConnection()) {  
            return false;
        }
        $this->error = false;
        $field = array();
        $values = array();
        $bind_params = [];
        foreach ($data as $key=>$val) {
            $field[] = $this->qn($key)." = ?";
            $bind_params[] = $val;
        }
        foreach ($where as $key=>$val) {
            $values[] = $this->qn($key)." = ?";
            $bind_params[] = $val;
        }
        if (count($values) > 0) 
        {
           // Limit not supported in sqlite, mantein only for compatibility
            $query = "UPDATE ".$this->qn($table)." SET ".implode(", ", $field)."  WHERE ".implode(" AND ", $values).";";
            $query = $this->sqlPrefix($query);
            $this->query($query, $bind_params);
            return !$this->error;
        } else {
            $this->error = true;
        }
        return false;
    }

    /**
     * Updates a record if it exists, or inserts it if it doesn't
     * 
     * @param string $table The table name
     * @param array $data Associative array of column names and values
     * @param array $where Associative array of conditions (column => value)
     * @return bool|int Insert ID on insert, true on update success, false on failure
     * 
     * @example
     * ```php
     * // Update user if exists, insert if not
     * $result = $db->save(
     *     'users',
     *     ['username' => 'john_doe', 'email' => 'john@example.com'],
     *     ['id' => 123]
     * );
     * 
     * // Create or update based on username
     * $result = $db->save(
     *     'settings',
     *     ['value' => 'new_value', 'updated_at' => date('Y-m-d H:i:s')],
     *     ['key' => 'site_title']
     * );
     * ```
     */
    public function save(string $table, array $data, array $where) {
        if(!$this->checkConnection()) {  
            return false;
        }
        $this->error = false;
        $values = array();
        $bind_params = [];
        
        foreach ($where as $key => $val) {
            if ($val == 0) continue;
            $values[] = $this->qn($key)." = ?";
            $bind_params[] = $val;
        }
        if (count($bind_params) > 0) 
        {
           // verifico se esiste già il record
            $exists = $this->getVar('SELECT count(*) as tot FROM '.$this->qn($table).' WHERE '.implode(" AND ", $values), $bind_params);
            if ($exists == 1) {
                // Merge data and where for update
                $merged_data = array_merge($data, $where);
                return $this->update($table, $merged_data, $where, 1);
            } else  if ($exists == 0) {
                // Merge data and where for insert
                $merged_data = array_merge($data, $where);
                return $this->insert($table, $merged_data);
            } else {
                $this->error = true;
                $this->last_error = "Error update is not possible because there are more than one record";
                return false;
            }
        } else {
            return $this->insert($table, $data);
        }
    }

    /**
     * Returns the ID generated by the last INSERT query
     * 
     * @return \integer
     */
    public function insertId() {
        if($this->checkConnection()) {  
            return $this->sqlite->lastInsertRowID();
        } else {
            return false;
        }
    } 

    /**
     * Returns the last executed query
     * 
     * @return string The last executed query
     * 
     */
    public function lastQuery() {
        return $this->last_query;
    } 

    /**
     * Escapes and quotes a table or column name for safe use in queries
     * 
     * Handles table.column notation and AS aliases
     * Evita di quotare nuovamente campi già quotati
     * 
     * @param string $val The name to quote
     * @return string The quoted name
     */
    public function qn(string $val) {
        $val = $this->sqlPrefix($val);
        
        // Handle explicit AS aliases
        if (preg_match('/^(.+?)\s+AS\s+(.+)$/i', $val, $matches)) {
            return $this->qnSafe(trim($matches[1])) . ' AS ' . $this->qnSafe(trim($matches[2]));
        }
        
        // Handle table.column notation
        if (strpos($val, '.') !== false) {
            $parts = explode('.', $val, 2);
            return $this->qnSafe($parts[0]) . '.' . $this->qnSafe($parts[1]);
        }
        
        return $this->qnSafe($val);
    }

    private function qnSafe(string $name) {
        $name = trim($name);
        
        // Se è già quotato con virgolette doppie, ritorna così com'è
        if (preg_match('/^".*"$/', $name)) {
            // Verifica che sia ben formato (virgolette bilanciate)
            $inner = substr($name, 1, -1);
            if (substr_count($inner, '"') % 2 === 0) {
                return $name; // È già quotato correttamente
            }
        }
        
        // Se è già quotato con backtick, ritorna così com'è
        if (preg_match('/^`.*`$/', $name)) {
            return $name;
        }
        
        // Verifica lunghezza
        if (strlen($name) > 64) {
            throw new \InvalidArgumentException("Identifier too long: $name");
        }
        
        if (empty($name)) {
            throw new \InvalidArgumentException("Empty identifier not allowed ");
        }
        
        // Whitelist rigorosa
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_ #\-]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid identifier: $name");
        }
        
        // SQLite: escape virgolette doppie
        return '"' . str_replace('"', '""', $name) . '"';
    }
    /**
     * Quotes a value for safe use in queries
     * 
     * @param string $val The value to quote
     * @return string The quoted value
     */
    public function quote(string $val): string {
        if (is_null($val) || strtolower($val) == 'null') {
            return 'NULL';
        } elseif (is_int($val)) {
            return (string)$val;
        } else {
            return "'".$this->sqlite->escapeString($val)."'";
        }
    }
  
   /**
     * Connects to a SQLite database
     *
     * @param string $dbname Database file path
     * @return bool True on success
     * @throws DatabaseException When connection fails
     *
     * @example
     * ```php
     * // Connect to SQLite database (creates file if not exists)
     * try {
     *     $db->connect('database.db');
     * } catch (DatabaseException $e) {
     *     echo "Connection failed: " . $e->getMessage();
     * }
     * ```
     */
    public function connect(string $dbname): bool {
        $this->close();
        $this->error = false;
        $this->dbname = STORAGE_DIR.'/'.$dbname;
        try {

            // Check if directory exists and is writable
            $dir = dirname($this->dbname);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new \Exception("Cannot create directory: $dir");
                }
            }

            if (!is_writable($dir)) {
                throw new \Exception("Directory is not writable: $dir");
            }

            // Open database with explicit read-write flags (creates file if not exists)
            $this->sqlite = new \SQLite3(
                $this->dbname,
                SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE
            );

            // Enable exceptions
            $this->sqlite->enableExceptions(true);

            // Set busy timeout to handle locked database (wait up to 5 seconds)
            $this->sqlite->busyTimeout(5000); // 5000 milliseconds = 5 seconds

            // Enable foreign keys
            $this->sqlite->exec('PRAGMA foreign_keys = ON');

            // Set performance optimizations
            // WAL mode allows concurrent reads and writes
            $walResult = $this->sqlite->exec('PRAGMA journal_mode = WAL'); // Write-Ahead Logging
            $this->sqlite->exec('PRAGMA synchronous = NORMAL'); // Faster writes

            // Ensure WAL files are writable (they are created with database permissions)
            if ($walResult) {
                @chmod($this->dbname . '-shm', 0666);
                @chmod($this->dbname . '-wal', 0666);
            }

        } catch (\Exception $e) {
            $errorMsg = "SQLite connection failed: " . $e->getMessage();
            Logs::set('DATABASE', "SQLITE Connect: dbname:".$this->dbname." error: ".$e->getMessage(), 'ERROR');
            $this->error = true;
            $this->last_error = $errorMsg;

            throw new DatabaseException(
                $errorMsg,
                'sqlite',
                [
                    'database' => $this->dbname,
                    'directory' => dirname($this->dbname)
                ]
            );
        }

        if (!$this->sqlite) {
            $errorMsg = "Failed to create SQLite3 object";
            $this->error = true;
            $this->last_error = $errorMsg;

            throw new DatabaseException(
                $errorMsg,
                'sqlite',
                [
                    'database' => $this->dbname
                ]
            );
        }

        return true;
    }

     /**
     * Begins a database transaction
     * 
     * Use transactions when you need to perform multiple related operations
     * that must all succeed or fail together as a single unit.
     * 
     * @example
     * ```php
     * try {
     *     $db->begin(); // Start transaction
     *     
     *     $db->insert('orders', ['total' => 100.00]);
     *     $orderId = $db->insertId();
     *     
     *     $db->insert('order_items', [
     *         'order_id' => $orderId,
     *         'product_id' => 123,
     *         'quantity' => 1
     *     ]);
     *     
     *     $db->save(); // Confirm changes
     * } catch (Exception $e) {
     *     $db->tearDown(); // Cancel on error
     *     echo "Transaction failed: " . $e->getMessage();
     * }
     * ```
     */
    public function begin(): void {
        if($this->checkConnection()) {   
            $this->sqlite->exec('BEGIN TRANSACTION');
        }
    }

    /**
     * Commits the current transaction, making all changes permanent
     * 
     * @see begin() For usage example
     */
    public function commit(): void {
        if($this->checkConnection()) {   
            $this->sqlite->exec('COMMIT');
        }
    }

    /**
     * Rolls back the current transaction, canceling all changes
     * 
     * @see begin() For usage example
     */
    public function tearDown(): void {
        if($this->checkConnection()) {   
            $this->sqlite->exec('ROLLBACK');
        }
    }

    /**
     * Closes the database connection
     */
    public function close(): void 
    {
        if($this->checkConnection()) {   
            $this->sqlite->close();
            $this->sqlite = null;
        }
    }

    /**
     * Replaces '#__' with the actual table prefix
     * 
     * @param string $query SQL query with #__ placeholders
     * @return string Query with replaced prefixes
     * 
     * @example
     * ```php
     * // If prefix is 'wp'
     * $prefixed = $this->sqlPrefix("SELECT * FROM #__users");
     * // Result: "SELECT * FROM wp_users"
     * ```
     */
    private function sqlPrefix(string $query): string {
        return str_replace("#__", $this->prefix."_", $query);
    }

     /**
     * Checks if the database connection is established
     * 
     * @return bool True if connected, false otherwise
     */
    public function checkConnection(): bool {
        if($this->sqlite == null) {   
            $this->error = true;
            $this->last_error = "No database connection";
            return false;
        }
        $this->last_error = '';
        $this->error = false;
        return true;
    }

    /**
     * @return string The last error message
     */
    public function getLastError(): string {
        return $this->last_error;
    }

    public function hasError(): bool {
        return ($this->last_error != '');
    }

    /**
     * @return string The database type (mysql or sqlite)
     */
    public function getType(): string {
        return $this->type;
    }


   /**
     * Preloads column information for all tables and views into cache
     * 
     * Optimizes performance by fetching all column data in a single query
     * instead of multiple PRAGMA table_info calls.
     * 
     * @return bool True on success, false on failure
     */
    private function preloadShowColumns(): bool {
        if(!$this->checkConnection()) {  
            return false;
        }
        
        if ($this->show_columns_list != null) {
            return true; // già caricato
        }
        
        // Reset cache
        $this->show_columns_list = array();
        
        // Get all tables
        $tables = $this->getTables(false);
        
        // Get all views
        $views_query = "SELECT name FROM sqlite_master WHERE type='view'";
        $views_result = $this->query($views_query);
        
        $views = array();
        if ($views_result !== false) {
            while($row = $views_result->fetch_array(SQLITE3_ASSOC)) {
                $views[] = $row['name'];
            }
        }
        
        // Combina tables e views
        $all_objects = array_merge($tables, $views);
        
        foreach ($all_objects as $object_name) {
            $query = "PRAGMA table_info(" . $this->qn($object_name) . ")";
            $result = $this->query($query);

            if ($result === false) {
                $this->last_error = "Error loading columns for object $object_name: " . $this->sqlite->lastErrorMsg();
                continue;
            }

            // Get the CREATE TABLE statement to check for AUTOINCREMENT
            $create_table_info = $this->showCreateTable($object_name);
            $create_sql = $create_table_info['sql'] ?? '';

            $this->show_columns_list[$object_name] = array();

            while($row = $result->fetch_array(SQLITE3_ASSOC)) {
                // Determine Extra field (auto_increment, etc.)
                $extra = '';

                // Check if this is a primary key with AUTOINCREMENT
                if ($row['pk'] == 1) {
                    // In SQLite, INTEGER PRIMARY KEY is automatically auto-increment
                    // But we need to check if AUTOINCREMENT is explicitly set in the schema
                    if (stripos($create_sql, 'AUTOINCREMENT') !== false) {
                        // AUTOINCREMENT is explicitly declared
                        $extra = 'auto_increment';
                    } elseif (strtoupper($row['type']) === 'INTEGER') {
                        // INTEGER PRIMARY KEY is implicitly auto-increment in SQLite
                        $extra = 'auto_increment';
                    }
                }

                // Convert to object format to match MySQL version
                $column_obj = new \stdClass();
                $column_obj->Field = $row['name'];
                $column_obj->Type = $row['type'];
                $column_obj->Null = $row['notnull'] ? 'NO' : 'YES';
                $column_obj->Key = $row['pk'] ? 'PRI' : '';
                $column_obj->Default = $row['dflt_value'];
                $column_obj->Extra = $extra;

                $this->show_columns_list[$object_name][] = $column_obj;
            }
        }

        return true;
    }

}
