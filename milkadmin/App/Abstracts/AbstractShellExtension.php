<?php
namespace App\Abstracts;

!defined('MILK_DIR') && die();

/**
 * Abstract Shell Extension
 *
 * Base class for Shell extensions that provides lifecycle hooks.
 *
 * Example usage:
 * ```php
 * class MyExtension extends AbstractShellExtension
 * {
 *     public function onInit(): void
 *     {
 *         // Initialize extension
 *     }
 *
 *     public function onSetup(): void
 *     {
 *         // Access $this->module->getPage(), etc.
 *     }
 * }
 * ```
 *
 * @package App\Abstracts
 */
abstract class AbstractShellExtension
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
     * Hook called after the Shell initialization
     * Can be overridden by child classes
     *
     * @return void
     */
    public function onInit(): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called after setHandleShell is called
     * Can be overridden by child classes
     *
     * @return void
     */
    public function onSetup(): void
    {
        // Default implementation - can be overridden
    }
}
