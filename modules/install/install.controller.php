<?php 
namespace Modules\Install;
use MilkCore\{Hooks, Theme, Route, Get, Permissions, Config, Cli, MessagesHandler, Settings, File, CacheRebuilder};

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * ITO Installation Module
 * The installation process of a module consists of:
 * - loading all the HTML of the modules to be installed and displaying them in a page
 * - When saving, the installation code of each module is executed
 * - Finally, the configuration file is created and the installation completion page is loaded
 * 
 * The template is empty because it doesn't need sidebars
 * Modules must have an installation file to be installed.
 * There will be an install table that keeps track of installed modules and their versions
 * 
 * @package     Modules
 * @subpackage  install
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @version     1.0.0
 */


// Aggiorno subito le tabelle del model per sicurezza
if (defined('NEW_VERSION') && NEW_VERSION >  Config::get('version')) {
    if (NEW_VERSION < '250600') {
        // update user tables
        $user_update_version = Settings::get('user_update_version');
        if ($user_update_version != NEW_VERSION) {
            require_once(MILK_DIR.'/modules/auth/auth.model.php');
            $model = new \Modules\Auth\UserModel();
            $model2 = new \Modules\Auth\SessionModel();
            $model3 = new \Modules\Auth\LoginAttemptsModel();
            $model->build_table();
            $model2->build_table();
            $model3->build_table();
            Settings::set('user_update_version', NEW_VERSION);
            Settings::save();
        }
    }
}

Route::set('install', function() {

    $files = glob(__DIR__.'/installer/*.php');
    foreach ($files as $file) {
        require_once $file;
    }

    $action = $_REQUEST['action'] ?? '';
    $model = new InstallModel();
    // Check: if the version doesn't exist, it's a new installation; otherwise, it's an update
    $version =  Config::get('version'); 
    if ($version == null) {
        // installation
        switch ($action) {
            case 'save-config':
              
                if ($model->check_data($_REQUEST)) {
                    // save configuration data
                    $model->execute_install($_REQUEST);
                    // save the configuration file
                    Install::save_config_file();
                    Get::theme_page('empty', __DIR__."/install_done.page.php");
                // $model->save_config($_REQUEST); 
                } else {
                    $html = $model->get_html_modules();
                    Get::theme_page('empty', __DIR__."/install.page.php", ['html' => $html]);
                }
                break;
            
            default:
                // set the page title
                Theme::set('header.title', 'Ito Installation');
                Get::theme_page('empty', __DIR__."/install.page.php", 
                ['html' => $model->get_html_modules()]);
              
                break;
        }
    } else {
        if (Permissions::check('_user.is_admin')) {
            // update
            $temp_dir = Get::temp_dir();
            $update_file = $temp_dir . '/update-to-do.zip';
            $update_dir = $temp_dir . '/update-extracted';
            
            // First, check if there is an update to process
            if (file_exists($update_file) && $action != 'upload-update') {
                // Process the update
                $update_result = process_update($update_file, $update_dir);
                
                if ($update_result === true) {
                    // Update completed successfully
                    MessagesHandler::add_success(_r('Update completed successfully. The system has been upgraded to the new version.'));
                    
                    // DO NOT run execute_update here – it will run on the next load
                    // when NEW_VERSION > $version
                    
                    // Reload the page to show the new version
                    Route::redirect(['page' => 'install', 'action' => 'update-step3']);
                } else {
                    // Error during update
                    MessagesHandler::add_error(_r('Error during update: ') . $update_result);
                    
                    // Remove update files in case of error
                    if (file_exists($update_file)) {
                        unlink($update_file);
                    }
                    if (is_dir($update_dir)) {
                        try {
                            Install::remove_directory($update_dir);
                        } catch (\Exception $e) {
                            // Ignore directory removal errors
                        }
                    }
                }
            }
            
            // Action handling
            switch ($action) {
                case 'upload-update':
                    // Handle update file upload
                    if (isset($_FILES['update_file']) && $_FILES['update_file']['error'] === UPLOAD_ERR_OK) {
                        $uploaded_file = $_FILES['update_file']['tmp_name'];
                        $file_name = $_FILES['update_file']['name'];
                        
                        // Check that it is a ZIP file
                        $file_info = pathinfo($file_name);
                        if (strtolower($file_info['extension']) !== 'zip') {
                            Route::redirect_error(['page' => 'install'], _r('The uploaded file must be in ZIP format.'));
                        }
                        
                        // Check backup confirmation
                        if (!isset($_POST['confirm_backup']) || $_POST['confirm_backup'] != '1') {
                            Route::redirect_error(['page' => 'install'], _r('You must confirm you have made a backup before proceeding.'));
                        }
                        
                        // Move the file to the temporary directory
                        if (!move_uploaded_file($uploaded_file, $update_file)) {
                            Route::redirect_error(['page' => 'install'], _r('Error during update file upload.'));
                        }
                    } else {
                        Route::redirect_error(['page' => 'install'], _r('No file uploaded or upload error.'));
                    }
                    // update-step2 non esiste, ma se c'è un file zippato lo rieseguo
                    Route::redirect(['page' => 'install', 'action' => 'update-step2']);
                    break;
                case 'update-step3':     
                    $model = new InstallModel();
                    // Files have already been updated, now update the DB/config
                    $html = '<p>'.sprintf(_r('Version %s has been updated.'), NEW_VERSION).'</p>';
                    $model->execute_update();
                    
                    // Update the version in the config
                    // Reload the current config file
                    $config_content = file_get_contents(MILK_DIR.'/config.php');
                    
                    // Replace ONLY the version line
                    // Look exactly for the line $conf['version'] = 'value';
                    $config_content = preg_replace(
                        "/^\s*\\\$conf\['version'\]\s*=\s*'[^']*';\s*$/m",
                        " \$conf['version'] = '".NEW_VERSION."';",
                        $config_content
                    );
                    
                    // Save the file
                    File::put_contents(MILK_DIR.'/config.php', $config_content);
                    
                    // Also update in memory
                    Config::set('version', NEW_VERSION);
                    $html = '<p>'._rh('Update completed successfully.').'</p>';
                    Get::theme_page('default', __DIR__."/update.page.php", ['html' => $html]);
                    break;
                default:
                    // Show update page
                    // the new version is found in the NEW_VERSION constant
                    if (defined('NEW_VERSION') && NEW_VERSION > $version) {
                        $model = new InstallModel();
                        // Files have already been updated, now update the DB/config
                        $html = '<p>'.sprintf(_r('Version %s has been updated.'), NEW_VERSION).'</p>';
                        $model->execute_update();
                        
                        // Update the version in the config
                        // Reload the current config file
                        $config_content = file_get_contents(MILK_DIR.'/config.php');
                        
                        // Replace ONLY the version line
                        // Look exactly for the line $conf['version'] = 'value';
                        $config_content = preg_replace(
                            "/^\s*\\\$conf\['version'\]\s*=\s*'[^']*';\s*$/m",
                            " \$conf['version'] = '".NEW_VERSION."';",
                            $config_content
                        );
                        
                        // Save the file
                        File::put_contents(MILK_DIR.'/config.php', $config_content);
                        
                        // Also update in memory
                        Config::set('version', NEW_VERSION);
                        $html = '<p>'._rh('Update completed successfully.').'</p>';
                    } else if (NEW_VERSION == $version) {
                        // nothing to update
                        $html = '<p>'._rh('Everything is fine.').'</p>';
                        $html .= '<p>'.sprintf(_r('%s has already been installed'), NEW_VERSION).'</p>';
                    } else {
                        $html = '<p>'.sprintf(_r('The system had version %s installed. Now a new less recent version %s has been proposed'), $version, NEW_VERSION).'</p>';
                    }
                    Get::theme_page('default', __DIR__."/update.page.php", ['html' => $html]);
                    break;
            }
        } else {
            $queryString = Route::get_query_string();
            Route::redirect('?page=deny&redirect='.Route::urlsafeB64Encode($queryString));
        }
    }
    
});

/**
 * Process the system update
 * 
 * @param string $update_file Path to the ZIP update file
 * @param string $update_dir Temporary directory for extraction
 * @return bool|string True on success, error message string on failure
 */
function process_update($update_file, $update_dir) {
    require_once __DIR__.'/install.class.php';
    
    // Clean any previous leftovers
    if (is_dir($update_dir)) {
        try {
            Install::remove_directory($update_dir);
        } catch (\Exception $e) {
            // Ignore errors, we will still try
        }
    }
    
    // Extract the ZIP file
    $extract_result = Install::extract_zip($update_file, $update_dir);
    if ($extract_result !== true) {
        return $extract_result;
    }
    
    // Check that files were extracted
    $files = scandir($update_dir);
    $files = array_diff($files, ['.', '..']);
    
    if (count($files) === 0) {
        return _r('The ZIP file is empty.');
    }
    
    // If there is only one main directory, enter it
    if (count($files) === 1) {
        $first_item = reset($files);
        $first_item_path = $update_dir . '/' . $first_item;
        if (is_dir($first_item_path)) {
            $update_dir = $first_item_path;
        }
    }
    
    // Check for required system files
    $required_files = ['index.php', 'milk-core'];
    $missing_files = [];
    
    foreach ($required_files as $required) {
        if (!file_exists($update_dir . '/' . $required)) {
            $missing_files[] = $required;
        }
    }
    
    if (!empty($missing_files)) {
        return sprintf(_r('The update file is not valid. Missing files: %s'), implode(', ', $missing_files));
    }
    
    try {
        // Copy the files excluding config.php and storage
        Install::copy_update_files($update_dir, MILK_DIR);
        
        // Remove temporary files
        unlink($update_file);
        
        // Completely remove extraction directory
        $temp_dir = Get::temp_dir();
        $extract_base_dir = $temp_dir . '/update-extracted';
        if (is_dir($extract_base_dir)) {
            Install::remove_directory($extract_base_dir);
        }
        
        return true;
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 * If a redirect is needed because a new version has been installed,
 * redirect to the installation page.
 */
Hooks::set('init', function() {
    if (Permissions::check('_user.is_admin')) {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] != 'install') {
            $version =  Config::get('version'); 
            if (NEW_VERSION > $version) {
                Route::redirect(['page'=>'install']);
            }
        }
    }
});


Cli::set("build-version", function($custom_version = '') {
    require_once __DIR__.'/install.class.php';
    // Faccio il rebuild della cache dei file
    $cache = new CacheRebuilder();
    $cache->rebuild_cache();
    // Generate new version number
    $version = Config::get('version');
    $count = 0;
    $substr_version = substr($version, 0, 4);
    if ($custom_version != '' && preg_match('/^[0-9]{2}(0[1-9]|1[0-2])([0-9]{2})$/', $custom_version)) {
        $new_version = $custom_version;
    } else {
        if ($substr_version == date('ym')) {
            $count = (int)substr($version, 4);
        }
        do {
            $new_version = date('ym').str_pad($count, 2, '0', STR_PAD_LEFT);
            $count++;
        } while($version == $new_version && $count < 99); 
    }
    if (is_dir(MILK_DIR.'/milk-admin-v'.$new_version)) {
        Cli::echo('Version already exists. I remove it and continue');
        // delete the folder and continue
        Install::remove_directory(MILK_DIR.'/milk-admin-v'.$new_version);
    }
    mkdir(MILK_DIR.'/milk-admin-v'.$new_version);
    if (!is_dir(MILK_DIR.'/milk-admin-v'.$new_version)) {
        Cli::error('Error creating version directory');
        return;
    }
    Install::copy_files(MILK_DIR, MILK_DIR.'/milk-admin-v'.$new_version);
    // overwrite the configuration file
    $new_config = file_get_contents(__DIR__.'/assets/installation-config.example.php');
    File::put_contents(MILK_DIR.'/milk-admin-v'.$new_version.'/config.php', $new_config);
    // update version in milk-core/setup.php
    $setup = file_get_contents(MILK_DIR.'/milk-core/setup.php');
    $setup = str_replace("define('NEW_VERSION', '".$version."');", "define('NEW_VERSION', '".$new_version."');", $setup);
    File::put_contents(MILK_DIR.'/milk-admin-v'.$new_version.'/milk-core/setup.php', $setup);
    Cli::success('New version created in folder: '.MILK_DIR.'/milk-admin-v'.$new_version);
});