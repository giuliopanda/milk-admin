<?php
namespace Modules\fourohfour;
use App\Theme;
use App\Route;
use App\Response;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Pagina di errore per la pagina non trovata.
 */
Route::set('404', function() {
    Theme::set('header.title', '404 Page not found');
    $page = preg_replace('/[^a-zA-Z0-9-_]/', '', $_REQUEST['page']);
    if ($page == '404') {
        Response::render( __DIR__ . '/404.page.php', ['success' => false, 'msg' =>_rt('Page not found')]);
    } else {
        Response::render( __DIR__ . '/404.page.php', ['success' => false, 'msg' => sprintf(_rt('I didn\'t find the page: %s'), $page)]);
  
       
    }
});
