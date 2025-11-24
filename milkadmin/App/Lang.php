<?php
namespace App;
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
 * @package     App
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
     * Load a PHP translation file
     * @param string $file
     * @param string $area
     * @return bool
     */
    public static function loadPhpFile(string $file, $area = 'all'):bool {
        $file = Get::dirPath($file);
        $file = Hooks::run('load_php_file', $file);
        if (!file_exists($file)) {
            return false;
        }
        try {
            $strings = include $file;
        } catch (\Exception $e) {
            die($e->getMessage());
            return false;
        }
        if (!is_array($strings)) {
            return false;
        }
        foreach ($strings as $key => $value) {
            self::set($key, $value, $area);
        }
        return true;
    }

   
    /**
     * Costruisce il contenuto JavaScript
     * 
     * @param array $translations Le traduzioni da includere
     * @param bool $minify Se minificare l'output
     * @return string Il codice JavaScript
     */
    public static function generateJs(string $page, bool $minify): string 
    {
        $translations = self::$strings;
        $jsonOptions = JSON_UNESCAPED_UNICODE;
        if (!$minify) {
            $jsonOptions |= JSON_PRETTY_PRINT;
        }
        $js_trans = [];
        $translations = array_reverse($translations);
        foreach ($translations as $area => $strings) {
            if ($area == "all" || $area == $page) {
                $js_trans = [...$js_trans, ...$strings];
            }
        }
        $json = json_encode($js_trans, $jsonOptions);
        
        if ($minify) {
            $js = "window.TRANSLATIONS=" . $json . ";";
        } else {
            $js = "// Translation file\n";
            $js .= "// Do not modify this file manually\n\n";
            $js .= "window.TRANSLATIONS = " . $json . ";\n\n";

        }
        
        return $js;
    }
    
    /**
     * Serve il JavaScript direttamente via HTTP
     * 
     * Utile per creare un endpoint come translations.php che serve
     * direttamente il JavaScript delle traduzioni.
     * 
     * @param bool $minify Se minificare l'output
     * @param int $cacheSeconds Secondi di cache (default: 3600 = 1 ora)
     * @return void
     */
    public static function serveJs(string $page, bool $minify = true, int $cacheSeconds = 3600): void 
    {
        // Headers per JavaScript
        header('Content-Type: application/javascript; charset=utf-8');
        
        // Headers per cache
        if ($cacheSeconds > 0) {
            header('Cache-Control: public, max-age=' . $cacheSeconds);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheSeconds) . ' GMT');
        }
        
        // Genera e invia il JavaScript
        echo self::generateJs($page, $minify);
        exit;
    }
}