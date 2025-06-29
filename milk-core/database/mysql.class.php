<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * MySQL Database Management Class
 * 
 * This class provides a comprehensive interface for interacting with MySQL databases.
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
 * @package     MilkCore
 */
class MySql
{
    /**
     * Last error message from database operations
     * 
     * @var string
     */
    public $last_error = '';
    
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
     * MySQLi connection object
     * 
     * @var \mysqli|null
     */
    var $mysqli = null;
    
    /**
     * List of tables in the database
     * 
     * @var array
     */
    var $tablesList = array();
    
    /**
     * Name of the current database
     * 
     * @var string
     */
    var $dbname = '';
    /**
     * Type of the database
     * 
     * @var string
     */
    public $type = 'mysql';
    
    /**
     * List of fields for each table
     * 
     * @var array
     */
    var $fields_list = array();
    
    /**
     * List of views in the database
     * 
     * @var array
     */
    var $viewsList = array();
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
     * @return mixed Query MySQLResult or false on error
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
    public function query(string $sql, array|null $params = null): mixed {
        if(!$this->check_connection()) {  
            $this->error = true;
            $this->last_error = 'No connection';
            return false;
        }
        $sql = $this->sql_prefix($sql);
        $this->last_query = $sql;
        $this->error = false;
        $this->last_error = '';
        if (is_array($params) && count($params) > 0) {
            // se la query è sbagliata ritorna fatal error!! devo impedire che torni fatal error!
            $stmt =  false;
            try {
                $stmt =  $this->mysqli->prepare($sql);
            } catch (\Exception $e) {
                Logs::set('system', 'ERROR', "MYSQL Prepare: '".$sql."' error:". $this->mysqli->error);
                $this->error = true;
                $this->last_error = $this->mysqli->error;
                return false;
            };
            if ($stmt === false) {
                Logs::set('system', 'ERROR', "MYSQL Prepare: '".$sql."' error:". $this->mysqli->error);
                $this->error = true;
                $this->last_error = $this->mysqli->error;
                return false;
            }
            $types = '';
            $values = array();
            foreach ($params as $val) {
                if (is_int($val)) {
                    $types .= 'i';
                } else if (is_double($val)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
            // get sql types
            //echo "<p>SQL Types: ".$types."</p>";
            $stmt->execute();
            $ris = $stmt->get_result();
            Logs::set('system', 'MYSQL::Query', $sql);
            $stmt->close();
        } else {
            try {
                $ris = $this->mysqli->query($sql);
                Logs::set('system', 'MYSQL Query', $sql);
            } catch (\Exception $e) {
                Logs::set('system', 'ERROR', "MYSQL Query: '".$sql."' error:". $this->mysqli->error);
                $this->error = true;
                $this->last_error = $this->mysqli->error;
                return false;
            };
        }

        if ($this->mysqli->error != '') {
            Logs::set('system', 'ERROR', "MYSQL Query false: '".$sql."' error:". $this->mysqli->error);
            $this->error = true;
            $this->last_error = $this->mysqli->error;
        }
        return $ris ? new MySQLResult($ris) : false;
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
    public function yield(string $sql, $params = null): ?\Generator {
        if(!$this->check_connection()) {  
            return null;
        }
        
        $query_result = $this->query($sql, $params);
        
        if ($query_result === false) return null;
          
        while($row = $query_result->fetch_object()) {
            yield $row;
        }
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
     * foreach ($db->non_buffered_query("large_table") as $key => $row) {
     *     echo $row['column_name'];
     * }
     * ```
     */
    public function non_buffered_query(string $table, bool $assoc = true): ?\Generator {
        if(!$this->check_connection()) {  
            return null;
        }
        $table = str_replace(";","", $table);
        $sql = 'SELECT * FROM `'. $this->sql_prefix($table).'`;';
        $this->last_query = $sql;
        $this->error = false;
        $this->last_error = '';

        try {
            $stmt = $this->mysqli->query($sql, MYSQLI_USE_RESULT);
            $key = 0;
            // while non associativo
            if ($assoc == false) {
                while ($row = $stmt->fetch_row()) {
                    // Processa ogni riga
                    yield $key => $row;
                    $key++;
                }
            } else {
                while ($row = $stmt->fetch_assoc()) {
                    // Processa ogni riga
                    yield $key => $row;
                    $key++;
                }
            }

          
            $stmt->close();
        } catch (\Exception $e) {
            Logs::set('system', 'ERROR', "MYSQL Query: '".$sql."' error:". $this->mysqli->error);
            $this->error = true;
            $this->last_error = $this->mysqli->error;
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
     * $users = $db->get_results("SELECT * FROM #__users WHERE status = ?", ['active']);
     * 
     * // Process results
     * foreach ($users as $user) {
     *     echo $user->username;
     * }
     * ```
     */
    public function get_results(string $sql, $params = null) {
        if (!$result = $this->query($sql, $params)) {
            return null;
        }
        $data = array();
        while($row = $result->fetch_object()) {
            $data[] = $row;
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
     * $user = $db->get_row("SELECT * FROM #__users WHERE id = ?", [1]);
     * echo $user->username;
     * 
     * // Get the second row from results
     * $user = $db->get_row("SELECT * FROM #__users ORDER BY id", null, 1);
     * ```
     */
    public function get_row(string $sql, $params = null, int $offset = 0): ?object {
        if (!$result = $this->query($sql, $params)) {
            return null;
        }
        if ($result->num_rows() <= $offset) {
            return null;
        }
       
        $k = 0;
        while($row = $result->fetch_object()) {
            if ($k == $offset) {
                return $row;
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
     * $count = $db->get_var("SELECT COUNT(*) FROM #__users");
     * 
     * // Get a username by ID
     * $username = $db->get_var("SELECT username FROM #__users WHERE id = ?", [1]);
     * ```
     */
    public function get_var(string $sql, $params = null, int $offset = 0): ?string {
        if (!$result = $this->query($sql, $params)) {
            return null;
        }
        $k = 0;
        while($row = $result->fetch_array()) {
            if ($k == $offset) {
                return array_shift($row);
            }
            $k++;
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
    public function get_tables(bool $cache = true): array {
        if(!$this->check_connection()) {  
            return [];
        }
        if ($cache && $this->tablesList != false) {
            return $this->tablesList;
        }
        
        $ris = array();
        $tables = $this->query('SHOW TABLES');
        if ($tables === false) {
            return [];
        }
        
        while($row = $tables->fetch_array()) {
            $ris[] = array_shift($row);
        }
        $this->tablesList = $ris;
        return $ris;
    }
    
   /**
     * Returns a list of views in the database
     * 
     * @param bool $cache Whether to use cached results (default true)
     * @return array List of view names
     * 
     */
     public function get_views(bool $cache = true): array {
        if(!$this->check_connection()) {  
            return [];
        }
        if ($cache && isset($this->viewsList) && $this->viewsList != false) {
            return $this->viewsList;
        }
        
        $ris = array();
        $views = $this->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()");
        if ($views === false) {
            return [];
        }
        
        while($row = $views->fetch_array()) {
            $ris[] = array_shift($row);
        }
        $this->viewsList = $ris;
        return $ris;
    }


      /**
     * Returns the SQL definition of a view
     * 
     * @param string $view_name The name of the view
     * @return string|null The SQL definition of the view or null if not found
     * 
     */
    public function get_view_definition(string $view_name): ?string {
        if(!$this->check_connection()) {  
            return null;
        }
        
        $query = "SELECT VIEW_DEFINITION FROM information_schema.VIEWS 
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"; 
        
        $result = $this->query($query, [$view_name]);
        if ($result === false) {
            return null;
        }
        
        if ($row = $result->fetch_object()) {
            return $row->VIEW_DEFINITION;
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
     * $columns = $db->get_columns('users');
     * 
     * // Force refresh the cache
     * $columns = $db->get_columns('users', true);
     * ```
     */
    public function get_columns(string $table_name, bool $force_reload = false): array {
        if(!$this->check_connection()) {  
            return [];
        }
        if ($force_reload || $this->show_columns_list == null) {
            $this->preload_show_columns();
        }
        $table_name = $this->sql_prefix($table_name);
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
     * 
     * @example
     * ```php
     * // Get table structure
     * $structure = $db->describes('users');
     * 
     * // Access field types
     * $idType = $structure['fields']['id']; // e.g., "int(11)"
     * 
     * // Get primary keys
     * $primaryKeys = $structure['keys']; // e.g., ["id"]
     * ```
     */
    public function describes(string $table_name, bool $cache = true): array {
        if(!$this->check_connection()) {  
            return [];
        }
        if ($cache && array_key_exists($table_name, $this->fields_list) != false) {
            return $this->fields_list[$table_name];
        }
        
        $ris = array();
        $fields = $this->query('DESCRIBE '.$this->qn($table_name)); 
        $primary = [];
        
        if ($fields === false) {
            $this->last_error = "Error in query 'DESCRIBE ".$this->qn($table_name)."' ".$this->mysqli->error;
            return [];
        }
        
        $complete_strcut = [];
        while($row = $fields->fetch_assoc()) {
            $ris[$row['Field']] = $row['Type'];
            if ($row['Key'] == "PRI") {
                $primary[] = $row['Field'];
            }
            $complete_strcut[$row['Field']] = (object)$row;
        }
        $this->fields_list[$table_name] = ['fields' => $ris, 'keys' => $primary, 'struct' => $complete_strcut];
        return $this->fields_list[$table_name];
    }
      /**
     * @return array [type, sql]
     */
    public function show_create_table(string $table_name): array {
        $result = $this->query("SHOW CREATE TABLE " . $this->qn($table_name));
        if ($result === false) {
            return ['type' => '', 'sql' => ''];
        }
        $row = $result->fetch_assoc();
        if ($row === false || $row === null) {
            return ['type' => '', 'sql' => ''];
        }
        
        // check if the table is a view
        if (isset($row['Create View'])) {
            return ['type'=>'view', 'sql'=>$row['Create View']];
        }
        return ['type'=>'table', 'sql'=>$row['Create Table']];
    }


    /**
     * Returns the number of affected rows from the last query
     * 
     * @return int Number of affected rows
     */
    public function affected_rows(): int {
        return $this->mysqli->affected_rows;
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
     * @example
     * ```php
     * // Set fields list for users table
     * $db->set_fields_list('myprefix_table', ['id' => 'int(11)', 'username' => 'varchar(250)'], 'id');
     * ```
     * 
     */
    public function set_fields_list(string $tableName, array $fields, array $primaryKey): void {
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
    public function insert(string $table, array $data): bool 
    {
        if(!$this->check_connection()) {  
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
        if (count($values) > 0) {
            $query = "INSERT INTO ".$this->qn($table)." (".implode(", ", $field)." ) VALUES (".implode(", ", $values).");";
            $query = $this->sql_prefix($query);
           // print "<p>".$query."</p>";
           $this->query($query, $bind_params);
            if (!$this->error) {
                return $this->mysqli->insert_id;
            } else {
                return false;
            }
           
        } else {
            $this->error = true;
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
        if(!$this->check_connection()) {  
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
            $query = $this->sql_prefix($query);
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
    public function drop_table($table): bool {
        if(!$this->check_connection()) {  
            return false;
        }
        $query = "DROP TABLE IF EXISTS ".$this->qn($table).";";
        $this->query($query);
        return !$this->error;
    }

    /**
     * Drops a view if it exists
     * 
     * @param string $view The view name
     * @return bool True on success, false on failure
     * 
     */
    public function drop_view($view): bool {
        if(!$this->check_connection()) {
            return false;
        }
        $query = "DROP VIEW IF EXISTS ".$this->qn($view).";";
        $this->query($query);
        return !$this->error;
    }

    /**
     * Renames a table
     * 
     * @param string $table_name The current table name
     * @param string $new_name The new table name
     * @return bool True on success, false on failure
     * 
     */
    public function rename_table($table_name, $new_name): bool {
        if(!$this->check_connection()) {
            return false;
        }
        $query = "RENAME TABLE ".$this->qn($table_name) ." TO ". $this->qn($new_name).";";
        $this->query($query); 
        return !$this->error;
    }

    /**
     * Truncates a table (removes all data and resets auto-increment)
     * 
     * @param string $table_name The table name
     * @return bool True on success, false on failure
     * 
     */
    public function truncate_table($table_name): bool {
        if(!$this->check_connection()) {
            return false;
        }
        if ($table_name == '') {
            $this->error = true;
            $this->last_error = 'Table name is required';
            return false;
        }
        $query = "TRUNCATE TABLE ".$this->qn($table_name).";";
        $this->query($query); 
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
     * $db->multi_query("
     *     CREATE TABLE temp (id INT);
     *     INSERT INTO temp VALUES (1), (2), (3);
     *     SELECT * FROM temp;
     * ");
     * ```
     */
    public function multi_query(string $sql) {
        if(!$this->check_connection()) {  
            return false;
        }
        $sql = $this->sql_prefix($sql);
        Logs::set('system', 'MYSQL::multy_query', $sql);
        return $this->mysqli->multi_query($sql);
    }

     /**
     * Updates records in a table based on conditions
     * 
     * @param string $table The table name
     * @param array $data Associative array of column names and new values
     * @param array $where Associative array of conditions (column => value)
     * @param int $limit Maximum number of records to update (0 for no limit)
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
     * 
     * // Update with limit
     * $success = $db->update(
     *     'users',
     *     ['status' => 'inactive'],
     *     ['last_login' => '2020-01-01'],
     *     10 // Only update 10 records
     * );
     * ```
     */
    public function update(string $table, array $data, array $where, int $limit = 0): bool {
        if(!$this->check_connection()) {  
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
            if ($limit > 0) {
                $limit = " LIMIT ". _absint($limit);
            } else {
                $limit = "";
            }
            $query = "UPDATE ".$this->qn($table)." SET ".implode(", ", $field)."  WHERE ".implode(" AND ", $values).$limit.";";
            $query = $this->sql_prefix($query);
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
        if(!$this->check_connection()) {  
            return false;
        }
        $this->error = false;
        $field = array();
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
            $exists = $this->get_var('SELECT count(*) as tot FROM '.$this->qn($table).' WHERE '.implode(" AND ", $values), $bind_params);
            if ($exists == 1) {
                return $this->update($table, $data, $where, 1);
            } else  if ($exists == 0) {
                return $this->insert($table, $data);
            } else {
                $this->error = true;
                $this->last_error = "Error update is not possible because there are more than one record";
                return false;
            }
        } else {
            //print "Insert";
            return $this->insert($table, $data);
        }

    }

    /**
     * Returns the ID generated by the last INSERT query
     * 
     * @return \integer
     */
    public function insert_id() {
        if($this->check_connection()) {  
            return $this->mysqli->insert_id;
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
    public function last_query() {
        return $this->last_query;
    } 

    /**
     * Escapes and quotes a table or column name for safe use in queries (MySQL)
     * 
     * Handles table.column notation and AS aliases
     * Evita di quotare nuovamente campi già quotati
     * 
     * @param string $val The name to quote
     * @return string The quoted name
     */
    public function qn(string $val) {
        $val = $this->sql_prefix($val);
        
        // Handle explicit AS aliases
        if (preg_match('/^(.+?)\s+AS\s+(.+)$/i', $val, $matches)) {
            return $this->qn_safe(trim($matches[1])) . ' AS ' . $this->qn_safe(trim($matches[2]));
        }
        
        // Handle implicit aliases (space-separated without AS)
        // I don't handle it because table or column names can contain spaces
        
        // Handle table.column notation
        if (strpos($val, '.') !== false) {
            $parts = explode('.', $val, 2); // Limit to 2 parts
            return $this->qn_safe($parts[0]) . '.' . $this->qn_safe($parts[1]);
        }
        
        return $this->qn_safe($val);
    }

    private function qn_safe(string $name) {
        // Rimuovi spazi iniziali/finali
        $name = trim($name);
        
        // Se è già quotato con backtick, ritorna così com'è
        if (preg_match('/^`.*`$/', $name)) {
            // Verifica che sia ben formato (backtick bilanciate)
            $inner = substr($name, 1, -1);
            if (substr_count($inner, '`') % 2 === 0) {
                return $name; // È già quotato correttamente
            }
        }
        
        // Se è già quotato con virgolette doppie, ritorna così com'è
        if (preg_match('/^".*"$/', $name)) {
            // Verifica che sia ben formato (virgolette bilanciate)
            $inner = substr($name, 1, -1);
            if (substr_count($inner, '"') % 2 === 0) {
                return $name; // È già quotato correttamente
            }
        }
        
        // Verifica lunghezza (MySQL limit: 64 caratteri)
        if (strlen($name) > 64) {
            throw new \InvalidArgumentException("Identifier too long: $name");
        }
        
        // Verifica che non sia vuoto
        if (empty($name)) {
            throw new \InvalidArgumentException("Empty identifier not allowed");
        }
        
        // Regex più permissiva per MySQL
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_ #\-]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid identifier: $name");
        }
        
        // MySQL: escape backtick
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * Quotes a value for safe use in queries
     * 
     * @param string $val The value to quote
     * @return string The quoted value
     */
    public function quote(string $val): string {
        if (is_null($val) || strtolower($val) == 'NULL') {
            return 'NULL';
        } elseif (is_int($val)) {
            return $val;
        } else {
            return "'".$this->mysqli->real_escape_string($val)."'";
        }
    }
  
   /**
     * Connects to a MySQL database
     * 
     * @param string $ip Database server hostname or IP
     * @param string $login Database username
     * @param string $pass Database password
     * @param string $dbname Database name
     * @return bool True on success, false on failure
     * 
     * @example
     * ```php
     * // Connect to local database
     * $connected = $db->connect('localhost', 'root', 'password', 'my_database');
     * 
     * if (!$connected) {
     *     echo "Connection failed: " . $db->last_error;
     * }
     * ```
     */
    public function connect(string $ip, string $login, string $pass, string $dbname): bool {
        $this->close();
        $this->error =  false;
        $this->dbname = $dbname;
        $timeout = 5; 
        $this->mysqli =  mysqli_init( );
        if (!$this->mysqli->options( MYSQLI_OPT_CONNECT_TIMEOUT, $timeout ) ) {
            Logs::set('system', 'MYSQL', "I cannot setup connectection timeout");
            $this->error =  true;
        } 
        try {
             $this->mysqli->real_connect($ip, $login, $pass, $dbname);
        } catch (\Exception $e) {
            Logs::set('system', 'MYSQL', "Connect. ip:".$ip." login:". $login." psw:".$pass." dbname:".$dbname);
            Logs::set('system', 'ERROR', "MYSQL Connect. ip:".$ip." login:". $login." psw:".$pass." dbname:".$dbname);
            $this->error =  true;
            return false;
        } 
        // è connesso? 
        if ($this->mysqli->connect_error) {
            Logs::set('system', 'ERROR', "MYSQL Connect. ip:".$ip." login:". $login." psw:".$pass." dbname:".$dbname);
            $this->error =  true;
            return false;
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
     *     $orderId = $db->insert_id();
     *     
     *     $db->insert('order_items', [
     *         'order_id' => $orderId,
     *         'product_id' => 123,
     *         'quantity' => 1
     *     ]);
     *     
     *     $db->commit(); // Confirm changes
     * } catch (Exception $e) {
     *     $db->tear_down(); // Cancel on error
     *     echo "Transaction failed: " . $e->getMessage();
     * }
     * ```
     */
    public function begin(): void {
        if($this->check_connection()) {   
            $this->mysqli->begin_transaction();
        }
    }

    /**
     * Commits the current transaction, making all changes permanent
     * 
     * @see begin() For usage example
     */
    public function commit(): void {
        if($this->check_connection()) {   
            $this->mysqli->commit();
        }
    }

    /**
     * Rolls back the current transaction, canceling all changes
     * 
     * @see begin() For usage example
     */
    public function tear_down(): void {
        if($this->check_connection()) {   
            $this->mysqli->rollback();
        }
    }
    

    /**
     * Closes the database connection
     */
    public function close(): void 
    {
        if($this->check_connection()) {   
            $this->mysqli->close();
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
     * // If prefix is 'wp_'
     * $prefixed = $this->sql_prefix("SELECT * FROM #__users");
     * // Result: "SELECT * FROM wp__users"
     * ```
     */
    private function sql_prefix(string $query): string {
        return str_replace("#__", $this->prefix."_", $query);
    }

     /**
     * Checks if the database connection is established
     * 
     * @return bool True if connected, false otherwise
     */
    public function check_connection(): bool {
        if($this->mysqli == null) {   
            $this->error = true;
            return false;
        }
        $this->error = false;
        return true;
    }

    /**
     * @return string The last error message
     */
    public function get_last_error(): string {
        return $this->last_error;
    }

    /**
     * Preloads column information for all tables into cache
     * 
     * Optimizes performance by fetching all column data in a single query
     * instead of multiple SHOW COLUMNS calls.
     * 
     * @return bool True on success, false on failure
     */
    private function preload_show_columns(): bool {
        if(!$this->check_connection()) {  
            return false;
        }
        
        if ($this->show_columns_list != null) {
            return true;
        }
        
        $query = "SELECT 
                    TABLE_NAME,
                    COLUMN_NAME AS 'Field',
                    COLUMN_TYPE AS 'Type',
                    IS_NULLABLE AS 'Null',
                    COLUMN_KEY AS 'Key',
                    COLUMN_DEFAULT AS 'Default',
                    EXTRA AS 'Extra'
                FROM 
                    INFORMATION_SCHEMA.COLUMNS
                WHERE 
                    TABLE_SCHEMA = DATABASE()
                ORDER BY 
                    TABLE_NAME, ORDINAL_POSITION";
        
        $result = $this->query($query);
        
        if ($result === false) {
            $this->last_error = "Error loading all columns: " . $this->mysqli->error;
            return false;
        }
        
        $this->show_columns_list = array();
        
        while($row = $result->fetch_assoc()) {
            $table_name = $row['TABLE_NAME'];
            unset($row['TABLE_NAME']);
            
            $row['Null'] = ($row['Null'] === 'YES') ? 'YES' : 'NO';
            
            $column_obj = new \stdClass();
            $column_obj->Field = $row['Field'];
            $column_obj->Type = $row['Type'];
            $column_obj->Null = $row['Null'];
            $column_obj->Key = $row['Key'];
            $column_obj->Default = $row['Default'];
            $column_obj->Extra = $row['Extra'];
            
            if (!isset($this->show_columns_list[$table_name])) {
                $this->show_columns_list[$table_name] = array();
            }
            
            $this->show_columns_list[$table_name][] = $column_obj;
        }

        return true;
    }
}