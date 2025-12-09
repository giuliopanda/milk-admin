<?php
namespace Extensions\Audit;

use App\Abstracts\AbstractApiExtension;
use App\Attributes\ApiEndpoint;


!defined('MILK_DIR') && die();

/**
 * Audit API Extension
 *
 * Minimal example extension for API classes.
 * Demonstrates lifecycle hooks for API extensions.
 *
 * @package Extensions\Audit
 */
class Api extends AbstractApiExtension
{
    /**
     * Hook called after API initialization
     * Use this for early setup
     *
     * @return void
     */
    public function onInit(): void
    {
        // Extension initialized
        // At this point, module/model are not yet available
        // Example: Set up API middleware or global handlers
    }

    /**
     * Hook called after setHandleApi
     * Module, page, and model are now available
     *
     * @return void
     */
    public function onSetup(): void
    {
        // API is now fully configured
        // Access module: $this->module
        // Access model: $this->module->getModel()
        // Access page: $this->module->getPage()

        // Example: Log API initialization
        // \App\Logs::set('audit', 'INFO', 'API initialized for: ' . $this->module->getPage());
    }
    
    // OK
    #[ApiEndpoint('/audit/test', 'GET')]
    public function testEndpoint()
    {
        return ['status' => 'ok', 'module' => $this->module->getPage()];
    }
}
