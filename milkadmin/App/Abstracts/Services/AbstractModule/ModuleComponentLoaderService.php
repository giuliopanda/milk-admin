<?php
namespace App\Abstracts\Services\AbstractModule;

use App\ExtensionLoader;

!defined('MILK_DIR') && die();

class ModuleComponentLoaderService
{
    /**
     * Instantiate a conventional module component class (<ModuleName><Suffix>) when available.
     */
    public static function instantiateConventionalComponent(object $module, string $suffix): ?object
    {
        $className = ModuleReflectionService::getConventionalClassName($module, $suffix);
        if ($className === '' || !class_exists($className)) {
            return null;
        }

        return new $className();
    }

    /**
     * Load extension instances for the given layer (Module, Controller, Api, ...).
     *
     * @param array<string, mixed> $extensions
     * @return array<int, object>
     */
    public static function loadExtensions(array $extensions, string $type, object $module): array
    {
        return ExtensionLoader::load($extensions, $type, $module);
    }

    /**
     * Trigger optional onInit hook on each loaded extension.
     *
     * @param array<int, object> $extensions
     */
    public static function callOnInit(array $extensions): void
    {
        foreach ($extensions as $extension) {
            if (method_exists($extension, 'onInit')) {
                $extension->onInit();
            }
        }
    }

    /**
     * Trigger optional onSetup hook on each loaded extension.
     *
     * @param array<int, object> $extensions
     */
    public static function callOnSetup(array $extensions): void
    {
        foreach ($extensions as $extension) {
            if (method_exists($extension, 'onSetup')) {
                $extension->onSetup();
            }
        }
    }

    /**
     * Trigger optional onRegisterHooks hook after hook registration has completed.
     *
     * @param array<int, object> $extensions
     */
    public static function callOnRegisterHooks(array $extensions): void
    {
        foreach ($extensions as $extension) {
            if (method_exists($extension, 'onRegisterHooks')) {
                $extension->onRegisterHooks();
            }
        }
    }
}
