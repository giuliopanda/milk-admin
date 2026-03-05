<?php
namespace Extensions\Projects\Classes\Module;

use App\Logs;

!defined('MILK_DIR') && die();

class ProjectInstallUpdateService
{
    /**
     * Build/update project tables from manifest.
     *
     * @param callable(string,string):(?string) $resolveModelClassForForm
     */
    public function syncProjectTables(
        string $moduleNamespace,
        string $moduleDir,
        string $manifestPath,
        callable $resolveModelClassForForm,
        bool $includeMain = false
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
        $mainFormName = $this->extractFormNameFromRef($mainFormRef);
        $mainExistingTable = $this->normalizeManifestBool($manifest['existingTable'] ?? false);

        $forms = $manifest['forms'] ?? [];
        $entries = $this->collectFormEntriesRecursive(is_array($forms) ? $forms : []);

        if ($includeMain && $mainFormRef !== '' && !$mainExistingTable) {
            array_unshift($entries, [
                'ref' => $mainFormRef,
                'existing_table' => false,
            ]);
        }

        $entries = $this->uniqueEntriesByRef($entries);
        if (empty($entries)) {
            return true;
        }

        $ok = true;
        foreach ($entries as $entry) {
            $formRef = trim((string) ($entry['ref'] ?? ''));
            if ($formRef === '') {
                continue;
            }

            $formName = $this->extractFormNameFromRef($formRef);
            if ($formName === '') {
                continue;
            }

            $isMain = $mainFormName !== '' && strcasecmp($formName, $mainFormName) === 0;
            if ($isMain && !$includeMain) {
                continue;
            }

            $entryExistingTable = $this->normalizeManifestBool($entry['existing_table'] ?? false);
            if (($isMain && $mainExistingTable) || $entryExistingTable) {
                continue;
            }

            $buildResult = $this->buildTableForFormRef(
                $resolveModelClassForForm,
                $moduleNamespace,
                $moduleDir,
                $formRef
            );

            if (!$buildResult) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * @param callable(string,string):(?string) $resolveModelClassForForm
     */
    protected function buildTableForFormRef(
        callable $resolveModelClassForForm,
        string $moduleNamespace,
        string $moduleDir,
        string $formRef
    ): bool {
        $formName = $this->extractFormNameFromRef($formRef);
        if ($formName === '') {
            return false;
        }

        $modelClass = $resolveModelClassForForm($moduleNamespace, $formName);
        if (!is_string($modelClass) || $modelClass === '' || !class_exists($modelClass)) {
            Logs::set('SYSTEM', "Projects install/update: missing model for form '{$formRef}'.", 'ERROR');
            return false;
        }

        try {
            $model = new $modelClass();
            if (!method_exists($model, 'buildTable')) {
                Logs::set(
                    'SYSTEM',
                    "Projects install/update: model '{$modelClass}' has no buildTable() for form '{$formRef}'.",
                    'ERROR'
                );
                return false;
            }

            $model->buildTable();
            $lastError = trim((string) ($model->last_error ?? ''));
            if ($lastError !== '') {
                Logs::set(
                    'SYSTEM',
                    "Projects install/update: model '{$modelClass}' error for form '{$formRef}' - {$lastError}",
                    'ERROR'
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Logs::set(
                'SYSTEM',
                "Projects install/update: failed for form '{$formRef}' - {$e->getMessage()}",
                'ERROR'
            );
            return false;
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
     * @return array<int,array{ref:string,existing_table:bool}>
     */
    protected function collectFormEntriesRecursive(array $forms): array
    {
        $entries = [];
        foreach ($forms as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $ref = trim((string) ($entry['ref'] ?? ''));
            if ($ref !== '') {
                $entries[] = [
                    'ref' => $ref,
                    'existing_table' => $this->normalizeManifestBool(
                        $entry['existingTable'] ?? ($entry['existing_table'] ?? false)
                    ),
                ];
            }

            $children = $entry['forms'] ?? [];
            if (is_array($children) && $children !== []) {
                foreach ($this->collectFormEntriesRecursive($children) as $childEntry) {
                    $entries[] = $childEntry;
                }
            }
        }

        return $entries;
    }

    /**
     * @param array<int,array{ref:string,existing_table:bool}> $entries
     * @return array<int,array{ref:string,existing_table:bool}>
     */
    protected function uniqueEntriesByRef(array $entries): array
    {
        $result = [];
        $seen = [];

        foreach ($entries as $entry) {
            $ref = trim((string) ($entry['ref'] ?? ''));
            if ($ref === '') {
                continue;
            }

            $key = strtolower($ref);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = [
                'ref' => $ref,
                'existing_table' => $this->normalizeManifestBool($entry['existing_table'] ?? false),
            ];
        }

        return $result;
    }
}
