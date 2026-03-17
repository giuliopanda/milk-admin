<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectEditAccessService
{
    /**
     * @param array{manifest_abs_path?:string}|null $project
     * @return array{
     *   can_edit:bool,
     *   message:string,
     *   inaccessible_paths:array<int,array{path:string,missing_permissions:string}>
     * }
     */
    public static function evaluate(?array $project): array
    {
        if (!is_array($project)) {
            return [
                'can_edit' => false,
                'message' => 'Project not found.',
                'inaccessible_paths' => [],
            ];
        }

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return [
                'can_edit' => false,
                'message' => 'This module is not editable because project files are not fully accessible.',
                'inaccessible_paths' => [],
            ];
        }

        $projectDir = dirname($manifestPath);
        if ($projectDir === '' || !is_dir($projectDir)) {
            return [
                'can_edit' => false,
                'message' => 'This module is not editable because project files are not fully accessible.',
                'inaccessible_paths' => [],
            ];
        }

        $issues = [];
        self::collectPathPermissionIssue($projectDir, true, $issues);
        self::collectPathPermissionIssue($manifestPath, false, $issues);

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($projectDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if (!$item instanceof \SplFileInfo) {
                    continue;
                }

                $path = (string) $item->getPathname();
                if ($path === '') {
                    continue;
                }

                self::collectPathPermissionIssue($path, $item->isDir(), $issues);
            }
        } catch (\Throwable $e) {
            self::collectPathPermissionIssue($projectDir, true, $issues);
        }

        if (empty($issues)) {
            return [
                'can_edit' => true,
                'message' => '',
                'inaccessible_paths' => [],
            ];
        }

        return [
            'can_edit' => false,
            'message' => 'This module is not editable because one or more files in the project folder do not have read and write permissions.',
            'inaccessible_paths' => $issues,
        ];
    }

    /**
     * @param array<int,array{path:string,missing_permissions:string}> $issues
     */
    private static function collectPathPermissionIssue(string $path, bool $isDir, array &$issues): void
    {
        $path = trim($path);
        if ($path === '') {
            return;
        }

        $canRead = @is_readable($path);
        $canWrite = @is_writable($path);
        if ($canRead && $canWrite) {
            return;
        }

        $missing = [];
        if (!$canRead) {
            $missing[] = 'read';
        }
        if (!$canWrite) {
            $missing[] = 'write';
        }

        $displayPath = self::toDisplayPath($path, $isDir);
        $key = $displayPath . '|' . implode('/', $missing);
        foreach ($issues as $issue) {
            $issueKey = ((string) ($issue['path'] ?? '')) . '|' . ((string) ($issue['missing_permissions'] ?? ''));
            if ($issueKey === $key) {
                return;
            }
        }

        $issues[] = [
            'path' => $displayPath,
            'missing_permissions' => implode('/', $missing),
        ];
    }

    private static function toDisplayPath(string $path, bool $isDir): string
    {
        $normalized = str_replace('\\', '/', $path);
        $localDir = str_replace('\\', '/', rtrim((string) LOCAL_DIR, '/\\'));
        if ($localDir !== '' && str_starts_with($normalized, $localDir . '/')) {
            $normalized = ltrim(substr($normalized, strlen($localDir)), '/');
        }

        if ($isDir) {
            $normalized = rtrim($normalized, '/') . '/';
        }

        return $normalized;
    }
}
