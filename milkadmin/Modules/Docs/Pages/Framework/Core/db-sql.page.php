<?php
namespace Modules\Docs\Pages;
/**
 * @title Database
 * @guide framework
 * @order
 * @tags Database, SQL, MySQL, SQLite, Query Builder, select, where, order, limit, get, get_results, get_row, get_var, insert, update, delete, yield, debug_prepared_query, get_tables, get_view_definition, get_columns, describes, show_create_table, save
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
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use \App\Database\Query;

$query = new Query('users');
$query->select('name, email, created_at')
      ->where('status = ?', ['active'])
      ->where('age >= ?', [18], 'AND')
      ->order('created_at', 'desc')
      ->limit(0, 10);

list($sql, $params) = $query->get();
$db = Get::db();
$users = $db->getResults($sql, $params);</code></pre>

    <h2>Overview</h2>
    <p>The MilkCore system supports two types of databases: <strong>MySQL</strong> and <strong>SQLite</strong>.
    Both drivers implement the same public interface, ensuring complete compatibility in application code.</p>

    <p>The framework provides two separate database connections:</p>
    <ul>
        <li><strong>Primary Database (db)</strong>: Used for configurations, internal modules, and system metadata</li>
        <li><strong>Secondary Database (db2)</strong>: Used for main application data</li>
    </ul>

    <p>Both connections can be either MySQL or SQLite and can point to the same database if needed.</p>

    <h3>Accessing Database Connections</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$db = Get::db();   // Primary database
$db2 = Get::db2(); // Secondary database</code></pre>

    <h2>Query Methods</h2>

    <h4 class="mt-4">query($sql, $params = null)</h4>
    <p>Executes any SQL query (SELECT, INSERT, UPDATE, DELETE). Always use prepared statements with parameters.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function query(string $sql, array|null $params = null): MySQLResult|SQLiteResult|bool</code></pre>
    <p><strong>Returns:</strong> Result object for SELECT, <code>true/false</code> for INSERT/UPDATE/DELETE.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $db->query("SELECT * FROM #__users WHERE id = ?", [1]);
$result = $db->query("SELECT * FROM #__users WHERE username = ? AND status = ?", ['john', 'active']);</code></pre>

    <h4 class="mt-4">getResults($sql, $params = null)</h4>
    <p>Executes a SELECT query and returns all results as an array of objects.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function getResults(string $sql, array|null $params = null): array|null</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$users = $db->getResults("SELECT * FROM #__users WHERE status = ?", ['active']);
foreach ($users as $user) {
    echo $user->username . " - " . $user->email;
}</code></pre>

    <h4 class="mt-4">getRow($sql, $params = null, $offset = 0)</h4>
    <p>Executes a SELECT query and returns a single row as an object.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function getRow(string $sql, array|null $params = null, int $offset = 0): ?object</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$user = $db->getRow("SELECT * FROM #__users WHERE id = ?", [123]);
if ($user) {
    echo $user->username;
}</code></pre>

    <h4 class="mt-4">getVar($sql, $params = null, $offset = 0)</h4>
    <p>Executes a SELECT query and returns a single value (first column of first row).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function getVar(string $sql, array|null $params = null, int $offset = 0): ?string</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$count = $db->getVar("SELECT COUNT(*) FROM #__users");
$username = $db->getVar("SELECT username FROM #__users WHERE id = ?", [123]);</code></pre>

    <h4 class="mt-4">yield($sql, $params = null)</h4>
    <p>Returns a generator to iterate over results without loading all data in memory. Ideal for large datasets.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function yield(string $sql, array|null $params = null): ?\Generator</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">foreach ($db->yield("SELECT * FROM #__large_table") as $row) {
    echo $row->username;
}</code></pre>

    <h2>Data Manipulation Methods</h2>

    <h4 class="mt-4">insert($table, $data)</h4>
    <p>Inserts a record into a table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function insert(string $table, array $data): bool|int</code></pre>
    <p><strong>Returns:</strong> Insert ID on success, <code>false</code> on error.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$userId = $db->insert('users', [
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'created_at' => date('Y-m-d H:i:s')
]);

if ($userId) {
    echo "User created with ID: {$userId}";
} else if ($db->hasError()) {
    echo "Error: " . $db->getLastError();
}</code></pre>

    <h4 class="mt-4">update($table, $data, $where, $limit = 0)</h4>
    <p>Updates records in a table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function update(string $table, array $data, array $where, int $limit = 0): bool</code></pre>
    <p><strong>Returns:</strong> <code>true</code> on success, <code>false</code> on error.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$success = $db->update(
    'users',
    ['username' => 'new_username', 'updated_at' => date('Y-m-d H:i:s')],
    ['id' => 123]
);

if ($success) {
    echo "Updated " . $db->affectedRows() . " rows";
}</code></pre>

    <h4 class="mt-4">save($table, $data, $where)</h4>
    <p>Updates a record if it exists, otherwise inserts it (upsert operation).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function save(string $table, array $data, array $where): bool|int</code></pre>
    <p><strong>Returns:</strong> Insert ID (if inserted), <code>true</code> (if updated), <code>false</code> on error.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $db->save(
    'settings',
    ['key' => 'site_title', 'value' => 'My Website'],
    ['key' => 'site_title']
);

if (is_int($result)) {
    echo "Inserted with ID: {$result}";
} elseif ($result === true) {
    echo "Updated successfully";
}</code></pre>

    <h4 class="mt-4">delete($table, $where)</h4>
    <p>Deletes records from a table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function delete(string $table, array $where): bool</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$success = $db->delete('users', ['id' => 123]);
$success = $db->delete('users', ['status' => 'inactive', 'last_login' => '2020-01-01']);</code></pre>

    <h4 class="mt-4">affectedRows()</h4>
    <p>Returns the number of rows affected by the last INSERT/UPDATE/DELETE query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function affectedRows(): int</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$db->query("UPDATE #__users SET status = ? WHERE last_login < ?", ['inactive', '2023-01-01']);
echo "Updated " . $db->affectedRows() . " users";</code></pre>

    <h4 class="mt-4">insertId()</h4>
    <p>Returns the auto-increment ID from the last INSERT.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function insertId(): int</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$db->insert('users', ['username' => 'test']);
$lastId = $db->insertId();</code></pre>

    <h2>Database Structure Methods</h2>

    <h4 class="mt-4">getTables($cache = true)</h4>
    <p>Returns the list of tables in the database.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function getTables(bool $cache = true): array</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$tables = $db->getTables();
foreach ($tables as $table) {
    echo $table;
}</code></pre>

    <h4 class="mt-4">getViews($cache = true)</h4>
    <p>Returns the list of views in the database.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function getViews(bool $cache = true): array</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$views = $db->getViews();
foreach ($views as $view) {
    echo $view;
}</code></pre>

    <h4 class="mt-4">getViewDefinition($view_name)</h4>
    <p>Returns the SQL definition of a view.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function getViewDefinition(string $view_name): ?string</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$definition = $db->getViewDefinition('user_profiles_view');</code></pre>

    <h4 class="mt-4">getColumns($tableName, $force_reload = false)</h4>
    <p>Returns the list of columns for a table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function getColumns(string $tableName, bool $force_reload = false): array</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$columns = $db->getColumns('users');
foreach ($columns as $column) {
    echo $column->Field . " - " . $column->Type;
}</code></pre>

    <h4 class="mt-4">describes($tableName, $cache = true)</h4>
    <p>Returns complete table information: field types, primary keys, and structure details.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function describes(string $tableName, bool $cache = true): array</code></pre>
    <p><strong>Returns:</strong> Array with 'fields' (field=>type), 'keys' (primary keys), 'struct' (detailed info).</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$info = $db->describes('users');
$idType = $info['fields']['id'];  // "int(11)"
$primaryKeys = $info['keys'];      // ["id"]
foreach ($info['struct'] as $field => $details) {
    echo $field . ": " . $details->Type;
}</code></pre>

    <h4 class="mt-4">showCreateTable($table_name)</h4>
    <p>Returns the CREATE statement for a table or view.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function showCreateTable(string $table_name): array</code></pre>
    <p><strong>Returns:</strong> Array with 'type' (table/view) and 'sql' (CREATE statement).</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$info = $db->showCreateTable('users');
echo $info['type'] . ": " . $info['sql'];</code></pre>

    <h2>Table Management Methods</h2>

    <h4 class="mt-4">dropTable($table)</h4>
    <p>Drops a table if it exists.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function dropTable(string $table): bool</code></pre>

    <h4 class="mt-4">dropView($view)</h4>
    <p>Drops a view if it exists.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function dropView(string $view): bool</code></pre>

    <h4 class="mt-4">renameTable($table_name, $new_name)</h4>
    <p>Renames a table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function renameTable(string $table_name, string $new_name): bool</code></pre>

    <h4 class="mt-4">truncateTable($table_name)</h4>
    <p>Removes all data from a table and resets auto-increment.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function truncateTable(string $table_name): bool</code></pre>

    <h4 class="mt-4">multiQuery($sql)</h4>
    <p>Executes multiple SQL statements separated by semicolons.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function multiQuery(string $sql): bool</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$sql = "
    CREATE TABLE #__temp (id INT, name VARCHAR(255));
    INSERT INTO #__temp VALUES (1, 'Test'), (2, 'Test2');
";
$db->multiQuery($sql);</code></pre>

    <h2>Transaction Methods</h2>
    <p>Transactions ensure multiple operations succeed or fail together as a single atomic unit.</p>

    <h4 class="mt-4">begin()</h4>
    <p>Starts a new transaction.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function begin(): void</code></pre>

    <h4 class="mt-4">commit()</h4>
    <p>Commits the transaction, making all changes permanent.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function commit(): void</code></pre>

    <h4 class="mt-4">tearDown()</h4>
    <p>Rolls back the transaction, canceling all changes.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function tearDown(): void</code></pre>

    <h3>Transaction Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">try {
    $db->begin();

    $orderId = $db->insert('orders', [
        'user_id' => 123,
        'total' => 99.50,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    if (!$orderId) {
        throw new Exception("Error creating order");
    }

    $db->insert('order_items', [
        'order_id' => $orderId,
        'product_id' => 1,
        'quantity' => 2
    ]);

    $db->commit();
    echo "Order created successfully!";

} catch (Exception $e) {
    $db->tearDown();
    echo "Error: " . $e->getMessage();
}</code></pre>

    <h2>Utility Methods</h2>

    <h4 class="mt-4">lastQuery()</h4>
    <p>Returns the last executed SQL query (for debugging).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function lastQuery(): string</code></pre>

    <h4 class="mt-4">debugPreparedQuery($query, $params)</h4>
    <p>Returns the query with parameters substituted (for debugging only, not for execution).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function debugPreparedQuery(string $query, array $params): string</code></pre>

    <h4 class="mt-4">qn($val)</h4>
    <p>Quotes table or column names for safe use in queries.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function qn(string $val): string</code></pre>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$sql = "SELECT {$db->qn('username')} FROM {$db->qn('users')}";</code></pre>

    <h4 class="mt-4">quote($val)</h4>
    <p>Quotes values for safe use (prefer prepared statements instead).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function quote(string $val): string</code></pre>

    <h2>Result Objects</h2>
    <p>SELECT queries return result objects (<code>MySQLResult</code> or <code>SQLiteResult</code>) with these methods:</p>

    <ul>
        <li><code>fetchArray()</code> / <code>fetchAssoc()</code> - Returns next row as array</li>
        <li><code>fetchObject()</code> - Returns next row as object (MySQL only)</li>
        <li><code>numRows()</code> - Returns number of rows</li>
        <li><code>numColumns()</code> - Returns number of columns</li>
        <li><code>reset()</code> - Resets cursor to first row</li>
        <li><code>dataSeek($offset)</code> - Moves cursor to specific row</li>
        <li><code>finalize()</code> - Frees memory</li>
    </ul>

    <h3>Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $db->query("SELECT * FROM #__users LIMIT 10");

if ($result) {
    echo "Rows: " . $result->num_rows();

    while ($row = $result->fetch_assoc()) {
        echo $row['username'];
    }

    $result->finalize();
}</code></pre>

    <h2>Error Handling</h2>
    <p>Check for errors after database operations using these methods:</p>

    <h4 class="mt-4">hasError()</h4>
    <p>Returns <code>true</code> if the last operation generated an error.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function hasError(): bool</code></pre>

    <h4 class="mt-4">getLastError()</h4>
    <p>Returns the last error message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function getLastError(): string</code></pre>

    <h3>Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $db->query("SELECT * FROM #__users");

if ($db->hasError()) {
    echo "Error: " . $db->getLastError();
    error_log("DB error: " . $db->getLastError());
} else {
    // Process results
}</code></pre>
</div>
