<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Framework Initialization and Session Configuration
 * 
 * This file handles initial framework setup, including session configuration,
 * security settings, and defining system constants.
 *
 * @package     App
 */

/**
 * Session Security Configuration
 * 
 * Sets secure session parameters to protect against common attacks:
 * - cookie_secure: Ensures cookies are only sent over HTTPS
 * - cookie_httponly: Prevents JavaScript access to session cookies
 * - cookie_samesite: Prevents CSRF attacks by restricting cookie sending
 */

 // I don't allow access to the cookie from js
if (substr(Config::get('base_url'), 0, 5) == 'https') {
    ini_set('session.cookie_secure', true);
}

if (!session_id()) {
    ini_set('session.cookie_httponly', true);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

if (Config::get('debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

 /**
  * Directory of the theme
  * @global string THEME_DIR
  */
define('THEME_DIR', realpath(MILK_DIR.'/'.(Config::get('theme_dir', 'Theme'))));

/**
 * Base URL of the application
 * @global string BASE_URL
 */
if (!defined('BASE_URL')) {
    define('BASE_URL',  Route::url());
}

/**
 * URL to the theme directory
 * @global string THEME_URL
 */
define('THEME_URL', Route::url()."/".(Config::get('theme_dir') ?? 'Theme'));

/**
 * Directory of the storage
 * @global string STORAGE_DIR
 */
define('STORAGE_DIR', realpath(LOCAL_DIR.'/'.Config::get('storage_dir', 'storage')));

/**
 * Directory root of the framework
 * @global string MILK_DIR
 */

/**
 * Version identifier in format AAmmXX (YearMonth + sequence)
 * @global string NEW_VERSION
 */
define('NEW_VERSION', '251100');

Token::config(Config::get('secret_key'), Config::get('token_key'));

Theme::set('site.title', Config::get('site-title', 'Milk Admin'));

if (Config::get('debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}


/**
 * Initialization Hook for Message Handling
 * 
 * This hook retrieves error and success messages from session data and
 * adds them to the MessagesHandler. This allows messages to persist
 * across redirects, which is useful for displaying form submission results.
 * 
 * The messages are set using the redirect_error and redirect_success methods
 * in the Route class.
 */
Hooks::set('init', function() {
    /**
     * Cancel custom headers
     * @global string NEW_VERSION
     */
    Route::getHeaderData();
    //
    $data = Route::getSessionData();
    // Check if there is a success message
    if (isset($data['alert-success'])) {
        MessagesHandler::addSuccess($data['alert-success']);
    }
    
    // Check if there is an error message
    if (isset($data['alert-error'])) {
        MessagesHandler::addError($data['alert-error']);
    }
    if (isset($data['message-handler'])) {
        foreach ($data['message-handler'] as $key => $message) {
            MessagesHandler::addError($message, $key);
        }
    }

    // Apply CSRF protection automatically in the init hook because the secret key is changed at login!
    CSRFProtection::validate();
}, 5);

/**
 * Preparo le traduzioni per il JavaScript
 */
Route::set('translationsjs', function() { 
    // Headers per JavaScript
    header('Content-Type: application/javascript; charset=utf-8');

    // Headers per cache
    $cacheSeconds = 3600;
    header('Cache-Control: public, max-age=' . $cacheSeconds);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheSeconds) . ' GMT');

    echo Lang::generateJs($_REQUEST['g'] ?? '', true);
});