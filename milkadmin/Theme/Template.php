<?php
namespace Theme;

use App\{Config, Get, Theme};

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
     * Carica tutti i css del tema inclusi gli assets e moduli e 
     * tutti i css da un hooks add-css
     */
    public static function getCss() {
        $version = Config::get('version');
        // carico tutti i css della cartella assets
        $css_files = glob(THEME_DIR.'/Assets/*.css');
        foreach ($css_files as $css) {
            _ph('<link href="'.Get::uriPath(THEME_URL.'/Assets/'.basename($css)).'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }
        // carico tutti i css dei moduli
        $css_files = glob(THEME_DIR.'/Plugins/*/*.css');
        foreach ($css_files as $css) {
            _ph('<link href="'.Get::uriPath(THEME_URL.'/Plugins/'.basename(dirname($css)).'/'.basename($css)).'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }
        foreach (Theme::for('styles') as $css) {
            _ph('<link href="'.$css.'?v='.$version.'" rel="stylesheet" crossorigin="anonymous">'."\n");
        }
    }

    public static function getJs() {
        $version = Config::get('version');
        // carico tutti i js della cartella assets
        $js_files = glob(THEME_DIR.'/Assets/*.js');
        foreach ($js_files as $js) {
            echo '<script src="'.Get::uriPath(THEME_URL.'/Assets/'.basename($js)).'?v='.$version.'"  crossorigin="anonymous"></script>'."\n";
        }
        // carico tutti i js dei moduli
        $js_files = glob(THEME_DIR.'/Plugins/*/*.js');
        foreach ($js_files as $js) {
            echo '<script src="'.Get::uriPath(THEME_URL.'/Plugins/'.basename(dirname($js)).'/'.basename($js)).'?v='.$version.'"  crossorigin="anonymous"></script>'."\n";
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
        // Funzione per convertire la notazione PHP in bytes
       
        
        // Recupera i valori di configurazione
        $upload_max_bytes = self::convertToBytes(ini_get('upload_max_filesize'));
        $post_max_bytes = self::convertToBytes(ini_get('post_max_size'));
        $memory_limit_bytes = self::convertToBytes(ini_get('memory_limit'));
        
        // Calcola il minimo tra upload_max_filesize e post_max_size
        $effective_max_size = min($upload_max_bytes, $post_max_bytes);
        
        // Se memory_limit è impostato, consideralo (lascia 2MB per l'elaborazione)
        if ($memory_limit_bytes > 0) {
            $memory_available = $memory_limit_bytes - (2 * 1024 * 1024);
            if ($memory_available > 0) {
                $effective_max_size = min($effective_max_size, $memory_available);
            }
        }
        
        // Converti in MB
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