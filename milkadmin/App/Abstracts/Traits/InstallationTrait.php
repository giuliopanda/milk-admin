<?php
namespace App\Abstracts\Traits;

use App\{Cli, Config, Hooks, MessagesHandler, Permissions, Settings};


!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Installation Trait
 *
 * This trait provides installation, update, and uninstallation functionality
 * for modules that extend AbstractModule. It handles database table creation,
 * CLI commands, and installation hooks.
 *
 * @package     App
 * @subpackage  Abstracts\Traits
 */
trait InstallationTrait {

    
    /**
     * Loaded Install extension instances
     * @var array
     */
    private array $loaded_install_extensions = [];

    /**
     * Loaded Hook extension instances
     * @var array
     */
    private array $loaded_hook_extensions = [];

    /**
     * Loaded Api extension instances
     * @var array
     */
    private array $loaded_api_extensions = [];

    /**
     * Loaded Shell extension instances
     * @var array
     */
    private array $loaded_shell_extensions = [];

    /**
     * Loaded Controller extension instances
     * @var array
     */
    private array $loaded_controller_extensions = [];



    /**
     * Set up installation hooks
     *
     * Called from _hook_init() to register installation-related hooks
     */
    public function setupInstallationHooks() {
        // Se non c'è la versione vuol dire che il sistema deve essere ancora installato
        if (Config::get('version') == null || NEW_VERSION > Config::get('version')) {
            // function($html, $errors)
            Hooks::set('install.get_html_modules', [$this, '_installGetHtmlModules'], 50);
            // function($errors, $data)
            Hooks::set('install.check_data', [$this, 'installCheckData'], 50);
            // in fase di installazione salva la configurazione nel config.php così poi si fa il redirect
            Hooks::set('install.execute_config', [$this, 'installExecuteConfig'], 50);
            // function($data)
            Hooks::set('install.execute', [$this, 'installExecute'], 50);
            // function($html)
            Hooks::set('install.done', [$this, '_installDone'], 50);
            // function($html)
            Hooks::set('install.update', [$this, 'installUpdate'], 50);

        }
    }

    /**
     * Set up CLI installation hooks
     *
     * Registers shell commands for installing, updating, and uninstalling the module
     * if the model has the necessary methods.
     * Respects the disable_cli flag to prevent automatic registration of these commands.
     */
    public function setupInstallationCliHooks() {
        // Check if CLI command generation is disabled for install/update/uninstall
        if ($this->disable_cli) {
            return;
        }

        if (is_object($this->model) && method_exists($this->model, 'buildTable') && method_exists($this->model, 'dropTable')) {
            Cli::set($this->page.":install", [$this, 'shellInstallModule']);
            if (!$this->is_core_module) {
                Cli::set($this->page.":uninstall", [$this, 'shellUninstallModule']);
            }
            Cli::set($this->page.":update", [$this, 'shellUpdateModule']);
        }
    }

    /**
     * Install the module
     *
     * This shell command installs the module by calling the install_execute method.
     * It can be run via CLI or by an admin user.
     *
     * @example
     * ```bash
     * php milkadmin/cli.php posts:install
     * ```
     */
    public function shellInstallModule() {
        if (Cli::isCli() || Permissions::check('_user.is_admin')) {
            $this->installExecute();
            $this->installExtensionExecute();
        }
    }

    public function shellUninstallModule() {
        $this->_shellUninstallModule();
       // $this->shellUninstallExtensionModule();
    }

    // Basta creare una nuova funzione che viene registrata nella shell???
   // public function shellUninstallExtensionModule() {
    //    $this->executeMethodExtension('install', 'shellUninstallModule');
   // }

    /**
     * Uninstall the module
     *
     * This shell command uninstalls the module by dropping its tables and disabling
     * the module directory. It can be run via CLI or by an admin user.
     * Also uninstalls additional models if registered.
     *
     * @example
     * ```bash
     * php milkadmin/cli.php posts:uninstall
     * ```
     */
    public function _shellUninstallModule() {
        if (Cli::isCli() || Permissions::check('_user.is_admin')) {

            // Uninstall additional models first
            $additionalModels = $this->getAdditionalModels();
            foreach ($additionalModels as $modelName => $modelInstance) {
                try {
                    $modelInstance->dropTable();
                } catch (\Exception $e) {
                    if (Cli::isCli()) {
                        Cli::error("Additional model $modelName: " . $e->getMessage());
                    }
                }
            }

            // Uninstall main model
            $this->model->dropTable();

            // trova la directory della classe che estende questa classe
            $dir = $this->getChildClassPath();
            if (is_dir($dir) == false)  return;
            // Rimuove completamente la directory invece di rinominarla con il punto
              $this->removeDirectory($dir);

            // Update settings: remove from module_version
            $settings_versions = Settings::get('module_version') ?: [];
            if (isset($settings_versions[$this->page])) {
                unset($settings_versions[$this->page]);
                Settings::set('module_version', $settings_versions);
            }

            // Save uninstall action to module_actions
            $user = \App\Get::make('Auth')->getUser();
            $user_id = $user->id ?? 0;
            $module_actions = Settings::get('module_actions') ?: [];
            $module_actions[strtolower($this->page)] = [
                'action' => 'uninstalled',
                'user_id' => $user_id,
                'date' => date('Y-m-d H:i:s')
            ];
            Settings::set('module_actions', $module_actions);

            if (Cli::isCli()) {
                Cli::success('Module '.$this->title.' uninstalled');
            }
        }
    }

    /**
     * Update the module
     *
     * This shell command updates the module by calling the install_update method.
     * It can be run via CLI or by an admin user.
     *
     * @example
     * ```bash
     * php milkadmin/cli.php posts:update
     * ```
     */
    public function shellUpdateModule() {
       if (Cli::isCli() || Permissions::check('_user.is_admin')) {
            $this->installUpdate('');
            $this->installExtensionUpdate('');
        }
    }

    public function _installDone($html) {
        $settings_versions = Settings::get('module_version') ?: [];
        $settings_versions[$this->page] = [
            'version' => $this->version ?? 1,
            'folder' => $this->getChildClassPath()
        ];
        $module_actions = Settings::get('module_actions') ?: [];
        $module_actions[$this->page] = [
            'action' => 'install',
            'user_id' => 1,
            'date' => date('Y-m-d H:i:s')
        ];

        // Save to settings
        Settings::set('module_actions', $module_actions);
        Settings::set('module_version', $settings_versions);
        return $this->installDone($html);
    }


     /**
     * Get HTML for the installation page
     *
     * Allows modifying the installation page with custom form elements.
     * This method is called if the system is not installed or there's a new version.
     *
     * @param string $html The current HTML of the installation page
     * @param array $errors Any validation errors from the installation form
     * @return string The modified HTML
     */
    public function _installGetHtmlModules(string $html, ?array $errors = []) {
        // Override in child classes to add custom form elements

        $html = $this->executeMethodExtension('install', 'onInstallGetHtmlModules', $html, $errors); 
      
        return $this->installGetHtmlModules($html, $errors);
    }

    /**
     * Modify the installation page HTML
     *
     * @param string $html The current HTML of the installation page
     * @param array $errors Any validation errors from the installation form
     * @return string The modified HTML
     */
    public function installGetHtmlModules(string $html, $errors = []) {
        return $html;
    }


    // Salva la configurazione in fase di prima installazione
    public function installExecuteConfig($data = []) {

        $data = $this->executeMethodExtension('install', 'onInstallExecuteConfig', $data);
        
        return $data;
    }

    /**
     * Calls the _installExecute method and returns the data
     *
     * @param array $data Data from the installation form
     * @return void
     */
    public function installExecute($data = []) {
       
        $this->_installExecute();
        return $data;
    }

    public function installExtensionExecute($data = []) {
        $data = $this->executeMethodExtension('install', 'installExecute', $data);
        return $data;
    }

    /**
     * Execute the module installation
     *
     * Performs the actual installation of the module, typically by creating
     * database tables through the model's build_table method.
     * Also installs additional models if registered.
     *
     * @return bool
     */
    public function _installExecute() {
        $success = true;
        $errors = [];
        $this_class= get_class($this);
        // Install main model
        if (is_object($this->model) && method_exists($this->model, 'buildTable')) {
            $this->model->buildTable();
            if ($this->model->last_error != '') {
                $success = false;
                $errors[] = $this->model->last_error;
            }
        }
        // Install additional models
        $additionalModels = $this->getAdditionalModels();
        foreach ($additionalModels as $modelName => $modelInstance) {
            try {
                $modelInstance->buildTable();
                if (isset($modelInstance->last_error) && $modelInstance->last_error != '') {
                    $success = false;

                    $errors[] = $this_class. ': Additional model '.$modelName. ': ' . $modelInstance->last_error;
                }
            } catch (\Exception $e) {
                $success = false;
                $errors[] = $this_class. ': Additional model '.$modelName. ': ' . $e->getMessage();
            }
        }

        // Report results
        if ($success) {
            if (Cli::isCli()) {
                Cli::success('Module '.$this->title.' installed');
            } else {
                MessagesHandler::addSuccess('Module '.$this->title.' installed');
            }
            return true;
        } else {
            foreach ($errors as $error) {
                if (Cli::isCli()) {
                    Cli::error($error);
                } else {
                    MessagesHandler::addError($error);
                }
            }
            return false;
        }
    }

    /**
     * Validate installation data
     *
     * Performs validation on the data submitted in the installation form.
     *
     * @example
     * ```php
     * public function installCheckData($errors, array $data = []) {
     *     if ($data['my_setting'] == '') {
     *         $errors['my_setting'] = 'My setting is required';
     *     }
     *     return $errors;
     * }
     * ```
     *
     * @param array $errors Current validation errors
     * @param array $data Data from the installation form
     * @return array Updated validation errors
     */
    public function installCheckData($errors, $data = []) {
        // Override in child classes to add custom validation


        $errors = $this->executeMethodExtension('install', 'onInstallCheckData', $errors, $data);

        return $errors;
    }
    /**
     * Installation completion message
     *
     * This method is called after installation and allows modifying the
     * HTML of the installation completion page.
     *
     * @example
     * ```php
     * public function installDone($html) {
     *     $html .= '<div>Module installed correctly</div>';
     *     return $html;
     * }
     * ```
     *
     * @param string $html Current HTML of the completion page
     * @return string Modified HTML
     */
    public function installDone($html) {
        // Override in child classes to customize completion message


        $html = $this->executeMethodExtension('install', 'onInstallDone',  $html);

        return $html;
    }

    /**
     * Update the module
     *
     * This method is called when updating the module. It typically rebuilds
     * the database tables to incorporate any schema changes.
     * Also updates additional models if registered.
     *
     * @param string $html Current HTML of the update page
     * @return string Modified HTML
     */
    public function installUpdate($html) {
        $this->_installExecute();
        return $html;
    }

    public function _installUninstall() {
        $this->uninstallExecute();
        $this->executeMethodExtension('install', 'uninstall'); 
    }


    public function uninstallExecute() {
       
    }

    public function installExtensionUpdate($html) {
        $html = $this->executeMethodExtension('install', 'installUpdate', $html); 
        return $html;
    }

    /**
     * Remove a directory and all its contents recursively
     * Only allows removal of directories within the modules folder for security
     *
     * @param string $dir Directory path to remove
     * @return bool True if successful, false otherwise
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        // Verifica di sicurezza: la directory deve essere dentro /Modules oppure dentro local_dir
        $modulesPath = realpath(MILK_DIR . '/Modules');
        $targetPath = realpath($dir);
        $dir1_check = $targetPath === false || strpos($targetPath, $modulesPath) !== 0;

        $modulesPath = realpath(LOCAL_DIR . '/Modules');
        $dir2_check = $targetPath === false || strpos($targetPath, $modulesPath) !== 0;

        if ($targetPath === false || ($dir1_check && $dir2_check)) {
            if (Cli::isCli()) {
                Cli::error('Security error: Cannot remove directory outside modules folder');
            }
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                if (!is_writable($path)) {
                    if (Cli::isCli()) {
                        Cli::error('Security error: Cannot remove directory that is not writable');
                    }
                    return false;
                }
                $this->removeDirectory($path);
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
            if (Cli::isCli()) {
                Cli::error('Security error: Cannot remove directory that is not writable');
            }
            return false;
        }
        return rmdir($dir);
    }

    /**
     * Get additional models registered for this module
     *
     * @return mixed Array of additional models [modelName => modelInstance] or single model instance
     */
    public function getAdditionalModels($model_name = '') {
        if (!isset($this->additional_models)) {
           return [];
        }
        // Auto-instantiate models if they are class names (strings)
        foreach ($this->additional_models as $key => $model) {
            if (is_string($model) && class_exists($model)) {
                $this->additional_models[$key] = new $model();
            }
        }
        if ($model_name == '') {
            return $this->additional_models;
        } else {
            return $this->additional_models[$model_name] ?? [];
        }
    }

     /**
     * Execute a method on all extensions of a specific type
     *
     * @param string $type The type of extensions to execute the method on
     * @param string $method The method to execute
     * @param $args The arguments to pass to the method
     * @return mixed
     */
    public function executeMethodExtension($type, $method, ...$args):  mixed {
        if ($args == []) {
            return null;
        }
        $first_value = array_shift($args);
        
        // Mappa type → proprietà dell’oggetto
        $map = [
            'install'    => 'loaded_install_extensions',
            'hook'       => 'loaded_hook_extensions',
            'api'        => 'loaded_api_extensions',
            'controller' => 'loaded_controller_extensions',
            'module'     => 'loaded_extensions',
            'shell'      => 'loaded_shell_extensions',
        ];

        if (!isset($map[$type])) {
            return  $first_value ;
        }

        $property = $map[$type];
        if (!isset($this->$property) || !is_array($this->$property)) {
            return  $first_value;
        }

        // Modalità pipeline: ritorna SOLO il valore finale
        
        
        foreach ($this->$property as $extension) {
            if (!method_exists($extension, $method)) {
                continue;
            }
            $first_value = $extension->$method($first_value, ...$args);
        }
        return $first_value;
    
    }

}
