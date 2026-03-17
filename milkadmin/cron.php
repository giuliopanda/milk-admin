<?php
use App\{Cli, Get, Hooks};

/**
 * PHP page called every minute to execute the cron
 */

define('MILK_DIR', __DIR__);
define('LOCAL_DIR', realpath(__DIR__ . '/../milkadmin_local'));
require __DIR__ . '/autoload.php';
Get::loadModules();
Hooks::run('jobs-init'); 

if (Cli::isCli()) {
   Hooks::run('jobs-start');
}