<?php
namespace Extensions\Projects\Classes\Module;

use App\MessagesHandler;
use Extensions\Projects\Classes\ProjectJsonStore;
use Extensions\Projects\Classes\ProjectManifestIndex;
use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

class ProjectPermissionService
{
    protected const SPECIAL_PERMISSION_MAIN_TABLE_EDIT = 'main_table_edit';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_VIEW = 'main_table_view';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_SOFT_DELETE = 'main_table_soft_delete';
    protected const SPECIAL_PERMISSION_MAIN_TABLE_HARD_DELETE = 'main_table_hard_delete';

    protected ProjectModuleLocator $locator;

    /** @var callable(string):bool */
    protected $permissionChecker;

    /** @var callable():string */
    protected $modulePageResolver;

    protected bool $specialPermissionManifestIdResolved = false;
    protected string $specialPermissionManifestId = '';

    /** @var array{block_title:string,block_name:string,row_name:string} */
    protected array $specialPermissionUiMeta = [
        'block_title' => '',
        'block_name' => 'Access Data',
        'row_name' => '',
    ];

    protected bool $specialPermissionUiMetaResolved = false;

    /**
     * @param callable(string):bool $permissionChecker
     * @param callable():string $modulePageResolver
     */
    public function __construct(ProjectModuleLocator $locator, callable $permissionChecker, callable $modulePageResolver)
    {
        $this->locator = $locator;
        $this->permissionChecker = $permissionChecker;
        $this->modulePageResolver = $modulePageResolver;
    }

    public function resetCache(): void
    {
        $this->specialPermissionManifestIdResolved = false;
        $this->specialPermissionManifestId = '';
        $this->specialPermissionUiMetaResolved = false;
        $this->specialPermissionUiMeta = [
            'block_title' => '',
            'block_name' => 'Access Data',
            'row_name' => '',
        ];
    }

    /**
     * @return array<int,array{
     *   permission:string,
     *   default:bool,
     *   block_title:string,
     *   block_name:string,
     *   row_name:string,
     *   row_order:int,
     *   ui_group:string,
     *   permission_label:string
     * }>
     */
    public function getAdditionalPermissionsWithDefault(): array
    {
        $uiMeta = $this->resolveSpecialPermissionUiMeta();
        $store = $this->locator->resolveProjectsJsonStore();
        if (!$store instanceof ProjectJsonStore) {
            return [];
        }

        $index = $store->manifestIndex();
        if (!$index instanceof ProjectManifestIndex) {
            return [];
        }

        $manifestAdditionalPermissionKeys = $this->resolveManifestAdditionalPermissionKeys($store);
        $permissions = [];
        $knownPermissions = [];
        $rowOrder = 0;

        foreach ($index->getNodes() as $formName => $node) {
            if (!is_array($node)) {
                continue;
            }

            $isRoot = empty($node['parent_form_name']);
            $rowName = $store->schemaTitle($formName, ProjectNaming::toTitle($formName));
            if ($rowName === '') {
                $rowName = $isRoot ? $uiMeta['row_name'] : $formName;
            }
            $currentRowOrder = $isRoot ? 0 : ($rowOrder + 1);

            foreach ($this->buildSupportedPermissionActionsForNode($node, $isRoot, []) as $permissionAction) {
                $permissionName = $this->buildFormSpecialPermissionName($formName, $permissionAction, $isRoot);
                if ($permissionName === '' || isset($knownPermissions[$permissionName])) {
                    continue;
                }

                $permissions[] = [
                    'permission' => 'project.' . $permissionName,
                    'default' => true,
                    'block_title' => $uiMeta['block_title'],
                    'block_name' => $uiMeta['block_name'],
                    'row_name' => $rowName,
                    'row_order' => $currentRowOrder,
                    'ui_group' => 'table',
                    'permission_label' => '',
                ];
                $knownPermissions[$permissionName] = true;
            }

            if (!$isRoot) {
                $rowOrder++;
            }
        }

        foreach ($manifestAdditionalPermissionKeys as $permissionKey) {
            $normalizedPermissionKey = $this->normalizeAdditionalSpecialPermissionKey($permissionKey);
            if ($normalizedPermissionKey === '') {
                continue;
            }

            $permissionName = $this->buildFormSpecialPermissionName('', $normalizedPermissionKey, true);
            if ($permissionName === '' || isset($knownPermissions[$permissionName])) {
                continue;
            }

            $permissions[] = [
                'permission' => 'project.' . $permissionName,
                'default' => true,
                'block_title' => $uiMeta['block_title'],
                'block_name' => $uiMeta['block_name'],
                'row_name' => '',
                'row_order' => 0,
                'ui_group' => 'additional',
                'permission_label' => ProjectNaming::toTitle($normalizedPermissionKey),
            ];
            $knownPermissions[$permissionName] = true;
        }

        return $permissions;
    }

    /**
     * @param array<int,array{permission:string,default:bool}>|mixed $permissions
     * @return array<int,array{permission:string,default:bool}>
     */
    public function collectSpecialPermissionsForHook(mixed $permissions = [], ?string $modulePage = null): array
    {
        $normalized = is_array($permissions) ? $permissions : [];
        $requestedModulePage = strtolower(trim((string) $modulePage));
        $currentModulePage = strtolower(trim((string) call_user_func($this->modulePageResolver)));

        if ($requestedModulePage !== '' && $currentModulePage !== '' && $requestedModulePage !== $currentModulePage) {
            return $normalized;
        }

        $known = [];
        foreach ($normalized as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $permission = trim((string) ($entry['permission'] ?? ''));
            if ($permission !== '') {
                $known[$permission] = true;
            }
        }

        foreach ($this->getAdditionalPermissionsWithDefault() as $entry) {
            $permission = trim((string) ($entry['permission'] ?? ''));
            if ($permission === '' || isset($known[$permission])) {
                continue;
            }

            $normalized[] = $entry;
            $known[$permission] = true;
        }

        return $normalized;
    }

    public function isDataMutationAllowedForContext(array $context): bool
    {
        return ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey(
                $context,
                ['project_allows_data_mutation', 'projectAllowsDataMutation'],
                true
            )
        );
    }

    public function buildDataMutationBlockedMessage(array $context, string $action): string
    {
        $status = $this->normalizeProjectStatus(
            ProjectJsonStore::resolveAliasedKey(
                $context,
                ['project_status', 'projectStatus'],
                'development'
            )
        );
        $statusLabel = ucfirst($status);
        $normalizedAction = strtolower(trim($action));
        if ($normalizedAction === '') {
            $normalizedAction = 'modify records';
        }

        return "Cannot {$normalizedAction}: project status is {$statusLabel}.";
    }

    public function normalizeProjectStatus(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        return match ($status) {
            'active', 'production', 'prod' => 'active',
            'suspended', 'suspend', 'paused', 'pause' => 'suspended',
            'closed', 'close' => 'closed',
            default => 'development',
        };
    }

    public function projectStatusAllowsDataMutation(string $status): bool
    {
        return !in_array($this->normalizeProjectStatus($status), ['suspended', 'closed'], true);
    }

    public function hasNonEmptyDateValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if ($value instanceof \DateTimeInterface) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_int($value) || is_float($value)) {
            return true;
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return trim((string) $value) !== '';
            }
            return true;
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return trim((string) $value) !== '';
    }

    /**
     * @param array<string,mixed> $specialPermissions
     */
    public function hasConfiguredSpecialPermission(array $specialPermissions, string $permissionAction): bool
    {
        $permissionAction = $this->normalizeAdditionalSpecialPermissionKey($permissionAction);
        if ($permissionAction === '') {
            return true;
        }

        $permissionName = trim((string) ($specialPermissions[$permissionAction] ?? ''));
        if ($permissionName === '') {
            return true;
        }

        return (bool) call_user_func($this->permissionChecker, $permissionName);
    }

    /**
     * @param array<string,mixed> $specialPermissions
     */
    public function canManageDeleteByConfiguredPermissions(
        bool $softDeleteEnabled,
        bool $allowDeleteRecord,
        array $specialPermissions
    ): bool {
        $canSoftDelete = !$softDeleteEnabled || $this->hasConfiguredSpecialPermission($specialPermissions, 'soft_delete');
        $canHardDelete = !$allowDeleteRecord || $this->hasConfiguredSpecialPermission($specialPermissions, 'hard_delete');

        if ($softDeleteEnabled && $allowDeleteRecord) {
            return $canSoftDelete || $canHardDelete;
        }
        if ($softDeleteEnabled) {
            return $canSoftDelete;
        }
        if ($allowDeleteRecord) {
            return $canHardDelete;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $node
     * @param array<int,string> $manifestAdditionalPermissionKeys
     * @return array<string,string>
     */
    public function buildContextSpecialPermissions(
        string $formName,
        array $node,
        bool $isRoot,
        array $manifestAdditionalPermissionKeys
    ): array {
        $specialPermissions = [];
        foreach ($this->buildSupportedPermissionActionsForNode($node, $isRoot, $manifestAdditionalPermissionKeys) as $permissionAction) {
            $permissionName = $this->buildFormSpecialPermissionName($formName, $permissionAction, $isRoot);
            if ($permissionName === '') {
                continue;
            }

            $specialPermissions[$permissionAction] = $permissionName;
        }

        return $specialPermissions;
    }

    /**
     * @return array<int,string>
     */
    public function resolveManifestAdditionalPermissionKeys(?ProjectJsonStore $store = null): array
    {
        try {
            $resolvedStore = $store instanceof ProjectJsonStore ? $store : $this->locator->resolveProjectsJsonStore();
            if (!$resolvedStore instanceof ProjectJsonStore) {
                return [];
            }

            $manifest = $resolvedStore->manifest();
            if (!is_array($manifest)) {
                return [];
            }

            $rawPermissions = ProjectJsonStore::resolveAliasedKey(
                $manifest,
                ['additionalPermissions', 'additional_permissions'],
                []
            );
            if (!is_array($rawPermissions)) {
                return [];
            }

            $permissions = [];
            foreach ($rawPermissions as $rawPermission) {
                $permissionKey = $this->normalizeAdditionalSpecialPermissionKey((string) $rawPermission);
                if ($permissionKey === '') {
                    continue;
                }

                $permissions[$permissionKey] = true;
            }

            $result = array_keys($permissions);
            sort($result);
            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    public function normalizeAdditionalSpecialPermissionKey(string $permissionKey): string
    {
        $normalized = strtolower(trim($permissionKey));
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized) ?? '';
        $normalized = preg_replace('/_+/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }

    public function ensureConfiguredPermission(array $context, string $permissionAction, string $errorMessage): bool
    {
        if ($this->hasConfiguredSpecialPermission(
            is_array($context['special_permissions'] ?? null) ? $context['special_permissions'] : [],
            $permissionAction
        )) {
            return true;
        }

        MessagesHandler::addError($errorMessage);
        return false;
    }

    public function canManageDeleteRecordsForContext(array $context): bool
    {
        if (array_key_exists('can_manage_delete_records', $context)) {
            return ProjectJsonStore::normalizeBool($context['can_manage_delete_records']);
        }

        $softDeleteEnabled = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($context, ['soft_delete', 'softDelete'], false)
        );
        $allowDeleteRecord = true;
        if (ProjectJsonStore::hasAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'])) {
            $allowDeleteRecord = ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'], true)
            );
        }

        return $this->canManageDeleteByConfiguredPermissions(
            $softDeleteEnabled,
            $allowDeleteRecord,
            is_array($context['special_permissions'] ?? null) ? $context['special_permissions'] : []
        );
    }

    public function isDeleteDisabledByConfig(array $context): bool
    {
        $softDeleteEnabled = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($context, ['soft_delete', 'softDelete'], false)
        );

        $allowDeleteRecord = true;
        if (ProjectJsonStore::hasAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'])) {
            $allowDeleteRecord = ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($context, ['allow_delete_record', 'allowDeleteRecord'], false)
            );
        }

        return !$softDeleteEnabled && !$allowDeleteRecord;
    }

    protected function buildFormSpecialPermissionName(string $formName, string $permissionAction, bool $isRoot): string
    {
        $manifestId = $this->resolveSpecialPermissionManifestId();
        $permissionKey = $this->buildFormSpecialPermissionKey($formName, $permissionAction, $isRoot);
        if ($manifestId === '' || $permissionKey === '') {
            return '';
        }

        return $manifestId . '.' . $permissionKey;
    }

    protected function buildFormSpecialPermissionKey(string $formName, string $permissionAction, bool $isRoot): string
    {
        $normalizedPermissionAction = $this->normalizeAdditionalSpecialPermissionKey($permissionAction);
        if ($normalizedPermissionAction === '') {
            return '';
        }

        if ($isRoot) {
            return match ($normalizedPermissionAction) {
                'view' => self::SPECIAL_PERMISSION_MAIN_TABLE_VIEW,
                'edit' => self::SPECIAL_PERMISSION_MAIN_TABLE_EDIT,
                'soft_delete' => self::SPECIAL_PERMISSION_MAIN_TABLE_SOFT_DELETE,
                'hard_delete' => self::SPECIAL_PERMISSION_MAIN_TABLE_HARD_DELETE,
                default => $normalizedPermissionAction,
            };
        }

        if (!in_array($normalizedPermissionAction, ['view', 'edit', 'soft_delete', 'hard_delete'], true)) {
            return $normalizedPermissionAction;
        }

        $formScope = ProjectNaming::toSnake($formName);
        if ($formScope === '') {
            return '';
        }

        return $formScope . '__' . $normalizedPermissionAction;
    }

    /**
     * @param array<string,mixed> $node
     * @param array<int,string> $manifestAdditionalPermissionKeys
     * @return array<int,string>
     */
    protected function buildSupportedPermissionActionsForNode(
        array $node,
        bool $isRoot,
        array $manifestAdditionalPermissionKeys
    ): array {
        $permissionActions = [];
        if (!array_key_exists('allow_view', $node) || (bool) $node['allow_view']) {
            $permissionActions[] = 'view';
        }
        if (!array_key_exists('allow_edit', $node) || (bool) $node['allow_edit']) {
            $permissionActions[] = 'edit';
        }
        if (!empty($node['soft_delete'])) {
            $permissionActions[] = 'soft_delete';
        }
        if (!array_key_exists('allow_delete_record', $node) || (bool) $node['allow_delete_record']) {
            $permissionActions[] = 'hard_delete';
        }

        foreach ($manifestAdditionalPermissionKeys as $permissionKey) {
            $normalizedPermissionKey = $this->normalizeAdditionalSpecialPermissionKey($permissionKey);
            if ($normalizedPermissionKey === '') {
                continue;
            }
            $permissionActions[] = $normalizedPermissionKey;
        }

        if ($isRoot) {
            return array_values(array_unique($permissionActions));
        }

        $unique = [];
        foreach ($permissionActions as $permissionAction) {
            $unique[$permissionAction] = true;
        }

        return array_keys($unique);
    }

    protected function resolveSpecialPermissionManifestId(): string
    {
        if ($this->specialPermissionManifestIdResolved) {
            return $this->specialPermissionManifestId;
        }

        $this->specialPermissionManifestIdResolved = true;
        $manifestData = ProjectJsonStore::getCurrentManifestData((string) call_user_func($this->modulePageResolver));
        if (!is_array($manifestData)) {
            $this->specialPermissionManifestId = '';
            return '';
        }

        $this->specialPermissionManifestId = strtolower(trim((string) ($manifestData['id'] ?? '')));
        return $this->specialPermissionManifestId;
    }

    /**
     * @return array{block_title:string,block_name:string,row_name:string}
     */
    protected function resolveSpecialPermissionUiMeta(): array
    {
        if ($this->specialPermissionUiMetaResolved) {
            return $this->specialPermissionUiMeta;
        }

        $this->specialPermissionUiMetaResolved = true;
        $blockTitle = '';
        $rowName = '';
        $blockName = 'Access Data';

        try {
            $store = $this->locator->resolveProjectsJsonStore();
            if ($store instanceof ProjectJsonStore) {
                $manifestData = $store->manifest();
                if (is_array($manifestData)) {
                    $blockTitle = trim((string) ($manifestData['name'] ?? ''));
                }

                $index = $store->manifestIndex();
                if ($index instanceof ProjectManifestIndex) {
                    $rootFormNames = $index->getRootFormNames();
                    $rootFormName = trim((string) ($rootFormNames[0] ?? ''));
                    if ($rootFormName !== '') {
                        $rowName = $store->schemaTitle($rootFormName, $rootFormName);
                    }
                }
            }
        } catch (\Throwable) {
            // Keep fallbacks.
        }

        if ($blockTitle === '') {
            $blockTitle = trim((string) call_user_func($this->modulePageResolver));
        }
        if ($rowName === '') {
            $rowName = 'Main Table';
        }

        $this->specialPermissionUiMeta = [
            'block_title' => $blockTitle,
            'block_name' => $blockName,
            'row_name' => $rowName,
        ];

        return $this->specialPermissionUiMeta;
    }
}
