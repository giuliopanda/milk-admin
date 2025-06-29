<?php
namespace MilkCore;
/**
 * PHP page for managing CLI calls.
 * This is called from php cli.php command_name arg1 arg2 arg3
 * Functions are registered inside 
 *  Cli::set('command_name', 'function_to_call');
 */

define('MILK_DIR', __DIR__);
require __DIR__ . '/milk-core/autoload.php';
Get::load_modules();
Hooks::run('cli-init'); 

/**
 * If a last error is set, I print it to the monitor
 * At the moment not used!
 * @Todo Verify if it is used as an idea or not.
 */
Hooks::set('set_last_error', function($error, $from) {
    Cli::error($from.": ".$error);
    return $error;
});


if (Cli::is_cli()) {

    if (!Cli::run($argv)) {
        if (count($argv) > 1) {
            $error = (Cli::$last_error != '') ? Cli::$last_error : "The function is not registered or is not callable.";
            Cli::error($error);
        }
        Cli::echo("    Registered cli command:");
        foreach (Cli::get_all_fn() as $name) {
           Cli::echo("\t - ".$name);
        }
    }
}

// Pulizia finale
Hooks::run('end-cli');
Settings::save();
Get::db()->close();
Get::db2()->close();
