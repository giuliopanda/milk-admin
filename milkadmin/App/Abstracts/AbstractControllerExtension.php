<?php
namespace App\Abstracts;

use App\Attributes\{RequestAction, AccessLevel};
use ReflectionClass;
use ReflectionMethod;

!defined('MILK_DIR') && die();

/**
 * Abstract Controller Extension
 *
 * Base class for Controller extensions that provides automatic attribute scanning.
 * Extensions can define methods with attributes (#[RequestAction], #[AccessLevel])
 * and they will be automatically registered in the Controller.
 *
 * Example usage:
 * ```php
 * class MyExtension extends AbstractControllerExtension
 * {
 *     #[RequestAction('my-action')]
 *     #[AccessLevel('registered')]
 *     public function myCustomAction()
 *     {
 *         Response::themePage('default', ['content' => 'Hello from extension!']);
 *     }
 * }
 * ```
 *
 * @package App\Abstracts
 */
abstract class AbstractControllerExtension
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
     * Hook called after the Controller's initialization
     * Can be overridden by child classes
     *
     * @return void
     */
    public function onInit(): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called during the Controller's hookInit method
     * Can be overridden by child classes
     *
     * @return void
     */
    public function onHookInit(): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called at the beginning of handleRoutes
     * Can be overridden by child classes
     *
     * @return void
     */
    public function onHandleRoutes(): void
    {
        // Default implementation - can be overridden
    }
}
