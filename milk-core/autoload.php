<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Framework Bootstrap and Autoloader
 * 
 * This file initializes the MilkCore framework and sets up the autoloader.
 * It must be included after defining the MILK_DIR constant.
 * 
 * @package     MilkCore
 * 
 * @example
 * // In your application's entry point (e.g., index.php):
 * <?php
 * // Define the application root directory
 * define('MILK_DIR', __DIR__);
 * 
 * // Include the framework bootstrap file
 * require __DIR__ . '/milk-core/autoload.php';
 * 
 * // Load application modules (optional)
 * // \MilkCore\Get::load_modules();
 * 
 * // Your application code here
 */

 require __DIR__ . '/functions.php';
//
require __DIR__ . '/autoload/autoload-logger.class.php';
require __DIR__ . '/autoload/file-analyzer.class.php';
require __DIR__ . '/autoload/lazy-autoload.class.php';
require __DIR__ . '/autoload/autoload-rebuild.class.php';
//


if (is_file(MILK_DIR . '/config.php')) {
    require MILK_DIR . '/config.php';
} else if (is_file(MILK_DIR . '/../config.php')) {
    // I upload the config to a top folder for protection
    require MILK_DIR . '/../config.php';
}
require __DIR__ . '/setup.php';

/**
 * Sets default user permissions
 * 
 * This configuration sets up the default permission set for the framework.
 * The '_user' role is a special role that doesn't belong to any permission group
 * and won't appear in the available permission sets in the Auth system.
 * 
 * Defaultclea permissions:
 * - is_admin: true - Grants administrative privileges by default
 * - is_guest: false - Guest access is disabled by default
 * 
 * Note: Any permission not explicitly set will default to false.
 */
Permissions::set('_user', ['is_admin' => 'Is Admin']);
     