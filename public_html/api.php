<?php
use App\{API, Config, Get, Hooks, Lang, Logs, Settings};

define('MILK_API_CONTEXT', true);

require 'milkadmin.php';

require MILK_DIR . '/autoload.php';

// Carica i moduli
Get::loadModules();

// Inizializza il sistema
Hooks::run('api-init');

// Carica i file di lingua
Lang::loadIniFile(MILK_DIR . '/lang/'.Config::get('lang', '').'.ini');
Lang::loadIniFile(MILK_DIR . '/lang/'.Config::get('lang', '').'.adding.ini');

// Trova l'endpoint API (solo page, come il router normale)
$page = $_REQUEST['page'] ?? '';

// Pulisci il parametro (caratteri alfanumerici, -, _ e /)
$page = preg_replace('/[^a-zA-Z0-9-_\/]/', '', $page);

if (empty($page)) {
    API::errorResponse('Page required', 400);
}

try {
    // Esegui l'endpoint API
    if (!API::run($page)) {
        API::errorResponse("API endpoint '$page' not found", 404);
    }
    
} catch (\Exception $e) {
    // Log dell'errore
    Logs::set('api', 'ERROR', 'API Exception: ' . $e->getMessage());
    
    // Risposta di errore
    API::errorResponse('Internal server error', 500);
}

// Pulizia finale
Hooks::run('end-api');
Settings::save();
Get::db()->close();
Get::db2()->close();
