<?php
use App\{API, Config, Get, Hooks, Lang, Logs, Settings};

define('MILK_API_CONTEXT', true);

require 'milkadmin.php';

require MILK_DIR . '/autoload.php';

// Load modules
Get::loadModules();

// Initialize system
Hooks::run('api-init');

// Load lang files
Lang::loadPhpFile(MILK_DIR.'/Lang/'.Get::userLocale().'.php');

// Find API endpoint (only page, like normal router)
$page = $_REQUEST['page'] ?? '';

// Clean parameter (alphanumeric, -, _ and /)
$page = preg_replace('/[^a-zA-Z0-9-_\/]/', '', $page);

if (empty($page)) {
    API::errorResponse('Page required', 400);
}

try {
    // Run API endpoint
    if (!API::run($page)) {
        API::errorResponse("API endpoint '$page' not found", 404);
    }

} catch (\Exception $e) {
    // Error log
    Logs::set('api', 'ERROR', 'API Exception: ' . $e->getMessage());

    // Error response
    API::errorResponse('Internal server error', 500);
}

// Final cleanup
Hooks::run('end-api');

// Output the buffered response if available
if (API::hasBufferedResponse()) {
    API::outputResponse();
}

// Clean up
Settings::save();
Get::db()->close();
Get::db2()->close();
