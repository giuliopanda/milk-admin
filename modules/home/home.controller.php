<?php
namespace Modules\Home;
use MilkCore\Permissions; 
use MilkCore\Theme;
use MilkCore\Route;
use MilkCore\Get;
use MilkCore\HttpClient;

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
    Theme::set('styles', Route::url().'/modules/home/assets/home.css');
    
    // Download the page from the server
    $response = HttpClient::get('https://www.milkadmin.org/ma32r4c2aa/api.php?page=home/get', ['timeout' => 2]);
    if ($response !== false && $response['status_code'] == 200) {
        $article = $response['body'];   
    } else {
        ob_start();
        require_once(Get::dir_path(__DIR__ . '/assets/welcome.php'));
        $article = ob_get_clean();
    }
    Get::theme_page('default', __DIR__ . '/assets/home.page.php', ['main_article' => $article]);

}, '_user.is_authenticated');
