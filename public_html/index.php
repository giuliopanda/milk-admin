<?php
use App\{Config, Get, Hooks, Lang, Route, Settings};

require 'milkadmin.php';

require MILK_DIR . '/autoload.php';
Get::loadModules();

// Find the page
$home = Config::get('home_page', '?page=home');
if (!isset($_REQUEST['page'])) {
    // Redirect to home
    Route::redirect($home);
}

Hooks::run('init');

// Load lang files
Lang::loadPhpFile(MILK_DIR.'/Lang/'.Get::userLocale().'.php');

require_once THEME_DIR.'/Template.php';

// Accept only a-z0-9A-Z -_ as characters for the page name
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