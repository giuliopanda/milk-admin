<?php
namespace App\Abstracts;

use App\Abstracts\Traits\InstallationTrait;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Abstract Install Class
 *
 * This class provides installation, update, and uninstallation functionality
 * for modules. When a module has an Install class that extends this abstract,
 * it will handle all installation operations instead of the InstallationTrait.
 *
 * @package     App
 * @subpackage  Abstracts
 */
abstract class AbstractInstall {

    use InstallationTrait;

    /**
     * Reference to the module that owns this install class
     * @var object
     */
    protected $module;
    
    /**
     * Model instance
     * @var object
     */
    protected $model;
    
    /**
     * Page name
     * @var string
     */
    protected $page;
    
    /**
     * Module title
     * @var string
     */
    protected $title;
    
    /**
     * Module version
     * @var string
     */
    protected $version;
    
    /**
     * Module path
     * @var string
     */
    protected $path;

    /**
     * Disable CLI
     * @var bool
     */
    protected $disable_cli;
    /**
     * Indicates if this is a core module
     * @var bool
     */
    protected $is_core_module = false;
    /**
     * Constructor
     * 
     * @param object $module The module instance
     */
    public function __construct() {
      
    }

    /**
     * Set handle install - provides access to module properties
     * 
     * This method is called automatically by the AbstractModule after bootstrap
     * to provide the Install class with access to the module's properties and methods.
     * Similar to how the router gets access via setHandleRoutes.
     * 
     * @param object $module The module instance
     */
    public function setHandleInstall($module) {
        $this->module = $module;
        $this->model = $module->getModel();
        $this->page = $module->getPage();
        $this->title = $module->getTitle();
        $this->version = $module->getVersion();
        $this->path = $module->getChildClassPath();
        $this->disable_cli = $module->getDisableCli();
        $this->is_core_module = $module->isCoreModule();

    }

     /**
     * Get the file path of the child class
     * 
     * Returns the directory path of the child module class.
     * 
     * @return string Directory path
     */
    public function getChildClassPath() {
        $childClass = get_called_class();
        $reflection = new \ReflectionClass($childClass);
        $filePath = $reflection->getFileName();
        $directoryPath = dirname($filePath);
        return $directoryPath;
    }


}