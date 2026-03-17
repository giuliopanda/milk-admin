<?php
namespace Extensions\Projects\Classes\Module;

use App\Get;
use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

class ProjectUninstallService
{
    /**
     * Drop project tables from manifest.
     *
     * @param callable(string,string):(?string) $resolveModelClassForForm
     */
    public function uninstallProjectTables(
        string $moduleNamespace,
        string $moduleDir,
        string $manifestPath,
        callable $resolveModelClassForForm
    ): bool {
        if (!is_file($manifestPath)) {
            return false;
        }

        $manifestRaw = @file_get_contents($manifestPath);
        $manifest = json_decode(is_string($manifestRaw) ? $manifestRaw : '', true);
        if (!is_array($manifest)) {
            return false;
        }

        $mainFormRef = $this->resolveMainFormRef($manifest);
        $mainExistingTable = $this->normalizeManifestBool($manifest['existingTable'] ?? false);

        $forms = $manifest['forms'] ?? [];
        $formRefs = $this->collectFormRefsRecursive(is_array($forms) ? $forms : []);
        foreach ($formRefs as $formRef) {
            $this->dropTableForFormRef($resolveModelClassForForm, $moduleNamespace, $moduleDir, $formRef, true);
        }

        if ($mainFormRef !== '' && !$mainExistingTable) {
            $this->dropTableForFormRef($resolveModelClassForForm, $moduleNamespace, $moduleDir, $mainFormRef, false);
        }

        return true;
    }

    /**
     * @param callable(string,string):(?string) $resolveModelClassForForm
     */
    protected function dropTableForFormRef(
        callable $resolveModelClassForForm,
        string $moduleNamespace,
        string $moduleDir,
        string $formRef,
        bool $preferProjectModels
    ): void {
        $formName = $this->extractFormNameFromRef($formRef);
        if ($formName === '') {
            return;
        }

        $modelClass = $resolveModelClassForForm($moduleNamespace, $formName);
        if (is_string($modelClass) && $modelClass !== '' && class_exists($modelClass)) {
            try {
                $model = new $modelClass();
                if (method_exists($model, 'dropTable')) {
                    $model->dropTable();
                    return;
                }
            } catch (\Throwable $e) {
                // Continue with file metadata fallback.
            }
        }

        $modelCandidates = $this->buildModelPathCandidates($moduleDir, $formName, $preferProjectModels);
        foreach ($modelCandidates as $modelPath) {
            $metadata = $this->readModelMetadataFromFile($modelPath);
            $tableName = trim((string) ($metadata['table_name'] ?? ''));
            if ($tableName === '') {
                continue;
            }
            $dbType = trim((string) ($metadata['db_type'] ?? ''));
            $this->dropTableByName($tableName, $dbType);
            return;
        }
    }

    protected function resolveMainFormRef(array $manifest): string
    {
        $mainRef = trim((string) ($manifest['ref'] ?? ''));
        if ($mainRef !== '') {
            return $mainRef;
        }

        $forms = $manifest['forms'] ?? [];
        if (!is_array($forms) || !is_array($forms[0] ?? null)) {
            return '';
        }

        return trim((string) ($forms[0]['ref'] ?? ''));
    }

    protected function normalizeManifestBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }
        if (!is_string($value)) {
            return false;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return false;
    }

    protected function extractFormNameFromRef(string $formRef): string
    {
        $formRef = trim($formRef);
        if ($formRef === '') {
            return '';
        }

        return trim((string) pathinfo(basename($formRef), PATHINFO_FILENAME));
    }

    /**
     * @param array<int,mixed> $forms
     * @return string[]
     */
    protected function collectFormRefsRecursive(array $forms): array
    {
        $refs = [];
        foreach ($forms as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $ref = trim((string) ($entry['ref'] ?? ''));
            if ($ref !== '') {
                $refs[] = $ref;
            }

            $children = $entry['forms'] ?? [];
            if (is_array($children) && $children !== []) {
                foreach ($this->collectFormRefsRecursive($children) as $childRef) {
                    $refs[] = $childRef;
                }
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * @return string[]
     */
    protected function buildModelPathCandidates(string $moduleDir, string $formName, bool $preferProjectModels): array
    {
        $studly = ProjectNaming::toStudlyCase($formName);
        $modelNames = array_values(array_unique(array_filter([
            $formName . 'Model',
            $studly !== '' ? $studly . 'Model' : '',
        ])));

        $projectModelsDir = rtrim($moduleDir, '/\\') . '/Project/Models';
        $moduleRootDir = rtrim($moduleDir, '/\\');
        $dirs = $preferProjectModels
            ? [$projectModelsDir, $moduleRootDir]
            : [$moduleRootDir, $projectModelsDir];

        $candidates = [];
        foreach ($dirs as $dir) {
            foreach ($modelNames as $modelName) {
                $candidates[] = $dir . '/' . $modelName . '.php';
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array{table_name:string,db_type:string}
     */
    protected function readModelMetadataFromFile(string $modelPath): array
    {
        if (!is_file($modelPath)) {
            return ['table_name' => '', 'db_type' => ''];
        }

        $content = @file_get_contents($modelPath);
        if (!is_string($content) || $content === '') {
            return ['table_name' => '', 'db_type' => ''];
        }

        $tableName = '';
        if (preg_match('/->table\\(\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $content, $matches) === 1) {
            $tableName = trim((string) $matches[1]);
        }

        $dbType = '';
        if (preg_match('/->db\\(\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $content, $matches) === 1) {
            $dbType = trim((string) $matches[1]);
        }

        return ['table_name' => $tableName, 'db_type' => $dbType];
    }

    protected function dropTableByName(string $tableName, string $dbType = ''): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        $connection = null;
        $normalizedDbType = strtolower(trim($dbType));
        try {
            if ($normalizedDbType === 'db2') {
                $connection = Get::db2();
            } elseif ($normalizedDbType !== '') {
                $connection = Get::dbConnection($normalizedDbType);
            } else {
                $connection = Get::db();
            }
        } catch (\Throwable $e) {
            $connection = null;
        }

        if (!is_object($connection)) {
            return false;
        }

        try {
            return (bool) $connection->dropTable($tableName);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
