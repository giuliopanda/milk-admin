<?php
namespace Extensions\Projects\Classes;

use App\Abstracts\AbstractModule;
use App\Exceptions\FileException;
use App\File;

!defined('MILK_DIR') && die();

/**
 * ProjectJsonStore — Unified JSON file manager for a single Project directory.
 *
 * Centralises all JSON I/O (read, parse, cache, mutate, save) for the files
 * that live inside one `Project/` folder:
 *
 *   - manifest.json           (project tree & settings)
 *   - <FormName>.json         (model schema per form)
 *   - view_layout.json        (view page layout)
 *   - search_filters.json     (root list search filters)
 *
 * Design goals:
 *   1. Every file is read from disk **at most once** per request — subsequent
 *      access returns the in-memory cache.
 *   2. Scoped per project directory — multiple projects coexist in the same
 *      PHP process without collisions (each gets its own store instance).
 *   3. Single place for normalisation helpers (normalizeBool, normalizeKey, etc.)
 *      so that duplicates across other classes can be removed.
 *   4. Supports the full lifecycle: read → query → mutate → save / create.
 *
 * Typical usage:
 * ```php
 *   $store = ProjectJsonStore::for('/path/to/Module/Project');
 *
 *   // Level 1: decoded JSON arrays (cached after first disk read)
 *   $manifest = $store->manifest();          // decoded array
 *   $schema   = $store->schema('Orders');    // decoded array for Orders.json
 *
 *   // Level 2: parsed structural objects (cached after first parse)
 *   $manifest = $store->parsedManifest();    // ProjectManifest value object
 *   $index    = $store->manifestIndex();     // ProjectManifestIndex (graph/lookups)
 *
 *   // Convenience accessors
 *   $title    = $store->schemaTitle('Orders');
 *   $name     = $store->manifestName();
 *
 *   // Dot-path access into any cached file
 *   $filters  = $store->manifestGet('search_filters.forms.RootForm');
 *   $method   = $store->schemaGet('Orders', 'model.fields.0.method');
 *
 *   // Mutation
 *   $store->schemaSet('Orders', 'model.fields.0.label', 'New label');
 *   $store->schemaSave('Orders');            // writes Orders.json to disk
 *
 *   // Creation
 *   $store->createSchema('NewForm', ['_name' => 'New Form', 'model' => [...]]);
 * ```
 *
 * Thread-safety / concurrency: none — this is a per-request in-memory cache,
 * appropriate for the typical PHP shared-nothing lifecycle.
 */
class ProjectJsonStore
{
    // ------------------------------------------------------------------
    //  Static registry: one instance per project directory per request
    // ------------------------------------------------------------------

    /** @var array<string, self> */
    private static array $instances = [];

    /**
     * Obtain the store for a given project directory.
     *
     * If a store already exists for the canonical path, it is returned as-is
     * (ensuring cache is shared across all callers within the same request).
     */
    public static function for(string $projectDir): self
    {
        $key = self::canonicalKey($projectDir);

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($projectDir);
        }

        return self::$instances[$key];
    }

    /**
     * Drop all cached stores.
     *
     * Useful in long-running workers, test suites, or after a project folder
     * has been structurally changed on disk.
     */
    public static function resetAll(): void
    {
        self::$instances = [];
    }

    /**
     * Drop the cache for a single project directory.
     */
    public static function reset(string $projectDir): void
    {
        $key = self::canonicalKey($projectDir);
        unset(self::$instances[$key]);
    }

    /**
     * Return the manifest data for the current/requested module page.
     *
     * Returns false when:
     * - page is missing or module instance is not available
     * - module does not use Projects extension
     * - manifest file is missing/unreadable/invalid
     *
     * @param string|null $modulePage Optional module page slug. Defaults to $_REQUEST['page'].
     * @return array|false
     */
    public static function getCurrentManifestData(?string $modulePage = null): array|false
    {
        $module = self::resolveModuleInstance($modulePage);
        if (!$module instanceof AbstractModule) {
            return false;
        }

        $extensions = self::readModuleExtensions($module);
        if (!self::moduleUsesProjectsExtension($module, $extensions)) {
            return false;
        }

        $moduleDir = self::resolveModuleDir($module);
        if ($moduleDir === '') {
            return false;
        }

        $projectsConfig = self::resolveProjectsExtensionConfig($extensions);
        $manifestPath = self::findModuleManifestPath($moduleDir, $projectsConfig);
        if ($manifestPath === null) {
            return false;
        }

        $manifest = self::for(dirname($manifestPath))->manifest();
        return is_array($manifest) ? $manifest : false;
    }

    // ------------------------------------------------------------------
    //  Instance state
    // ------------------------------------------------------------------

    /** Canonical absolute path to the Project/ folder. */
    protected string $dir;

    /**
     * In-memory cache of decoded JSON files.
     *
     * Key   = logical slot name:
     *           'manifest'        → manifest.json
     *           'view_layout'     → view_layout.json
     *           'search_filters'  → search_filters.json
     *           'schema:<Name>'   → <Name>.json
     *
     * Value = decoded array | null (null = attempted load, file missing/invalid).
     *
     * A slot that has never been requested is simply absent from the map.
     *
     * @var array<string, array|null>
     */
    protected array $cache = [];

    /**
     * Raw JSON strings — kept when a file is loaded so that save() can diff
     * or fall back without re-reading.
     *
     * @var array<string, string>
     */
    protected array $rawCache = [];

    /**
     * Tracks which slots have been modified in memory (dirty flag).
     *
     * @var array<string, bool>
     */
    protected array $dirty = [];

    /**
     * Cached parsed manifest object (level-2 cache: structural parse result).
     *
     * Level 1 = $cache['manifest'] holds the decoded array (json_decode result).
     * Level 2 = $parsedManifest holds the validated ProjectManifest value object.
     *
     * This avoids re-running ProjectManifestParser::parseArray() on every call.
     */
    protected ?ProjectManifest $parsedManifest = null;

    /**
     * Cached manifest index (level-2 cache: graph/index built from ProjectManifest).
     *
     * Built once from $parsedManifest; provides parent/child lookups, FK chains, etc.
     */
    protected ?ProjectManifestIndex $parsedManifestIndex = null;

    /**
     * Accumulated warnings from reads/parses (non-fatal).
     *
     * @var string[]
     */
    protected array $warnings = [];

    /**
     * Accumulated errors from reads/parses (fatal per-file, but non-fatal for the store).
     *
     * @var string[]
     */
    protected array $errors = [];

    // ------------------------------------------------------------------
    //  Constructor (private — use ::for())
    // ------------------------------------------------------------------

    private function __construct(string $projectDir)
    {
        $this->dir = rtrim(str_replace('\\', '/', $projectDir), '/');
    }

    /**
     * Resolve module instance from an explicit page or current request page.
     */
    private static function resolveModuleInstance(?string $modulePage = null): ?AbstractModule
    {
        $page = trim((string) ($modulePage ?? ($_REQUEST['page'] ?? '')));
        if ($page === '') {
            return null;
        }

        $module = AbstractModule::getInstance($page);
        return $module instanceof AbstractModule ? $module : null;
    }

    /**
     * Read normalized extensions array from module instance.
     *
     * @return array<string|int, mixed>
     */
    private static function readModuleExtensions(AbstractModule $module): array
    {
        try {
            $reflection = new \ReflectionObject($module);
            while ($reflection instanceof \ReflectionClass) {
                if ($reflection->hasProperty('extensions')) {
                    $property = $reflection->getProperty('extensions');
                    $value = $property->getValue($module);
                    return is_array($value) ? $value : [];
                }
                $reflection = $reflection->getParentClass();
            }
        } catch (\Throwable) {
            return [];
        }

        return [];
    }

    /**
     * Detect whether the given module uses the Projects extension.
     *
     * @param array<string|int, mixed> $extensions
     */
    private static function moduleUsesProjectsExtension(AbstractModule $module, array $extensions): bool
    {
        $loaded = $module->getLoadedExtensions('Projects');
        if (is_object($loaded)) {
            return true;
        }

        foreach ($extensions as $key => $value) {
            if (is_string($key) && strcasecmp(trim($key), 'Projects') === 0) {
                return true;
            }
            if (is_int($key) && is_string($value) && strcasecmp(trim($value), 'Projects') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract Projects extension config from module extensions.
     *
     * @param array<string|int, mixed> $extensions
     * @return array<string,mixed>
     */
    private static function resolveProjectsExtensionConfig(array $extensions): array
    {
        foreach ($extensions as $key => $value) {
            if (is_string($key) && strcasecmp(trim($key), 'Projects') === 0) {
                return is_array($value) ? $value : [];
            }
            if (is_int($key) && is_string($value) && strcasecmp(trim($value), 'Projects') === 0) {
                return [];
            }
        }

        return [];
    }

    /**
     * Resolve module directory from module concrete class file path.
     */
    private static function resolveModuleDir(AbstractModule $module): string
    {
        try {
            $moduleFilePath = (string) (new \ReflectionClass($module))->getFileName();
        } catch (\Throwable) {
            return '';
        }

        if ($moduleFilePath === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $moduleFilePath);
        if (preg_match('~^(.*?/Modules/[^/]+)(?:/.*)?$~', $normalized, $matches) === 1) {
            return rtrim((string) $matches[1], '/');
        }

        return rtrim((string) dirname($moduleFilePath), '/');
    }

    /**
     * Find manifest path for a module directory.
     *
     * @param array<string,mixed> $projectsConfig
     */
    private static function findModuleManifestPath(string $moduleDir, array $projectsConfig = []): ?string
    {
        $moduleDir = rtrim(str_replace('\\', '/', $moduleDir), '/');
        if ($moduleDir === '') {
            return null;
        }

        $folders = [];
        $schemaFolder = trim((string) ($projectsConfig['schemaFolder'] ?? ''));
        if ($schemaFolder !== '') {
            $folders[] = trim($schemaFolder, "/\\ \t\n\r\0\x0B");
        }

        $folders[] = 'Project';
        $folders[] = 'project';
        $folders[] = 'Projects';
        $folders[] = 'projects';
        $folders = array_values(array_unique(array_filter($folders, static fn($folder): bool => $folder !== '')));

        foreach ($folders as $folder) {
            $path = $moduleDir . '/' . $folder . '/manifest.json';
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    //  1. MANIFEST — manifest.json
    // ------------------------------------------------------------------

    /**
     * Get the decoded manifest array (cached).
     *
     * @return array|null  null if file is missing/unreadable/invalid.
     */
    public function manifest(): ?array
    {
        return $this->load('manifest', 'manifest.json');
    }

    /**
     * Manifest project name (`name` key).
     */
    public function manifestName(): string
    {
        return trim((string) (($this->manifest() ?? [])['name'] ?? ''));
    }

    /**
     * Manifest version (`version` key).
     */
    public function manifestVersion(): string
    {
        $version = trim((string) (($this->manifest() ?? [])['version'] ?? '1.0'));
        return $version !== '' ? $version : '1.0';
    }

    /**
     * Manifest settings sub-object.
     *
     * @return array<string, mixed>
     */
    public function manifestSettings(): array
    {
        $description = trim((string) (($this->manifest() ?? [])['description'] ?? ''));
        return $description !== '' ? ['description' => $description] : [];
    }

    /**
     * Deep-get a value from the manifest using dot-notation.
     *
     * Example: `$store->manifestGet('search_filters.forms.Root')`
     */
    public function manifestGet(string $dotPath, mixed $default = null): mixed
    {
        return self::dotGet($this->manifest() ?? [], $dotPath, $default);
    }

    /**
     * Deep-set a value in the manifest cache (marks dirty, invalidates parsed objects).
     */
    public function manifestSet(string $dotPath, mixed $value): void
    {
        $data = $this->manifest() ?? [];
        self::dotSet($data, $dotPath, $value);
        $this->cache['manifest'] = $data;
        $this->dirty['manifest'] = true;
        $this->invalidateParsedManifest();
    }

    /**
     * Remove a key from the manifest cache (marks dirty, invalidates parsed objects).
     */
    public function manifestRemove(string $dotPath): void
    {
        $data = $this->manifest() ?? [];
        self::dotRemove($data, $dotPath);
        $this->cache['manifest'] = $data;
        $this->dirty['manifest'] = true;
        $this->invalidateParsedManifest();
    }

    /**
     * Write manifest cache to disk.
     *
     * @param int $jsonFlags  json_encode flags (default: pretty + unescaped unicode).
     * @return bool
     */
    public function manifestSave(int $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): bool
    {
        return $this->save('manifest', 'manifest.json', $jsonFlags);
    }

    // ------------------------------------------------------------------
    //  1b. PARSED MANIFEST — ProjectManifest + ProjectManifestIndex
    //      (level-2 cache: structural parse, built once per request)
    // ------------------------------------------------------------------

    /**
     * Get the parsed ProjectManifest value object (cached).
     *
     * This is the result of running ProjectManifestParser::parseArray() on the
     * decoded manifest JSON. It is built once and reused by every caller —
     * Module.php::configure(), Module.php::bootstrap(), every Model::configure().
     *
     * @return ProjectManifest|null  null if manifest file is missing/invalid or parsing fails.
     */
    public function parsedManifest(): ?ProjectManifest
    {
        if ($this->parsedManifest !== null) {
            return $this->parsedManifest;
        }

        $data = $this->manifest();
        if ($data === null) {
            return null;
        }

        try {
            $parser = new ProjectManifestParser();
            $this->parsedManifest = $parser->parseArray($data);
        } catch (\Throwable $e) {
            $this->addError("Manifest parse error in manifest.json: {$e->getMessage()}");
            return null;
        }

        return $this->parsedManifest;
    }

    /**
     * Get the ProjectManifestIndex (cached).
     *
     * Provides parent/child lookups, FK chain resolution, ancestor traversal, etc.
     * Built once from parsedManifest().
     *
     * @return ProjectManifestIndex|null  null if manifest is missing/invalid.
     */
    public function manifestIndex(): ?ProjectManifestIndex
    {
        if ($this->parsedManifestIndex !== null) {
            return $this->parsedManifestIndex;
        }

        $manifest = $this->parsedManifest();
        if ($manifest === null) {
            return null;
        }

        try {
            $this->parsedManifestIndex = new ProjectManifestIndex($manifest);
        } catch (\Throwable $e) {
            $this->addError("Manifest index error in manifest.json: {$e->getMessage()}");
            return null;
        }

        return $this->parsedManifestIndex;
    }

    /**
     * Invalidate the level-2 manifest cache.
     *
     * Call this after modifying the manifest data in cache (manifestSet/manifestRemove)
     * if you need the parsed objects to reflect the changes.
     */
    public function invalidateParsedManifest(): void
    {
        $this->parsedManifest = null;
        $this->parsedManifestIndex = null;
    }

    // ------------------------------------------------------------------
    //  2. FORM SCHEMAS — <FormName>.json
    // ------------------------------------------------------------------

    /**
     * Get the decoded schema array for a form (cached).
     *
     * @param string $formName  Form name without extension (e.g. 'Orders').
     * @return array|null  null if file missing/unreadable/invalid.
     */
    public function schema(string $formName): ?array
    {
        $formName = trim($formName);
        if ($formName === '') {
            return null;
        }

        $slot = 'schema:' . $formName;
        return $this->load($slot, $formName . '.json');
    }

    /**
     * Schema `_name` (human-readable title from the JSON).
     */
    public function schemaTitle(string $formName, string $fallback = ''): string
    {
        $title = trim((string) ($this->schema($formName)['_name'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        if ($fallback !== '') {
            return $fallback;
        }

        return ProjectNaming::toTitle($formName);
    }

    /**
     * Get the `model` section of a form schema.
     *
     * @return array<string, mixed>
     */
    public function schemaModel(string $formName): array
    {
        $m = $this->schema($formName)['model'] ?? null;
        return is_array($m) ? $m : [];
    }

    /**
     * Deep-get a value from a schema file using dot-notation.
     */
    public function schemaGet(string $formName, string $dotPath, mixed $default = null): mixed
    {
        return self::dotGet($this->schema($formName) ?? [], $dotPath, $default);
    }

    /**
     * Deep-set a value in a schema cache (marks dirty).
     */
    public function schemaSet(string $formName, string $dotPath, mixed $value): void
    {
        $slot = 'schema:' . trim($formName);
        $data = $this->schema($formName) ?? [];
        self::dotSet($data, $dotPath, $value);
        $this->cache[$slot] = $data;
        $this->dirty[$slot] = true;
    }

    /**
     * Remove a key from a schema cache (marks dirty).
     */
    public function schemaRemove(string $formName, string $dotPath): void
    {
        $slot = 'schema:' . trim($formName);
        $data = $this->schema($formName) ?? [];
        self::dotRemove($data, $dotPath);
        $this->cache[$slot] = $data;
        $this->dirty[$slot] = true;
    }

    /**
     * Replace the entire schema cache for a form (marks dirty).
     */
    public function schemaReplace(string $formName, array $data): void
    {
        $slot = 'schema:' . trim($formName);
        $this->cache[$slot] = $data;
        $this->dirty[$slot] = true;
    }

    /**
     * Write a single form schema to disk.
     */
    public function schemaSave(string $formName, int $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): bool
    {
        $slot = 'schema:' . trim($formName);
        return $this->save($slot, trim($formName) . '.json', $jsonFlags);
    }

    /**
     * Create a new schema file (write + populate cache).
     *
     * @throws \RuntimeException if file already exists.
     */
    public function createSchema(string $formName, array $data, int $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): bool
    {
        $formName = trim($formName);
        $filePath = $this->dir . '/' . $formName . '.json';

        if (is_file($filePath)) {
            throw new \RuntimeException("Schema file already exists: {$filePath}");
        }

        $slot = 'schema:' . $formName;
        $this->cache[$slot] = $data;
        $this->dirty[$slot] = true;

        return $this->save($slot, $formName . '.json', $jsonFlags);
    }

    // ------------------------------------------------------------------
    //  3. VIEW LAYOUT — view_layout.json
    // ------------------------------------------------------------------

    /**
     * Get the decoded view_layout array (cached).
     *
     * Falls back to the manifest's embedded `view_layout` key when the
     * dedicated file does not exist.
     */
    public function viewLayout(): ?array
    {
        $data = $this->load('view_layout', 'view_layout.json');

        if ($data !== null) {
            return $data;
        }

        // Fallback: embedded in manifest.
        $embedded = $this->manifestGet('view_layout');
        if (is_array($embedded) && !empty($embedded)) {
            $this->cache['view_layout'] = $embedded;
            return $embedded;
        }

        return null;
    }

    /**
     * Deep-get from view layout.
     */
    public function viewLayoutGet(string $dotPath, mixed $default = null): mixed
    {
        return self::dotGet($this->viewLayout() ?? [], $dotPath, $default);
    }

    /**
     * Deep-set in view layout cache (marks dirty).
     */
    public function viewLayoutSet(string $dotPath, mixed $value): void
    {
        $data = $this->viewLayout() ?? [];
        self::dotSet($data, $dotPath, $value);
        $this->cache['view_layout'] = $data;
        $this->dirty['view_layout'] = true;
    }

    /**
     * Write view_layout.json to disk.
     */
    public function viewLayoutSave(int $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): bool
    {
        return $this->save('view_layout', 'view_layout.json', $jsonFlags);
    }

    // ------------------------------------------------------------------
    //  4. SEARCH FILTERS — search_filters.json
    // ------------------------------------------------------------------

    /**
     * Get the decoded search_filters array (cached).
     *
     * Falls back to the manifest's embedded `search_filters` key.
     */
    public function searchFilters(): ?array
    {
        $data = $this->load('search_filters', 'search_filters.json');

        if ($data !== null) {
            return $data;
        }

        // Fallback: embedded in manifest.
        $embedded = $this->manifestGet('search_filters');
        if (is_array($embedded) && !empty($embedded)) {
            $this->cache['search_filters'] = $embedded;
            return $embedded;
        }

        return null;
    }

    /**
     * Deep-get from search filters.
     */
    public function searchFiltersGet(string $dotPath, mixed $default = null): mixed
    {
        return self::dotGet($this->searchFilters() ?? [], $dotPath, $default);
    }

    /**
     * Write search_filters.json to disk.
     */
    public function searchFiltersSave(int $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): bool
    {
        return $this->save('search_filters', 'search_filters.json', $jsonFlags);
    }

    // ------------------------------------------------------------------
    //  5. GENERIC / RAW ACCESS
    // ------------------------------------------------------------------

    /**
     * Load any JSON file inside this project directory (cached by slot).
     *
     * This is useful for custom/future files that don't have a dedicated API yet.
     */
    public function loadFile(string $filename): ?array
    {
        $slot = 'file:' . $filename;
        return $this->load($slot, $filename);
    }

    /**
     * Get the raw JSON string from a cached file.
     *
     * Useful for diff/review UIs.
     */
    public function rawJson(string $filename): ?string
    {
        $slot = $this->resolveSlotFromFilename($filename);
        // Trigger load if not yet cached.
        $this->load($slot, $filename);
        return $this->rawCache[$slot] ?? null;
    }

    /**
     * Get the project directory path.
     */
    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * Check if a file exists in this project directory.
     */
    public function fileExists(string $filename): bool
    {
        return is_file($this->dir . '/' . $filename);
    }

    /**
     * Get full path for a file inside this project.
     */
    public function filePath(string $filename): string
    {
        return $this->dir . '/' . $filename;
    }

    /**
     * Check if a specific slot has unsaved changes.
     */
    public function isDirty(string $slotOrFilename): bool
    {
        $slot = $this->resolveSlotFromFilename($slotOrFilename);
        return !empty($this->dirty[$slot]);
    }

    /**
     * Check if any slot has unsaved changes.
     */
    public function hasUnsavedChanges(): bool
    {
        return !empty($this->dirty);
    }

    /**
     * Discard in-memory changes for a slot (reload from disk on next access).
     */
    public function discard(string $slotOrFilename): void
    {
        $slot = $this->resolveSlotFromFilename($slotOrFilename);
        unset($this->cache[$slot], $this->rawCache[$slot], $this->dirty[$slot]);

        // If manifest slot is discarded, level-2 objects must go too.
        if ($slot === 'manifest') {
            $this->invalidateParsedManifest();
        }
    }

    /**
     * Discard all in-memory data (full reset of this store instance).
     */
    public function discardAll(): void
    {
        $this->cache = [];
        $this->rawCache = [];
        $this->dirty = [];
        $this->warnings = [];
        $this->errors = [];
        $this->parsedManifest = null;
        $this->parsedManifestIndex = null;
    }

    // ------------------------------------------------------------------
    //  6. DIAGNOSTICS
    // ------------------------------------------------------------------

    /** @return string[] */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Summary of what has been loaded (useful for debug / profiling).
     *
     * @return array<string, array{loaded: bool, dirty: bool, file: string}>
     */
    public function getCacheStatus(): array
    {
        $status = [];
        foreach ($this->cache as $slot => $data) {
            $status[$slot] = [
                'loaded' => true,
                'dirty'  => !empty($this->dirty[$slot]),
                'file'   => $this->slotToFilename($slot),
                'valid'  => $data !== null,
            ];
        }
        return $status;
    }

    // ------------------------------------------------------------------
    //  7. STATIC NORMALISATION UTILITIES
    //     (single-source-of-truth; other classes should call these)
    // ------------------------------------------------------------------

    /**
     * Normalize a value to bool.
     *
     * Accepts: true, false, 1, 0, '1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'.
     */
    public static function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_float($value)) {
            return ((int) $value) === 1;
        }

        $v = strtolower(trim((string) $value));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Normalize display mode values.
     *
     * @return string  One of 'page', 'offcanvas', 'modal'.
     */
    public static function normalizeDisplayMode(mixed $value, string $default = 'page'): string
    {
        $v = strtolower(trim((string) $value));
        if (in_array($v, ['page', 'offcanvas', 'modal'], true)) {
            return $v;
        }
        return $default;
    }

    /**
     * Normalize child count column visibility mode.
     *
     * @return string  '' (default behavior), 'hide', or 'show'.
     */
    public static function normalizeChildCountColumnMode(mixed $value): string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '' || in_array($v, ['default', 'auto'], true)) {
            return '';
        }
        if (in_array($v, ['hide', 'show'], true)) {
            return $v;
        }
        return '';
    }

    /**
     * Normalize max_records manifest values.
     *
     * @return string  '1', '<N>', 'n', or 'unlimited'.
     */
    public static function normalizeMaxRecords(mixed $value): string
    {
        if (is_int($value)) {
            return $value <= 0 ? 'n' : (string) $value;
        }

        $v = strtolower(trim((string) $value));

        if ($v !== '' && ctype_digit($v)) {
            $n = (int) $v;
            return $n <= 0 ? 'n' : (string) $n;
        }

        return match ($v) {
            '1', 'one', 'single' => '1',
            'unlimited', 'infinite' => 'unlimited',
            default => 'n',
        };
    }

    /**
     * Resolve a manifest key that may appear in camelCase or snake_case.
     *
     * Usage:
     *   $val = ProjectJsonStore::resolveAliasedKey($data, ['showIf', 'show_if']);
     *
     * Returns the value of the first key found, or $default.
     */
    public static function resolveAliasedKey(array $data, array $aliases, mixed $default = null): mixed
    {
        foreach ($aliases as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }
        return $default;
    }

    /**
     * Check if at least one of the aliased keys exists in the array.
     */
    public static function hasAliasedKey(array $data, array $aliases): bool
    {
        foreach ($aliases as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitize a string for safe use as a filename component.
     *
     * Removes path separators, null bytes, and non-printable characters.
     * Keeps alphanumeric, dash, underscore, and dot.
     */
    public static function sanitizeFilename(string $name): string
    {
        $name = str_replace(["\0", '\\', '/'], '', $name);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '', $name);
        return trim((string) $name, '.');
    }

    // ------------------------------------------------------------------
    //  8. STATIC DOT-NOTATION HELPERS
    // ------------------------------------------------------------------

    /**
     * Get a value from a nested array using dot-notation.
     *
     * Example: dotGet($data, 'model.fields.0.name')
     */
    public static function dotGet(array $data, string $path, mixed $default = null): mixed
    {
        if ($path === '') {
            return $data;
        }

        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } elseif (is_array($current) && ctype_digit($key) && array_key_exists((int) $key, $current)) {
                $current = $current[(int) $key];
            } else {
                return $default;
            }
        }

        return $current;
    }

    /**
     * Set a value in a nested array using dot-notation.
     *
     * Creates intermediate arrays as needed.
     */
    public static function dotSet(array &$data, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$data;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                if (ctype_digit($key)) {
                    $current[(int) $key] = $value;
                } else {
                    $current[$key] = $value;
                }
            } else {
                $resolved = ctype_digit($key) ? (int) $key : $key;
                if (!isset($current[$resolved]) || !is_array($current[$resolved])) {
                    $current[$resolved] = [];
                }
                $current = &$current[$resolved];
            }
        }
    }

    /**
     * Remove a key from a nested array using dot-notation.
     */
    public static function dotRemove(array &$data, string $path): void
    {
        $keys = explode('.', $path);
        $current = &$data;

        for ($i = 0; $i < count($keys) - 1; $i++) {
            $key = ctype_digit($keys[$i]) ? (int) $keys[$i] : $keys[$i];
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return;
            }
            $current = &$current[$key];
        }

        $lastKey = end($keys);
        $resolved = ctype_digit($lastKey) ? (int) $lastKey : $lastKey;
        unset($current[$resolved]);
    }

    /**
     * Check if a dot-notation path exists in a nested array.
     */
    public static function dotHas(array $data, string $path): bool
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            $resolved = ctype_digit($key) ? (int) $key : $key;
            if (!is_array($current) || !array_key_exists($resolved, $current)) {
                return false;
            }
            $current = $current[$resolved];
        }

        return true;
    }

    // ------------------------------------------------------------------
    //  INTERNAL: file I/O
    // ------------------------------------------------------------------

    /**
     * Load a JSON file into the cache (if not already loaded).
     *
     * @return array|null  Decoded data, or null on failure.
     */
    protected function load(string $slot, string $filename): ?array
    {
        // Already in cache (even if null = "loaded but invalid/missing").
        if (array_key_exists($slot, $this->cache)) {
            return $this->cache[$slot];
        }

        $filePath = $this->dir . '/' . $filename;

        if (!is_file($filePath)) {
            $this->cache[$slot] = null;
            return null;
        }

        try {
            $raw = File::getContents($filePath);
        } catch (FileException) {
            $this->addWarning("Empty or unreadable file: {$filename}");
            $this->cache[$slot] = null;
            return null;
        }

        if (trim($raw) === '') {
            $this->addWarning("Empty or unreadable file: {$filename}");
            $this->cache[$slot] = null;
            return null;
        }

        $this->rawCache[$slot] = $raw;

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->addError("Invalid JSON in {$filename}: {$e->getMessage()}");
            $this->cache[$slot] = null;
            return null;
        }

        if (!is_array($data)) {
            $this->addError("JSON root must be an object/array in {$filename}.");
            $this->cache[$slot] = null;
            return null;
        }

        $this->cache[$slot] = $data;
        return $data;
    }

    /**
     * Write the cached data for a slot to disk.
     */
    protected function save(string $slot, string $filename, int $jsonFlags): bool
    {
        if (!array_key_exists($slot, $this->cache) || $this->cache[$slot] === null) {
            $this->addError("Cannot save {$filename}: no data in cache.");
            return false;
        }

        $json = json_encode($this->cache[$slot], $jsonFlags);
        if ($json === false) {
            $this->addError("Failed to encode JSON for {$filename}: " . json_last_error_msg());
            return false;
        }

        $filePath = $this->dir . '/' . $filename;

        // Ensure the directory exists.
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                $this->addError("Cannot create directory: {$dir}");
                return false;
            }
        }

        try {
            File::putContents($filePath, $json . "\n");
        } catch (FileException) {
            $this->addError("Failed to write {$filePath}");
            return false;
        }

        // Update raw cache and clear dirty flag.
        $this->rawCache[$slot] = $json;
        unset($this->dirty[$slot]);

        return true;
    }

    // ------------------------------------------------------------------
    //  INTERNAL: slot/key helpers
    // ------------------------------------------------------------------

    private static function canonicalKey(string $dir): string
    {
        $normalized = rtrim(str_replace('\\', '/', $dir), '/');
        $real = realpath($normalized);
        return $real !== false ? $real : $normalized;
    }

    /**
     * Resolve a user-provided slot-or-filename to the internal slot key.
     */
    protected function resolveSlotFromFilename(string $input): string
    {
        // Already a slot key?
        if (str_contains($input, ':') || in_array($input, ['manifest', 'view_layout', 'search_filters'], true)) {
            return $input;
        }

        return match ($input) {
            'manifest.json'        => 'manifest',
            'view_layout.json'     => 'view_layout',
            'search_filters.json'  => 'search_filters',
            default                => 'file:' . $input,
        };
    }

    /**
     * Reverse: slot key → filename.
     */
    protected function slotToFilename(string $slot): string
    {
        if (str_starts_with($slot, 'schema:')) {
            return substr($slot, 7) . '.json';
        }
        if (str_starts_with($slot, 'file:')) {
            return substr($slot, 5);
        }

        return match ($slot) {
            'manifest'       => 'manifest.json',
            'view_layout'    => 'view_layout.json',
            'search_filters' => 'search_filters.json',
            default          => $slot . '.json',
        };
    }

    // ------------------------------------------------------------------
    //  INTERNAL: diagnostics
    // ------------------------------------------------------------------

    protected function addWarning(string $msg): void
    {
        $this->warnings[] = $msg;
    }

    protected function addError(string $msg): void
    {
        $this->errors[] = $msg;
    }
}
