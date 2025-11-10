<?php

use App\{Cli, Get, Hooks, Settings};

/**
 * PHP page for managing CLI calls.
 * This is called from php milkadmin/cli.php command_name arg1 arg2 arg3
 * Functions are registered inside 
 *  Cli::set('command_name', 'function_to_call');
 */

define('MILK_DIR', __DIR__);
define('LOCAL_DIR', realpath(__DIR__ . '/../milkadmin_local'));
require __DIR__ . '/autoload.php';
Get::loadModules();
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

if (Cli::isCli()) {

    if (!Cli::run($argv)) {
        if (count($argv) > 1) {
            $error = (Cli::$last_error != '') ? Cli::$last_error : "The function is not registered or is not callable.";
            Cli::error($error);
            
            // Try to find similar commands
            $attempted_command = $argv[1];
            $all_commands = Cli::getAllFn();
            $similar_commands = [];
            
            // Function to calculate similarity
            $calculate_similarity = function($str1, $str2) {
                $len1 = strlen($str1);
                $len2 = strlen($str2);
                $distance = levenshtein($str1, $str2);
                $max_len = max($len1, $len2);
                if ($max_len == 0) return 1;
                return 1 - ($distance / $max_len);
            };
            
            // Find commands with similarity > 0.3
            foreach ($all_commands as $command) {
                $similarity = $calculate_similarity(strtolower($attempted_command), strtolower($command));
                if ($similarity > 0.3) {
                    $similar_commands[] = ['command' => $command, 'similarity' => $similarity];
                }
            }
            
            // Sort by similarity (highest first)
            usort($similar_commands, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            // Show similar commands if found
            if (!empty($similar_commands)) {
                Cli::echo("");
                Cli::echo("   \033[1;33mDid you mean:\033[0m");
                Cli::echo("   \033[0;33m─────────────\033[0m");
                
                // Show only top 5 most similar commands
                $count = 0;
                foreach ($similar_commands as $item) {
                    if ($count >= 5) break;
                    
                    if (strpos($item['command'], ':') !== false) {
                        // Module command
                        $parts = explode(':', $item['command'], 2);
                        $module = $parts[0];
                        $command = $parts[1];
                        Cli::echo("     \033[0;36m▸\033[0m \033[1;35m" . $module . ":\033[0m\033[0;37m" . $command . "\033[0m");
                    } else {
                        // System command
                        Cli::echo("     \033[1;33m•\033[0m \033[1;37m" . $item['command'] . "\033[0m");
                    }
                    $count++;
                }
                
                Cli::echo("");
                Cli::echo("   \033[0;37mLeave empty to see all available commands.\033[0m");
                return;
            }
        }
        // Create a nice branded CLI output
        $all_commands = Cli::getAllFn();
        
        // Separate system commands from module commands
        $system_commands = [];
        $module_commands = [];
        
        foreach ($all_commands as $name) {
            if (strpos($name, ':') !== false) {
                $module_commands[] = $name;
            } else {
                $system_commands[] = $name;
            }
        }
        
        // Sort commands alphabetically
        sort($system_commands);
        sort($module_commands);
        
        Cli::echo("");
        Cli::echo("   \033[1;36m╔════════════════════════════════════════════════════════╗\033[0m");
        Cli::echo("   \033[1;36m║\033[0m                        \033[1;37mMilk cli\033[0m                        \033[1;36m║\033[0m");
        Cli::echo("   \033[1;36m║\033[0m                 \033[0;37mCommand Line Interface\033[0m                 \033[1;36m║\033[0m");
        Cli::echo("   \033[1;36m╚════════════════════════════════════════════════════════╝\033[0m");
        Cli::echo("");
        
        if (!empty($system_commands)) {
            Cli::echo("   \033[1;32mSYSTEM COMMANDS:\033[0m");
            Cli::echo("   \033[0;32m─────────────────────\033[0m");
            foreach ($system_commands as $name) {
                Cli::echo("     \033[1;33m•\033[0m \033[1;37m" . $name . "\033[0m");
            }
            Cli::echo("");
        }
        
        if (!empty($module_commands)) {
            Cli::echo("   \033[1;34mMODULE COMMANDS:\033[0m");
            Cli::echo("   \033[0;34m─────────────────────\033[0m");
            
            // Group by module
            $grouped_modules = [];
            foreach ($module_commands as $name) {
                $parts = explode(':', $name, 2);
                $module = $parts[0];
                $command = $parts[1] ?? '';
                $grouped_modules[$module][] = $command;
            }
            
            foreach ($grouped_modules as $module => $commands) {
                Cli::echo("     \033[1;35m" . $module . ":\033[0m");
                foreach ($commands as $command) {
                    Cli::echo("        \033[0;36m▸\033[0m \033[0;37m" . $module . ":" . $command . "\033[0m");
                }
                Cli::echo("");
            }
        }
        
        Cli::echo("   \033[1;33mUsage:\033[0m \033[0;37mphp milkadmin/cli.php <command> [arguments]\033[0m");
        Cli::echo("");
    }
}

// Pulizia finale
Hooks::run('end-cli');
Settings::save();
Get::db()->close();
Get::db2()->close();
