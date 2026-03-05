<?php

namespace Modules\Projects;

use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

class ProjectFormEditorService
{
    /**
     * @return array{
     *   requested_ref:string,
     *   resolved_ref:string,
     *   form_name:string,
     *   json_title:string,
     *   form_display_logic:string,
     *   existing_table_locked:bool,
     *   can_edit:bool,
     *   fields:array<int,array{name:string,builder_locked:bool,can_delete:bool,config:array<string,mixed>}>,
     *   containers:array<int,array{id:string,fields:array<int,string|array<int,string>>,cols:int|array<int,int>,position_before:string,title:string,attributes:array<string,mixed>}>,
     *   error:string
     * }
     */
    public static function buildPageData(?array $project, string $requestedRef): array
    {
        $payload = [
            'requested_ref' => trim($requestedRef),
            'resolved_ref' => '',
            'form_name' => '',
            'json_title' => '',
            'form_display_logic' => '',
            'existing_table_locked' => false,
            'can_edit' => false,
            'fields' => [],
            'containers' => [],
            'error' => '',
        ];

        if (!is_array($project)) {
            $payload['error'] = 'Project not found or invalid module parameter.';
            return $payload;
        }

        $structureAccess = ProjectSettingsService::evaluateStructureEditAccess($project);
        if (empty($structureAccess['can_edit_structure'])) {
            $payload['error'] = (string) ($structureAccess['message'] ?? 'You cannot edit this table structure.');
            return $payload;
        }

        $refBase = basename($payload['requested_ref']);
        if ($refBase === '') {
            $payload['error'] = 'You cannot edit this table: invalid ref.';
            return $payload;
        }
        $payload['resolved_ref'] = $refBase;
        $payload['form_name'] = trim((string) pathinfo($refBase, PATHINFO_FILENAME));

        $formsTree = ProjectFormsService::buildFormsTreeFromProject($project);
        $nodeContext = self::findNodeContextByRef($formsTree, $refBase);
        if (!is_array($nodeContext) || !is_array($nodeContext['node'] ?? null)) {
            $payload['error'] = 'You cannot edit this table: ref not found in manifest.';
            return $payload;
        }
        $node = $nodeContext['node'];
        $parentFormName = trim((string) ($nodeContext['parent_form_name'] ?? ''));

        $manifestLocked = self::normalizeBool($node['existingTable'] ?? ($node['existing_table'] ?? false));
        $payload['existing_table_locked'] = $manifestLocked;

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        $projectDir = $manifestPath !== '' ? dirname($manifestPath) : '';
        if ($projectDir === '' || !is_dir($projectDir)) {
            $payload['error'] = 'You cannot edit this table: Project folder is not available.';
            return $payload;
        }

        $schemaPath = $projectDir . '/' . $refBase;
        if (!is_file($schemaPath)) {
            $payload['error'] = 'You cannot edit this table: associated JSON file does not exist.';
            return $payload;
        }

        $schema = self::readJsonFile($schemaPath);
        if (!is_array($schema)) {
            $payload['error'] = 'You cannot edit this table: associated JSON is invalid.';
            return $payload;
        }

        $jsonTitle = trim((string) ($schema['_name'] ?? ''));
        if ($jsonTitle === '') {
            $jsonTitle = trim((string) ($node['title'] ?? ''));
        }
        if ($jsonTitle === '') {
            $jsonTitle = $payload['form_name'] !== '' ? $payload['form_name'] : $refBase;
        }
        $payload['json_title'] = $jsonTitle;
        $payload['form_display_logic'] = trim((string) ($node['showIf'] ?? ''));
        $payload['containers'] = self::extractSchemaContainers($schema);

        $schemaFieldMeta = self::extractSchemaFieldMeta($schema);
        $untouchableFieldMap = self::buildUntouchableFieldMap($parentFormName);
        $fieldsResult = self::resolveModelFields(
            $project,
            $payload['form_name'],
            $schemaFieldMeta['builder_locked'],
            $schemaFieldMeta['json_fields'],
            $schemaFieldMeta['json_order'],
            $schemaFieldMeta['field_configs'],
            $untouchableFieldMap
        );
        if (!empty($fieldsResult['error'])) {
            $payload['error'] = (string) $fieldsResult['error'];
            return $payload;
        }

        $payload['fields'] = is_array($fieldsResult['fields'] ?? null) ? $fieldsResult['fields'] : [];
        $payload['can_edit'] = true;

        return $payload;
    }

    /**
     * @param array<string,mixed> $schema
     * @return array<int,array{id:string,fields:array<int,string|array<int,string>>,cols:int|array<int,int>,position_before:string,title:string,attributes:array<string,mixed>}>
     */
    private static function extractSchemaContainers(array $schema): array
    {
        $model = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $rawContainers = is_array($model['containers'] ?? null) ? $model['containers'] : [];
        $containers = [];
        $seenIds = [];

        foreach ($rawContainers as $rawContainer) {
            if (!is_array($rawContainer)) {
                continue;
            }

            $id = trim((string) ($rawContainer['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $idLower = strtolower($id);
            if (isset($seenIds[$idLower])) {
                continue;
            }

            $fields = [];
            $seenFieldNames = [];
            $rawFields = is_array($rawContainer['fields'] ?? null) ? $rawContainer['fields'] : [];
            foreach ($rawFields as $fieldEntry) {
                if (is_array($fieldEntry)) {
                    $group = [];
                    foreach ($fieldEntry as $groupEntry) {
                        if (is_array($groupEntry)) {
                            continue;
                        }
                        $fieldName = trim((string) $groupEntry);
                        if ($fieldName === '') {
                            continue;
                        }
                        $fieldLower = strtolower($fieldName);
                        if (isset($seenFieldNames[$fieldLower])) {
                            continue;
                        }
                        $seenFieldNames[$fieldLower] = true;
                        $group[] = $fieldName;
                    }

                    if (count($group) === 1) {
                        $fields[] = $group[0];
                    } elseif (!empty($group)) {
                        $fields[] = $group;
                    }
                    continue;
                }

                $fieldName = trim((string) $fieldEntry);
                if ($fieldName === '') {
                    continue;
                }
                $fieldLower = strtolower($fieldName);
                if (isset($seenFieldNames[$fieldLower])) {
                    continue;
                }
                $seenFieldNames[$fieldLower] = true;
                $fields[] = $fieldName;
            }

            if (empty($fields)) {
                continue;
            }

            $seenIds[$idLower] = true;
            $containers[] = [
                'id' => $id,
                'fields' => $fields,
                'cols' => ContainerNormalizer::normalizeContainerCols($rawContainer['cols'] ?? count($fields), count($fields)),
                'position_before' => trim((string) ($rawContainer['position_before'] ?? ($rawContainer['positionBefore'] ?? ''))),
                'title' => trim((string) ($rawContainer['title'] ?? '')),
                'attributes' => ContainerNormalizer::normalizeContainerAttributes($rawContainer['attributes'] ?? []),
            ];
        }

        return $containers;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function findNodeContextByRef(array $formsTree, string $refBase): ?array
    {
        $main = is_array($formsTree['main'] ?? null) ? $formsTree['main'] : [];
        if (!empty($main) && self::refEquals($main, $refBase)) {
            return [
                'node' => $main,
                'parent_form_name' => '',
            ];
        }

        $children = is_array($formsTree['children'] ?? null) ? $formsTree['children'] : [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $parentFormName = trim((string) ($main['form_name'] ?? ''));
            $found = self::findNodeByRefRecursive($child, $refBase, $parentFormName);
            if (is_array($found)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function findNodeByRefRecursive(array $node, string $refBase, string $parentFormName): ?array
    {
        if (self::refEquals($node, $refBase)) {
            return [
                'node' => $node,
                'parent_form_name' => $parentFormName,
            ];
        }

        $currentFormName = trim((string) ($node['form_name'] ?? ''));
        if ($currentFormName === '') {
            $currentFormName = $parentFormName;
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $found = self::findNodeByRefRecursive($child, $refBase, $currentFormName);
            if (is_array($found)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $node
     */
    private static function refEquals(array $node, string $refBase): bool
    {
        $nodeRef = basename(trim((string) ($node['ref'] ?? '')));
        return $nodeRef !== '' && strcasecmp($nodeRef, $refBase) === 0;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readJsonFile(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @return array{
     *   builder_locked:array<string,bool>,
     *   json_fields:array<string,bool>,
     *   json_order:array<string,int>,
     *   field_configs:array<string,array<string,mixed>>
     * }
     */
    private static function extractSchemaFieldMeta(array $schema): array
    {
        $builderLockedMap = [];
        $jsonFieldMap = [];
        $jsonFieldOrderMap = [];
        $fieldConfigMap = [];
        $model = is_array($schema['model'] ?? null) ? $schema['model'] : [];
        $fields = is_array($model['fields'] ?? null) ? $model['fields'] : [];
        $order = 0;

        foreach ($fields as $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }

            $name = trim((string) ($fieldDef['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $nameLower = strtolower($name);
            $jsonFieldMap[$nameLower] = true;
            if (!isset($jsonFieldOrderMap[$nameLower])) {
                $jsonFieldOrderMap[$nameLower] = $order;
                $order++;
            }
            $fieldConfigMap[$nameLower] = self::buildEditorFieldConfigFromSchema($fieldDef, $name);

            $locked = self::normalizeBool($fieldDef['builderLocked'] ?? ($fieldDef['builder_locked'] ?? false));
            if ($locked) {
                $builderLockedMap[$nameLower] = true;
            }
        }

        return [
            'builder_locked' => $builderLockedMap,
            'json_fields' => $jsonFieldMap,
            'json_order' => $jsonFieldOrderMap,
            'field_configs' => $fieldConfigMap,
        ];
    }

    /**
     * @return array<string,bool> map field_name(lowercase) => true
     */
    private static function buildUntouchableFieldMap(string $parentFormName): array
    {
        $map = [];
        $parentFormName = trim($parentFormName);
        if ($parentFormName === '') {
            return $map;
        }

        $map[strtolower(ProjectNaming::rootIdField())] = true;
        $map[strtolower(ProjectNaming::foreignKeyFieldForParentForm($parentFormName))] = true;

        return $map;
    }

    /**
     * @param array<string,bool> $builderLockedFieldMap
     * @param array<string,bool> $schemaFieldMap
     * @param array<string,int> $schemaFieldOrderMap
     * @param array<string,array<string,mixed>> $schemaFieldConfigMap
     * @param array<string,bool> $untouchableFieldMap
     * @return array{fields:array<int,array{name:string,builder_locked:bool,can_delete:bool,config:array<string,mixed>}>,error:string}
     */
    private static function resolveModelFields(
        array $project,
        string $formName,
        array $builderLockedFieldMap = [],
        array $schemaFieldMap = [],
        array $schemaFieldOrderMap = [],
        array $schemaFieldConfigMap = [],
        array $untouchableFieldMap = []
    ): array
    {
        $formName = trim($formName);
        if ($formName === '') {
            return [
                'fields' => [],
                'error' => 'You cannot edit this table: invalid form name.',
            ];
        }

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        $moduleName = trim((string) ($project['module_name'] ?? ''));
        if ($manifestPath === '' || $moduleName === '' || !is_file($manifestPath)) {
            return [
                'fields' => [],
                'error' => 'You cannot edit this table: invalid manifest path.',
            ];
        }

        $moduleDir = dirname(dirname($manifestPath));
        [$defaultDir, $defaultNamespace] = ManifestService::resolveModelLocation($manifestPath, $moduleDir, $moduleName);
        $locations = [];
        self::pushModelLocation($locations, $defaultDir, $defaultNamespace);
        self::pushModelLocation($locations, dirname($manifestPath) . '/Models', 'Local\\Modules\\' . $moduleName . '\\Project\\Models');
        self::pushModelLocation($locations, $moduleDir, 'Local\\Modules\\' . $moduleName);

        $candidates = [];
        $candidates[] = $formName . 'Model';
        $studly = self::toStudlyCase($formName);
        if ($studly !== '' && strcasecmp($studly, $formName) !== 0) {
            $candidates[] = $studly . 'Model';
        }
        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $classShort) {
            foreach ($locations as $loc) {
                $dir = (string) ($loc['dir'] ?? '');
                $namespace = trim((string) ($loc['namespace'] ?? ''), '\\');
                if ($dir === '') {
                    continue;
                }

                $file = rtrim($dir, '/\\') . '/' . $classShort . '.php';
                if (!is_file($file)) {
                    continue;
                }

                $fqcn = $namespace !== '' ? $namespace . '\\' . $classShort : $classShort;
                if (!class_exists($fqcn, false)) {
                    require_once $file;
                }
                if (!class_exists($fqcn)) {
                    continue;
                }

                try {
                    $model = new $fqcn();
                    $rules = method_exists($model, 'getRules') ? $model->getRules() : [];
                    $modelConfigureOnlyRules = method_exists($model, 'getRulesDefinedInModelConfigureOnly')
                        ? $model->getRulesDefinedInModelConfigureOnly()
                        : [];
                } catch (\Throwable $e) {
                    return [
                        'fields' => [],
                        'error' => 'You cannot edit this table: error loading the model.',
                    ];
                }

                if (!is_array($rules)) {
                    $rules = [];
                }
                if (!is_array($modelConfigureOnlyRules)) {
                    $modelConfigureOnlyRules = [];
                }

                $modelConfigureOnlyFieldSet = [];
                foreach (array_keys($modelConfigureOnlyRules) as $modelFieldName) {
                    $name = trim((string) $modelFieldName);
                    if ($name === '') {
                        continue;
                    }
                    $modelConfigureOnlyFieldSet[strtolower($name)] = true;
                }

                $primaryKey = '';
                if (method_exists($model, 'getPrimaryKey')) {
                    $primaryKey = trim((string) $model->getPrimaryKey());
                }

                $jsonOrderedFields = [];
                $extraFields = [];
                foreach (array_keys($rules) as $fieldName) {
                    $name = trim((string) $fieldName);
                    if ($name === '') {
                        continue;
                    }
                    if ($primaryKey !== '' && strcasecmp($name, $primaryKey) === 0) {
                        continue;
                    }
                    $nameLower = strtolower($name);
                    $isBuilderLocked = isset($builderLockedFieldMap[$nameLower]) || isset($untouchableFieldMap[$nameLower]);
                    if ($isBuilderLocked) {
                        // Untouchable field: do not show it in the "Model fields" list.
                        continue;
                    }

                    $isDefinedInModelPhp = isset($modelConfigureOnlyFieldSet[$nameLower]);
                    $isDefinedInSchemaJson = isset($schemaFieldMap[$nameLower]);
                    if (!$isDefinedInModelPhp && !$isDefinedInSchemaJson) {
                        // Runtime/extension field: do not show it in the editor UI.
                        continue;
                    }

                    $entry = [
                        'name' => $name,
                        'builder_locked' => $isBuilderLocked,
                        'can_delete' => !$isDefinedInModelPhp,
                        'config' => is_array($schemaFieldConfigMap[$nameLower] ?? null)
                            ? $schemaFieldConfigMap[$nameLower]
                            : self::buildMinimalEditorFieldConfig($name),
                    ];

                    if (isset($schemaFieldOrderMap[$nameLower])) {
                        $jsonOrderedFields[(int) $schemaFieldOrderMap[$nameLower]] = $entry;
                    } else {
                        $extraFields[] = $entry;
                    }
                }

                $fields = [];
                if (!empty($jsonOrderedFields)) {
                    ksort($jsonOrderedFields, SORT_NUMERIC);
                    foreach ($jsonOrderedFields as $entry) {
                        $fields[] = $entry;
                    }
                }
                if (!empty($extraFields)) {
                    $fields = array_merge($fields, $extraFields);
                }

                return [
                    'fields' => $fields,
                    'error' => '',
                ];
            }
        }

        return [
            'fields' => [],
            'error' => 'You cannot edit this table: model not found.',
        ];
    }

    /**
     * @param array<int,array{dir:string,namespace:string}> $locations
     */
    private static function pushModelLocation(array &$locations, string $dir, string $namespace): void
    {
        $dir = trim($dir);
        $namespace = trim($namespace, '\\');
        if ($dir === '') {
            return;
        }

        $key = strtolower(rtrim($dir, '/\\')) . '|' . strtolower($namespace);
        foreach ($locations as $existing) {
            $existingDir = strtolower(rtrim((string) ($existing['dir'] ?? ''), '/\\'));
            $existingNs = strtolower((string) ($existing['namespace'] ?? ''));
            if ($existingDir . '|' . $existingNs === $key) {
                return;
            }
        }

        $locations[] = [
            'dir' => rtrim($dir, '/\\'),
            'namespace' => $namespace,
        ];
    }

    private static function toStudlyCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/[^A-Za-z0-9 ]+/', ' ', (string) $value);
        $value = ucwords(strtolower(trim((string) $value)));
        return str_replace(' ', '', $value);
    }

    /**
     * @param array<string,mixed> $fieldDef
     * @return array<string,mixed>
     */
    private static function buildEditorFieldConfigFromSchema(array $fieldDef, string $fallbackName): array
    {
        $fieldName = trim((string) ($fieldDef['name'] ?? ''));
        if ($fieldName === '') {
            $fieldName = $fallbackName;
        }
        $method = trim((string) ($fieldDef['method'] ?? 'string'));
        $schemaType = strtolower(trim((string) ($fieldDef['type'] ?? '')));
        $formTypeRaw = trim((string) ($fieldDef['formType'] ?? ''));
        $belongsTo = is_array($fieldDef['belongsTo'] ?? null) ? $fieldDef['belongsTo'] : [];
        $hasBelongsTo = trim((string) ($belongsTo['related_model'] ?? '')) !== '';
        $editorType = $method === 'decimal' ? 'int' : $method;
        $optionsSource = is_array($fieldDef['optionsSource'] ?? null) ? $fieldDef['optionsSource'] : [];
        $optionsSourceMode = strtolower(trim((string) ($optionsSource['mode'] ?? '')));
        if ($editorType === 'select' && $optionsSourceMode === 'relation') {
            $editorType = 'relation';
        }
        if ($hasBelongsTo) {
            $isSelectLike = in_array(strtolower($editorType), ['select', 'list'], true);
            $isMilkSelect = strtolower($formTypeRaw) === 'milkselect';
            if (!$isSelectLike || $isMilkSelect || $optionsSourceMode === 'relation') {
                $editorType = 'relation';
            }
        }
        if ($method === 'field' && $schemaType === 'html') {
            $editorType = 'html';
        }
        if (strtolower($formTypeRaw) === 'hidden') {
            $editorType = 'hidden';
        }
        $formParams = is_array($fieldDef['formParams'] ?? null) ? $fieldDef['formParams'] : [];
        $fieldLabel = trim((string) ($fieldDef['label'] ?? ''));
        if ($fieldLabel === '') {
            $fieldLabel = trim((string) ($formParams['label'] ?? ''));
        }
        if ($fieldLabel === '') {
            $fieldLabel = self::toTitle($fieldName);
        }

        $config = [
            'field_name' => $fieldName,
            'field_label' => $fieldLabel,
            'type' => $editorType,
            '_draft_minimal' => false,
        ];
        if ($method === 'decimal') {
            $digits = (int) ($fieldDef['length'] ?? 10);
            $precision = (int) ($fieldDef['precision'] ?? 2);
            $config['db_type'] = 'decimal';
            $config['db_type_params'] = [
                'digits' => $digits > 0 ? $digits : 10,
                'precision' => $precision >= 0 ? $precision : 0,
            ];
            $config['number_decimals'] = $config['db_type_params']['precision'];
            $config['form_type'] = 'number';
        }
        if ($method === 'text') {
            $textDbType = strtolower(trim((string) ($fieldDef['dbType'] ?? '')));
            if (in_array($textDbType, ['tinytext', 'text', 'mediumtext', 'longtext'], true)) {
                $config['db_type'] = $textDbType;
            }
        }
        if (array_key_exists('formType', $fieldDef) && trim((string) $fieldDef['formType']) !== '') {
            $config['form_type'] = trim((string) $fieldDef['formType']);
        }

        $required = null;
        if (array_key_exists('required', $fieldDef)) {
            $required = self::normalizeBool($fieldDef['required']);
        }
        if (array_key_exists('nullable', $fieldDef) && !self::normalizeBool($fieldDef['nullable'])) {
            $required = true;
        }
        if ($required !== null) {
            $config['required'] = $required;
        }
        if (array_key_exists('default', $fieldDef)) {
            $config['default'] = $fieldDef['default'];
        }
        $customAlignment = '';
        if (array_key_exists('custom_alignment', $fieldDef)) {
            $customAlignment = self::normalizeCustomAlignment((string) $fieldDef['custom_alignment']);
        }
        if ($customAlignment === '') {
            $columns = (int) ($formParams['columns'] ?? 0);
            if ($columns >= 2 && $columns <= 4) {
                $customAlignment = 'vertical_' . $columns;
            } elseif (array_key_exists('inline', $formParams)) {
                $customAlignment = self::normalizeBool($formParams['inline']) ? 'horizontal' : 'vertical_1';
            } elseif (strcasecmp(trim((string) ($formParams['label-position'] ?? '')), 'left') === 0) {
                $customAlignment = 'vertical_1';
            }
        }
        if (
            $customAlignment !== ''
            && in_array(strtolower($editorType), ['checkboxes', 'radio'], true)
        ) {
            $config['custom_alignment'] = $customAlignment;
        }
        if (array_key_exists('unsigned', $fieldDef) && $editorType === 'int') {
            $allowNegative = !self::normalizeBool($fieldDef['unsigned']);
            $config['number_allow_negative'] = $allowNegative;
            $dbTypeParams = is_array($config['db_type_params'] ?? null) ? $config['db_type_params'] : [];
            if (!$allowNegative) {
                $dbTypeParams['unsigned'] = true;
            }
            if (!empty($dbTypeParams)) {
                $config['db_type_params'] = $dbTypeParams;
            }
        }

        if (array_key_exists('readonly', $formParams)) {
            $config['readonly'] = self::normalizeBool($formParams['readonly']);
        }
        if (!empty($config['readonly'])) {
            $config['required'] = false;
        }
        if (array_key_exists('help-text', $formParams)) {
            $helpText = trim((string) ($formParams['help-text'] ?? ''));
            if ($helpText !== '') {
                $config['help_text'] = $helpText;
            }
        } elseif (array_key_exists('helpText', $fieldDef)) {
            $helpText = trim((string) ($fieldDef['helpText'] ?? ''));
            if ($helpText !== '') {
                $config['help_text'] = $helpText;
            }
        } elseif (array_key_exists('help_text', $fieldDef)) {
            $helpText = trim((string) ($fieldDef['help_text'] ?? ''));
            if ($helpText !== '') {
                $config['help_text'] = $helpText;
            }
        }
        if (array_key_exists('data-milk-show', $formParams)) {
            $showIf = trim((string) ($formParams['data-milk-show'] ?? ''));
            if ($showIf !== '') {
                $config['show_if'] = $showIf;
            }
        } elseif (array_key_exists('showIf', $fieldDef)) {
            $showIf = trim((string) ($fieldDef['showIf'] ?? ''));
            if ($showIf !== '') {
                $config['show_if'] = $showIf;
            }
        } elseif (array_key_exists('show_if', $fieldDef)) {
            $showIf = trim((string) ($fieldDef['show_if'] ?? ''));
            if ($showIf !== '') {
                $config['show_if'] = $showIf;
            }
        }

        $visibility = [];
        if (array_key_exists('hideFromList', $fieldDef)) {
            $visibility['list'] = !self::normalizeBool($fieldDef['hideFromList']);
        }
        if (array_key_exists('hideFromEdit', $fieldDef)) {
            $visibility['edit'] = !self::normalizeBool($fieldDef['hideFromEdit']);
        }
        if (array_key_exists('hideFromView', $fieldDef)) {
            $visibility['view'] = !self::normalizeBool($fieldDef['hideFromView']);
        }
        if (!empty($visibility)) {
            $config['visibility'] = $visibility;
        }

        if (array_key_exists('options', $fieldDef)) {
            $config['options'] = $fieldDef['options'];
        }
        if (array_key_exists('optionsSource', $fieldDef)) {
            $config['options_source'] = $fieldDef['optionsSource'];
        }
        if ($hasBelongsTo) {
            $config['relation_model'] = trim((string) ($belongsTo['related_model'] ?? ''));
            $config['relation_alias'] = trim((string) ($belongsTo['alias'] ?? ''));
            $config['relation_value_field'] = trim((string) ($belongsTo['related_key'] ?? 'id'));

            $apiUrl = $fieldDef['apiUrl'] ?? null;
            $displayField = '';
            $apiUrlValue = '';
            if (is_array($apiUrl)) {
                $apiUrlValue = trim((string) ($apiUrl['url'] ?? ''));
                $displayField = trim((string) ($apiUrl['display_field'] ?? ''));
            } elseif (is_string($apiUrl)) {
                $apiUrlValue = trim($apiUrl);
            }
            if ($displayField === '' && array_key_exists('apiDisplayField', $fieldDef)) {
                $displayField = trim((string) ($fieldDef['apiDisplayField'] ?? ''));
            }
            if ($displayField !== '') {
                $config['relation_label_field'] = $displayField;
            }

            if ($apiUrlValue !== '') {
                $parsedQuery = parse_url($apiUrlValue, PHP_URL_QUERY);
                if (is_string($parsedQuery) && $parsedQuery !== '') {
                    $queryParams = [];
                    parse_str($parsedQuery, $queryParams);
                    $pageParam = trim((string) ($queryParams['page'] ?? ''));
                    if ($pageParam !== '') {
                        $config['relation_module_page'] = $pageParam;
                    }
                }
            }

            // relation where
            $where = is_array($belongsTo['where'] ?? null) ? $belongsTo['where'] : [];
            $whereCondition = trim((string) ($where['condition'] ?? ''));
            if ($whereCondition !== '') {
                $config['relation_where'] = $whereCondition;
            }

            // relation multiple
            $multipleRaw = $fieldDef['multiple'] ?? ($formParams['multiple'] ?? null);
            if ($multipleRaw !== null && self::normalizeBool($multipleRaw)) {
                $config['allow_multiple'] = true;
            }
        }

        $validation = [];
        foreach (['min', 'max', 'step'] as $validationKey) {
            if (array_key_exists($validationKey, $fieldDef)) {
                $validation[$validationKey] = $fieldDef[$validationKey];
            }
        }
        if (array_key_exists('validateExpr', $fieldDef)) {
            $validation['validate_expr'] = $fieldDef['validateExpr'];
        }
        if (array_key_exists('error', $fieldDef)) {
            $validation['error_message'] = $fieldDef['error'];
        }
        if (!empty($validation)) {
            $config['validation'] = $validation;
        }

        if (array_key_exists('calcExpr', $fieldDef)) {
            $config['calc_expr'] = $fieldDef['calcExpr'];
        }
        if (array_key_exists('html', $fieldDef)) {
            $config['html_content'] = (string) ($fieldDef['html'] ?? '');
        } elseif (array_key_exists('html_content', $fieldDef)) {
            $config['html_content'] = (string) ($fieldDef['html_content'] ?? '');
        }
        if (array_key_exists('excludeFromDatabase', $fieldDef)) {
            $config['exclude_from_db'] = self::normalizeBool($fieldDef['excludeFromDatabase']);
        }
        // multiple (select / relation)
        if (in_array(strtolower($editorType), ['select', 'list', 'relation'], true)) {
            $multipleRaw = $fieldDef['multiple'] ?? ($formParams['multiple'] ?? null);
            if ($multipleRaw !== null && self::normalizeBool($multipleRaw)) {
                $config['allow_multiple'] = true;
            }
        }

        // allowEmpty (select)
        if (in_array(strtolower($editorType), ['select', 'list'], true)) {
            if (isset($fieldDef['allowEmpty']) && self::normalizeBool($fieldDef['allowEmpty'])) {
                $config['allow_empty'] = true;
            }
        }

        if (in_array(strtolower($editorType), ['file', 'image'], true)) {
            $maxFilesRaw = $fieldDef['maxFiles'] ?? ($formParams['max-files'] ?? null);
            $maxFiles = 0;
            if ($maxFilesRaw !== null) {
                $maxFiles = (int) $maxFilesRaw;
                if ($maxFiles > 0) {
                    $config['max_files'] = $maxFiles;
                }
            }
            $multipleRaw = $fieldDef['multiple'] ?? ($formParams['multiple'] ?? null);
            $multipleEnabled = false;
            if ($multipleRaw !== null) {
                $multipleEnabled = self::normalizeBool($multipleRaw) || strtolower(trim((string) $multipleRaw)) === 'multiple';
            }
            $config['sortable'] = ($maxFiles > 1) || ($maxFiles <= 0 && $multipleEnabled);
        }

        if (isset($fieldDef['listOptions']) && is_array($fieldDef['listOptions'])) {
            $lo = $fieldDef['listOptions'];
            $converted = [];

            if (isset($lo['link']) && is_array($lo['link'])) {
                $converted['link'] = $lo['link'];
            }

            if (!empty($lo['html'])) {
                $converted['html'] = true;
            }

            if (isset($lo['truncate'])) {
                $converted['truncate'] = (int) $lo['truncate'];
            }

            if (isset($lo['changeValues']) && is_array($lo['changeValues'])) {
                $converted['change_values'] = $lo['changeValues'];
            }

            if (isset($lo['relationFields']) && is_array($lo['relationFields'])) {
                $relationFields = [];
                $seenRelationFields = [];
                foreach ($lo['relationFields'] as $entry) {
                    $fieldName = '';
                    $customLabel = '';
                    if (is_array($entry)) {
                        $fieldName = trim((string) ($entry['field'] ?? ($entry['name'] ?? '')));
                        $customLabel = trim((string) ($entry['label'] ?? ''));
                    } else {
                        $fieldName = trim((string) $entry);
                    }
                    if ($fieldName === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $fieldName) !== 1) {
                        continue;
                    }
                    $lower = strtolower($fieldName);
                    if (isset($seenRelationFields[$lower])) {
                        continue;
                    }
                    $seenRelationFields[$lower] = true;
                    if ($customLabel !== '') {
                        $relationFields[] = [
                            'field' => $fieldName,
                            'label' => $customLabel,
                        ];
                    } else {
                        $relationFields[] = $fieldName;
                    }
                }
                if (!empty($relationFields)) {
                    $converted['relation_fields'] = $relationFields;
                }
            }

            if (!empty($converted)) {
                $config['list_options'] = $converted;
            }
        }

        if ($editorType === 'hidden') {
            $config['form_type'] = 'hidden';
            unset(
                $config['required'],
                $config['readonly'],
                $config['help_text'],
                $config['show_if'],
                $config['validation'],
                $config['default'],
                $config['custom_alignment']
            );
        }

        return $config;
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildMinimalEditorFieldConfig(string $fieldName): array
    {
        return [
            'field_name' => trim($fieldName),
            'field_label' => self::toTitle($fieldName),
            'type' => 'string',
            '_draft_minimal' => true,
        ];
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

    private static function normalizeCustomAlignment(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === 'horizontal') {
            return 'horizontal';
        }
        if ($normalized === 'vertical') {
            return 'vertical_1';
        }
        if (in_array($normalized, ['vertical_1', 'vertical_2', 'vertical_3', 'vertical_4'], true)) {
            return $normalized;
        }
        return '';
    }

    private static function toTitle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);
        $value = str_replace(['_', '-'], ' ', (string) $value);
        $value = preg_replace('/\\s+/', ' ', (string) $value);

        return ucwords(strtolower(trim((string) $value)));
    }
}
