<?php
namespace App\Abstracts;

use App\Interfaces\ModuleExtensionInterface;
use ReflectionClass;

!defined('MILK_DIR') && die();

/**
 * Abstract Module Extension
 *
 * Base class for Module extensions that provides lifecycle hooks.
 * Extensions can extend module configuration, bootstrap logic, and initialization.
 *
 * Example usage:
 * ```php
 * class MyExtension extends AbstractModuleExtension
 * {
 *     public function configure(ModuleRuleBuilder $rule): void
 *     {
 *         // Extend module configuration
 *         $rule->addMenuLink(['url' => 'action=settings', 'name' => 'Settings', 'icon' => '']);
 *     }
 *
 *     public function bootstrap(): void
 *     {
 *         // Initialize extension components
 *     }
 *
 *     public function init(): void
 *     {
 *         // Load assets or page-specific setup
 *     }
 * }
 * ```
 *
 * @package App\Abstracts
 */
abstract class AbstractModuleExtension
{
    /**
     * The module instance being extended
     * @var AbstractModule
     */
    protected AbstractModule $module;

    /**
     * Constructor
     *
     * @param AbstractModule $module The module being extended
     */
    public function __construct(AbstractModule $module)
    {
        $this->module = $module;
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
     * Hook called during the Module's configure() method
     * Can be overridden by child classes
     *
     * @param ModuleRuleBuilder $rule_builder The rule builder instance
     * @return void
     */
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called after the Module's bootstrap() method
     * Can be overridden by child classes
     *
     * @return void
     */
    public function bootstrap(): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called after the Module's init() method
     * Can be overridden by child classes
     *
     * @return void
     */
    public function init(): void
    {
        // Default implementation - can be overridden
    }
}
