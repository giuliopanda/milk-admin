<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Logging System Class
 * 
 * This class provides a flexible logging system that allows for organizing logs
 * into groups, with different message types and optional parameter storage.
 * It can also automatically track the file path that generated each log entry.
 * 
 * @example
 * ```php
 * // Basic log entry
 * Logs::set('system', 'INFO', 'Application started');
 * 
 * // Log with parameters
 * Logs::set('user', 'LOGIN', 'User login attempt', [
 *     'username' => 'john.doe',
 *     'ip' => '192.168.1.1'
 * ]);
 * 
 * // Log without file path tracking
 * Logs::set('performance', 'BENCHMARK', 'Query execution time', ['time' => 0.023], false);
 * ```
 *
 * @package     App
 */

class Logs
{
    /**
     * Storage for all log entries
     * 
     * Organized by log group.
     * 
     * @var array
     */
    private static $logs = [];

    /**
     * Adds a log entry to a log group
     * 
     * This method creates a new log entry and stores it in the specified group.
     * It can optionally include additional parameters and automatically track
     * the file path that generated the log entry.
     * 
     * @example
     * ```php
     * // Simple error log
     * Logs::set('errors', 'ERROR', 'Database connection failed');
     * 
     * // Detailed log with parameters
     * Logs::set('security', 'WARNING', 'Invalid access attempt', [
     *     'ip' => $_SERVER['REMOTE_ADDR'],
     *     'user_agent' => $_SERVER['HTTP_USER_AGENT']
     * ]);
     * ```
     *
     * @param string $group The log group name (also used as filename when writing to disk)
     * @param string $msgType The message type (free text, typically uppercase like 'ERROR', 'INFO')
     * @param string $msg Optional message text to log
     * @param mixed $params Optional parameters to store with the log entry
     * @param bool|array $path Whether to include file paths that led to this log call, or custom paths
     * @return void
     */
    public static function set($group, $msgType, $msg = "", $params = "", $path = true): void {
        $in = false;
        if (is_array($path)) {
            $in = $path;
        }
        if ($path === true) {
            $debug = (debug_backtrace());
            if (count ($debug) > 0) {
                array_shift($debug);
            }
            if (count ($debug) > 0) {
                $in = array();
                foreach ($debug as $d) {
                    if (array_key_exists('file', $d) && array_key_exists('line', $d)) {
                        $in[] = $d['file'].":".$d['line'];
                    } else if (array_key_exists('file', $d)) {
                        $in[] = $d['file'];
                    }
                }
            }
        } 
        if (!array_key_exists($group, self::$logs)) {
            self::$logs[$group] = array();
        }
        self::$logs[$group][] = array('msgType'=>$msgType, 'msg'=>$msg, 'params' =>$params, 'time'=>date('YmdHis'), "in"=> $in);
    }
    
    /**
     * Returns the saved message group
     * @param \string $group
     */
    public static function get($group) {
        if (array_key_exists($group, self::$logs)) {
            return self::$logs[$group];
        } else {
            return array();
        }
    }

    /**
     * Returns all error logs
     * @return array
     */
    public static function getAllErrors() {
        $get_logs = [];
        foreach (self::$logs as $group => $logs) {
            foreach ($logs as $log) {
                if ($log['msgType'] == 'ERROR') {
                    $get_logs[] = $log;
                }
            }
        }
        return $get_logs;
    }

    /**
     * Clean string before saving to log
     * @param \string $string
     * @return \string
     */
    public static function cleanStr($string) {
        if (is_array($string) || is_object($string)) {
            return json_encode($string);
        } 
        $string = str_replace(["\\", "\n","\r",'"', "  "], ["\\\\", "" ,"" ,'\"', " "], $string);
        $string = trim($string);
        if (strpos($string, " ") !== false) {
            $string = '"'.$string.'"';
        }
        return $string;
    }
    /** 
     * Cleans a log string that was modified with cleanStr
     * 
     * @param \string $string
     * @return \string
     */
    public static function logStr($string) {
        $string = trim($string);
        if (substr($string,0,1) == '"') {
            $string = substr($string,1);
        }
        if (substr($string,-1,1) == '"') {
            $string = substr($string,0, -1);
        }
        return stripslashes($string);
    }
}