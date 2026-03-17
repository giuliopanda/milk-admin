<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Session Security Configuration
 */
$base_url  = (string) Config::get('base_url', '');
$base_path = (string) parse_url($base_url, PHP_URL_PATH) ?: '/';
$base_path = '/' . trim($base_path, '/') . '/';
$base_path = $base_path === '//' ? '/' : $base_path;

$default_session_name = 'MKSESSID_' . substr(
    hash('sha256', $base_url !== '' ? $base_url : MILK_DIR),
    0,
    12
);

$session_cookie_name = preg_replace(
    '/[^A-Za-z0-9]/',
    '',
    (string) Config::get('session_cookie_name', $default_session_name)
) ?: $default_session_name;

$session_cookie_path = (string) Config::get('session_cookie_path', $base_path);
$session_cookie_path = '/' . trim($session_cookie_path, '/') . '/';
$session_cookie_path = $session_cookie_path === '//' ? '/' : $session_cookie_path;

$is_https = strtolower((string) parse_url($base_url, PHP_URL_SCHEME)) === 'https';

if ($is_https) {
    ini_set('session.cookie_secure', '1');
}

if (!\App\Cli::isCli()) {

    $startSession = static function () use ($session_cookie_name, $session_cookie_path, $is_https) {

        session_name($session_cookie_name);

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => $session_cookie_path,
                'secure'   => $is_https,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        } else {
            session_set_cookie_params(0, $session_cookie_path, '', $is_https, true);
        }

        ini_set('session.cookie_path', $session_cookie_path);
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');

        session_start();
    };

    if (session_status() === PHP_SESSION_ACTIVE) {

        if (session_name() !== $session_cookie_name) {

            $oldName = session_name();
            $data    = $_SESSION ?? [];
            session_write_close();
            $startSession();
            if ($data) {
                $_SESSION = array_replace($_SESSION ?? [], $data);
            }
            setcookie($oldName, '', time() - 3600, '/');
        }

    } elseif (!session_id()) {
        $startSession();
    }
}

/**
 * Framework constants
 */

define('THEME_DIR', realpath(MILK_DIR . '/' . (Config::get('theme_dir', 'Theme'))));

if (!defined('BASE_URL')) {
    define('BASE_URL', Route::url());
}

define('THEME_URL', Route::url() . "/" . (Config::get('theme_dir') ?? 'Theme'));

define('STORAGE_DIR', realpath(LOCAL_DIR . '/' . Config::get('storage_dir', 'storage')));

define('NEW_VERSION', '0.9.7');


$current_version = Config::get('version');
$normalized_version = Version::normalize($current_version);

if ($normalized_version !== null && $normalized_version !== $current_version) {
    Config::set('version', $normalized_version);
}

Token::config(Config::get('secret_key'), Config::get('token_key'));
Theme::set('site.title', Config::get('site-title', 'Milk Admin'));


/**
 * Init Hook
 */

Hooks::set('init', function() {

    Route::getHeaderData();
    $data = Route::getSessionData();

    if (isset($data['alert-success'])) {
        MessagesHandler::addSuccess($data['alert-success']);
    }

    if (isset($data['alert-error'])) {
        MessagesHandler::addError($data['alert-error']);
    }

    if (isset($data['message-handler'])) {
        foreach ($data['message-handler'] as $key => $message) {
            MessagesHandler::addError($message, $key);
        }
    }

    CSRFProtection::validate();

}, 5);


/**
 * JS Translations
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
