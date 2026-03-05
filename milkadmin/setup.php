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
$base_url = (string) Config::get('base_url', '');
$base_path = (string) parse_url($base_url, PHP_URL_PATH);
if ($base_path === '') {
    $base_path = '/';
}
$base_path = '/' . ltrim(trim($base_path), '/');
if ($base_path !== '/') {
    $base_path = rtrim($base_path, '/') . '/';
}

$default_session_name = 'MKSESSID_' . substr(hash('sha256', ($base_url !== '' ? $base_url : MILK_DIR)), 0, 12);
$session_cookie_name = (string) Config::get('session_cookie_name', $default_session_name);
$session_cookie_name = preg_replace('/[^A-Za-z0-9]/', '', $session_cookie_name);
if ($session_cookie_name === '') {
    $session_cookie_name = $default_session_name;
}

$session_cookie_path = (string) Config::get('session_cookie_path', $base_path);
$session_cookie_path = '/' . ltrim(trim($session_cookie_path), '/');
if ($session_cookie_path !== '/') {
    $session_cookie_path = rtrim($session_cookie_path, '/') . '/';
}

$is_https_base_url = (strtolower((string) parse_url($base_url, PHP_URL_SCHEME)) === 'https');
if ($is_https_base_url) {
    ini_set('session.cookie_secure', '1');
}

if (!session_id() && !\App\Cli::isCli()) {
    session_name($session_cookie_name);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $session_cookie_path,
            'secure' => $is_https_base_url,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    } else {
        session_set_cookie_params(0, $session_cookie_path, '', $is_https_base_url, true);
    }

    ini_set('session.cookie_path', $session_cookie_path);
    ini_set('session.cookie_httponly', '1');
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
 * Version identifier in semver format (1.x.y).
 * Legacy numeric versions are normalized to "0.<value>".
 *
 * @global string NEW_VERSION
 */
define('NEW_VERSION', '0.9.5');

$current_version = Config::get('version');
$normalized_version = Version::normalize($current_version);
if ($normalized_version !== null && $normalized_version !== $current_version) {
    Config::set('version', $normalized_version);
}

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
