<?php
namespace Extensions\Audit;

use App\Abstracts\AbstractShellExtension;

!defined('MILK_DIR') && die();

/**
 * Audit Shell Extension
 *
 * Minimal example extension for Shell (CLI) classes.
 * Demonstrates lifecycle hooks for Shell extensions.
 *
 * @package Extensions\Audit
 */
class Shell extends AbstractShellExtension
{
    /**
     * Hook called after Shell initialization
     * Use this for early setup
     *
     * @return void
     */
    public function onInit(): void
    {
        // Extension initialized
        // At this point, module/model are not yet available
        // Example: Set up CLI helpers or global command handlers
    }

    /**
     * Hook called after setHandleShell
     * Module, page, and model are now available
     *
     * @return void
     */
    public function onSetup(): void
    {
        // Shell is now fully configured
        // Access module: $this->module
        // Access model: $this->module->getModel()
        // Access page: $this->module->getPage()

        // Example: Log CLI initialization
        // \App\Cli::info('Audit Shell extension loaded for: ' . $this->module->getPage());
    }
}
