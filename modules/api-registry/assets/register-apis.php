<?php
namespace Modules\ApiRegistry;

use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

// This file is used to register APIs with the ApiRegistry module.
// Add your API registrations here using ApiRegistryContract::register_api().

// Example:
/*
ApiRegistryContract::register_api('example_api', [
    'description' => 'Example API endpoint',
    'endpoint' => '/api/v1/example',
    'methods' => ['GET', 'POST'],
    'auth_required' => true,
    'handler' => function($request) {
        // Handle API request
        return ['status' => 'success'];
    }
]);
*/
