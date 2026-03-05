<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ContainerNormalizer
{
    public static function isValidContainerId(string $id): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim($id)) === 1;
    }

    /**
     * @return int|array<int,int>
     */
    public static function normalizeContainerCols(mixed $value, int $fieldCount): int|array
    {
        $fieldCount = $fieldCount > 0 ? $fieldCount : 1;

        if (is_array($value)) {
            $cols = [];
            foreach ($value as $colRaw) {
                if (!is_numeric($colRaw)) {
                    continue;
                }
                $col = (int) $colRaw;
                if ($col < 1) {
                    continue;
                }
                $cols[] = min(12, $col);
            }
            if (!empty($cols)) {
                return $cols;
            }
        }

        $cols = is_numeric($value) ? (int) $value : $fieldCount;
        if ($cols < 1) {
            $cols = $fieldCount;
        }

        return min(12, $cols);
    }

    /**
     * @return array<string,mixed>
     */
    public static function normalizeContainerAttributes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $attributes = [];
        foreach ($value as $key => $attributeValue) {
            $name = trim((string) $key);
            if ($name === '') {
                continue;
            }
            if (
                is_string($attributeValue)
                || is_int($attributeValue)
                || is_float($attributeValue)
                || is_bool($attributeValue)
            ) {
                $attributes[$name] = $attributeValue;
            }
        }

        return $attributes;
    }
}
