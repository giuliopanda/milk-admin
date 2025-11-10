<?php
namespace App\Abstracts;

use App\{Cli, Hooks};
use App\Abstracts\Traits\AttributeShellTrait;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Abstract Shell Class
 *
 * This class serves as the base for CLI command management in the framework.
 * It provides a standardized structure for defining CLI commands using attributes.
 *
 * @example
 * ```php
 * class PostsShell extends \App\Abstracts\AbstractShell {
 *     #[Shell('import')]
 *     public function importPosts($file) {
 *         Cli::echo("Importing posts from: " . $file);
 *     }
 *
 *     #[Shell('export')]
 *     public function exportPosts($format = 'json') {
 *         Cli::echo("Exporting posts as: " . $format);
 *     }
 * }
 * ```
 *
 * @package     App
 * @subpackage  Abstracts
 */


abstract class AbstractShell {
    
    use AttributeShellTrait;

    /**
     * The module instance that owns this shell
     * @var object|null
     */
    protected $module = null;

    /**
     * The page/module name for command prefixing
     * @var string|null
     */
    protected $page = null;

    protected $model = null;

    protected $disable_cli = false;

    /**
     * Constructor
     */
    public function __construct() {
        Hooks::set('cli-init', [$this, 'setupAttributeShellTraitCliHooks'], 90);
    }

    /**
     * Set the module that owns this shell
     * 
     * @param object $module The module instance
     * @return void
     */
    public function setHandleShell($module): void {
        $this->module = $module;
        $this->page = $module->getPage();
        $this->model = $module->getModel();
        $this->disable_cli = $module->getDisableCli();
    }

    /**
     * Get the page/module name for this shell
     * 
     * @return string|null
     */
    public function getPage(): ?string {
        return $this->page;
    }

    /**
     * Get the module instance
     * 
     * @return object|null
     */
    public function getModule(): ?object {
        return $this->module;
    }

    public function getModel(): ?object {
        return $this->model;
    }
}