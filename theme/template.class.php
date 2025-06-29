<?php
namespace Theme;
use MilkCore\Theme;
use MilkCore\Get;
use MilkCore\Config;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * La classe template si occupa di gestire il template del sito
 * L'idea è che tramite parametri si sceglierà su ogni pagina 
 * osa visualizzare tra header, footer, menu e sidebar
 * è richiesta la costante THEME_DIR
 */

class Template
{

    static $last_plugin_name = '';


    public static function get_head() {
        require Get::dir_path(THEME_DIR.'/template_parts/head.php'); 
    }

    public static function get_header() {
        require Get::dir_path(THEME_DIR.'/template_parts/header-menu.php'); 
    }

    public static function get_utilities() {
        require Get::dir_path(THEME_DIR.'/template_parts/utilities.php');
    }

    public static function get_footer() {
        require Get::dir_path(THEME_DIR.'/template_parts/footer.php');
    }

    public static function get_close_theme() {
        require Get::dir_path(THEME_DIR.'/template_parts/close-theme.php');
    }

    public static function get_sidebar() {
        require Get::dir_path(THEME_DIR.'/template_parts/sidebar.php');
    }
    

    public static function get_logo($custom_class = '', $path = '') {
        require Get::dir_path(THEME_DIR.'/template_parts/logo.php');
    }

  

    /**
     * Carica tutti i css del tema inclusi gli assets e moduli e 
     * tutti i css da un hooks add-css
     */
    public static function get_css() {
        $version = Config::get('version');
        // carico tutti i css della cartella assets
        $css_files = glob(THEME_DIR.'/assets/*.css');
        foreach ($css_files as $css) {
            _ph('<link href="'.Get::uri_path(THEME_URL.'/assets/'.basename($css)).'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }
        // carico tutti i css dei moduli
        $css_files = glob(THEME_DIR.'/plugins/*/*.css');
        foreach ($css_files as $css) {
            _ph('<link href="'.Get::uri_path(THEME_URL.'/plugins/'.basename(dirname($css)).'/'.basename($css)).'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }
        foreach (Theme::for('styles') as $css) {
            _ph('<link href="'.$css.'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }
    }

    public static function get_js() {
        $version = Config::get('version');
        // carico tutti i js della cartella assets
        $js_files = glob(THEME_DIR.'/assets/*.js');
        foreach ($js_files as $js) {
            echo '<script src="'.Get::uri_path(THEME_URL.'/assets/'.basename($js)).'?v='.$version.'"  crossorigin="anonymous"></script>'."\n";
        }
        // carico tutti i js dei moduli
        $js_files = glob(THEME_DIR.'/plugins/*/*.js');
        foreach ($js_files as $js) {
            echo '<script src="'.Get::uri_path(THEME_URL.'/plugins/'.basename(dirname($js)).'/'.basename($js)).'?v='.$version.'"  crossorigin="anonymous"></script>'."\n";
        }
        foreach (Theme::for('javascript') as $js) {
            echo '<script src="'.$js.'?v='.$version.'"  crossorigin="anonymous"></script>'."\n"; 
        }
    }

    /**
     * Aggiunge attributi html da usare sempre nei plugin!!!!
     * @param $attrs array di attributi es. ['table' => ['class' => 'table table-hover'], 'all_els' => ['class' => 'custom_el']
     * @param $key chiave dell'array da cui prendere gli attributi es. 'table'
     * @param $default_key chiave di default da cui prendere gli attributi 
     * se non esiste $key o per farne il merge es. 'all_els'
     */
    public static function add_attrs($attrs, $key, $default_key = null, $other_attrs = []) {
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

}


//require_once Get::dir_path(THEME_DIR . '/functions.php');