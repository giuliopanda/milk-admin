<?php
namespace Theme;

!defined('MILK_DIR') && die(); // Avoid direct access

class AssetsExtensionsManifest
{
    private const CSS_BEFORE_THEME = [
        'AssetsExtensions/Bootstrap/Css/bootstrap.min.css',
    ];

    private const CSS_AFTER_THEME = [
        'AssetsExtensions/Bootstrap/Icons/Font/bootstrap-icons.min.css',
        'AssetsExtensions/TrixEditor/trix.css',
    ];

    private const JS_BEFORE_THEME = [
        'AssetsExtensions/Bootstrap/Js/bootstrap.bundle.min.js',
        'AssetsExtensions/chart.js',
        'AssetsExtensions/chartjs-plugin-datalabels.min.js',
        'AssetsExtensions/TrixEditor/trix.min.js',
    ];

    public static function getCssBeforeThemeUrls(): array
    {
        return self::toUrls(self::CSS_BEFORE_THEME);
    }

    public static function getCssAfterThemeUrls(): array
    {
        return self::toUrls(self::CSS_AFTER_THEME);
    }

    public static function getJsBeforeThemeUrls(): array
    {
        return self::toUrls(self::JS_BEFORE_THEME);
    }

    public static function getCssBeforeThemePaths(): array
    {
        return self::toPaths(self::CSS_BEFORE_THEME);
    }

    public static function getCssAfterThemePaths(): array
    {
        return self::toPaths(self::CSS_AFTER_THEME);
    }

    public static function getJsBeforeThemePaths(): array
    {
        return self::toPaths(self::JS_BEFORE_THEME);
    }

    private static function toUrls(array $relativeFiles): array
    {
        $urls = [];
        foreach ($relativeFiles as $relativeFile) {
            $path = self::toThemePath($relativeFile);
            if (is_file($path)) {
                $urls[] = THEME_URL . '/' . ltrim(str_replace('\\', '/', $relativeFile), '/');
            }
        }
        return $urls;
    }

    private static function toPaths(array $relativeFiles): array
    {
        $paths = [];
        foreach ($relativeFiles as $relativeFile) {
            $path = self::toThemePath($relativeFile);
            if (is_file($path)) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    private static function toThemePath(string $relativeFile): string
    {
        return THEME_DIR . '/' . ltrim(str_replace('\\', '/', $relativeFile), '/');
    }
}
