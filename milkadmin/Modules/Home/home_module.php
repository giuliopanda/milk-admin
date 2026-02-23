<?php
namespace Modules\Home;

use App\{HttpClient, Route, Theme, Response, Get};

/**
 * 
 * @package     Modules
 * @subpackage  home
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @version     1.0.0
 */

!defined('MILK_DIR') && die(); // Avoid direct access

Route::set('home', function() {
    Theme::set('styles', Route::url().'/Modules/Home/Assets/home.css');

    ob_start();
    require_once(Get::dirPath(__DIR__ . '/Assets/welcome.php'));
    $article = ob_get_clean();
   
    Response::themePage('default', __DIR__ . '/Assets/home.page.php', ['main_article' => $article]);

}, '_user.is_authenticated');
