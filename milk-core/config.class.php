<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Configuration data management class
 *
 * @package     MilkCore
 */

class Config
{
    /** @var array $config */
    static private $config = [];

    /**
     * Returns a configuration value
     * 
     * @param string $key The configuration key to retrieve
     * @param mixed $default The default value if key doesn't exist
     * @return mixed The configuration value or default if not found
     */
    public static function get(string $key, $default = null): mixed {
        if (!array_key_exists($key, self::$config)) {
            return $default;
        }
        return self::$config[$key];
    }

    /**
     * Returns all configuration values
     * 
     * @return array All configuration settings as an associative array
     */
    public static function get_all():array {
        return self::$config;
    }

    /**
     * Sets a configuration value
     * 
     * @param string $key The configuration key to set
     * @param mixed $value The value to assign to the key
     * @return void
     */
    public static function set(string $key, $value):void {
        self::$config[$key] = $value;
    }

    /**
     * Sets all configuration values
     * 
     * @param array $config An associative array of configuration values
     * @return void
     */
    public static function set_all(array $config):void {
        if (array_key_exists('base_url', $config)) {
            // Ensure the base_url ends with a trailing slash to prevent 301 redirects for JSON calls
            if (substr($config['base_url'],-1) != "/") {
                $config['base_url'] .= "/";
            }
        }
        self::$config = $config;
    }
}