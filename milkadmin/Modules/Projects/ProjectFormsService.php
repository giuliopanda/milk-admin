<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectFormsService
{
    public static function saveFormsTreeFromRawInput(string|false $jsonData, string $projectsPage): array
    {
        $input = is_string($jsonData) ? json_decode($jsonData, true) : null;
        if (!is_array($input)) {
            return ['success' => false, 'msg' => 'Invalid JSON payload.'];
        }

        return self::saveFormsTree($input, $projectsPage);
    }

    public static function deleteFormTableFromRawInput(string|false $jsonData, string $projectsPage): array
    {
        $input = is_string($jsonData) ? json_decode($jsonData, true) : null;
        if (!is_array($input)) {
            return ['success' => false, 'msg' => 'Invalid JSON payload.'];
        }

        return self::deleteFormTable($input, $projectsPage);
    }

    public static function buildFormsTreeFromProject(array $project): array
    {
        $manifest = $project['manifest_data'] ?? null;
        if (!is_array($manifest)) {
            $manifestPath = (string) ($project['manifest_abs_path'] ?? '');
            $manifest = ManifestService::readManifest($manifestPath);
        }

        if (!is_array($manifest)) {
            return ManifestService::defaultFormsTree();
        }

        $manifestPath = (string) ($project['manifest_abs_path'] ?? '');
        $projectDir = $manifestPath !== '' ? dirname($manifestPath) : '';
        if ($projectDir === '' || !is_dir($projectDir)) {
            return ManifestService::defaultFormsTree();
        }

        $formsTree = ManifestService::buildFormsTree($manifest, $projectDir);
        return ProjectFormsIntegrityService::applyInitialChecks($formsTree, $project, $manifest);
    }

    private static function saveFormsTree(array $input, string $projectsPage): array
    {
        $moduleName = trim((string) ($input['module'] ?? ''));
        if ($moduleName === '' || preg_match('/^[A-Za-z0-9_-]+$/', $moduleName) !== 1) {
            return ['success' => false, 'msg' => 'Invalid module name.'];
        }

        $incomingRoot = $input['forms_tree']['root'] ?? null;
        if (!is_array($incomingRoot)) {
            return ['success' => false, 'msg' => 'Missing forms_tree.root.'];
        }

        $project = ProjectCatalogService::findProjectByModuleName($moduleName, $projectsPage);
        if ($project === null) {
            return ['success' => false, 'msg' => 'Project not found.'];
        }

        $structureAccess = ProjectSettingsService::evaluateStructureEditAccess($project);
        if (empty($structureAccess['can_edit_structure'])) {
            return [
                'success' => false,
                'msg' => (string) ($structureAccess['message'] ?? 'Structure editing is disabled for this project.'),
            ];
        }

        $manifestPath = (string) ($project['manifest_abs_path'] ?? '');
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return ['success' => false, 'msg' => 'Manifest file not found.'];
        }

        $existingManifest = ManifestService::readManifest($manifestPath);
        if ($existingManifest === null) {
            return ['success' => false, 'msg' => 'Failed to read existing manifest.'];
        }

        $updatedManifest = ManifestService::mergeFormsTree($existingManifest, $incomingRoot);
        if (!ManifestService::writeManifest($manifestPath, $updatedManifest)) {
            return ['success' => false, 'msg' => 'Failed to write manifest file.'];
        }

        $existingRefs = ManifestService::collectChildRefsFromManifest($existingManifest);
        $updatedRefs = ManifestService::collectChildRefsFromManifest($updatedManifest);
        $newRefs = array_values(array_diff($updatedRefs, $existingRefs));

        $assetResults = ManifestService::ensureFormAssets($manifestPath, $moduleName, $updatedManifest, $newRefs);
        if (!empty($assetResults['errors'])) {
            return self::rollbackAndBuildErrorResponse($manifestPath, $existingManifest, $assetResults);
        }

        $response = ['success' => true, 'msg' => 'Forms tree saved.'];
        if (!empty($assetResults['created_models'])) {
            $response['created_models'] = $assetResults['created_models'];
        }
        if (!empty($assetResults['created_tables'])) {
            $response['created_tables'] = $assetResults['created_tables'];
        }

        return $response;
    }

    private static function rollbackAndBuildErrorResponse(string $manifestPath, array $existingManifest, array $assetResults): array
    {
        $rollbackWarnings = [];

        if (!ManifestService::writeManifest($manifestPath, $existingManifest)) {
            $rollbackWarnings[] = 'Failed to restore manifest file.';
        }

        $rollbackResult = ManifestService::rollbackFormAssets($assetResults);
        if (!empty($rollbackResult['warnings'])) {
            $rollbackWarnings = array_merge($rollbackWarnings, $rollbackResult['warnings']);
        }

        $response = [
            'success' => false,
            'msg' => 'Failed to ensure form assets: ' . implode(', ', $assetResults['errors']),
        ];
        if (!empty($rollbackWarnings)) {
            $response['rollback_warnings'] = $rollbackWarnings;
        }

        return $response;
    }

    private static function deleteFormTable(array $input, string $projectsPage): array
    {
        $moduleName = trim((string) ($input['module'] ?? ''));
        if ($moduleName === '' || preg_match('/^[A-Za-z0-9_-]+$/', $moduleName) !== 1) {
            return ['success' => false, 'msg' => 'Invalid module name.'];
        }

        $requestedRef = trim((string) ($input['ref'] ?? ''));
        $targetRefBase = basename($requestedRef);
        if ($targetRefBase === '' || trim((string) pathinfo($targetRefBase, PATHINFO_FILENAME)) === '') {
            return ['success' => false, 'msg' => 'Invalid table reference.'];
        }

        $project = ProjectCatalogService::findProjectByModuleName($moduleName, $projectsPage);
        if ($project === null) {
            return ['success' => false, 'msg' => 'Project not found.'];
        }

        $manifestPath = (string) ($project['manifest_abs_path'] ?? '');
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return ['success' => false, 'msg' => 'Manifest file not found.'];
        }

        $manifest = ManifestService::readManifest($manifestPath);
        if (!is_array($manifest)) {
            return ['success' => false, 'msg' => 'Unable to read manifest file.'];
        }

        $projectStatus = ProjectSettingsService::resolveProjectStatusFromManifest($manifest);
        if ($projectStatus !== ProjectSettingsService::STATUS_DEVELOPMENT) {
            return ['success' => false, 'msg' => 'Table deletion is allowed only when project status is Development.'];
        }

        $rootNode = ManifestService::extractRootNode($manifest);
        if (!is_array($rootNode)) {
            return ['success' => false, 'msg' => 'Invalid manifest forms structure.'];
        }

        $rootRefBase = basename(trim((string) ($rootNode['ref'] ?? '')));
        if ($rootRefBase !== '' && strcasecmp($rootRefBase, $targetRefBase) === 0) {
            return ['success' => false, 'msg' => 'The main project table cannot be deleted here.'];
        }

        $removedRefs = [];
        $removed = self::removeFormNodeByRef($rootNode, $targetRefBase, $removedRefs);
        if (!$removed || empty($removedRefs)) {
            return ['success' => false, 'msg' => 'Table not found in manifest forms.'];
        }
        $removedRefs = array_values(array_unique($removedRefs));

        $updatedManifest = ManifestService::mergeFormsTree($manifest, $rootNode);
        if (!ManifestService::writeManifest($manifestPath, $updatedManifest)) {
            return ['success' => false, 'msg' => 'Unable to write manifest file.'];
        }

        $projectDir = dirname($manifestPath);
        $moduleDir = dirname($projectDir);
        [$modelDir, $modelNamespace] = ManifestService::resolveModelLocation($manifestPath, $moduleDir, $moduleName);

        $deletedSchemaFiles = [];
        $deletedModelFiles = [];
        $droppedTables = [];
        $warnings = [];
        $removedTableNames = [];

        foreach ($removedRefs as $removedRef) {
            $refBase = basename((string) $removedRef);
            if ($refBase === '') {
                continue;
            }

            $formName = trim((string) pathinfo($refBase, PATHINFO_FILENAME));
            if ($formName !== '') {
                $removedTableNames[] = $formName;

                $modelClassName = $formName . 'Model';
                $modelPath = rtrim($modelDir, '/\\') . '/' . $modelClassName . '.php';
                $dropResult = self::dropDatabaseTableForModel($modelNamespace, $modelClassName, $modelPath);
                if (!empty($dropResult['dropped'])) {
                    $droppedTableName = trim((string) ($dropResult['table_name'] ?? ''));
                    if ($droppedTableName !== '') {
                        $droppedTables[] = $droppedTableName;
                    }
                } elseif (trim((string) ($dropResult['warning'] ?? '')) !== '') {
                    $warnings[] = (string) $dropResult['warning'];
                }
            }

            $schemaPath = $projectDir . '/' . $refBase;
            if (is_file($schemaPath)) {
                if (@unlink($schemaPath)) {
                    $deletedSchemaFiles[] = $schemaPath;
                } else {
                    $warnings[] = 'Unable to delete schema file: ' . $schemaPath;
                }
            }

            if ($formName !== '') {
                $modelPath = rtrim($modelDir, '/\\') . '/' . $formName . 'Model.php';
                if (is_file($modelPath)) {
                    if (@unlink($modelPath)) {
                        $deletedModelFiles[] = $modelPath;
                    } else {
                        $warnings[] = 'Unable to delete model file: ' . $modelPath;
                    }
                }
            }
        }

        $viewLayoutResult = self::removeTablesFromViewLayout($projectDir, $removedTableNames);
        if (!empty($viewLayoutResult['warnings'])) {
            $warnings = array_merge($warnings, $viewLayoutResult['warnings']);
        }

        $msg = count($removedRefs) > 1
            ? 'Table and nested subtables deleted successfully.'
            : 'Table deleted successfully.';

        $response = [
            'success' => true,
            'msg' => $msg,
            'deleted_refs' => $removedRefs,
            'deleted_schema_files' => $deletedSchemaFiles,
            'deleted_model_files' => $deletedModelFiles,
            'dropped_tables' => array_values(array_unique($droppedTables)),
            'view_layout_removed_tables' => $viewLayoutResult['removed_tables'] ?? [],
        ];
        if (!empty($warnings)) {
            $response['warnings'] = array_values(array_unique($warnings));
        }

        return $response;
    }

    /**
     * @param array<string,mixed> $node
     * @param string[] $removedRefs
     */
    private static function removeFormNodeByRef(array &$node, string $targetRefBase, array &$removedRefs): bool
    {
        $children = is_array($node['forms'] ?? null) ? $node['forms'] : [];
        if (empty($children)) {
            return false;
        }

        $found = false;
        $filteredChildren = [];

        foreach ($children as $childNode) {
            if (!is_array($childNode)) {
                $filteredChildren[] = $childNode;
                continue;
            }

            $childRefBase = basename(trim((string) ($childNode['ref'] ?? '')));
            if ($childRefBase !== '' && strcasecmp($childRefBase, $targetRefBase) === 0) {
                self::collectNodeRefsRecursive($childNode, $removedRefs);
                $found = true;
                continue;
            }

            if (self::removeFormNodeByRef($childNode, $targetRefBase, $removedRefs)) {
                $found = true;
            }

            $filteredChildren[] = $childNode;
        }

        if ($found) {
            if (empty($filteredChildren)) {
                unset($node['forms']);
            } else {
                $node['forms'] = $filteredChildren;
            }
        }

        return $found;
    }

    /**
     * @param array<string,mixed> $node
     * @param string[] $refs
     */
    private static function collectNodeRefsRecursive(array $node, array &$refs): void
    {
        $refBase = basename(trim((string) ($node['ref'] ?? '')));
        if ($refBase !== '') {
            $refs[] = $refBase;
        }

        $children = is_array($node['forms'] ?? null) ? $node['forms'] : [];
        foreach ($children as $childNode) {
            if (!is_array($childNode)) {
                continue;
            }
            self::collectNodeRefsRecursive($childNode, $refs);
        }
    }

    /**
     * @param string[] $tableNames
     * @return array{removed_tables:string[],warnings:string[]}
     */
    private static function removeTablesFromViewLayout(string $projectDir, array $tableNames): array
    {
        $result = [
            'removed_tables' => [],
            'warnings' => [],
        ];

        $layoutPath = rtrim($projectDir, '/\\') . '/view_layout.json';
        if (!is_file($layoutPath)) {
            return $result;
        }

        $lookup = [];
        foreach ($tableNames as $tableName) {
            $normalized = trim((string) $tableName);
            if ($normalized !== '') {
                $lookup[strtolower($normalized)] = true;
            }
        }
        if (empty($lookup)) {
            return $result;
        }

        $rawLayout = @file_get_contents($layoutPath);
        if (!is_string($rawLayout) || trim($rawLayout) === '') {
            $result['warnings'][] = 'Unable to read view_layout.json.';
            return $result;
        }

        try {
            $layout = json_decode($rawLayout, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $result['warnings'][] = 'Invalid JSON in view_layout.json.';
            return $result;
        }

        if (!is_array($layout)) {
            $result['warnings'][] = 'Invalid data in view_layout.json.';
            return $result;
        }

        $cards = is_array($layout['cards'] ?? null) ? $layout['cards'] : [];
        $updatedCards = [];
        $changed = false;
        $removedTableLookup = [];

        foreach ($cards as $card) {
            if (!is_array($card)) {
                $updatedCards[] = $card;
                continue;
            }

            $cardType = trim((string) ($card['type'] ?? 'group'));
            if ($cardType === 'single-table') {
                $tableNode = is_array($card['table'] ?? null) ? $card['table'] : [];
                $tableName = trim((string) ($tableNode['name'] ?? ''));
                if ($tableName !== '' && isset($lookup[strtolower($tableName)])) {
                    $removedTableLookup[$tableName] = true;
                    $changed = true;
                    continue;
                }
                $updatedCards[] = $card;
                continue;
            }

            $tableNodes = is_array($card['tables'] ?? null) ? $card['tables'] : [];
            $updatedTables = [];
            foreach ($tableNodes as $tableNode) {
                if (!is_array($tableNode)) {
                    $updatedTables[] = $tableNode;
                    continue;
                }

                $tableName = trim((string) ($tableNode['name'] ?? ''));
                if ($tableName !== '' && isset($lookup[strtolower($tableName)])) {
                    $removedTableLookup[$tableName] = true;
                    $changed = true;
                    continue;
                }

                $updatedTables[] = $tableNode;
            }

            if (count($updatedTables) !== count($tableNodes)) {
                $changed = true;
            }

            if (empty($updatedTables)) {
                $changed = true;
                continue;
            }

            $card['tables'] = array_values($updatedTables);
            $updatedCards[] = $card;
        }

        if (!$changed) {
            return $result;
        }

        $layout['cards'] = array_values($updatedCards);
        try {
            $json = json_encode(
                $layout,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            $result['warnings'][] = 'Unable to encode updated view_layout.json.';
            return $result;
        }

        if (@file_put_contents($layoutPath, (string) $json . "\n") === false) {
            $result['warnings'][] = 'Unable to write view_layout.json.';
            return $result;
        }

        $result['removed_tables'] = array_values(array_keys($removedTableLookup));
        return $result;
    }

    /**
     * @return array{dropped:bool,table_name:string,warning:string}
     */
    private static function dropDatabaseTableForModel(string $modelNamespace, string $modelClassName, string $modelFilePath): array
    {
        $result = [
            'dropped' => false,
            'table_name' => '',
            'warning' => '',
        ];

        $modelNamespace = trim($modelNamespace, '\\');
        $modelClassName = trim($modelClassName);
        if ($modelNamespace === '' || $modelClassName === '') {
            $result['warning'] = 'Unable to drop table: invalid model namespace or class name.';
            return $result;
        }

        $modelFqcn = $modelNamespace . '\\' . $modelClassName;

        try {
            if (!class_exists($modelFqcn, false)) {
                if ($modelFilePath === '' || !is_file($modelFilePath)) {
                    $result['warning'] = 'Unable to drop table for ' . $modelClassName . ': model file not found.';
                    return $result;
                }
                require_once $modelFilePath;
            }

            if (!class_exists($modelFqcn)) {
                $result['warning'] = 'Unable to drop table for ' . $modelClassName . ': model class not found.';
                return $result;
            }

            $model = new $modelFqcn();
            if (method_exists($model, 'getTable')) {
                $result['table_name'] = trim((string) $model->getTable());
            }

            if (!method_exists($model, 'dropTable')) {
                $result['warning'] = 'Unable to drop table for ' . $modelClassName . ': dropTable() not available.';
                return $result;
            }

            if (!$model->dropTable()) {
                $error = method_exists($model, 'getLastError') ? trim((string) $model->getLastError()) : '';
                if ($error === '') {
                    $error = 'unknown error';
                }
                $tableLabel = $result['table_name'] !== '' ? $result['table_name'] : $modelClassName;
                $result['warning'] = 'Failed to drop table ' . $tableLabel . ': ' . $error;
                return $result;
            }

            $result['dropped'] = true;
            return $result;
        } catch (\Throwable $e) {
            $tableLabel = $result['table_name'] !== '' ? $result['table_name'] : $modelClassName;
            $result['warning'] = 'Failed to drop table ' . $tableLabel . ': ' . $e->getMessage();
            return $result;
        }
    }
}
