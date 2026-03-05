<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectFormConfigService
{
    /**
     * @param array<string,mixed>|null $project
     * @return array{
     *   requested_ref:string,
     *   resolved_ref:string,
     *   form_name:string,
     *   form_display_logic:string,
     *   max_records:string,
     *   edit_display:string,
     *   soft_delete:bool,
     *   supports_soft_delete:bool,
     *   allow_delete_record:bool,
     *   allow_edit:bool,
     *   show_created:bool,
     *   show_updated:bool,
     *   main_table_count_visibility:string,
     *   default_order_enabled:bool,
     *   default_order_field:string,
     *   default_order_direction:string,
     *   default_order_field_options:array<string,string>,
     *   can_edit:bool,
     *   error:string
     * }
     */
    public static function buildPageData(?array $project, string $requestedRef): array
    {
        $payload = [
            'requested_ref' => trim($requestedRef),
            'resolved_ref' => '',
            'form_name' => '',
            'form_display_logic' => '',
            'max_records' => 'n',
            'edit_display' => 'page',
            'soft_delete' => false,
            'supports_soft_delete' => false,
            'allow_delete_record' => true,
            'allow_edit' => true,
            'show_created' => false,
            'show_updated' => false,
            'main_table_count_visibility' => 'auto',
            'default_order_enabled' => false,
            'default_order_field' => '',
            'default_order_direction' => 'asc',
            'default_order_field_options' => ['id' => 'ID'],
            'can_edit' => false,
            'error' => '',
        ];

        if (!is_array($project)) {
            $payload['error'] = 'Project not found.';
            return $payload;
        }

        $refBase = basename($payload['requested_ref']);
        if ($refBase === '') {
            $payload['error'] = 'Invalid ref.';
            return $payload;
        }

        $payload['resolved_ref'] = $refBase;
        $payload['form_name'] = trim((string) pathinfo($refBase, PATHINFO_FILENAME));

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            $payload['error'] = 'Manifest file not found.';
            return $payload;
        }

        $manifest = ManifestService::readManifest($manifestPath);
        if (!is_array($manifest)) {
            $payload['error'] = 'Failed to read manifest file.';
            return $payload;
        }

        $node = self::findManifestNodeByRef($manifest, $refBase);
        if (!is_array($node)) {
            $payload['error'] = 'Form ref not found in manifest.';
            return $payload;
        }

        $payload['form_display_logic'] = trim((string) ($node['showIf'] ?? ''));
        $payload['max_records'] = ManifestService::normalizeMaxRecords($node['max_records'] ?? 'n');
        $payload['edit_display'] = self::normalizeEditDisplay((string) ($node['editDisplay'] ?? 'page'));
        $payload['soft_delete'] = self::normalizeBool($node['softDelete'] ?? ($node['soft_delete'] ?? false));
        $payload['allow_delete_record'] = self::normalizeBool(
            $node['allowDeleteRecord'] ?? ($node['allow_delete_record'] ?? true)
        );
        $payload['allow_edit'] = self::normalizeBool($node['allowEdit'] ?? ($node['allow_edit'] ?? true));
        $payload['default_order_enabled'] = self::normalizeBool(
            $node['defaultOrderEnabled'] ?? ($node['default_order_enabled'] ?? false)
        );
        $payload['default_order_field'] = trim((string) (
            $node['defaultOrderField'] ?? ($node['default_order_field'] ?? '')
        ));
        $payload['default_order_direction'] = self::normalizeOrderDirection((string) (
            $node['defaultOrderDirection'] ?? ($node['default_order_direction'] ?? 'asc')
        ));

        $schema = self::readSchemaForRef($project, $refBase);
        $payload['default_order_field_options'] = self::extractOrderFieldOptionsFromSchema($schema);
        $payload['supports_soft_delete'] = self::schemaHasAllFields($schema, [
            'deleted_at' => true,
            'deleted_by' => true,
        ]);
        if (!$payload['supports_soft_delete']) {
            $payload['soft_delete'] = false;
        }
        $payload['show_created'] = self::isAuditGroupShown($schema, self::createdAuditFieldNames());
        $payload['show_updated'] = self::isAuditGroupShown($schema, self::updatedAuditFieldNames());
        $payload['main_table_count_visibility'] = self::normalizeChildCountColumnMode(
            $node['childCountColumn'] ?? ($node['child_count_column'] ?? 'auto')
        );

        if (!$payload['default_order_enabled']) {
            $payload['default_order_field'] = '';
            $payload['default_order_direction'] = 'asc';
        } elseif (!self::isAllowedOrderField(
            $payload['default_order_field'],
            $payload['default_order_field_options']
        )) {
            $payload['default_order_enabled'] = false;
            $payload['default_order_field'] = '';
            $payload['default_order_direction'] = 'asc';
        }

        $payload['can_edit'] = true;

        return $payload;
    }

    /**
     * @param array<string,mixed>|null $project
     * @param array<string,mixed> $postData
     * @return array{success:bool,msg:string,resolved_ref:string}
     */
    public static function saveConfig(?array $project, string $requestedRef, array $postData): array
    {
        if (!is_array($project)) {
            return ['success' => false, 'msg' => 'Project not found.', 'resolved_ref' => ''];
        }

        $refBase = basename(trim($requestedRef));
        if ($refBase === '') {
            return ['success' => false, 'msg' => 'Invalid ref.', 'resolved_ref' => ''];
        }

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return ['success' => false, 'msg' => 'Manifest file not found.', 'resolved_ref' => $refBase];
        }

        $manifest = ManifestService::readManifest($manifestPath);
        if (!is_array($manifest)) {
            return ['success' => false, 'msg' => 'Failed to read manifest file.', 'resolved_ref' => $refBase];
        }

        $currentNode = self::findManifestNodeByRef($manifest, $refBase);
        if (!is_array($currentNode)) {
            return ['success' => false, 'msg' => 'Form ref not found in manifest.', 'resolved_ref' => $refBase];
        }

        $schemaPath = self::resolveSchemaPath($project, $refBase);
        $schema = self::readSchemaFile($schemaPath);
        $supportsSoftDelete = self::schemaHasAllFields($schema, [
            'deleted_at' => true,
            'deleted_by' => true,
        ]);
        $currentShowCreated = self::isAuditGroupShown($schema, self::createdAuditFieldNames());
        $currentShowUpdated = self::isAuditGroupShown($schema, self::updatedAuditFieldNames());
        $orderFieldOptions = self::extractOrderFieldOptionsFromSchema($schema);

        $displayLogic = trim((string) ($postData['form_display_logic'] ?? ($currentNode['showIf'] ?? '')));
        $maxRecordsResult = self::parseMaxRecords($postData['max_records'] ?? ($currentNode['max_records'] ?? 'n'));
        if (!$maxRecordsResult['success']) {
            return [
                'success' => false,
                'msg' => $maxRecordsResult['msg'],
                'resolved_ref' => $refBase,
            ];
        }

        $currentEditDisplay = self::normalizeEditDisplay((string) ($currentNode['editDisplay'] ?? 'page'));
        $currentSoftDelete = self::normalizeBool($currentNode['softDelete'] ?? ($currentNode['soft_delete'] ?? false));
        $currentAllowDeleteRecord = self::normalizeBool(
            $currentNode['allowDeleteRecord'] ?? ($currentNode['allow_delete_record'] ?? true)
        );
        $currentAllowEdit = self::normalizeBool($currentNode['allowEdit'] ?? ($currentNode['allow_edit'] ?? true));
        $currentDefaultOrderEnabled = self::normalizeBool(
            $currentNode['defaultOrderEnabled'] ?? ($currentNode['default_order_enabled'] ?? false)
        );
        $currentDefaultOrderField = trim((string) (
            $currentNode['defaultOrderField'] ?? ($currentNode['default_order_field'] ?? '')
        ));
        $currentDefaultOrderDirection = self::normalizeOrderDirection((string) (
            $currentNode['defaultOrderDirection'] ?? ($currentNode['default_order_direction'] ?? 'asc')
        ));
        $currentChildCountColumnMode = self::normalizeChildCountColumnMode(
            $currentNode['childCountColumn'] ?? ($currentNode['child_count_column'] ?? 'auto')
        );

        $editDisplay = array_key_exists('edit_display', $postData)
            ? self::normalizeEditDisplay((string) $postData['edit_display'])
            : $currentEditDisplay;
        $allowDeleteRecord = array_key_exists('allow_delete_record', $postData)
            ? self::normalizeBool($postData['allow_delete_record'])
            : $currentAllowDeleteRecord;
        $allowEdit = array_key_exists('allow_edit', $postData)
            ? self::normalizeBool($postData['allow_edit'])
            : $currentAllowEdit;
        $softDelete = array_key_exists('soft_delete', $postData)
            ? self::normalizeBool($postData['soft_delete'])
            : $currentSoftDelete;
        if (!$supportsSoftDelete) {
            $softDelete = false;
        }

        $showCreated = array_key_exists('show_created', $postData)
            ? self::normalizeBool($postData['show_created'])
            : $currentShowCreated;
        $showUpdated = array_key_exists('show_updated', $postData)
            ? self::normalizeBool($postData['show_updated'])
            : $currentShowUpdated;

        $defaultOrderEnabled = array_key_exists('default_order_enabled', $postData)
            ? self::normalizeBool($postData['default_order_enabled'])
            : $currentDefaultOrderEnabled;
        $defaultOrderField = array_key_exists('default_order_field', $postData)
            ? trim((string) $postData['default_order_field'])
            : $currentDefaultOrderField;
        $defaultOrderDirection = array_key_exists('default_order_direction', $postData)
            ? self::normalizeOrderDirection((string) $postData['default_order_direction'])
            : $currentDefaultOrderDirection;
        $childCountColumnMode = array_key_exists('main_table_count_visibility', $postData)
            ? self::normalizeChildCountColumnMode($postData['main_table_count_visibility'])
            : $currentChildCountColumnMode;

        if (!$defaultOrderEnabled) {
            $defaultOrderField = '';
            $defaultOrderDirection = 'asc';
        } elseif (!self::isAllowedOrderField($defaultOrderField, $orderFieldOptions)) {
            $defaultOrderEnabled = false;
            $defaultOrderField = '';
            $defaultOrderDirection = 'asc';
        }

        $updatedSchema = $schema;
        if (is_array($updatedSchema)) {
            self::applyAuditGroupVisibilityToSchema($updatedSchema, self::createdAuditFieldNames(), $showCreated);
            self::applyAuditGroupVisibilityToSchema($updatedSchema, self::updatedAuditFieldNames(), $showUpdated);
        }

        $config = [
            'showIf' => $displayLogic,
            'max_records' => (string) ($maxRecordsResult['value'] ?? 'n'),
            'editDisplay' => $editDisplay,
            'softDelete' => $softDelete,
            'allowDeleteRecord' => $allowDeleteRecord,
            'allowEdit' => $allowEdit,
            'childCountColumnMode' => $childCountColumnMode,
            'defaultOrderEnabled' => $defaultOrderEnabled,
            'defaultOrderField' => $defaultOrderField,
            'defaultOrderDirection' => $defaultOrderDirection,
        ];

        $found = self::applyConfigToManifest($manifest, $refBase, $config);
        if (!$found) {
            return ['success' => false, 'msg' => 'Form ref not found in manifest.', 'resolved_ref' => $refBase];
        }

        if (is_array($updatedSchema) && $schemaPath !== '') {
            if (!self::writeSchemaFile($schemaPath, $updatedSchema)) {
                return ['success' => false, 'msg' => 'Failed to write form schema JSON file.', 'resolved_ref' => $refBase];
            }
        }

        if (!ManifestService::writeManifest($manifestPath, $manifest)) {
            if (is_array($schema) && $schemaPath !== '') {
                self::writeSchemaFile($schemaPath, $schema);
            }

            return ['success' => false, 'msg' => 'Failed to write manifest file.', 'resolved_ref' => $refBase];
        }

        return ['success' => true, 'msg' => 'Form config saved.', 'resolved_ref' => $refBase];
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
     * @param array{
     *   showIf:string,
     *   max_records:string,
     *   editDisplay:string,
     *   softDelete:bool,
     *   allowDeleteRecord:bool,
     *   allowEdit:bool,
     *   childCountColumnMode:string,
     *   defaultOrderEnabled:bool,
     *   defaultOrderField:string,
     *   defaultOrderDirection:string
     * } $config
     */
    private static function applyConfigToManifest(array &$manifest, string $refBase, array $config): bool
    {
        $rootRef = basename(trim((string) ($manifest['ref'] ?? '')));
        if ($rootRef !== '' && strcasecmp($rootRef, $refBase) === 0) {
            self::applyConfigToNode($manifest, $config);
            return true;
        }

        $forms = is_array($manifest['forms'] ?? null) ? $manifest['forms'] : [];
        $found = self::applyConfigRecursive($forms, $refBase, $config);
        if ($found) {
            $manifest['forms'] = $forms;
        }

        return $found;
    }

    /**
     * @param array<int,mixed> $nodes
     * @param array{
     *   showIf:string,
     *   max_records:string,
     *   editDisplay:string,
     *   softDelete:bool,
     *   allowDeleteRecord:bool,
     *   allowEdit:bool,
     *   childCountColumnMode:string,
     *   defaultOrderEnabled:bool,
     *   defaultOrderField:string,
     *   defaultOrderDirection:string
     * } $config
     */
    private static function applyConfigRecursive(array &$nodes, string $refBase, array $config): bool
    {
        foreach ($nodes as &$node) {
            if (!is_array($node)) {
                continue;
            }

            $nodeRef = basename(trim((string) ($node['ref'] ?? '')));
            if ($nodeRef !== '' && strcasecmp($nodeRef, $refBase) === 0) {
                self::applyConfigToNode($node, $config);
                return true;
            }

            $children = is_array($node['forms'] ?? null) ? $node['forms'] : [];
            if (!empty($children) && self::applyConfigRecursive($children, $refBase, $config)) {
                $node['forms'] = $children;
                return true;
            }
        }
        unset($node);

        return false;
    }

    /**
     * @param array<string,mixed> $node
     * @param array{
     *   showIf:string,
     *   max_records:string,
     *   editDisplay:string,
     *   softDelete:bool,
     *   allowDeleteRecord:bool,
     *   allowEdit:bool,
     *   childCountColumnMode:string,
     *   defaultOrderEnabled:bool,
     *   defaultOrderField:string,
     *   defaultOrderDirection:string
     * } $config
     */
    private static function applyConfigToNode(array &$node, array $config): void
    {
        if ($config['showIf'] === '') {
            unset($node['showIf']);
        } else {
            $node['showIf'] = $config['showIf'];
        }

        $node['max_records'] = $config['max_records'];
        $node['editDisplay'] = $config['editDisplay'];
        $node['softDelete'] = $config['softDelete'];
        $node['allowDeleteRecord'] = $config['allowDeleteRecord'];
        $node['allowEdit'] = $config['allowEdit'];
        $childCountColumnMode = self::normalizeChildCountColumnMode($config['childCountColumnMode'] ?? 'auto');
        if ($childCountColumnMode === 'auto') {
            unset($node['childCountColumn'], $node['child_count_column']);
        } else {
            $node['childCountColumn'] = $childCountColumnMode;
            unset($node['child_count_column']);
        }
        $node['defaultOrderEnabled'] = $config['defaultOrderEnabled'];
        $node['defaultOrderField'] = $config['defaultOrderField'];
        $node['defaultOrderDirection'] = self::normalizeOrderDirection($config['defaultOrderDirection']);
        unset(
            $node['allowDeleteFiles'],
            $node['soft_delete'],
            $node['allow_delete_files'],
            $node['allow_delete_record'],
            $node['allow_edit'],
            $node['default_order_enabled'],
            $node['default_order_field'],
            $node['default_order_direction']
        );
    }

    /**
     * @return array{success:bool,value?:string,msg:string}
     */
    private static function parseMaxRecords(mixed $rawValue): array
    {
        $value = strtolower(trim((string) $rawValue));
        if ($value === '') {
            return ['success' => true, 'value' => 'n', 'msg' => ''];
        }

        if (in_array($value, ['n', 'unlimited', 'many', 'multiple'], true)) {
            return ['success' => true, 'value' => 'n', 'msg' => ''];
        }

        if (in_array($value, ['one', 'single'], true)) {
            return ['success' => true, 'value' => '1', 'msg' => ''];
        }

        if (ctype_digit($value) && (int) $value > 0) {
            return ['success' => true, 'value' => (string) ((int) $value), 'msg' => ''];
        }

        return [
            'success' => false,
            'msg' => 'Max number records must be a positive integer or "n".',
        ];
    }

    private static function normalizeEditDisplay(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        if (in_array($normalized, ['page', 'offcanvas', 'modal'], true)) {
            return $normalized;
        }

        return 'page';
    }

    private static function normalizeChildCountColumnMode(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['show', 'yes', '1', 'true', 'on'], true)) {
            return 'show';
        }
        if (in_array($normalized, ['hide', 'no', '0', 'false', 'off'], true)) {
            return 'hide';
        }

        return 'auto';
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

    private static function normalizeOrderDirection(string $value): string
    {
        $value = strtolower(trim($value));
        return $value === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @param array<string,mixed>|null $schema
     * @return array<string,string>
     */
    private static function extractOrderFieldOptionsFromSchema(?array $schema): array
    {
        $options = ['id' => 'ID'];
        if (!is_array($schema)) {
            return $options;
        }

        $model = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $fields = is_array($model['fields'] ?? null) ? $model['fields'] : [];
        foreach ($fields as $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }

            $name = trim((string) ($fieldDef['name'] ?? ''));
            if ($name === '' || !self::isSafeOrderFieldName($name)) {
                continue;
            }
            if (array_key_exists($name, $options)) {
                continue;
            }

            $label = trim((string) ($fieldDef['label'] ?? ($fieldDef['formLabel'] ?? ($fieldDef['form-label'] ?? ''))));
            if ($label === '') {
                $label = ManifestService::toTitle($name);
            }
            if ($label === '') {
                $label = $name;
            }

            $options[$name] = $label;
        }

        return $options;
    }

    /**
     * @param array<string,string> $options
     */
    private static function isAllowedOrderField(string $field, array $options): bool
    {
        $field = trim($field);
        if ($field === '' || !self::isSafeOrderFieldName($field)) {
            return false;
        }

        return array_key_exists($field, $options);
    }

    private static function isSafeOrderFieldName(string $field): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) === 1;
    }

    /**
     * @param array<string,mixed>|null $schema
     * @param array<string,bool> $requiredFields
     */
    private static function schemaHasAllFields(?array $schema, array $requiredFields): bool
    {
        if (!is_array($schema) || empty($requiredFields)) {
            return false;
        }

        $required = [];
        foreach ($requiredFields as $fieldName => $enabled) {
            if (!$enabled) {
                continue;
            }
            $fieldName = strtolower(trim((string) $fieldName));
            if ($fieldName === '') {
                continue;
            }
            $required[$fieldName] = true;
        }
        if (empty($required)) {
            return false;
        }

        $model = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $fields = is_array($model['fields'] ?? null) ? $model['fields'] : [];
        foreach ($fields as $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }

            $name = strtolower(trim((string) ($fieldDef['name'] ?? '')));
            if ($name === '' || !isset($required[$name])) {
                continue;
            }

            unset($required[$name]);
            if (empty($required)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed>|null $schema
     * @param array<string,bool> $fieldNameMap
     */
    private static function isAuditGroupShown(?array $schema, array $fieldNameMap): bool
    {
        if (!is_array($schema)) {
            return false;
        }

        $model = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $fields = is_array($model['fields'] ?? null) ? $model['fields'] : [];

        $found = 0;
        $allUnlocked = true;

        foreach ($fields as $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }

            $name = strtolower(trim((string) ($fieldDef['name'] ?? '')));
            if ($name === '' || !isset($fieldNameMap[$name])) {
                continue;
            }

            $found++;
            if (self::normalizeBool($fieldDef['builderLocked'] ?? ($fieldDef['builder_locked'] ?? false))) {
                $allUnlocked = false;
            }
        }

        return $found > 0 && $allUnlocked;
    }

    /**
     * @param array<string,mixed> $schema
     * @param array<string,bool> $fieldNameMap
     */
    private static function applyAuditGroupVisibilityToSchema(array &$schema, array $fieldNameMap, bool $isShown): void
    {
        $model = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $fields = is_array($model['fields'] ?? null) ? $model['fields'] : [];

        foreach ($fields as $index => $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }

            $name = strtolower(trim((string) ($fieldDef['name'] ?? '')));
            if ($name === '' || !isset($fieldNameMap[$name])) {
                continue;
            }

            if ($isShown) {
                unset($fieldDef['builderLocked'], $fieldDef['builder_locked']);
                $fieldDef['hideFromList'] = false;
                $fieldDef['hideFromEdit'] = true;
            } else {
                $fieldDef['builderLocked'] = true;
                unset($fieldDef['builder_locked']);
                $fieldDef['hideFromList'] = true;
                $fieldDef['hideFromEdit'] = true;
            }

            $fields[$index] = $fieldDef;
        }

        $model['fields'] = $fields;
        $schema['model'] = $model;
    }

    /**
     * @return array<string,bool>
     */
    private static function createdAuditFieldNames(): array
    {
        return [
            'created_at' => true,
            'created_by' => true,
        ];
    }

    /**
     * @return array<string,bool>
     */
    private static function updatedAuditFieldNames(): array
    {
        return [
            'updated_at' => true,
            'updated_by' => true,
        ];
    }

    /**
     * @param array<string,mixed> $project
     */
    private static function resolveSchemaPath(array $project, string $refBase): string
    {
        return \Modules\Projects\DraftService\DraftModelResolver::resolveSchemaPath($project, basename(trim($refBase)));
    }

    /**
     * @param array<string,mixed> $project
     * @return array<string,mixed>|null
     */
    private static function readSchemaForRef(array $project, string $refBase): ?array
    {
        $schemaPath = self::resolveSchemaPath($project, $refBase);
        return self::readSchemaFile($schemaPath);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readSchemaFile(string $path): ?array
    {
        if (trim($path) === '') {
            return null;
        }

        return \Modules\Projects\DraftService\DraftJsonFileHandler::read($path);
    }

    /**
     * @param array<string,mixed> $schema
     */
    private static function writeSchemaFile(string $path, array $schema): bool
    {
        if (trim($path) === '') {
            return false;
        }

        return \Modules\Projects\DraftService\DraftJsonFileHandler::write($path, $schema);
    }
}
