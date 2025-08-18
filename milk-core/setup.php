<?php
namespace MilkCore;
use MilkCore\Theme;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Framework Initialization and Session Configuration
 * 
 * This file handles initial framework setup, including session configuration,
 * security settings, and defining system constants.
 *
 * @package     MilkCore
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
ini_set('session.cookie_httponly', true);
ini_set('session.cookie_samesite', 'Strict');

session_start();


if (Config::get('debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

 /**
  * Directory of the theme
  * @global string THEME_DIR
  */
define('THEME_DIR', realpath(MILK_DIR.'/'.(Config::get('theme_dir', 'theme'))));

/**
 * Base URL of the application
 * @global string BASE_URL
 */
define('BASE_URL',  Route::url());

/**
 * URL to the theme directory
 * @global string THEME_URL
 */
define('THEME_URL', Route::url()."/".(Config::get('theme_dir') ?? 'theme'));

/**
 * Directory of the storage
 * @global string STORAGE_DIR
 */
define('STORAGE_DIR', realpath(MILK_DIR.'/'.Config::get('storage_dir', 'storage')));

/**
 * Directory root of the framework
 * @global string MILK_DIR
 */

/**
 * Version identifier in format AAmmXX (YearMonth + sequence)
 * @global string NEW_VERSION
 */
define('NEW_VERSION', '250801');

Token::config(Config::get('secret_key'), Config::get('token_key'));

Theme::set('site.title', Config::get('site-title', 'Milk Admin'));

if (Config::get('debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

Hooks::set('init', function() {
    /**
     * Cancel custom headers
     * @global string NEW_VERSION
     */
   Route::get_header_data();
});

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
    $data = Route::get_session_data();
    // Check if there is a success message
    if (isset($data['alert-success'])) {
        MessagesHandler::add_success($data['alert-success']);
    }
    
    // Check if there is an error message
    if (isset($data['alert-error'])) {
        MessagesHandler::add_error($data['alert-error']);
    }
    if (isset($data['message-handler'])) {
        foreach ($data['message-handler'] as $key => $message) {
            MessagesHandler::add_error($message, $key);
        }
    }
});
// Apply CSRF protection automatically in the init hook because the secret key is changed at login!
Hooks::set('init', function() {
    CSRFProtection::validate();
}, 5);