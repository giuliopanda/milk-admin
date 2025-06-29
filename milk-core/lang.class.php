<?php
namespace MilkCore;
use MilkCore\Get;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Internationalization and Localization Class
 * 
 * This class provides a simple translation system with two main functions:
 * - set: to define a string and its translation
 * - get: to retrieve the translation for a string
 * 
 * The class supports area-specific translations, allowing different translations
 * for the same string in different parts of the application.
 *
 * @example
 * ```php
 * // Set translations
 * Lang::set('Hello', 'Bonjour', 'french');
 * Lang::set('Hello', 'Hola', 'spanish');
 * 
 * // Get translations
 * echo Lang::get('Hello', 'french'); // Outputs: Bonjour
 * echo Lang::get('Hello', 'spanish'); // Outputs: Hola
 * echo Lang::get('Hello'); // Outputs: Hello (falls back to original if no translation in 'all' area)
 * ```
 *
 * @package     MilkCore
 */

class Lang
{
    /**
     * Array of stored translations
     * 
     * Organized by area and then by original string.
     * Format: ['area' => ['original' => 'translation']]
     * 
     * @var array
     */
    static private $strings = [];

    /**
     * Sets a string and its translation
     * 
     * This method defines a translation for a specific string. Translations can be
     * limited to specific areas of the application by specifying the area parameter.
     * 
     * @example
     * ```php
     * // Global translation
     * Lang::set('Save', 'Enregistrer');
     * 
     * // Area-specific translation
     * Lang::set('Post', 'Article', 'blog');
     * ```
     *
     * @param string $string The original string to translate
     * @param string $translation The translated string
     * @param string $area The area to limit this translation to (default: 'all')
     * @return void
     */
    public static function set(string $string, string $translation, $area = 'all'):void {
        if ($area == '' || !is_scalar($area)) {
            $area = 'all';
        }
        if (!isset(self::$strings[$area])) {
            self::$strings[$area] = [];
        }
        self::$strings[$area][$string] = $translation;
    }

    /**
     * Gets the translation for a string
     * 
     * This method retrieves the translation for a specific string in the specified area.
     * If no translation is found in the specified area, it falls back to the 'all' area.
     * If no translation is found at all, it returns the original string.
     * 
     * @example
     * ```php
     * // Get a translation in a specific area
     * $translated = Lang::get('Submit', 'forms');
     * 
     * // Get a global translation
     * $translated = Lang::get('Cancel');
     * ```
     *
     * @param string $string The original string to translate
     * @param string $area The area to look for the translation in (default: 'all')
     * @return string The translated string or the original if no translation is found
     */
    public static function get(string $string, $area = 'all'):string {
        if ($area == '' || !is_scalar($area)) {
            $area = 'all';
        }
        if (isset(self::$strings[$area]) && array_key_exists($string, self::$strings[$area])) {
            return self::$strings[$area][$string];
        }
        if (isset(self::$strings['all']) && array_key_exists($string, self::$strings['all'])) {
            return self::$strings['all'][$string] ?? $string;
        }
        return $string;
    }

    /**
     * Upload a translation file
     * @param string $file
     * @param string $area
     * @return bool
     */
    public static function load_ini_file(string $file, $area = 'all'):bool {
        $file = Get::dir_path($file);

        $file = Hooks::run('load_ini_file', $file); 
        if (!file_exists($file)) {
            return false;
        }
        try {
            $strings = parse_ini_file($file);
        } catch (\Exception $e) {
            return false;
        }
        if (!$strings) {
            return false;
        }

        foreach ($strings as $key => $value) {
            self::set($key, $value, $area);
        }
        return true;
    }

}