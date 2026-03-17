<?php
declare(strict_types=1);

/**
 * Bootstrap minimo per PHPStan.
 * Evita side effects del bootstrap applicativo e definisce solo il necessario
 * per analizzare il codice in App.
 */

$projectRoot = __DIR__;

if (!defined('MILK_DIR')) {
    define('MILK_DIR', $projectRoot);
}

if (!defined('LOCAL_DIR')) {
    define('LOCAL_DIR', $projectRoot);
}

if (!defined('STORAGE_DIR')) {
    define('STORAGE_DIR', $projectRoot . '/storage');
}

if (!defined('THEME_DIR')) {
    define('THEME_DIR', $projectRoot . '/Theme');
}

if (!defined('THEME_URL')) {
    define('THEME_URL', '/Theme');
}

if (!defined('NEW_VERSION')) {
    define('NEW_VERSION', false);
}

spl_autoload_register(static function (string $class) use ($projectRoot): void {
    $relative = str_replace('\\', '/', $class) . '.php';
    $path = $projectRoot . '/' . $relative;
    if (is_file($path)) {
        require_once $path;
    }
});

require_once $projectRoot . '/App/functions.php';
require_once $projectRoot . '/App/NamespacedFunctionAliases.php';

if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class, false)) {
    $vendorAutoload = dirname($projectRoot) . '/vendor/autoload.php';
    if (is_file($vendorAutoload)) {
        require_once $vendorAutoload;
    }
}

if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class, false)) {
    $phpMailerStub = $projectRoot . '/App/PsalmStubs/PHPMailer.php';
    if (is_file($phpMailerStub)) {
        require_once $phpMailerStub;
    }
}
