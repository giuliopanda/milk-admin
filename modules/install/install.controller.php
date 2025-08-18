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
    
    require_once __DIR__.'/install.service.php';

    $action = $_REQUEST['action'] ?? '';
    
    // Load JavaScript assets
    Theme::set('javascript', Route::url().'/modules/install/assets/install.js');
    $model = new InstallModel();
    
    // Set breadcrumbs for install pages
    $breadcrumbs_html = '';
    if ($action === 'update-modules') {
        $breadcrumbs_html = '<a href="' . Route::url(['page' => 'install']) . '">' . _r('Install') . '</a>';
        $breadcrumbs_html .= ' / <span class="active">' . _r('List of Modules') . '</span>';
    } else {
        $breadcrumbs_html = '<span class="active">' . _r('Install') . '</span>';
        $breadcrumbs_html .= ' / <a href="' . Route::url(['page' => 'install', 'action' => 'update-modules']) . '">' . _r('List of Modules') . '</a>';
    }
    Theme::set('header.breadcrumbs', $breadcrumbs_html);
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
                    Get::theme_page('empty', __DIR__."/views/install_done.page.php");
                // $model->save_config($_REQUEST); 
                } else {
                    $html = $model->get_html_modules();
                    Get::theme_page('empty', __DIR__."/views/install.page.php", ['html' => $html]);
                }
                break;
            
            default:
                // set the page title
                Theme::set('header.title', 'Ito Installation');
                Get::theme_page('empty', __DIR__."/views/install.page.php", 
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
                $update_result = InstallService::process_update($update_file, $update_dir);
                
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
                case 'enable-module':
                    if (isset($_POST['module'])) {
                        $result = InstallService::enable_module($_POST['module']);
                        Get::response_json(['status' => $result['success'] ? 'success' : 'error', 'data' => $result]);
                    }
                    break;
                    
                case 'disable-module':
                    if (isset($_POST['module'])) {
                        $result = InstallService::disable_module($_POST['module']);
                        Get::response_json(['status' => $result['success'] ? 'success' : 'error', 'data' => $result]);
                    }
                    break;
                    
                case 'uninstall-module':
                    if (isset($_POST['module'])) {
                        $result = InstallService::uninstall_active_module($_POST['module']);
                      
                         Get::response_json(['status' => $result['success'] ? 'success' : 'error', 'data' => $result]);
                    } else {
                        Get::response_json(['status' => 'error', 'data' => ['success' => false, 'message' => _r('No module name provided.')]]);
                    }
                    break;
                    
                case 'uninstall-active-module':
                    //php error
                    error_reporting(E_ALL);
                    ini_set('display_errors', 1);

                    if (isset($_REQUEST['module'])) {
                        require_once __DIR__ . '/install.service.php';
                        $result = InstallService::uninstall_active_module($_REQUEST['module']);
                        if ($result['success']) {
                            Route::redirect_success(['page' => 'install', 'action' => 'update-modules'], $result['message']);
                        } else {
                            Route::redirect_error(['page' => 'install', 'action' => 'update-modules'], $result['message']);
                        }
                    }
                    break;
                    
                case 'upload-update':
                    $result = InstallService::handle_upload_update($_FILES, $_POST);
                    if (!$result['success']) {
                        Route::redirect_error($result['redirect'], $result['message']);
                    }
                    Route::redirect($result['redirect']);
                    break;
                    
                case 'upload-module':
                   
                    if (isset($_FILES['module_file'])) {
                        $result = InstallService::handle_module_upload($_FILES, $_POST);
                        
                        if ($result['success']) {
                            Route::redirect_success(['page' => 'install', 'action' => 'update-modules'], $result['message']);
                        } else {
                            Route::redirect_error(['page' => 'install', 'action' => 'update-modules'], $result['message']);
                        }
                    } else {
                        Route::redirect_error(['page' => 'install', 'action' => 'update-modules'], _r('No module file provided.'));
                    }
                    break;
                    
                case 'install-module':
                    if (isset($_POST['module_name'])) {
                        $result = InstallService::attempt_module_installation($_POST['module_name']);
                        Get::response_json(['status' => $result['success'] ? 'success' : 'error', 'data' => $result]);
                    } else {
                        Get::response_json(['status' => 'error', 'data' => ['success' => false, 'message' => _r('No module name provided.')]]);
                    }
                    break;
                    
                case 'update-modules-json':
                    $module_data = InstallService::get_module_status_data();
                    if (!empty($module_data['modules_to_update'])) {
                        $updated_modules = InstallService::execute_module_updates($module_data['modules_to_update']);
                        Get::response_json([
                            'status' => 'success', 
                            'data' => [
                                'success' => true,
                                'message' => _r('Modules updated successfully'),
                                'updated_modules' => $updated_modules,
                                'total_modules' => count($module_data['modules_to_update'])
                            ]
                        ]);
                    } else {
                        Get::response_json([
                            'status' => 'success',
                            'data' => [
                                'success' => true,
                                'message' => _r('No modules need updating'),
                                'updated_modules' => [],
                                'total_modules' => 0
                            ]
                        ]);
                    }
                    break;
                case 'update-modules':
                    $module_data = InstallService::get_module_status_data();
                    
                    // Check for action parameter to perform updates
                    if (isset($_POST['update_modules']) && $_POST['update_modules'] == '1') {
                        InstallService::execute_module_updates($module_data['modules_to_update']);
                        Route::redirect(['page' => 'install', 'action' => 'update-modules']);
                    }
                    
                    $html = InstallService::generate_module_status_html($module_data);
                    Get::theme_page('default', __DIR__."/views/update-modules.page.php", ['html' => $html]);
                    break;
                case 'update-step3':     
                    $model = new InstallModel();
                    $html = InstallService::execute_update_step3($model);
                    Get::theme_page('default', __DIR__."/views/update.page.php", ['html' => $html]);
                    break;
                default:
                    // Show update page
                    // the new version is found in the NEW_VERSION constant
                    if (defined('NEW_VERSION') && NEW_VERSION > $version) {
                        $model = new InstallModel();
                        /*
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
                        */
                        $html = InstallService::execute_update_step3($model);
                    } else if (NEW_VERSION == $version) {
                        // nothing to update
                        $html = '';
                    } else {
                        $html = '<p class="alert alert-danger">'.sprintf(_r('The system had version %s installed. Now a new less recent version %s has been proposed'), $version, NEW_VERSION).'</p>';
                    }
                    Get::theme_page('default', __DIR__."/views/update.page.php", ['html' => $html]);
                    break;
            }
        } else {
            $queryString = Route::get_query_string();
            Route::redirect('?page=deny&redirect='.Route::urlsafeB64Encode($queryString));
        }
    }
    
});



/**
 * Add sidebar link for installation/updates instead of redirecting
 */
Hooks::set('init', function() {
    if (Permissions::check('_user.is_admin')) {
        $version = Config::get('version'); 
        $system_needs_update = NEW_VERSION > $version;
        
        $current_versions = Config::get('module_version');
        $settings_versions = Settings::get('module_version');
        
        // Compare only versions from properly formatted arrays
        $current_versions_only = [];
        $settings_versions_only = [];
        
        foreach ($current_versions as $module => $data) {
            if (is_array($data) && isset($data['version'])) {
                $current_versions_only[$module] = $data['version'];
            }
        }
        
        // Handle null or invalid settings_versions with proper error handling
        if (!is_array($settings_versions)) {
           // \MilkCore\MessagesHandler::add_error("Module version settings could not be loaded properly. Please check system configuration.", 'system');
           Settings::set('module_version', $current_versions);
            $settings_versions = []; // Fallback to empty array
            
        }
        
        foreach ($settings_versions as $module => $data) {
            if (is_array($data) && isset($data['version'])) {
                $settings_versions_only[$module] = $data['version'];
            } elseif (!is_array($data)) {
                $settings_versions_only[$module] = $data; // Legacy format
            }
        }
        
        // Check if any current version is higher than settings version (needs update)
        $modules_need_update = false;
        foreach ($current_versions_only as $module => $current_version) {
            $settings_version = $settings_versions_only[$module] ?? null;
            if ($settings_version !== null && $current_version > $settings_version) {
                $modules_need_update = true;
                break;
            }
        }
        
        // Add sidebar link - always same icon, title and position
        $link_url = ($system_needs_update || $modules_need_update) ? 
            ($system_needs_update ? 
                Route::url(['page' => 'install']) : 
                Route::url(['page' => 'install', 'action' => 'update-modules'])
            ) : 
            Route::url(['page' => 'install', 'action' => 'update-modules']);
        
        $sidebar_link = [
            'url' => $link_url,
            'title' => _r('Installation'),
            'icon' => 'bi bi-gear-fill',
            'order' => 95
        ];
        
        // Add badge if updates are available
        if ($system_needs_update || $modules_need_update) {
            $sidebar_link['badge'] = '!';
            $sidebar_link['badge_color'] = 'danger';
        }
        
        Theme::set('sidebar.links', $sidebar_link);
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