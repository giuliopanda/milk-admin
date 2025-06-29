<?php
namespace MilkCore;
use MilkCore\Hooks;
/**
 * PHP page called every minute to execute the cron
 */

define('MILK_DIR', __DIR__);
require __DIR__ . '/milk-core/autoload.php';
Get::load_modules();
Hooks::run('jobs-init'); 

if (Cli::is_cli()) {
   Hooks::run('jobs-start');
}