<?php
namespace App\Abstracts\Services\AbstractModule;

use App\{Get, Lang, Logs};

!defined('MILK_DIR') && die();

class ModuleReflectionService
{
    private static array $reflection_cache = [];

    /**
     * Extract module base name from class short name (<Name>Module => <Name>).
     */
    public static function getModuleName(object $module): string
    {
        $reflection = self::reflectionFromObject($module);
        return str_replace('Module', '', $reflection->getShortName());
    }

    /**
     * Return the physical directory where the module class is defined.
     */
    public static function getChildClassPath(string $className): string
    {
        $reflection = self::reflectionFromClassName($className);
        $filePath = $reflection->getFileName();
        return dirname($filePath);
    }

    /**
     * Build a sibling class name replacing the "Module" suffix and appending another suffix.
     */
    public static function getClassName(object $module, string $suffix = ''): ?string
    {
        $reflection = self::reflectionFromObject($module);
        $className = $reflection->getShortName();
        $newClassName = str_replace('Module', '', $className) . $suffix;

        return $newClassName !== $className ? $newClassName : null;
    }

    /**
     * Return namespace of a module class.
     */
    public static function getChildNamespace(string $className): string
    {
        $reflection = self::reflectionFromClassName($className);
        return $reflection->getNamespaceName();
    }

    /**
     * Initialize a class configured as string, first in module namespace then as FQCN.
     */
    public static function initializeClass(string $nameSpace, &$class): void
    {
        if (!is_scalar($class)) {
            return;
        }

        $className = $nameSpace . '\\' . $class;
        if (class_exists($className)) {
            $class = new $className();
            return;
        }

        if (class_exists($class)) {
            $class = new $class();
            return;
        }

        Logs::set('SYSTEM', 'Class not found: ' . $class, 'WARNING');
        $class = null;
    }

    /**
     * Resolve folder/module identifier used when tracking active modules in config.
     */
    public static function getFolderOrFileCalled(string $className): string
    {
        $reflection = self::reflectionFromClassName($className);
        $filePath = $reflection->getFileName();
        $directoryPath = dirname($filePath);

        $modulesPath = MILK_DIR . '/Modules';
        if ($directoryPath === $modulesPath || str_starts_with($directoryPath, $modulesPath . '/')) {
            // Module class file placed directly under Modules/.
            if ($directoryPath === $modulesPath) {
                return basename($filePath);
            }

            $relativePath = ltrim(substr($directoryPath, strlen($modulesPath)), '/');
            if ($relativePath === '') {
                return basename($filePath);
            }

            $pathParts = explode('/', $relativePath);
            return $pathParts[0];
        }

        if (basename($directoryPath) == 'Modules') {
            return basename($filePath);
        }

        return basename($directoryPath);
    }

    /**
     * Load localized language file for the module based on current user locale.
     */
    public static function loadLangForModule(object $module, ?string $page): void
    {
        $reflection = self::reflectionFromObject($module);
        $fileName = $reflection->getFileName();
        $langDir = dirname($fileName) . '/Lang/';
        if (!is_dir($langDir)) {
            return;
        }

        $lang = Get::userLocale();
        Lang::loadPhpFile($langDir . '/' . $lang . '.php', $page);
    }

    /**
     * Build conventional sibling class FQCN (<Namespace>\\<ModuleBase><Suffix>).
     */
    public static function getConventionalClassName(object $module, string $suffix): string
    {
        $reflection = self::reflectionFromObject($module);
        return $reflection->getNamespaceName() . '\\' . str_replace('Module', '', $reflection->getShortName()) . $suffix;
    }

    /**
     * Return ReflectionClass for a module object using the shared cache.
     */
    private static function reflectionFromObject(object $module): \ReflectionClass
    {
        return self::reflectionFromClassName(get_class($module));
    }

    /**
     * Return ReflectionClass for a class name using an internal cache.
     */
    private static function reflectionFromClassName(string $className): \ReflectionClass
    {
        if (isset(self::$reflection_cache[$className])) {
            return self::$reflection_cache[$className];
        }

        self::$reflection_cache[$className] = new \ReflectionClass($className);
        return self::$reflection_cache[$className];
    }
}
