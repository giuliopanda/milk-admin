<?php
namespace Modules\deny;
use MilkCore\Hooks; 
use MilkCore\Theme;
use MilkCore\Route;
use MilkCore\Get;
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
    Get::theme_page('default', __DIR__ . '/deny.page.php');
});
