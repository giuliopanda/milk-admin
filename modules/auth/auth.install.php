<?php
namespace Modules\Auth;
use MilkCore\Hooks;
use Modules\Install\Install;
use MilkCore\Route;
/**
 * File for installation of auth module
 */
/*
Hooks::set('install.execute', function($data) {
    // create tables
    Install::execute_sql_file(__DIR__.'/assets/mysql-install.sql');

    $auth_data = [
        'auth_expires_session' => ['value'=>'60*24*30','type'=>'number','comment' => 'Session duration in minutes'],
    ];
    Install::set_config_file('AUTH', $auth_data);

    return $data;
}, 20);


Hooks::set('install.done', function($html) {
    $html .= "Log in with the following credentials:<br>username: <b>admin</b><br>password: <b>admin</b><br>";
    $html .= '<br><a href="'. Route::url(['page' => 'auth']).'">Go to login</a><br>';
    $html .= 'After logging in, create a new super admin user and log in with the new user.<br>Once you log in with the new user, <b>don\'t forget to remove the admin user!</b><br><br>';
    return $html;
}, 20);

*/