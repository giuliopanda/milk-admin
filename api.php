<?php
namespace MilkCore;

// Configurazione errori PHP per debugging (puoi rimuovere in produzione)
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('MILK_DIR', __DIR__);
require __DIR__ . '/milk-core/autoload.php';

// Carica i moduli
Get::load_modules();

// Inizializza il sistema
Hooks::run('api-init');

// Carica i file di lingua
Lang::load_ini_file(MILK_DIR . '/lang/'.Config::get('lang', '').'.ini');
Lang::load_ini_file(MILK_DIR . '/lang/'.Config::get('lang', '').'.adding.ini');

// Trova l'endpoint API (solo page, come il router normale)
$page = $_REQUEST['page'] ?? '';

// Pulisci il parametro (caratteri alfanumerici, -, _ e /)
$page = preg_replace('/[^a-zA-Z0-9-_\/]/', '', $page);

if (empty($page)) {
    API::error_response('Page required', 400);
}

try {
    // Esegui l'endpoint API
    if (!API::run($page)) {
        API::error_response("API endpoint '$page' not found", 404);
    }
    
} catch (\Exception $e) {
    // Log dell'errore
    Logs::set('api', 'ERROR', 'API Exception: ' . $e->getMessage());
    
    // Risposta di errore
    API::error_response('Internal server error', 500);
}

// Pulizia finale
Hooks::run('end-api');
Settings::save();
Get::db()->close();
Get::db2()->close();
