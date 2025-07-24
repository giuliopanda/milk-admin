<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Multi-Database Support
 * @category Framework
 * @order 
 * @tags multi-database, MySQL, SQLite, PostgreSQL, database-abstraction, query-conversion, connection-management, database-types, MySQLResult, unified-interface, database-connections, result-standardization
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Multi-Database Support Documentation</h1>
    <p>The MilkCore framework provides comprehensive support for multiple database types through a unified interface. The system allows you to work with MySQL, SQLite, and (in the future) PostgreSQL databases using the same API, with automatic query conversion and standardized result handling.</p>
    
    <h2>Introduction</h2>
    <p>The multi-database architecture in MilkCore consists of three main components:</p>
    <ul>
        <li><strong>Database Connections</strong>: Support for primary and secondary database connections</li>
        <li><strong>Result Standardization</strong>: 
        Unified result handling through the <code>MySQLResult</code> wrapper</li>
        <li><strong>Query Conversion</strong>: Automatic translation between different SQL dialects</li>
    </ul>

    <h2>Database Connections</h2>
    
    <h4>Primary Database Connection</h4>
    <p>The primary database connection is accessed through <code>Get::db()</code> and is typically used for configuration and main application data.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Get primary database connection
$db = Get::db();

// The connection type is determined by configuration
// 'db_type' can be 'mysql' or 'sqlite'
$users = $db->get_results("SELECT * FROM users WHERE active = 1");
    </code></pre>

    <h4>Secondary Database Connection</h4>
    <p>The secondary database connection is accessed through <code>Get::db2()</code> and can be used for separate data storage, analytics, or external data sources.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Get secondary database connection
$db2 = Get::db2();

// Secondary database can be of a different type
// 'db_type2' can be configured independently
$analytics = $db2->get_results("SELECT * FROM analytics_data WHERE date >= ?", ['2024-01-01']);
    </code></pre>

    <h4>Database Type Detection</h4>
    <p>Each database connection automatically detects its type and adapts its behavior accordingly.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$db = Get::db();

// Check database type
if ($db->type === 'mysql') {
    // MySQL-specific operations
    echo "Using MySQL database";
} elseif ($db->type === 'sqlite') {
    // SQLite-specific operations
    echo "Using SQLite database";
}
    </code></pre>

    <h2>MySQLResult - Standardized Result Handling</h2>
    
    <h4>Overview</h4>
    <p>Query results have a wrapper class to standardize retrieval functions across different database types. This ensures that the code works consistently regardless of the underlying database.</p>
    
    <h4>Core Methods</h4>
    
    <h4>fetch_array()</h4>
    <p>Fetches a result row as an associative array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$result = $db->query("SELECT id, username, email FROM users LIMIT 5");

while ($row = $result->fetch_array()) {
    echo $row['username'] . ' - ' . $row['email'] . "\n";
}
    </code></pre>

    <h4>fetch_assoc()</h4>
    <p>Fetches a result row as an associative array. Alias of fetch_array().</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$result = $db->query("SELECT * FROM users WHERE id = ?", [123]);
$user = $result->fetch_assoc();

if ($user) {
    echo "User: " . $user['username'];
}
    </code></pre>

    <h4>fetch_object()</h4>
    <p>Fetches a result row as an object.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$result = $db->query("SELECT * FROM products WHERE category = ?", ['electronics']);

while ($product = $result->fetch_object()) {
    echo $product->name . ' - $' . $product->price . "\n";
}
    </code></pre>

    <h4>Column Information Methods</h4>
    <p>Access column metadata with SQLite3-like compatibility.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$result = $db->query("SELECT id, username, created_at FROM users LIMIT 1");

// Get column count
$columnCount = $result->num_columns();

// Get column names and types
for ($i = 0; $i < $columnCount; $i++) {
    $columnName = $result->column_name($i);
    $columnType = $result->column_type($i);
    echo "Column $i: $columnName (Type: $columnType)\n";
}
    </code></pre>

    <h4>Result Navigation</h4>
    <p>Navigate through result sets efficiently.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$result = $db->query("SELECT * FROM users ORDER BY id");

// Get total rows
echo "Total rows: " . $result->num_rows() . "\n";

// Reset to first row
$result->reset();

// Jump to specific row
$result->data_seek(5);
$row = $result->fetch_assoc();

// Always finalize when done
$result->finalize();
    </code></pre>

    <h2>Query Conversion System</h2>
    
    <h4>Automatic Conversion</h4>
    <p>The <code>QueryConverter</code> class automatically translates SQL queries between different database dialects. This happens transparently when using the <code>Query</code> class.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// This query works with both MySQL and SQLite
$query = new Query('users');
$query->select(['id', 'username', 'created_at'])
      ->where('status = ?', ['active'])
      ->order('created_at', 'DESC')
      ->limit(0, 10);

// Automatic conversion based on database type
list($sql, $params) = $query->get();

// The SQL is automatically adapted:
// MySQL: SELECT `id`,`username`,`created_at` FROM `users` WHERE (status = ?) ORDER BY `created_at` DESC LIMIT 0,10
// SQLite: SELECT "id","username","created_at" FROM "users" WHERE (status = ?) ORDER BY "created_at" DESC LIMIT 0,10
    </code></pre>

    <h4>Manual Conversion</h4>
    <p>You can also manually convert queries for specific database types.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Original MySQL query
$mysqlQuery = "SELECT `users`.`id`, DATE_FORMAT(`created_at`, '%Y-%m-%d') as `date` FROM `users` LIMIT 10,20";
$params = [];

// Convert to PostgreSQL
$converter = new QueryConverter('postgres');
list($postgresQuery, $postgresParams) = $converter->convert($mysqlQuery, $params);

// Convert to SQLite
$converter = new QueryConverter('sqlite');
list($sqliteQuery, $sqliteParams) = $converter->convert($mysqlQuery, $params);
    </code></pre>

    <h4>Database-Specific Queries</h4>
    <p>Some operations require database-specific handling. You can check the database type and execute appropriate queries.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$db = Get::db();

// Database-specific table description
switch ($db->type) {
    case 'mysql':
        $tableInfo = $db->describes('users');
        $fields = $tableInfo['fields'];
        $primaryKeys = $tableInfo['keys'];
        break;
        
    case 'sqlite':
        // SQLite has different metadata handling
        $columns = $db->get_columns('users');
        break;
        
    case 'postgres':
        // Future PostgreSQL implementation
        break;
}
    </code></pre>

    <h2>Configuration Examples</h2>
    
    <h4>MySQL Primary, SQLite Secondary</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// config.php
Config::set('db_type', 'mysql');
Config::set('connect_ip', 'localhost');
Config::set('connect_login', 'username');
Config::set('connect_pass', 'password');
Config::set('connect_dbname', 'main_database');
Config::set('prefix', 'app');

// Secondary database (SQLite for local data)
Config::set('db_type2', 'sqlite');
Config::set('connect_dbname2', '/path/to/local.db');
Config::set('prefix2', 'local');
    </code></pre>

    <h4>Usage with Different Database Types</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Use primary database (MySQL)
$db = Get::db();
$users = $db->get_results("SELECT * FROM app_users WHERE active = 1");

// Use secondary database (SQLite)  
$db2 = Get::db2();
$cache = $db2->get_results("SELECT * FROM local_cache WHERE expires > ?", [time()]);

// Both databases use the same API
foreach ($users as $user) {
    echo "User: " . $user->username . "\n";
}

foreach ($cache as $item) {
    echo "Cache: " . $item->key . "\n";
}
    </code></pre>

    <h2>Advanced Features</h2>
    
    <h4>Transaction Support</h4>
    <p>All database types support transactions with the same interface.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$db = Get::db();

try {
    $db->begin();
    
    $db->insert('orders', [
        'user_id' => 123,
        'total' => 99.99,
        'status' => 'pending'
    ]);
    
    $orderId = $db->insert_id();
    
    $db->insert('order_items', [
        'order_id' => $orderId,
        'product_id' => 456,
        'quantity' => 2
    ]);
    
    $db->commit();
    echo "Order created successfully";
    
} catch (Exception $e) {
    $db->tear_down();
    echo "Transaction failed: " . $e->getMessage();
}
    </code></pre>

    <h4>Memory-Efficient Processing</h4>
    <p>Handle large datasets efficiently across different database types.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Generator-based processing for large result sets
foreach ($db->yield("SELECT * FROM large_table") as $row) {
    // Process one row at a time without loading all into memory
    echo $row->column_name;
}

// Non-buffered queries for MySQL (not available for SQLite)
if ($db->type === 'mysql') {
    foreach ($db->non_buffered_query("massive_table") as $key => $row) {
        // Extremely memory-efficient for very large tables
        processRow($row);
    }
}
    </code></pre>

    <h2>Future PostgreSQL Support</h2>
    <p>The framework is designed to easily accommodate PostgreSQL support in future releases. The query converter already includes PostgreSQL conversion logic, and the result standardization will be extended to cover PostgreSQL-specific features.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Future PostgreSQL configuration
Config::set('db_type', 'postgres');
Config::set('connect_ip', 'localhost');
Config::set('connect_login', 'postgres_user');
Config::set('connect_pass', 'postgres_pass');
Config::set('connect_dbname', 'postgres_db');

// Same API will work with PostgreSQL
$db = Get::db();
$results = $db->get_results("SELECT * FROM users WHERE created_at > ?", ['2024-01-01']);
    </code></pre>

    <h2>Best Practices</h2>
    <ul>
        <li><strong>Use the Query class</strong> whenever possible for automatic conversion</li>
        <li><strong>Check database type</strong> only when absolutely necessary for database-specific features</li>
        <li><strong>Leverage prepared statements</strong> for security across all database types</li>
        <li><strong>Use transactions</strong> for multi-step operations</li>
        <li><strong>Always finalize result sets</strong> when done to free memory</li>
        <li><strong>Test with multiple database types</strong> if your application needs to support them</li>
    </ul>
</div>
