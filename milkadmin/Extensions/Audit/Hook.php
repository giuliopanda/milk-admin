<?php
namespace Extensions\Audit;

use App\Abstracts\AbstractHookExtension;
use App\Attributes\HookCallback;

!defined('MILK_DIR') && die();

/**
 * Audit Hook Extension
 *
 * Example extension for Hook classes.
 * Demonstrates lifecycle hooks and #[HookCallback] attribute usage.
 *
 * Extensions can define their own hook callbacks using #[HookCallback]
 * attributes, which will be automatically scanned and registered.
 *
 * @package Extensions\Audit
 */
class Hook extends AbstractHookExtension
{
    /**
     * Hook called after Hook initialization
     * Use this for early setup
     *
     * @return void
     */
    public function onInit(): void
    {
        // Extension initialized
        // Example: Set up extension state or logging
    }

    /**
     * Hook called after all hooks are registered
     * Main class and extension hooks are now registered
     *
     * @return void
     */
    public function onRegisterHooks(): void
    {
        // All hooks have been registered
        // Example: Log registered hooks or perform post-registration setup
        // $hooks = $this->hook->getRegisteredHooks();
        // \App\Cli::info('Registered hooks: ' . count($hooks));
    }

    /**
     * Example hook callback for user login events
     * This will be automatically registered and called when Hooks::run('user.login', $data)
     *
     * @param array $data User login data
     * @return array Modified data
     */
    #[HookCallback('init', 10)]
    public function testInit()
    {
        
    }


   
}
