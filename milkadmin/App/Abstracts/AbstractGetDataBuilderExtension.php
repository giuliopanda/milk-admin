<?php
namespace App\Abstracts;

use ReflectionClass;

!defined('MILK_DIR') && die();

/**
 * Abstract GetDataBuilder Extension
 *
 * Base class for GetDataBuilder extensions that provides lifecycle hooks.
 * Extensions can extend builder configuration and data processing logic.
 *
 * Example usage:
 * ```php
 * class MyExtension extends AbstractGetDataBuilderExtension
 * {
 *     public function configure($builder): void
 *     {
 *         // Extend builder configuration
 *         $builder->field('status')->label('Status');
 *     }
 *
 *     public function beforeGetData(): void
 *     {
 *         // Modify query before data retrieval
 *     }
 *
 *     public function afterGetData(array $data): array
 *     {
 *         // Process data after retrieval
 *         return $data;
 *     }
 * }
 * ```
 *
 * @package App\Abstracts
 */
abstract class AbstractGetDataBuilderExtension
{
    /**
     * The builder instance being extended
     * @var object GetDataBuilder instance
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
     * Hook called during the builder's configure() method
     * Can be overridden by child classes to add columns, filters, actions, etc.
     *
     * @param object $builder The builder instance
     * @return void
     */
    public function configure(object $builder): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called before data is retrieved
     * Can be used to modify query or add filters
     *
     * @return void
     */
    public function beforeGetData(): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called after data is retrieved
     * Can be used to modify the data array before returning
     *
     * @param array $data The data array
     * @return array Modified data array
     */
    public function afterGetData(array $data): array
    {
        // Default implementation - can be overridden
        return $data;
    }
}
