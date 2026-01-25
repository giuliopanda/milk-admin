<?php
namespace App;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Version helper for semantic and legacy versions.
 */
class Version
{
    public const DEFAULT = '1.0.0';

    /**
     * Normalize version strings.
     * - Legacy numeric versions become "0.<value>"
     * - Semver-like values are normalized to 3 parts (1.2 -> 1.2.0)
     */
    public static function normalize($version): ?string
    {
        if (self::isEmpty($version)) {
            return null;
        }

        $version = trim((string)$version);

        if (preg_match('/^v(.+)$/i', $version, $matches)) {
            $version = $matches[1];
        }

        // Legacy version format: only 0.X (two parts)
        if (preg_match('/^0\.(\d+)$/', $version, $matches)) {
            return '0.' . $matches[1];
        }

        if (preg_match('/^\d+$/', $version)) {
            return '0.' . $version;
        }

        if (!preg_match('/^\d+(?:\.\d+)+$/', $version)) {
            return $version;
        }

        $parts = array_values(array_filter(explode('.', $version), 'strlen'));
        $clean_parts = [];
        foreach ($parts as $part) {
            $clean_parts[] = (string)((int)$part);
        }

        if (count($clean_parts) === 1) {
            return '0.' . $clean_parts[0];
        }
        if (count($clean_parts) === 2) {
            $clean_parts[] = '0';
        } elseif (count($clean_parts) > 3) {
            $clean_parts = array_slice($clean_parts, 0, 3);
        }

        return implode('.', $clean_parts);
    }

    /**
     * Compare two versions.
     *
     * @return int -1 if $a < $b, 0 if equal, 1 if $a > $b
     */
    public static function compare($a, $b): int
    {
        [$a_major, $a_minor, $a_patch] = self::toParts($a);
        [$b_major, $b_minor, $b_patch] = self::toParts($b);

        if ($a_major !== $b_major) {
            return $a_major <=> $b_major;
        }
        if ($a_minor !== $b_minor) {
            return $a_minor <=> $b_minor;
        }
        return $a_patch <=> $b_patch;
    }

    public static function isLegacy($version): bool
    {
        $normalized = self::normalize($version);
        if ($normalized === null) {
            return false;
        }

        return preg_match('/^0\.\d+$/', $normalized) === 1;
    }

    public static function isEmpty($version): bool
    {
        if ($version === null) {
            return true;
        }
        if (is_int($version) && $version === 0) {
            return true;
        }

        $version = trim((string)$version);
        if ($version === '') {
            return true;
        }

        return $version === '0';
    }

    public static function toSemver($version): string
    {
        $normalized = self::normalize($version);
        if ($normalized === null) {
            return self::DEFAULT;
        }

        $parts = self::toParts($normalized);
        return implode('.', $parts);
    }

    public static function bump($version, string $part = 'patch'): string
    {
        [$major, $minor, $patch] = self::toParts($version);

        switch ($part) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        return $major . '.' . $minor . '.' . $patch;
    }

    public static function next($version, string $part = 'patch'): string
    {
        if (self::isEmpty($version) || self::isLegacy($version)) {
            return self::DEFAULT;
        }

        return self::bump($version, $part);
    }

    private static function toParts($version): array
    {
        $normalized = self::normalize($version);
        if ($normalized === null) {
            return [0, 0, 0];
        }

        $parts = explode('.', $normalized);
        $major = (int)($parts[0] ?? 0);
        $minor = (int)($parts[1] ?? 0);
        $patch = (int)($parts[2] ?? 0);

        return [$major, $minor, $patch];
    }
}
