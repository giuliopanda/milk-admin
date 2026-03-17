<?php
namespace App\Abstracts\Services\AbstractModule;

use App\{API, Cli, Hooks};

!defined('MILK_DIR') && die();

class ModuleExtensionAttributeScannerService
{
    /**
     * Scan hook extensions and register methods annotated with #[HookCallback].
     *
     * @param array<int, object> $extensions
     */
    public static function scanHookExtensions(array $extensions): void
    {
        foreach ($extensions as $extension) {
            $reflection = new \ReflectionClass($extension);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(\App\Attributes\HookCallback::class);
                foreach ($attributes as $attribute) {
                    $hook = $attribute->newInstance();
                    Hooks::set($hook->hook_name, [$extension, $method->getName()], $hook->order);
                }
            }
        }
    }

    /**
     * Scan API extensions and register methods annotated with #[ApiEndpoint].
     * Documentation metadata is also registered when #[ApiDoc] is present.
     *
     * @param array<int, object> $extensions
     */
    public static function scanApiExtensions(array $extensions): void
    {
        if (!defined('MILK_API_CONTEXT')) {
            return;
        }

        foreach ($extensions as $extension) {
            $reflection = new \ReflectionClass($extension);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(\App\Attributes\ApiEndpoint::class);

                foreach ($attributes as $attribute) {
                    $api = $attribute->newInstance();
                    $methodName = $method->getName();

                    $options = array_merge($api->options, [
                        'method' => $api->method ?? 'ANY',
                    ]);

                    API::set($api->url, [$extension, $methodName], $options);

                    $docAttributes = $method->getAttributes(\App\Attributes\ApiDoc::class);
                    if (!empty($docAttributes)) {
                        $apiDoc = $docAttributes[0]->newInstance();
                        API::setDocumentation($api->url, $apiDoc->toArray());
                    }
                }
            }
        }
    }

    /**
     * Scan shell extensions and register methods annotated with #[Shell].
     *
     * @param array<int, object> $extensions
     */
    public static function scanShellExtensions(array $extensions, ?string $page): void
    {
        foreach ($extensions as $extension) {
            $reflection = new \ReflectionClass($extension);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(\App\Attributes\Shell::class);

                foreach ($attributes as $attribute) {
                    $shell = $attribute->newInstance();
                    $methodName = $method->getName();

                    if (isset($shell->system) && $shell->system === true) {
                        Cli::set($shell->command, [$extension, $methodName]);
                        continue;
                    }

                    if ($page) {
                        Cli::set($page . ':' . $shell->command, [$extension, $methodName]);
                    }
                }
            }
        }
    }
}
