<?php
namespace App\Abstracts;

use ReflectionClass;

!defined('MILK_DIR') && die();

/**
 * Abstract SearchBuilder Extension
 *
 * Base class for SearchBuilder extensions that provides lifecycle hooks.
 * Extensions can extend builder configuration to add search fields, filters, etc.
 *
 * Example usage:
 * ```php
 * class MyExtension extends AbstractSearchBuilderExtension
 * {
 *     public function configure($builder): void
 *     {
 *         // Add search fields
 *         $builder->actionList('status')
 *             ->label('Status:')
 *             ->options(['active' => 'Active', 'inactive' => 'Inactive'])
 *             ->selected('active');
 *     }
 * }
 * ```
 *
 * @package App\Abstracts
 */
abstract class AbstractSearchBuilderExtension
{
    /**
     * The builder instance being extended
     * @var object SearchBuilder instance
     */
    protected object $builder;

    /**
     * Constructor
     *
     * @param object $builder The builder being extended
     */
    public function __construct(object $builder)
    {
        $this->builder = $builder;
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
     * Hook called during the builder's initialization
     * Can be overridden by child classes to add search fields, filters, etc.
     *
     * @param object $builder The builder instance
     * @return void
     */
    public function configure(object $builder): void
    {
        // Default implementation - can be overridden
    }
}
