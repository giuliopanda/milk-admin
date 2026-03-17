<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectFormsIntegrityService
{
    public static function getBuildFormsBlockingError(array $formsTree): string
    {
        $main = is_array($formsTree['main'] ?? null) ? $formsTree['main'] : [];
        $rootChildren = is_array($formsTree['children'] ?? null) ? $formsTree['children'] : [];

        if (!empty($main)) {
            // In build-forms tree, root children are stored in formsTree['children'].
            // Normalize to node['children'] so we can run a single parent/child DB check.
            $mainWithChildren = $main;
            $mainWithChildren['children'] = $rootChildren;

            $error = self::findCrossDbParentChildMismatch($mainWithChildren);
            if ($error !== '') {
                return $error;
            }

            $error = self::findCrossDbParentChildMismatch($main);
            if ($error !== '') {
                return $error;
            }
        }

        foreach ($rootChildren as $child) {
            if (!is_array($child)) {
                continue;
            }
            $error = self::findCrossDbParentChildMismatch($child);
            if ($error !== '') {
                return $error;
            }
        }

        return '';
    }

    /**
     * @param array{main:array,children:array} $formsTree
     * @param array{module_name?:string,manifest_abs_path?:string} $project
     * @param array<string,mixed> $manifest
     * @return array{main:array,children:array}
     */
    public static function applyInitialChecks(array $formsTree, array $project, array $manifest): array
    {
        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return $formsTree;
        }

        $projectDir = dirname($manifestPath);
        $moduleDir = dirname($projectDir);
        $moduleName = trim((string) ($project['module_name'] ?? ''));
        if ($moduleName === '') {
            return $formsTree;
        }

        $dbType = trim((string) ($manifest['db'] ?? 'db'));
        if ($dbType === '') {
            $dbType = 'db';
        }

        $modelLocations = self::buildModelSearchLocations($manifestPath, $moduleDir, $moduleName);

        $context = [
            'module_name' => $moduleName,
            'db_type' => $dbType,
            'project_dir' => $projectDir,
            'model_locations' => $modelLocations,
        ];

        $main = is_array($formsTree['main'] ?? null) ? $formsTree['main'] : [];
        $main = self::inspectNode($main, $context, true);

        $children = is_array($formsTree['children'] ?? null) ? $formsTree['children'] : [];
        $checkedChildren = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $checkedChildren[] = self::inspectNode($child, $context, false);
        }

        return [
            'main' => $main,
            'children' => $checkedChildren,
        ];
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function inspectNode(array $node, array $context, bool $isMain): array
    {
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $checkedChildren = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $checkedChildren[] = self::inspectNode($child, $context, false);
        }
        $node['children'] = $checkedChildren;

        $refBase = basename((string) ($node['ref'] ?? ''));
        $formName = trim((string) pathinfo($refBase, PATHINFO_FILENAME));
        if ($formName === '') {
            $formName = trim((string) ($node['form_name'] ?? ''));
        }

        $jsonExists = false;
        if ($refBase !== '') {
            $schemaPath = rtrim($context['project_dir'], '/\\') . '/' . $refBase;
            $jsonExists = is_file($schemaPath);
        }

        $modelLocations = is_array($context['model_locations'] ?? null) ? $context['model_locations'] : [];
        $modelInfo = self::findModelInfo($formName, $modelLocations);
        $modelExists = $modelInfo['exists'];

        $tableName = trim((string) ($modelInfo['table_name'] ?? ''));
        $tableNameIsValid = self::isSupportedModelTableReference($tableName);
        $modelDbType = trim((string) ($modelInfo['db_type'] ?? ''));
        if ($modelDbType === '') {
            $modelDbType = trim((string) ($context['db_type'] ?? 'db'));
            if ($modelDbType === '') {
                $modelDbType = 'db';
            }
        }

        $tableExistsResult = null;
        $tableExists = false;
        if ($modelExists && $tableNameIsValid) {
            $tableExistsResult = ProjectFormAssetValidationService::tableExistsOnConnection($tableName, $modelDbType);
            $tableExists = $tableExistsResult === true;
        }
        $existingTableLocked = self::normalizeBool($node['existingTable'] ?? ($node['existing_table'] ?? false));

        $hasChildren = count($checkedChildren) > 0;
        $status = 'ok';
        $message = '';
        $isEditable = true;
        $deleteAllowed = false;

        if (!$modelExists || !$tableNameIsValid) {
            $status = 'missing_model';
            $isEditable = false;
            if ($hasChildren) {
                $message = "Contact your administrator: manifest is inconsistent (child rows exist).";
                $deleteAllowed = false;
            } else {
                $message = "Model is missing or table() is not configured in the model. You can delete this row.";
                $deleteAllowed = !$isMain;
            }
        } elseif ($tableExists !== true) {
            $status = 'missing_table';
            $isEditable = false;
            if ($hasChildren) {
                $message = "Contact your administrator: database '{$modelDbType}' is inconsistent (child rows exist).";
                $deleteAllowed = false;
            } else {
                $message = "Table not found in database '{$modelDbType}'. You can delete this row.";
                $deleteAllowed = !$isMain;
            }
        } elseif ($existingTableLocked) {
            $status = 'locked_existing_table';
            $isEditable = false;
            $deleteAllowed = false;
            $message = "This form is linked to an existing table and cannot be modified.";
        } elseif ($tableExists && $modelExists && !$jsonExists) {
            $status = 'locked_no_schema';
            $isEditable = false;
            $message = "Table exists but JSON schema is missing. This row is locked.";
        }

        $node['integrity_status'] = $status;
        $node['integrity_message'] = $message;
        $node['integrity_json_exists'] = $jsonExists;
        $node['integrity_model_exists'] = $modelExists;
        $node['integrity_table_exists'] = $tableExists;
        $node['integrity_model_generated'] = false;
        $node['integrity_has_children'] = $hasChildren;
        $node['integrity_delete_allowed'] = $deleteAllowed;
        $node['integrity_is_editable'] = $isEditable;
        $node['integrity_table_name'] = $tableName;
        $node['integrity_table_db'] = $modelDbType;
        $node['integrity_existing_table_locked'] = $existingTableLocked;

        return $node;
    }

    /**
     * @return array{exists:bool,model_file_path:string,model_class_name:string,table_name:string,db_type:string}
     */
    private static function findModelInfo(string $formName, array $modelLocations): array
    {
        $candidates = self::candidateModelClassNames($formName);
        foreach ($candidates as $classShort) {
            foreach ($modelLocations as $location) {
                $modelDir = trim((string) ($location['dir'] ?? ''));
                $modelNamespace = trim((string) ($location['namespace'] ?? ''));
                if ($modelDir === '') {
                    continue;
                }

                $modelPath = rtrim($modelDir, '/\\') . '/' . $classShort . '.php';
                if (!is_file($modelPath)) {
                    continue;
                }

                $metadata = self::readModelMetadataFromFile($modelPath);
                $tableName = trim((string) ($metadata['table_name'] ?? ''));
                $modelClassName = $modelNamespace !== '' ? (trim($modelNamespace, '\\') . '\\' . $classShort) : $classShort;

                return [
                    'exists' => true,
                    'model_file_path' => $modelPath,
                    'model_class_name' => $modelClassName,
                    'table_name' => $tableName,
                    'db_type' => trim((string) ($metadata['db_type'] ?? '')),
                ];
            }
        }

        return [
            'exists' => false,
            'model_file_path' => '',
            'model_class_name' => '',
            'table_name' => '',
            'db_type' => '',
        ];
    }

    /**
     * @return string[]
     */
    private static function candidateModelClassNames(string $formName): array
    {
        $candidates = [];
        $base = trim($formName);
        if ($base !== '') {
            $candidates[] = $base . 'Model';
            $studly = self::toStudlyCase($base);
            if ($studly !== '') {
                $candidates[] = $studly . 'Model';
            }
        }

        $candidates = array_values(array_unique($candidates));
        $candidates = array_values(array_filter($candidates, static function ($candidate) {
            return ProjectFormAssetValidationService::isStrictModelClassName((string) $candidate);
        }));

        return $candidates;
    }

    private static function toStudlyCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/[^A-Za-z0-9 ]+/', ' ', (string) $value);
        $value = ucwords(strtolower(trim((string) $value)));
        return str_replace(' ', '', $value);
    }

    /**
     * @return array{table_name:string,db_type:string}
     */
    private static function readModelMetadataFromFile(string $modelPath): array
    {
        $content = @file_get_contents($modelPath);
        if (!is_string($content) || $content === '') {
            return ['table_name' => '', 'db_type' => ''];
        }

        $tableName = '';
        if (preg_match('/->table\\(\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $content, $matches) === 1) {
            $tableName = trim((string) ($matches[1] ?? ''));
        }
        $dbType = '';
        if (preg_match('/->db\\(\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $content, $matches) === 1) {
            $dbType = trim((string) ($matches[1] ?? ''));
        }

        return ['table_name' => $tableName, 'db_type' => $dbType];
    }

    /**
     * @return array<int,array{dir:string,namespace:string}>
     */
    private static function buildModelSearchLocations(string $manifestPath, string $moduleDir, string $moduleName): array
    {
        $locations = [];
        $seen = [];

        // Keep existing default location as first lookup priority.
        [$defaultDir, $defaultNamespace] = ManifestService::resolveModelLocation($manifestPath, $moduleDir, $moduleName);
        self::pushModelLocation($locations, $seen, $defaultDir, $defaultNamespace);

        // Also search inside Project/Models.
        $projectModelsDir = dirname($manifestPath) . '/Models';
        $projectModelsNamespace = 'Local\\Modules\\' . $moduleName . '\\Project\\Models';
        self::pushModelLocation($locations, $seen, $projectModelsDir, $projectModelsNamespace);

        // Also search in module root.
        $moduleRootNamespace = 'Local\\Modules\\' . $moduleName;
        self::pushModelLocation($locations, $seen, $moduleDir, $moduleRootNamespace);

        return $locations;
    }

    /**
     * @param array<int,array{dir:string,namespace:string}> $locations
     * @param array<string,bool> $seen
     */
    private static function pushModelLocation(array &$locations, array &$seen, string $dir, string $namespace): void
    {
        $dir = trim($dir);
        $namespace = trim($namespace, '\\');
        if ($dir === '') {
            return;
        }

        $key = strtolower(rtrim($dir, '/\\')) . '|' . strtolower($namespace);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        $locations[] = [
            'dir' => rtrim($dir, '/\\'),
            'namespace' => $namespace,
        ];
    }

    private static function findCrossDbParentChildMismatch(array $node): string
    {
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if (empty($children)) {
            return '';
        }

        $parentDb = strtolower(trim((string) ($node['integrity_table_db'] ?? '')));
        $parentModelExists = (bool) ($node['integrity_model_exists'] ?? false);
        $parentFormName = self::resolveNodeFormName($node);

        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $childDb = strtolower(trim((string) ($child['integrity_table_db'] ?? '')));
            $childModelExists = (bool) ($child['integrity_model_exists'] ?? false);

            if ($parentModelExists && $childModelExists && $parentDb !== '' && $childDb !== '' && $parentDb !== $childDb) {
                $childFormName = self::resolveNodeFormName($child);
                return "Critical integrity error: parent form '{$parentFormName}' uses database '{$parentDb}' but child form '{$childFormName}' uses database '{$childDb}'. Mixed databases in parent/child forms are not supported for list rendering. Fix model db() configuration before using this page.";
            }

            $nestedError = self::findCrossDbParentChildMismatch($child);
            if ($nestedError !== '') {
                return $nestedError;
            }
        }

        return '';
    }

    private static function resolveNodeFormName(array $node): string
    {
        $formName = trim((string) ($node['form_name'] ?? ''));
        if ($formName !== '') {
            return $formName;
        }

        $ref = basename((string) ($node['ref'] ?? ''));
        $fromRef = trim((string) pathinfo($ref, PATHINFO_FILENAME));
        return $fromRef !== '' ? $fromRef : 'unknown';
    }

    private static function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }

        $v = strtolower(trim((string) $value));
        if ($v === '') {
            return false;
        }

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    private static function isSupportedModelTableReference(string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        return preg_match('/^(#__)?[A-Za-z0-9_]+$/', $tableName) === 1;
    }
}
