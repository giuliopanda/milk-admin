<?php
namespace Modules\Deny;

use App\{Hooks, Response, Route, Theme};

 
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Error page for denied access.
 * 
 * @package     Modules
 * @subpackage  Deny
 * 
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @version     1.0.0
 */

Route::set('deny', function() {
    // Auth module redirect to login if not logged in
    Hooks::run('deny');
    Theme::set('header.title', 'Denied');
    Theme::set('container-class', 'container');
    Response::themePage('default', __DIR__ . '/deny.page.php');
});
