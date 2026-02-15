<?php
namespace App;

use App\{Logs};

!defined('MILK_DIR') && die();

/**
 * Extension Loader Class
 *
 * Manages loading and initialization of extensions for Abstract classes
 * Extensions are located in Extensions/ folder with structure:
 * Extensions/ExtensionName/Model.php, Controller.php, Module.php, etc.
 *
 * @package App
 */
class ExtensionLoader
{
    /**
     * Cache of loaded extension file paths and class names
     * Structure: [file_path => class_name]
     * @var array
     */
    private static array $loaded_files = [];


    private static array $prevent_recursion = [];
    /**
     * Load extensions for a specific type (Model, Controller, Module, etc.)
     *
     * @param array $extension_names Associative array of extension names and their parameters
     *                               Format: ['ExtName' => ['param1' => 'value1'], ...]
     * @param string $type Type of extension (Model, Controller, Module, etc.)
     * @param object $target The object that is loading the extension
     * @return array Array of loaded extension instances
     * @throws \Exception If extension file is not found
     */
    public static function load(array $extension_names, string $type, object $target): array
    {
        $loaded = [];

        foreach ($extension_names as $extension_name => $params) {
            // Support both old format (numeric keys) and new format (associative)
            if (is_int($extension_name)) {
                $extension_name = $params;
                $params = [];
            }

            // Support for inline class definitions (for testing)
            // If the extension_name is a fully qualified class name, instantiate it directly
            if (class_exists($extension_name)) {
                $instance = new $extension_name($target);
                if (method_exists($instance, 'applyParameters') && !empty($params)) {
                    $instance->applyParameters($params);
                }
                $loaded[$extension_name] = $instance;
            } else {
                // Load from file system
                $instance = self::loadExtension($extension_name, $type, $target, $params);
                if ($instance !== null) {
                    $loaded[$extension_name] = $instance;
                }
            }
        }

        return $loaded;
    }

    /**
     * Load a single extension
     *
     * Searches for extension files in this order:
     * 1. Local module directory: {module_dir}/Extensions/{extension_name}/{type}.php
     * 2. Global directory: milkadmin/Extensions/{extension_name}/{type}.php
     *
     * If local Extensions folder exists, ONLY local is used (no fallback to global).
     *
     * @param string $extension_name Name of the extension
     * @param string $type Type of extension (Model, Controller, Module, etc.)
     * @param object $target The object that is loading the extension
     * @param array $params Parameters to pass to the extension
     * @return object|null Extension instance or null if not found
     * @throws \Exception If extension file is not found
     */
    private static function loadExtension(string $extension_name, string $type, object $target, array $params = []): ?object
    {
        // Safety-first policy:
        // always create a fresh extension instance bound to the current target.
        // This avoids stale-state leaks between different target instances.

        // Determine the directory of the calling module/model
        $target_reflection = new \ReflectionClass($target);
        $target_dir = dirname($target_reflection->getFileName());

        // Build possible extension file paths
        $local_extension_dir = $target_dir . '/Extensions/' . $extension_name;
        $local_extension_file = $local_extension_dir . '/' . $type . '.php';

        $global_extension_dir = MILK_DIR . '/Extensions/' . $extension_name;
        $global_extension_file = $global_extension_dir . '/' . $type . '.php';

        // Determine which file to load
        // If local Extensions/{extension_name} directory exists, use ONLY local (no fallback)
        $file_to_load = null;
        $is_local = false;

        if (is_dir($local_extension_dir)) {
            // Local extension directory exists - use only local
            $file_to_load = $local_extension_file;
            $is_local = true;
        } else {
            // No local directory - use global
            $file_to_load = $global_extension_file;
        }

        // Check if file exists
        if (!file_exists($file_to_load)) {
            // Only the requested type is required (Model when loading from Model, Module when loading from Module)
            // All other types (Hook, Api, Shell, Install, Controller) are optional
            $required_types = ['Model', 'Module'];

            if (in_array($type, $required_types)) {
                // This is a required file - throw exception
                $location = $is_local ? 'local' : 'global';
                $error_msg = "Extension '{$extension_name}' type '{$type}' not found in {$location} directory. Expected file: {$file_to_load}";
                Logs::set('SYSTEM',  $error_msg, 'ERROR');
                throw new \Exception($error_msg);
            }

            // Optional extension file - return null
            return null;
        }

        // Load the file and get the class name
        $extension_class = self::loadExtensionFile($file_to_load, $type);

        if ($extension_class === null) {
            $error_msg = "No valid class found in extension file: {$file_to_load}";
            Logs::set('SYSTEM', $error_msg, 'ERROR');
            throw new \Exception($error_msg);
        }

        // Instantiate extension
        $instance = new $extension_class($target);

        // Apply parameters to the extension instance
        if (method_exists($instance, 'applyParameters')) {
            $instance->applyParameters($params);
        }

        return $instance;
    }

    /**
     * Load an extension file and return the class name
     * Handles already-loaded files via cache
     *
     * @param string $file_path Path to the extension file
     * @param string $type Type of extension (Model, Controller, Module, etc.)
     * @return string|null The class name or null if not found
     */
    private static function loadExtensionFile(string $file_path, string $type): ?string
    {
        $real_path = realpath($file_path) ?: $file_path;

        // Check if file was already loaded
        if (isset(self::$loaded_files[$file_path])) {
            return self::$loaded_files[$file_path];
        }
        if (isset(self::$loaded_files[$real_path])) {
            $cached_class = self::$loaded_files[$real_path];
            self::$loaded_files[$file_path] = $cached_class;
            return $cached_class;
        }

        // Capture declared classes before loading
        $classes_before = get_declared_classes();

        // Load the file
        require_once $file_path;

        // Find newly declared classes
        $classes_after = get_declared_classes();
        $new_classes = array_diff($classes_after, $classes_before);

        if (empty($new_classes)) {
            // File may have been loaded previously through another path or earlier bootstrap.
            $resolved = self::resolveDeclaredClassForFile($real_path, $type);
            if ($resolved !== null) {
                self::$loaded_files[$file_path] = $resolved;
                self::$loaded_files[$real_path] = $resolved;
                return $resolved;
            }
            return null;
        }

        // Find the concrete (non-abstract) extension class
        $extension_class = null;
        $expected_base_class = "App\\Abstracts\\Abstract{$type}Extension";

        foreach ($new_classes as $class_name) {
            $reflection = new \ReflectionClass($class_name);

            // Skip abstract classes
            if ($reflection->isAbstract()) {
                continue;
            }

            // Check if it extends the expected base class or is a valid extension class
            if (is_subclass_of($class_name, $expected_base_class)) {
                $extension_class = $class_name;
                break;
            }
        }

        // If no class found matching the base class, take the first non-abstract class
        if ($extension_class === null) {
            foreach ($new_classes as $class_name) {
                $reflection = new \ReflectionClass($class_name);
                if (!$reflection->isAbstract()) {
                    $extension_class = $class_name;
                    break;
                }
            }
        }

        if ($extension_class === null) {
            return null;
        }

        // Cache the mapping
        self::$loaded_files[$file_path] = $extension_class;
        self::$loaded_files[$real_path] = $extension_class;

        return $extension_class;
    }

    /**
     * Resolve an already-declared extension class that comes from $real_path.
     */
    private static function resolveDeclaredClassForFile(string $real_path, string $type): ?string
    {
        $expected_base_class = "App\\Abstracts\\Abstract{$type}Extension";

        foreach (get_declared_classes() as $class_name) {
            $reflection = new \ReflectionClass($class_name);
            if ($reflection->isAbstract()) {
                continue;
            }
            $class_file = $reflection->getFileName();
            if (!is_string($class_file) || $class_file === '') {
                continue;
            }
            if ((realpath($class_file) ?: $class_file) !== $real_path) {
                continue;
            }

            if (is_subclass_of($class_name, $expected_base_class)) {
                return $class_name;
            }
        }

        foreach (get_declared_classes() as $class_name) {
            $reflection = new \ReflectionClass($class_name);
            if ($reflection->isAbstract()) {
                continue;
            }
            $class_file = $reflection->getFileName();
            if (!is_string($class_file) || $class_file === '') {
                continue;
            }
            if ((realpath($class_file) ?: $class_file) === $real_path) {
                return $class_name;
            }
        }

        return null;
    }

    /**
     * Call a hook method on all loaded extensions
     *
     * @param array $extensions Array of extension instances
     * @param string $hook_name Name of the hook method to call
     * @param array $params Parameters to pass to the hook method
     * @return void
     */
    public static function callHook(array $extensions, string $hook_name, array $params = []): void
    {
        foreach ($extensions as $extension_name => $extension) {
            if (!method_exists($extension, $hook_name)) {
                continue;
            }

            try {
                $extension->$hook_name(...$params);
            } catch (\Exception $e) {
                Logs::set('SYSTEM', "Extension '{$extension_name}' hook '{$hook_name}' failed: " . $e->getMessage(), 'ERROR');
                throw $e;
            }
        }
    }


    public static function callReturnHook(array $extensions, string $hook_name, array $params = []): mixed
    {
        if (empty($params) || count($params) == 0) {
            $first_param = null;
            $params  = [];
        } else {
            $first_param = array_shift($params);
        }

        foreach ($extensions as $extension_name => $extension) {
            if (!method_exists($extension, $hook_name)) {
                continue;
            }

            try {
                $first_param = $extension->$hook_name($first_param, ...$params);
            } catch (\Exception $e) {
                Logs::set('SYSTEM',  "Extension '{$extension_name}' hook '{$hook_name}' failed: " . $e->getMessage(), 'ERROR');
                throw $e;
            }
        }
        return $first_param;
    }


    public static function preventRecursion($ext_name): bool{
        if (isset(self::$prevent_recursion[$ext_name]) && self::$prevent_recursion[$ext_name] > 0) {
            self::$prevent_recursion[$ext_name]++;
            return false;
        }
        self::$prevent_recursion[$ext_name] = 1;
        return true;
    }

    public static function freeRecursion($ext_name): void
    {
        if (isset(self::$prevent_recursion[$ext_name]) && self::$prevent_recursion[$ext_name] > 0) {
            self::$prevent_recursion[$ext_name]--;
        }
        if (self::$prevent_recursion[$ext_name] <= 0) {
            unset(self::$prevent_recursion[$ext_name]);
        }
    }


    /**
     * Clear extension cache (useful for testing)
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$loaded_files = [];
    }
}
