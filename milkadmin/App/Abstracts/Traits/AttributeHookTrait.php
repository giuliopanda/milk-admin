<?php
namespace App\Abstracts\Traits;

use App\Attributes\HookCallback;
use App\Hooks;
use ReflectionClass;
use ReflectionMethod;

!defined('MILK_DIR') && die();

trait AttributeHookTrait {

    private array $hookMap = [];
    private bool $hooksRegistered = false;

    public function registerHooks(): void {
        if ($this->hooksRegistered) {
            return;
        }

        $this->buildHookMap();
        $this->hooksRegistered = true;

    }

    private function buildHookMap(): void {
        // Scan main Hook class methods
        $this->scanHookAttributesFromClass($this);
    }

    /**
     * Scan methods from a class/object for HookCallback attributes
     *
     * @param object $object The object to scan
     * @return void
     */
    private function scanHookAttributesFromClass(object $object): void {
        $reflection = new ReflectionClass($object);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(HookCallback::class);

            foreach ($attributes as $attribute) {
                $hook = $attribute->newInstance();
                $methodName = $method->getName();

                // Register the method as a hook callback
                Hooks::set(
                    $hook->hook_name,
                    [$object, $methodName],
                    $hook->order
                );

                // Store in internal map for reference
                if (!isset($this->hookMap[$hook->hook_name])) {
                    $this->hookMap[$hook->hook_name] = [];
                }
                $this->hookMap[$hook->hook_name][] = [
                    'method' => $methodName,
                    'order' => $hook->order,
                    'source' => get_class($object)
                ];
            }
        }
    }

    public function getRegisteredHooks(): array {
        if (!$this->hooksRegistered) {
            $this->registerHooks();
        }
        return $this->hookMap;
    }
}
