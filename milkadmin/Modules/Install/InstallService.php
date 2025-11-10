<?php
namespace Modules\Install;

use App\{Cli, Config, Get, Hooks, MessagesHandler, Route, Settings, File};

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
     * Check if a filename is a valid module file
     * Supports: _module.php, Module.php (with capital M), .module.php (legacy)
     *
     * @param string $filename File name to check
     * @return bool True if it's a valid module file
     */
    private static function isValidModuleFile($filename)
    {
        return str_ends_with($filename, '_module.php') ||
               str_ends_with($filename, 'Module.php') ||
               str_ends_with($filename, '.module.php'); // Legacy support
    }

    /**
     * Extract module name from module filename
     *
     * @param string $filename Module file name
     * @return string|null Module name or null if not valid
     */
    private static function getModuleNameFromFile($filename)
    {
        if (str_ends_with($filename, '_module.php')) {
            return substr($filename, 0, -11); // Remove _module.php
        } elseif (str_ends_with($filename, 'Module.php')) {
            return substr($filename, 0, -10); // Remove Module.php
        } elseif (str_ends_with($filename, '.module.php')) {
            return substr($filename, 0, -11); // Remove .module.php (legacy)
        }
        return null;
    }

    /**
     * Enable a module by renaming from .module to module
     * 
     * @param string $module Module name
     * @return bool Success status
     */
    public static function enableModule($module)
    {
        // For disabled modules, $module contains the folder path directly (without initial dot)
        $disabled_path = MILK_DIR . '/Modules/.' . $module;
        $enabled_path = MILK_DIR . '/Modules/' . $module;

        if ((is_dir($disabled_path) && !is_dir($enabled_path)) || (is_file($disabled_path) && !is_file($enabled_path))) {
            if (rename($disabled_path, $enabled_path)) {
                // Save enable action to settings
                self::saveModuleAction($module, 'enabled');
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
    public static function disableModule($module)
    {
        $module_data = Config::get('module_version');

        if (!isset($module_data[$module])) {
            return ['success' => false, 'message' => sprintf(_r('Module %s not found'), $module)];
        }

        if (!isset($module_data[$module]['folder'])) {
            return ['success' => false, 'message' => sprintf(_r('Module %s folder not found'), $module)];
        }

        // Check if module is a core module - prevent disabling
        if (self::isModuleCoreByFolder($module_data[$module]['folder'])) {
            return ['success' => false, 'message' => sprintf(_r('Cannot disable core module %s'), $module)];
        }
        
        $enabled_path = MILK_DIR . '/Modules/' . $module_data[$module]['folder'];
        $disabled_path = MILK_DIR . '/Modules/.' . $module_data[$module]['folder'];
       
        if ((is_dir($enabled_path) && !is_dir($disabled_path)) || (is_file($enabled_path) && !is_file($disabled_path))) {
            if (rename($enabled_path, $disabled_path)) {
                // Save disable action to settings
                self::saveModuleAction($module, 'disabled');
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
    public static function uninstallActiveModule($module)
    {
        Hooks::run('cli-init');
        $module_data = Config::get('module_version');

        if (!isset($module_data[$module])) {
            return ['success' => false, 'message' => sprintf(_r('Module %s not found'), $module)];
        }

        if (!isset($module_data[$module]['folder'])) {
            return ['success' => false, 'message' => sprintf(_r('Module %s folder not found'), $module)];
        }

        // Check if module is a core module - prevent uninstalling
        if (self::isModuleCoreByFolder($module_data[$module]['folder'])) {
            return ['success' => false, 'message' => sprintf(_r('Cannot uninstall core module %s'), $module)];
        }

        $module_path = MILK_DIR . '/Modules/' . $module_data[$module]['folder'];
        
        if (!is_dir($module_path) && !is_file($module_path)) {
            return ['success' => false, 'message' => sprintf(_r('Module %s files not found'), $module)];
        }

        // Call the module's uninstall function using Cli::callFunction
        Cli::callFunction($module . ":uninstall");
        // Remove the module files/directory regardless of uninstall function result
        if (is_dir($module_path)) {
            if (!self::removeDirectory($module_path)) {
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
        self::saveModuleAction($module, 'uninstalled');
       
        return ['success' => true, 'message' => sprintf(_r('Module %s uninstalled successfully'), $module)];

    }
    
    /**
     * Handle update file upload
     * 
     * @param array $files $_FILES array
     * @param array $post $_POST array
     * @return array Response with success/error and redirect info
     */
    public static function handleUploadUpdate($files, $post)
    {
       
        if (!isset($files['update_file']) || $files['update_file']['error'] !== UPLOAD_ERR_OK) {
            $msg_error = MessagesHandler::hasErrors() ? MessagesHandler::errorsToString() : _r('No file uploaded or upload error!.');
            return ['success' => false, 'redirect' => ['page' => 'install'], 'message' => $msg_error];
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
        
        $temp_dir = Get::tempDir();
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
    public static function getModuleStatusData()
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
        $disabled_modules = self::scanDisabledModules();
        
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
    private static function scanDisabledModules()
    {
        $disabled_modules = [];
        $modules_dir = MILK_DIR . '/Modules';
        
        if (is_dir($modules_dir)) {
            $items = scandir($modules_dir);
            foreach ($items as $item) {
                if ($item[0] === '.' && $item !== '..' && $item !== '.') {
                    $module_name = substr($item, 1);
                    $item_path = $modules_dir . '/' . $item;
                    
                    if (is_dir($item_path)) {
                        $disabled_modules[$module_name] = ['type' => 'directory', 'enabled' => false, 'folder' => $module_name];
                    } elseif (is_file($item_path) && self::isValidModuleFile($item)) {
                        $clean_module_name = self::getModuleNameFromFile($module_name);
                        if ($clean_module_name) {
                            $disabled_modules[$clean_module_name] = ['type' => 'file', 'enabled' => false, 'folder' => $module_name];
                        }
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
    public static function processUpdate($update_file, $update_dir) {

        // Clean any previous leftovers
        if (is_dir($update_dir)) {
            try {
                Install::removeDirectory($update_dir);
            } catch (\Exception $e) {
                // Ignore errors, we will still try
            }
        }
        
        // Extract the ZIP file
        $extract_result = Install::extractZip($update_file, $update_dir);
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
        $required_files = ['milkadmin'];
        $missing_files = [];
        
        foreach ($required_files as $required) {
            if (!file_exists($update_dir . '/' . $required)) {
                $missing_files[] = $required;
            }
        }
        
        if (!empty($missing_files)) {
            return sprintf(_r('The update file is not valid. Missing: %s'), implode(', ', $missing_files));
        }
        
        try {
            // Copy the files excluding config.php and storage
            Install::copyUpdateFiles($update_dir.'/milkadmin', MILK_DIR);
            
            // Remove temporary files
            unlink($update_file);
            
            // Completely remove extraction directory
            $temp_dir = Get::tempDir();
            $extract_base_dir = $temp_dir . '/update-extracted';
            if (is_dir($extract_base_dir)) {
                Install::removeDirectory($extract_base_dir);
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
    public static function executeModuleUpdates($modules_to_update)
    {
        $updated_modules = [];
        Hooks::run('cli-init');
        foreach ($modules_to_update as $module => $versions) {
          
            if ($versions['previous'] == 0 || $versions['previous'] == NULL) {
                $install_result = Cli::callFunction($module.":install");
                $action = 'installed';
            } else {
                $install_result = Cli::callFunction($module.":update");
                $action = 'updated';
            }
            
            if ($install_result === false) {
                MessagesHandler::addError(sprintf(_r('Error updating module %s: %s'), $module, Cli::getLastError()));
            } else {
                $updated_modules[] = $module;
                // Save the action to module_actions
                self::saveModuleAction($module, $action);
            }
           
        }
       
        // Update settings with current versions
        $current_versions = Config::get('module_version');
        Settings::set('module_version', $current_versions);
        
        if (!empty($updated_modules)) {
            MessagesHandler::addSuccess(sprintf(_r('Successfully updated modules: %s'), implode(', ', $updated_modules)));
        }
        
        return $updated_modules;
    }
    
    /**
     * Get list of removed modules (modules with 'uninstalled' action that are no longer present)
     * 
     * @param array $current_modules List of currently present modules
     * @return array Removed modules data
     */
    private static function getRemovedModules($current_modules)
    {
        $module_actions = Settings::get('module_actions') ?: [];
        $removed_modules = [];

        // Create a normalized (lowercase) version of current_modules keys for comparison
        $normalized_current = [];
        foreach ($current_modules as $mod_name => $mod_data) {
            $normalized_current[strtolower($mod_name)] = true;
        }

        foreach ($module_actions as $module => $action_data) {
            // Check if module has 'uninstalled' action and is not in current modules
            // Use normalized comparison (case-insensitive)
            $module_normalized = strtolower($module);
            if (isset($action_data['action']) && $action_data['action'] === 'uninstalled' &&
                !isset($normalized_current[$module_normalized])) {
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
    public static function generateModuleStatusHtml($module_data)
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
        $html .= '<p class="text-body-secondary mb-3">'._r('Upload a ZIP file containing a new module. The module will be extracted and installed automatically.').'</p>';
        $html .= '<form id="module-upload-form" method="post" action="'.Route::url('?page=install&action=upload-module').'" enctype="multipart/form-data">';
        $html .= '<div class="mb-3">';
        $html .= '<label for="module_file" class="form-label">'._r('Module ZIP file').'</label>';
        $html .= '<input type="file" class="form-control" name="module_file" id="module_file" accept=".zip" required>';
        $html .= '<div class="form-text">'._r('Accepted format: ZIP containing module files with _module.php or Module.php').'</div>';
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
            if (self::isModuleCliDisabled($module)) {
                continue;
            }

            // Check if module is a core module - if so, hide it from the install interface
            if (self::isModuleCoreByFolder($module_data_item['folder'])) {
                continue;
            }

            $version = $module_data_item['version'];
            $needs_update = isset($module_data['modules_to_update'][$module]);
            
            // Check module folder permissions
            $module_folder = $module_data_item['folder'];
            $module_path = MILK_DIR . '/Modules/' . $module_folder;
            $has_write_permission = is_writable($module_path);
            
            // Apply hook for additional status checks
            $status_badge = $needs_update ? 
                '<span class="badge bg-warning">'._r('Needs Update').'</span>' : 
                '<span class="badge bg-success">'._r('Up to Date').'</span>';
                
            if (!$has_write_permission) {
                $status_badge = '<span class="badge bg-danger">'._r('Permission Issue').'</span>';
            }
            
            $disable_button = '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleModule(\''.$module.'\', \'disable-module\')">'._r('Disable!').'</button>';
            $download_button = '<a href="'.Route::url(['page' => 'install', 'action' => 'download-module', 'module' => $module]).'" class="btn btn-sm btn-outline-info ms-1"><i class="bi bi-download"></i> '._r('Download').'</a>';
            $uninstall_button = '<button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="uninstallModule(\''.$module.'\')">'._r('Uninstall').'</button>';

            // Get last action info for this module
            $last_action_info = self::getLastModuleActionInfo($module);

            $html .= '<tr>';
            $html .= '<td>'.htmlspecialchars($module).'</td>';
            $html .= '<td>'.htmlspecialchars($version).'</td>';
            $html .= '<td>'.$status_badge.'</td>';
            $html .= '<td>'.$last_action_info.'</td>';
            $html .= '<td>'.$disable_button.$download_button.$uninstall_button.'</td>';
            $html .= '</tr>';
        }
        
        // Show disabled modules
        foreach ($module_data['disabled_modules'] as $module => $info) {
            $status_badge = '<span class="badge bg-secondary">'._r('Disabled').'</span>';
            $enable_button = '<button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleModule(\''.$info['folder'].'\', \'enable-module\')">'._r('Enable').'</button>';

            // Get last action info for this module
            $last_action_info = self::getLastModuleActionInfo($module);

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
        $removed_modules = self::getRemovedModules($all_current_modules);
        
        if (!empty($removed_modules)) {
            $html .= '<div class="mt-4">';
            $html .= '<h6 class="text-body-secondary">'._r('Removed Modules:').'</h6>';
            $html .= '<div class="table-responsive">';
            $html .= '<table class="table table-sm table-bordered">';
            $html .= '<tbody>';
            
            foreach ($removed_modules as $module => $action_data) {
                $formatted_date = isset($action_data['date']) ? Get::formatDate($action_data['date'], 'datetime') : '-';
                $username = isset($action_data['user_id']) ? self::getUsernameById($action_data['user_id']) : '-';
                
                $html .= '<tr class="text-body-secondary">';
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
    private static function isModuleCliDisabled($module) {
        // Try different module file formats
        $possible_files = [
            MILK_DIR . '/Modules/' . $module . '/' . $module . 'Module.php',  // New format: ModuleNameModule.php
            MILK_DIR . '/Modules/' . $module . '/' . $module . '_module.php',  // Alternative: ModuleName_module.php
            MILK_DIR . '/Modules/' . $module . '/' . $module . '.module.php',  // Legacy: ModuleName.module.php
        ];

        foreach ($possible_files as $module_file) {
            if (file_exists($module_file)) {
                try {
                    // Read the module file and check for disable_cli property
                    $content = file_get_contents($module_file);

                    // Look for protected $disable_cli = true
                    if (preg_match('/protected\s+\$disable_cli\s*=\s*true/i', $content)) {
                        return true;
                    }

                    return false;
                } catch (\Exception $e) {
                    return false;
                }
            }
        }

        return false;
    }
    
    /**
     * Check if an active module is a core module (by folder path)
     *
     * @param string $folder Module folder name
     * @return bool True if module has is_core_module = true, false otherwise
     */
    private static function isModuleCoreByFolder($folder) {
        // Clean folder name (remove any leading dot if present)
        $clean_folder = ltrim($folder, '.');

        // Try different module file formats in ACTIVE module folder (without dot)
        $possible_files = [
            MILK_DIR . '/Modules/' . $clean_folder . '/' . $clean_folder . 'Module.php',  // New format: Auth/AuthModule.php
            MILK_DIR . '/Modules/' . $clean_folder . '/' . $clean_folder . '_module.php',  // Alternative: Auth/Auth_module.php
        ];

        foreach ($possible_files as $module_file) {
            if (file_exists($module_file)) {
                try {
                    // Read the module file and check for is_core_module property
                    $content = file_get_contents($module_file);

                    // Look for protected $is_core_module = true
                    if (preg_match('/protected\s+\$is_core_module\s*=\s*true/i', $content)) {
                        return true;
                    }

                    // Alternative pattern: IsCoreModule() method call
                    if (preg_match('/IsCoreModule\s*\(\s*\)/i', $content)) {
                        return true;
                    }

                    return false;
                } catch (\Exception $e) {
                    return false;
                }
            }
        }

        return false;
    }
    
    /**
     * Execute system update step 3
     * 
     * @param object $model InstallModel instance
     * @return string Generated HTML
     */
    public static function executeUpdateStep3($model)
    {
        $model->executeUpdate();
        
        // Update the version in the config
        $config_content = file_get_contents(LOCAL_DIR.'/config.php');
        
        $config_content = preg_replace(
            "/^\s*\\\$conf\['version'\]\s*=\s*'[^']*';\s*$/m",
            " \$conf['version'] = '".NEW_VERSION."';",
            $config_content
        );
        
        File::putContents(LOCAL_DIR.'/config.php', $config_content);
        Config::set('version', NEW_VERSION);
        
        return '<p class="alert alert-success">'._r('Update completed successfully.').'</p>';
    }
    
    /**
     * Save module enable/disable action with date and user ID
     * 
     * @param string $module Module name
     * @param string $action Action performed (enabled/disabled)
     */
    private static function saveModuleAction($module, $action)
    {
        // Normalize module name to lowercase for consistency
        $module_key = strtolower($module);

        // Get current user ID
        $user = Get::make('Auth')->getUser();
        $user_id = $user->id ?? 0;

        // Get existing module actions or create empty array
        $module_actions = Settings::get('module_actions') ?: [];

        // Save only the last action (no history)
        $module_actions[$module_key] = [
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
    private static function getLastModuleActionInfo($module)
    {
        // Normalize module name to lowercase for consistency
        $module_key = strtolower($module);

        $module_actions = Settings::get('module_actions') ?: [];

        if (!isset($module_actions[$module_key]) || empty($module_actions[$module_key])) {
            return '-';
        }

        // Get the action (now it's directly the action object, not an array)
        $last_action = $module_actions[$module_key];

        if (!isset($last_action['date']) || !isset($last_action['action']) || !isset($last_action['user_id'])) {
            return '-';
        }

        $formatted_date = Get::formatDate($last_action['date'], 'datetime');
        $action_text = ucfirst($last_action['action']);
        $username = self::getUsernameById($last_action['user_id']);

        return $action_text . '<br><small>' . $formatted_date . '<br>by ' . htmlspecialchars($username) . '</small>';
    }
    
    /**
     * Get username by user ID
     * 
     * @param int $user_id User ID
     * @return string Username or fallback text
     */
    private static function getUsernameById($user_id)
    {
        if ($user_id == 0) {
            return 'System';
        }
        
        try {
            // Get user from auth system
            $auth = Get::make('Auth');
            if ($auth) {
                $user = $auth->getUser($user_id);
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
    public static function handleModuleUpload($files, $post = [])
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
        
        $temp_dir = Get::tempDir();
        $module_upload_file = $temp_dir . '/module-upload-' . time() . '.zip';
        $module_extract_dir = $temp_dir . '/module-extract-' . time();
        
        try {
            // Move uploaded file to temp directory
            if (!move_uploaded_file($uploaded_file, $module_upload_file)) {
                return ['success' => false, 'message' => _r('Error during module file upload.')];
            }
            
            // Extract the module
            $extract_result = self::extractModuleZip($module_upload_file, $module_extract_dir);
            if ($extract_result !== true) {
                // Clean up
                if (file_exists($module_upload_file)) unlink($module_upload_file);
                return ['success' => false, 'message' => $extract_result];
            }
            
            // Copy module files to modules directory
            $copy_result = self::copyExtractedModule($module_extract_dir, basename($file_name, '.zip'));
            
            // Clean up temp files
            if (file_exists($module_upload_file)) unlink($module_upload_file);
            if (is_dir($module_extract_dir)) {
                self::removeDirectory($module_extract_dir);
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
                self::removeDirectory($module_extract_dir);
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
    private static function extractModuleZip($zip_file, $extract_dir)
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
    private static function copyExtractedModule($extract_dir, $zip_name)
    {
        // Check extracted contents
        $items = scandir($extract_dir);
        $items = array_diff($items, ['.', '..']);
        
        if (count($items) === 0) {
            return ['success' => false, 'message' => _r('The ZIP file is empty.')];
        }
        
        $source_dir = $extract_dir;
        $module_name = null;
        $module_file = null;
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
                    if (is_file($first_item_path . '/' . $item) && self::isValidModuleFile($item)) {
                        $module_file = $item;

                        // If we don't have a module name yet, extract it from module filename
                        if (!$module_name) {
                            $module_name = self::getModuleNameFromFile($item);
                        }
                        break;
                    }
                }
            } else if (is_file($extract_dir . '/' . $first_item) && self::isValidModuleFile($first_item)) {
                $module_file = $first_item;
                $module_name = $zip_name;
            }
        } else {
            return ['success' => false, 'message' => _r('The ZIP file must contain exactly one directory or a module file (_module.php, Module.php).')];
        }

        // Look for valid module file to identify/validate module
        if (!$module_file) {
            return ['success' => false, 'message' => _r('No valid module file found. Module must contain a _module.php or Module.php file.')];
        }
        
        // If we still don't have a module name, use the ZIP filename
        if (!$module_name) {
            $module_name = $zip_name;
        }
        
        // Check if module already exists
        $target_dir = MILK_DIR . '/Modules/' . $module_name;
        $target_file = MILK_DIR . '/Modules/' . $module_name . '.module.php';
        
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
                    self::removeDirectory($target_dir);
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
            self::copyDirectory($source_dir,  MILK_DIR . '/Modules/');
        } else {
            // Single module file - copy directly
            if (!copy($source_dir . '/' . $module_file, MILK_DIR . '/Modules/' . $module_file)) {
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
    private static function copyDirectory($source, $destination)
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
     private static function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
       
        // Verifica di sicurezza: la directory deve essere dentro /modules
        $modulesPath = realpath(MILK_DIR . '/modules');
        $targetPath = realpath($dir);
        
        if ($targetPath === false || strpos($targetPath, $modulesPath) !== 0 || $targetPath == $modulesPath) {
            if (Cli::isCli()) {
                Cli::error('Security error: Cannot remove directory outside modules folder');
            }
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
               if (!self::removeDirectory($path)) {
                    return false;
               }
            } else {
                if (!is_writable($path)) {
                    if (Cli::isCli()) {
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
    public static function attemptModuleInstallation($module_name)
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

    /**
     * Download a module as ZIP file
     *
     * @param string $module_name Module name to download
     */
    public static function downloadModule($module_name)
    {
        $module_data = Config::get('module_version');

        if (!isset($module_data[$module_name])) {
            MessagesHandler::addError(sprintf(_r('Module %s not found'), $module_name));
            Route::redirect(['page' => 'install', 'action' => 'update-modules']);
            return;
        }

        if (!isset($module_data[$module_name]['folder'])) {
            MessagesHandler::addError(sprintf(_r('Module %s folder not found'), $module_name));
            Route::redirect(['page' => 'install', 'action' => 'update-modules']);
            return;
        }

        $module_folder = $module_data[$module_name]['folder'];
        $module_path = MILK_DIR . '/Modules/' . $module_folder;

        // Check if module exists
        if (!is_dir($module_path) && !is_file($module_path)) {
            MessagesHandler::addError(sprintf(_r('Module %s files not found'), $module_name));
            Route::redirect(['page' => 'install', 'action' => 'update-modules']);
            return;
        }

        // Create ZIP file
        $temp_dir = Get::tempDir();
        $zip_filename = $module_name . '-' . date('Ymd-His') . '.zip';
        $zip_path = $temp_dir . '/' . $zip_filename;

        if (!class_exists('ZipArchive')) {
            MessagesHandler::addError(_r('ZipArchive class not available. Please install php-zip extension.'));
            Route::redirect(['page' => 'install', 'action' => 'update-modules']);
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            MessagesHandler::addError(_r('Cannot create ZIP file.'));
            Route::redirect(['page' => 'install', 'action' => 'update-modules']);
            return;
        }

        // Add files to ZIP
        if (is_dir($module_path)) {
            // Module is a directory
            self::addDirectoryToZip($zip, $module_path, $module_folder);
        } else {
            // Module is a single file
            $zip->addFile($module_path, basename($module_path));
        }

        $zip->close();

        // Send file to browser
        if (file_exists($zip_path)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
            header('Content-Length: ' . filesize($zip_path));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');

            readfile($zip_path);

            // Remove temporary file
            unlink($zip_path);
            exit;
        } else {
            MessagesHandler::addError(_r('Error creating module ZIP file.'));
            Route::redirect(['page' => 'install', 'action' => 'update-modules']);
        }
    }

    /**
     * Add directory contents to ZIP archive recursively
     *
     * @param \ZipArchive $zip ZIP archive object
     * @param string $directory Directory path
     * @param string $base_name Base name for ZIP entries
     */
    private static function addDirectoryToZip($zip, $directory, $base_name = '')
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $file_path = $file->getPathname();
            $relative_path = substr($file_path, strlen($directory) + 1);

            // Create the path in ZIP with base_name as root folder
            $zip_path = $base_name . '/' . $relative_path;

            if ($file->isDir()) {
                $zip->addEmptyDir($zip_path);
            } else {
                $zip->addFile($file_path, $zip_path);
            }
        }
    }
}