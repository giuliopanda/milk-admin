<?php
namespace MilkCore;


define('MILK_DIR', __DIR__);
require __DIR__ . '/milk-core/autoload.php';

Get::load_modules();


// find the page
$home = Config::get('home_page', '?page=home');
if (!isset($_REQUEST['page'])) {
    // redirect to home
    Route::redirect($home);
}

Hooks::run('init'); 

Lang::load_ini_file(MILK_DIR . '/lang/'.Config::get('lang', '').'.ini');
Lang::load_ini_file(MILK_DIR . '/lang/'.Config::get('lang', '').'.adding.ini');


require_once THEME_DIR.'/template.class.php';

// accetta solo a-z0-9A-Z -_ come caratteri per il nome della pagina
$page = preg_replace('/[^a-zA-Z0-9-_]/', '', $_REQUEST['page']);
if (empty($page)) $page = '404';
if (!Route::run($page)) {
    $page_not_found = Config::get('page_not_found', '404');
    Route::run($page_not_found);
}

Hooks::run('end-page'); 

Settings::save();
Get::db()->close();
Get::db2()->close();