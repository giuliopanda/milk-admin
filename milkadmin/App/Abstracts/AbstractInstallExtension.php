<?php
namespace App\Abstracts;

!defined('MILK_DIR') && die();

/**
 * Abstract Install Extension
 *
 * Base class for Install extensions that provides lifecycle hooks.
 * Extensions can extend installation, update, and uninstallation processes.
 *
 * Example usage:
 * ```php
 * class MyExtension extends AbstractInstallExtension
 * {
 *     public function installExecute(array $data = []): array
 *     {
 *         // Perform additional installation tasks
 *         return $data;
 *     }
 *
 *     public function installUpdate(string $html = ''): string
 *     {
 *         // Perform additional update tasks
 *         return $html;
 *     }
 *
 *     public function shellUninstallModule(): void
 *     {
 *         // Cleanup before uninstallation
 *     }
 * }
 * ```
 *
 * @package App\Abstracts
 */
abstract class AbstractInstallExtension
{
    /**
     * The Module instance being extended
     * @var AbstractModule
     */
    protected \WeakReference $module;

    /**
     * Constructor
     *
     * @param AbstractModule $module The Module being extended
     */
    public function __construct(AbstractModule $module)
    {
        $this->module = \WeakReference::create($module);
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
     * Hook called after Install initialization
     * Can be overridden by child classes
     *
     * @return void
     */
    public function onInit(): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called after setHandleInstall
     * Can be overridden by child classes
     *
     * @return void
     */
    public function onSetup(): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Hook called after installGetHtmlModules
     * Can be overridden by child classes
     *
     * @param string $html Current HTML
     * @param array $errors Current errors
     * @return string Modified HTML
     */
    public function onInstallGetHtmlModules(string $html, array $errors): string
    {
        return $html;
    }

    /**
     * Hook called after installExecuteConfig
     * Can be overridden by child classes
     *
     * @param array $data Configuration data
     * @return array Modified data
     */
    public function onInstallExecuteConfig(array $data): array
    {
        return $data;
    }

    /**
     * Hook called after installCheckData
     * Can be overridden by child classes
     *
     * @param array $errors Current errors
     * @param array $data Form data
     * @return array Modified errors
     */
    public function onInstallCheckData(array $errors, array $data): array
    {
        return $errors;
    }

    /**
     * Hook called after installDone
     * Can be overridden by child classes
     *
     * @param string $html Completion HTML
     * @return string Modified HTML
     */
    public function onInstallDone(string $html): string
    {
        return $html;
    }

    /**
     * Execute module installation
     * Can be overridden by child classes to perform additional installation tasks
     *
     * @param array $data Installation data
     * @return array Modified data
     */
    public function installExecute(array $data = []): array
    {
        return $data;
    }

    /**
     * Execute module update
     * Can be overridden by child classes to perform additional update tasks
     *
     * @param string $html Update HTML
     * @return string Modified HTML
     */
    public function installUpdate(string $html = ''): string
    {
        return $html;
    }

    /**
     * Execute module uninstallation
     * Can be overridden by child classes to perform cleanup before uninstallation
     *
     * @return void
     */
    public function shellUninstallModule(): void
    {
        // Default implementation - can be overridden
    }
}
