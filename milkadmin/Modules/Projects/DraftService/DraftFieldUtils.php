<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

class DraftFieldUtils
{
    public static function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    public static function toTitle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);
        $value = str_replace(['_', '-'], ' ', (string) $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);

        return ucwords(strtolower(trim((string) $value)));
    }

    public static function toStudlyCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/[^A-Za-z0-9 ]+/', ' ', (string) $value);
        $value = ucwords(strtolower(trim((string) $value)));
        return str_replace(' ', '', $value);
    }

    public static function isValidFieldName(string $name): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]{0,31}$/', trim($name)) === 1;
    }

    public static function isFieldBuilderLocked(array $fieldDef): bool
    {
        return self::normalizeBool($fieldDef['builderLocked'] ?? ($fieldDef['builder_locked'] ?? false));
    }
}
