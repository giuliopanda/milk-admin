<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectMainFormConfigService
{
    /**
     * @param array<string,mixed>|null $project
     * @return array{
     *   edit_display:string,
     *   soft_delete:bool,
     *   supports_soft_delete:bool,
     *   allow_delete_record:bool,
     *   allow_edit:bool,
     *   show_created:bool,
     *   show_updated:bool,
     *   default_order_enabled:bool,
     *   default_order_field:string,
     *   default_order_direction:string,
     *   default_order_field_options:array<string,string>,
     *   can_edit:bool,
     *   error:string
     * }
     */
    public static function buildPageData(?array $project): array
    {
        $payload = [
            'edit_display' => 'page',
            'soft_delete' => false,
            'supports_soft_delete' => false,
            'allow_delete_record' => true,
            'allow_edit' => true,
            'show_created' => false,
            'show_updated' => false,
            'default_order_enabled' => false,
            'default_order_field' => '',
            'default_order_direction' => 'asc',
            'default_order_field_options' => [],
            'can_edit' => false,
            'error' => '',
        ];

        if (!is_array($project)) {
            $payload['error'] = 'Project not found.';
            return $payload;
        }

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

        $rootNode = ManifestService::extractRootNode($manifest);
        if (!is_array($rootNode)) {
            $payload['error'] = 'Main form not found in manifest.';
            return $payload;
        }

        $payload['edit_display'] = self::normalizeEditDisplay((string) ($rootNode['editDisplay'] ?? 'page'));
        $payload['soft_delete'] = self::normalizeBool(
            $rootNode['softDelete'] ?? ($rootNode['soft_delete'] ?? false)
        );
        $payload['allow_delete_record'] = self::normalizeBool(
            $rootNode['allowDeleteRecord'] ?? ($rootNode['allow_delete_record'] ?? true)
        );
        $payload['allow_edit'] = self::normalizeBool(
            $rootNode['allowEdit'] ?? ($rootNode['allow_edit'] ?? true)
        );
        $payload['default_order_enabled'] = self::normalizeBool(
            $rootNode['defaultOrderEnabled'] ?? ($rootNode['default_order_enabled'] ?? false)
        );
        $payload['default_order_field'] = trim((string) (
            $rootNode['defaultOrderField'] ?? ($rootNode['default_order_field'] ?? '')
        ));
        $payload['default_order_direction'] = self::normalizeOrderDirection((string) (
            $rootNode['defaultOrderDirection'] ?? ($rootNode['default_order_direction'] ?? 'asc')
        ));

        $rootRefBase = basename(trim((string) ($rootNode['ref'] ?? '')));
        $payload['supports_soft_delete'] = self::supportsSoftDeleteForRoot($project, $rootRefBase);
        if (!$payload['supports_soft_delete']) {
            $payload['soft_delete'] = false;
        }

        $schema = self::readRootSchema($project, $rootRefBase);
        if (is_array($schema)) {
            $payload['show_created'] = self::isAuditGroupShown($schema, self::createdAuditFieldNames());
            $payload['show_updated'] = self::isAuditGroupShown($schema, self::updatedAuditFieldNames());
            $payload['default_order_field_options'] = self::extractOrderFieldOptionsFromSchema($schema);
        } else {
            $payload['default_order_field_options'] = ['id' => 'ID'];
        }

        if (!$payload['default_order_enabled']) {
            $payload['default_order_field'] = '';
        } elseif (!self::isAllowedOrderField(
            $payload['default_order_field'],
            $payload['default_order_field_options']
        )) {
            $payload['default_order_enabled'] = false;
            $payload['default_order_field'] = '';
        }

        $payload['can_edit'] = true;

        return $payload;
    }

    /**
     * @param array<string,mixed>|null $project
     * @param array<string,mixed> $postData
     * @return array{
     *   success:bool,
     *   msg:string,
     *   edit_display:string,
     *   soft_delete:bool,
     *   supports_soft_delete:bool,
     *   allow_delete_record:bool,
     *   allow_edit:bool,
     *   show_created:bool,
     *   show_updated:bool,
     *   default_order_enabled:bool,
     *   default_order_field:string,
     *   default_order_direction:string,
     *   default_order_field_options:array<string,string>
     * }
     */
    public static function saveConfig(?array $project, array $postData): array
    {
        if (!is_array($project)) {
            return self::buildSaveResult(
                false,
                'Project not found.',
                self::normalizeEditDisplay((string) ($postData['edit_display'] ?? 'page')),
                self::normalizeBool($postData['soft_delete'] ?? false),
                false,
                self::normalizeBool($postData['allow_delete_record'] ?? true),
                self::normalizeBool($postData['allow_edit'] ?? true),
                self::normalizeBool($postData['show_created'] ?? false),
                self::normalizeBool($postData['show_updated'] ?? false)
            );
        }

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return self::buildSaveResult(
                false,
                'Manifest file not found.',
                self::normalizeEditDisplay((string) ($postData['edit_display'] ?? 'page')),
                self::normalizeBool($postData['soft_delete'] ?? false),
                false,
                self::normalizeBool($postData['allow_delete_record'] ?? true),
                self::normalizeBool($postData['allow_edit'] ?? true),
                self::normalizeBool($postData['show_created'] ?? false),
                self::normalizeBool($postData['show_updated'] ?? false)
            );
        }

        $manifest = ManifestService::readManifest($manifestPath);
        if (!is_array($manifest)) {
            return self::buildSaveResult(
                false,
                'Failed to read manifest file.',
                self::normalizeEditDisplay((string) ($postData['edit_display'] ?? 'page')),
                self::normalizeBool($postData['soft_delete'] ?? false),
                false,
                self::normalizeBool($postData['allow_delete_record'] ?? true),
                self::normalizeBool($postData['allow_edit'] ?? true),
                self::normalizeBool($postData['show_created'] ?? false),
                self::normalizeBool($postData['show_updated'] ?? false)
            );
        }

        $rootNode = ManifestService::extractRootNode($manifest);
        if (!is_array($rootNode)) {
            return self::buildSaveResult(
                false,
                'Main form not found in manifest.',
                self::normalizeEditDisplay((string) ($postData['edit_display'] ?? 'page')),
                self::normalizeBool($postData['soft_delete'] ?? false),
                false,
                self::normalizeBool($postData['allow_delete_record'] ?? true),
                self::normalizeBool($postData['allow_edit'] ?? true),
                self::normalizeBool($postData['show_created'] ?? false),
                self::normalizeBool($postData['show_updated'] ?? false)
            );
        }

        $currentEditDisplay = self::normalizeEditDisplay((string) ($rootNode['editDisplay'] ?? 'page'));
        $currentSoftDelete = self::normalizeBool($rootNode['softDelete'] ?? ($rootNode['soft_delete'] ?? false));
        $currentAllowDeleteRecord = self::normalizeBool($rootNode['allowDeleteRecord'] ?? ($rootNode['allow_delete_record'] ?? true));
        $currentAllowEdit = self::normalizeBool($rootNode['allowEdit'] ?? ($rootNode['allow_edit'] ?? true));
        $currentDefaultOrderEnabled = self::normalizeBool(
            $rootNode['defaultOrderEnabled'] ?? ($rootNode['default_order_enabled'] ?? false)
        );
        $currentDefaultOrderField = trim((string) (
            $rootNode['defaultOrderField'] ?? ($rootNode['default_order_field'] ?? '')
        ));
        $currentDefaultOrderDirection = self::normalizeOrderDirection((string) (
            $rootNode['defaultOrderDirection'] ?? ($rootNode['default_order_direction'] ?? 'asc')
        ));

        $rootRefBase = basename(trim((string) ($rootNode['ref'] ?? '')));
        $supportsSoftDelete = self::supportsSoftDeleteForRoot($project, $rootRefBase);

        $schemaPath = self::resolveRootSchemaPath($project, $rootRefBase);
        $schema = $schemaPath !== '' ? self::readSchemaFile($schemaPath) : null;

        $currentShowCreated = false;
        $currentShowUpdated = false;
        if (is_array($schema)) {
            $currentShowCreated = self::isAuditGroupShown($schema, self::createdAuditFieldNames());
            $currentShowUpdated = self::isAuditGroupShown($schema, self::updatedAuditFieldNames());
        }
        $orderFieldOptions = self::extractOrderFieldOptionsFromSchema($schema);

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

        if (!self::applyToRootNode(
            $manifest,
            $editDisplay,
            $softDelete,
            $allowDeleteRecord,
            $allowEdit,
            $defaultOrderEnabled,
            $defaultOrderField,
            $defaultOrderDirection
        )) {
            return self::buildSaveResult(
                false,
                'Main form not found in manifest.',
                $editDisplay,
                $softDelete,
                $supportsSoftDelete,
                $allowDeleteRecord,
                $allowEdit,
                $showCreated,
                $showUpdated,
                $defaultOrderEnabled,
                $defaultOrderField,
                $defaultOrderDirection,
                $orderFieldOptions
            );
        }

        if (is_array($updatedSchema) && $schemaPath !== '') {
            if (!self::writeSchemaFile($schemaPath, $updatedSchema)) {
                return self::buildSaveResult(
                    false,
                    'Failed to write root schema JSON file.',
                    $editDisplay,
                    $softDelete,
                    $supportsSoftDelete,
                    $allowDeleteRecord,
                    $allowEdit,
                    $showCreated,
                    $showUpdated,
                    $defaultOrderEnabled,
                    $defaultOrderField,
                    $defaultOrderDirection,
                    $orderFieldOptions
                );
            }
        }

        if (!ManifestService::writeManifest($manifestPath, $manifest)) {
            if (is_array($schema) && $schemaPath !== '') {
                self::writeSchemaFile($schemaPath, $schema);
            }

            return self::buildSaveResult(
                false,
                'Failed to write manifest file.',
                $editDisplay,
                $softDelete,
                $supportsSoftDelete,
                $allowDeleteRecord,
                $allowEdit,
                $showCreated,
                $showUpdated,
                $defaultOrderEnabled,
                $defaultOrderField,
                $defaultOrderDirection,
                $orderFieldOptions
            );
        }

        return self::buildSaveResult(
            true,
            'Main form config saved.',
            $editDisplay,
            $softDelete,
            $supportsSoftDelete,
            $allowDeleteRecord,
            $allowEdit,
            $showCreated,
            $showUpdated,
            $defaultOrderEnabled,
            $defaultOrderField,
            $defaultOrderDirection,
            $orderFieldOptions
        );
    }

    /**
     * @return array{
     *   success:bool,
     *   msg:string,
     *   edit_display:string,
     *   soft_delete:bool,
     *   supports_soft_delete:bool,
     *   allow_delete_record:bool,
     *   allow_edit:bool,
     *   show_created:bool,
     *   show_updated:bool,
     *   default_order_enabled:bool,
     *   default_order_field:string,
     *   default_order_direction:string,
     *   default_order_field_options:array<string,string>
     * }
     */
    private static function buildSaveResult(
        bool $success,
        string $msg,
        string $editDisplay,
        bool $softDelete,
        bool $supportsSoftDelete,
        bool $allowDeleteRecord,
        bool $allowEdit,
        bool $showCreated,
        bool $showUpdated,
        bool $defaultOrderEnabled = false,
        string $defaultOrderField = '',
        string $defaultOrderDirection = 'asc',
        array $defaultOrderFieldOptions = []
    ): array {
        return [
            'success' => $success,
            'msg' => $msg,
            'edit_display' => $editDisplay,
            'soft_delete' => $supportsSoftDelete ? $softDelete : false,
            'supports_soft_delete' => $supportsSoftDelete,
            'allow_delete_record' => $allowDeleteRecord,
            'allow_edit' => $allowEdit,
            'show_created' => $showCreated,
            'show_updated' => $showUpdated,
            'default_order_enabled' => $defaultOrderEnabled,
            'default_order_field' => $defaultOrderField,
            'default_order_direction' => self::normalizeOrderDirection($defaultOrderDirection),
            'default_order_field_options' => self::normalizeOrderFieldOptions($defaultOrderFieldOptions),
        ];
    }

    private static function normalizeEditDisplay(string $value): string
    {
        $value = strtolower(trim($value));
        if (!in_array($value, ['page', 'offcanvas', 'modal'], true)) {
            return 'page';
        }

        return $value;
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
     * @param array<string,mixed> $options
     * @return array<string,string>
     */
    private static function normalizeOrderFieldOptions(array $options): array
    {
        $normalized = [];
        foreach ($options as $name => $label) {
            $name = trim((string) $name);
            if ($name === '' || !self::isSafeOrderFieldName($name)) {
                continue;
            }

            $label = trim((string) $label);
            if ($label === '') {
                $label = ManifestService::toTitle($name);
            }
            if ($label === '') {
                $label = $name;
            }

            $normalized[$name] = $label;
        }

        if (empty($normalized)) {
            $normalized['id'] = 'ID';
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private static function applyToRootNode(
        array &$manifest,
        string $editDisplay,
        bool $softDelete,
        bool $allowDeleteRecord,
        bool $allowEdit,
        bool $defaultOrderEnabled,
        string $defaultOrderField,
        string $defaultOrderDirection
    ): bool {
        $rootRef = trim((string) ($manifest['ref'] ?? ''));
        $defaultOrderDirection = self::normalizeOrderDirection($defaultOrderDirection);
        if ($rootRef !== '') {
            $manifest['editDisplay'] = $editDisplay;
            $manifest['softDelete'] = $softDelete;
            $manifest['allowDeleteRecord'] = $allowDeleteRecord;
            $manifest['allowEdit'] = $allowEdit;
            $manifest['defaultOrderEnabled'] = $defaultOrderEnabled;
            $manifest['defaultOrderField'] = $defaultOrderField;
            $manifest['defaultOrderDirection'] = $defaultOrderDirection;
            unset(
                $manifest['allowDeleteFiles'],
                $manifest['soft_delete'],
                $manifest['allow_delete_files'],
                $manifest['allow_delete_record'],
                $manifest['allow_edit'],
                $manifest['default_order_enabled'],
                $manifest['default_order_field'],
                $manifest['default_order_direction']
            );
            return true;
        }

        $forms = is_array($manifest['forms'] ?? null) ? $manifest['forms'] : [];
        if (empty($forms) || !is_array($forms[0])) {
            return false;
        }

        $forms[0]['editDisplay'] = $editDisplay;
        $forms[0]['softDelete'] = $softDelete;
        $forms[0]['allowDeleteRecord'] = $allowDeleteRecord;
        $forms[0]['allowEdit'] = $allowEdit;
        $forms[0]['defaultOrderEnabled'] = $defaultOrderEnabled;
        $forms[0]['defaultOrderField'] = $defaultOrderField;
        $forms[0]['defaultOrderDirection'] = $defaultOrderDirection;
        unset(
            $forms[0]['allowDeleteFiles'],
            $forms[0]['soft_delete'],
            $forms[0]['allow_delete_files'],
            $forms[0]['allow_delete_record'],
            $forms[0]['allow_edit'],
            $forms[0]['default_order_enabled'],
            $forms[0]['default_order_field'],
            $forms[0]['default_order_direction']
        );
        $manifest['forms'] = $forms;

        return true;
    }

    /**
     * @param array<string,mixed> $project
     */
    private static function supportsSoftDeleteForRoot(array $project, string $rootRefBase): bool
    {
        $rootRefBase = basename(trim($rootRefBase));
        if ($rootRefBase === '') {
            return false;
        }

        $modelInfo = \Modules\Projects\DraftService\DraftModelResolver::resolveModelInfoForRef($project, $rootRefBase);
        if (empty($modelInfo['success'])) {
            return false;
        }

        $modelFqcn = trim((string) ($modelInfo['model_fqcn'] ?? ''));
        $modelFilePath = trim((string) ($modelInfo['model_file_path'] ?? ''));
        if ($modelFqcn === '' || $modelFilePath === '' || !is_file($modelFilePath)) {
            return false;
        }

        try {
            if (!class_exists($modelFqcn, false)) {
                require_once $modelFilePath;
            }
            if (!class_exists($modelFqcn)) {
                return false;
            }

            $model = new $modelFqcn();
            if (!method_exists($model, 'getTable') || !method_exists($model, 'getDb')) {
                return false;
            }

            $tableName = trim((string) $model->getTable());
            $db = $model->getDb();
            if ($tableName === '' || !is_object($db) || !method_exists($db, 'describes')) {
                return false;
            }

            $describe = $db->describes($tableName, true);
            if (!is_array($describe)) {
                return false;
            }

            $columns = self::extractColumnNameMapFromDescribe($describe);
            return isset($columns['deleted_at']) && isset($columns['deleted_by']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $describe
     * @return array<string,bool>
     */
    private static function extractColumnNameMapFromDescribe(array $describe): array
    {
        $names = [];

        $struct = is_array($describe['struct'] ?? null) ? $describe['struct'] : [];
        if (empty($struct)) {
            $struct = $describe;
        }

        foreach ($struct as $item) {
            $row = is_object($item) ? get_object_vars($item) : (is_array($item) ? $item : []);
            if (empty($row)) {
                continue;
            }

            $name = trim((string) ($row['Field'] ?? $row['field'] ?? $row['name'] ?? $row['column_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $names[strtolower($name)] = true;
        }

        $fields = is_array($describe['fields'] ?? null) ? $describe['fields'] : [];
        foreach ($fields as $fieldName => $_) {
            if (!is_string($fieldName)) {
                continue;
            }
            $fieldName = trim($fieldName);
            if ($fieldName === '') {
                continue;
            }
            $names[strtolower($fieldName)] = true;
        }

        return $names;
    }

    /**
     * @param array<string,mixed> $project
     * @return array<string,mixed>|null
     */
    private static function readRootSchema(array $project, string $rootRefBase): ?array
    {
        $schemaPath = self::resolveRootSchemaPath($project, $rootRefBase);
        if ($schemaPath === '') {
            return null;
        }

        return self::readSchemaFile($schemaPath);
    }

    /**
     * @param array<string,mixed> $project
     */
    private static function resolveRootSchemaPath(array $project, string $rootRefBase): string
    {
        $rootRefBase = basename(trim($rootRefBase));
        if ($rootRefBase === '') {
            return '';
        }

        return \Modules\Projects\DraftService\DraftModelResolver::resolveSchemaPath($project, $rootRefBase);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readSchemaFile(string $path): ?array
    {
        return \Modules\Projects\DraftService\DraftJsonFileHandler::read($path);
    }

    /**
     * @param array<string,mixed> $schema
     * @param array<string,bool> $fieldNameMap
     */
    private static function isAuditGroupShown(array $schema, array $fieldNameMap): bool
    {
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
     * @param array<string,mixed> $schema
     */
    private static function writeSchemaFile(string $path, array $schema): bool
    {
        return \Modules\Projects\DraftService\DraftJsonFileHandler::write($path, $schema);
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
