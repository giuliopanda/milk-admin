<?php

namespace Modules\Projects;

use App\Get;

!defined('MILK_DIR') && die();

class ProjectCatalogService
{
    /** @var array<int,string> */
    private static array $usernameCache = [];

    /**
     * @return array<int,array{
     *   module_name:string,
     *   module_page:string,
     *   project_name:string,
     *   description:string,
     *   created_at:string,
     *   created_by:int,
     *   created_by_username:string,
     *   manifest_path:string,
     *   manifest_abs_path:string,
     *   manifest_data:array,
     *   main_table_row_count:int|null,
     *   related_tables_count:int,
     *   enter_url:string,
     *   edit_url:string,
     *   build_forms_url:string
     * }>
     */
    public static function discoverProjects(string $projectsPage): array
    {
        $modulesDir = rtrim((string) LOCAL_DIR, '/\\') . '/Modules';
        if (!is_dir($modulesDir)) {
            return [];
        }

        $rows = [];
        $directories = @scandir($modulesDir);
        if (!is_array($directories)) {
            return [];
        }

        foreach ($directories as $moduleName) {
            if ($moduleName === '.' || $moduleName === '..') {
                continue;
            }
            if (isset($moduleName[0]) && $moduleName[0] === '.') {
                continue;
            }

            $moduleDir = $modulesDir . '/' . $moduleName;
            if (!is_dir($moduleDir)) {
                continue;
            }

            $manifestPath = ManifestService::findManifestPath($moduleDir);
            if ($manifestPath === null) {
                continue;
            }

            $manifest = ManifestService::readManifest($manifestPath);
            if ($manifest === null) {
                continue;
            }

            $projectName = trim((string) ($manifest['name'] ?? ''));
            if ($projectName === '') {
                $projectName = (string) $moduleName;
            }

            $description = trim((string) ($manifest['description'] ?? ''));
            $createdAt = trim((string) ($manifest['created_at'] ?? ''));
            $createdBy = self::normalizeUserId($manifest['created_by'] ?? 0);
            $createdByUsername = self::resolveUsernameById($createdBy);
            $modulePage = self::resolveModulePage($moduleDir, (string) $moduleName);
            $rootListAction = self::resolveRootListAction($manifest);
            $mainTableRowCount = self::resolveMainTableRowCount($moduleDir, (string) $moduleName, $manifestPath, $manifest);
            $relatedTablesCount = self::resolveRelatedTablesCount($manifest);

            $enterUrl = '?page=' . rawurlencode($modulePage);
            if ($rootListAction !== '') {
                $enterUrl .= '&action=' . rawurlencode($rootListAction);
            }

            $rows[] = [
                'module_name' => (string) $moduleName,
                'module_page' => $modulePage,
                'project_name' => $projectName,
                'description' => $description,
                'created_at' => $createdAt,
                'created_by' => $createdBy,
                'created_by_username' => $createdByUsername,
                'manifest_path' => str_replace((string) LOCAL_DIR . '/', '', $manifestPath),
                'manifest_abs_path' => $manifestPath,
                'manifest_data' => $manifest,
                'main_table_row_count' => $mainTableRowCount,
                'related_tables_count' => $relatedTablesCount,
                'enter_url' => $enterUrl,
                'edit_url' => '?page=' . rawurlencode($projectsPage) . '&action=edit&module=' . rawurlencode((string) $moduleName),
                'build_forms_url' => '?page=' . rawurlencode($projectsPage) . '&action=edit&module=' . rawurlencode((string) $moduleName),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return strcasecmp((string) ($a['project_name'] ?? ''), (string) ($b['project_name'] ?? ''));
        });

        return $rows;
    }

    public static function findProjectByModuleName(string $moduleName, string $projectsPage): ?array
    {
        foreach (self::discoverProjects($projectsPage) as $project) {
            if (strcasecmp((string) ($project['module_name'] ?? ''), $moduleName) === 0) {
                return $project;
            }
        }

        return null;
    }

    private static function resolveRootListAction(array $manifest): string
    {
        $rootRef = trim((string) ($manifest['ref'] ?? ''));

        if ($rootRef === '' && isset($manifest['forms'][0]['ref']) && is_string($manifest['forms'][0]['ref'])) {
            $rootRef = trim((string) $manifest['forms'][0]['ref']);
        }

        if ($rootRef === '') {
            return '';
        }

        $rootForm = trim((string) pathinfo(basename($rootRef), PATHINFO_FILENAME));
        if ($rootForm === '') {
            return '';
        }

        return self::toActionSlug($rootForm) . '-list';
    }

    private static function resolveModulePage(string $moduleDir, string $moduleName): string
    {
        $moduleFiles = glob($moduleDir . '/*Module.php') ?: [];
        $mainModule = $moduleDir . '/' . $moduleName . 'Module.php';
        if (is_file($mainModule)) {
            array_unshift($moduleFiles, $mainModule);
            $moduleFiles = array_values(array_unique($moduleFiles));
        }

        foreach ($moduleFiles as $moduleFile) {
            $content = @file_get_contents($moduleFile);
            if (!is_string($content)) {
                continue;
            }

            if (preg_match('/->page\\(\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $content, $matches) === 1) {
                $page = trim((string) ($matches[1] ?? ''));
                if ($page !== '') {
                    return $page;
                }
            }
        }

        return self::toActionSlug($moduleName);
    }

    private static function toActionSlug(string $name): string
    {
        $slug = preg_replace('/([a-z])([A-Z])/', '$1-$2', $name);
        $slug = str_replace('_', '-', (string) $slug);
        return strtolower((string) $slug);
    }

    private static function resolveMainTableRowCount(
        string $moduleDir,
        string $moduleName,
        string $manifestPath,
        array $manifest
    ): ?int {
        $mainFormName = self::resolveMainFormName($manifest);
        if ($mainFormName === '') {
            return null;
        }

        $modelPath = self::findMainModelPath($mainFormName, $manifestPath, $moduleDir, $moduleName);
        if ($modelPath === '') {
            return null;
        }

        $metadata = self::readModelMetadataFromFile($modelPath);
        $tableName = trim((string) ($metadata['table_name'] ?? ''));
        if (!self::isSupportedModelTableReference($tableName)) {
            return null;
        }

        $dbType = trim((string) ($metadata['db_type'] ?? ''));
        if ($dbType === '') {
            $dbType = trim((string) ($manifest['db'] ?? 'db'));
        }
        if ($dbType === '') {
            $dbType = 'db';
        }

        $db = self::resolveDbConnection($dbType);
        if (!is_object($db) || !method_exists($db, 'qn') || !method_exists($db, 'getVar')) {
            return null;
        }

        try {
            $schema = Get::schema($tableName, $db);
            if (is_object($schema) && method_exists($schema, 'exists') && !$schema->exists()) {
                return 0;
            }
        } catch (\Throwable $e) {
            return null;
        }

        try {
            $countRaw = $db->getVar('SELECT COUNT(*) FROM ' . $db->qn($tableName));
            if (!is_string($countRaw) || !is_numeric($countRaw)) {
                return null;
            }

            $count = (int) $countRaw;
            return $count >= 0 ? $count : 0;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function resolveMainFormName(array $manifest): string
    {
        $rootRef = trim((string) ($manifest['ref'] ?? ''));

        if ($rootRef === '' && isset($manifest['forms'][0]['ref']) && is_string($manifest['forms'][0]['ref'])) {
            $rootRef = trim((string) $manifest['forms'][0]['ref']);
        }

        if ($rootRef === '') {
            return '';
        }

        $rootRefBase = basename($rootRef);
        return trim((string) pathinfo($rootRefBase, PATHINFO_FILENAME));
    }

    private static function resolveRelatedTablesCount(array $manifest): int
    {
        $forms = $manifest['forms'] ?? null;
        return is_array($forms) ? count($forms) : 0;
    }

    private static function findMainModelPath(
        string $formName,
        string $manifestPath,
        string $moduleDir,
        string $moduleName
    ): string {
        if ($formName === '') {
            return '';
        }

        [$defaultModelDir] = ManifestService::resolveModelLocation($manifestPath, $moduleDir, $moduleName);
        $projectModelsDir = dirname($manifestPath) . '/Models';

        $modelDirs = [];
        foreach ([$defaultModelDir, $projectModelsDir, $moduleDir] as $dir) {
            $dir = rtrim((string) $dir, '/\\');
            if ($dir === '' || !is_dir($dir)) {
                continue;
            }
            $modelDirs[$dir] = $dir;
        }

        $candidates = self::candidateModelClassNames($formName);
        foreach ($candidates as $classShort) {
            foreach ($modelDirs as $modelDir) {
                $path = $modelDir . '/' . $classShort . '.php';
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    private static function candidateModelClassNames(string $formName): array
    {
        $formName = trim($formName);
        if ($formName === '') {
            return [];
        }

        $candidates = [$formName . 'Model'];
        $studly = self::toStudlyCase($formName);
        if ($studly !== '') {
            $candidates[] = $studly . 'Model';
        }

        $candidates = array_values(array_unique($candidates));

        return array_values(array_filter($candidates, static function ($candidate): bool {
            return preg_match('/^[A-Za-z][A-Za-z0-9]*$/', (string) $candidate) === 1;
        }));
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

    private static function resolveDbConnection(string $dbType): object|null
    {
        $connectionName = strtolower(trim($dbType));
        if ($connectionName === '') {
            $connectionName = 'db';
        }

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

    private static function isSupportedModelTableReference(string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        return preg_match('/^(#__)?[A-Za-z0-9_]+$/', $tableName) === 1;
    }

    private static function normalizeUserId(mixed $rawUserId): int
    {
        if (is_int($rawUserId)) {
            return $rawUserId > 0 ? $rawUserId : 0;
        }
        if (is_numeric($rawUserId)) {
            $id = (int) $rawUserId;
            return $id > 0 ? $id : 0;
        }

        return 0;
    }

    private static function resolveUsernameById(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        if (array_key_exists($userId, self::$usernameCache)) {
            return self::$usernameCache[$userId];
        }

        $username = '';
        try {
            $user = Get::user($userId);
            if (is_object($user)) {
                $username = trim((string) ($user->username ?? ''));
            }
        } catch (\Throwable $e) {
            $username = '';
        }

        if ($username === '') {
            $username = 'User #' . $userId;
        }

        self::$usernameCache[$userId] = $username;
        return $username;
    }
}
