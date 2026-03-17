<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectSettingsService
{
    public const STATUS_DEVELOPMENT = 'development';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    /**
     * @param array<string,mixed>|null $project
     * @param array<string,mixed> $input
     * @return array{success:bool,msg:string}
     */
    public static function saveProjectSettings(?array $project, array $input): array
    {
        if (!is_array($project)) {
            return ['success' => false, 'msg' => 'Project not found.'];
        }

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return ['success' => false, 'msg' => 'Manifest file not found.'];
        }

        $manifest = ManifestService::readManifest($manifestPath);
        if (!is_array($manifest)) {
            return ['success' => false, 'msg' => 'Unable to read manifest file.'];
        }

        $projectTitle = trim((string) ($input['project_title'] ?? ''));
        if ($projectTitle === '') {
            return ['success' => false, 'msg' => 'Project title is required.'];
        }

        $projectDescription = trim((string) ($input['project_description'] ?? ''));
        $currentViewSingleRecord = self::normalizeBool(
            $manifest['viewSingleRecord'] ?? ($manifest['view_single_record'] ?? false)
        );
        $viewSingleRecord = array_key_exists('view_single_record', $input)
            ? self::normalizeBool($input['view_single_record'])
            : $currentViewSingleRecord;
        $currentStatus = self::resolveProjectStatusFromManifest($manifest);
        $projectStatus = self::normalizeProjectStatus($input['project_status'] ?? $currentStatus);

        $manifest['name'] = $projectTitle;
        $manifest['description'] = $projectDescription;
        unset($manifest['_name'], $manifest['_version'], $manifest['settings']);

        $manifest['viewSingleRecord'] = $viewSingleRecord;
        $manifest['projectStatus'] = $projectStatus;
        unset($manifest['project_status']);

        // Menu configuration
        $menuName = trim((string) ($input['menu_name'] ?? ''));
        if ($menuName !== '' && $menuName !== $projectTitle) {
            $manifest['menu'] = $menuName;
        } else {
            unset($manifest['menu']);
        }

        $menuIcon = trim((string) ($input['menu_icon'] ?? ''));
        if ($menuIcon !== '') {
            $manifest['menuIcon'] = $menuIcon;
        } else {
            unset($manifest['menuIcon']);
        }

        $selectMenu = trim((string) ($input['select_menu'] ?? ''));
        if ($selectMenu !== '') {
            $manifest['selectMenu'] = $selectMenu;
        } else {
            unset($manifest['selectMenu'], $manifest['selectedMenu'], $manifest['select_menu']);
        }

        if (!ManifestService::writeManifest($manifestPath, $manifest)) {
            return ['success' => false, 'msg' => 'Unable to write manifest file.'];
        }

        return ['success' => true, 'msg' => 'Project settings updated.'];
    }

    /**
     * @param array<string,mixed> $manifest
     */
    public static function resolveProjectStatusFromManifest(array $manifest): string
    {
        return self::normalizeProjectStatus(
            $manifest['projectStatus']
                ?? ($manifest['project_status']
                    ?? ($manifest['status'] ?? self::STATUS_DEVELOPMENT))
        );
    }

    public static function normalizeProjectStatus(mixed $value): string
    {
        $status = strtolower(trim((string) $value));

        return match ($status) {
            'development', 'dev' => self::STATUS_DEVELOPMENT,
            'active', 'production', 'prod' => self::STATUS_ACTIVE,
            'suspended', 'suspend', 'paused', 'pause' => self::STATUS_SUSPENDED,
            'closed', 'close' => self::STATUS_CLOSED,
            default => self::STATUS_DEVELOPMENT,
        };
    }

    public static function canEditStructureByStatus(string $status): bool
    {
        $status = self::normalizeProjectStatus($status);
        return in_array($status, [self::STATUS_DEVELOPMENT, self::STATUS_SUSPENDED], true);
    }

    public static function projectStatusLabel(string $status): string
    {
        $status = self::normalizeProjectStatus($status);
        return match ($status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_CLOSED => 'Closed',
            default => 'Development',
        };
    }

    /**
     * @param array<string,mixed>|null $project
     * @return array{
     *   can_edit_structure:bool,
     *   status:string,
     *   status_label:string,
     *   message:string
     * }
     */
    public static function evaluateStructureEditAccess(?array $project): array
    {
        $manifest = is_array($project) && is_array($project['manifest_data'] ?? null)
            ? $project['manifest_data']
            : null;
        if (!is_array($manifest)) {
            $manifestPath = trim((string) (is_array($project) ? ($project['manifest_abs_path'] ?? '') : ''));
            $manifest = $manifestPath !== '' ? ManifestService::readManifest($manifestPath) : null;
        }

        $status = is_array($manifest)
            ? self::resolveProjectStatusFromManifest($manifest)
            : self::STATUS_DEVELOPMENT;
        $statusLabel = self::projectStatusLabel($status);
        $canEditStructure = self::canEditStructureByStatus($status);

        return [
            'can_edit_structure' => $canEditStructure,
            'status' => $status,
            'status_label' => $statusLabel,
            'message' => $canEditStructure
                ? ''
                : 'Structure editing is disabled because project status is ' . $statusLabel . '.',
        ];
    }

    public static function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
