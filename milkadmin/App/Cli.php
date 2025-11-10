<?php 
namespace App;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Class for registering functions to be called from the command line
 * 
 * ```php
 * function testEchoFn($data) {
 *     Cli::success("Params:");
 *     var_dump ($data);
 * }
 * Cli::set('test_echo', 'test_echo_fn');
 * ```  
 * Opening the shell inside the phpBridge folder
 * ```Shell
 * $ php milkadmin/cli.php test_echo foo bar
 * ```
 * 
 * @package     App
 */

class Cli 
{
    /**
     * Last error message from CLI operations
     * 
     * @var string
     */
    static public $last_error = '';
    /**
     * List of registered functions
     * 
     * @var array
     */
    static private $functions = [];
    /**
     * Current function being executed
     * 
     * @var string
     */
    static private $current_function = ''; 
    /**
     * Checks if the code is running from the command line
     * 
     * @return bool True if running from CLI, false otherwise
     */
    public static function isCli() {
        return (php_sapi_name() === 'cli');
    }

    /**
     * Executes the function passed as an argument
     * 
     * @param array $argv Arguments from command line
     * @return bool True if the function was executed successfully, false otherwise
     */
    public static function run($argv) {
        array_shift($argv);
        self::$current_function = array_shift($argv);
        if (self::$current_function == NULL || !self::isCli()) return false;
        return self::callFunction(self::$current_function, $argv);
    }

    /**
     * Returns the names of all registered functions
     * 
     * @return array Array of function names
     */
    public static function getAllFn() {
        return array_keys(self::$functions);
    }

    /**
     * Registers a function
     * 
     * @param string $name Function name
     * @param callable $function Function to register
     * @return bool True if the function was registered successfully, false otherwise
     */
    public static function set($name, $function) {
        self::$last_error = '';;
        if (empty($name) || !is_string($name)) {
            self::$last_error = "Function name must be a non-empty string";
            return false;
        }
        if (!is_callable($function)) {
            self::$last_error = "Function must be callable";
            return false;
        }
        if (isset(self::$functions[$name])) {
            self::$last_error = "Function '$name' already registered";
            return false;
        }
        self::$functions[$name] = $function;
        return true;
    }

    /**
     * Calls a registered function
     * 
     * @param string $name Function name
     * @param array $args Arguments to pass to the function
     * @return bool True if the function was called successfully, false otherwise
     */
    public static function callFunction($name, ...$args)
    {
        self::$last_error = '';
       
        if (!array_key_exists($name, self::$functions)) {
            $error = "Function '$name' not registered";
            self::$last_error = $error;
            return false;
        }
        if (!is_callable(self::$functions[$name])) {
            $error = "Function '$name' is not callable";
            self::$last_error = $error;
            return false;
        }
        if (count($args) == 0) {
            $new_args = self::completeArgs(self::$functions[$name]);
        } else {
            $new_args = self::completeArgs(self::$functions[$name], $args[0]);
        }
        call_user_func_array(self::$functions[$name], $new_args);
        return true;

    }

    /**
     * Prepares the array with parameters to pass to the function
     * Analyzes function requirements and ensures correct argument count
     * 
     * @param callable $function The function to analyze
     * @param array $args Arguments to pass to the function
     * @return array Completed arguments array
     */
    static private function completeArgs($function, $args = []) {
        if (is_array($function)) {
            $ref = new \ReflectionMethod($function[0], $function[1]);
        } else {
            $ref = new \ReflectionFunction($function);
        }
        
        $num_args = $ref->getNumberOfParameters();
        $new_args = [];
        
        // Copy available arguments to new array
        if (count($args) > 0) { 
            foreach ($args as $arg) {
                $new_args[] = $arg;
            }
        }
        
        // Fill missing arguments with null values
        if (count($new_args) < $num_args) {
            for ($i = count($new_args); $i < $num_args; $i++) {
                $new_args[] = null;
            }
        }
        
        return $new_args;
    }
 
    /**
     * Draws a formatted table in the console
     * 
     * @param array $data Data to display in the table
     * @param array $columns Column definitions (optional)
     */
    static function drawTable($data, $columns = null) {
        // Early return if data is empty
        if (!is_array($data) || count($data) == 0) {
            echo "No data to show\n";
            return;
        }
        
        // Generate column headers if not provided
        if ($columns == null) {
            $columns = [];
            foreach (array_keys($data[0]) as $value) {
                $columns[$value] = str_replace(["-", "_"], " ", $value);
            }
        } 

        // Calculate column widths based on data content
        $columnWidths = [];
        foreach ($data as $row) {
            foreach ($columns as $key => $_) {
                if (isset($row[$key])) {
                    $value = $row[$key];
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value);
                    }
                    $columnWidths[$key] = max($columnWidths[$key] ?? 0, strlen($value));
                }
            }
        }
        
        // Adjust column widths based on headers and limits
        foreach ($columns as $key=>$value) {
            $columnWidths[$key] = max($columnWidths[$key], strlen($value));
            if ($columnWidths[$key] < 3) $columnWidths[$key] = 3;
            if ($columnWidths[$key] > 70) $columnWidths[$key] = 70;
        }
    
        // Draw the top border
        echo "+";
        foreach ($columns as $key=>$_) {
            echo str_repeat("-", $columnWidths[$key] + 2) . "+";
        }
        
        // Draw the header
        echo "\n|";
        foreach ($columns as $key=>$value) {
            echo " " . str_pad($value, $columnWidths[$key]) . " |";
        }
        echo "\n";
        
        // Draw the separator after header
        echo "+";
        foreach ($columns as $key=>$_) {
            echo str_repeat("-", $columnWidths[$key] + 2) . "+";
        }
        echo "\n";
    
        // Draw the data rows
        foreach ($data as $row) {
            echo "|";
            foreach ($columns as $key=>$_) {
                $value = $row[$key] ?? '';
                
                // Format special data types for display
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                } elseif (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                } elseif (is_null($value)) {
                    $value = 'null';
                }
                
                // Truncate long values
                if (strlen($value) > 70) {
                    $value = substr($value, 0, 65) . ' ...';
                }
                
                echo " " . str_pad($value, $columnWidths[$key]) . " |";
            }
            echo "\n";
        }
    
        // Draw the bottom border
        echo "+";
        foreach ($columns as $key => $_) {
            echo str_repeat("-", $columnWidths[$key] + 2) . "+";
        }
        echo "\n";
    }

    /**
     * Prints an error message in red on the console
     * 
     * @param string $msg Message to print
     */
    static function error($msg) {
        print "\n\033[31mError:\033[0m ".$msg."\n";
    }

    /**
     * @return string The last error message
     */
    static function getLastError(): string {
        return self::$last_error;
    }

    /**
     * Prints a message on the console
     * 
     * @param string $msg Message to print
     */
    static function echo($msg) {
        print $msg."\n";
    }
    
    /**
     * Prints a success message in green on the console
     * 
     * @param string $msg Message to print
     */
    static function success($msg) {
        print "\n\033[32mSuccess:\033[0m ".$msg."\n";
    }
}