<?php
namespace Theme;

use App\Config;
use App\Settings;
use App\Theme as ThemeHooks;

!defined('MILK_DIR') && die(); // Avoid direct access

class AssetsBundle
{
    private const SETTINGS_GROUP = 'theme_assets_bundle';
    private const CSS_FILENAME = 'bundle-theme.css';
    private const JS_FILENAME = 'bundle-theme.js';
    private const STATIC_SYNC_EXTENSIONS = [
        'css', 'js', 'map',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'xml', 'tab', 'json', 'txt', 'md',
        'mp4', 'mp3', 'wav', 'avi', 'mov', 'wmv', 'flv', 'webm',
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2',
    ];
    private static bool $compileChecked = false;
    private static bool $compileResult = false;
    private static bool $developmentCleanupChecked = false;

    public static function isProduction(): bool
    {
        return strtolower((string) Config::get('environment', 'development')) === 'production';
    }

    public static function ensureCompiled(): bool
    {
        if (self::$compileChecked) {
            return self::$compileResult;
        }
        self::$compileChecked = true;

        $version = (string) Config::get('version', '0');
        self::ensurePublicThemeStaticFiles($version);

        $cssFiles = self::collectCssFiles();
        $jsFiles = self::collectJsFiles();

        $cssOutputPath = self::getOutputPath(self::CSS_FILENAME);
        $jsOutputPath = self::getOutputPath(self::JS_FILENAME);

        $storedVersion = (string) (Settings::get('version', self::SETTINGS_GROUP) ?? '');
        $storedCssFiles = Settings::get('css_files', self::SETTINGS_GROUP);
        $storedJsFiles = Settings::get('js_files', self::SETTINGS_GROUP);

        $mustRebuild = false;
        if (!is_file($cssOutputPath) || !is_file($jsOutputPath)) {
            $mustRebuild = true;
        }
        if ($storedVersion !== $version) {
            $mustRebuild = true;
        }
        if (!is_array($storedCssFiles) || !is_array($storedJsFiles)) {
            $mustRebuild = true;
        } else {
            if ($storedCssFiles !== $cssFiles || $storedJsFiles !== $jsFiles) {
                $mustRebuild = true;
            }
        }

        if (!$mustRebuild) {
            self::ensureManagedFilesListHasBundles();
            self::$compileResult = true;
            return self::$compileResult;
        }

        self::ensureOutputDirExists();
        self::writeBundle($cssOutputPath, $cssFiles, 'css');
        self::writeBundle($jsOutputPath, $jsFiles, 'js');

        $managedStaticFiles = Settings::get('managed_static_files', self::SETTINGS_GROUP);
        if (!is_array($managedStaticFiles)) {
            $managedStaticFiles = [];
        }
        $managedFiles = self::uniqueRelativePaths(array_merge(
            $managedStaticFiles,
            [
                'Assets/' . self::CSS_FILENAME,
                'Assets/' . self::JS_FILENAME,
            ]
        ));

        Settings::setMultiple([
            'version' => $version,
            'css_files' => $cssFiles,
            'js_files' => $jsFiles,
            'css_bundle' => self::CSS_FILENAME,
            'js_bundle' => self::JS_FILENAME,
            'managed_files' => $managedFiles,
            'updated_at' => date('c'),
        ], self::SETTINGS_GROUP);
        Settings::save(self::SETTINGS_GROUP, false);

        self::$compileResult = true;
        return self::$compileResult;
    }

    public static function cleanupIfDevelopment(): void
    {
        if (self::isProduction() || self::$developmentCleanupChecked) {
            return;
        }
        self::$developmentCleanupChecked = true;

        $settingsPath = self::getSettingsFilePath();
        if (!is_file($settingsPath)) {
            return;
        }

        $settingsData = [];
        $rawJson = @file_get_contents($settingsPath);
        if (is_string($rawJson) && $rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $settingsData = $decoded;
            }
        }

        $managedFiles = $settingsData['managed_files'] ?? [];
        if (is_array($managedFiles) && !empty($managedFiles)) {
            self::removeManagedFiles($managedFiles);
        } else {
            self::deletePathRecursively(self::getPublicThemeRootDir() . '/Assets');
            self::deletePathRecursively(self::getPublicThemeRootDir() . '/AssetsExtensions');
            self::deletePathRecursively(self::getPublicThemeRootDir() . '/Plugins');
        }

        @unlink($settingsPath);
    }

    private static function getSettingsFilePath(): string
    {
        return STORAGE_DIR . '/' . self::SETTINGS_GROUP . '.json';
    }

    private static function removeManagedFiles(array $managedFiles): void
    {
        $publicRoot = rtrim(self::normalizePath(self::getPublicThemeRootDir()), '/');
        $publicRootWithSlash = $publicRoot . '/';
        $dirsToPrune = [];

        foreach ($managedFiles as $relativePath) {
            if (!is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $cleanRelativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
            $absolutePath = self::normalizePath($publicRootWithSlash . $cleanRelativePath);

            if (!str_starts_with($absolutePath, $publicRootWithSlash)) {
                continue;
            }

            if (is_file($absolutePath)) {
                @unlink($absolutePath);
                $dirsToPrune[] = dirname($absolutePath);
                continue;
            }

            if (is_dir($absolutePath)) {
                self::deletePathRecursively($absolutePath);
                $dirsToPrune[] = dirname($absolutePath);
            }
        }

        $dirsToPrune[] = $publicRoot . '/Assets';
        $dirsToPrune[] = $publicRoot . '/AssetsExtensions';
        $dirsToPrune[] = $publicRoot . '/Plugins';
        self::pruneEmptyDirectories($dirsToPrune, $publicRoot);
    }

    private static function pruneEmptyDirectories(array $directories, string $publicRoot): void
    {
        $publicRoot = rtrim(self::normalizePath($publicRoot), '/');
        $uniqueDirs = self::uniquePaths(array_map(static fn ($dir): string => self::normalizePath((string) $dir), $directories));

        usort($uniqueDirs, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($uniqueDirs as $dir) {
            $dir = rtrim($dir, '/');
            while ($dir !== '' && str_starts_with($dir . '/', $publicRoot . '/')) {
                if (is_dir($dir)) {
                    @rmdir($dir);
                }
                if ($dir === $publicRoot) {
                    break;
                }
                $parent = dirname($dir);
                if ($parent === $dir) {
                    break;
                }
                $dir = $parent;
            }
        }
    }

    private static function deletePathRecursively(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }

    private static function ensureManagedFilesListHasBundles(): void
    {
        $managedFiles = Settings::get('managed_files', self::SETTINGS_GROUP);
        $managedStaticFiles = Settings::get('managed_static_files', self::SETTINGS_GROUP);
        if (!is_array($managedFiles)) {
            $managedFiles = [];
        }
        if (!is_array($managedStaticFiles)) {
            $managedStaticFiles = [];
        }

        $expectedManagedFiles = self::uniqueRelativePaths(array_merge(
            $managedStaticFiles,
            [
                'Assets/' . self::CSS_FILENAME,
                'Assets/' . self::JS_FILENAME,
            ]
        ));

        if ($expectedManagedFiles === $managedFiles) {
            return;
        }

        Settings::set('managed_files', $expectedManagedFiles, self::SETTINGS_GROUP);
        Settings::save(self::SETTINGS_GROUP, false);
    }

    public static function getCssBundleUrl(): string
    {
        return THEME_URL . '/Assets/' . self::CSS_FILENAME;
    }

    public static function getJsBundleUrl(): string
    {
        return THEME_URL . '/Assets/' . self::JS_FILENAME;
    }

    public static function isCssUrlBundled(string $url): bool
    {
        return self::isUrlBundled($url, 'css_files');
    }

    public static function isJsUrlBundled(string $url): bool
    {
        return self::isUrlBundled($url, 'js_files');
    }

    private static function collectCssFiles(): array
    {
        $files = AssetsExtensionsManifest::getCssBeforeThemePaths();

        $assetFiles = glob(THEME_DIR . '/Assets/*.css') ?: [];
        foreach ($assetFiles as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        $pluginFiles = glob(THEME_DIR . '/Plugins/*/*.css') ?: [];
        foreach ($pluginFiles as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        foreach (ThemeHooks::for('styles') as $url) {
            $path = self::resolveLocalPathFromUrl((string) $url);
            if ($path !== null && is_file($path)) {
                $files[] = $path;
            }
        }

        foreach (AssetsExtensionsManifest::getCssAfterThemePaths() as $path) {
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        return self::uniquePaths($files);
    }

    private static function collectJsFiles(): array
    {
        $files = AssetsExtensionsManifest::getJsBeforeThemePaths();

        $assetFiles = glob(THEME_DIR . '/Assets/*.js') ?: [];
        foreach ($assetFiles as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        $pluginFiles = glob(THEME_DIR . '/Plugins/*/*.js') ?: [];
        foreach ($pluginFiles as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        foreach (ThemeHooks::for('javascript') as $url) {
            $path = self::resolveLocalPathFromUrl((string) $url);
            if ($path !== null && is_file($path)) {
                $files[] = $path;
            }
        }

        return self::uniquePaths($files);
    }

    private static function isUrlBundled(string $url, string $settingsKey): bool
    {
        $path = self::resolveLocalPathFromUrl($url);
        if ($path === null) {
            return false;
        }

        $storedFiles = Settings::get($settingsKey, self::SETTINGS_GROUP);
        if (!is_array($storedFiles) || $storedFiles === []) {
            return false;
        }

        $normalizedPath = self::normalizePath($path);
        foreach ($storedFiles as $storedPath) {
            if (!is_string($storedPath)) {
                continue;
            }

            if (self::normalizePath($storedPath) === $normalizedPath) {
                return true;
            }
        }

        return false;
    }

    private static function resolveLocalPathFromUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $withoutQuery = (string) strtok($url, '?');

        if (str_starts_with($withoutQuery, THEME_URL . '/')) {
            $relative = substr($withoutQuery, strlen(THEME_URL . '/'));
            return THEME_DIR . '/' . ltrim((string) $relative, '/');
        }

        if (str_starts_with($withoutQuery, '/')) {
            $themePos = strpos($withoutQuery, '/Theme/');
            if ($themePos !== false) {
                $relative = substr($withoutQuery, $themePos + strlen('/Theme/'));
                return THEME_DIR . '/' . ltrim((string) $relative, '/');
            }
        }

        if (str_starts_with($withoutQuery, THEME_DIR . '/')) {
            return $withoutQuery;
        }

        return null;
    }

    private static function getOutputPath(string $filename): string
    {
        return self::getOutputDir() . '/' . $filename;
    }

    private static function getPublicThemeRootDir(): string
    {
        return dirname(MILK_DIR) . '/public_html/Theme';
    }

    private static function getOutputDir(): string
    {
        return self::getPublicThemeRootDir() . '/Assets';
    }

    private static function ensureOutputDirExists(): void
    {
        $outputDir = self::getOutputDir();
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
    }

    private static function ensurePublicThemeStaticFiles(string $version): void
    {
        $publicThemeRoot = self::getPublicThemeRootDir();
        $storedVersion = (string) (Settings::get('public_theme_sync_version', self::SETTINGS_GROUP) ?? '');
        $storedProbe = (string) (Settings::get('public_theme_sync_probe', self::SETTINGS_GROUP) ?? '');

        $isAlreadySynced = $storedVersion === $version
            && is_dir($publicThemeRoot)
            && $storedProbe !== ''
            && is_file($publicThemeRoot . '/' . ltrim($storedProbe, '/'));

        if ($isAlreadySynced) {
            return;
        }

        $themeFiles = self::collectThemeStaticFilesForSync();
        $themeRoot = rtrim(str_replace('\\', '/', THEME_DIR), '/');
        $probeRelative = '';
        $managedStaticFiles = [];

        foreach ($themeFiles as $sourcePath) {
            $normalizedSourcePath = str_replace('\\', '/', $sourcePath);
            if (!str_starts_with($normalizedSourcePath, $themeRoot . '/')) {
                continue;
            }

            $relativePath = ltrim(substr($normalizedSourcePath, strlen($themeRoot)), '/');
            if ($relativePath === '') {
                continue;
            }

            if ($probeRelative === '') {
                $probeRelative = $relativePath;
            }
            $managedStaticFiles[] = $relativePath;

            $targetPath = $publicThemeRoot . '/' . $relativePath;
            $targetDir = dirname($targetPath);

            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new \RuntimeException('Unable to create directory: ' . $targetDir);
            }

            $mustCopy = !is_file($targetPath)
                || filesize($targetPath) !== filesize($sourcePath)
                || filemtime($targetPath) < filemtime($sourcePath);

            if ($mustCopy && !copy($sourcePath, $targetPath)) {
                throw new \RuntimeException('Unable to copy file: ' . $sourcePath . ' -> ' . $targetPath);
            }
        }

        $managedStaticFiles = self::uniqueRelativePaths($managedStaticFiles);
        $existingManagedFiles = Settings::get('managed_files', self::SETTINGS_GROUP);
        if (!is_array($existingManagedFiles)) {
            $existingManagedFiles = [];
        }
        $managedFiles = self::uniqueRelativePaths(array_merge($existingManagedFiles, $managedStaticFiles));

        Settings::setMultiple([
            'public_theme_sync_version' => $version,
            'public_theme_sync_probe' => $probeRelative,
            'public_theme_sync_files_count' => count($themeFiles),
            'managed_static_files' => $managedStaticFiles,
            'managed_files' => $managedFiles,
            'public_theme_sync_at' => date('c'),
        ], self::SETTINGS_GROUP);
        Settings::save(self::SETTINGS_GROUP, false);
    }

    private static function collectThemeStaticFilesForSync(): array
    {
        $roots = [
            THEME_DIR . '/Assets',
            THEME_DIR . '/AssetsExtensions',
            THEME_DIR . '/Plugins',
        ];

        $files = [];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                $extension = strtolower((string) pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
                if ($extension === '' || !in_array($extension, self::STATIC_SYNC_EXTENSIONS, true)) {
                    continue;
                }

                $files[] = $fileInfo->getPathname();
            }
        }

        return self::uniquePaths($files);
    }

    private static function writeBundle(string $targetPath, array $files, string $type): void
    {
        $content = '';
        $chunks = [];

        foreach ($files as $filePath) {
            $fileContent = @file_get_contents($filePath);
            if ($fileContent === false) {
                continue;
            }

            if ($type === 'css') {
                $fileContent = self::rebaseCssUrls($fileContent, $filePath);
                $fileContent = self::minifyCssContent($fileContent);
            }

            if ($fileContent !== '') {
                $chunks[] = $fileContent;
            }
        }

        // Keep a single separator between concatenated files.
        $content = implode("\n", $chunks);
        $written = file_put_contents($targetPath, $content);
        if ($written === false) {
            throw new \RuntimeException('Unable to write bundle file: ' . $targetPath);
        }
    }

    private static function minifyCssContent(string $css): string
    {
        // Remove CSS comments.
        $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;

        // Collapse all whitespace to a single space.
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;

        // Remove spaces around common CSS separators.
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css) ?? $css;

        // Remove unnecessary trailing semicolons before closing brace.
        $css = str_replace(';}', '}', $css);

        return trim($css);
    }

    private static function rebaseCssUrls(string $css, string $sourcePath): string
    {
        $sourceDir = dirname($sourcePath);

        return preg_replace_callback(
            '/url\(\s*(["\']?)(.*?)\1\s*\)/i',
            static function (array $matches) use ($sourceDir): string {
                $quote = $matches[1] ?? '';
                $rawUrl = trim((string) ($matches[2] ?? ''));

                if ($rawUrl === '' || self::isAbsoluteCssUrl($rawUrl)) {
                    return 'url(' . $quote . $rawUrl . $quote . ')';
                }

                $rebasedUrl = self::toThemeAbsoluteUrl($sourceDir, $rawUrl);
                if ($rebasedUrl === null) {
                    return 'url(' . $quote . $rawUrl . $quote . ')';
                }

                return 'url(' . $quote . $rebasedUrl . $quote . ')';
            },
            $css
        ) ?? $css;
    }

    private static function isAbsoluteCssUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '//') || str_starts_with($url, '#')) {
            return true;
        }

        return (bool) preg_match('/^[a-z][a-z0-9+\-.]*:/i', $url);
    }

    private static function toThemeAbsoluteUrl(string $sourceDir, string $url): ?string
    {
        [$pathPart, $suffix] = self::splitUrlPathAndSuffix($url);
        if ($pathPart === '') {
            return null;
        }

        $resolvedPath = self::normalizePath($sourceDir . '/' . $pathPart);
        $themeRoot = self::normalizePath(THEME_DIR);

        if (!str_starts_with($resolvedPath, rtrim($themeRoot, '/') . '/')) {
            return null;
        }

        $relative = ltrim(substr($resolvedPath, strlen(rtrim($themeRoot, '/'))), '/');
        if ($relative === '') {
            return null;
        }

        return rtrim(THEME_URL, '/') . '/' . str_replace('\\', '/', $relative) . $suffix;
    }

    private static function splitUrlPathAndSuffix(string $url): array
    {
        $queryPos = strpos($url, '?');
        $hashPos = strpos($url, '#');
        $positions = array_filter([$queryPos, $hashPos], static fn ($pos): bool => $pos !== false);

        if (empty($positions)) {
            return [$url, ''];
        }

        $splitPos = min($positions);
        return [substr($url, 0, $splitPos), substr($url, $splitPos)];
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = '';

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $prefix = substr($path, 0, 2);
            $path = substr($path, 2);
        }

        $hasLeadingSlash = str_starts_with($path, '/');
        $parts = explode('/', $path);
        $normalizedParts = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (!empty($normalizedParts)) {
                    array_pop($normalizedParts);
                }
                continue;
            }
            $normalizedParts[] = $part;
        }

        $normalized = implode('/', $normalizedParts);
        if ($hasLeadingSlash) {
            $normalized = '/' . $normalized;
        }

        return $prefix . $normalized;
    }

    private static function uniquePaths(array $paths): array
    {
        $seen = [];
        $unique = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '' || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $unique[] = $path;
        }
        return $unique;
    }

    private static function uniqueRelativePaths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            $path = ltrim(str_replace('\\', '/', $path), '/');
            if ($path === '') {
                continue;
            }
            $normalized[] = $path;
        }
        return self::uniquePaths($normalized);
    }
}
