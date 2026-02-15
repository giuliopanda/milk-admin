<?php
namespace Modules\Install\Installer;

use App\{Hooks, Token, File};
use Modules\Install\Install;

!defined('MILK_DIR') && die(); // Avoid direct access

Hooks::set('install.execute_config', function($data) {
        $ris = Token::generateKeyPair();
        $auth_data = ['api_token'=> bin2hex(random_bytes(32))];

        if ($ris) {
            $data['private_key'] = $ris['private_key'];
            $data['public_key'] = $ris['public_key'];
            try {
                File::putContents(STORAGE_DIR.'/private-key.pem', $ris['private_key']);
                File::putContents(STORAGE_DIR.'/public-key.pem', $ris['public_key']);
                $auth_data['jwt_private_key'] = 'private-key.pem';
                $auth_data['jwt_public_key'] = 'public-key.pem';
            } catch (\App\Exceptions\FileException $e) {
                throw new \Exception("Failed to save JWT keys: " . $e->getMessage());
            }
        }

        Install::setConfigFile('API', $auth_data);
        return $data;
});