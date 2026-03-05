<?php
namespace App\Abstracts\Services\AbstractModule;

use App\Abstracts\AbstractModule;

!defined('MILK_DIR') && die();

class ModuleModelMetadataService
{
    /**
     * Build model metadata for all module instances indexed by module page.
     *
     * @param array<string, mixed> $instances
     * @return array<string, array{class:string, namespace:string, shortName:string, instance:object|null}>
     */
    public static function getAllModels(array $instances): array
    {
        $models = [];

        foreach ($instances as $page => $module) {
            if (!$module instanceof AbstractModule) {
                continue;
            }

            $modelMeta = self::resolveModelMetaForModule($module);
            if ($modelMeta === null) {
                continue;
            }

            $models[$page] = $modelMeta;
        }

        return $models;
    }

    /**
     * Resolve model metadata for a single module without throwing exceptions.
     *
     * @return array{class:string, namespace:string, shortName:string, instance:object|null}|null
     */
    public static function resolveModelMetaForModule(AbstractModule $module): ?array
    {
        $instance = null;
        $className = '';
        $model = $module->getModel();

        if (is_object($model)) {
            $instance = $model;
            $className = get_class($model);
        } elseif (is_scalar($model)) {
            $className = self::resolveModelClassName((string) $model, self::resolveModuleNamespace($module));
        }

        if ($className === '') {
            $className = self::resolveConventionalModelClassName($module);
        }

        if ($className === '') {
            return null;
        }

        if (!is_object($instance)) {
            $instance = self::instantiateModelSafely($className);
        }

        try {
            $reflection = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            return null;
        }

        return [
            'class' => $reflection->getName(),
            'namespace' => $reflection->getNamespaceName(),
            'shortName' => $reflection->getShortName(),
            'instance' => $instance,
        ];
    }

    /**
     * Resolve a configured model string to a fully qualified class name.
     * Supports both FQCN and short class names in the module namespace.
     */
    private static function resolveModelClassName(string $model, string $moduleNamespace): string
    {
        $model = trim($model);
        if ($model === '') {
            return '';
        }

        $candidate = ltrim($model, '\\');
        if (class_exists($candidate)) {
            return $candidate;
        }

        if (strpos($candidate, '\\') === false) {
            $moduleNamespaceCandidate = $moduleNamespace . '\\' . $candidate;
            if (class_exists($moduleNamespaceCandidate)) {
                return $moduleNamespaceCandidate;
            }
        }

        return '';
    }

    /**
     * Resolve default model class by naming convention: <ModuleName>Model.
     */
    private static function resolveConventionalModelClassName(object $module): string
    {
        try {
            $moduleReflection = new \ReflectionClass($module);
        } catch (\ReflectionException $e) {
            return '';
        }

        $moduleShortName = $moduleReflection->getShortName();
        $moduleBaseName = str_replace('Module', '', $moduleShortName);
        if ($moduleBaseName === '' || $moduleBaseName === $moduleShortName) {
            return '';
        }

        $candidate = $moduleReflection->getNamespaceName() . '\\' . $moduleBaseName . 'Model';

        return class_exists($candidate) ? $candidate : '';
    }

    /**
     * Safely instantiate a model only when constructor requirements allow it.
     */
    private static function instantiateModelSafely(string $className): ?object
    {
        if ($className === '' || !class_exists($className)) {
            return null;
        }

        try {
            $reflection = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            return null;
        }

        if (!$reflection->isInstantiable()) {
            return null;
        }

        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        try {
            return $reflection->newInstance();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the namespace for a module object.
     */
    private static function resolveModuleNamespace(object $module): string
    {
        try {
            return (new \ReflectionClass($module))->getNamespaceName();
        } catch (\ReflectionException $e) {
            return '';
        }
    }
}
