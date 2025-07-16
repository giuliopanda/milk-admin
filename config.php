<?php
/**
 * Configuration for installation DON'T EDIT!
 * 
 * Install istruction:
 * 1. Create a database 
 * 2. Open browser and go to the site home page
 * 3. Follow the instructions. 
 */
use MilkCore\Config;
!defined('MILK_DIR') && die(); // Avoid direct access

// base url
$temp = $_SERVER['REQUEST_URI'];
if (strpos($temp, '?') !== false) {
    $temp = substr($temp, 0, strpos($temp, '?'));
}
if (strpos($temp, '.php') !== false) {
    $temp = dirname($temp);
}
if (substr($temp, -1) != '/') {
    $temp .= '/';
}
$conf['base_url'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] .$temp;

// home page
$conf['home_page'] = '?page=install';
$conf['page_not_found'] = '404';
$conf['debug'] = true;

// default title
$conf['site-title'] = 'MilkAdmin - Framework';

Config::set_all($conf);