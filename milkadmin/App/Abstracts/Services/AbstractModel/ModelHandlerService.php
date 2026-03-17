<?php
namespace App\Abstracts\Services\AbstractModel;

!defined('MILK_DIR') && die();

class ModelHandlerService
{
    public function register(array $methodHandlers, string $fieldName, string $type, string|callable $methodName): array
    {
        if (!isset($methodHandlers[$fieldName])) {
            $methodHandlers[$fieldName] = [];
        }

        $methodHandlers[$fieldName][$type] = is_callable($methodName)
            ? $methodName
            : $methodName;

        return $methodHandlers;
    }

    public function remove(array $methodHandlers, string $fieldName, ?string $type = null): array
    {
        if ($type === null) {
            unset($methodHandlers[$fieldName]);
            return $methodHandlers;
        }

        unset($methodHandlers[$fieldName][$type]);
        return $methodHandlers;
    }

    public function get(array $methodHandlers, string $fieldName, string $type): ?callable
    {
        $handler = $methodHandlers[$fieldName][$type] ?? null;
        if ($handler === null) {
            return null;
        }

        if (is_callable($handler)) {
            return $handler;
        }

        return null;
    }

    public function has(array $methodHandlers, string $fieldName, string $type): bool
    {
        return isset($methodHandlers[$fieldName][$type]);
    }

    public function getFieldsWithHandlers(array $methodHandlers, string $type): array
    {
        $fields = [];

        foreach ($methodHandlers as $fieldName => $handlers) {
            if (isset($handlers[$type])) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }

    public function getRelationshipHandlers(array $methodHandlers, string $alias, string $type): array
    {
        $handlers = [];
        $prefix = $alias . '.';
        $prefixLength = strlen($prefix);

        foreach ($methodHandlers as $fieldName => $fieldHandlers) {
            if (!str_starts_with($fieldName, $prefix) || !isset($fieldHandlers[$type])) {
                continue;
            }

            $actualField = substr($fieldName, $prefixLength);
            $handlers[$actualField] = $fieldHandlers[$type];
        }

        return $handlers;
    }
}
