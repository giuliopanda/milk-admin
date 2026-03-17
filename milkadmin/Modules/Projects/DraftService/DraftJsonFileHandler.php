<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

class DraftJsonFileHandler
{
    /**
     * @return array<string,mixed>|null
     */
    public static function read(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function write(string $path, array $data): bool
    {
        try {
            $json = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $e) {
            return false;
        }

        return file_put_contents($path, $json . "\n") !== false;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function prettyJson(array $data): string
    {
        try {
            $json = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            return is_string($json) ? $json : '{}';
        } catch (\Throwable $e) {
            return '{}';
        }
    }
}
