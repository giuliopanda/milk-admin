<?php

namespace Modules\Projects;

use Extensions\Projects\Classes\ProjectNaming;
use Extensions\Projects\Classes\SearchFiltersConfigParser;
use Modules\Projects\DraftService\DraftModelResolver;

!defined('MILK_DIR') && die();

class ProjectSearchFiltersService
{
    /**
     * @param array<string,mixed>|null $project
     * @return array{
     *   can_edit:bool,
     *   error:string,
     *   root_ref:string,
     *   root_form_name:string,
     *   model_fqcn:string,
     *   model_file_path:string,
     *   fields:array<int,array{name:string,label:string,type:string}>,
     *   db_fields:array<int,array{name:string,label:string,type:string,sanitize_type:string}>,
     *   search_filters_path:string,
     *   search_filters_notice:string,
     *   initial_config:array{
     *      search_mode:string,
     *      auto_buttons:bool,
     *      url_params:array<int,array<string,mixed>>,
     *      filters:array<int,array<string,mixed>>
     *   }
     * }
     */
    public static function buildPageData(?array $project): array
    {
        $payload = [
            'can_edit' => false,
            'error' => '',
            'root_ref' => '',
            'root_form_name' => '',
            'model_fqcn' => '',
            'model_file_path' => '',
            'fields' => [],
            'db_fields' => [],
            'search_filters_path' => '',
            'search_filters_notice' => '',
            'initial_config' => self::defaultInitialConfig(),
        ];

        if (!is_array($project)) {
            $payload['error'] = 'Project not found or invalid module parameter.';
            return $payload;
        }

        $manifest = is_array($project['manifest_data'] ?? null) ? $project['manifest_data'] : [];
        if (empty($manifest)) {
            $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
            if ($manifestPath !== '' && is_file($manifestPath)) {
                $manifest = ManifestService::readManifest($manifestPath) ?? [];
            }
        }
        if (empty($manifest)) {
            $payload['error'] = 'Manifest file is missing or invalid.';
            return $payload;
        }

        $rootNode = ManifestService::extractRootNode($manifest);
        if (!is_array($rootNode)) {
            $payload['error'] = 'Unable to resolve root form from manifest.';
            return $payload;
        }

        $rootRef = basename(trim((string) ($rootNode['ref'] ?? '')));
        if ($rootRef === '') {
            $payload['error'] = 'Root form ref is missing in manifest.';
            return $payload;
        }

        $rootFormName = trim((string) pathinfo($rootRef, PATHINFO_FILENAME));
        if ($rootFormName === '') {
            $payload['error'] = 'Invalid root form name.';
            return $payload;
        }

        $payload['root_ref'] = $rootRef;
        $payload['root_form_name'] = $rootFormName;
        self::hydrateInitialSearchConfig($payload, $project, $rootFormName);

        $modelInfo = DraftModelResolver::resolveModelInfoForRef($project, $rootRef);
        if (empty($modelInfo['success'])) {
            $payload['error'] = trim((string) ($modelInfo['error'] ?? 'Root model not found.'));
            return $payload;
        }

        $modelFilePath = trim((string) ($modelInfo['model_file_path'] ?? ''));
        $modelFqcn = trim((string) ($modelInfo['model_fqcn'] ?? ''));
        if ($modelFilePath === '' || !is_file($modelFilePath) || $modelFqcn === '') {
            $payload['error'] = 'Root model path is invalid.';
            return $payload;
        }

        try {
            if (!class_exists($modelFqcn, false)) {
                require_once $modelFilePath;
            }
            if (!class_exists($modelFqcn)) {
                $payload['error'] = 'Root model class cannot be loaded.';
                return $payload;
            }

            $model = new $modelFqcn();
            if (!method_exists($model, 'getRules')) {
                $payload['error'] = 'Root model does not expose getRules().';
                return $payload;
            }

            $rules = $model->getRules();
            if (!is_array($rules)) {
                $rules = [];
            }

            $primaryKey = method_exists($model, 'getPrimaryKey')
                ? trim((string) $model->getPrimaryKey())
                : '';

            $fields = [];
            $seen = [];
            foreach ($rules as $fieldName => $ruleRaw) {
                $name = trim((string) $fieldName);
                if ($name === '' || !self::isSafeIdentifier($name)) {
                    continue;
                }
                if ($primaryKey !== '' && strcasecmp($name, $primaryKey) === 0) {
                    continue;
                }

                $nameLower = strtolower($name);
                if (isset($seen[$nameLower])) {
                    continue;
                }

                $rule = is_array($ruleRaw) ? $ruleRaw : [];
                $isVirtual = self::normalizeBool($rule['virtual'] ?? false);
                $isSqlEnabled = !array_key_exists('sql', $rule) || self::normalizeBool($rule['sql']);
                $isWithCount = !empty($rule['withCount']);
                if ($isVirtual || !$isSqlEnabled || $isWithCount) {
                    continue;
                }

                $label = trim((string) ($rule['label'] ?? ($rule['form-label'] ?? '')));
                if ($label === '') {
                    $label = ManifestService::toTitle($name);
                }
                if ($label === '') {
                    $label = $name;
                }

                $fields[] = [
                    'name' => $name,
                    'label' => $label,
                    'type' => trim((string) ($rule['type'] ?? 'string')),
                ];
                $seen[$nameLower] = true;
            }
            self::appendRelationshipQueryFields($fields, $seen, $rules);

            $payload['fields'] = $fields;
            $payload['db_fields'] = self::resolveDbFields($model);
            $payload['can_edit'] = true;
            $payload['model_fqcn'] = $modelFqcn;
            $payload['model_file_path'] = $modelFilePath;

            return $payload;
        } catch (\Throwable) {
            $payload['error'] = 'Error loading root model fields.';
            return $payload;
        }
    }

    /**
     * @return array<int,array{name:string,label:string,type:string,sanitize_type:string}>
     */
    private static function resolveDbFields(object $model): array
    {
        $tableName = method_exists($model, 'getTable')
            ? trim((string) $model->getTable())
            : '';
        if ($tableName === '') {
            return [];
        }

        $db = method_exists($model, 'getDb') ? $model->getDb() : null;
        if (!is_object($db) || !method_exists($db, 'describes')) {
            return [];
        }

        try {
            $describe = $db->describes($tableName, true);
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($describe)) {
            return [];
        }

        $result = [];
        $seen = [];
        $struct = is_array($describe['struct'] ?? null) ? $describe['struct'] : [];
        foreach ($struct as $fallbackName => $rowRaw) {
            $row = is_object($rowRaw) ? get_object_vars($rowRaw) : (is_array($rowRaw) ? $rowRaw : []);
            if (!is_array($row)) {
                continue;
            }

            $fieldName = trim((string) ($row['Field'] ?? $row['field'] ?? $row['name'] ?? $fallbackName));
            if ($fieldName === '') {
                continue;
            }

            $lowerName = strtolower($fieldName);
            if (isset($seen[$lowerName])) {
                continue;
            }

            $sqlType = trim((string) ($row['Type'] ?? $row['type'] ?? ($describe['fields'][$fieldName] ?? '')));
            $nullableRaw = strtoupper(trim((string) ($row['Null'] ?? $row['null'] ?? '')));
            $nullable = $nullableRaw === '' || $nullableRaw === 'YES';
            $key = strtoupper(trim((string) ($row['Key'] ?? $row['key'] ?? '')));
            $extra = strtolower(trim((string) ($row['Extra'] ?? $row['extra'] ?? '')));

            $result[] = self::buildDbFieldEntry($fieldName, $sqlType, $nullable, $key, $extra);
            $seen[$lowerName] = true;
        }

        if (empty($result) && is_array($describe['fields'] ?? null)) {
            foreach ($describe['fields'] as $fieldName => $fieldType) {
                $name = trim((string) $fieldName);
                if ($name === '') {
                    continue;
                }
                $lowerName = strtolower($name);
                if (isset($seen[$lowerName])) {
                    continue;
                }

                $sqlType = trim((string) $fieldType);
                $result[] = self::buildDbFieldEntry($name, $sqlType, true, '', '');
                $seen[$lowerName] = true;
            }
        }

        return $result;
    }

    /**
     * @return array{name:string,label:string,type:string,sanitize_type:string}
     */
    private static function buildDbFieldEntry(
        string $fieldName,
        string $sqlType,
        bool $nullable,
        string $key,
        string $extra
    ): array {
        $title = ManifestService::toTitle($fieldName);
        if ($title === '') {
            $title = $fieldName;
        }

        $meta = [];
        if ($sqlType !== '') {
            $meta[] = $sqlType;
        }
        if ($key !== '') {
            $meta[] = $key;
        }
        if ($extra !== '') {
            $meta[] = $extra;
        }
        $meta[] = $nullable ? 'NULL' : 'NOT NULL';

        $label = $title . ' (' . $fieldName . ')';
        if (!empty($meta)) {
            $label .= ' - ' . implode(', ', $meta);
        }

        return [
            'name' => $fieldName,
            'label' => $label,
            'type' => $sqlType !== '' ? $sqlType : 'string',
            'sanitize_type' => self::inferSanitizeTypeFromSqlType($fieldName, $sqlType),
        ];
    }

    private static function inferSanitizeTypeFromSqlType(string $fieldName, string $sqlType): string
    {
        $type = strtolower(trim($sqlType));
        $base = $type;
        if (preg_match('/^[a-z]+/', $type, $matches) === 1) {
            $base = $matches[0];
        }

        $fieldNameLower = strtolower(trim($fieldName));
        if ($base === 'tinyint' && preg_match('/^tinyint\s*\(\s*1\s*\)/', $type) === 1) {
            return 'bool';
        }

        if (in_array($base, ['int', 'integer', 'smallint', 'mediumint', 'bigint', 'tinyint', 'serial'], true)) {
            return 'int';
        }
        if (in_array($base, ['decimal', 'numeric', 'float', 'double', 'real'], true)) {
            return 'float';
        }
        if (in_array($base, ['bool', 'boolean', 'bit'], true)) {
            return 'bool';
        }
        if ($base === 'uuid') {
            return 'uuid';
        }

        if (str_contains($fieldNameLower, 'uuid') || preg_match('/^char\s*\(\s*36\s*\)/', $type) === 1) {
            return 'uuid';
        }
        if (str_contains($fieldNameLower, 'slug')) {
            return 'slug';
        }

        return 'string';
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
     * @param array<int,array{name:string,label:string,type:string}> $fields
     * @param array<string,bool> $seen
     * @param array<string,mixed> $rules
     */
    private static function appendRelationshipQueryFields(array &$fields, array &$seen, array $rules): void
    {
        foreach ($rules as $ruleRaw) {
            $rule = is_array($ruleRaw) ? $ruleRaw : [];
            $relationship = is_array($rule['relationship'] ?? null) ? $rule['relationship'] : [];
            if (empty($relationship)) {
                continue;
            }

            $relationshipType = strtolower(trim((string) ($relationship['type'] ?? '')));
            if (!in_array($relationshipType, ['belongsto', 'hasone', 'hasmany'], true)) {
                continue;
            }

            $alias = trim((string) ($relationship['alias'] ?? ''));
            if (!self::isSafeIdentifier($alias)) {
                continue;
            }

            $relatedModelClass = trim((string) ($relationship['related_model'] ?? ''));
            if ($relatedModelClass === '' || !class_exists($relatedModelClass)) {
                continue;
            }

            try {
                $relatedModel = new $relatedModelClass();
            } catch (\Throwable) {
                continue;
            }
            if (!method_exists($relatedModel, 'getRules')) {
                continue;
            }

            $relatedRules = $relatedModel->getRules();
            if (!is_array($relatedRules) || empty($relatedRules)) {
                continue;
            }

            $relationshipTitle = ManifestService::toTitle($alias);
            if ($relationshipTitle === '') {
                $relationshipTitle = $alias;
            }

            foreach ($relatedRules as $relatedFieldName => $relatedRuleRaw) {
                $relatedName = trim((string) $relatedFieldName);
                if (!self::isSafeIdentifier($relatedName)) {
                    continue;
                }

                $relatedRule = is_array($relatedRuleRaw) ? $relatedRuleRaw : [];
                $isVirtual = self::normalizeBool($relatedRule['virtual'] ?? false);
                $isSqlEnabled = !array_key_exists('sql', $relatedRule) || self::normalizeBool($relatedRule['sql']);
                $isWithCount = !empty($relatedRule['withCount']);
                if ($isVirtual || !$isSqlEnabled || $isWithCount) {
                    continue;
                }

                $queryFieldName = $alias . '.' . $relatedName;
                $queryFieldKey = strtolower($queryFieldName);
                if (isset($seen[$queryFieldKey])) {
                    continue;
                }

                $relatedLabel = trim((string) ($relatedRule['label'] ?? ($relatedRule['form-label'] ?? '')));
                if ($relatedLabel === '') {
                    $relatedLabel = ManifestService::toTitle($relatedName);
                }
                if ($relatedLabel === '') {
                    $relatedLabel = $relatedName;
                }

                $fields[] = [
                    'name' => $queryFieldName,
                    'label' => $relationshipTitle . ' -> ' . $relatedLabel,
                    'type' => trim((string) ($relatedRule['type'] ?? 'string')),
                ];
                $seen[$queryFieldKey] = true;
            }
        }
    }

    private static function isSafeIdentifier(string $value): bool
    {
        return $value !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    /**
     * @return array{
     *   search_mode:string,
     *   auto_buttons:bool,
     *   url_params:array<int,array<string,mixed>>,
     *   filters:array<int,array<string,mixed>>
     * }
     */
    private static function defaultInitialConfig(): array
    {
        return [
            'search_mode' => 'submit',
            'auto_buttons' => true,
            'url_params' => [],
            'filters' => [],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $project
     */
    private static function hydrateInitialSearchConfig(array &$payload, array $project, string $rootFormName): void
    {
        $projectDir = self::resolveProjectDir($project);
        if ($projectDir === '') {
            return;
        }

        $searchFiltersPath = $projectDir . '/search_filters.json';
        $payload['search_filters_path'] = $searchFiltersPath;
        if (!is_file($searchFiltersPath)) {
            return;
        }

        $parser = new SearchFiltersConfigParser();
        try {
            $configByForm = $parser->parseFile($searchFiltersPath);
            $resolvedConfig = self::resolveConfigForForm($configByForm, $rootFormName);
            $payload['initial_config'] = self::normalizeInitialConfig($resolvedConfig);

            $warnings = $parser->getWarnings();
            if (!empty($warnings)) {
                $payload['search_filters_notice'] = implode(' ', array_map(static function ($warning): string {
                    return trim((string) $warning);
                }, $warnings));
            }
        } catch (\Throwable $e) {
            $payload['search_filters_notice'] = trim((string) $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $project
     */
    private static function resolveProjectDir(array $project): string
    {
        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath !== '' && is_file($manifestPath)) {
            return rtrim((string) dirname($manifestPath), '/\\');
        }

        $moduleName = trim((string) ($project['module_name'] ?? ''));
        if ($moduleName !== '') {
            $candidate = rtrim((string) LOCAL_DIR, '/\\') . '/Modules/' . $moduleName . '/Project';
            if (is_dir($candidate)) {
                return rtrim($candidate, '/\\');
            }
        }

        return '';
    }

    /**
     * @param array<string,array<string,mixed>> $configByForm
     * @return array<string,mixed>
     */
    private static function resolveConfigForForm(array $configByForm, string $formName): array
    {
        $formName = trim($formName);
        if ($formName !== '' && isset($configByForm[$formName]) && is_array($configByForm[$formName])) {
            return $configByForm[$formName];
        }

        $slug = ProjectNaming::toActionSlug($formName);
        if ($slug !== '' && isset($configByForm[$slug]) && is_array($configByForm[$slug])) {
            return $configByForm[$slug];
        }

        if (isset($configByForm['*']) && is_array($configByForm['*'])) {
            return $configByForm['*'];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $config
     * @return array{
     *   search_mode:string,
     *   auto_buttons:bool,
     *   url_params:array<int,array<string,mixed>>,
     *   filters:array<int,array<string,mixed>>
     * }
     */
    private static function normalizeInitialConfig(array $config): array
    {
        $default = self::defaultInitialConfig();

        $default['search_mode'] = 'submit';
        $default['auto_buttons'] = self::normalizeBool($config['auto_buttons'] ?? true);

        $rawFilters = is_array($config['filters'] ?? null) ? $config['filters'] : [];
        $filters = [];
        foreach ($rawFilters as $filter) {
            if (is_array($filter)) {
                $filters[] = $filter;
            }
        }
        $default['filters'] = $filters;

        $rawUrlParams = is_array($config['url_params'] ?? ($config['urlParams'] ?? null))
            ? ($config['url_params'] ?? $config['urlParams'])
            : [];
        $urlParams = [];
        foreach ($rawUrlParams as $urlParam) {
            if (is_array($urlParam)) {
                $urlParams[] = $urlParam;
            }
        }
        $default['url_params'] = $urlParams;

        return $default;
    }
}
