<?php
namespace App\Abstracts;

use ReflectionClass;

!defined('MILK_DIR') && die();

/**
 * Abstract FormBuilder Extension
 *
 * Base class for FormBuilder extensions that provides lifecycle hooks.
 * Extensions can extend builder configuration to add fields, modify form behavior, etc.
 *
 * Example usage:
 * ```php
 * class MyExtension extends AbstractFormBuilderExtension
 * {
 *     public function configure($builder): void
 *     {
 *         // Add or modify form fields
 *         $builder->addField('custom_field', 'text', ['label' => 'Custom Field']);
 *     }
 *
 *     public function beforeRender(array $fields): array
 *     {
 *         // Modify fields before rendering
 *         return $fields;
 *     }
 *
 *     public function afterSave(array $request): void
 *     {
 *         // Execute custom logic after save
 *     }
 * }
 * ```
 *
 * @package App\Abstracts
 */
abstract class AbstractFormBuilderExtension
{
    /**
     * The builder instance being extended
     * @var object FormBuilder instance
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
     * Can be overridden by child classes to add form fields, modify configuration, etc.
     *
     * @param object $builder The builder instance
     * @return void
     */
    public function configure(object $builder): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called before form is rendered
     * Can be used to modify fields before rendering
     *
     * @param array $fields The form fields array
     * @return array Modified fields array
     */
    public function beforeRender(array $fields): array
    {
        // Default implementation - can be overridden
        return $fields;
    }

    /**
     * Hook called after form data is saved
     * Can be used to execute custom logic after save
     *
     * @param mixed $formBuilderOrRequest The FormBuilder instance or request data (for backwards compatibility)
     * @param array|null $request The saved request data (when FormBuilder is passed as first param)
     * @return void
     */
    public function afterSave($formBuilderOrRequest, ?array $request = null): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called before form data is saved
     * Can be used to validate or modify data before save
     *
     * @param array $request The request data to be saved
     * @return array Modified request data
     */
    public function beforeSave(array $request): array
    {
        // Default implementation - can be overridden
        return $request;
    }
}
