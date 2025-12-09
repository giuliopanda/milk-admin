<?php
namespace App\Abstracts;

use App\Interfaces\ModelExtensionInterface;
use App\Attributes\{ToDisplayValue, SetValue, Validate, ToDatabaseValue};
use ReflectionClass;
use ReflectionMethod;

!defined('MILK_DIR') && die();

/**
 * Abstract Model Extension
 *
 * Base class for Model extensions that provides automatic attribute scanning.
 * Extensions can define methods with attributes (#[ToDatabaseValue], #[ToDisplayValue], etc.)
 * and they will be automatically registered in the Model.
 *
 * Example usage:
 * ```php
 * class MyExtension extends AbstractModelExtension
 * {
 *     #[ToDatabaseValue('created_by')]
 *     public function setCreatedBy($current_record)
 *     {
 *         $user = \App\Get::make('Auth')->getUser();
 *         return $user->id ?? 0;
 *     }
 * }
 * ```
 *
 * @package App\Abstracts
 */
abstract class AbstractModelExtension 
{
    /**
     * The model instance being extended
     * @var \WeakReference AbstractModel
     */
    protected \WeakReference $model;

    /**
     * Constructor
     *
     * @param AbstractModel $model The model being extended
     */
    public function __construct(AbstractModel $model)
    {
        $this->model = \WeakReference::create($model);
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

                    // Make the property accessible (even if protected/private)
                    $property->setAccessible(true);

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
     * Configure the model by adding fields and rules
     * Can be overridden by child classes to extend model configuration
     *
     * @param RuleBuilder $rule_builder The rule builder instance
     * @return void
     */
    public function configure(RuleBuilder $rule_builder): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called after attribute methods are scanned and cached
     * Automatically scans extension methods for attributes
     *
     * @return void
     */
    public function onAttributeMethodsScanned(): void
    {
        $this->scanAndCacheAttributeMethods();
    }

    /**
     * Scan extension methods for attributes and register them in the Model
     * Similar to AbstractModel::scanAndCacheAttributeMethods() but for extensions
     *
     * Supported attributes:
     * - #[ToDisplayValue(field_name)] - For formatting field values for display
     * - #[ToDatabaseValue(field_name)] - For processing values before saving to database
     * - #[SetValue(field_name)] - For setting field values
     * - #[Validate(field_name)] - For custom field validation
     *
     * @return void
     */
    protected function scanAndCacheAttributeMethods(): void
    {
        // Use static::class to get the concrete extension class, not AbstractModelExtension
        $class_name = static::class;

        // Get reflection of the extension class
        $reflection = new ReflectionClass($class_name);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip methods inherited from AbstractModelExtension
            if ($method->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            // Skip methods from ModelExtensionInterface
            if ($method->getDeclaringClass()->isInterface()) {
                continue;
            }
            $model = $this->model->get();
            // Check for ToDisplayValue attribute #[ToDisplayValue(field_name)]
            $attributes = $method->getAttributes(ToDisplayValue::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $model->registerMethodHandler($instance->field_name, 'get_formatted', [$this, $method->getName()]);
            }

            // Check for ToDatabaseValue attribute #[ToDatabaseValue(field_name)]
            $attributes = $method->getAttributes(ToDatabaseValue::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $model->registerMethodHandler($instance->field_name, 'get_sql', [$this, $method->getName()]);
            }

            // Check for SetValue attribute #[SetValue(field_name)]
            $attributes = $method->getAttributes(SetValue::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $model->registerMethodHandler($instance->field_name, 'set_value', [$this, $method->getName()]);
            }

            // Check for Validate attribute #[Validate(field_name)]
            $attributes = $method->getAttributes(Validate::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $model->registerMethodHandler($instance->field_name, 'validate', [$this, $method->getName()]);
            }
        }
    }
}
