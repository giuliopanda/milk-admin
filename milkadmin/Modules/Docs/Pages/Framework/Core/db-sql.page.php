<?php
namespace Modules\Docs\Pages;
/**
 * @title Database (MySQL/SQLite/ArrayDB)
 * @guide framework
 * @order
 * @tags Database, SQL, MySQL, SQLite, ArrayDB, ArrayQuery, ArrayEngine, query, getResults, getRow, getVar, insert, update, delete, save, transaction, DatabaseException
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Database Class</h1>
    <p class="text-muted">Revision: 2025-11-12</p>
    <p>Low-level database access for SQL queries, transactions, and schema inspection. Supports MySQL, SQLite, and ArrayDB with unified interface.</p>

    <div class="alert alert-info">
        <strong><i class="bi bi-info-circle"></i> Higher-Level Abstractions</strong><br>
        For object-oriented data management, use <strong>AbstractModel</strong> (automatic validation, relationships, CRUD).<br>
        For programmatic query building, use the <strong>Query class</strong> (fluent interface).
    </div>

    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle"></i> Error Handling</strong><br>
        Query failures throw <strong>DatabaseException</strong>. Always use try/catch in production.
    </div>

    <h2 class="mt-4">Connections</h2>
    <p>Two database connections available:</p>
    <ul>
        <li><strong>Primary (db)</strong> - System configurations, internal modules</li>
        <li><strong>Secondary (db2)</strong> - Main application data</li>
    </ul>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$db = Get::db();   // Primary
$db2 = Get::db2(); // Secondary
// Both support identical methods</code></pre>

    <h2 class="mt-4">ArrayDB (in-memory)</h2>
    <p>ArrayDB is an in-memory database powered by ArrayQuery. It is accessed through the <code>Get::arrayDb()</code>
        singleton, so the instance is reused within the same request. Tables are populated with <code>addTable()</code>,
        where you can also specify the auto-increment column.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Get;

$db = Get::arrayDb(); // singleton

$db->addTable('users', [
    ['id' => 1, 'name' => 'Mario', 'age' => 30],
    ['id' => 2, 'name' => 'Laura', 'age' => 25],
], 'id');

$adults = $db->getResults('SELECT name FROM users WHERE age > ?', [26]);</code></pre>

    <h3 class="mt-3">Available methods</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Metodo</th>
                <th>Parametri</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>connect($data = null, array $autoIncrementColumns = [])</code></td>
                <td>ArrayEngine|array|null, auto_increment map</td>
                <td>Initializes the in-memory database from ArrayEngine or from a tables array.</td>
            </tr>
            <tr>
                <td><code>addTable(string $tableName, array $data = [], ?string $autoIncrementColumn = null)</code></td>
                <td>name, rows, auto_increment column</td>
                <td>Adds a table and optionally defines the auto-increment column.</td>
            </tr>
            <tr>
                <td><code>query(string $sql, array|null $params = null)</code></td>
                <td>SQL, params</td>
                <td>Executes DML SQL (SELECT/INSERT/UPDATE/DELETE) with <code>?</code> placeholders.</td>
            </tr>
            <tr>
                <td><code>getResults(string $sql, $params = null)</code></td>
                <td>SQL, params</td>
                <td>Returns all rows as an array of objects.</td>
            </tr>
            <tr>
                <td><code>getRow(string $sql, $params = null, int $offset = 0)</code></td>
                <td>SQL, params, offset</td>
                <td>Returns a single row.</td>
            </tr>
            <tr>
                <td><code>getVar(string $sql, $params = null, int $offset = 0)</code></td>
                <td>SQL, params, offset</td>
                <td>Returns the first value of the selected row.</td>
            </tr>
            <tr>
                <td><code>yield(string $sql, $params = null)</code></td>
                <td>SQL, params</td>
                <td>Iterates results with a generator for large datasets.</td>
            </tr>
            <tr>
                <td><code>nonBufferedQuery(string $table, bool $assoc = true)</code></td>
                <td>table, associative</td>
                <td>Iterates records from a table without buffering.</td>
            </tr>
            <tr>
                <td><code>insert(string $table, array $data)</code></td>
                <td>table, data</td>
                <td>Inserts a record and returns the id or false.</td>
            </tr>
            <tr>
                <td><code>update(string $table, array $data, array $where, int $limit = 0)</code></td>
                <td>table, data, where, limit</td>
                <td>Updates records that match the conditions.</td>
            </tr>
            <tr>
                <td><code>delete(string $table, array $where)</code></td>
                <td>table, where</td>
                <td>Deletes records that match the conditions.</td>
            </tr>
            <tr>
                <td><code>save(string $table, array $data, array $where)</code></td>
                <td>table, data, where</td>
                <td>Upsert: updates if it exists, otherwise inserts.</td>
            </tr>
            <tr>
                <td><code>affectedRows()</code></td>
                <td>-</td>
                <td>Number of rows affected by the last operation.</td>
            </tr>
            <tr>
                <td><code>insertId()</code></td>
                <td>-</td>
                <td>Last auto-increment id inserted.</td>
            </tr>
            <tr>
                <td><code>getTables(bool $cache = true)</code></td>
                <td>cache</td>
                <td>List of tables in memory.</td>
            </tr>
            <tr>
                <td><code>getColumns(string $table, bool $force_reload = false)</code></td>
                <td>table, reload</td>
                <td>Columns and metadata based on the current data.</td>
            </tr>
            <tr>
                <td><code>describes(string $table, bool $cache = true)</code></td>
                <td>table, cache</td>
                <td>Full table structure.</td>
            </tr>
            <tr>
                <td><code>showCreateTable(string $table)</code></td>
                <td>table</td>
                <td>Fake SQL string for CREATE TABLE.</td>
            </tr>
            <tr>
                <td><code>dropTable($table)</code></td>
                <td>table</td>
                <td>Removes the table from memory.</td>
            </tr>
            <tr>
                <td><code>renameTable($table, $new_name)</code></td>
                <td>table, new name</td>
                <td>Renames a table in memory.</td>
            </tr>
            <tr>
                <td><code>truncateTable($table)</code></td>
                <td>table</td>
                <td>Empties the table while keeping the structure.</td>
            </tr>
            <tr>
                <td><code>multiQuery(string $sql)</code></td>
                <td>multi SQL</td>
                <td>Executes multiple queries separated by semicolons.</td>
            </tr>
            <tr>
                <td><code>lastQuery()</code></td>
                <td>-</td>
                <td>Last executed query (debug).</td>
            </tr>
            <tr>
                <td><code>getLastError()</code></td>
                <td>-</td>
                <td>Error message from the last operation.</td>
            </tr>
            <tr>
                <td><code>hasError()</code></td>
                <td>-</td>
                <td>True if the last operation failed.</td>
            </tr>
            <tr>
                <td><code>getType()</code></td>
                <td>-</td>
                <td>Returns the connection type: <code>array</code>.</td>
            </tr>
            <tr>
                <td><code>begin(), commit(), tearDown(), close()</code></td>
                <td>-</td>
                <td>Provided for compatibility, no effect on ArrayDB.</td>
            </tr>
        </tbody>
    </table>

    <h2 class="mt-4">Query Execution</h2>

    <h4 class="text-primary mt-4">query(string $sql, array|null $params = null) : MySQLResult|SQLiteResult|bool</h4>
    <p>Executes SQL with prepared statements. Returns result object for SELECT, bool for others.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// SELECT
$result = $db->query("SELECT * FROM #__users WHERE status = ?", ['active']);

// Multiple parameters
$result = $db->query(
    "SELECT * FROM #__users WHERE username = ? AND status = ?",
    ['john', 'active']
);</code></pre>

    <h4 class="text-primary mt-4">getResults(string $sql, array|null $params = null) : array|null</h4>
    <p>Returns all rows as array of objects.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$users = $db->getResults("SELECT * FROM #__users WHERE status = ?", ['active']);
foreach ($users as $user) {
    echo $user->username;
}</code></pre>

    <h4 class="text-primary mt-4">getRow(string $sql, array|null $params = null, int $offset = 0) : object|null</h4>
    <p>Returns single row as object.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$user = $db->getRow("SELECT * FROM #__users WHERE id = ?", [123]);
echo $user?->username;</code></pre>

    <h4 class="text-primary mt-4">getVar(string $sql, array|null $params = null, int $offset = 0) : string|null</h4>
    <p>Returns single value from first column.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$count = $db->getVar("SELECT COUNT(*) FROM #__users");
$username = $db->getVar("SELECT username FROM #__users WHERE id = ?", [123]);</code></pre>

    <h4 class="text-primary mt-4">yield(string $sql, array|null $params = null) : \Generator|null</h4>
    <p>Returns generator to iterate large datasets without loading all in memory.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">foreach ($db->yield("SELECT * FROM #__large_table") as $row) {
    processRow($row);
}</code></pre>

    <h2 class="mt-4">Data Manipulation</h2>

    <h4 class="text-primary mt-4">insert(string $table, array $data) : bool|int</h4>
    <p>Inserts record, returns auto-increment ID or false.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$userId = $db->insert('users', [
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'created_at' => date('Y-m-d H:i:s')
]);

if ($userId) {
    echo "User created with ID: {$userId}";
}</code></pre>

    <h4 class="text-primary mt-4">update(string $table, array $data, array $where, int $limit = 0) : bool</h4>
    <p>Updates records matching WHERE conditions.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$db->update(
    'users',
    ['username' => 'new_username'],
    ['id' => 123]
);
echo "Updated " . $db->affectedRows() . " rows";</code></pre>

    <h4 class="text-primary mt-4">save(string $table, array $data, array $where) : bool|int</h4>
    <p>Upsert - updates if exists, inserts if not.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $db->save(
    'settings',
    ['key' => 'site_title', 'value' => 'My Website'],
    ['key' => 'site_title']
);

if (is_int($result)) echo "Inserted with ID: {$result}";
elseif ($result === true) echo "Updated successfully";</code></pre>

    <h4 class="text-primary mt-4">delete(string $table, array $where) : bool</h4>
    <p>Deletes records matching WHERE conditions.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$db->delete('users', ['id' => 123]);
$db->delete('users', ['status' => 'inactive']);</code></pre>

    <h4 class="text-primary mt-4">affectedRows() : int</h4>
    <p>Returns rows affected by last INSERT/UPDATE/DELETE.</p>

    <h4 class="text-primary mt-4">insertId() : int</h4>
    <p>Returns auto-increment ID from last INSERT.</p>

    <h2 class="mt-4">Schema Inspection</h2>

    <h4 class="text-primary mt-4">getTables(bool $cache = true) : array</h4>
    <p>Returns list of table names.</p>

    <h4 class="text-primary mt-4">getViews(bool $cache = true) : array</h4>
    <p>Returns list of view names.</p>

    <h4 class="text-primary mt-4">getViewDefinition(string $view_name) : string|null</h4>
    <p>Returns SQL definition of a view.</p>

    <h4 class="text-primary mt-4">getColumns(string $table_name, bool $force_reload = false) : array</h4>
    <p>Returns column information with caching.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$columns = $db->getColumns('users');
foreach ($columns as $column) {
    echo $column->Field . " - " . $column->Type;
}</code></pre>

    <h4 class="text-primary mt-4">describes(string $tableName, bool $cache = true) : array</h4>
    <p>Returns complete table structure: fields, primary keys, column details.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$info = $db->describes('users');
$idType = $info['fields']['id'];  // "int(11)"
$primaryKeys = $info['keys'];      // ["id"]

foreach ($info['struct'] as $field => $details) {
    echo $field . ": " . $details->Type;
}</code></pre>

    <h4 class="text-primary mt-4">showCreateTable(string $table_name) : array</h4>
    <p>Returns array ['type' => 'table'|'view', 'sql' => 'CREATE...'].</p>

    <h2 class="mt-4">Table Management</h2>

    <h4 class="text-primary mt-4">dropTable(string $table) : bool</h4>
    <p>Drops table if exists.</p>

    <h4 class="text-primary mt-4">dropView(string $view) : bool</h4>
    <p>Drops view if exists.</p>

    <h4 class="text-primary mt-4">renameTable(string $table_name, string $new_name) : bool</h4>
    <p>Renames table.</p>

    <h4 class="text-primary mt-4">truncateTable(string $table_name) : bool</h4>
    <p>Removes all data and resets auto-increment.</p>

    <h4 class="text-primary mt-4">multiQuery(string $sql) : bool</h4>
    <p>Executes multiple statements separated by semicolons.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$sql = "
    CREATE TABLE #__temp (id INT, name VARCHAR(255));
    INSERT INTO #__temp VALUES (1, 'Test');
";
$db->multiQuery($sql);</code></pre>

    <h2 class="mt-4">Transaction Control</h2>

    <h4 class="text-primary mt-4">begin() : void</h4>
    <p>Starts transaction.</p>

    <h4 class="text-primary mt-4">commit() : void</h4>
    <p>Commits transaction (makes changes permanent).</p>

    <h4 class="text-primary mt-4">tearDown() : void</h4>
    <p>Rolls back transaction (cancels all changes).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">try {
    $db->begin();

    $orderId = $db->insert('orders', [
        'user_id' => 123,
        'total' => 99.50
    ]);

    $db->insert('order_items', [
        'order_id' => $orderId,
        'product_id' => 1,
        'quantity' => 2
    ]);

    $db->commit();
    echo "Order created!";

} catch (Exception $e) {
    $db->tearDown();
    echo "Error: " . $e->getMessage();
}</code></pre>

    <h2 class="mt-4">Error Handling</h2>

    <h4 class="text-primary mt-4">hasError() : bool</h4>
    <p>Returns true if last operation generated error.</p>

    <h4 class="text-primary mt-4">getLastError() : string</h4>
    <p>Returns last error message.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">try {
    $result = $db->query("SELECT * FROM #__users");
} catch (DatabaseException $e) {
    echo "Error: " . $e->getMessage();
    error_log("DB error: " . $db->getLastError());
}</code></pre>

    <h2 class="mt-4">Utility Methods</h2>

    <h4 class="text-primary mt-4">lastQuery() : string</h4>
    <p>Returns last executed SQL query.</p>

    <h4 class="text-primary mt-4">toSql(string $query, array $params) : string</h4>
    <p>Returns query with substituted parameters (debug only, NOT for execution).</p>

    <h4 class="text-primary mt-4">qn(string $val) : string</h4>
    <p>Quotes table/column names for safe use.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$sql = "SELECT {$db->qn('username')} FROM {$db->qn('users')}";</code></pre>

    <h4 class="text-primary mt-4">quote(string $val) : string</h4>
    <p>Quotes values (prefer prepared statements).</p>

    <h2 class="mt-4">Result Objects</h2>
    <p>SELECT queries return result objects with these methods:</p>
    <ul>
        <li><code>fetchArray()</code> / <code>fetchAssoc()</code> - Next row as array</li>
        <li><code>fetchObject()</code> - Next row as object</li>
        <li><code>numRows()</code> - Number of rows</li>
        <li><code>numColumns()</code> - Number of columns</li>
        <li><code>reset()</code> - Reset cursor</li>
        <li><code>finalize()</code> - Free memory</li>
    </ul>

    <h2 class="mt-4">Table Prefix</h2>
    <p>Use <code>#__</code> placeholder - automatically replaced with configured prefix:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// If prefix is 'wp', becomes: SELECT * FROM wp_users
$db->query("SELECT * FROM #__users");</code></pre>

    <h2 class="mt-4">Examples</h2>

    <h4 class="mt-4">Complete CRUD Transaction</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Exceptions\DatabaseException;

try {
    $db->begin();

    // Create user
    $userId = $db->insert('users', [
        'username' => 'john_doe',
        'email' => 'john@example.com'
    ]);

    // Create profile
    $db->insert('profiles', [
        'user_id' => $userId,
        'bio' => 'Hello world'
    ]);

    // Update status
    $db->update('users', ['status' => 'active'], ['id' => $userId]);

    $db->commit();
    echo "Success!";

} catch (DatabaseException $e) {
    $db->tearDown();
    error_log("Transaction failed: " . $e->getMessage());
}</code></pre>

    <h4 class="mt-4">Schema Inspection</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// List all tables and their columns
$tables = $db->getTables();
foreach ($tables as $table) {
    echo "Table: $table\n";

    $columns = $db->getColumns($table);
    foreach ($columns as $col) {
        echo "  - {$col->Field} ({$col->Type})\n";
    }
}</code></pre>

    <h4 class="mt-4">Streaming Large Dataset</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Process millions of rows efficiently
foreach ($db->yield("SELECT * FROM #__logs WHERE year = ?", [2024]) as $log) {
    processLog($log);
    // Memory usage stays constant
}</code></pre>
</div>
