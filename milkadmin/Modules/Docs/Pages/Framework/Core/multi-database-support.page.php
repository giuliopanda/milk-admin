<?php
namespace Modules\Docs\Pages;
/**
 * @title Multi-Database Support
 * @guide framework
 * @order 
 * @tags multi-database, MySQL, SQLite, PostgreSQL, database-abstraction, query-conversion, connection-management, database-types, MySQLResult, unified-interface, database-connections, result-standardization
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Multi-Database Support</h1>
    <p>The MilkCore framework supports multiple database connections to MySQL and SQLite through a unified interface with automatic query conversion.</p>

    <p>By default, the framework is configured to use two databases (<code>db</code> and <code>db2</code>) during installation. You can add unlimited additional connections via configuration or at runtime.</p>

    <h2>DatabaseManager - API Reference</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Method</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>connection(?string $name = null)</code></td>
                <td>Get a database connection (default: 'db')</td>
            </tr>
            <tr>
                <td><code>addConnection(string $name, array $config, bool $replace = false)</code></td>
                <td>Add a connection at runtime</td>
            </tr>
            <tr>
                <td><code>getAvailableConnections()</code></td>
                <td>List all available connections</td>
            </tr>
            <tr>
                <td><code>hasConnection(string $name)</code></td>
                <td>Check if a connection exists</td>
            </tr>
            <tr>
                <td><code>getConnectionConfig(string $name)</code></td>
                <td>Get connection configuration</td>
            </tr>
            <tr>
                <td><code>disconnect(string $name)</code></td>
                <td>Disconnect a specific connection</td>
            </tr>
            <tr>
                <td><code>reconnect(string $name)</code></td>
                <td>Reconnect a connection</td>
            </tr>
            <tr>
                <td><code>removeConnection(string $name)</code></td>
                <td>Remove a connection entirely</td>
            </tr>
        </tbody>
    </table>

    <h2>Basic Usage</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
use App\DatabaseManager;

// Default connection
$db = DatabaseManager::connection();

// Named connections
$analytics = DatabaseManager::connection('analytics');
$logging = DatabaseManager::connection('logging');
    </code></pre>

    <h2>Configuration</h2>

    <h3>Numbered Connections (db, db2, db3, ...)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// config.php

// Primary connection (db)
Config::set('db_type', 'mysql');
Config::set('connect_ip', 'localhost');
Config::set('connect_login', 'root');
Config::set('connect_pass', 'password');
Config::set('connect_dbname', 'main_db');
Config::set('prefix', 'app_');

// Secondary connection (db2)
Config::set('db_type2', 'mysql');
Config::set('connect_ip2', '192.168.1.100');
Config::set('connect_login2', 'analytics_user');
Config::set('connect_pass2', 'secret');
Config::set('connect_dbname2', 'analytics');
Config::set('prefix2', 'analytics_');

// Third connection (db3) - SQLite
Config::set('db_type3', 'sqlite');
Config::set('connect_dbname3', '/var/db/cache.db');
Config::set('prefix3', 'cache_');
    </code></pre>

    <h3>Named Connections</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// config.php

Config::set('db_connections', [
    'analytics' => [
        'type' => 'mysql',
        'host' => 'analytics.db.example.com',
        'username' => 'analytics_ro',
        'password' => 'readonly123',
        'database' => 'analytics_db',
        'prefix' => 'an_'
    ],
    'logs' => [
        'type' => 'sqlite',
        'database' => '/var/log/app.db',
        'prefix' => 'log_'
    ]
]);
    </code></pre>

    <h3>Runtime Registration</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
DatabaseManager::addConnection('external_api', [
    'type' => 'mysql',
    'host' => 'api.external.com',
    'username' => 'api_user',
    'password' => 'api_pass',
    'database' => 'api_data',
    'prefix' => 'ext_'
]);

$external = DatabaseManager::connection('external_api');
$data = $external->getResults("SELECT * FROM api_table");
    </code></pre>

    <h2>Access via Get Class</h2>
    <p>Database connections can also be obtained through the Get class:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Primary connection
$db = Get::db();

// Secondary connection
$db2 = Get::db2();

// Any named connection
$analytics = Get::dbConnection('analytics');
$logs = Get::dbConnection('logs');
    </code></pre>
</div>
