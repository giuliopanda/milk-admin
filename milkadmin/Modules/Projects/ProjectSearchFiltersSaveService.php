<?php

namespace Modules\Projects;

use Extensions\Projects\Classes\SearchFiltersConfigParser;

!defined('MILK_DIR') && die();

class ProjectSearchFiltersSaveService
{
    public static function saveFromRawInput(string|false $jsonData, string $projectsPage): array
    {
        $input = is_string($jsonData) ? json_decode($jsonData, true) : null;
        if (!is_array($input)) {
            return ['success' => false, 'msg' => 'Invalid JSON payload.'];
        }

        $moduleName = trim((string) ($input['module'] ?? ''));
        if ($moduleName === '' || preg_match('/^[A-Za-z0-9_-]+$/', $moduleName) !== 1) {
            return ['success' => false, 'msg' => 'Invalid module name.'];
        }

        $rawConfig = is_array($input['config'] ?? null) ? $input['config'] : null;
        if (!is_array($rawConfig)) {
            return ['success' => false, 'msg' => 'Missing config payload.'];
        }

        $project = ProjectCatalogService::findProjectByModuleName($moduleName, $projectsPage);
        if (!is_array($project)) {
            return ['success' => false, 'msg' => 'Project not found.'];
        }

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return ['success' => false, 'msg' => 'Manifest file not found.'];
        }

        $manifest = ManifestService::readManifest($manifestPath);
        if (!is_array($manifest)) {
            return ['success' => false, 'msg' => 'Unable to read manifest file.'];
        }

        $rootNode = ManifestService::extractRootNode($manifest);
        if (!is_array($rootNode)) {
            return ['success' => false, 'msg' => 'Unable to resolve root form from manifest.'];
        }

        $rootRef = basename(trim((string) ($rootNode['ref'] ?? '')));
        $rootFormName = trim((string) pathinfo($rootRef, PATHINFO_FILENAME));
        if ($rootFormName === '') {
            return ['success' => false, 'msg' => 'Invalid root form ref in manifest.'];
        }

        $projectDir = dirname($manifestPath);
        if ($projectDir === '' || !is_dir($projectDir)) {
            return ['success' => false, 'msg' => 'Project directory not found.'];
        }

        $searchFiltersPath = $projectDir . '/search_filters.json';
        $normalizedConfigResult = self::normalizeIncomingConfig($rootFormName, $rawConfig);
        if (!$normalizedConfigResult['success']) {
            return ['success' => false, 'msg' => (string) ($normalizedConfigResult['msg'] ?? 'Invalid config.')];
        }

        $normalizedConfig = is_array($normalizedConfigResult['config'] ?? null)
            ? $normalizedConfigResult['config']
            : ['search_mode' => 'submit', 'auto_buttons' => true, 'url_params' => [], 'filters' => []];
        $warnings = is_array($normalizedConfigResult['warnings'] ?? null)
            ? $normalizedConfigResult['warnings']
            : [];

        $existingRaw = self::readExistingRawConfig($searchFiltersPath);
        $merged = self::mergeConfigForRootForm($existingRaw, $rootFormName, $normalizedConfig);

        try {
            $json = json_encode(
                $merged,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            return ['success' => false, 'msg' => 'Unable to encode search filters JSON.'];
        }

        $written = @file_put_contents($searchFiltersPath, $json . "\n");
        if ($written === false) {
            return ['success' => false, 'msg' => 'Unable to write search_filters.json.'];
        }

        $result = [
            'success' => true,
            'msg' => 'Search filters saved.',
            'saved_path' => $searchFiltersPath,
            'saved_config' => $normalizedConfig,
        ];
        if (!empty($warnings)) {
            $result['warnings'] = $warnings;
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $rawConfig
     * @return array{success:bool,msg?:string,config?:array<string,mixed>,warnings?:array<int,string>}
     */
    private static function normalizeIncomingConfig(string $rootFormName, array $rawConfig): array
    {
        $searchMode = 'submit';
        $autoButtons = self::normalizeBool($rawConfig['auto_buttons'] ?? true);
        $filtersRaw = is_array($rawConfig['filters'] ?? null) ? $rawConfig['filters'] : [];
        $urlParamsRaw = is_array($rawConfig['url_params'] ?? ($rawConfig['urlParams'] ?? null))
            ? ($rawConfig['url_params'] ?? $rawConfig['urlParams'])
            : [];
        $warnings = [];
        $filtersRaw = self::assignGeneratedNamesToFilters($filtersRaw, $warnings);

        $parser = new SearchFiltersConfigParser();
        try {
            $normalizedByForm = $parser->parseArray([
                'forms' => [
                    $rootFormName => [
                        'search_mode' => $searchMode,
                        'auto_buttons' => $autoButtons,
                        'url_params' => $urlParamsRaw,
                        'filters' => $filtersRaw,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }

        $normalized = is_array($normalizedByForm[$rootFormName] ?? null)
            ? $normalizedByForm[$rootFormName]
            : [
                'search_mode' => $searchMode,
                'auto_buttons' => $autoButtons,
                'url_params' => [],
                'filters' => [],
            ];

        return [
            'success' => true,
            'config' => $normalized,
            'warnings' => array_values(array_unique(array_merge($warnings, $parser->getWarnings()))),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function readExistingRawConfig(string $searchFiltersPath): array
    {
        if (!is_file($searchFiltersPath)) {
            return [];
        }

        $raw = @file_get_contents($searchFiltersPath);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $normalizedConfig
     * @return array<string,mixed>
     */
    private static function mergeConfigForRootForm(array $existing, string $rootFormName, array $normalizedConfig): array
    {
        $out = $existing;
        $forms = is_array($out['forms'] ?? null) ? $out['forms'] : [];

        if (empty($forms)) {
            $singleShapeConfig = self::extractSingleShapeConfig($out);
            if (!empty($singleShapeConfig)) {
                $forms['*'] = $singleShapeConfig;
            }
        }

        $baseConfig = [];
        if (is_array($forms[$rootFormName] ?? null)) {
            $baseConfig = $forms[$rootFormName];
        } elseif (is_array($forms['*'] ?? null)) {
            $baseConfig = $forms['*'];
        }

        $baseConfig['search_mode'] = 'submit';
        $baseConfig['auto_buttons'] = self::normalizeBool($normalizedConfig['auto_buttons'] ?? true);
        $baseConfig['url_params'] = is_array($normalizedConfig['url_params'] ?? null) ? $normalizedConfig['url_params'] : [];
        $baseConfig['filters'] = is_array($normalizedConfig['filters'] ?? null) ? $normalizedConfig['filters'] : [];

        $forms[$rootFormName] = $baseConfig;
        $out['forms'] = $forms;

        if (!isset($out['_version']) || trim((string) $out['_version']) === '') {
            $out['_version'] = '1.0';
        }

        unset(
            $out['search_mode'],
            $out['searchMode'],
            $out['auto_buttons'],
            $out['autoButtons'],
            $out['wrapper_class'],
            $out['wrapperClass'],
            $out['form_classes'],
            $out['formClasses'],
            $out['container_classes'],
            $out['containerClass'],
            $out['url_params'],
            $out['urlParams'],
            $out['filters']
        );

        return $out;
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private static function extractSingleShapeConfig(array $raw): array
    {
        $single = [];

        if (array_key_exists('search_mode', $raw) || array_key_exists('searchMode', $raw)) {
            $single['search_mode'] = 'submit';
        }
        if (array_key_exists('auto_buttons', $raw) || array_key_exists('autoButtons', $raw)) {
            $single['auto_buttons'] = self::normalizeBool($raw['auto_buttons'] ?? ($raw['autoButtons'] ?? true));
        }
        if (array_key_exists('wrapper_class', $raw) || array_key_exists('wrapperClass', $raw)) {
            $single['wrapper_class'] = trim((string) ($raw['wrapper_class'] ?? ($raw['wrapperClass'] ?? '')));
        }
        if (array_key_exists('form_classes', $raw) || array_key_exists('formClasses', $raw)) {
            $single['form_classes'] = trim((string) ($raw['form_classes'] ?? ($raw['formClasses'] ?? '')));
        }
        if (array_key_exists('container_classes', $raw) || array_key_exists('containerClass', $raw)) {
            $single['container_classes'] = trim((string) ($raw['container_classes'] ?? ($raw['containerClass'] ?? '')));
        }
        if ((array_key_exists('url_params', $raw) || array_key_exists('urlParams', $raw))
            && is_array($raw['url_params'] ?? ($raw['urlParams'] ?? null))) {
            $single['url_params'] = $raw['url_params'] ?? $raw['urlParams'];
        }
        if (array_key_exists('filters', $raw) && is_array($raw['filters'])) {
            $single['filters'] = $raw['filters'];
        }

        return $single;
    }

    private static function normalizeBool(mixed $value): bool
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

    /**
     * @param array<int,mixed> $filtersRaw
     * @param array<int,string> $warnings
     * @return array<int,mixed>
     */
    private static function assignGeneratedNamesToFilters(array $filtersRaw, array &$warnings): array
    {
        $result = [];
        $usedNames = [];

        foreach ($filtersRaw as $index => $filterRaw) {
            if (!is_array($filterRaw)) {
                $result[] = $filterRaw;
                continue;
            }

            $filter = $filterRaw;
            $type = strtolower(trim((string) ($filter['type'] ?? '')));
            if (in_array($type, ['newline', 'search_button', 'clear_button'], true)) {
                $result[] = $filter;
                continue;
            }

            $label = trim((string) ($filter['label'] ?? ''));
            if ($label === '') {
                $fallback = trim((string) ($filter['name'] ?? ''));
                if ($fallback !== '') {
                    $label = $fallback;
                } else {
                    $label = 'filter';
                    $warnings[] = 'Filter #' . ((int) $index + 1) . ' has empty label. Generated default name.';
                }
            }

            $baseName = self::normalizeFilterNameBase($label);
            if ($baseName === '') {
                $baseName = 'filter';
                $warnings[] = 'Filter #' . ((int) $index + 1) . ' label produced an empty name. Fallback "filter" used.';
            }

            $filter['name'] = self::buildUniqueFilterName($baseName, $usedNames);
            $result[] = $filter;
        }

        return $result;
    }

    private static function normalizeFilterNameBase(string $label): string
    {
        $normalized = strtolower(trim($label));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);
        return is_string($normalized) ? trim($normalized) : '';
    }

    /**
     * @param array<string,bool> $usedNames
     */
    private static function buildUniqueFilterName(string $baseName, array &$usedNames): string
    {
        $candidate = $baseName;
        if (!isset($usedNames[$candidate])) {
            $usedNames[$candidate] = true;
            return $candidate;
        }

        $suffix = 1;
        while (true) {
            $candidate = $baseName . str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            if (!isset($usedNames[$candidate])) {
                $usedNames[$candidate] = true;
                return $candidate;
            }
            $suffix++;
        }
    }
}
