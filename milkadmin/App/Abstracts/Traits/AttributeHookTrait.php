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
        $reflection = new ReflectionClass($this);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(HookCallback::class);

            foreach ($attributes as $attribute) {
                $hook = $attribute->newInstance();
                $methodName = $method->getName();

                // Register the method as a hook callback
                Hooks::set(
                    $hook->hook_name,
                    [$this, $methodName],
                    $hook->order
                );

                // Store in internal map for reference
                if (!isset($this->hookMap[$hook->hook_name])) {
                    $this->hookMap[$hook->hook_name] = [];
                }
                $this->hookMap[$hook->hook_name][] = [
                    'method' => $methodName,
                    'order' => $hook->order
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
