<?php
namespace Theme\Plugins\Editor;

use App\{Hooks, Route};

 
// i file .class.php sono classi che vengono caricate in automatico all'inizio dell'esecuzione
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Upload files devo gestire gli upload automatici dei file.
 * sicurezza:
 * - Chiamo un nome univoco di upload che deve essere istanziato quando creo il campo e che 
 * definisce i permessi, il token di sicurezza, max file size, e il tipo di file accettato
 */

 class EditorUploadFiles 
 {
    function __construct() {
        // inizializzo il campo
        Route::set('editor-upload-file', [$this, 'uploadFile']);
    }

    function uploadFile() {
        header('Content-Type: application/json');
        // verifico che i dati siano passati in POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['msg' => 'No POST method', 'success' => false, 'url'=>'']);
            return;
        }
    
        // controllo se il file è stato caricato
        if (!isset($_FILES) || !isset($_FILES['file'])) {
            echo json_encode(['msg' => 'No file uploaded', 'success' => false]);
            return;
        }

        // controllo se il file è stato caricato
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['msg' => 'Upload error', 'success' => false]);
            return;
        }

        $name = $_FILES['file']['name'];

        // altri controlli possono essere fatti con hooks
        $error_msg = Hooks::run("editor_upload_check", '', $_FILES['file']);
        if ($error_msg) {
            echo json_encode(['msg' => $error_msg, 'success' => false]);
            return;
        }

        // controllo il tipo di file $accept ex. image/*
        $accept = Hooks::run("editor_upload_accept", '');
        $accepts = explode(',', $accept);
        foreach ($accepts as $key => $value) {
            $accepts[$key] = trim($value);
        }
        
        if ($accept != '' && self::isFileTypeAccepted($accept, $_FILES['file']['type']) == false) {
            echo json_encode(['msg' => 'Invalid file type ('.$accept.')', 'success' => false]);
            return;
        }

        $max_size = min(self::returnBytes(ini_get('post_max_size')), self::returnBytes(ini_get('upload_max_filesize')));
        
        $max_size = Hooks::run("editor_upload_maxsize", $max_size);
        // controllo se il file è stato caricato
        if ($_FILES['file']['size'] > $max_size && $max_size > 0) {
            echo json_encode(['msg' => 'File too large ('.self::humanFileSize($_FILES['file']['size']).' > '.self::humanFileSize($max_size).')', 'success' => false]);
            return;
        }

        $temp_dir = MILK_DIR.'/media/';
        // la cartella dove salvare il file
        $temp_dir = Hooks::run('editor_upload_save_dir',  $temp_dir);

        // se non esiste la directory la creo
        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0750, true);
        }
        $count_name = 0;
        do {
            $name_without_ext = pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
            $file_name = _raz(substr(strtolower($name_without_ext),0,30));
            if ($count_name > 0) {
                $file_name .= str_pad($count_name, 3, '0', STR_PAD_LEFT);
            }
            $count_name++;
            $file_name .= ".".pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $file_name = Hooks::run('editor_upload_file_name',  $file_name, $_FILES['file']);
        } while(is_file($temp_dir."/".$file_name));

        $file_path = $temp_dir."/".$file_name;
        $url = Route::url()."/media/".$file_name;

        if (!move_uploaded_file ($_FILES['file']['tmp_name'], $file_path)) {
            echo json_encode(['msg' => 'Error moving file', 'success' => false]);
            return;
        }
        // metto i permessi al file 664
        $permission = Hooks::run('upload_permission_file_'.$name, 0664);
        try {
            chmod($file_path, $permission);
        } catch (\Exception $e) {
            // non faccio nulla
        }

        echo json_encode(['url' => $url, 'success' => true]);
    }

    // human file size
    static function humanFileSize($size) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $size > 1024; $i++) {
            $size = (float)$size;
            $size /= 1024;
        }
        return round($size, 2).$units[$i];
    }

    static private function returnBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }

    static function isFileTypeAccepted($accept, $fileType) {
        // Spezza la stringa $accept in un array di tipi MIME
        $acceptedTypes = explode(',', $accept);
    
        foreach ($acceptedTypes as $type) {
            $type = trim($type); // Rimuove spazi superflui
    
            // Controllo wildcard, es. 'image/*'
            if (strpos($type, '/*') !== false) {
                // Estrarre il prefisso, es. 'image/'
                $prefix = rtrim($type, '*');
                if (strpos($fileType, $prefix) === 0) {
                    return true;
                }
            } else {
                // Controllo tipo MIME specifico, es. 'image/png'
                if ($fileType === $type) {
                    return true;
                }
            }
        }
    
        // Se nessun tipo MIME corrisponde
        return false;
    }

 }

new EditorUploadFiles();