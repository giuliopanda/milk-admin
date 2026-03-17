<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;
use App\Abstracts\AbstractModelExtension;
use App\Abstracts\RuleBuilder;
use App\Attributes\{DefaultQuery, GetRawValue, Query as QueryAttribute, SetValue, ToDatabaseValue, ToDisplayValue, Validate};
use App\ExtensionLoader;
use ReflectionClass;
use ReflectionMethod;

!defined('MILK_DIR') && die();

class ModelBootstrapService
{
    public function __construct(
        private ?ExtensionRegistryService $extensionRegistryService = null
    ) {
        $this->extensionRegistryService ??= new ExtensionRegistryService();
    }

    public function boot(
        AbstractModel $model,
        array $extensions,
        string $table,
        string $dbType,
        string $primaryKey
    ): array {
        $ruleBuilder = new RuleBuilder();
        $normalizedExtensions = $this->extensionRegistryService->normalize($extensions);
        $loadedExtensions = $this->loadExtensions($normalizedExtensions, $model);

        $this->invokeConfigure($model, $ruleBuilder);

        $table = $ruleBuilder->getTable() ?? $table;
        $dbType = $ruleBuilder->getDbType() ?? $dbType;
        $primaryKey = $ruleBuilder->getPrimaryKey() ?? $primaryKey;

        if ($ruleBuilder->getExtensions() !== null) {
            $mergedExtensions = $this->extensionRegistryService->merge($normalizedExtensions, $ruleBuilder->getExtensions());
            if (count($mergedExtensions) > count($normalizedExtensions)) {
                $loadedExtensions = $this->loadExtensions($mergedExtensions, $model);
            }
            $normalizedExtensions = $mergedExtensions;
        }

        ExtensionLoader::callHook($loadedExtensions, 'configure', [$ruleBuilder]);
        [$defaultQueries, $namedQueries] = $this->discoverAttributeMethods($model, $loadedExtensions);

        return [
            'rule_builder' => $ruleBuilder,
            'extensions' => $normalizedExtensions,
            'loaded_extensions' => $loadedExtensions,
            'table' => $table,
            'db_type' => $dbType,
            'primary_key' => $primaryKey,
            'default_queries' => $defaultQueries,
            'named_queries' => $namedQueries,
        ];
    }

    private function loadExtensions(array $extensions, AbstractModel $model): array
    {
        if ($extensions === []) {
            return [];
        }

        return ExtensionLoader::load($extensions, 'Model', $model);
    }

    private function invokeConfigure(AbstractModel $model, RuleBuilder $ruleBuilder): void
    {
        $configure = \Closure::bind(
            function (RuleBuilder $builder): void {
                $this->configure($builder);
            },
            $model,
            $model
        );

        $configure($ruleBuilder);
    }

    private function discoverAttributeMethods(AbstractModel $model, array $loadedExtensions): array
    {
        $defaultQueries = [];
        $namedQueries = [];

        $targets = array_merge([$model], array_values($loadedExtensions));

        foreach ($targets as $target) {
            [$defaultQueries, $namedQueries] = $this->scanAttributesFromClass(
                $model,
                $target,
                $defaultQueries,
                $namedQueries
            );
        }

        return [$defaultQueries, $namedQueries];
    }

    private function scanAttributesFromClass(
        AbstractModel $model,
        object $target,
        array $defaultQueries,
        array $namedQueries
    ): array {
        $reflection = new ReflectionClass($target);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);

        foreach ($methods as $method) {
            $declaringClass = $method->getDeclaringClass()->getName();
            if ($declaringClass === AbstractModel::class || $declaringClass === AbstractModelExtension::class) {
                continue;
            }

            $this->registerMethodHandlers($model, $target, $method, ToDisplayValue::class, 'get_formatted');
            $this->registerMethodHandlers($model, $target, $method, ToDatabaseValue::class, 'get_sql');
            $this->registerMethodHandlers($model, $target, $method, SetValue::class, 'set_value');
            $this->registerMethodHandlers($model, $target, $method, Validate::class, 'validate');
            $this->registerMethodHandlers($model, $target, $method, GetRawValue::class, 'get_raw');

            foreach ($method->getAttributes(DefaultQuery::class) as $_attribute) {
                $defaultQueries[$method->getName()] = $this->createMethodCallable($target, $method);
            }

            foreach ($method->getAttributes(QueryAttribute::class) as $attribute) {
                $instance = $attribute->newInstance();
                $namedQueries[$instance->name] = $this->createMethodCallable($target, $method);
            }
        }

        return [$defaultQueries, $namedQueries];
    }

    private function registerMethodHandlers(
        AbstractModel $model,
        object $target,
        ReflectionMethod $method,
        string $attributeClass,
        string $handlerType
    ): void {
        foreach ($method->getAttributes($attributeClass) as $attribute) {
            $instance = $attribute->newInstance();
            $model->registerMethodHandler(
                $instance->field_name,
                $handlerType,
                $this->createMethodCallable($target, $method)
            );
        }
    }

    private function createMethodCallable(object $target, ReflectionMethod $method): callable
    {
        return static function (...$args) use ($target, $method): mixed {
            return $method->invokeArgs($target, $args);
        };
    }
}
