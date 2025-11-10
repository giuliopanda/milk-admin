<?php
namespace Modules\Docs\Assets;

use App\Route;

if (!defined('MILK_DIR')) die();

// Inizializzo gli array per i differenti tipi di risultati
$results_name_and_content = []; // Risultati sia nel nome che nel contenuto
$results_name_only = [];        // Risultati solo nel nome del file
$results_content_only = [];     // Risultati solo nel contenuto

ob_start();
$array_pages = scandir(MILK_DIR . '/modules/docs/pages');
$search = ($_REQUEST['search'] ?? '');

// Se la ricerca è vuota, non proseguire
if (empty($search)) {
    $content = ob_get_clean();
    echo json_encode(['html'=> '<div>Inserisci un termine di ricerca</div>']);
    exit;
}

// Funzione per ottenere il nome del file senza percorso
function getFileName($path) {
    $parts = explode('/', $path);
    return end($parts);
}

// Costruzione dell'array delle pagine
foreach ($array_pages as $key => $value) {
    if ($value == '.' || $value == '..') unset($array_pages[$key]);
    if (is_dir(MILK_DIR . '/Modules/Docs/Pages/' . $value)) {
        $sub_pages = scandir(MILK_DIR . '/Modules/Docs/Pages/' . $value);
        foreach ($sub_pages as $sub_key => $sub_value) {
            if ($sub_value == '.' || $sub_value == '..') {
                unset($sub_pages[$sub_key]);
                continue;
            }
            $array_pages[$key.str_replace("./", "", $sub_key)] = '/Modules/Docs/Pages/' . $value . '/' . str_replace([".php", "./"], "", $sub_value); 
        }
    } else {
        $array_pages[str_replace("./", "", $key)] = '/Modules/Docs/Pages/'.str_replace([".php", "./"], "", $value);
    }
}

// Effettua la ricerca e classifica i risultati
foreach ($array_pages as $page) {
    if (!is_file(MILK_DIR . $page . ".php")) continue;
    
    $file_path = MILK_DIR . $page . ".php";
    $file_content = file_get_contents($file_path);
    $file_name = getFileName($page);
    
    $name_match = stripos($file_name, $search) !== false;
    $content_match = stripos($file_content, $search) !== false;
    
    if ($name_match && $content_match) {
        // Match sia nel nome che nel contenuto
        $results_name_and_content[$page] = [
            'name' => $file_name,
            'path' => $page,
            'content' => $file_content
        ];
    } elseif ($name_match) {
        // Match solo nel nome
        $results_name_only[$page] = [
            'name' => $file_name,
            'path' => $page,
            'content' => $file_content
        ];
    } elseif ($content_match) {
        // Match solo nel contenuto
        $results_content_only[$page] = [
            'name' => $file_name,
            'path' => $page,
            'content' => $file_content
        ];
    }
}

// Funzione per stampare i risultati trovati
function printResult($page, $file_info, $search) {
    echo '<h6><a href="' . Route::url('?page=docs&action=' . $page) . '">' . str_replace('/modules/docs/pages', '', $page) . '</a></h6>';
    
    $box = [];
    
    // Trova le righe che contengono la parola di ricerca
    $lines = explode("\n", $file_info['content']);
    foreach ($lines as $count => $line) {
        if (count($box) > 4) {
            $box[] = '<div style="border:1px solid #ccc; padding:10px; margin:5px 0; font-size:.8rem">... </div>';
            break;
        }
        if (stripos($line, $search) !== false && stripos($line, 'require') === false && stripos($line, 'include') === false) {
            $box[] = echoLine($line ?? '', $search, $count);
        }
    }
    $box = array_filter($box);
    if (!empty($box)) {
        echo '<div style="border:1px solid #ccc; padding:10px; margin:5px 0; font-size:.8rem">' . implode('', $box) . '</div>';
    }
}

// 1. Risultati sia nel nome che nel contenuto
if (!empty($results_name_and_content)) {
    foreach ($results_name_and_content as $page => $file_info) {
        printResult($page, $file_info, $search);
    }
}

// 2. Risultati solo nel nome
if (!empty($results_name_only)) {
    foreach ($results_name_only as $page => $file_info) {
        printResult($page, $file_info, $search);
    }
}

// 3. Risultati solo nel contenuto
if (!empty($results_content_only)) {
    foreach ($results_content_only as $page => $file_info) {
        printResult($page, $file_info, $search);
    }
}

// Se non ci sono risultati
if (empty($results_name_and_content) && empty($results_name_only) && empty($results_content_only)) {
    echo '<div>No results found for "' . _p($search) . '"</div>';
}

// Funzione per evidenziare la riga con la parola di ricerca
function echoLine($string, $search, $row) {
    $string = strip_tags($string);
    if (strlen($string) > 500) {
        $pos = stripos($string, $search);
        if ($pos > 250) {
            $string = substr($string, $pos - 250, 500);
        } else {
            $string = substr($string, 0, 500);
        }
    }
    // se trovo il testo lo evidenzio
    if (stripos($string, $search) !== false) {
        // trovo il primo carattere della stringa
        $pos = stripos($string, $search);
        $replace = substr($string, $pos, strlen($search));
        $string = str_ireplace($search, '<span style="background-color:yellow;">' . $replace . '</span>', $string);
    }
    $string = trim($string);
    if ($string != '') {
        ob_start();
        ?><div><span style="color:#ccc; width:70px;"><?php echo $row; ?></span> <?php echo $string; ?></div><?php
        $string = ob_get_clean();
    }
    return $string;
}

$content = ob_get_clean();
echo json_encode(['html'=> $content]);