<?php
namespace App\Abstracts;

use App\Interfaces\HookExtensionInterface;

!defined('MILK_DIR') && die();

/**
 * Abstract Hook Extension
 *
 * Base class for Hook extensions that provides lifecycle hooks.
 * Extensions can also use #[HookCallback] attributes on their methods
 * which will be automatically registered.
 *
 * Example usage:
 * ```php
 * class MyExtension extends AbstractHookExtension
 * {
 *     public function onInit(): void
 *     {
 *         // Initialize extension
 *     }
 *
 *     public function onRegisterHooks(): void
 *     {
 *         // Called after all hooks are registered
 *     }
 *
 *     #[HookCallback('my_hook', 10)]
 *     public function myHookHandler($data)
 *     {
 *         // This will be automatically registered as a hook
 *         return $data;
 *     }
 * }
 * ```
 *
 * @package App\Abstracts
 */
abstract class AbstractHookExtension
{
    /**
     * The Module instance being extended
     * @var \WeakReference AbstractModule
     */
    protected \WeakReference $module;

    /**
     * Constructor
     *
     * @param AbstractModule $module The Module being extended
     */
    public function __construct(AbstractModule $module)
    {
        $this->module = \WeakReference::create($module);
    }

    /**
     * Apply parameters to the extension properties
     * Sets protected properties from the parameters array
     *
     * @param array $params Parameters to apply
     * @return void
     */
    public function applyParameters(array $params): void
    {
        if (empty($params)) {
            return;
        }

        $reflection = new \ReflectionClass($this);

        foreach ($params as $key => $value) {
            try {
                // Check if the property exists
                if ($reflection->hasProperty($key)) {
                    $property = $reflection->getProperty($key);
                    
                    // Set the value
                    $property->setValue($this, $value);
                }
            } catch (\ReflectionException $e) {
                // Property doesn't exist or can't be set - silently skip
                continue;
            }
        }
    }

    /**
     * Hook called after the Hook initialization
     * Can be overridden by child classes
     *
     * @return void
     */
    public function onInit(): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called after registerHooks completes
     * Can be overridden by child classes
     *
     * @return void
     */
    public function onRegisterHooks(): void
    {
        // Default implementation - can be overridden
    }
}
