<?php
namespace Theme;

use App\{Config, Get, Theme};

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * The Template class is responsible for rendering the site layout.
 * The idea is to control, per page, what to show among
 * header, footer, menu and sidebar through parameters.
 * The THEME_DIR constant is required.
 */

class Template
{

    static $last_plugin_name = '';

    public static function getHead() {
        require Get::dirPath(THEME_DIR.'/TemplateParts/head.php'); 
    }

    public static function getHeader() {
        require Get::dirPath(THEME_DIR.'/TemplateParts/header-menu.php'); 
    }

    public static function getUtilities() {
        require Get::dirPath(THEME_DIR.'/TemplateParts/utilities.php');
    }

    public static function getFooter() {
        require Get::dirPath(THEME_DIR.'/TemplateParts/footer.php');
    }

    public static function getCloseTheme() {
        require Get::dirPath(THEME_DIR.'/TemplateParts/close-theme.php');
    }

    public static function getSidebar() {
        require Get::dirPath(THEME_DIR.'/TemplateParts/sidebar.php');
    }

    public static function getLogo($custom_class = '', $path = '') {
        require Get::dirPath(THEME_DIR.'/TemplateParts/logo.php');
    }

    /**
     * Loads all theme CSS files, including assets, plugins,
     * and CSS entries registered through add-css hooks.
     */
    public static function getCss() {
        AssetsBundle::cleanupIfDevelopment();
        $version = Config::get('version');
        $bundleRendered = false;

        if (AssetsBundle::isProduction()) {
            try {
                AssetsBundle::ensureCompiled();
                _ph('<link href="'.Get::uriPath(AssetsBundle::getCssBundleUrl()).'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
                $bundleRendered = true;
            } catch (\Throwable $e) {
                error_log('Template::getCss bundle failed: ' . $e->getMessage());
            }
        }

        if ($bundleRendered) {
            foreach (Theme::for('styles') as $css) {
                if (!AssetsBundle::isCssUrlBundled((string) $css)) {
                    _ph('<link href="'.$css.'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
                }
            }
            return;
        }

        foreach (AssetsExtensionsManifest::getCssBeforeThemeUrls() as $cssUrl) {
            _ph('<link href="'.Get::uriPath($cssUrl).'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }

        // Load all CSS files from the assets folder
        $css_files = glob(THEME_DIR.'/Assets/*.css');
        foreach ($css_files as $css) {
            _ph('<link href="'.Get::uriPath(THEME_URL.'/Assets/'.basename($css)).'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }
        // Load all CSS files from plugin folders
        $css_files = glob(THEME_DIR.'/Plugins/*/*.css');
        foreach ($css_files as $css) {
            _ph('<link href="'.Get::uriPath(THEME_URL.'/Plugins/'.basename(dirname($css)).'/'.basename($css)).'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }
        foreach (Theme::for('styles') as $css) {
            _ph('<link href="'.$css.'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }

        foreach (AssetsExtensionsManifest::getCssAfterThemeUrls() as $cssUrl) {
            _ph('<link href="'.Get::uriPath($cssUrl).'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }
    }

    public static function getJs() {
        AssetsBundle::cleanupIfDevelopment();
        $version = Config::get('version');
        $bundleRendered = false;

        if (AssetsBundle::isProduction()) {
            try {
                AssetsBundle::ensureCompiled();
                echo '<script src="'.Get::uriPath(AssetsBundle::getJsBundleUrl()).'?v='.$version.'"  crossorigin="anonymous"></script>'."\n";
                $bundleRendered = true;
            } catch (\Throwable $e) {
                error_log('Template::getJs bundle failed: ' . $e->getMessage());
            }
        }

        if ($bundleRendered) {
            foreach (Theme::for('javascript') as $js) {
                if (!AssetsBundle::isJsUrlBundled((string) $js)) {
                    echo '<script src="'.$js.'?v='.$version.'"  crossorigin="anonymous"></script>'."\n";
                }
            }
            return;
        }

        foreach (AssetsExtensionsManifest::getJsBeforeThemeUrls() as $jsUrl) {
            echo '<script src="'.Get::uriPath($jsUrl).'?v='.$version.'"  crossorigin="anonymous"></script>'."\n";
        }

        // Load all JS files from the assets folder
        $js_files = glob(THEME_DIR.'/Assets/*.js');
        foreach ($js_files as $js) {
            echo '<script src="'.Get::uriPath(THEME_URL.'/Assets/'.basename($js)).'?v='.$version.'"  crossorigin="anonymous"></script>'."\n";
        }
        // Load all JS files from plugin folders
        $js_files = glob(THEME_DIR.'/Plugins/*/*.js');
        foreach ($js_files as $js) {
            echo '<script src="'.Get::uriPath(THEME_URL.'/Plugins/'.basename(dirname($js)).'/'.basename($js)).'?v='.$version.'"  crossorigin="anonymous"></script>'."\n";
        }
        foreach (Theme::for('javascript') as $js) {
            echo '<script src="'.$js.'?v='.$version.'"  crossorigin="anonymous"></script>'."\n"; 
        }
    }

    /**
     * Adds HTML attributes intended for plugin templates.
     * @param $attrs attributes array, e.g. ['table' => ['class' => 'table table-hover'], 'all_els' => ['class' => 'custom_el']]
     * @param $key array key from which to read attributes, e.g. 'table'
     * @param $default_key default key used to read/merge fallback attributes
     * when $key is missing, or to merge with a generic key such as 'all_els'
     */
    public static function addAttrs($attrs, $key, $default_key = null, $other_attrs = []) {
        if (!is_array($attrs) && $default_key == null && count($other_attrs) == 0) return;
        if ($default_key && array_key_exists($default_key, $attrs)) {
            if (array_key_exists($key, $attrs)) {
                $attrs[$key] = array_merge($attrs[$default_key], $attrs[$key]);
            } else {
                $attrs[$key] = $attrs[$default_key];
            }
        }
        if (!is_array($other_attrs)) {
            $other_attrs = [];
        } 
        if (array_key_exists($key, $attrs)) {
            foreach ($attrs[$key] as $key_label => $value) {
                if (array_key_exists($key_label, $other_attrs)) {
                    if (!is_array($other_attrs[$key_label])) {
                        $other_attrs[$key_label] = [$other_attrs[$key_label]];
                    }
                    if (!is_array($value)) {
                        $value = array_merge([$value], $other_attrs[$key_label]);
                    } else {
                        $value = array_merge($value, $other_attrs[$key_label]);
                    }
                }
                if (!is_array($value)) {
                    echo ' '._r($key_label).'="'._r($value).'"';
                } else {
                    $value = array_filter($value);
                    $value = array_unique($value);
                    echo ' '._r($key_label).'="'._r(implode(' ', $value)).'"';
                }
            }
            //
        }
        foreach ($other_attrs as $key_label => $value) {
            if (!array_key_exists($key, $attrs) || !array_key_exists($key_label, $attrs[$key])) {
                if (!is_array($value)) {
                    echo ' '._r($key_label).'="'._r($value).'"';
                } else {
                    echo ' '._r($key_label).'="'._r(implode(' ', $value)).'"';
                }
            } 
        }
                      
    }

    public static function getMaxUploadSizeMB() {
        // Converts shorthand PHP size notation to bytes
       
        
        // Read configuration values
        $upload_max_bytes = self::convertToBytes(ini_get('upload_max_filesize'));
        $post_max_bytes = self::convertToBytes(ini_get('post_max_size'));
        $memory_limit_bytes = self::convertToBytes(ini_get('memory_limit'));
        
        // Take the minimum between upload_max_filesize and post_max_size
        $effective_max_size = min($upload_max_bytes, $post_max_bytes);
        
        // If memory_limit is set, include it (reserve 2MB for processing)
        if ($memory_limit_bytes > 0) {
            $memory_available = $memory_limit_bytes - (2 * 1024 * 1024);
            if ($memory_available > 0) {
                $effective_max_size = min($effective_max_size, $memory_available);
            }
        }
        
        // Convert to MB
        return round($effective_max_size / (1024 * 1024), 2);
    }

    public static function convertToBytes($value) {
        if (empty($value)) {
            return 0;
        }
        
        $value = trim($value);
        $unit = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;
        
        switch ($unit) {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
        }
        
        return $number;
    }

}

//require_once Get::dirPath(THEME_DIR . '/functions.php');
