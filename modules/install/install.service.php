<?php
namespace Modules\Install;
use MilkCore\{Config, Get, Route, Cli, MessagesHandler, Settings, File, Hooks};

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Install Service Classa
 * Static service methods for module management
 * 
 * @package     Modules
 * @subpackage  install
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @version     1.0.0
 */
class InstallService
{
    /**
     * Enable a module by renaming from .module to module
     * 
     * @param string $module Module name
     * @return bool Success status
     */
    public static function enable_module($module)
    {
        // For disabled modules, $module contains the folder path directly (without initial dot)
        $disabled_path = MILK_DIR . '/modules/.' . $module;
        $enabled_path = MILK_DIR . '/modules/' . $module;
        
        if ((is_dir($disabled_path) && !is_dir($enabled_path)) || (is_file($disabled_path) && !is_file($enabled_path))) {
            if (rename($disabled_path, $enabled_path)) {
                // Save enable action to settings
                self::save_module_action($module, 'enabled');
                return ['success' => true, 'message' => sprintf(_r('Module %s enabled successfully'), $module)];
            } else {
                return ['success' => false, 'message' => sprintf(_r('Error enabling module %s'), $module)];
            }
        } else {
            return ['success' => false, 'message' => sprintf(_r("Module %s not found or already enabled"), $module)];
        }
    }
    
    /**
     * Disable a module by renaming from module to .module
     * 
     * @param string $module Module name
     * @return bool Success status
     */
    public static function disable_module($module)
    {
        $module_data = Config::get('module_version');
        
        if (!isset($module_data[$module])) {
            return ['success' => false, 'message' => sprintf(_r('Module %s not found'), $module)];
        }
     
        if (!isset($module_data[$module]['folder'])) {
            return ['success' => false, 'message' => sprintf(_r('Module %s folder not found'), $module)];
        }
        
        $enabled_path = MILK_DIR . '/modules/' . $module_data[$module]['folder'];
        $disabled_path = MILK_DIR . '/modules/.' . $module_data[$module]['folder'];
       
        if ((is_dir($enabled_path) && !is_dir($disabled_path)) || (is_file($enabled_path) && !is_file($disabled_path))) {
            if (rename($enabled_path, $disabled_path)) {
                // Save disable action to settings
                self::save_module_action($module, 'disabled');
                return ['success' => true, 'message' => sprintf(_r('Module %s disabled successfully'), $module)];
            } else {
                return ['success' => false, 'message' => sprintf(_r('Error disabling module %s'), $module)];
            }
        } else {
            return ['success' => false, 'message' => sprintf(_r("An error occurred while disabling module %s"), $module)];
        }
    }
    
    /**
     * Uninstall an active module by calling uninstall function and removing files
     * 
     * @param string $module Module name
     * @return array Success status and message
     */
    public static function uninstall_active_module($module)
    {
        Hooks::run('cli-init');
        $module_data = Config::get('module_version');
      
        if (!isset($module_data[$module])) {
            return ['success' => false, 'message' => sprintf(_r('Module %s not found'), $module)];
        }
     
        if (!isset($module_data[$module]['folder'])) {
            return ['success' => false, 'message' => sprintf(_r('Module %s folder not found'), $module)];
        }

        $module_path = MILK_DIR . '/modules/' . $module_data[$module]['folder'];
        
        if (!is_dir($module_path) && !is_file($module_path)) {
            return ['success' => false, 'message' => sprintf(_r('Module %s files not found'), $module)];
        }
     
       
        // Call the module's uninstall function using Cli::callFunction
        Cli::callFunction($module . ":uninstall");
        // Remove the module files/directory regardless of uninstall function result
        if (is_dir($module_path)) {
            if (!self::remove_directory($module_path)) {
                return ['success' => false, 'message' => sprintf(_r('Error removing module %s'), $module)];
            }
        } else if (is_file($module_path)) {
            if (!unlink($module_path)) {
                return ['success' => false, 'message' => sprintf(_r('Error removing module %s'), $module)];
            }
        }
        
        
        // Remove module from settings module_version
        $settings_versions = Settings::get('module_version') ?: [];
        if (isset($settings_versions[$module])) {
            unset($settings_versions[$module]);
            Settings::set('module_version', $settings_versions);
        }
      
        
        // Save uninstall action to settings (only last action, not a log)
        self::save_module_action($module, 'uninstalled');
       
        return ['success' => true, 'message' => sprintf(_r('Module %s uninstalled successfully'), $module)];
          
            
        
    }
    
    /**
     * Handle update file upload
     * 
     * @param array $files $_FILES array
     * @param array $post $_POST array
     * @return array Response with success/error and redirect info
     */
    public static function handle_upload_update($files, $post)
    {
        if (!isset($files['update_file']) || $files['update_file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'redirect' => ['page' => 'install'], 'message' => _r('No file uploaded or upload error.')];
        }

        $uploaded_file = $files['update_file']['tmp_name'];
        $file_name = $files['update_file']['name'];
        
        // Check that it is a ZIP file
        $file_info = pathinfo($file_name);
        if (strtolower($file_info['extension']) !== 'zip') {
            return ['success' => false, 'redirect' => ['page' => 'install'], 'message' => _r('The uploaded file must be in ZIP format.')];
        }
        
        // Check backup confirmation
        if (!isset($post['confirm_backup']) || $post['confirm_backup'] != '1') {
            return ['success' => false, 'redirect' => ['page' => 'install'], 'message' => _r('You must confirm you have made a backup before proceeding.')];
        }
        
        $temp_dir = Get::temp_dir();
        $update_file = $temp_dir . '/update-to-do.zip';
        
        // Move the file to the temporary directory
        if (!move_uploaded_file($uploaded_file, $update_file)) {
            return ['success' => false, 'redirect' => ['page' => 'install'], 'message' => _r('Error during update file upload.')];
        }
        
        return ['success' => true, 'redirect' => ['page' => 'install', 'action' => 'update-step2']];
    }
    
    /**
     * Get module status data for update-modules page
     * 
     * @return array Module status data
     */
    public static function get_module_status_data()
    {
        $current_versions = Config::get('module_version') ?: [];
        $settings_versions = Settings::get('module_version') ?: [];
        
        // Check if folder structure differs between current and settings
        $settings_need_update = false;
        foreach ($current_versions as $module => $current_data) {
            if (!is_array($current_data) || !isset($current_data['folder'])) {
                continue;
            }
            
            $settings_data = $settings_versions[$module] ?? null;
            if (!$settings_data) {
                continue;
            }
            
            // Get folder from settings
            $settings_folder = is_array($settings_data) ? ($settings_data['folder'] ?? null) : null;
            
            // If folder is different, mark for update
            if ($settings_folder !== $current_data['folder']) {
                $settings_need_update = true;
                
                // Update settings data structure
                if (is_array($settings_data)) {
                    $settings_versions[$module]['folder'] = $current_data['folder'];
                } else {
                    // Convert legacy format
                    $settings_versions[$module] = [
                        'version' => $settings_data,
                        'folder' => $current_data['folder']
                    ];
                }
            }
        }
        
        // Save updated settings if needed
        if ($settings_need_update) {
            Settings::set('module_version', $settings_versions);
        }
        
        // Scan for disabled modules
        $disabled_modules = self::scan_disabled_modules();
        
        // Find modules that need updating
        $modules_to_update = [];
        foreach ($current_versions as $module => $current_data) {
            $settings_data = $settings_versions[$module] ?? null;
            
            if (!is_array($current_data) || !isset($current_data['version'])) {
                continue;
            }
            
            $current_version = $current_data['version'];
            $settings_version = is_array($settings_data) ? ($settings_data['version'] ?? null) : $settings_data;
            
            if ($settings_version != $current_version) {
                $modules_to_update[$module] = [
                    'current' => $current_version,
                    'previous' => $settings_version
                ];
            }
        }
        
        return [
            'current_versions' => $current_versions,
            'disabled_modules' => $disabled_modules,
            'modules_to_update' => $modules_to_update
        ];
    }
    
    
    /**
     * Scan for disabled modules
     * 
     * @return array Disabled modules data
     */
    private static function scan_disabled_modules()
    {
        $disabled_modules = [];
        $modules_dir = MILK_DIR . '/modules';
        
        if (is_dir($modules_dir)) {
            $items = scandir($modules_dir);
            foreach ($items as $item) {
                if ($item[0] === '.' && $item !== '..' && $item !== '.') {
                    $module_name = substr($item, 1);
                    $item_path = $modules_dir . '/' . $item;
                    
                    if (is_dir($item_path)) {
                        $disabled_modules[$module_name] = ['type' => 'directory', 'enabled' => false, 'folder' => $module_name];
                    } elseif (is_file($item_path) && str_ends_with($item, '.controller.php')) {
                        $clean_module_name = substr($module_name, 0, -15);
                        $clean_module_name = rtrim($clean_module_name, '.');
                        $disabled_modules[$clean_module_name] = ['type' => 'file', 'enabled' => false, 'folder' => $module_name];
                    }
                }
            }
        }
        
        ksort($disabled_modules);
        return $disabled_modules;
    }

    /**
     * Process the system update
     * 
     * @param string $update_file Path to the ZIP update file
     * @param string $update_dir Temporary directory for extraction
     * @return bool|string True on success, error message string on failure
     */
    public static function process_update($update_file, $update_dir) {
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
     * Execute module updates
     * 
     * @param array $modules_to_update Modules that need updating
     * @return array Result with updated modules list
     */
    public static function execute_module_updates($modules_to_update)
    {
        $updated_modules = [];
        Hooks::run('cli-init');
        foreach ($modules_to_update as $module => $versions) {
            try {
                if ($versions['previous'] == 0 || $versions['previous'] == NULL) {
                    $install_result = Cli::callFunction($module.":install");
                    $action = 'installed';
                } else {
                    $install_result = Cli::callFunction($module.":update");
                    $action = 'updated';
                }
                
                if ($install_result === false) {
                   MessagesHandler::add_error(sprintf(_r('Error updating module %s: %s'), $module, Cli::get_last_error()));
                } else {
                    $updated_modules[] = $module;
                    // Save the action to module_actions
                    self::save_module_action($module, $action);
                }
            } catch (\Exception $e) {
                MessagesHandler::add_error(sprintf(_r('Error updating module %s: %s'), $module, $e->getMessage()));
            }
        }
        
        // Update settings with current versions
        $current_versions = Config::get('module_version');
        Settings::set('module_version', $current_versions);
        
        if (!empty($updated_modules)) {
            MessagesHandler::add_success(sprintf(_r('Successfully updated modules: %s'), implode(', ', $updated_modules)));
        }
        
        return $updated_modules;
    }
    
    /**
     * Get list of removed modules (modules with 'uninstalled' action that are no longer present)
     * 
     * @param array $current_modules List of currently present modules
     * @return array Removed modules data
     */
    private static function get_removed_modules($current_modules)
    {
        $module_actions = Settings::get('module_actions') ?: [];
        $removed_modules = [];
        
        foreach ($module_actions as $module => $action_data) {
            // Check if module has 'uninstalled' action and is not in current modules
            if (isset($action_data['action']) && $action_data['action'] === 'uninstalled' && 
                !isset($current_modules[$module])) {
                $removed_modules[$module] = $action_data;
            }
        }
        
        return $removed_modules;
    }
    
    /**
     * Generate HTML for update-modules page
     * 
     * @param array $module_data Module status data
     * @return string Generated HTML
     */
    public static function generate_module_status_html($module_data)
    {
        $html = '';
        
        // Modules requiring updates section
        if (!empty($module_data['modules_to_update'])) {
            // Check if there are any "Not installed" modules
            $has_not_installed = false;
            foreach ($module_data['modules_to_update'] as $module => $versions) {
                if ($versions['previous'] == 0 || $versions['previous'] == NULL) {
                    $has_not_installed = true;
                    break;
                }
            }
            
            $html .= '<div class="alert alert-info">';
            $html .= '<h5>'._r('Modules requiring updates:').'</h5>';
            
            if ($has_not_installed) {
                // Show preloader and auto-update for "Not installed" modules
                $html .= '<div id="module-update-container">';
                $html .= '<div id="module-preloader" class="text-center py-4">';
                $html .= '<div class="spinner-border text-primary" role="status">';
                $html .= '<span class="visually-hidden">Loading...</span>';
                $html .= '</div>';
                $html .= '<p class="mt-3">'._r('Please wait while we complete the installation').'...</p>';
                $html .= '</div>';
                $html .= '<div id="module-result" class="d-none">';
                $html .= '<div id="result-message"></div>';
                $html .= '</div>';
                $html .= '</div>';
            } else {
                // Show regular form for updates
                $html .= '<form method="post">';
                $html .= '<table class="table table-striped">';
                $html .= '<thead><tr><th>'._r('Module').'</th><th>'._r('Previous Version').'</th><th>'._r('Current Version').'</th><th>'._r('Status').'</th></tr></thead>';
                $html .= '<tbody>';
                
                foreach ($module_data['modules_to_update'] as $module => $versions) {
                    $html .= '<tr>';
                    $html .= '<td><strong>'.htmlspecialchars($module).'</strong></td>';
                    $html .= '<td>'.htmlspecialchars($versions['previous'] ?? _r('Not installed')).'</td>';
                    $html .= '<td>'.htmlspecialchars($versions['current']).'</td>';
                    $html .= '<td><span class="badge bg-warning">'._r('Needs Update').'</span></td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';

                // Show form button only if no "Not installed" modules
                $html .= '<div class="form-group mb-3">';
                $html .= '<button type="submit" class="btn btn-primary" name="update_modules" value="1">';
                $html .= '<i class="bi bi-arrow-up-circle"></i> '._r('Update All Modules');
                $html .= '</button>';
                $html .= '</div>';
                $html .= '</form>';
            }
            
            $html .= '</div>';
        }
        
        // Module upload section
        $html .= '<div class="card mb-4">';
        $html .= '<div class="card-header">';
        $html .= '<h5 class="mb-0">'._r('Upload New Module').'</h5>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        $html .= '<p class="text-muted mb-3">'._r('Upload a ZIP file containing a new module. The module will be extracted and installed automatically.').'</p>';
        $html .= '<form id="module-upload-form" method="post" action="'.Route::url('?page=install&action=upload-module').'" enctype="multipart/form-data">';
        $html .= '<div class="mb-3">';
        $html .= '<label for="module_file" class="form-label">'._r('Module ZIP file').'</label>';
        $html .= '<input type="file" class="form-control" name="module_file" id="module_file" accept=".zip" required>';
        $html .= '<div class="form-text">'._r('Accepted format: ZIP containing module files with .controller.php').'</div>';
        $html .= '</div>';
        $html .= '<button type="submit" class="btn btn-primary" id="module-upload-btn">';
        $html .= '<i class="bi bi-upload"></i> '._r('Upload Module');
        $html .= '</button>';
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';
        
        // All modules status section
        $html .= '<h5>'._r('All Modules Status:').'</h5>';
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>'._r('Module').'</th><th>'._r('Version').'</th><th>'._r('Status').'</th><th>'._r('Last Action').'</th><th>'._r('Actions').'</th></tr></thead>';
        $html .= '<tbody>';
        
        // Show enabled modules
        $current_versions = $module_data['current_versions'];
        ksort($current_versions);
        foreach ($current_versions as $module => $module_data_item) {
            if (!is_array($module_data_item) || !isset($module_data_item['version']) || !isset($module_data_item['folder'])) {
                $html .= '<tr>';
                $html .= '<td>'.htmlspecialchars($module).'</td>';
                $html .= '<td>-</td>';
                $html .= '<td><span class="badge bg-danger">'._r('Error: Missing folder info').'</span></td>';
                $html .= '<td>-</td>';
                $html .= '<td>-</td>';
                $html .= '</tr>';
                continue;
            }
            
            // Check if module has CLI disabled - if so, hide it from the install interface
            if (self::is_module_cli_disabled($module)) {
                continue;
            }
            
            $version = $module_data_item['version'];
            $needs_update = isset($module_data['modules_to_update'][$module]);
            
            // Check module folder permissions
            $module_folder = $module_data_item['folder'];
            $module_path = MILK_DIR . '/modules/' . $module_folder;
            $has_write_permission = is_writable($module_path);
            
            // Apply hook for additional status checks
            $status_badge = $needs_update ? 
                '<span class="badge bg-warning">'._r('Needs Update').'</span>' : 
                '<span class="badge bg-success">'._r('Up to Date').'</span>';
                
            if (!$has_write_permission) {
                $status_badge = '<span class="badge bg-danger">'._r('Permission Issue').'</span>';
            }
            
            $disable_button = '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleModule(\''.$module.'\', \'disable-module\')">'._r('Disable!').'</button>';
            $uninstall_button = '<button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="uninstallModule(\''.$module.'\')">'._r('Uninstall').'</button>';
            
            // Get last action info for this module
            $last_action_info = self::get_last_module_action_info($module);
                
            $html .= '<tr>';
            $html .= '<td>'.htmlspecialchars($module).'</td>';
            $html .= '<td>'.htmlspecialchars($version).'</td>';
            $html .= '<td>'.$status_badge.'</td>';
            $html .= '<td>'.$last_action_info.'</td>';
            $html .= '<td>'.$disable_button.$uninstall_button.'</td>';
            $html .= '</tr>';
        }
        
        // Show disabled modules
        foreach ($module_data['disabled_modules'] as $module => $info) {
            // Check if module has CLI disabled - if so, hide it from the install interface
            if (self::is_module_cli_disabled_by_folder($info['folder'])) {
                continue;
            }
            
            $status_badge = '<span class="badge bg-secondary">'._r('Disabled').'</span>';
            $enable_button = '<button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleModule(\''.$info['folder'].'\', \'enable-module\')">'._r('Enable').'</button>';
            
            // Get last action info for this module
            $last_action_info = self::get_last_module_action_info($module);
            
            $html .= '<tr>';
            $html .= '<td><del>'.htmlspecialchars($module).'</del></td>';
            $html .= '<td>-</td>';
            $html .= '<td>'.$status_badge.'</td>';
            $html .= '<td>'.$last_action_info.'</td>';
            $html .= '<td>'.$enable_button.'</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        if (empty($module_data['modules_to_update'])) {
            $html .= '<div class="alert alert-success">';
            $html .= '<i class="bi bi-check-circle"></i> '._r('All modules are up to date.');
            $html .= '</div>';
        }
        
        // Get removed modules and show them if any exist
        $all_current_modules = array_merge($module_data['current_versions'], $module_data['disabled_modules']);
        $removed_modules = self::get_removed_modules($all_current_modules);
        
        if (!empty($removed_modules)) {
            $html .= '<div class="mt-4">';
            $html .= '<h6 class="text-muted">'._r('Removed Modules:').'</h6>';
            $html .= '<div class="table-responsive">';
            $html .= '<table class="table table-sm table-bordered">';
            $html .= '<tbody>';
            
            foreach ($removed_modules as $module => $action_data) {
                $formatted_date = isset($action_data['date']) ? Get::format_date($action_data['date'], 'datetime') : '-';
                $username = isset($action_data['user_id']) ? self::get_username_by_id($action_data['user_id']) : '-';
                
                $html .= '<tr class="text-muted">';
                $html .= '<td><small><del>'.htmlspecialchars($module).'</del></small></td>';
                $html .= '<td><small>'.$formatted_date.'</small></td>';
                $html .= '<td><small>by '.htmlspecialchars($username).'</small></td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Check if a module has CLI disabled
     * 
     * @param string $module Module name
     * @return bool True if module has disable_cli = true, false otherwise
     */
    private static function is_module_cli_disabled($module) {
        // Try to get the controller class for this module
        $controller_file = MILK_DIR . '/modules/' . $module . '/' . $module . '.controller.php';
        
        if (!file_exists($controller_file)) {
            return false;
        }
        
        try {
            // Read the controller file and check for disable_cli property
            $content = file_get_contents($controller_file);
            
            // Look for protected $disable_cli = true
            if (preg_match('/protected\s+\$disable_cli\s*=\s*true/i', $content)) {
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if a disabled module has CLI disabled (by folder path)
     * 
     * @param string $folder Module folder name (may have leading dot for disabled modules)
     * @return bool True if module has disable_cli = true, false otherwise
     */
    private static function is_module_cli_disabled_by_folder($folder) {
        // Remove leading dot if present (disabled modules have .modulename format)
        $clean_folder = ltrim($folder, '.');
        
        // Try to get the controller class for this module
        $controller_file = MILK_DIR . '/modules/.' . $clean_folder . '/' . $clean_folder . '.controller.php';
        
        if (!file_exists($controller_file)) {
            return false;
        }
        
        try {
            // Read the controller file and check for disable_cli property
            $content = file_get_contents($controller_file);
            
            // Look for protected $disable_cli = true
            if (preg_match('/protected\s+\$disable_cli\s*=\s*true/i', $content)) {
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Execute system update step 3
     * 
     * @param object $model InstallModel instance
     * @return string Generated HTML
     */
    public static function execute_update_step3($model)
    {
        $model->execute_update();
        
        // Update the version in the config
        $config_content = file_get_contents(MILK_DIR.'/config.php');
        
        $config_content = preg_replace(
            "/^\s*\\\$conf\['version'\]\s*=\s*'[^']*';\s*$/m",
            " \$conf['version'] = '".NEW_VERSION."';",
            $config_content
        );
        
        File::put_contents(MILK_DIR.'/config.php', $config_content);
        Config::set('version', NEW_VERSION);
        
        return '<p class="alert alert-success">'._r('Update completed successfully.').'</p>';
    }
    
    /**
     * Save module enable/disable action with date and user ID
     * 
     * @param string $module Module name
     * @param string $action Action performed (enabled/disabled)
     */
    private static function save_module_action($module, $action)
    {
        // Get current user ID
        $user = Get::make('auth')->get_user();
        $user_id = $user->id ?? 0;
        
        // Get existing module actions or create empty array
        $module_actions = Settings::get('module_actions') ?: [];
        
        // Save only the last action (no history)
        $module_actions[$module] = [
            'action' => $action,
            'user_id' => $user_id,
            'date' => date('Y-m-d H:i:s')
        ];
        
        // Save to settings
        Settings::set('module_actions', $module_actions);
    }
    
    /**
     * Get last action info for a module (date, action and user)
     * 
     * @param string $module Module name
     * @return string Formatted action info or empty string
     */
    private static function get_last_module_action_info($module)
    {
        $module_actions = Settings::get('module_actions') ?: [];
        
        if (!isset($module_actions[$module]) || empty($module_actions[$module])) {
            return '-';
        }
        
        // Get the action (now it's directly the action object, not an array)
        $last_action = $module_actions[$module];
        
        if (!isset($last_action['date']) || !isset($last_action['action']) || !isset($last_action['user_id'])) {
            return '-';
        }
        
        $formatted_date = Get::format_date($last_action['date'], 'datetime');
        $action_text = ucfirst($last_action['action']);
        $username = self::get_username_by_id($last_action['user_id']);
        
        return $action_text . '<br><small>' . $formatted_date . '<br>by ' . htmlspecialchars($username) . '</small>';
    }
    
    /**
     * Get username by user ID
     * 
     * @param int $user_id User ID
     * @return string Username or fallback text
     */
    private static function get_username_by_id($user_id)
    {
        if ($user_id == 0) {
            return 'System';
        }
        
        try {
            // Get user from auth system
            $auth = Get::make('auth');
            if ($auth) {
                $user = $auth->get_user($user_id);
                if ($user && isset($user->username)) {
                    return $user->username;
                }
            }
            
            // Fallback: try to get from database directly
            $db = Get::db();
            $result = $db->query("SELECT username FROM " . $db->prefix . "users WHERE id = ?", [$user_id]);
            if ($result && count($result) > 0) {
                return $result[0]['username'];
            }
        } catch (\Exception) {
            // Ignore errors and return fallback
        }
        
        return 'User #' . $user_id;
    }
    
    /**
     * Handle module upload (extraction and copying only)
     * 
     * @param array $files $_FILES array
     * @param array $post $_POST array
     * @return array Response with success/error info and module name for installation
     */
    public static function handle_module_upload($files, $post = [])
    {
        if (!isset($files['module_file']) || $files['module_file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => _r('No file uploaded or upload error.')];
        }

        $uploaded_file = $files['module_file']['tmp_name'];
        $file_name = $files['module_file']['name'];
        
        // Check that it is a ZIP file
        $file_info = pathinfo($file_name);
        if (strtolower($file_info['extension']) !== 'zip') {
            return ['success' => false, 'message' => _r('The uploaded file must be in ZIP format.')];
        }
        
        $temp_dir = Get::temp_dir();
        $module_upload_file = $temp_dir . '/module-upload-' . time() . '.zip';
        $module_extract_dir = $temp_dir . '/module-extract-' . time();
        
        try {
            // Move uploaded file to temp directory
            if (!move_uploaded_file($uploaded_file, $module_upload_file)) {
                return ['success' => false, 'message' => _r('Error during module file upload.')];
            }
            
            // Extract the module
            $extract_result = self::extract_module_zip($module_upload_file, $module_extract_dir);
            if ($extract_result !== true) {
                // Clean up
                if (file_exists($module_upload_file)) unlink($module_upload_file);
                return ['success' => false, 'message' => $extract_result];
            }
            
            // Copy module files to modules directory
            $copy_result = self::copy_extracted_module($module_extract_dir, basename($file_name, '.zip'));
            
            // Clean up temp files
            if (file_exists($module_upload_file)) unlink($module_upload_file);
            if (is_dir($module_extract_dir)) {
                self::remove_directory($module_extract_dir);
            }
            
            if ($copy_result['success']) {
                return [
                    'success' => true, 
                    'message' => sprintf(_r('Module %s uploaded successfully.'), $copy_result['module_name']),
                    'module_name' => $copy_result['module_name'],
                    'needs_installation' => true
                ];
            } else {
                return $copy_result;
            }
            
        } catch (\Exception $e) {
            // Clean up on error
            if (file_exists($module_upload_file)) unlink($module_upload_file);
            if (is_dir($module_extract_dir)) {
                self::remove_directory($module_extract_dir);
            }
            return ['success' => false, 'message' => _r('Error during module upload: ') . $e->getMessage()];
        }
    }
    
    /**
     * Extract module ZIP file
     * 
     * @param string $zip_file Path to ZIP file
     * @param string $extract_dir Directory to extract to
     * @return bool|string True on success, error message on failure
     */
    private static function extract_module_zip($zip_file, $extract_dir)
    {
        if (!class_exists('ZipArchive')) {
            return _r('ZipArchive class not available. Please install php-zip extension.');
        }
        
        $zip = new \ZipArchive();
        $result = $zip->open($zip_file);
        
        if ($result !== TRUE) {
            return _r('Cannot open ZIP file: ') . $result;
        }
        
        // Create extraction directory
        if (!is_dir($extract_dir)) {
            if (!mkdir($extract_dir, 0755, true)) {
                $zip->close();
                return _r('Cannot create extraction directory.');
            }
        }
        
        // Extract ZIP contents
        if (!$zip->extractTo($extract_dir)) {
            $zip->close();
            return _r('Cannot extract ZIP contents.');
        }
        
        $zip->close();
        return true;
    }
    
    /**
     * Copy extracted module to modules directory with proper folder naming
     * 
     * @param string $extract_dir Extraction directory
     * @param string $zip_name Original ZIP filename without extension
     * @return array Result with success/error info and module name
     */
    private static function copy_extracted_module($extract_dir, $zip_name)
    {
        // Check extracted contents
        $items = scandir($extract_dir);
        $items = array_diff($items, ['.', '..']);
        
        if (count($items) === 0) {
            return ['success' => false, 'message' => _r('The ZIP file is empty.')];
        }
        
        $source_dir = $extract_dir;
        $module_name = null;
        $controller_file = null;
        // If there's only one directory, enter it and use its name or the ZIP name
        if (count($items) === 1) {
            $first_item = reset($items);
            $first_item_path = $extract_dir . '/' . $first_item;
            if (is_dir($first_item_path)) {
                $module_name = $first_item; // Use the directory name
                
                // Re-scan the contents of the inner directory
                $subitems = scandir($first_item_path);
                $subitems = array_diff($subitems, ['.', '..']);
                foreach ($subitems as $item) {
                    if (is_file($first_item_path . '/' . $item) && str_ends_with($item, '.controller.php')) {
                        $controller_file = $item;
                        
                        // If we don't have a module name yet, extract it from controller filename
                        if (!$module_name) {
                            $module_name = substr($item, 0, -15); // Remove .controller.php
                        }
                        break;
                    }
                }
            } else if (is_file($extract_dir . '/' . $first_item) && str_ends_with($first_item, '.controller.php')) {
                $controller_file = $first_item;
                $module_name = $zip_name;
            }
        } else {
            return ['success' => false, 'message' => _r('The ZIP file must contain exactly one directory or a .controller.php file.')];
        }
        
        // Look for .controller.php file to identify/validate module
        if (!$controller_file) {
            return ['success' => false, 'message' => _r('No valid module controller file found. Module must contain a .controller.php file.')];
        }
        
        // If we still don't have a module name, use the ZIP filename
        if (!$module_name) {
            $module_name = $zip_name;
        }
        
        // Check if module already exists
        $target_dir = MILK_DIR . '/modules/' . $module_name;
        $target_file = MILK_DIR . '/modules/' . $module_name . '.controller.php';
        
        if (is_dir($target_dir) || is_file($target_file)) {
            // Check if we can overwrite (check permissions)
            $can_overwrite = false;
            $permission_error = '';
            
            if (is_dir($target_dir)) {
                // Check directory permissions
                if (is_writable($target_dir)) {
                    $can_overwrite = true;
                } else {
                    $permission_error = sprintf(_r('Cannot overwrite module %s: insufficient permissions on directory %s'), $module_name, $target_dir);
                }
            } else if (is_file($target_file)) {
                // Check file permissions
                if (is_writable($target_file) && is_writable(dirname($target_file))) {
                    $can_overwrite = true;
                } else {
                    $permission_error = sprintf(_r('Cannot overwrite module %s: insufficient permissions on file %s'), $module_name, $target_file);
                }
            }
            
            if (!$can_overwrite) {
                return ['success' => false, 'message' => $permission_error];
            }
            
            // Module exists but can be overwritten - remove existing version
            try {
                if (is_dir($target_dir)) {
                    self::remove_directory($target_dir);
                } else if (is_file($target_file)) {
                    unlink($target_file);
                }
            } catch (\Exception $e) {
                return ['success' => false, 'message' => sprintf(_r('Cannot remove existing module %s: %s'), $module_name, $e->getMessage())];
            }
        }
      
        // Copy module files to modules directory
        if (count($items) > 1 || is_dir($source_dir . '/' . reset($items))) {
            // Multiple files or contains directories - create module directory
            self::copy_directory($source_dir,  MILK_DIR . '/modules/');
        } else {
            // Single controller file - copy directly
            if (!copy($source_dir . '/' . $controller_file, MILK_DIR . '/modules/' . $controller_file)) {
                return ['success' => false, 'message' => _r('Failed to copy module file.')];
            }
        }
        
        return [
            'success' => true, 
            'message' => sprintf(_r('Module %s copied successfully.'), $module_name),
            'module_name' => $module_name
        ];
            
        
    }
    
    
    /**
     * Copy directory recursively
     * 
     * @param string $source Source directory
     * @param string $destination Destination directory
     */
    private static function copy_directory($source, $destination)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relative_path = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $target = $destination . DIRECTORY_SEPARATOR . $relative_path;
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item, $target);
            }
        }
    }
    
    /**
     * Remove directory recursively
     * 
     * @param string $directory Directory to remove
     */
     private static function remove_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
       
        // Verifica di sicurezza: la directory deve essere dentro /modules
        $modulesPath = realpath(MILK_DIR . '/modules');
        $targetPath = realpath($dir);
        
        if ($targetPath === false || strpos($targetPath, $modulesPath) !== 0 || $targetPath == $modulesPath) {
            if (Cli::is_cli()) {
                Cli::error('Security error: Cannot remove directory outside modules folder');
            }
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
               if (!self::remove_directory($path)) {
                    return false;
               }
            } else {
                if (!is_writable($path)) {
                    if (Cli::is_cli()) {
                        Cli::error('Security error: Cannot remove file that is not writable');
                    }
                    return false;
                }
                unlink($path);
            }
        }
        if (!is_writable($dir)) {
            return false;
        }
        return rmdir($dir);
    }
    
    /**
     * Attempt to install a module using CLI::callFunction (no error if it fails)
     * 
     * @param string $module_name Module name to install
     * @return array Result with success/error info
     */
    public static function attempt_module_installation($module_name)
    {
        Hooks::run('cli-init');
        try {
            $install_result = Cli::callFunction($module_name . ":install", function() use ($module_name) { 
                return $module_name; 
            });
            
            if ($install_result !== false) {
                return [
                    'success' => true, 
                    'message' => sprintf(_r('Module %s installed successfully.'), $module_name),
                    'module_name' => $module_name
                ];
            } else {
                return [
                    'success' => true, 
                    'message' => sprintf(_r('Module %s uploaded successfully. Installation function not available or failed, but this is normal for modules without installation procedures.'), $module_name),
                    'module_name' => $module_name
                ];
            }
        } catch (\Exception) {
            // Module uploaded but installation failed - this is OK for open source modules
            return [
                'success' => true, 
                'message' => sprintf(_r('Module %s uploaded successfully. Installation failed but this is normal for modules without installation procedures.'), $module_name),
                'module_name' => $module_name
            ];
        }
    }
}