<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectFormDisplayLogicService
{
    /**
     * @param array<string,mixed>|null $project
     * @return array{success:bool,msg:string,display_logic:string}
     */
    public static function readDisplayLogicFromProject(?array $project, string $requestedRef): array
    {
        if (!is_array($project)) {
            return ['success' => false, 'msg' => 'Project not found.', 'display_logic' => ''];
        }

        $refBase = basename(trim($requestedRef));
        if ($refBase === '') {
            return ['success' => false, 'msg' => 'Invalid ref.', 'display_logic' => ''];
        }

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return ['success' => false, 'msg' => 'Manifest file not found.', 'display_logic' => ''];
        }

        $manifest = ManifestService::readManifest($manifestPath);
        if (!is_array($manifest)) {
            return ['success' => false, 'msg' => 'Failed to read manifest file.', 'display_logic' => ''];
        }

        $node = self::findManifestNodeByRef($manifest, $refBase);
        if (!is_array($node)) {
            return ['success' => false, 'msg' => 'Form ref not found in manifest.', 'display_logic' => ''];
        }

        return [
            'success' => true,
            'msg' => '',
            'display_logic' => trim((string) ($node['showIf'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed>|null $project
     * @return array{success:bool,msg:string}
     */
    public static function applyDisplayLogicToProject(?array $project, string $requestedRef, string $displayLogic): array
    {
        if (!is_array($project)) {
            return ['success' => false, 'msg' => 'Project not found.'];
        }

        $refBase = basename(trim($requestedRef));
        if ($refBase === '') {
            return ['success' => false, 'msg' => 'Invalid ref.'];
        }
        $displayLogic = trim($displayLogic);

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return ['success' => false, 'msg' => 'Manifest file not found.'];
        }

        $manifest = ManifestService::readManifest($manifestPath);
        if (!is_array($manifest)) {
            return ['success' => false, 'msg' => 'Failed to read manifest file.'];
        }

        $locked = false;
        $found = self::applyShowIfToManifest($manifest, $refBase, $displayLogic, $locked);
        if ($locked) {
            return ['success' => false, 'msg' => 'Cannot modify this table: manifest marks it as existingTable.'];
        }
        if (!$found) {
            return ['success' => false, 'msg' => 'Form ref not found in manifest.'];
        }

        if (!ManifestService::writeManifest($manifestPath, $manifest)) {
            return ['success' => false, 'msg' => 'Failed to write manifest file.'];
        }

        return ['success' => true, 'msg' => 'Form display logic saved.'];
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>|null
     */
    private static function findManifestNodeByRef(array $manifest, string $refBase): ?array
    {
        $rootRef = basename(trim((string) ($manifest['ref'] ?? '')));
        if ($rootRef !== '' && strcasecmp($rootRef, $refBase) === 0) {
            return $manifest;
        }

        $forms = is_array($manifest['forms'] ?? null) ? $manifest['forms'] : [];
        return self::findNodeRecursive($forms, $refBase);
    }

    /**
     * @param array<int,mixed> $nodes
     * @return array<string,mixed>|null
     */
    private static function findNodeRecursive(array $nodes, string $refBase): ?array
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
                $found = self::findNodeRecursive($children, $refBase);
                if (is_array($found)) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private static function applyShowIfToManifest(array &$manifest, string $refBase, string $displayLogic, bool &$locked): bool
    {
        $rootRef = basename(trim((string) ($manifest['ref'] ?? '')));
        if ($rootRef !== '' && strcasecmp($rootRef, $refBase) === 0) {
            $locked = self::normalizeBool($manifest['existingTable'] ?? ($manifest['existing_table'] ?? false));
            if ($locked) {
                return true;
            }
            self::applyShowIf($manifest, $displayLogic);
            return true;
        }

        $forms = is_array($manifest['forms'] ?? null) ? $manifest['forms'] : [];
        $found = self::applyShowIfRecursive($forms, $refBase, $displayLogic, $locked);
        if ($found) {
            $manifest['forms'] = $forms;
        }

        return $found;
    }

    /**
     * @param array<int,mixed> $nodes
     */
    private static function applyShowIfRecursive(array &$nodes, string $refBase, string $displayLogic, bool &$locked): bool
    {
        foreach ($nodes as &$node) {
            if (!is_array($node)) {
                continue;
            }

            $nodeRef = basename(trim((string) ($node['ref'] ?? '')));
            if ($nodeRef !== '' && strcasecmp($nodeRef, $refBase) === 0) {
                $locked = self::normalizeBool($node['existingTable'] ?? ($node['existing_table'] ?? false));
                if (!$locked) {
                    self::applyShowIf($node, $displayLogic);
                }
                return true;
            }

            $children = is_array($node['forms'] ?? null) ? $node['forms'] : [];
            if (!empty($children) && self::applyShowIfRecursive($children, $refBase, $displayLogic, $locked)) {
                $node['forms'] = $children;
                return true;
            }
        }
        unset($node);

        return false;
    }

    /**
     * @param array<string,mixed> $node
     */
    private static function applyShowIf(array &$node, string $displayLogic): void
    {
        if ($displayLogic === '') {
            unset($node['showIf']);
            return;
        }

        $node['showIf'] = $displayLogic;
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
}
