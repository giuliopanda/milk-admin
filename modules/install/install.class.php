<?php
namespace Modules\Install;
use MilkCore\Get;
use MilkCore\Config;
use MilkCore\File;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Funzioni di servizio per l'installazione
 */

class Install
{
    /**
     * @var array $configs ['title'=>'string',  'params'=>[]]
     */
    static $configs = []; 

    /**
     * Aggiunge gli errori al modulo
     */
    public static function set_errors($module, $errors, $new_errors) {
        if (!is_array($errors)) {
            $errors = [];
        }
        if (is_array($new_errors) && count($new_errors) > 0) {
            $errors = array_merge($errors, [$module => $new_errors]);
        }
        return $errors;
    }

    public static function print_errors($errors) {
        if (isset($errors) && is_array($errors) && count($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $key => $value): ?>
                    <p><?php echo $value; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }

    /**
     * Imposto i parametri del file config
     */
    public static function set_config_file($title, $configs) {
        /** stampo i config */
        self::$configs[] = ['title'=>$title, 'params'=>$configs];
    }


    /**
     * Salvo il file di configurazione   
     */
    public static function save_config_file() {
        $conf = [];
       
        foreach (self::$configs as $config) {
            if ($config['title']  != '') {
                $config['title'] = str_replace("\n", "\n * ", $config['title']);
                $conf[] = "/**\n * ".$config['title']."\n */";
            }
            foreach ($config['params'] as $key => $value) {
              
                if (is_array($value) && isset($value['value'])) {
                    $real_value = str_replace("'", "\'",$value['value']);
                    if (isset($value['type']) && ($value['type'] == 'number' || $value['type'] == 'boolean')) {
                        $real_value = str_replace("\n","", $real_value);
                    } else {
                        $real_value = "'".$real_value."'";
                    }
                    $comment = isset($value['comment']) ? " // ".str_replace("\n"," ", $value['comment']) : '';
                    $conf[] = " \$conf['"._r($key)."'] = ".$real_value.";".$comment;
                } else {
                    $value = str_replace("'", "\'", $value);
                    $key = str_replace("'", "\'", $key);
                    if (substr($key,0,3) == "___") {
                        // variabili commentate
                        $key = substr($key, 3);
                        $conf[] = "// \$conf['"._r($key)."'] = '".$value."';";
                    } else {
                        if ($value == 'true' || $value == 'false') {
                            $conf[] = " \$conf['"._r($key)."'] = ".($value == 'true' ? 'true' : 'false').";";
                        } else if ($value === true || $value === false) {
                            $conf[] = " \$conf['"._r($key)."'] = ".($value ? 'true' : 'false').";";
                        } else {
                            $conf[] = " \$conf['"._r($key)."'] = '".$value."';";
                        }
                    }
                }
            }
            $conf[] = "";
          
        }
        $html_conf = "<?php\nuse MilkCore\Config;\n!defined('MILK_DIR') && die(); // Avoid direct access\n\n";
        $conf = implode("\n", $conf);
        
        $html_conf .= "\$conf = [];\n\n".$conf."\nConfig::set_all(\$conf);";
        // copio il file prima di salvarlo

        File::put_contents(MILK_DIR."/config.php", $html_conf);
        // TODO verificare se il file funziona?!?!

    }

    /**
     * Execute sql file
     * @param string $file
     * @param array $variables Array of variables to replace in the sql file es. ['[var1]'=>'value1', '[var2]'=>'value2']
     * return true or error
     */
    public static function execute_sql_file($file, $variables = []) {
        $db = Get::db();
        $prefix = Config::get('prefix');
        $sql = file_get_contents($file);
        foreach ($variables as $key => $value) {
            $sql = str_replace($key, $value, $sql);
        }
        $sql = str_replace("\r", "\n", $sql);
        $sql = str_replace("#_",  $prefix, $sql);
        $sql = explode(";\n", $sql); 
        foreach ($sql as $query) {
            if (trim($query) != '') {
                $db->query($query);
            }
        }
        if ($db->error)  {
            return $db->last_error;
        }
        return true;

    }

    /**
     * Copia i file da una directory all'altra
     */
    public static function copy_files($sourceDir, $destinationDir) {
        // Crea la directory di destinazione se non esiste
        if (!file_exists($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }
    
        // Ottieni il percorso assoluto della directory di destinazione
        $destPath = realpath($destinationDir);
        
        // Apri la directory sorgente
        $dir = opendir($sourceDir);
        
        // Leggi il contenuto della directory
        while (($file = readdir($dir)) !== false) {
            // Salta i file/cartelle che iniziano con . (nascosti)
            if (strpos($file, '.') === 0) {
                continue;
            }
            // escludo i file test
            if (substr($file, 0, 5) == 'test-' || strpos($file, '.test') !== false) {
                continue;
            }
            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $file;
            $destFile = $destinationDir . DIRECTORY_SEPARATOR . $file;
            
            // Se è una directory
            if (is_dir($sourcePath)) {
                // Salta se la directory di destinazione è uguale a quella che stiamo processando
                if (realpath($sourcePath) === $destPath) {
                    continue;
                }
                
                if ( realpath($sourcePath) == $sourceDir . DIRECTORY_SEPARATOR .'customizations')  {
                    mkdir($destPath.DIRECTORY_SEPARATOR.'customizations', 0755, true);
                    if (is_file($sourceDir . DIRECTORY_SEPARATOR .'customizations' . DIRECTORY_SEPARATOR . 'readme.md')) {
                        copy($sourceDir . DIRECTORY_SEPARATOR .'customizations' . DIRECTORY_SEPARATOR . 'readme.md', $destPath.DIRECTORY_SEPARATOR.'customizations' . DIRECTORY_SEPARATOR . 'readme.md');
                    }
                    continue;
                }
                // Skip milk-admin-v* directories
                if (preg_match('/milk-admin-v.*$/', $file)) {
                    continue;
                }
                
                // Skip media directory
                if (realpath($sourcePath) == $sourceDir . DIRECTORY_SEPARATOR .'media')  {
                    mkdir($destPath.DIRECTORY_SEPARATOR.'media', 0755, true);
                    if (is_file($sourceDir . DIRECTORY_SEPARATOR .'media' . DIRECTORY_SEPARATOR . 'index.html')) {
                        copy($sourceDir . DIRECTORY_SEPARATOR .'media' . DIRECTORY_SEPARATOR . 'index.html', $destPath.DIRECTORY_SEPARATOR.'media' . DIRECTORY_SEPARATOR . 'index.html');
                    }
                    continue;
                }else if(realpath($sourcePath) == $sourceDir . DIRECTORY_SEPARATOR .'storage' ) {
                    // creo la directory
                    mkdir($destPath.DIRECTORY_SEPARATOR.'storage', 0755, true);
                    if (is_file($sourceDir . DIRECTORY_SEPARATOR .'storage' . DIRECTORY_SEPARATOR . '.htaccess')) {
                        copy($sourceDir . DIRECTORY_SEPARATOR .'storage' . DIRECTORY_SEPARATOR . '.htaccess', $destPath.DIRECTORY_SEPARATOR.'storage' . DIRECTORY_SEPARATOR . '.htaccess');
                    } else if (is_file($sourceDir . DIRECTORY_SEPARATOR .'storage' . DIRECTORY_SEPARATOR . 'htaccess')) {
                        copy($sourceDir . DIRECTORY_SEPARATOR .'storage' . DIRECTORY_SEPARATOR . 'htaccess', $destPath.DIRECTORY_SEPARATOR.'storage' . DIRECTORY_SEPARATOR . '.htaccess');
                    }
                    if (is_file($sourceDir . DIRECTORY_SEPARATOR .'storage' . DIRECTORY_SEPARATOR . 'index.html')) {    
                        copy($sourceDir . DIRECTORY_SEPARATOR .'storage' . DIRECTORY_SEPARATOR . 'index.html', $destPath.DIRECTORY_SEPARATOR.'storage' . DIRECTORY_SEPARATOR . 'index.html');
                    }
                    if (is_file($sourceDir . DIRECTORY_SEPARATOR .'storage' . DIRECTORY_SEPARATOR .'autoload_cache.php')) {    
                        copy($sourceDir . DIRECTORY_SEPARATOR .'storage' . DIRECTORY_SEPARATOR  .'autoload_cache.php', $destPath.DIRECTORY_SEPARATOR.'storage' . DIRECTORY_SEPARATOR  .'autoload_cache.php');
                    }
                    continue;
                }
                
                // Copia ricorsivamente il contenuto della directory
                self::copy_files($sourcePath, $destFile);
            } 
            // Se è un file
            elseif (is_file($sourcePath)) {
                copy($sourcePath, $destFile);
            }
        }
        
        closedir($dir);
    }

    public static function remove_directory($path) {
        if (!file_exists($path)) {
            throw new \Exception("Il percorso specificato non esiste: " . $path);
        }
    
        // Ottieni una lista di tutti i file e directory nel percorso
        $files = array_diff(scandir($path), array('.', '..'));
    
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) {
                // Se è una directory, chiamata ricorsiva
                self::remove_directory($fullPath);
            } else {
                // Se è un file, eliminalo
                if (!unlink($fullPath)) {
                    throw new \Exception("Impossibile eliminare il file: " . $fullPath);
                }
            }
        }

        if (!rmdir($path)) {
            throw new \Exception("Impossibile eliminare la directory: " . $path);
        }
    
        return true;
    }

    /**
     * Estrae un file ZIP in una directory
     * @param string $zipFile Percorso del file ZIP
     * @param string $extractTo Directory di destinazione
     * @return bool|string True se successo, stringa errore se fallisce
     */
    public static function extract_zip($zipFile, $extractTo) {
        if (!file_exists($zipFile)) {
            return "File ZIP non trovato: $zipFile";
        }

        if (!class_exists('ZipArchive')) {
            return "Estensione ZIP di PHP non disponibile";
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipFile);

        if ($result !== TRUE) {
            return "Impossibile aprire il file ZIP: " . self::get_zip_error($result);
        }

        // Crea la directory di destinazione se non esiste
        if (!is_dir($extractTo)) {
            mkdir($extractTo, 0755, true);
        }

        $extracted = $zip->extractTo($extractTo);
        $zip->close();

        if (!$extracted) {
            return "Errore durante l'estrazione del file ZIP";
        }

        return true;
    }

    /**
     * Ottiene il messaggio di errore per ZipArchive
     */
    private static function get_zip_error($code) {
        switch($code) {
            case \ZipArchive::ER_OK: return 'Nessun errore';
            case \ZipArchive::ER_MULTIDISK: return 'Archivi multi-disco non supportati';
            case \ZipArchive::ER_RENAME: return 'Rinomina fallita';
            case \ZipArchive::ER_CLOSE: return 'Chiusura archivio fallita';
            case \ZipArchive::ER_SEEK: return 'Errore di seek';
            case \ZipArchive::ER_READ: return 'Errore di lettura';
            case \ZipArchive::ER_WRITE: return 'Errore di scrittura';
            case \ZipArchive::ER_CRC: return 'Errore CRC';
            case \ZipArchive::ER_ZIPCLOSED: return 'Archivio chiuso';
            case \ZipArchive::ER_NOENT: return 'File non trovato';
            case \ZipArchive::ER_EXISTS: return 'Il file esiste già';
            case \ZipArchive::ER_OPEN: return 'Impossibile aprire il file';
            case \ZipArchive::ER_TMPOPEN: return 'Impossibile creare file temporaneo';
            case \ZipArchive::ER_ZLIB: return 'Errore Zlib';
            case \ZipArchive::ER_MEMORY: return 'Errore di allocazione memoria';
            case \ZipArchive::ER_CHANGED: return 'Entry modificata';
            case \ZipArchive::ER_COMPNOTSUPP: return 'Metodo di compressione non supportato';
            case \ZipArchive::ER_EOF: return 'Fine del file prematura';
            case \ZipArchive::ER_INVAL: return 'Argomento non valido';
            case \ZipArchive::ER_NOZIP: return 'Non è un archivio ZIP';
            case \ZipArchive::ER_INTERNAL: return 'Errore interno';
            case \ZipArchive::ER_INCONS: return 'Archivio ZIP inconsistente';
            case \ZipArchive::ER_REMOVE: return 'Impossibile rimuovere il file';
            case \ZipArchive::ER_DELETED: return 'Entry eliminata';
            default: return "Errore sconosciuto ($code)";
        }
    }

    /**
     * Copia i file per l'aggiornamento, escludendo config.php e storage
     */
    public static function copy_update_files($sourceDir, $destinationDir) {
        // Crea la directory di destinazione se non esiste
        if (!file_exists($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }
    
        // Ottieni il percorso assoluto della directory di destinazione
        $destPath = realpath($destinationDir);
        
        // Apri la directory sorgente
        $dir = opendir($sourceDir);
        
        // Leggi il contenuto della directory
        while (($file = readdir($dir)) !== false) {
            // Salta i file/cartelle che iniziano con . (nascosti)
            if (strpos($file, '.') === 0) {
                continue;
            }
            
            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $file;
            $destFile = $destinationDir . DIRECTORY_SEPARATOR . $file;
            
            // Calcola il percorso relativo
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $sourcePath);
            
            // Salta la directory storage
            if ($file === 'storage' || strpos($relativePath, 'storage' . DIRECTORY_SEPARATOR) === 0) {
                continue;
            }
            
            // Salta config.php
            if ($file === 'config.php') {
                continue;
            }
            
            // Se è una directory
            if (is_dir($sourcePath)) {
                // Copia ricorsivamente il contenuto della directory
                self::copy_update_files($sourcePath, $destFile);
            } 
            // Se è un file
            elseif (is_file($sourcePath)) {
                // Crea le directory necessarie se non esistono
                $destDir = dirname($destFile);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                copy($sourcePath, $destFile);
            }
        }
        
        closedir($dir);
    }
}

Get::bind('install', Install::class);