<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Hook management system that allows registering one or more functions to be called later
 * 
 * @example
 * ```php
 * class test {
 *    public function __construct() {
 *        HOOKS::set('my_hook', [get_called_class(), 'test']);
 *    }
 *    
 *    public static function test($a, $b) {
 *        print "test ".$a." ".$b."\n";
 *        return $a + $B;
 *    }
 * }
 * function test_new($a) {  
 *     print "Somma di A ".$a."\n"; 
 * }
 * 
 * new test();
 * HOOKS::set('my_hook', 'test_new');
 * HOOKS::run('my_hook', 1, 2);
 * ```
 *
 * @package     MilkCore
 */

class Hooks
{
    /** @var array $functions ['name' => ['fn'=>function, 'order'=>int], ...] */
    static private $functions = [];

    /**
     * Registers a function for a specific hook
     * 
     * @example
     * ```php
     * Hooks::set($name, $function, $order = int)
     * ```
     * 
     * @param string $name The name of the hook
     * @param callable $function The function to be called
     * @param int $order The execution order (lower numbers execute first)
     * @return void
     */
    public static function set($name, $function, $order = 20):void {
        $name = _raz($name);
      
            // Determine where set() was called from
        $trace = debug_backtrace();
        $caller = ($trace[0]['file'] ?? '') . ':' . ($trace[0]['line'] ?? '');
        
        if (!array_key_exists($name, self::$functions)) {
            self::$functions[$name] = [];
        }
        self::$functions[$name][] = ['fn'=> $function, 'order'=>$order, 'caller' => $caller];
    }

    /**
     * Calls a group of registered functions, modifying and returning the first value (if present)
     *
     * When a hook is called, arguments are passed. The first argument will be the return value
     * 
     * @example
     * ```php
     * $return = Hooks::run($name, ...$args)
     * ```
     * 
     * @param string $name The name of the hook to run
     * @param mixed $args Arguments to pass to the hook functions
     * @return mixed The modified value after all hooks have processed it
     */
    public static function run($name, ...$args) {
      
        $name = _raz($name);
        if (count($args) == 0) {
            $value = null;
        } else {
            $value = array_shift($args);
        }
        if (array_key_exists($name, self::$functions)) { 
            usort(self::$functions[$name], function($a, $b) {
                return $a['order'] - $b['order'];
            });
            foreach (self::$functions[$name] as $function) {
                if (is_callable($function['fn'])) {
                    $new_args = self::complete_args($function['fn'], $args, $value);
                    $value = call_user_func_array($function['fn'], $new_args);
                }
            }
        }
    
        return $value;
    }

    /**
     * Retrieves all registered functions for a specific hook, sorted by order.
     *
     * @param string $name The name of the hook.
     * @return array An array of registered function metadata (['fn' => callable, 'order' => int, 'caller' => string]),
     *               or an empty array if the hook has no registrations.
     */
    public static function get_hook_registrations($name): array {
        $name = _raz($name);
        if (array_key_exists($name, self::$functions)) {
            $hook_registrations = self::$functions[$name];
            usort($hook_registrations, function($a, $b) {
                return $a['order'] - $b['order'];
            });
            return $hook_registrations;
        }
        return [];
    }

    /**
     * Prepares the array of parameters to pass to the function
     * The first parameter is the value which can be modified
     * 
     * @param callable $function The function to prepare arguments for
     * @param array $args Additional arguments
     * @param mixed $value The value that can be modified
     * @return array The prepared arguments array
     */
    static private function complete_args($function, $args, $value) {
        if (is_array($function)) {
            $ref = new \ReflectionMethod($function[0], $function[1]);
        } else {
            $ref = new \ReflectionFunction($function);
        }
        //$ref = new ReflectionMethod(__CLASS__, 'first_client_request');
        $num_args = $ref->getNumberOfParameters();
        $new_args = [];
        if ($num_args > 0) {
            $new_args = [$value];
        }
        if (count($args) > 0) { 
            $count = 0;
            foreach ($args as $arg) {
                if ($count < count($args)) {
                    $new_args[] = $arg;
                } 
                $count++;
            }
        } 
        
        if (count($new_args) < $num_args) {
            for ($i = count($new_args); $i < $num_args; $i++) {
                $new_args[] = null;
            }
        }
        return $new_args;
    }
}