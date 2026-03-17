<?php

namespace Modules\Projects;

use App\Config;
use App\Get;

!defined('MILK_DIR') && die();

class ProjectCreationService
{
    private const TEMPLATE_DIR = __DIR__ . '/Templates/NewProject';

    /**
     * @return array<int,string>
     */
    public static function getDb2Tables(): array
    {
        try {
            $db = Get::db2();
            if (!is_object($db) || !method_exists($db, 'getTables')) {
                return [];
            }

            $tables = $db->getTables();
            if (!is_array($tables)) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        $result = [];
        foreach ($tables as $table) {
            $table = trim((string) $table);
            if ($table === '') {
                continue;
            }
            $result[] = $table;
        }

        $result = array_values(array_unique($result));
        usort($result, static function (string $a, string $b): int {
            return strcasecmp($a, $b);
        });

        return $result;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{
     *   success:bool,
     *   errors:array<int,string>,
     *   msg:string,
      *   module_name:string,
      *   module_page:string,
      *   project_name:string,
     *   project_description:string,
      *   root_form_name:string,
      *   root_table_name:string,
      *   main_table_source:string,
      *   existing_table_locked:bool
     * }
     */
    public static function createProject(array $input, string $projectsPage): array
    {
        $validation = self::validateInput($input, $projectsPage);
        if (!$validation['success']) {
            return $validation;
        }

        $moduleName = $validation['module_name'];
        $modulePage = $validation['module_page'];
        $projectName = $validation['project_name'];
        $projectDescription = $validation['project_description'];
        $rootFormName = $validation['root_form_name'];
        $rootTableName = self::normalizeGeneratedTableName($validation['root_table_name']);
        $mainTableSource = 'new';
        $existingTableLocked = false;

        $moduleDir = rtrim((string) LOCAL_DIR, '/\\') . '/Modules/' . $moduleName;
        $projectDir = $moduleDir . '/Project';
        $createdFiles = [];
        $createdDirs = [];

        try {
            if (is_dir($moduleDir) || is_file($moduleDir)) {
                return self::errorResult("Module directory '{$moduleName}' already exists.");
            }

            if (!@mkdir($moduleDir, 0755, true)) {
                return self::errorResult("Unable to create module directory '{$moduleName}'.");
            }
            $createdDirs[] = $moduleDir;

            if (!@mkdir($projectDir, 0755, true)) {
                self::rollback($createdFiles, $createdDirs);
                return self::errorResult("Unable to create project directory for '{$moduleName}'.");
            }
            $createdDirs[] = $projectDir;

            if (!self::writeProjectAccessDenyFile($projectDir, $createdFiles)) {
                self::rollback($createdFiles, $createdDirs);
                return self::errorResult("Unable to protect project directory for '{$moduleName}'.");
            }

            $tokens = [
                'MODULE_NAME' => $moduleName,
                'MODULE_PAGE' => $modulePage,
                'PROJECT_TITLE' => $projectName,
                'PROJECT_TITLE_PHP' => self::escapePhpSingleQuoted($projectName),
                'MENU_TITLE' => self::escapePhpSingleQuoted(self::buildMenuTitle($projectName)),
                'PROJECT_TITLE_JSON' => self::toJsonStringLiteral($projectName),
                'PROJECT_MANIFEST_NAME_JSON' => self::toJsonStringLiteral($projectName . ' Project'),
                'PROJECT_DESCRIPTION_JSON' => self::toJsonStringLiteral($projectDescription),
                'MANIFEST_CREATED_AT_JSON' => self::toJsonStringLiteral(date('Y-m-d H:i:s')),
                'MANIFEST_CREATED_BY_JSON' => (string) self::resolveCurrentUserId(),
                'ROOT_FORM_DESCRIPTION_JSON' => self::toJsonStringLiteral('Main form for ' . $projectName . ' project'),
                'ROOT_FORM_NAME' => $rootFormName,
                'ROOT_TABLE_NAME' => $rootTableName,
                'ROOT_EXISTING_TABLE_ROW' => '',
                'ROOT_SCHEMA_FIELDS_JSON' => self::buildRootSchemaFieldsJson(true, $modulePage),
            ];

            $moduleFile = $moduleDir . '/' . $moduleName . 'Module.php';
            if (!self::writeFileFromTemplate('module.php.tpl', $moduleFile, $tokens, $createdFiles)) {
                self::rollback($createdFiles, $createdDirs);
                return self::errorResult('Unable to generate module file from template.');
            }

            $modelFile = $moduleDir . '/' . $rootFormName . 'Model.php';
            if (!self::writeFileFromTemplate('root-model.php.tpl', $modelFile, $tokens, $createdFiles)) {
                self::rollback($createdFiles, $createdDirs);
                return self::errorResult('Unable to generate root model file from template.');
            }

            $manifestFile = $projectDir . '/manifest.json';
            if (!self::writeFileFromTemplate('manifest.json.tpl', $manifestFile, $tokens, $createdFiles)) {
                self::rollback($createdFiles, $createdDirs);
                return self::errorResult('Unable to generate manifest file from template.');
            }

            $schemaFile = $projectDir . '/' . $rootFormName . '.json';
            if (!self::writeFileFromTemplate('root-form.json.tpl', $schemaFile, $tokens, $createdFiles)) {
                self::rollback($createdFiles, $createdDirs);
                return self::errorResult('Unable to generate root form JSON from template.');
            }

            $searchFiltersFile = $projectDir . '/search_filters.json';
            if (!self::writeFileFromTemplate('search-filters.json.tpl', $searchFiltersFile, $tokens, $createdFiles)) {
                self::rollback($createdFiles, $createdDirs);
                return self::errorResult('Unable to generate search filters JSON from template.');
            }

            $modelFqcn = 'Local\\Modules\\' . $moduleName . '\\' . $rootFormName . 'Model';
            $tableBuild = ProjectTableService::buildDatabaseTable($modelFile, $modelFqcn);
            if (empty($tableBuild['success'])) {
                ProjectTableService::rollbackTables([
                    [
                        'model_fqcn' => $modelFqcn,
                        'model_file_path' => $modelFile,
                        'table_name' => (string) ($tableBuild['table_name'] ?? $rootTableName),
                        'table_existence_known_before' => (bool) ($tableBuild['table_existence_known'] ?? false),
                        'table_existed_before' => (bool) ($tableBuild['table_existed_before'] ?? true),
                    ],
                ]);
                self::rollback($createdFiles, $createdDirs);
                return self::errorResult('Unable to create database table: ' . trim((string) ($tableBuild['error'] ?? 'unknown error')));
            }
        } catch (\Throwable $e) {
            self::rollback($createdFiles, $createdDirs);
            return self::errorResult('Project creation failed: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'errors' => [],
            'msg' => "Project '{$projectName}' created successfully.",
            'module_name' => $moduleName,
            'module_page' => $modulePage,
            'project_name' => $projectName,
            'project_description' => $projectDescription,
            'root_form_name' => $rootFormName,
            'root_table_name' => $rootTableName,
            'main_table_source' => $mainTableSource,
            'existing_table_locked' => $existingTableLocked,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{
     *   success:bool,
     *   errors:array<int,string>,
     *   msg:string,
      *   module_name:string,
      *   module_page:string,
      *   project_name:string,
     *   project_description:string,
      *   root_form_name:string,
      *   root_table_name:string,
      *   main_table_source:string,
      *   existing_table_locked:bool
     * }
     */
    private static function validateInput(array $input, string $projectsPage): array
    {
        $projectNameRaw = trim((string) ($input['project_name'] ?? ''));
        if ($projectNameRaw === '') {
            return self::errorResult('Project name is required.');
        }
        if (preg_match('/^[A-Za-z0-9 _-]+$/', $projectNameRaw) !== 1) {
            return self::errorResult('Project name can contain only letters, numbers, spaces, underscore, and dash.');
        }
        $projectDescription = trim((string) ($input['project_description'] ?? ''));
        if ($projectDescription === '') {
            $projectDescription = 'Project generated from Projects module';
        }

        $moduleName = ProjectFormAssetValidationService::normalizeFormNameForCreation($projectNameRaw);
        if (!ProjectFormAssetValidationService::isStrictFormName($moduleName)) {
            return self::errorResult('Unable to generate a valid module name from project name.');
        }
        if (ProjectCatalogService::findProjectByModuleName($moduleName, $projectsPage) !== null) {
            return self::errorResult("A project module named '{$moduleName}' already exists.");
        }

        $modulePage = self::toActionSlug($moduleName);
        if ($modulePage === '') {
            return self::errorResult('Unable to generate a valid module page slug.');
        }

        $source = 'new';

        $db2Tables = self::getDb2Tables();
        $db2Map = [];
        foreach ($db2Tables as $table) {
            $table = trim((string) $table);
            if ($table === '') {
                continue;
            }

            $db2Map[strtolower($table)] = $table;

            $normalizedTable = self::normalizeGeneratedTableName($table);
            if ($normalizedTable !== '' && !isset($db2Map[strtolower($normalizedTable)])) {
                $db2Map[strtolower($normalizedTable)] = $table;
            }
        }

        $rootTableName = '';
        $existingTableLocked = false;
        $requested = trim((string) ($input['main_table_name'] ?? ''));
        if ($requested === '') {
            return self::errorResult('Main table name is required.');
        }

        $normalized = self::normalizeGeneratedTableName(self::normalizeNewTableName($requested));
        if (!ProjectFormAssetValidationService::isStrictTableName($normalized)) {
            return self::errorResult('Invalid new table name. Use lowercase letters, numbers, underscore (max 64 chars).');
        }

        if (isset($db2Map[strtolower($normalized)])) {
            return self::errorResult("Table '{$normalized}' already exists on db2. Choose a different new table name.");
        }

        $rootTableName = $normalized;

        return [
            'success' => true,
            'errors' => [],
            'msg' => '',
            'module_name' => $moduleName,
            'module_page' => $modulePage,
            'project_name' => $projectNameRaw,
            'project_description' => $projectDescription,
            'root_form_name' => $moduleName,
            'root_table_name' => $rootTableName,
            'main_table_source' => $source,
            'existing_table_locked' => $existingTableLocked,
        ];
    }

    private static function normalizeNewTableName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        $value = preg_replace('/[^A-Za-z0-9_]+/', '_', $value);
        $value = preg_replace('/_+/', '_', (string) $value);
        $value = strtolower(trim((string) $value, '_'));

        if ($value === '') {
            return '';
        }

        return substr($value, 0, ProjectFormAssetValidationService::MAX_TABLE_NAME_LENGTH);
    }

    private static function normalizeGeneratedTableName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = (string) preg_replace('/^#__/', '', $value);
        $prefixCandidates = [];

        $configPrefix = trim((string) Config::get('prefix2', ''));
        if ($configPrefix !== '') {
            $prefixCandidates[] = $configPrefix;
        }

        try {
            $db2 = Get::db2();
            if (is_object($db2) && property_exists($db2, 'prefix')) {
                $dbPrefix = trim((string) $db2->prefix);
                if ($dbPrefix !== '') {
                    $prefixCandidates[] = $dbPrefix;
                }
            }
        } catch (\Throwable $e) {
            // Ignore prefix autodetection failures and keep current value.
        }

        $prefixCandidates = array_values(array_unique($prefixCandidates));
        foreach ($prefixCandidates as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix === '') {
                continue;
            }

            $prefixToken = strtolower($prefix . '_');
            if (str_starts_with(strtolower($value), $prefixToken)) {
                $value = (string) substr($value, strlen($prefix) + 1);
                break;
            }
        }

        return trim($value);
    }

    private static function toActionSlug(string $value): string
    {
        $slug = preg_replace('/([a-z])([A-Z])/', '$1-$2', $value);
        $slug = str_replace('_', '-', (string) $slug);
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', (string) $slug);
        $slug = strtolower(trim((string) $slug, '-'));

        return $slug;
    }

    private static function buildMenuTitle(string $projectName): string
    {
        $title = trim($projectName);
        if ($title === '') {
            return 'Project';
        }

        if (strlen($title) <= 20) {
            return $title;
        }

        return rtrim(substr($title, 0, 20));
    }

    /**
     * @param array<string,string> $tokens
     * @param array<int,string> $createdFiles
     */
    private static function writeFileFromTemplate(
        string $templateFile,
        string $destinationPath,
        array $tokens,
        array &$createdFiles
    ): bool {
        $content = self::renderTemplate($templateFile, $tokens);
        if (!is_string($content)) {
            return false;
        }

        if (file_put_contents($destinationPath, $content) === false) {
            return false;
        }

        $createdFiles[] = $destinationPath;
        return true;
    }

    /**
     * @param array<string,string> $tokens
     */
    private static function renderTemplate(string $templateFile, array $tokens): string|false
    {
        $templatePath = rtrim(self::TEMPLATE_DIR, '/\\') . '/' . $templateFile;
        if (!is_file($templatePath)) {
            return false;
        }

        $template = @file_get_contents($templatePath);
        if (!is_string($template)) {
            return false;
        }

        $replace = [];
        foreach ($tokens as $key => $value) {
            $replace['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replace);
    }

    /**
     * @param array<int,string> $createdFiles
     */
    private static function writeProjectAccessDenyFile(string $projectDir, array &$createdFiles): bool
    {
        $htaccessPath = rtrim($projectDir, '/\\') . '/.htaccess';
        $content = "<IfModule mod_authz_core.c>\n"
            . "    Require all denied\n"
            . "</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n"
            . "    Deny from all\n"
            . "</IfModule>\n";

        if (file_put_contents($htaccessPath, $content) === false) {
            return false;
        }

        $createdFiles[] = $htaccessPath;
        return true;
    }

    /**
     * @param array<int,string> $createdFiles
     * @param array<int,string> $createdDirs
     */
    private static function rollback(array $createdFiles, array $createdDirs): void
    {
        foreach (array_reverse($createdFiles) as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        foreach (array_reverse($createdDirs) as $dirPath) {
            if (is_dir($dirPath)) {
                @rmdir($dirPath);
            }
        }
    }

    private static function escapePhpSingleQuoted(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    /**
     * Build JSON object rows to place inside root-form template "model.fields".
     */
    private static function buildRootSchemaFieldsJson(bool $includeAuditFields, string $modulePage): string
    {
        if (!$includeAuditFields) {
            return '';
        }

        $modulePage = trim($modulePage);
        if ($modulePage === '') {
            $modulePage = 'home';
        }

        $fields = [
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

        $rows = [];
        foreach ($fields as $field) {
            try {
                $json = json_encode($field, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $json = false;
            }
            if (!is_string($json) || $json === '') {
                continue;
            }

            $lines = explode("\n", $json);
            $rows[] = implode("\n", array_map(static fn(string $line): string => '            ' . $line, $lines));
        }

        return implode(",\n", $rows);
    }

    private static function toJsonStringLiteral(string $value): string
    {
        try {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            return is_string($json) ? $json : '""';
        } catch (\Throwable $e) {
            return '""';
        }
    }

    private static function resolveCurrentUserId(): int
    {
        try {
            $user = Get::user();
            if (is_object($user) && isset($user->id) && is_numeric($user->id)) {
                return (int) $user->id;
            }
        } catch (\Throwable $e) {
            // fallback for guest/cli contexts
        }

        return 0;
    }

    /**
     * @return array{
     *   success:bool,
     *   errors:array<int,string>,
     *   msg:string,
      *   module_name:string,
      *   module_page:string,
      *   project_name:string,
     *   project_description:string,
      *   root_form_name:string,
      *   root_table_name:string,
      *   main_table_source:string,
      *   existing_table_locked:bool
     * }
     */
    private static function errorResult(string $message): array
    {
        return [
            'success' => false,
            'errors' => [$message],
            'msg' => $message,
            'module_name' => '',
            'module_page' => '',
            'project_name' => '',
            'project_description' => '',
            'root_form_name' => '',
            'root_table_name' => '',
            'main_table_source' => 'new',
            'existing_table_locked' => false,
        ];
    }
}
