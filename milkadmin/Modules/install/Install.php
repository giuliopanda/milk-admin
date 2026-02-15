<?php
namespace Modules\Install;

use App\{Get, Hooks, File, Config, Cli};

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
    public static function setErrors($module, $errors, $new_errors) {
        if (!is_array($errors)) {
            $errors = [];
        }
        if (is_array($new_errors) && count($new_errors) > 0) {
            $errors = array_merge($errors, [$module => $new_errors]);
        }
        return $errors;
    }

    public static function printErrors($errors) {
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
    public static function setConfigFile($title, $configs) {
        /** stampo i config */
        self::$configs[] = ['title'=>$title, 'params'=>$configs];
    }

    /**
     * Salvo il file di configurazione   
     */
    public static function saveConfigFile() {
        $conf = [];
        $session_params = [];
        foreach (self::$configs as $config) {
            if ($config['title']  != '') {
                $config['title'] = str_replace("\n", "\n * ", $config['title']);
                $conf[] = "/**\n * ".$config['title']."\n */";
            }
            $session_params = array_merge($session_params, $config['params']);
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

        $_SESSION['installation_params'] = $session_params;
        $html_conf = "<?php\nuse App\Config;\n!defined('MILK_DIR') && die(); // Avoid direct access\n\n";
        $conf = implode("\n", $conf);

        $html_conf .= "\$conf = [];\n\n".$conf."\nConfig::setAll(\$conf);";
        // copio il file prima di salvarlo
        try {
            File::putContents(LOCAL_DIR."/config.php", $html_conf);
        } catch (\App\Exceptions\FileException $e) {
            throw new \Exception("Failed to save config file: " . $e->getMessage());
        }
        // TODO verificare se il file funziona?!?!

    }

    /**
     * Execute sql file
     * @param string $file
     * @param array $variables Array of variables to replace in the sql file es. ['[var1]'=>'value1', '[var2]'=>'value2']
     * return true or error
     */
    public static function executeSqlFile($file, $variables = []) {
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
        if ($db->hasError()) { 
            return $db->getLastError();
        }
        return true;

    }

    /**
     * Copia i file da una directory all'altra
     */
    public static function copyFiles($sourceDir, $destinationDir) {
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
                if (Hooks::run('install.copy_files', false, $sourcePath, $destPath)) {
                    continue;
                }
              
                // Skip milk-admin-v* directories
                if (preg_match('/milk-admin-v.*$/', $file)) {
                    continue;
                }
               
                // Copia ricorsivamente il contenuto della directory
                self::copyFiles($sourcePath, $destFile);
            } 
            // Se è un file
            elseif (is_file($sourcePath)) {
                copy($sourcePath, $destFile);
            }
        }
        
        closedir($dir);
    }

    public static function removeDirectory($path) {
        if (!file_exists($path)) {
            throw new \Exception("The directory: " . $path . " doesn't exist");
        }
    
        // Ottieni una lista di tutti i file e directory nel percorso
        $files = array_diff(scandir($path), array('.', '..'));
    
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) {
                // Se è una directory, chiamata ricorsiva
                self::removeDirectory($fullPath);
            } else {
                // Se è un file, eliminalo
                if (!@unlink($fullPath)) {
                    Cli::error("It's not possible to delete the file: " . $fullPath);
                    die();
                }
            }
        }

        if (!rmdir($path)) {
            Cli::error("It's not possible to delete the directory: " . $path);
            die();
        }
    
        return true;
    }

    /**
     * Estrae un file ZIP in una directory
     * @param string $zipFile Percorso del file ZIP
     * @param string $extractTo Directory di destinazione
     * @return bool|string True se successo, stringa errore se fallisce
     */
    public static function extractZip($zipFile, $extractTo) {
        if (!file_exists($zipFile)) {
            return "The file ZIP: $zipFile doesn't exist";
        }

        if (!class_exists('ZipArchive')) {
            return "The extension ZIP of PHP is not available";
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipFile);

        if ($result !== TRUE) {
            return "It's not possible to open the file ZIP: " . self::getZipError($result);
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
    private static function getZipError($code) {
        switch($code) {
            case \ZipArchive::ER_OK: return 'No error';
            case \ZipArchive::ER_MULTIDISK: return 'Multi-disk archives not supported';
            case \ZipArchive::ER_RENAME: return 'Rename failed';
            case \ZipArchive::ER_CLOSE: return 'Archive closing failed';
            case \ZipArchive::ER_SEEK: return 'Seek error';
            case \ZipArchive::ER_READ: return 'Read error';
            case \ZipArchive::ER_WRITE: return 'Write error';
            case \ZipArchive::ER_CRC: return 'CRC error';
            case \ZipArchive::ER_ZIPCLOSED: return 'Archive closed';
            case \ZipArchive::ER_NOENT: return 'File not found';
            case \ZipArchive::ER_EXISTS: return 'The file already exists';
            case \ZipArchive::ER_OPEN: return 'It\'s not possible to open the file';
            case \ZipArchive::ER_TMPOPEN: return 'It\'s not possible to create temporary file';
            case \ZipArchive::ER_ZLIB: return 'Zlib error';
            case \ZipArchive::ER_MEMORY: return 'Memory allocation error';
            case \ZipArchive::ER_CHANGED: return 'Entry modified';
            case \ZipArchive::ER_COMPNOTSUPP: return 'Compression method not supported';
            case \ZipArchive::ER_EOF: return 'Premature end of file';
            case \ZipArchive::ER_INVAL: return 'Invalid argument';
            case \ZipArchive::ER_NOZIP: return 'Not a zip archive';
            case \ZipArchive::ER_INTERNAL: return 'Internal error';
            case \ZipArchive::ER_INCONS: return 'Zip archive inconsistent';
            case \ZipArchive::ER_REMOVE: return 'It\'s not possible to remove the file';
            case \ZipArchive::ER_DELETED: return 'Entry deleted';
            default: return "Unknown error ($code)";
        }
    }

    /**
     * Copy files for update, excluding config.php and Storage
     */
    public static function copyUpdateFiles($sourceDir, $destinationDir) {
        // Create the destination directory if it doesn't exist
        if (!file_exists($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }
    
        // Get the absolute path of the destination directory
        $destPath = realpath($destinationDir);
        
        // Open the source directory
        $dir = opendir($sourceDir);
        
        // Read the content of the directory
        while (($file = readdir($dir)) !== false) {
            // Skip files/folders that start with . (hidden)
            if (strpos($file, '.') === 0) {
                continue;
            }
            
            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $file;
            $destFile = $destinationDir . DIRECTORY_SEPARATOR . $file;
            
            // Calculate the relative path
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $sourcePath);
            
            // Skip the Storage directory
            if ($file === 'storage' || strpos($relativePath, 'Storage' . DIRECTORY_SEPARATOR) === 0) {
                continue;
            }
            
            // Skip config.php
            if ($file === 'config.php') {
                continue;
            }
            if ($file == 'milkadmin.php') {
                continue;
            }
            
            // If it's a directory
            if (is_dir($sourcePath)) {
                // Recursively copy the directory content
                self::copyUpdateFiles($sourcePath, $destFile);
            } 
            // If it's a file
            elseif (is_file($sourcePath)) {
                // Create necessary directories if they don't exist
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