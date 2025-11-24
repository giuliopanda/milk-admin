<?php
namespace App;

use App\Exceptions\CliException;
use App\Exceptions\CliFunctionExecutionException;

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
    public static function isCli(): bool {
        return (php_sapi_name() === 'cli');
    }

    /**
     * Executes the function passed as an argument
     * Handles exceptions and displays error messages in a user-friendly format
     *
     * @param array $argv Arguments from command line
     * @return bool True if the function was executed successfully, false otherwise
     */
    public static function run(array $argv): bool {
        array_shift($argv);
        self::$current_function = array_shift($argv);

        if (self::$current_function == NULL || !self::isCli()) {
            return false;
        }

        try {
            self::callFunction(self::$current_function, $argv);
            return true;
        } catch (CliFunctionExecutionException $e) {
            self::error($e->getMessage());
            // Show previous exception if available
            if ($e->getPrevious()) {
                self::echo("Caused by: " . $e->getPrevious()->getMessage());
            }
            return false;
        } catch (CliException $e) {
            self::error($e->getMessage());
            return false;
        } catch (\Exception $e) {
            self::error("Unexpected error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns the names of all registered functions
     *
     * @return array Array of function names
     */
    public static function getAllFn(): array {
        return array_keys(self::$functions);
    }

    /**
     * Registers a function
     *
     * @param string $name Function name
     * @param callable|string|array $function Function to register (callable, function name, or [class, method])
     * @return void
     * @throws CliException If registration fails (invalid name, not callable, or already registered)
     */
    public static function set(string $name, callable|string|array $function): void {
        if (empty($name) || !is_string($name)) {
            throw new CliException("Function name must be a non-empty string");
        }
        if (!is_callable($function)) {
            throw new CliException("Function '$name' must be callable");
        }
        if (isset(self::$functions[$name])) {
            throw new CliException("Function '$name' is already registered");
        }
        self::$functions[$name] = $function;
    }

    /**
     * Calls a registered function
     *
     * @param string $name Function name
     * @param mixed ...$args Arguments to pass to the function (variadic)
     * @return void
     * @throws CliException If function is not registered or not callable
     * @throws CliFunctionExecutionException If the function execution fails
     */
    public static function callFunction(string $name, ...$args): void
    {
        if (!array_key_exists($name, self::$functions)) {
            throw new CliException("Function '$name' is not registered");
        }
        if (!is_callable(self::$functions[$name])) {
            throw new CliException("Function '$name' is not callable");
        }

        // Handle arguments - if first arg is an array and only one arg, use it as args list
        // Otherwise use all args directly
        if (count($args) == 1 && is_array($args[0])) {
            $new_args = self::completeArgs(self::$functions[$name], $args[0]);
        } else {
            $new_args = self::completeArgs(self::$functions[$name], $args);
        }

        try {
            call_user_func_array(self::$functions[$name], $new_args);
        } catch (\Exception $e) {
            // If it's already a CliException, rethrow it
            if ($e instanceof CliException) {
                throw $e;
            }
            // Wrap other exceptions as execution errors
            throw new CliFunctionExecutionException(
                "Function '$name' execution failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Prepares the array with parameters to pass to the function
     * Analyzes function requirements and ensures correct argument count
     *
     * @param callable|string|array $function The function to analyze
     * @param array $args Arguments to pass to the function
     * @return array Completed arguments array
     */
    static private function completeArgs(callable|string|array $function, array $args = []): array {
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
     * @param array|null $columns Column definitions (optional)
     * @return void
     */
    static function drawTable(array $data, ?array $columns = null): void {
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
     * Draws a title box in the console
     * Automatically calculates box size based on title length
     *
     * @param string $title The title to display
     * @param int $padding Additional padding on each side (default: 4)
     * @param string $color ANSI color code (default: cyan - \033[1;36m)
     * @return void
     */
    static function drawTitle(string $title, int $padding = 4, string $color = "\033[1;36m"): void {
        $reset = "\033[0m";
        $title_length = strlen($title);
        $box_width = $title_length + ($padding * 2);

        // Top border
        echo "\n";
        echo $color . "╔" . str_repeat("═", $box_width) . "╗" . $reset . "\n";

        // Title line with centering
        $left_padding = str_repeat(" ", $padding);
        $right_padding = str_repeat(" ", $padding);
        echo $color . "║" . $reset . $left_padding . "\033[1;37m" . $title . $reset . $right_padding . $color . "║" . $reset . "\n";

        // Bottom border
        echo $color . "╚" . str_repeat("═", $box_width) . "╝" . $reset . "\n";
        echo "\n";
    }

    /**
     * Draws a section separator line
     *
     * @param string $title Optional section title
     * @param int $width Line width (default: 40)
     * @param string $color ANSI color code (default: yellow)
     * @return void
     */
    static function drawSeparator(string $title = '', int $width = 40, string $color = "\033[0;33m"): void {
        $reset = "\033[0m";

        if (empty($title)) {
            echo $color . str_repeat("━", $width) . $reset . "\n";
        } else {
            $title_with_spaces = " " . $title . " ";
            $title_length = strlen($title_with_spaces);

            if ($title_length >= $width) {
                echo $color . $title_with_spaces . $reset . "\n";
            } else {
                $line_length = floor(($width - $title_length) / 2);
                $left_line = str_repeat("━", $line_length);
                $right_line = str_repeat("━", $width - $line_length - $title_length);
                echo $color . $left_line . $reset . $title_with_spaces . $color . $right_line . $reset . "\n";
            }
        }
    }

    /**
     * Prints an error message in red on the console
     *
     * @param string $msg Message to print
     * @return void
     */
    static function error(string $msg): void {
        print "\n\033[31mError:\033[0m ".$msg."\n";
    }

    /**
     * Prints a message on the console
     *
     * @param string $msg Message to print
     * @return void
     */
    static function echo(string $msg): void {
        print $msg."\n";
    }

    /**
     * Prints a success message in green on the console
     *
     * @param string $msg Message to print
     * @return void
     */
    static function success(string $msg): void {
        print "\n\033[32mSuccess:\033[0m ".$msg."\n";
    }
}