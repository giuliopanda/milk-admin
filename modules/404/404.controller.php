<?php
namespace Modules\fourohfour;
use MilkCore\Hooks; 
use MilkCore\Theme;
use MilkCore\Route;
use MilkCore\Get;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Pagina di errore per la pagina non trovata.
 */
Route::set('404', function() {
    Theme::set('header.title', '404 Page not found');
    Theme::set('container-class', 'container');
    Get::theme_page('404', __DIR__ . '/404.page.php');
});
