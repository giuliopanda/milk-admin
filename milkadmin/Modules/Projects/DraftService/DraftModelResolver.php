<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

use Modules\Projects\ManifestService;

class DraftModelResolver
{
    /**
     * @param array<string,mixed> $project
     */
    public static function resolveSchemaPath(array $project, string $refBase): string
    {
        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return '';
        }

        $projectDir = dirname($manifestPath);
        if (!is_dir($projectDir)) {
            return '';
        }

        $schemaPath = $projectDir . '/' . basename($refBase);
        if (!is_file($schemaPath)) {
            return '';
        }

        return $schemaPath;
    }

    /**
     * @param array<string,mixed> $project
     * @return array{success:bool,error:string,model_file_path:string,model_fqcn:string}
     */
    public static function resolveModelInfoForRef(array $project, string $refBase): array
    {
        $formName = trim((string) pathinfo($refBase, PATHINFO_FILENAME));
        if ($formName === '') {
            return ['success' => false, 'error' => 'Invalid form name for table update.', 'model_file_path' => '', 'model_fqcn' => ''];
        }

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        $moduleName = trim((string) ($project['module_name'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath) || $moduleName === '') {
            return ['success' => false, 'error' => 'Invalid project paths for table update.', 'model_file_path' => '', 'model_fqcn' => ''];
        }

        $moduleDir = dirname(dirname($manifestPath));
        [$defaultDir, $defaultNamespace] = ManifestService::resolveModelLocation($manifestPath, $moduleDir, $moduleName);

        $locations = [];
        self::pushModelLocation($locations, $defaultDir, $defaultNamespace);
        self::pushModelLocation($locations, dirname($manifestPath) . '/Models', 'Local\\Modules\\' . $moduleName . '\\Project\\Models');
        self::pushModelLocation($locations, $moduleDir, 'Local\\Modules\\' . $moduleName);

        $candidates = [$formName . 'Model'];
        $studly = DraftFieldUtils::toStudlyCase($formName);
        if ($studly !== '' && strcasecmp($studly, $formName) !== 0) {
            $candidates[] = $studly . 'Model';
        }
        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $classShort) {
            foreach ($locations as $location) {
                $dir = trim((string) ($location['dir'] ?? ''));
                $namespace = trim((string) ($location['namespace'] ?? ''), '\\');
                if ($dir === '') {
                    continue;
                }

                $modelFilePath = rtrim($dir, '/\\') . '/' . $classShort . '.php';
                if (!is_file($modelFilePath)) {
                    continue;
                }

                $modelFqcn = $namespace !== '' ? ($namespace . '\\' . $classShort) : $classShort;
                return [
                    'success' => true,
                    'error' => '',
                    'model_file_path' => $modelFilePath,
                    'model_fqcn' => $modelFqcn,
                ];
            }
        }

        return ['success' => false, 'error' => 'Model file not found for table update.', 'model_file_path' => '', 'model_fqcn' => ''];
    }

    /**
     * @param array<string,mixed> $project
     */
    public static function isExistingTableLockedForRef(array $project, string $refBase): bool
    {
        $refBase = basename(trim($refBase));
        if ($refBase === '') {
            return false;
        }

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return false;
        }

        $manifest = ManifestService::readManifest($manifestPath);
        if (!is_array($manifest)) {
            return false;
        }

        $rootRef = basename(trim((string) ($manifest['ref'] ?? '')));
        if ($rootRef !== '' && strcasecmp($rootRef, $refBase) === 0) {
            return DraftFieldUtils::normalizeBool($manifest['existingTable'] ?? ($manifest['existing_table'] ?? false));
        }

        $forms = is_array($manifest['forms'] ?? null) ? $manifest['forms'] : [];
        $node = self::findManifestNodeByRefRecursive($forms, $refBase);
        if (!is_array($node)) {
            return false;
        }

        return DraftFieldUtils::normalizeBool($node['existingTable'] ?? ($node['existing_table'] ?? false));
    }

    /**
     * @param array<int,array{dir:string,namespace:string}> $locations
     */
    private static function pushModelLocation(array &$locations, string $dir, string $namespace): void
    {
        $dir = trim($dir);
        $namespace = trim($namespace, '\\');
        if ($dir === '') {
            return;
        }

        $key = strtolower(rtrim($dir, '/\\')) . '|' . strtolower($namespace);
        foreach ($locations as $existing) {
            $existingDir = strtolower(rtrim((string) ($existing['dir'] ?? ''), '/\\'));
            $existingNs = strtolower((string) ($existing['namespace'] ?? ''));
            if ($existingDir . '|' . $existingNs === $key) {
                return;
            }
        }

        $locations[] = [
            'dir' => rtrim($dir, '/\\'),
            'namespace' => $namespace,
        ];
    }

    /**
     * @param array<int,mixed> $nodes
     * @return array<string,mixed>|null
     */
    private static function findManifestNodeByRefRecursive(array $nodes, string $refBase): ?array
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $nodeRef = basename(trim((string) ($node['ref'] ?? '')));
            if ($nodeRef !== '' && strcasecmp($nodeRef, $refBase) === 0) {
                return $node;
            }

            $children = is_array($node['forms'] ?? null) ? $node['forms'] : [];
            if (!empty($children)) {
                $found = self::findManifestNodeByRefRecursive($children, $refBase);
                if (is_array($found)) {
                    return $found;
                }
            }
        }

        return null;
    }
}
