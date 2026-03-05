<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

class DraftFieldNormalizer
{
    /**
     * @param array<int,mixed> $rawFields
     * @return array{fields:array<int,array{name:string,builder_locked:bool,can_delete:bool,config:array<string,mixed>}>,error:string}
     */
    public static function normalize(array $rawFields): array
    {
        $fields = [];
        $seen = [];

        foreach ($rawFields as $index => $rawField) {
            if (!is_array($rawField)) {
                continue;
            }

            $config = is_array($rawField['config'] ?? null) ? $rawField['config'] : [];
            $name = trim((string) ($config['field_name'] ?? ($rawField['name'] ?? '')));
            if ($name === '') {
                return [
                    'fields' => [],
                    'error' => 'Invalid field at position ' . ($index + 1) . ': missing field name.',
                ];
            }

            $name = self::normalizeReservedFieldName($name, $seen);
            if (!DraftFieldUtils::isValidFieldName($name)) {
                return [
                    'fields' => [],
                    'error' => "Invalid field name '{$name}'. Use letters, numbers, underscore, start with a letter, max 32 chars.",
                ];
            }

            $nameLower = strtolower($name);
            if (isset($seen[$nameLower])) {
                return [
                    'fields' => [],
                    'error' => "Duplicate field name '{$name}'.",
                ];
            }
            $seen[$nameLower] = true;

            $config['field_name'] = $name;
            if (!array_key_exists('field_label', $config) || trim((string) ($config['field_label'] ?? '')) === '') {
                $config['field_label'] = DraftFieldUtils::toTitle($name);
            }
            if (!array_key_exists('type', $config) || trim((string) ($config['type'] ?? '')) === '') {
                $config['type'] = 'string';
            }

            $fields[] = [
                'name' => $name,
                'builder_locked' => DraftFieldUtils::normalizeBool($rawField['builder_locked'] ?? ($rawField['builderLocked'] ?? false)),
                'can_delete' => array_key_exists('can_delete', $rawField)
                    ? DraftFieldUtils::normalizeBool($rawField['can_delete'])
                    : true,
                'config' => $config,
            ];
        }

        return [
            'fields' => $fields,
            'error' => '',
        ];
    }

    /**
     * @param array<string,bool> $seen
     */
    private static function normalizeReservedFieldName(string $name, array $seen): string
    {
        $trimmed = trim($name);
        if (strcasecmp($trimmed, 'user') !== 0) {
            return $trimmed;
        }

        $base = 'user_field';
        $candidate = $base;
        $suffix = 2;

        while (isset($seen[strtolower($candidate)])) {
            $suffixToken = '_' . $suffix;
            $maxBaseLength = 32 - strlen($suffixToken);
            $candidateBase = $maxBaseLength > 0
                ? substr($base, 0, $maxBaseLength)
                : 'field';
            $candidate = $candidateBase . $suffixToken;
            $suffix++;
        }

        return $candidate;
    }
}
