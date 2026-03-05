<?php

namespace Modules\Projects;

use App\Config;
use App\Get;
use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

class ManifestService
{
    /**
     * Read and decode a manifest file.
     */
    public static function readManifest(string $manifestPath): ?array
    {
        $json = @file_get_contents($manifestPath);
        if (!is_string($json) || trim($json) === '') {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * Write manifest to disk (pretty JSON + trailing newline).
     */
    public static function writeManifest(string $manifestPath, array $manifest): bool
    {
        if (!is_writable($manifestPath) && !is_writable(dirname($manifestPath))) {
            return false;
        }

        try {
            $json = json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $e) {
            return false;
        }

        return file_put_contents($manifestPath, $json . "\n") !== false;
    }

    /**
     * Detect manifest pattern:
     *   'A' = root ref at top level (e.g. LongitudinalDatabase)
     *   'B' = first form is root (e.g. ProjectsExtensionTest)
     */
    public static function detectPattern(array $manifest): string
    {
        $rootRef = trim((string) ($manifest['ref'] ?? ''));
        return $rootRef !== '' ? 'A' : 'B';
    }

    /**
     * Merge the incoming forms tree (from the UI) into the existing manifest.
     * Preserves all non-form keys.
     */
    public static function mergeFormsTree(array $existingManifest, array $incomingRoot): array
    {
        $result = $existingManifest;
        $pattern = self::detectPattern($existingManifest);

        if ($pattern === 'A') {
            // Pattern A: root ref is at manifest top level, forms are children
            $result['ref'] = $incomingRoot['ref'] ?? ($result['ref'] ?? '');

            // Copy root-level form properties
            $rootProps = [
                'max_records', 'viewSingleRecord', 'editDisplay', 'listDisplay',
                'viewDisplay', 'showIf', 'showIfMessage', 'showSearch',
                'softDelete', 'allowDeleteRecord', 'allowEdit',
                'defaultOrderEnabled', 'defaultOrderField', 'defaultOrderDirection',
                'soft_delete', 'allow_delete_record', 'allow_edit',
                'default_order_enabled', 'default_order_field', 'default_order_direction',
                'existingTable', 'existing_table',
            ];
            foreach ($rootProps as $prop) {
                if (array_key_exists($prop, $incomingRoot)) {
                    $result[$prop] = $incomingRoot[$prop];
                }
            }

            // Replace the forms array
            $incomingForms = is_array($incomingRoot['forms'] ?? null) ? $incomingRoot['forms'] : [];
            $result['forms'] = array_map([self::class, 'sanitizeFormNode'], $incomingForms);
        } else {
            // Pattern B: root is forms[0], everything nested inside
            $rootNode = self::sanitizeFormNode($incomingRoot);
            $result['forms'] = [$rootNode];
        }

        return $result;
    }

    /**
     * Sanitize a form node for writing back to manifest.
     * Removes display-only fields (form_name, title) and recurses into children/forms.
     */
    public static function sanitizeFormNode(array $node): array
    {
        $result = [];
        $displayOnlyKeys = ['form_name', 'title', 'children'];

        foreach ($node as $key => $value) {
            if (in_array($key, $displayOnlyKeys, true) || str_starts_with((string) $key, 'integrity_')) {
                continue;
            }
            $result[$key] = $value;
        }

        // Recurse into forms
        if (isset($result['forms']) && is_array($result['forms'])) {
            if (empty($result['forms'])) {
                unset($result['forms']);
            } else {
                $result['forms'] = array_map([self::class, 'sanitizeFormNode'], $result['forms']);
            }
        }

        return $result;
    }

    // ─── Tree building (manifest → UI) ──────────────────────────────────

    /**
     * Build the full forms tree for the UI from a manifest.
     * Unlike the old buildFormNode(), this preserves ALL form properties.
     *
     * @return array{main: array, children: array}
     */
    public static function buildFormsTree(array $manifest, string $projectDir): array
    {
        $rootNode = self::extractRootNode($manifest);
        if (!is_array($rootNode)) {
            return self::defaultFormsTree();
        }

        $main = self::buildFormNodeForUI($rootNode, $projectDir);
        $children = $main['children'] ?? [];
        unset($main['children']);

        return [
            'main' => $main,
            'children' => is_array($children) ? $children : [],
        ];
    }

    /**
     * Extract the root form node from the manifest.
     * Handles both Pattern A and Pattern B.
     */
    public static function extractRootNode(array $manifest): ?array
    {
        $rootRef = trim((string) ($manifest['ref'] ?? ''));

        if ($rootRef !== '') {
            // Pattern A: root ref at top level
            $rootNode = [
                'ref' => $rootRef,
                'max_records' => $manifest['max_records'] ?? 1,
                'forms' => is_array($manifest['forms'] ?? null) ? $manifest['forms'] : [],
            ];
            // Copy other root-level form props
            foreach ([
                'viewSingleRecord', 'editDisplay', 'listDisplay', 'viewDisplay',
                'showIf', 'showIfMessage', 'showSearch',
                'softDelete', 'allowDeleteRecord', 'allowEdit',
                'defaultOrderEnabled', 'defaultOrderField', 'defaultOrderDirection',
                'soft_delete', 'allow_delete_record', 'allow_edit',
                'default_order_enabled', 'default_order_field', 'default_order_direction',
                'existingTable', 'existing_table',
            ] as $prop) {
                if (array_key_exists($prop, $manifest)) {
                    $rootNode[$prop] = $manifest[$prop];
                }
            }
            return $rootNode;
        }

        // Pattern B: first form is root
        $forms = is_array($manifest['forms'] ?? null) ? $manifest['forms'] : [];
        if (empty($forms) || !is_array($forms[0])) {
            return null;
        }

        $rootNode = $forms[0];

        // If there are sibling forms (forms[1..n]), merge them as children of forms[0]
        if (count($forms) > 1) {
            $existingChildren = is_array($rootNode['forms'] ?? null) ? $rootNode['forms'] : [];
            for ($i = 1, $count = count($forms); $i < $count; $i++) {
                if (is_array($forms[$i])) {
                    $existingChildren[] = $forms[$i];
                }
            }
            $rootNode['forms'] = $existingChildren;
        }

        return $rootNode;
    }

    /**
     * Build a single form node for the UI. Preserves all manifest properties
     * and adds computed display fields (form_name, title).
     */
    private static function buildFormNodeForUI(array $node, string $projectDir): array
    {
        $ref = trim((string) ($node['ref'] ?? ''));
        $refBase = basename($ref);
        $formName = trim((string) pathinfo($refBase, PATHINFO_FILENAME));
        if ($formName === '') {
            $formName = 'Form';
        }

        // Process children recursively
        $children = [];
        $rawChildren = is_array($node['forms'] ?? null) ? $node['forms'] : [];
        foreach ($rawChildren as $childNode) {
            if (!is_array($childNode)) {
                continue;
            }
            $children[] = self::buildFormNodeForUI($childNode, $projectDir);
        }

        // Start with all original properties (except forms, which becomes children)
        $result = [];
        foreach ($node as $key => $value) {
            if ($key === 'forms') {
                continue;
            }
            $result[$key] = $value;
        }

        // Normalize specific fields
        $result['ref'] = $refBase;
        $result['max_records'] = self::normalizeMaxRecords($node['max_records'] ?? 'n');

        // Add computed display fields
        $result['form_name'] = $formName;
        $result['title'] = self::resolveFormTitle($projectDir, $refBase, $formName);

        // Add children
        $result['children'] = $children;

        return $result;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public static function defaultFormsTree(): array
    {
        return [
            'main' => [
                'form_name' => '',
                'ref' => '',
                'title' => 'Project Main Form',
                'max_records' => '1',
            ],
            'children' => [],
        ];
    }

    public static function normalizeMaxRecords(mixed $value): string
    {
        if (is_int($value)) {
            return $value <= 0 ? 'n' : (string) $value;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '' || $normalized === 'many' || $normalized === 'multiple') {
            return 'n';
        }
        if ($normalized === 'one' || $normalized === 'single') {
            return '1';
        }
        if (ctype_digit($normalized)) {
            return ((int) $normalized <= 0) ? 'n' : (string) ((int) $normalized);
        }
        if ($normalized === 'n' || $normalized === 'unlimited') {
            return $normalized;
        }

        return 'n';
    }

    public static function resolveFormTitle(string $projectDir, string $schemaRef, string $fallbackFormName): string
    {
        $fallback = self::toTitle($fallbackFormName);
        if ($fallback === '') {
            $fallback = $fallbackFormName;
        }

        if ($schemaRef === '' || $projectDir === '') {
            return $fallback;
        }

        $schemaPath = $projectDir . '/' . basename($schemaRef);
        if (!is_file($schemaPath)) {
            return $fallback;
        }

        $schemaJson = @file_get_contents($schemaPath);
        if (!is_string($schemaJson) || trim($schemaJson) === '') {
            return $fallback;
        }

        try {
            $schema = json_decode($schemaJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return $fallback;
        }

        if (!is_array($schema)) {
            return $fallback;
        }

        $title = trim((string) ($schema['_name'] ?? ''));
        return $title !== '' ? $title : $fallback;
    }

    public static function toTitle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);
        $value = str_replace(['_', '-'], ' ', (string) $value);
        $value = preg_replace('/\\s+/', ' ', (string) $value);

        return ucwords(strtolower(trim((string) $value)));
    }

    /**
     * Discover manifest path for a module directory.
     */
    public static function findManifestPath(string $moduleDir): ?string
    {
        $candidates = [
            $moduleDir . '/Project/manifest.json',
            $moduleDir . '/project/manifest.json',
            $moduleDir . '/Projects/manifest.json',
            $moduleDir . '/projects/manifest.json',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    // ─── Asset generation (JSON schema + Model PHP + DB table) ──────────

    /**
     * Ensure all forms in the manifest have their JSON schema, Model PHP, and DB table.
     * Skips the root form (assumed to be manually created by the developer).
     *
     * @return array{
     *   created_schemas: string[],
     *   created_models: string[],
     *   created_tables: string[],
     *   created_schema_paths: string[],
     *   created_model_paths: string[],
     *   table_rollback_candidates: array<int,array{
     *     model_class: string,
     *     model_fqcn: string,
     *     model_file_path: string,
     *     table_name: string,
     *     table_existence_known_before: bool,
     *     table_existed_before: bool,
     *     row_count_before: ?int
     *   }>,
     *   errors: string[]
     * }
     */
    public static function ensureFormAssets(
        string $manifestPath,
        string $moduleName,
        array $manifest,
        array $newChildRefs = []
    ): array
    {
        $projectDir = dirname($manifestPath);
        $moduleDir = dirname($projectDir);
        $dbType = trim((string) ($manifest['db'] ?? 'db'));
        if ($dbType === '') {
            $dbType = 'db';
        }

        [$modelDir, $modelNamespace] = self::resolveModelLocation($manifestPath, $moduleDir, $moduleName);

        $childForms = self::collectChildFormsFromManifest($manifest);
        $newRefLookup = [];
        foreach ($newChildRefs as $newRef) {
            if (!is_string($newRef)) {
                continue;
            }
            $newRefBase = basename(trim($newRef));
            if ($newRefBase !== '') {
                $newRefLookup[$newRefBase] = true;
            }
        }

        $results = [
            'created_schemas' => [],
            'created_models' => [],
            'created_tables' => [],
            'created_schema_paths' => [],
            'created_model_paths' => [],
            'table_rollback_candidates' => [],
            'errors' => [],
        ];
        $reservedClassNames = [];
        $reservedTableNames = [];

        foreach ($childForms as $childMeta) {
            $refBase = basename((string) ($childMeta['ref'] ?? ''));
            $formName = trim((string) ($childMeta['form_name'] ?? pathinfo($refBase, PATHINFO_FILENAME)));
            $parentFormName = trim((string) ($childMeta['parent_form_name'] ?? ''));
            if ($formName === '') {
                continue;
            }
            $isNewForm = isset($newRefLookup[$refBase]);

            $formTitle = self::toTitle($formName);
            if ($formTitle === '') {
                $formTitle = $formName;
            }

            // 1. Ensure JSON schema exists
            $schemaPath = $projectDir . '/' . $refBase;
            if (!is_file($schemaPath)) {
                if (self::generateDefaultSchema($projectDir, $refBase, $formTitle, $parentFormName)) {
                    $results['created_schemas'][] = $refBase;
                    $results['created_schema_paths'][] = $schemaPath;
                } else {
                    $results['errors'][] = "Failed to create schema: " . $refBase;
                    continue;
                }
            }

            // 2. Ensure Model PHP exists
            $modelClassName = $formName . 'Model';
            $modelFilePath = $modelDir . '/' . $modelClassName . '.php';
            $modelFqcn = $modelNamespace . '\\' . $modelClassName;
            $tableName = ProjectFormAssetValidationService::buildTableName($moduleName, $formName, $dbType);
            $modelCreated = false;

            if ($isNewForm) {
                $validation = ProjectFormAssetValidationService::validateNewFormAsset(
                    $formName,
                    $moduleName,
                    $dbType,
                    $modelDir,
                    $modelNamespace,
                    $reservedClassNames,
                    $reservedTableNames
                );
                if (!$validation['success']) {
                    $results['errors'][] = $validation['error'];
                    continue;
                }

                $modelClassName = $validation['model_class_name'];
                $modelFilePath = $validation['model_file_path'];
                $modelFqcn = $validation['model_fqcn'];
                $tableName = $validation['table_name'];
            }

            if (!is_file($modelFilePath)) {
                if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $modelClassName) !== 1) {
                    $results['errors'][] = "Invalid model class name '{$modelClassName}' for form '{$formName}'.";
                    continue;
                }
                if ($tableName === '') {
                    $results['errors'][] = "Failed to generate table name for form '{$formName}'.";
                    continue;
                }

                $created = self::generateDefaultModel(
                    $modelDir,
                    $modelNamespace,
                    $modelClassName,
                    $tableName,
                    $dbType,
                    $parentFormName
                );
                if ($created !== false) {
                    $results['created_models'][] = $modelClassName;
                    $results['created_model_paths'][] = $modelFilePath;
                    $modelCreated = true;
                } else {
                    $results['errors'][] = "Failed to create model: {$modelClassName}";
                }
            }

            // 3. Build DB table (for newly created models)
            if ($modelCreated && is_file($modelFilePath)) {
                $tableResult = ProjectTableService::buildDatabaseTable($modelFilePath, $modelFqcn);

                if ($tableResult['table_name'] !== '') {
                    $results['table_rollback_candidates'][] = [
                        'model_class' => $modelClassName,
                        'model_fqcn' => $modelFqcn,
                        'model_file_path' => $modelFilePath,
                        'table_name' => $tableResult['table_name'],
                        'table_existence_known_before' => $tableResult['table_existence_known'],
                        'table_existed_before' => $tableResult['table_existed_before'],
                        'row_count_before' => $tableResult['row_count_before'],
                    ];
                }

                if ($tableResult['success']) {
                    $results['created_tables'][] = $tableResult['table_name'] !== '' ? $tableResult['table_name'] : $tableName;
                } else {
                    $results['errors'][] = "Failed to build table for {$modelClassName}: " . ($tableResult['error'] ?? 'unknown');
                }
            }
        }

        return $results;
    }

    /**
     * Roll back generated assets created by ensureFormAssets().
     *
     * @param array{
     *   created_schema_paths?: string[],
     *   created_model_paths?: string[],
     *   table_rollback_candidates?: array<int,array{
     *     model_fqcn?: string,
     *     model_file_path?: string,
     *     table_name?: string,
     *     table_existence_known_before?: bool,
     *     table_existed_before?: bool
     *   }>
     * } $assetResults
     * @return array{
     *   deleted_schemas: string[],
     *   deleted_models: string[],
     *   dropped_tables: string[],
     *   warnings: string[]
     * }
     */
    public static function rollbackFormAssets(array $assetResults): array
    {
        $rollback = [
            'deleted_schemas' => [],
            'deleted_models' => [],
            'dropped_tables' => [],
            'warnings' => [],
        ];

        // Rollback tables before deleting model files used to instantiate classes.
        $tableCandidates = is_array($assetResults['table_rollback_candidates'] ?? null) ? $assetResults['table_rollback_candidates'] : [];
        $tableRollback = ProjectTableService::rollbackTables($tableCandidates);
        if (!empty($tableRollback['dropped_tables'])) {
            $rollback['dropped_tables'] = array_merge($rollback['dropped_tables'], $tableRollback['dropped_tables']);
        }
        if (!empty($tableRollback['warnings'])) {
            $rollback['warnings'] = array_merge($rollback['warnings'], $tableRollback['warnings']);
        }

        $modelPaths = is_array($assetResults['created_model_paths'] ?? null) ? $assetResults['created_model_paths'] : [];
        foreach ($modelPaths as $modelPath) {
            if (!is_string($modelPath) || trim($modelPath) === '') {
                continue;
            }
            if (!is_file($modelPath)) {
                continue;
            }
            if (!@unlink($modelPath)) {
                $rollback['warnings'][] = 'Failed to delete model file: ' . $modelPath;
                continue;
            }
            $rollback['deleted_models'][] = $modelPath;
        }

        $schemaPaths = is_array($assetResults['created_schema_paths'] ?? null) ? $assetResults['created_schema_paths'] : [];
        foreach ($schemaPaths as $schemaPath) {
            if (!is_string($schemaPath) || trim($schemaPath) === '') {
                continue;
            }
            if (!is_file($schemaPath)) {
                continue;
            }
            if (!@unlink($schemaPath)) {
                $rollback['warnings'][] = 'Failed to delete schema file: ' . $schemaPath;
                continue;
            }
            $rollback['deleted_schemas'][] = $schemaPath;
        }

        return $rollback;
    }

    /**
     * @return string[]
     */
    public static function collectChildRefsFromManifest(array $manifest): array
    {
        $refs = [];
        foreach (self::collectChildFormsFromManifest($manifest) as $childMeta) {
            $ref = trim((string) ($childMeta['ref'] ?? ''));
            if ($ref === '') {
                continue;
            }
            $refs[] = $ref;
        }

        return $refs;
    }

    /**
     * @return array<int,array{ref:string,form_name:string,parent_form_name:string}>
     */
    private static function collectChildFormsFromManifest(array $manifest): array
    {
        $rootNode = self::extractRootNode($manifest);
        if (!is_array($rootNode)) {
            return [];
        }

        $rootRefBase = basename(trim((string) ($rootNode['ref'] ?? '')));
        $rootFormName = trim((string) pathinfo($rootRefBase, PATHINFO_FILENAME));

        $forms = [];
        $children = is_array($rootNode['forms'] ?? null) ? $rootNode['forms'] : [];
        foreach ($children as $childNode) {
            if (!is_array($childNode)) {
                continue;
            }
            self::collectFormMetaRecursive($childNode, $rootFormName, $forms);
        }

        return $forms;
    }

    /**
     * @param array<int,array{ref:string,form_name:string,parent_form_name:string}> $forms
     */
    private static function collectFormMetaRecursive(array $node, string $parentFormName, array &$forms): void
    {
        $ref = trim((string) ($node['ref'] ?? ''));
        $refBase = basename($ref);
        $formName = trim((string) pathinfo($refBase, PATHINFO_FILENAME));

        if ($ref !== '' && $formName !== '') {
            $forms[] = [
                'ref' => $ref,
                'form_name' => $formName,
                'parent_form_name' => $parentFormName,
            ];
        }

        $nextParentFormName = $formName !== '' ? $formName : $parentFormName;
        $children = is_array($node['forms'] ?? null) ? $node['forms'] : [];
        foreach ($children as $childNode) {
            if (!is_array($childNode)) {
                continue;
            }
            self::collectFormMetaRecursive($childNode, $nextParentFormName, $forms);
        }
    }

    /**
     * Determine where Model PHP files should go and with what namespace.
     *
     * Rules:
     * - If manifest is in "Project/": Models in Project/Models/, namespace ...\Project\Models
     * - Otherwise: Models at module root, namespace = module root namespace
     *
     * @return array{0: string, 1: string} [directory, namespace]
     */
    public static function resolveModelLocation(string $manifestPath, string $moduleDir, string $moduleName): array
    {
        $projectDir = dirname($manifestPath);
        $projectSubDir = basename($projectDir);

        if (strtolower($projectSubDir) === 'project') {
            $modelDir = $projectDir . '/Models';
            $namespace = 'Local\\Modules\\' . $moduleName . '\\Project\\Models';
        } else {
            // Models at module root (extension checks moduleNamespace\FormNameModel)
            $modelDir = $moduleDir;
            $namespace = 'Local\\Modules\\' . $moduleName;
        }

        return [$modelDir, $namespace];
    }

    /**
     * Create a minimal JSON schema file for a new form.
     */
    public static function generateDefaultSchema(string $projectDir, string $ref, string $formTitle, string $parentFormName = ''): bool
    {
        $schemaPath = $projectDir . '/' . basename($ref);
        if (is_file($schemaPath)) {
            return true; // already exists
        }

        $fields = self::buildDefaultSchemaFields($parentFormName, $projectDir);
        $schema = [
            '_version' => '1.0',
            '_name' => $formTitle,
            'model' => [
                'fields' => $fields,
            ],
        ];

        try {
            $json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return false;
        }

        return file_put_contents($schemaPath, $json . "\n") !== false;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function buildDefaultSchemaFields(string $parentFormName, string $projectDir): array
    {
        $parentFormName = trim($parentFormName);
        if ($parentFormName === '') {
            return [];
        }

        $parentFkField = ProjectNaming::foreignKeyFieldForParentForm($parentFormName);
        $rootIdField = ProjectNaming::rootIdField();

        $fields = [
            [
                'name' => $parentFkField,
                'method' => 'int',
                'label' => ProjectNaming::toTitle($parentFormName) . ' ID',
                'formType' => 'hidden',
                'required' => true,
                'hideFromList' => true,
                'formParams' => ['readonly' => true],
                'builderLocked' => true,
            ],
        ];

        $fields[] = [
            'name' => $rootIdField,
            'method' => 'int',
            'label' => 'Root ID',
            'formType' => 'hidden',
            'required' => true,
            'hideFromList' => true,
            'formParams' => ['readonly' => true],
            'builderLocked' => true,
        ];

        return array_merge($fields, self::buildDefaultAuditSchemaFields($projectDir));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function buildDefaultAuditSchemaFields(string $projectDir): array
    {
        $modulePage = self::resolveModulePageFromProjectDir($projectDir);
        return [
            [
                'name' => 'created_at',
                'method' => 'created_at',
                'label' => 'Created At',
                'formType' => 'datetime',
                'hideFromList' => true,
                'hideFromEdit' => true,
                'formParams' => ['readonly' => true],
                'builderLocked' => true,
            ],
            [
                'name' => 'updated_at',
                'method' => 'updated_at',
                'label' => 'Updated At',
                'formType' => 'datetime',
                'hideFromList' => true,
                'hideFromEdit' => true,
                'formParams' => ['readonly' => true],
                'builderLocked' => true,
            ],
            [
                'name' => 'created_by',
                'method' => 'int',
                'label' => 'Created by ',
                'formType' => 'milkSelect',
                'hideFromList' => true,
                'hideFromEdit' => true,
                'belongsTo' => [
                    'alias' => 'created_user',
                    'related_model' => 'Modules\\Auth\\UserModel',
                    'related_key' => 'id',
                ],
                'apiUrl' => [
                    'url' => '?page=' . $modulePage . '&action=related-search-field&f=created_by',
                    'display_field' => 'username',
                ],
                'builderLocked' => true,
            ],
            [
                'name' => 'updated_by',
                'method' => 'int',
                'label' => 'Updated by ',
                'formType' => 'milkSelect',
                'hideFromList' => true,
                'hideFromEdit' => true,
                'belongsTo' => [
                    'alias' => 'updated_user',
                    'related_model' => 'Modules\\Auth\\UserModel',
                    'related_key' => 'id',
                ],
                'apiUrl' => [
                    'url' => '?page=' . $modulePage . '&action=related-search-field&f=updated_by',
                    'display_field' => 'username',
                ],
                'builderLocked' => true,
            ],
            [
                'name' => 'deleted_at',
                'method' => 'datetime',
                'label' => 'Deleted At',
                'formType' => 'hidden',
                'hideFromList' => true,
                'hideFromEdit' => true,
                'formParams' => ['readonly' => true],
                'builderLocked' => true,
            ],
            [
                'name' => 'deleted_by',
                'method' => 'int',
                'label' => 'Deleted By',
                'formType' => 'hidden',
                'hideFromList' => true,
                'hideFromEdit' => true,
                'formParams' => ['readonly' => true],
                'builderLocked' => true,
            ],
        ];
    }

    private static function resolveModulePageFromProjectDir(string $projectDir): string
    {
        $moduleDir = dirname(rtrim($projectDir, '/\\'));
        $moduleName = basename($moduleDir);
        $modulePage = ProjectNaming::toActionSlug($moduleName);
        $modulePage = trim($modulePage);
        return $modulePage !== '' ? $modulePage : 'home';
    }

    /**
     * Generate a default Model PHP file for a new form.
     *
     * @return string|false Path of created file, or false on failure
     */
    public static function generateDefaultModel(
        string $modelDir,
        string $namespace,
        string $modelClassName,
        string $tableName,
        string $dbType,
        string $parentFormName = ''
    ): string|false {
        $className = trim($modelClassName);
        if ($className === '') {
            return false;
        }
        $filePath = $modelDir . '/' . $className . '.php';

        if (is_file($filePath)) {
            return $filePath; // already exists
        }

        // Ensure directory exists
        if (!is_dir($modelDir)) {
            if (!@mkdir($modelDir, 0755, true)) {
                return false;
            }
        }

        $systemFieldsPhp = self::buildDefaultModelSystemFieldsPhp($parentFormName);
        $tableRuleName = self::escapePhpSingleQuoted(self::toTablePlaceholder($tableName, $dbType));
        $dbTypeLiteral = self::escapePhpSingleQuoted($dbType);

        $php = <<<PHP
<?php
namespace {$namespace};

use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

class {$className} extends AbstractModel
{
    protected function configure(\$rule): void
    {
        \$rule->table('{$tableRuleName}')
            ->db('{$dbTypeLiteral}')
            ->id('id')
{$systemFieldsPhp}
            ->extensions(['Projects']);
    }
}
PHP;

        if (file_put_contents($filePath, $php . "\n") === false) {
            return false;
        }

        return $filePath;
    }

    private static function buildDefaultModelSystemFieldsPhp(string $parentFormName): string
    {
        $parentFormName = trim($parentFormName);
        $segments = [];

        if ($parentFormName !== '') {
            $parentFkField = ProjectNaming::foreignKeyFieldForParentForm($parentFormName);
            $parentLabel = self::escapePhpSingleQuoted(ProjectNaming::toTitle($parentFormName) . ' ID');
            $segments[] = "            ->int('{$parentFkField}')\n"
                . "                ->label('{$parentLabel}')\n"
                . "                ->hide()\n"
                . "                ->formParams(['readonly' => true])\n"
                . "                ->required()";
            $rootIdField = ProjectNaming::rootIdField();
            $segments[] = "            ->int('{$rootIdField}')\n"
                . "                ->label('Root ID')\n"
                . "                ->hide()\n"
                . "                ->formParams(['readonly' => true])\n"
                . "                ->required()";
        }

        $segments[] = "            ->created_at('created_at')\n"
            . "                ->hideFromList()\n"
            . "                ->hideFromEdit()";
        $segments[] = "            ->updated_at('updated_at')\n"
            . "                ->hideFromList()\n"
            . "                ->hideFromEdit()";
        $segments[] = "            ->created_by('created_by')\n"
            . "                ->hideFromList()\n"
            . "                ->hideFromEdit()";
        $segments[] = "            ->updated_by('updated_by')\n"
            . "                ->hideFromList()\n"
            . "                ->hideFromEdit()";
        $segments[] = "            ->datetime('deleted_at')\n"
            . "                ->formType('hidden')\n"
            . "                ->hideFromList()\n"
            . "                ->hideFromEdit()";
        $segments[] = "            ->int('deleted_by')\n"
            . "                ->formType('hidden')\n"
            . "                ->hideFromList()\n"
            . "                ->hideFromEdit()";

        return implode("\n", $segments) . "\n";
    }

    private static function toTablePlaceholder(string $tableName, string $dbType): string
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return '';
        }

        $tableName = (string) preg_replace('/^#__/', '', $tableName);
        $connectionName = self::normalizeConnectionName($dbType);
        $prefixCandidates = [];

        $suffix = '';
        if (preg_match('/^db([0-9]+)$/', $connectionName, $matches) === 1) {
            $suffix = (string) ($matches[1] ?? '');
        }
        $configPrefix = trim((string) Config::get('prefix' . $suffix, ''));
        if ($configPrefix !== '') {
            $prefixCandidates[] = $configPrefix;
        }

        $db = self::resolveDbConnection($connectionName);
        if (is_object($db) && property_exists($db, 'prefix')) {
            $dbPrefix = trim((string) $db->prefix);
            if ($dbPrefix !== '') {
                $prefixCandidates[] = $dbPrefix;
            }
        }

        $prefixCandidates = array_values(array_unique($prefixCandidates));
        foreach ($prefixCandidates as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix === '') {
                continue;
            }

            $prefixToken = strtolower($prefix . '_');
            if (str_starts_with(strtolower($tableName), $prefixToken)) {
                $tableName = (string) substr($tableName, strlen($prefix) + 1);
                break;
            }
        }

        return '#__' . $tableName;
    }

    private static function resolveDbConnection(string $dbType): object|null
    {
        $connectionName = self::normalizeConnectionName($dbType);

        try {
            if ($connectionName === 'db2') {
                return Get::db2();
            }
            if ($connectionName === 'db') {
                return Get::db();
            }
            if (method_exists(Get::class, 'dbConnection')) {
                return Get::dbConnection($connectionName);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private static function normalizeConnectionName(string $dbType): string
    {
        $normalized = strtolower(trim($dbType));
        return $normalized !== '' ? $normalized : 'db';
    }

    private static function escapePhpSingleQuoted(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

}
