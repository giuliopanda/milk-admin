<?php
namespace App;

use App\Database\{MySql, SQLite};
use App\Exceptions\DatabaseException;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Database Connection Manager
 *
 * Manages multiple database connections with lazy loading and connection pooling.
 * Supports unlimited database connections configured via Config or runtime.
 *
 * Features:
 * - Multiple named connections (db, db2, custom names)
 * - Lazy loading (connections created only when needed)
 * - Connection pooling and reuse
 * - Auto-discovery from Config
 * - Runtime connection registration
 * - MySQL and SQLite support
 *
 * @example
 * ```php
 * // Get default connection (db)
 * $db = DatabaseManager::connection();
 *
 * // Get named connection
 * $db2 = DatabaseManager::connection('db2');
 *
 * // Add custom connection at runtime
 * DatabaseManager::addConnection('analytics', [
 *     'type' => 'mysql',
 *     'host' => 'analytics.example.com',
 *     'username' => 'reader',
 *     'password' => 'secret',
 *     'database' => 'analytics_db',
 *     'prefix' => 'analytics_'
 * ]);
 *
 * $analytics = DatabaseManager::connection('analytics');
 *
 * // List all available connections
 * $connections = DatabaseManager::getAvailableConnections();
 * ```
 *
 * @package App
 */
class DatabaseManager
{
    /**
     * Active database connection instances
     *
     * @var array<string, MySql|SQLite>
     */
    private static array $connections = [];

    /**
     * Connection configurations
     *
     * @var array<string, array>
     */
    private static array $configurations = [];

    /**
     * Default connection name
     *
     * @var string
     */
    private static string $defaultConnection = 'db';

    /**
     * Flag to track if auto-discovery has been done
     *
     * @var bool
     */
    private static bool $discoveryDone = false;

    /**
     * Get a database connection by name
     *
     * Returns an existing connection or creates a new one if it doesn't exist.
     * Auto-discovers connections from Config on first call.
     *
     * @param string|null $name Connection name (null = default connection)
     * @return MySql|SQLite|null Database connection instance
     * @throws DatabaseException If connection fails or configuration not found
     */
    public static function connection(?string $name = null): MySql|SQLite|null
    {
        // Auto-discover connections from Config
        if (!self::$discoveryDone) {
            self::discoverConnections();
            self::$discoveryDone = true;
        }

        // Use default connection if no name provided
        $name = $name ?? self::$defaultConnection;

        // Return existing connection if already established
        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        // Create new connection
        return self::createConnection($name);
    }

    /**
     * Auto-discover database connections from Config
     *
     * Looks for connection configurations in Config and registers them.
     * Searches for patterns: db, db2, db3, ... and custom_connection_name
     *
     * @return void
     */
    private static function discoverConnections(): void
    {
        // Discover numbered connections (db, db2, db3, ...)
        $index = 1;
        while (true) {
            $suffix = $index === 1 ? '' : (string)$index;
            $configKey = 'db_type' . $suffix;

            // Stop if configuration doesn't exist
            if (Config::get($configKey) === null) {
                break;
            }

            $connectionName = 'db' . ($index === 1 ? '' : $index);

            self::$configurations[$connectionName] = [
                'type' => Config::get('db_type' . $suffix, 'mysql'),
                'prefix' => Config::get('prefix' . $suffix, ''),
                'host' => Config::get('connect_ip' . $suffix),
                'username' => Config::get('connect_login' . $suffix),
                'password' => Config::get('connect_pass' . $suffix),
                'database' => Config::get('connect_dbname' . $suffix),
            ];

            $index++;
        }

        // Check for custom named connections in Config
        // Format: db_connections.custom_name.type, db_connections.custom_name.host, etc.
        $customConnections = Config::get('db_connections', []);
        if (is_array($customConnections)) {
            foreach ($customConnections as $name => $config) {
                if (!isset(self::$configurations[$name])) {
                    self::$configurations[$name] = $config;
                }
            }
        }
    }

    /**
     * Add a database connection configuration
     *
     * Allows adding custom connections at runtime without modifying Config files.
     *
     * @param string $name Connection name
     * @param array $config Connection configuration
     * @param bool $replace If true, replaces existing configuration
     * @throws \InvalidArgumentException If connection exists and $replace is false
     *
     * @example
     * ```php
     * DatabaseManager::addConnection('analytics', [
     *     'type' => 'mysql',
     *     'host' => 'analytics.db.example.com',
     *     'username' => 'analytics_user',
     *     'password' => 'secret123',
     *     'database' => 'analytics',
     *     'prefix' => 'an_'
     * ]);
     * ```
     */
    public static function addConnection(string $name, array $config, bool $replace = false): void
    {
        if (isset(self::$configurations[$name]) && !$replace) {
            throw new \InvalidArgumentException("Connection '{$name}' already exists. Use \$replace=true to override.");
        }

        // Validate required fields
        $required = ['type'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new \InvalidArgumentException("Missing required field '{$field}' in connection configuration.");
            }
        }

        self::$configurations[$name] = $config;

        // Remove existing connection instance to force recreation
        if (isset(self::$connections[$name])) {
            unset(self::$connections[$name]);
        }
    }

    /**
     * Create a new database connection
     *
     * @param string $name Connection name
     * @return MySql|SQLite|null Database connection instance
     * @throws DatabaseException If connection fails or configuration not found
     */
    private static function createConnection(string $name): MySql|SQLite|null
    {
        if (!isset(self::$configurations[$name])) {
            return null;
          //  throw new DatabaseException("Database connection '{$name}' not configured.");
        }

        $config = self::$configurations[$name];
        $type = $config['type'] ?? 'mysql';
        $prefix = $config['prefix'] ?? '';

        try {
            if ($type === 'sqlite') {
                $connection = new SQLite($prefix);
                $database = $config['database'] ?? null;

                if ($database !== null) {
                    $connection->connect($database);
                }
            } else {
                // MySQL connection (default)
                $connection = new MySql($prefix);

                if (isset($config['host'])) {
                    $connection->connect(
                        $config['host'],
                        $config['username'] ?? '',
                        $config['password'] ?? '',
                        $config['database'] ?? ''
                    );
                }
            }

            // Store connection for reuse
            self::$connections[$name] = $connection;

            return $connection;

        } catch (DatabaseException $e) {
            Logs::set('SYSTEM', "Database connection '{$name}' failed: " . $e->getMessage(), 'ERROR');
            throw new DatabaseException(
                "Failed to connect to database '{$name}': " . $e->getMessage(),
                $type,
                $config,
                0,
                $e
            );
        }
    }

    /**
     * Get list of all available connection names
     *
     * @return array<string> List of connection names
     */
    public static function getAvailableConnections(): array
    {
        if (!self::$discoveryDone) {
            self::discoverConnections();
            self::$discoveryDone = true;
        }

        return array_keys(self::$configurations);
    }

    /**
     * Check if a connection is configured
     *
     * @param string $name Connection name
     * @return bool True if connection is configured
     */
    public static function hasConnection(string $name): bool
    {
        if (!self::$discoveryDone) {
            self::discoverConnections();
            self::$discoveryDone = true;
        }

        return isset(self::$configurations[$name]);
    }

    /**
     * Get connection configuration
     *
     * @param string $name Connection name
     * @return array|null Connection configuration or null if not found
     */
    public static function getConnectionConfig(string $name): ?array
    {
        if (!self::$discoveryDone) {
            self::discoverConnections();
            self::$discoveryDone = true;
        }

        return self::$configurations[$name] ?? null;
    }

    /**
     * Set the default connection name
     *
     * @param string $name Connection name
     * @throws \InvalidArgumentException If connection doesn't exist
     */
    public static function setDefaultConnection(string $name): void
    {
        if (!self::hasConnection($name)) {
            throw new \InvalidArgumentException("Cannot set default connection: '{$name}' is not configured.");
        }

        self::$defaultConnection = $name;
    }

    /**
     * Get the default connection name
     *
     * @return string Default connection name
     */
    public static function getDefaultConnection(): string
    {
        return self::$defaultConnection;
    }

    /**
     * Close a specific connection
     *
     * @param string $name Connection name
     * @return void
     */
    public static function disconnect(string $name): void
    {
        if (isset(self::$connections[$name])) {
            // The database classes handle their own cleanup in destructor
            unset(self::$connections[$name]);
        }
    }

    /**
     * Close all active connections
     *
     * Useful for cleanup or testing scenarios.
     *
     * @return void
     */
    public static function disconnectAll(): void
    {
        self::$connections = [];
    }

    /**
     * Reconnect to a database
     *
     * Closes existing connection and creates a new one.
     *
     * @param string $name Connection name
     * @return MySql|SQLite New connection instance
     */
    public static function reconnect(string $name): MySql|SQLite
    {
        self::disconnect($name);
        return self::connection($name);
    }

    /**
     * Remove a connection configuration
     *
     * Disconnects active connection and removes configuration.
     *
     * @param string $name Connection name
     * @return void
     */
    public static function removeConnection(string $name): void
    {
        self::disconnect($name);
        unset(self::$configurations[$name]);
    }

    /**
     * Reset the manager state (for testing)
     *
     * Closes all connections and clears all configurations.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::disconnectAll();
        self::$configurations = [];
        self::$discoveryDone = false;
        self::$defaultConnection = 'db';
    }
}
