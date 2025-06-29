<?php
namespace Modules\docs;
/**
 * @title Database
 * @category Framework
 * @order 
 * @tags 
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Database Documentation - MilkCore</h1>
    
    <div class="alert alert-info">
        <strong>Note:</strong> This documentation describes methods for direct database access through SQL queries. 
        For advanced and object-oriented data management, it is recommended to use the <strong>abstract Model class</strong> 
        which provides a safer and more modern interface for CRUD operations. Additionally, table management 
        (creation and modification) can be automatically handled by the structure proposed by classes that extend <strong>abstractObject</strong>.
        <br><br>
        <strong>Query Construction:</strong> For building complex SQL queries, it is recommended to use the <strong>Query class</strong> 
        which provides a fluent interface for constructing queries programmatically.
    </div>
    
    <h3>Query Builder Example</h3>
    <p>The Query class allows you to build complex SQL queries using a fluent interface:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use MilkCore\Query;

// Create a query builder for the 'users' table
$query = new Query('users');

// Build the query using method chaining
$query->select('name, email, created_at')
      ->where('status = ?', ['active'])
      ->where('age >= ?', [18], 'AND')
      ->order('created_at', 'desc')
      ->limit(0, 10);

// Get the SQL and parameters
list($sql, $params) = $query->get();

// Execute the query
$db = Get::db();
$users = $db->get_results($sql, $params);

// Get total count for pagination
list($totalSql, $totalParams) = $query->get_total();
$total = $db->get_var($totalSql, $totalParams);</code></pre>
        
    <h2>Overview</h2>
    <p>The MilkCore system supports two types of databases: <strong>MySQL</strong> and <strong>SQLite</strong>. 
    Both drivers implement the same public interface, ensuring complete compatibility in application code.</p>
    
    <p>The framework provides two separate database connections:</p>
    <ul>
        <li><strong>Primary Database (db)</strong>: Used for configurations, internal modules, and system metadata</li>
        <li><strong>Secondary Database (db2)</strong>: Used for main application data</li>
    </ul>
    
    <p>Both connections can be either MySQL or SQLite according to configuration and can point to the same database if needed.</p>

    <h3>Accessing Database Connections</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Primary database (configurations and internal modules)
$db = Get::db();

// Secondary database (application data)  
$db2 = Get::db2();</code></pre>

    <h2>Public Methods</h2>
   
    <h4 class="mt-4">get_results($sql, $params = null)</h4>
    <p>Executes a SELECT query and returns all results as an array of objects.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function get_results(string $sql, array|null $params = null): array|null</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$sql</code> - <code>String</code> - The SQL query to execute.</li>
        <li><code>$params</code> - <code>Array</code> (optional) - Parameters for prepared statement.</li>
    </ul>
    <p><strong>Returns:</strong> <code>Array|null</code> - Array of objects with results or <code>null</code> on error.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get all active users
$users = $db->get_results("SELECT * FROM #__users WHERE status = ?", ['active']);

// Process results
foreach ($users as $user) {
    echo "User: " . $user->username . " - Email: " . $user->email . "\n";
}

// Check if there are results
if ($users !== null && count($users) > 0) {
    echo "Found " . count($users) . " active users";
}</code></pre>
    
    <h4 class="mt-4">get_row($sql, $params = null, $offset = 0)</h4>
    <p>Executes a SELECT query and returns a single row as an object.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function get_row(string $sql, array|null $params = null, int $offset = 0): ?object</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$sql</code> - <code>String</code> - The SQL query to execute.</li>
        <li><code>$params</code> - <code>Array</code> (optional) - Parameters for prepared statement.</li>
        <li><code>$offset</code> - <code>Integer</code> (optional) - Row offset to return (default: 0).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Object|null</code> - Object containing the row or <code>null</code> if not found or error.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get a specific user by ID
$user = $db->get_row("SELECT * FROM #__users WHERE id = ?", [123]);
if ($user) {
    echo "Username: " . $user->username;
} else {
    echo "User not found";
}

// Get the second row from results
$secondUser = $db->get_row("SELECT * FROM #__users ORDER BY created_at DESC", null, 1);</code></pre>
    
    <h4 class="mt-4">get_var($sql, $params = null, $offset = 0)</h4>
    <p>Executes a SELECT query and returns the first value of the first column.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function get_var(string $sql, array|null $params = null, int $offset = 0): ?string</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$sql</code> - <code>String</code> - The SQL query to execute.</li>
        <li><code>$params</code> - <code>Array</code> (optional) - Parameters for prepared statement.</li>
        <li><code>$offset</code> - <code>Integer</code> (optional) - Row offset to return (default: 0).</li>
    </ul>
    <p><strong>Returns:</strong> <code>String|null</code> - The value of the first column or <code>null</code> if not found or error.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Count number of users
$userCount = $db->get_var("SELECT COUNT(*) FROM #__users");
echo "Total users: " . $userCount;

// Get username of a specific user
$username = $db->get_var("SELECT username FROM #__users WHERE id = ?", [123]);

// Get the last inserted ID
$lastId = $db->get_var("SELECT MAX(id) FROM #__users");</code></pre>

    <h4 class="mt-4">query($sql, $params = null)</h4>
    <p>Executes an SQL query. Supports prepared statements when parameters are provided.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function query(string $sql, array|null $params = null): mixed</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$sql</code> - <code>String</code> - The SQL query to execute. Use <code>#__</code> for table prefix.</li>
        <li><code>$params</code> - <code>Array</code> (optional) - Parameters for prepared statement.</li>
    </ul>
    <p><strong>Returns:</strong> <code>MySQLResult|SQLiteResult|false</code> - Query result or <code>false</code> on error.</p>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Simple query
$result = $db->query("SELECT * FROM #__users");

// Prepared statement with one parameter
$result = $db->query("SELECT * FROM #__users WHERE id = ?", [1]);

// Prepared statement with multiple parameters
$result = $db->query(
    "SELECT * FROM #__users WHERE username = ? AND status = ?", 
    ['john_doe', 'active']
);

// Join between tables
$result = $db->query(
    "SELECT u.*, p.full_name FROM #__users u 
     JOIN #__profiles p ON u.id = p.user_id 
     WHERE u.created_at > ?",
    ['2024-01-01']
);</code></pre>
    
    <h4 class="mt-4">yield($sql, $params = null)</h4>
    <p>Executes a query and returns a generator to iterate over results. Useful for very large datasets.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function yield(string $sql, array|null $params = null): ?\Generator</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$sql</code> - <code>String</code> - The SQL query to execute.</li>
        <li><code>$params</code> - <code>Array</code> (optional) - Parameters for prepared statement.</li>
    </ul>
    <p><strong>Returns:</strong> <code>Generator|null</code> - A generator that iterates over results or <code>null</code> on error.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Process large datasets without loading everything into memory
foreach ($db->yield("SELECT * FROM #__large_table WHERE status = ?", ['active']) as $row) {
    echo "User: " . $row->username . "\n";
    // Process each row individually
}</code></pre>
  
    
    <h4 class="mt-4">get_tables($cache = true)</h4>
    <p>Returns the list of tables in the database.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function get_tables(bool $cache = true): array</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$cache</code> - <code>Boolean</code> (optional) - Whether to use cache (default: true).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Array</code> - Array containing table names.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get all tables (with cache)
$tables = $db->get_tables();

// Force cache refresh
$tables = $db->get_tables(false);

// Display tables
foreach ($tables as $table) {
    echo "Table: " . $table . "\n";
}</code></pre>

    <h4 class="mt-4">get_views($cache = true)</h4>
    <p>Returns the list of views in the database.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function get_views(bool $cache = true): array</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$cache</code> - <code>Boolean</code> (optional) - Whether to use cache (default: true).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Array</code> - Array containing view names.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$views = $db->get_views();
foreach ($views as $view) {
    echo "View: " . $view . "\n";
}</code></pre>

    <h4 class="mt-4">get_view_definition($view_name)</h4>
    <p>Returns the SQL definition of a view.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function get_view_definition(string $view_name): ?string</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$view_name</code> - <code>String</code> - View name.</li>
    </ul>
    <p><strong>Returns:</strong> <code>String|null</code> - SQL definition of the view or <code>null</code> if not found.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$definition = $db->get_view_definition('user_profiles_view');
if ($definition) {
    echo "View definition: " . $definition;
}</code></pre>
    
    <h4 class="mt-4">get_columns($tableName, $force_reload = false)</h4>
    <p>Returns the list of columns for a table (uses cache).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function get_columns(string $tableName, bool $force_reload = false): array</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$tableName</code> - <code>String</code> - Table name.</li>
        <li><code>$force_reload</code> - <code>Boolean</code> (optional) - Whether to force cache refresh (default: false).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Array</code> - Array containing column information.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get columns for users table (with cache)
$columns = $db->get_columns('users');

// Force cache refresh
$columns = $db->get_columns('users', true);

// Display columns
foreach ($columns as $column) {
    echo "Field: " . $column->Field . " - Type: " . $column->Type . "\n";
}</code></pre>

    <h4 class="mt-4">describes($tableName, $cache = true)</h4>
    <p>Returns an array with complete table information: fields, primary keys, and detailed structure.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function describes(string $tableName, bool $cache = true): array</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$tableName</code> - <code>String</code> - Table name.</li>
        <li><code>$cache</code> - <code>Boolean</code> (optional) - Whether to use cache (default: true).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Array</code> - Associative array with 'fields', 'keys', and 'struct'.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get complete table structure
$structure = $db->describes('users');

// Access field types
$idType = $structure['fields']['id']; // e.g., "int(11)"
$usernameType = $structure['fields']['username']; // e.g., "varchar(255)"

// Get primary keys
$primaryKeys = $structure['keys']; // e.g., ["id"]

// Access detailed structure
foreach ($structure['struct'] as $fieldName => $fieldInfo) {
    echo "Field: {$fieldName}\n";
    echo "  Type: {$fieldInfo->Type}\n";
    echo "  Null: {$fieldInfo->Null}\n";
    echo "  Key: {$fieldInfo->Key}\n";
    echo "  Default: {$fieldInfo->Default}\n";
}</code></pre>

    <h4 class="mt-4">show_create_table($table_name)</h4>
    <p>Returns the SQL command to create the table or view.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function show_create_table(string $table_name): array</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$table_name</code> - <code>String</code> - Table or view name.</li>
    </ul>
    <p><strong>Returns:</strong> <code>Array</code> - Array with 'type' ('table' or 'view') and 'sql' (CREATE command).</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$createInfo = $db->show_create_table('users');
echo "Type: " . $createInfo['type'] . "\n";
echo "SQL: " . $createInfo['sql'] . "\n";</code></pre>

    <h4 class="mt-4">affected_rows()</h4>
    <p>Returns the number of rows affected by the last query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function affected_rows(): int</code></pre>
    <p><strong>Returns:</strong> <code>Integer</code> - Number of affected rows.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$db->query("UPDATE #__users SET status = 'inactive' WHERE last_login < ?", ['2023-01-01']);
$affectedRows = $db->affected_rows();
echo "Updated {$affectedRows} users";</code></pre>
    
    <h4 class="mt-4">insert($table, $data)</h4>
    <p>Inserts a record into a table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function insert(string $table, array $data): bool|int</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$table</code> - <code>String</code> - Table name.</li>
        <li><code>$data</code> - <code>Array</code> - Associative array with data to insert.</li>
    </ul>
    <p><strong>Returns:</strong> <code>Integer|false</code> - Insert ID if successful, <code>false</code> otherwise.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Insert a new user
$userId = $db->insert('users', [
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'password' => password_hash('mypassword', PASSWORD_DEFAULT),
    'created_at' => date('Y-m-d H:i:s'),
    'status' => 'active'
]);

if ($userId) {
    echo "User created with ID: {$userId}";
} else {
    echo "Insert error: " . $db->last_error;
}</code></pre>



<h4 class="mt-4">update($table, $data, $where, $limit = 0)</h4>
    <p>Updates records in a table based on specified conditions.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function update(string $table, array $data, array $where, int $limit = 0): bool</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$table</code> - <code>String</code> - Table name.</li>
        <li><code>$data</code> - <code>Array</code> - Associative array with data to update.</li>
        <li><code>$where</code> - <code>Array</code> - Associative array with conditions.</li>
        <li><code>$limit</code> - <code>Integer</code> (optional) - Limit of rows to update (0 = no limit).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Boolean</code> - <code>true</code> if update was successful, <code>false</code> otherwise.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Update a specific user
$success = $db->update(
    'users',
    [
        'username' => 'new_username',
        'email' => 'new@example.com',
        'updated_at' => date('Y-m-d H:i:s')
    ],
    ['id' => 123]
);

// Update with limit
$success = $db->update(
    'users',
    ['status' => 'inactive'],
    ['last_login' => '2020-01-01'],
    10 // Only 10 records
);

if ($success) {
    echo "Update successful";
    echo "Affected rows: " . $db->affected_rows();
}</code></pre>

    <h4 class="mt-4">save($table, $data, $where)</h4>
    <p>Updates a record if it exists, otherwise inserts it.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function save(string $table, array $data, array $where): bool|int</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$table</code> - <code>String</code> - Table name.</li>
        <li><code>$data</code> - <code>Array</code> - Associative array with data.</li>
        <li><code>$where</code> - <code>Array</code> - Associative array with search conditions.</li>
    </ul>
    <p><strong>Returns:</strong> <code>Integer|boolean</code> - Insert ID, <code>true</code> for successful update, <code>false</code> for error.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Update if exists, insert if not
$result = $db->save(
    'settings',
    [
        'key' => 'site_title',
        'value' => 'My Website',
        'updated_at' => date('Y-m-d H:i:s')
    ],
    ['key' => 'site_title']
);

// Handle result
if (is_integer($result)) {
    echo "New record inserted with ID: {$result}";
} elseif ($result === true) {
    echo "Record updated";
} else {
    echo "Error: " . $db->last_error;
}</code></pre>
    
    <h4 class="mt-4">insert_id()</h4>
    <p>Returns the ID generated by the last INSERT query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function insert_id(): int</code></pre>
    <p><strong>Returns:</strong> <code>Integer</code> - The ID from the last INSERT query.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$db->insert('users', ['username' => 'test', 'email' => 'test@example.com']);
$lastId = $db->insert_id();
echo "Last inserted ID: {$lastId}";</code></pre>
    
    <h4 class="mt-4">last_query()</h4>
    <p>Returns the last executed query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function last_query(): string</code></pre>
    <p><strong>Returns:</strong> <code>String</code> - The last executed query.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$db->query("SELECT * FROM #__users");
$lastQuery = $db->last_query();
echo "Last query: " . $lastQuery;</code></pre>
    
    <h4 class="mt-4">qn($val)</h4>
    <p>Quotes table or column names for safe use in queries.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function qn(string $val): string</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$val</code> - <code>String</code> - The name to quote.</li>
    </ul>
    <p><strong>Returns:</strong> <code>String</code> - The quoted and safe name.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Quote table/column names
$tableName = $db->qn('users');           // `users` (MySQL) or "users" (SQLite)
$columnName = $db->qn('user_name');      // `user_name` or "user_name"
$qualified = $db->qn('users.username');  // `users`.`username` or "users"."username"

// Handle aliases
$alias = $db->qn('users AS u');          // `users` AS `u` or "users" AS "u"

// Build dynamic queries
$sql = "SELECT {$db->qn('username')}, {$db->qn('email')} FROM {$db->qn('users')}";</code></pre>

    
    <h4 class="mt-4">delete($table, $where)</h4>
    <p>Deletes records from the database.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function delete(string $table, array $where): bool</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$table</code> - <code>String</code> - Table name.</li>
        <li><code>$where</code> - <code>Array</code> - Associative array with conditions for deletion.</li>
    </ul>
    <p><strong>Returns:</strong> <code>Boolean</code> - <code>true</code> if deletion was successful, <code>false</code> otherwise.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Delete a specific user
$success = $db->delete('users', ['id' => 123]);

// Delete with multiple conditions
$success = $db->delete('users', [
    'status' => 'inactive',
    'last_login' => '2020-01-01'
]);

if ($success) {
    echo "Deletion successful";
} else {
    echo "Deletion error: " . $db->last_error;
}</code></pre>

    <h4 class="mt-4">drop_table($table)</h4>
    <p>Drops a table if it exists.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function drop_table(string $table): bool</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$success = $db->drop_table('temp_table');
if ($success) {
    echo "Table dropped";
}</code></pre>

    <h4 class="mt-4">drop_view($view)</h4>
    <p>Drops a view if it exists.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function drop_view(string $view): bool</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$success = $db->drop_view('user_summary_view');</code></pre>

    <h4 class="mt-4">rename_table($table_name, $new_name)</h4>
    <p>Renames a table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function rename_table(string $table_name, string $new_name): bool</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$success = $db->rename_table('old_users', 'users_backup');</code></pre>

    <h4 class="mt-4">truncate_table($table_name)</h4>
    <p>Truncates a table (removes all data and resets auto-increment).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function truncate_table(string $table_name): bool</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$success = $db->truncate_table('session_data');
if ($success) {
    echo "Table truncated";
}</code></pre>
    
    <h4 class="mt-4">multi_query($sql)</h4>
    <p>Executes multiple SQL statements separated by semicolons.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function multi_query(string $sql): bool</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$sql</code> - <code>String</code> - SQL queries to execute separated by semicolons</li>
    </ul>
    <p><strong>Returns:</strong> <code>Boolean</code> - <code>true</code> if queries were successful, <code>false</code> otherwise.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$sql = "
    CREATE TABLE #__temp (id INT PRIMARY KEY, name VARCHAR(255));
    INSERT INTO #__temp VALUES (1, 'Test'), (2, 'Test2');
    UPDATE #__temp SET name = 'Modified' WHERE id = 1;
";
$success = $db->multi_query($sql);</code></pre>
    
    <h4 class="mt-4">quote($val)</h4>
    <p>Quotes values for safe use in queries (prefer prepared parameters).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function quote(string $val): string</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$val</code> - <code>String</code> - The value to quote.</li>
    </ul>
    <p><strong>Returns:</strong> <code>String</code> - The quoted and escaped value.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Using quote (not recommended, prefer prepared parameters)
$username = $db->quote("john's_account");
$sql = "SELECT * FROM users WHERE username = {$username}";

// Preferable to use prepared parameters
$result = $db->query("SELECT * FROM users WHERE username = ?", ["john's_account"]);</code></pre>
    
    <h4 class="mt-4">connect(...)</h4>
    <p>Manually configures the database connection.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// MySQL
public function connect(string $ip, string $login, string $pass, string $dbname): bool

// SQLite  
public function connect(string $dbname): bool</code></pre>
    <p><strong>MySQL Parameters:</strong></p>
    <ul>
        <li><code>$ip</code> - <code>String</code> - Database server IP address.</li>
        <li><code>$login</code> - <code>String</code> - Username for connection.</li>
        <li><code>$pass</code> - <code>String</code> - Password for connection.</li>
        <li><code>$dbname</code> - <code>String</code> - Database name.</li>
    </ul>
    <p><strong>SQLite Parameters:</strong></p>
    <ul>
        <li><code>$dbname</code> - <code>String</code> - Database file path.</li>
    </ul>
    <p><strong>Returns:</strong> <code>Boolean</code> - <code>true</code> if connection was successful, <code>false</code> otherwise.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Manual MySQL connection (not recommended)
$db = new \MilkCore\MySql();
$success = $db->connect('localhost', 'user', 'password', 'my_database');

// Manual SQLite connection (not recommended)
$db = new \MilkCore\SQLite();
$success = $db->connect('my_database.db');

// Recommended method: use Get::db() or Get::db2()
$db = Get::db(); // Uses automatic configuration</code></pre>
    
    <h4 class="mt-4">close()</h4>
    <p>Closes the database connection.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function close(): void</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Manual closure (normally not needed)
$db->close();</code></pre>

    <h2>Transaction Management</h2>
    <p>The system supports database transactions to ensure data integrity during multiple related operations.</p>

    <h4 class="mt-4">begin()</h4>
    <p>Starts a new SQL transaction. Transactions allow executing multiple operations as a single atomic unit.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function begin(): void</code></pre>

    <h4 class="mt-4">commit()</h4>
    <p>Confirms all operations executed since the start of the transaction, making changes permanent in the database.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function commit(): void</code></pre>

    <h4 class="mt-4">tear_down()</h4>
    <p>Rolls back all operations executed since the start of the transaction, restoring the previous database state.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function tear_down(): void</code></pre>

    <h3>Complete Transaction Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">try {
    $db = Get::db();
    $db->begin(); // Start transaction
    
    // Insert a new order
    $orderId = $db->insert('orders', [
        'user_id' => 123,
        'total' => 99.50,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if (!$orderId) {
        throw new Exception("Error creating order");
    }
    
    // Insert order items
    $items = [
        ['product_id' => 1, 'quantity' => 2, 'price' => 25.00],
        ['product_id' => 2, 'quantity' => 1, 'price' => 49.50]
    ];
    
    foreach ($items as $item) {
        $item['order_id'] = $orderId;
        $itemId = $db->insert('order_items', $item);
        
        if (!$itemId) {
            throw new Exception("Error inserting item");
        }
        
        // Update inventory
        $updated = $db->update(
            'products',
            ['stock' => 'stock - ' . $item['quantity']], // For SQL expressions
            ['id' => $item['product_id']]
        );
        
        if (!$updated) {
            throw new Exception("Error updating inventory");
        }
    }
    
    // Everything went well, confirm changes
    $db->commit();
    echo "Order #{$orderId} created successfully!";
    
} catch (Exception $e) {
    // Error: rollback all changes
    $db->tear_down();
    echo "Transaction error: " . $e->getMessage();
    error_log("Transaction error: " . $e->getMessage());
}</code></pre>

    <h2>Transaction Notes</h2>
    <ul>
        <li>Transactions are useful when you need to execute multiple related operations that must all succeed or fail together as a single unit</li>
        <li>Make sure the database engine supports transactions (e.g., InnoDB for MySQL)</li>
        <li>It's important to properly handle errors using try/catch and call <code>tear_down()</code> in case of problems</li>
        <li>Transactions can improve performance when executing multiple related queries</li>
        <li>Avoid transactions that are too long as they might block other operations</li>
    </ul>

    <h2>Result Objects</h2>
    <p>Queries that return results return <code>MySQLResult</code> or <code>SQLiteResult</code> objects that implement a common interface:</p>

    <h3>Result Object Methods</h3>
    <ul>
        <li><code>fetch_array()</code> - Returns the next row as an associative array</li>
        <li><code>fetch_assoc()</code> - Alias of fetch_array()</li>
        <li><code>fetch_object()</code> - Returns the next row as an object (<strong>MySQL only</strong>)</li>
        <li><code>num_rows()</code> - Returns the total number of rows</li>
        <li><code>num_columns()</code> - Returns the number of columns</li>
        <li><code>reset()</code> - Resets cursor to the first row</li>
        <li><code>data_seek($offset)</code> - Moves cursor to a specific row</li>
        <li><code>column_name($index)</code> - Returns column name by index</li>
        <li><code>column_type($index)</code> - Returns column type by index</li>
        <li><code>finalize()</code> - Frees result set memory</li>
    </ul>

    <h3>Result Usage Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $db->query("SELECT id, username, email FROM #__users LIMIT 10");

if ($result) {
    echo "Number of rows: " . $result->num_rows() . "\n";
    echo "Number of columns: " . $result->num_columns() . "\n";
    
    // Iterate over results
    while ($row = $result->fetch_assoc()) {
        echo "User: {$row['username']} - Email: {$row['email']}\n";
    }
    
    // Reset to beginning
    $result->reset();
    
    // Go to third row
    $result->data_seek(2);
    $thirdRow = $result->fetch_assoc();
    
    // Free memory
    $result->finalize();
}</code></pre>

    <h2>Error Handling</h2>
    <p>Each database instance provides properties for error handling:</p>
    <ul>
        <li><code>$db->error</code> - <code>Boolean</code> - Indicates if the last operation generated an error</li>
        <li><code>$db->last_error</code> - <code>String</code> - Last error message</li>
    </ul>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $db->query("SELECT * FROM #__nonexistent_table");

if ($db->error) {
    echo "Query error: " . $db->last_error;
    // Log the error
    error_log("Database error: " . $db->last_error . " in query: " . $db->last_query());
} else {
    // Process results
    while ($row = $result->fetch_assoc()) {
        // ...
    }
}</code></pre>

    <h2>Best Practices</h2>
    <ul>
        <li><strong>Always use prepared parameters</strong> to prevent SQL injection</li>
        <li><strong>Use Get::db() and Get::db2()</strong> instead of creating manual connections</li>
        <li><strong>Always handle errors</strong> by checking <code>$db->error</code> after critical operations</li>
        <li><strong>Table prefixes</strong>: use <code>#__</code> in queries for automatic prefix</li>
        <li><strong>Free memory</strong> by calling <code>finalize()</code> on large result sets</li>
        <li><strong>For advanced operations</strong>, consider using the abstract Model class</li>
    </ul>

    <h3>Recommended Patterns</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Basic pattern for safe queries
function getUserById($id) {
    $db = Get::db();
    $user = $db->get_row("SELECT * FROM #__users WHERE id = ?", [$id]);
    
    if ($db->error) {
        error_log("Error getting user {$id}: " . $db->last_error);
        return null;
    }
    
    return $user;
}

// Pattern for operations with verification
function updateUserEmail($userId, $newEmail) {
    $db = Get::db();
    
    $success = $db->update(
        'users',
        ['email' => $newEmail, 'updated_at' => date('Y-m-d H:i:s')],
        ['id' => $userId]
    );
    
    if (!$success) {
        error_log("Failed to update email for user {$userId}: " . $db->last_error);
        return false;
    }
    
    $affectedRows = $db->affected_rows();
    return $affectedRows > 0;
}</code></pre>
</div>