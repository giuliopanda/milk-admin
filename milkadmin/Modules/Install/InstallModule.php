<?php
namespace Modules\Install;

use App\Abstracts\AbstractModule;
use App\Attributes\{RequestAction, Shell, AccessLevel};
use App\{Cli, Config, File, Hooks, MessagesHandler, Permissions, Response, Route, Settings, Theme};

!defined('MILK_DIR') && die();

/**
 * Install Module
 * OOrganizes the installation and update process of the system and its modules.
 *
 * @package     Modules
 * @subpackage  Install
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 */
class InstallModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('install')
             ->title('Installation')
             ->access('admin')
             ->disableCli() // Disabilita install/update/uninstall automatici
             ->setJs('/Assets/install.js')
             ->isCoreModule()
             ->addHeaderLink('Install', '?page=install', 'bi bi-gear-fill')
             ->addHeaderLink('Update modules', '?page=install&action=update-modules', 'bi bi-gear-fill')
             ->version(251100);
    }

    /**
     * Bootstrap - carica i file installer
     */
    public function bootstrap()
    {
        // Carica i file installer solo se necessario
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === 'install') {
            $files = glob($this->getChildClassPath().'/Installer/*.php');
            foreach ($files as $file) {
                require_once $file;
            }
        }
    }


    /**
     * Hook per aggiungere link nella sidebar con badge per updates
     */
    public function hookInit()
    {
        if (!Permissions::check('_user.is_admin')) {
            return;
        }

        $version = Config::get('version');
        $system_needs_update = defined('NEW_VERSION') && NEW_VERSION > $version;

        $current_versions = Config::get('modules_active');
        $settings_versions = Settings::get('module_version');

        // Verifica se ci sono moduli da aggiornare
        $modules_need_update = $this->checkModulesNeedUpdate(
            $current_versions,
            $settings_versions
        );

        // Link nella sidebar
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

        // Badge se ci sono update
        if ($system_needs_update || $modules_need_update) {
            $sidebar_link['badge'] = '!';
            $sidebar_link['badge_color'] = 'danger';
        }

        Theme::set('sidebar.links', $sidebar_link);
    }

    /**
     * Home - gestisce prima installazione o update
     */
    #[AccessLevel('public')]
    #[RequestAction('home')]
    public function actionHome()
    {
        $version = Config::get('version');
        $action = $_REQUEST['action'] ?? '';

        // Carica i file installer
        Hooks::run('install.init');

        // Check: if version doesn't exist, it's a new installation; otherwise, it's an update
        if ($version == null) {
            // FIRST installation
           
            // set the page title
            Theme::set('header.title', 'Ito Installation');
            Response::themePage('empty', __DIR__."/Views/install_page.php",
            ['html' => $this->model->getHtmlModules()]);
    
           
        } else {
            if (Permissions::check('_user.is_admin')) {
                // Update
                $this->handleSystemUpdate($version);
            } else {
                $queryString = Route::getQueryString();
                Route::redirect('?page=deny&redirect='.Route::urlsafeB64Encode($queryString));
            }
        }
    }

    #[RequestAction('save-config')]
    #[AccessLevel('public')]
    public function actionSaveConfig() {  
        if (Permissions::check('_user.is_admin') || Config::get('version') === null) {
            Hooks::run('install.init');
            if ($this->model->checkData($_REQUEST)) {
                // save configuration data
                $this->model->executeInstallConfig($_REQUEST);
                // save the configuration file
                Install::saveConfigFile();
                // save file for protect install-execute
                file_put_contents(MILK_DIR.'/install-execute', time());
                // Reset cache per config.php
                opcache_reset();
                // redirect to install-execute
                Response::themePage('empty', __DIR__."/Views/install_loading_page.php");
            } else {
                $html = $this->model->getHtmlModules();
                Response::themePage('empty', __DIR__."/Views/install_page.php", ['html' => $html]);
            }
        } else {
            die('Access denied???');     
        }
    }

    /**
    
     * Gestisce lista moduli e aggiornamenti
     */
    #[RequestAction('update-modules')]
    public function actionUpdateModules()
    {
        if (!Permissions::check('_user.is_admin')) {
            $queryString = Route::getQueryString();
            Route::redirect('?page=deny&redirect='.Route::urlsafeB64Encode($queryString));
            return;
        }

        $module_data = InstallService::getModuleStatusData();
        // Check for action parameter to perform updates
        if (isset($_POST['update_modules']) && $_POST['update_modules'] == '1') {
            try {
                InstallService::executeModuleUpdates($module_data['modules_to_update']);
            } catch (\Exception $e) {
                Route::redirectError(['page' => 'install', 'action' => 'update-modules'], sprintf(_r('Error updating modules: %s'), $e->getMessage()));
            }
            
            Route::redirect(['page' => 'install', 'action' => 'update-modules']);
        }
      
        $html = InstallService::generateModuleStatusHtml($module_data);
        Response::themePage('default', __DIR__."/Views/update_modules_page.php", ['html' => $html]);
    }

    /**
     * JSON API per update moduli
     */
    #[RequestAction('update-modules-json')]
    public function actionUpdateModulesJson()
    {
        try {
            $module_data = InstallService::getModuleStatusData();

            if (!empty($module_data['modules_to_update'])) {
                $updated_modules = InstallService::executeModuleUpdates($module_data['modules_to_update']);

                Response::json([
                    'status' => 'success',
                    'data' => [
                        'success' => true,
                        'message' => _r('Modules updated successfully'),
                        'updated_modules' => $updated_modules,
                        'total_modules' => count($module_data['modules_to_update'])
                    ]
                ]);
            } else {
                Response::json([
                    'status' => 'success',
                    'data' => [
                        'success' => true,
                        'message' => _r('No modules need updating'),
                        'updated_modules' => [],
                        'total_modules' => 0
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Response::json([
                'status' => 'error',
                'data' => [
                    'success' => false,
                    'message' => sprintf(_r('Error during module update process: %s'), $e->getMessage()),
                    'error_type' => get_class($e),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'error_trace' => $e->getTraceAsString()
                ]
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'status' => 'error',
                'data' => [
                    'success' => false,
                    'message' => sprintf(_r('Critical error during module update: %s'), $e->getMessage()),
                    'error_type' => get_class($e),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'error_trace' => $e->getTraceAsString()
                ]
            ]);
        }
    }

    /**
     * Abilita un modulo
     */
    #[RequestAction('enable-module')]
    public function actionEnableModule()
    {
        if (isset($_POST['module'])) {
            $result = InstallService::enableModule($_POST['module']);
            Response::json(['status' => $result['success'] ? 'success' : 'error', 'data' => $result]);
        }
    }

    /**
     * Disabilita un modulo
     */
    #[RequestAction('disable-module')]
    public function actionDisableModule()
    {
        if (isset($_POST['module'])) {
            $result = InstallService::disableModule($_POST['module']);
            Response::json(['status' => $result['success'] ? 'success' : 'error', 'data' => $result]);
        }
    }

    /**
     * Disinstalla un modulo
     */
    #[RequestAction('uninstall-module')]
    public function actionUninstallModule()
    {
        if (isset($_POST['module'])) {
            $result = InstallService::uninstallActiveModule($_POST['module']);
            Response::json(['status' => $result['success'] ? 'success' : 'error', 'data' => $result]);
        } else {
            Response::json(['status' => 'error', 'data' => ['success' => false, 'message' => _r('No module name provided.')]]);
        }
    }

    /**
     * Disinstalla un modulo attivo (con redirect)
     */
    #[RequestAction('uninstall-active-module')]
    public function actionUninstallActiveModule()
    {
        //php error
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        if (isset($_REQUEST['module'])) {
            $result = InstallService::uninstallActiveModule($_REQUEST['module']);
            if ($result['success']) {
                Route::redirectSuccess(['page' => 'install', 'action' => 'update-modules'], $result['message']);
            } else {
                Route::redirectError(['page' => 'install', 'action' => 'update-modules'], $result['message']);
            }
        }
    }

    /**
     * Scarica un modulo in formato ZIP
     */
    #[RequestAction('download-module')]
    public function actionDownloadModule()
    {
        if (!Permissions::check('_user.is_admin')) {
            $queryString = Route::getQueryString();
            Route::redirect('?page=deny&redirect='.Route::urlsafeB64Encode($queryString));
            return;
        }

        if (isset($_REQUEST['module'])) {
            InstallService::downloadModule($_REQUEST['module']);
        } else {
            Route::redirectError(['page' => 'install', 'action' => 'update-modules'], _r('No module specified.'));
        }
    }

    /**
     * Upload update del sistema
     */
    #[RequestAction('upload-update')]
    public function actionUploadUpdate()
    {
        $result = InstallService::handleUploadUpdate($_FILES, $_POST);
        if (!$result['success']) {
            Route::redirectError($result['redirect'], $result['message']);
        }
        Route::redirect($result['redirect']);
    }

    /**
     * Upload nuovo modulo
     */
    #[RequestAction('upload-module')]
    public function actionUploadModule()
    {
        if (isset($_FILES['module_file'])) {
            $result = InstallService::handleModuleUpload($_FILES, $_POST);

            if ($result['success']) {
                Route::redirectSuccess(['page' => 'install', 'action' => 'update-modules'], $result['message']);
            } else {
                Route::redirectError(['page' => 'install', 'action' => 'update-modules'], $result['message']);
            }
        } else {
            Route::redirectError(['page' => 'install', 'action' => 'update-modules'], _r('No module file provided.'));
        }
    }

    /**
     * Installa un modulo
     */
    #[RequestAction('install-module')]
    public function actionInstallModule()
    {
        if (isset($_POST['module_name'])) {
            $result = InstallService::attemptModuleInstallation($_POST['module_name']);
            Response::json(['status' => $result['success'] ? 'success' : 'error', 'data' => $result]);
        } else {
            Response::json(['status' => 'error', 'data' => ['success' => false, 'message' => _r('No module name provided.')]]);
        }
    }

    /**
     * Step 3 dell'update
     */
    #[RequestAction('update-step3')]
    public function actionUpdateStep3()
    {
        $html = InstallService::executeUpdateStep3($this->model);
        Response::themePage('default', __DIR__."/Views/update_page.php", ['html' => $html]);
    }

    /**
     * Comando CLI per build version
     */
    #[Shell('build-version', system: true)]
    public function buildVersion($custom_version = '')
    {
        // Check if the parameter is "zip" for creating a zip package
        $create_zip = ($custom_version === 'zip');

        // If zip is requested, reset custom_version to empty for normal build
        if ($create_zip) {
            $custom_version = '';
        }

        // Generate new version number (YYMMDD)
        if (is_string($custom_version) && $custom_version !== '' && preg_match('/^[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])$/', $custom_version)) {
            $new_version = $custom_version;
        } else {
            $new_version = date('ymd');
        }

        $version_dir = MILK_DIR.'/../milk-admin-v'.$new_version;

        if (is_dir($version_dir)) {
            Cli::echo('Version already exists. I remove it and continue');
            // Verifico che non sia la cartella attuale
            if ($version_dir == MILK_DIR) {
                Cli::error('Error: cannot remove current directory');
                return;
            }
            // delete the folder and continue
            Install::removeDirectory($version_dir);
        }

        mkdir($version_dir);
        if (!is_dir($version_dir)) {
            Cli::error('Error creating version directory');
            return;
        }

        $folder_milkadmin = $version_dir.'/milkadmin';
        mkdir($folder_milkadmin);
        Install::copyFiles(MILK_DIR, $folder_milkadmin);

        // update version in setup.php
        $setup_file = MILK_DIR.'/setup.php';
        if (file_exists($setup_file)) {
            $setup = file_get_contents($setup_file);
            // Use regex to replace any version number, not just the current one
            $setup = preg_replace(
                "/define\('NEW_VERSION',\s*'[0-9]+'\);/",
                "define('NEW_VERSION', '".$new_version."');",
                $setup
            );
            try {
                File::putContents($folder_milkadmin.'/setup.php', $setup);
            } catch (\App\Exceptions\FileException $e) {
                Cli::error('Failed to write setup.php: ' . $e->getMessage());
                return;
            }
        } else {
            Cli::echo('Warning: setup.php not found at ' . $setup_file);
        }

        // copy public_html folder
        if (is_dir(MILK_DIR.'/../public_html')) {
            mkdir($version_dir.'/public_html');
            Install::copyFiles(MILK_DIR.'/../public_html', $version_dir.'/public_html');
        }

        // copy public_html/.htaccess
        if (is_file(MILK_DIR.'/../public_html/.htaccess')) {
            copy(MILK_DIR.'/../public_html/.htaccess', $version_dir.'/public_html/.htaccess');
        }

        // milkadmin_local Questa è strutturata di default
        mkdir($version_dir.'/milkadmin_local');
        mkdir($version_dir.'/milkadmin_local/storage');
        mkdir($version_dir.'/milkadmin_local/media');
        // add index to storage and media
        File::putContents($version_dir.'/milkadmin_local/storage/index.php', '<?php // Silence is golden');
        File::putContents($version_dir.'/milkadmin_local/media/index.php', '<?php // Silence is golden');

        // copy milkadmin_local/Modules folder if exists
        if (is_dir(MILK_DIR.'/../milkadmin_local/Modules')) {
            mkdir($version_dir.'/milkadmin_local/Modules');
            Install::copyFiles(MILK_DIR.'/../milkadmin_local/Modules', $version_dir.'/milkadmin_local/Modules');
        }

        // config
        $config_file = __DIR__.'/Assets/InstallFiles/installation_config_example.php';
        if (file_exists($config_file)) {
            $new_config = file_get_contents($config_file);
            try {
                File::putContents($version_dir.'/milkadmin_local/config.php', $new_config);
            } catch (\App\Exceptions\FileException $e) {
                Cli::error('Failed to write config.php: ' . $e->getMessage());
                return;
            }
        } else {
            Cli::echo('Warning: installation_config_example.php not found at ' . $config_file);
        }

        // function
        $functions_file = __DIR__.'/Assets/InstallFiles/functions_example.php';
        if (file_exists($functions_file)) {
            $new_functions = file_get_contents($functions_file);
            try {
                File::putContents($version_dir.'/milkadmin_local/functions.php', $new_functions);
            } catch (\App\Exceptions\FileException $e) {
                Cli::error('Failed to write functions.php: ' . $e->getMessage());
                return;
            }
        } else {
            Cli::echo('Warning: functions_example.php not found at ' . $functions_file);
        }

        try {
            $new_readme = file_get_contents(__DIR__.'/Assets/InstallFiles/readme_example.md');
            File::putContents($version_dir.'/milkadmin_local/readme.md', $new_readme);

            $license = file_get_contents(MILK_DIR.'/../LICENSE');
            File::putContents($version_dir.'/LICENSE', $license);

            $readme = file_get_contents(MILK_DIR.'/../readme.md');
            File::putContents($version_dir.'/readme.md', $readme);

            $new_milkadmin = file_get_contents( __DIR__.'/Assets/InstallFiles/milkadmin_example.php');
            File::putContents($version_dir.'/public_html/milkadmin.php', $new_milkadmin);

            $index = file_get_contents( MILK_DIR.'/../index.html');
            File::putContents($version_dir.'/index.html', $index);
        } catch (\App\Exceptions\FileException $e) {
            Cli::error('Failed to write distribution files: ' . $e->getMessage());
            return;
        }

        try {
            // Copy composer.json
            if (file_exists(MILK_DIR.'/../composer.json')) {
                $composer_json = file_get_contents(MILK_DIR.'/../composer.json');
                File::putContents($version_dir.'/composer.json', $composer_json);
            } else {
                Cli::echo('  Warning: composer.json not found in root directory');
            }

            // Copy composer.lock
            if (file_exists(MILK_DIR.'/../composer.lock')) {
                $composer_lock = file_get_contents(MILK_DIR.'/../composer.lock');
                File::putContents($version_dir.'/composer.lock', $composer_lock);
            } else {
                Cli::echo('  Warning: composer.lock not found in root directory');
            }
        } catch (\App\Exceptions\FileException $e) {
            Cli::error('Failed to copy composer files: ' . $e->getMessage());
            return;
        }

        // If "zip" parameter was passed, create a zip file
        if ($create_zip) {
            $this->createZipPackage($new_version);
        } else {
            $real_version_dir = realpath($version_dir) ?: $version_dir;
            Cli::success('New version created in folder: '.$real_version_dir);
            Cli::echo('Note: vendor/ was not copied. Run the following command to generate vendor and install production dependencies:');
            Cli::echo('  cd '.$real_version_dir.' && composer install --no-dev');
        }
    }

    /**
     * Crea un pacchetto ZIP per l'installazione
     */
    private function createZipPackage($new_version)
    {
        $version_dir = MILK_DIR.'/../milk-admin-v'.$new_version;
        $zip_file = $version_dir.'/milk-admin-v'.$new_version.'.zip';
        $install_script = __DIR__.'/Assets/InstallFiles/install_from_zip.php';

        if (!file_exists($install_script)) {
            Cli::error('Error: install_from_zip.php not found at ' . $install_script);
            return;
        }

        // Create ZIP archive
        $zip = new \ZipArchive();
        if ($zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            Cli::error('Error: Cannot create ZIP file at ' . $zip_file);
            return;
        }

        Cli::echo('Creating ZIP package...');

        // Add all files from the version directory to the zip
        $folders_to_add = ['milkadmin', 'milkadmin_local', 'public_html'];

        foreach ($folders_to_add as $folder) {
            $folder_path = $version_dir . '/' . $folder;
            if (is_dir($folder_path)) {
                Cli::echo('  Adding folder: ' . $folder);
                $this->addDirectoryToZip($zip, $folder_path, $folder);
            }
        }

        // Add root files
        $root_files = ['LICENSE', 'readme.md', 'index.html'];
        foreach ($root_files as $file) {
            $file_path = $version_dir . '/' . $file;
            if (file_exists($file_path)) {
                $zip->addFile($file_path, $file);
            }
        }

        $total_files = $zip->numFiles;
        $zip->close();

        // Copy install_from_zip.php outside the version directory
        $install_script_dest = $version_dir.'/install_from_zip.php';
        if (!copy($install_script, $install_script_dest)) {
            Cli::error('Error: Cannot copy install_from_zip.php to ' . $install_script_dest);
            return;
        }

        // Clean up: remove original folders and files, keep only ZIP and install script
        Cli::echo('Cleaning up original files...');

        // Remove folders
        foreach ($folders_to_add as $folder) {
            $folder_path = $version_dir . '/' . $folder;
            if (is_dir($folder_path)) {
                Install::removeDirectory($folder_path);
            }
        }

        // Remove root files
        foreach ($root_files as $file) {
            $file_path = $version_dir . '/' . $file;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        Cli::success('ZIP package created successfully with ' . $total_files . ' files!');
        Cli::success('Location: ' . $zip_file);
        Cli::success('Install script: ' . $install_script_dest);
        Cli::success('Original files cleaned up - only ZIP and install script remain');
    }

    /**
     * Aggiunge ricorsivamente una directory al file ZIP
     */
    private function addDirectoryToZip($zip, $dir, $zip_dir)
    {
        if (!is_dir($dir)) {
            Cli::echo('Warning: Directory not found: ' . $dir);
            return;
        }

        // Get the real path to handle .. and symlinks correctly
        $real_dir = realpath($dir);

        $file_count = 0;
        $dir_count = 0;
        $directories = [$zip_dir . '/' => true]; // Always include the root folder

        // Check if directory has content
        $items = @scandir($real_dir);
        $has_content = count($items) > 2; // More than . and ..

        if ($has_content) {
            // Collect all directories
            $dirIterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($real_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($dirIterator as $item) {
                if ($item->isDir()) {
                    $dir_path = $item->getRealPath();
                    $relative_path = substr($dir_path, strlen($real_dir) + 1);
                    $zip_path = $zip_dir . '/' . $relative_path;
                    $directories[$zip_path] = true;
                }
            }

            // Add all files
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($real_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    $relative_path = substr($file_path, strlen($real_dir) + 1);
                    $zip_path = $zip_dir . '/' . $relative_path;

                    if ($zip->addFile($file_path, $zip_path)) {
                        $file_count++;
                    }

                    // Remove parent directories (they'll be created automatically with files)
                    $parent_dir = dirname($zip_path);
                    while ($parent_dir !== '.' && $parent_dir !== $zip_dir) {
                        unset($directories[$parent_dir]);
                        $parent_dir = dirname($parent_dir);
                    }
                }
            }
        }

        // Add empty directories
        foreach (array_keys($directories) as $empty_dir) {
            $zip->addEmptyDir($empty_dir);
            $dir_count++;
        }

        Cli::echo('    Added ' . $file_count . ' files + ' . $dir_count . ' directories');
    }

    /**
     * CLI Command to update paths and URL configuration
     * Usage: php milkadmin/cli.php update-paths [new_url]
     *
     * If new_url is not provided, only directory paths will be updated.
     * If new_url is provided, both paths and URL will be updated.
     */
    #[Shell('update-paths', system: true)]
    public function updatePaths($new_url = '')
    {
        Cli::drawTitle("Update Paths and URL Configuration", 8);

        // Get current paths
        $milk_dir = MILK_DIR;
        $local_dir = LOCAL_DIR;
        $project_root = dirname($milk_dir);

        Cli::echo("\n\033[1;37mCurrent configuration:\033[0m");
        Cli::echo("  MILK_DIR:  \033[0;36m" . $milk_dir . "\033[0m");
        Cli::echo("  LOCAL_DIR: \033[0;36m" . $local_dir . "\033[0m");

        // Update public_html/milkadmin.php
        $milkadmin_php = $project_root . '/public_html/milkadmin.php';

        if (!file_exists($milkadmin_php)) {
            Cli::error("File not found: " . $milkadmin_php);
            return;
        }

        Cli::echo("\n\033[1;33m[1/2]\033[0m Updating paths in \033[0;37mpublic_html/milkadmin.php\033[0m...");

        $content = file_get_contents($milkadmin_php);
        $original_content = $content;

        // Update MILK_DIR path
        $content = preg_replace(
            '/define\(\'MILK_DIR\',\s*realpath\(__DIR__\s*\.\s*"\/\.\.\/[^"]+"\)\);/',
            'define(\'MILK_DIR\', realpath(__DIR__."/../milkadmin"));',
            $content
        );

        // Update LOCAL_DIR path
        $content = preg_replace(
            '/define\(\'LOCAL_DIR\',\s*realpath\(__DIR__\s*\.\s*"\/\.\.\/[^"]+"\)\);/',
            'define(\'LOCAL_DIR\', realpath(__DIR__."/../milkadmin_local"));',
            $content
        );

        if ($content !== $original_content) {
            try {
                File::putContents($milkadmin_php, $content);
                Cli::success("  ✓ Paths updated successfully in milkadmin.php");
            } catch (\App\Exceptions\FileException $e) {
                Cli::error("  Failed to update milkadmin.php: " . $e->getMessage());
                return;
            }
        } else {
            Cli::echo("  \033[0;33m✓ No changes needed in milkadmin.php\033[0m");
        }

        // Update URL in config.php if new_url is provided
        if (!empty($new_url)) {
            $config_php = $local_dir . '/config.php';

            if (!file_exists($config_php)) {
                Cli::error("File not found: " . $config_php);
                return;
            }

            Cli::echo("\n\033[1;33m[2/2]\033[0m Updating URL in \033[0;37mmilkadmin_local/config.php\033[0m...");

            // Make sure URL ends with /
            if (substr($new_url, -1) !== '/') {
                $new_url .= '/';
            }

            $config_content = file_get_contents($config_php);
            $original_config = $config_content;

            // Update base_url in config
            $config_content = preg_replace(
                '/\$conf\[\'base_url\'\]\s*=\s*\'[^\']*\';/',
                "\$conf['base_url'] = '" . addslashes($new_url) . "';",
                $config_content
            );

            if ($config_content !== $original_config) {
                try {
                    File::putContents($config_php, $config_content);
                    Cli::success("  ✓ URL updated successfully to: \033[1;36m" . $new_url . "\033[0m");
                } catch (\App\Exceptions\FileException $e) {
                    Cli::error("  Failed to update config.php: " . $e->getMessage());
                    return;
                }
            } else {
                Cli::echo("  \033[0;33m✓ No changes needed in config.php\033[0m");
            }

            // Also update BASE_URL constant in milkadmin.php if it exists
            $milkadmin_content = file_get_contents($milkadmin_php);
            $original_milkadmin = $milkadmin_content;

            $milkadmin_content = preg_replace(
                '/define\(\'BASE_URL\',\s*\'[^\']*\'\);/',
                "define('BASE_URL', '" . addslashes($new_url) . "');",
                $milkadmin_content
            );

            if ($milkadmin_content !== $original_milkadmin) {
                try {
                    File::putContents($milkadmin_php, $milkadmin_content);
                    Cli::success("  ✓ BASE_URL updated in milkadmin.php");
                } catch (\App\Exceptions\FileException $e) {
                    Cli::error("  Failed to update BASE_URL in milkadmin.php: " . $e->getMessage());
                }
            }
        } else {
            Cli::echo("\n\033[0;33m[2/2]\033[0m Skipping URL update (no new URL provided)");
            Cli::echo("  To update URL, use: \033[0;37mphp milkadmin/cli.php update-paths <new_url>\033[0m");
        }

        Cli::echo("\n\033[1;32m✓ Configuration update completed!\033[0m\n");
    }

    /**
     * Verifica se ci sono moduli che necessitano aggiornamento
     */
    private function checkModulesNeedUpdate($current_versions, $settings_versions): bool
    {
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
            Settings::set('module_version', $current_versions);
            return false;
        }

        foreach ($settings_versions as $module => $data) {
            if (is_array($data) && isset($data['version'])) {
                $settings_versions_only[$module] = $data['version'];
            } elseif (!is_array($data)) {
                $settings_versions_only[$module] = $data; // Legacy format
            }
        }

        // Check if any current version is higher than settings version (needs update)
        foreach ($current_versions_only as $module => $current_version) {
            $settings_version = $settings_versions_only[$module] ?? null;
            if ($settings_version !== null && $current_version > $settings_version) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gestisce l'update del sistema
     */
    private function handleSystemUpdate($version)
    {
        $temp_dir = \App\Get::tempDir();
        $update_file = $temp_dir . '/update-to-do.zip';
        $update_dir = $temp_dir . '/update-extracted';

        // First, check if there is an update to process
        if (file_exists($update_file) && ($_REQUEST['action'] ?? '') != 'upload-update') {
            // Process the update
            $update_result = InstallService::processUpdate($update_file, $update_dir);

            if ($update_result === true) {
                // Update completed successfully
                MessagesHandler::addSuccess(_r('Update completed successfully. The system has been upgraded to the new version.'));

                // DO NOT run execute_update here – it will run on the next load
                // when NEW_VERSION > $version

                // Reload the page to show the new version
                Route::redirect(['page' => 'install', 'action' => 'update-step3']);
            } else {
                // Error during update
                MessagesHandler::addError(_r('Error during update: ') . $update_result);

                // Remove update files in case of error
                if (file_exists($update_file)) {
                    unlink($update_file);
                }
                if (is_dir($update_dir)) {
                    try {
                        Install::removeDirectory($update_dir);
                    } catch (\Exception $e) {
                        // Ignore directory removal errors
                    }
                }
            }
        }

        // Show update page
        // the new version is found in the NEW_VERSION constant
        if (defined('NEW_VERSION') && NEW_VERSION > $version) {
            $html = InstallService::executeUpdateStep3($this->model);
        } else if (NEW_VERSION == $version) {
            // nothing to update
            $html = '';
        } else {
            $html = '<p class="alert alert-danger">'.sprintf(_r('The system had version %s installed. Now a new less recent version %s has been proposed'), $version, NEW_VERSION).'</p>';
        }
        Response::themePage('default', __DIR__."/Views/update_page.php", ['html' => $html]);
    }
}



// blocco la versione per evitare il login e completare l'installazione
if (($_REQUEST['page'] ?? '') == "install" && ( $_REQUEST['action'] ?? '') == 'install-execute' && is_file(MILK_DIR.'/install-execute')) {
        Config::set('version', null);
}
// Hook per eseguire l'installazione
Hooks::set('after_modules_loaded', function() {
    if (($_REQUEST['page'] ?? '') == "install" && ( $_REQUEST['action'] ?? '') == 'install-execute' && is_file(MILK_DIR.'/install-execute')) {
        Hooks::run('install.init');

        if (is_file(MILK_DIR.'/install-execute')) {
            unlink(MILK_DIR.'/install-execute');
        } else {
            die('Security check failed, please try again and check the directory permissions');
        }
        $model = new InstallModel();
        $model->executeInstall();
        Response::themePage('empty', __DIR__."/Views/install_done_page.php");
        
    }
}, 50);
