<?php
/**
 * Configuration for installation DON'T EDIT!
 * 
 * Install istruction:
 * 1. Create a database 
 * 2. Open browser and go to the site home page
 * 3. Follow the instructions. 
 */
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


$scheme = 'http'; 
if ((!empty($_SERVER['REQUEST_SCHEME']) && strtolower($_SERVER['REQUEST_SCHEME']) === 'https') ||
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
    (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] === 'on')) {
    $scheme = 'https';
}

// Proxy/load balancer header (AWS ELB, Cloudflare, Nginx, etc.)
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $scheme = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
}

// Cloudflare specific header
if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
    $visitor = json_decode($_SERVER['HTTP_CF_VISITOR']);
    if (isset($visitor->scheme)) {
        $scheme = strtolower($visitor->scheme);
    }
}

$conf['base_url'] = $scheme . '://' . $_SERVER['HTTP_HOST'] .$temp;

// home page
$conf['home_page'] = '?page=install';
$conf['page_not_found'] = '404';
$conf['debug'] = true;

// default title
$conf['site-title'] = 'MilkAdmin - Framework';

\App\Config::setAll($conf);