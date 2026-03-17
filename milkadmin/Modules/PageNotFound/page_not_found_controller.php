<?php
namespace Modules\PageNotFound;

use App\{Response, Route, Theme};

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Pagina di errore per la pagina non trovata.
 */
Route::set('404', function() {
    Theme::set('header.title', '404 Page not found');
    Theme::set('container-class', 'container');
    Response::themePage('404', __DIR__ . '/404_page.php');
});
