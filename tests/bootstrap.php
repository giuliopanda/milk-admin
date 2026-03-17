<?php
/**
 * Bootstrap file per PHPUnit tests
 *
 * Questo file viene caricato prima di ogni test suite
 */

// Imposta il livello di error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Directory root del progetto
define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// Carica l'applicazione MilkAdmin
require_once PROJECT_ROOT . '/public_html/milkadmin.php';
require_once MILK_DIR . '/autoload.php';

// Run unit tests against an isolated SQLite database file inside STORAGE_DIR.
$phpunitSqliteFilename = 'milkadmin-phpunit-' . getmypid() . '.sqlite';
$phpunitSqlitePath = PROJECT_ROOT . '/milkadmin_local/storage/' . $phpunitSqliteFilename;
if (is_file($phpunitSqlitePath)) {
    @unlink($phpunitSqlitePath);
}
if (is_file($phpunitSqlitePath . '-wal')) {
    @unlink($phpunitSqlitePath . '-wal');
}
if (is_file($phpunitSqlitePath . '-shm')) {
    @unlink($phpunitSqlitePath . '-shm');
}

\App\Config::set('db_type', 'sqlite');
\App\Config::set('connect_dbname', $phpunitSqliteFilename);
\App\DatabaseManager::reset();
\App\Get::resetDatabaseConnections();

register_shutdown_function(static function () use ($phpunitSqlitePath): void {
    if (is_file($phpunitSqlitePath)) {
        unlink($phpunitSqlitePath);
    }
    if (is_file($phpunitSqlitePath . '-wal')) {
        unlink($phpunitSqlitePath . '-wal');
    }
    if (is_file($phpunitSqlitePath . '-shm')) {
        unlink($phpunitSqlitePath . '-shm');
    }
});

// Carica i moduli
\App\Get::loadModules();

// Output buffering per i test
if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

echo "PHPUnit Bootstrap completed\n";
echo "Project root: " . PROJECT_ROOT . "\n";
echo "Milk dir: " . MILK_DIR . "\n";
