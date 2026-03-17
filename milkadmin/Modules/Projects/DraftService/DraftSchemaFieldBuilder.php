<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

class DraftSchemaFieldBuilder
{
    /**
     * @param array{name:string,builder_locked:bool,can_delete:bool,config:array<string,mixed>} $draftField
     * @param array<string,mixed> $baseField
     * @return array<string,mixed>
     */
    public static function build(array $draftField, array $baseField, bool $isDefinedInModelPhp = false): array
    {
        $name = trim((string) ($draftField['name'] ?? ''));
        $config = is_array($draftField['config'] ?? null) ? $draftField['config'] : [];
        $isBuilderLocked = DraftFieldUtils::normalizeBool(
            $draftField['builder_locked']
            ?? ($draftField['builderLocked']
            ?? ($baseField['builderLocked']
            ?? ($baseField['builder_locked'] ?? false)))
        );

        $isMinimalDraft = DraftFieldUtils::normalizeBool($config['_draft_minimal'] ?? false);
        if ($isMinimalDraft && !empty($baseField)) {
            $result = $baseField;
            $result['name'] = $name;
            return self::finalize($result, $baseField, $isDefinedInModelPhp, $isBuilderLocked);
        }
        if ($isMinimalDraft && empty($baseField) && $isDefinedInModelPhp) {
            return self::finalize(
                ['name' => $name],
                $baseField,
                $isDefinedInModelPhp,
                $isBuilderLocked
            );
        }

        $result = is_array($baseField) ? $baseField : [];
        $result['name'] = $name;

        $label = trim((string) ($config['field_label'] ?? ''));
        if ($label !== '') {
            $result['label'] = $label;
        } elseif (trim((string) ($result['label'] ?? '')) === '') {
            $result['label'] = DraftFieldUtils::toTitle($name);
        }

        $method = trim((string) ($config['type'] ?? ''));
        $isRelationField = strcasecmp($method, 'relation') === 0;
        if ($isRelationField) {
            $method = 'int';
        }
        if ($method !== '') {
            $result['method'] = $method;
        } elseif (trim((string) ($result['method'] ?? '')) === '') {
            $result['method'] = 'string';
        }

        $isHiddenField = strcasecmp((string) ($config['type'] ?? ''), 'hidden') === 0
            || strcasecmp((string) ($config['form_type'] ?? ''), 'hidden') === 0;

        if ($isHiddenField) {
            $resolvedHiddenMethod = strtolower(trim((string) ($baseField['method'] ?? $result['method'] ?? '')));
            if ($resolvedHiddenMethod === '' || in_array($resolvedHiddenMethod, ['hidden', 'field', 'html'], true)) {
                $resolvedHiddenMethod = 'string';
            }
            $method = $resolvedHiddenMethod;
            $result['method'] = $resolvedHiddenMethod;
        }

        self::applyDbType($result, $config, $method);
        self::applyNumericProps($result, $config, $method);
        self::applyFormType($result, $config, $isHiddenField);
        self::applyRequired($result, $config, $isHiddenField);
        self::applyDefault($result, $config);
        self::applyFormParams($result, $config, $name, $method, $isHiddenField);
        self::applyUploadConfig($result, $config, $method);

        if ($isHiddenField) {
            unset($result['required']);
            unset($result['default'], $result['custom_alignment']);
            unset($result['min'], $result['max'], $result['step'], $result['validateExpr'], $result['error']);
        }

        // The field-level "disabled" concept is not managed anymore in Projects builder.
        unset($result['disabled']);
        $formParams = is_array($result['formParams'] ?? null) ? $result['formParams'] : [];
        if ($isHiddenField) {
            unset($formParams['readonly'], $formParams['data-milk-show'], $formParams['help-text']);
        }
        if (array_key_exists('disabled', $formParams)) {
            unset($formParams['disabled']);
        }
        if (!empty($formParams)) {
            $result['formParams'] = $formParams;
        } else {
            unset($result['formParams']);
        }

        self::applyVisibility($result, $config);
        self::applyValidation($result, $config, $isHiddenField);

        if (is_array($config['options'] ?? null)) {
            $result['options'] = $config['options'];
        } elseif ($isRelationField || array_key_exists('options', $config)) {
            unset($result['options']);
        }
        if (!$isRelationField) {
            self::applyOptionsSource($result, $config, $isHiddenField);
        } else {
            unset($result['optionsSource']);
        }
        self::applyRelationConfig($result, $config, $name, $isHiddenField);

        self::applyCalcExpr($result, $config, $method, $isHiddenField);
        self::applyHtmlContent($result, $config, $method);
        self::applyListOptions($result, $config);

        // multiple (select / relation)
        if (array_key_exists('allow_multiple', $config)) {
            if (!$isHiddenField && DraftFieldUtils::normalizeBool($config['allow_multiple'])) {
                $result['multiple'] = true;
                // Override method to text type for storing JSON array in DB
                $multipleDbType = strtolower(trim((string) ($config['db_type'] ?? '')));
                if (in_array($multipleDbType, ['tinytext', 'text', 'mediumtext', 'longtext'], true)) {
                    $result['method'] = 'text';
                    if ($multipleDbType !== 'text') {
                        $result['dbType'] = $multipleDbType;
                    }
                } elseif ($multipleDbType === 'varchar') {
                    $result['method'] = 'string';
                } elseif ($result['method'] === 'int') {
                    // Default: int with multiple → tinytext
                    $result['method'] = 'text';
                    $result['dbType'] = 'tinytext';
                }
            } else {
                unset($result['multiple']);
            }
        }

        // allowEmpty (select)
        if (array_key_exists('allow_empty', $config)) {
            if (!$isHiddenField && DraftFieldUtils::normalizeBool($config['allow_empty'])) {
                $result['allowEmpty'] = true;
            } else {
                unset($result['allowEmpty']);
            }
        }

        if (array_key_exists('exclude_from_db', $config)) {
            $result['excludeFromDatabase'] = DraftFieldUtils::normalizeBool($config['exclude_from_db']);
        }
        if ($method === 'html') {
            $result['excludeFromDatabase'] = true;
        }

        return self::finalize($result, $baseField, $isDefinedInModelPhp, $isBuilderLocked);
    }

    /**
     * @param array<string,mixed> $result
     * @param array<string,mixed> $baseField
     * @return array<string,mixed>
     */
    public static function finalize(
        array $result,
        array $baseField,
        bool $isDefinedInModelPhp,
        bool $isBuilderLocked
    ): array {
        if ($isDefinedInModelPhp) {
            foreach (['method', 'dbType', 'length', 'precision', 'unsigned', 'excludeFromDatabase'] as $structuralKey) {
                if (array_key_exists($structuralKey, $baseField)) {
                    $result[$structuralKey] = $baseField[$structuralKey];
                } else {
                    unset($result[$structuralKey]);
                }
            }
        }

        if ($isBuilderLocked || DraftFieldUtils::isFieldBuilderLocked($baseField)) {
            $result['builderLocked'] = true;
        } else {
            unset($result['builderLocked'], $result['builder_locked']);
        }

        return $result;
    }

    // ─── Private helpers ───────────────────────────────────────────────

    private static function applyDbType(array &$result, array $config, string $method): void
    {
        if ($method === 'text') {
            $textDbType = strtolower(trim((string) ($config['db_type'] ?? '')));
            if (in_array($textDbType, ['tinytext', 'text', 'mediumtext', 'longtext'], true) && $textDbType !== 'text') {
                $result['dbType'] = $textDbType;
            } else {
                unset($result['dbType']);
            }
        } else {
            unset($result['dbType']);
        }
    }

    private static function applyNumericProps(array &$result, array $config, string $method): void
    {
        $dbType = strtolower(trim((string) ($config['db_type'] ?? '')));
        $dbTypeParams = is_array($config['db_type_params'] ?? null) ? $config['db_type_params'] : [];
        $numberDecimalsRaw = $config['number_decimals'] ?? null;
        $numberDecimals = is_numeric($numberDecimalsRaw) ? (int) $numberDecimalsRaw : null;

        $isDecimalNumber = $method === 'int'
            && (in_array($dbType, ['decimal', 'float', 'double'], true) || ($numberDecimals !== null && $numberDecimals > 0));

        if ($isDecimalNumber) {
            $result['method'] = 'decimal';
            $digitsRaw = $dbTypeParams['digits'] ?? ($result['length'] ?? 10);
            $precisionRaw = $dbTypeParams['precision'] ?? ($numberDecimals ?? ($result['precision'] ?? 2));
            $digits = (int) $digitsRaw;
            $precision = (int) $precisionRaw;
            $result['length'] = $digits > 0 ? $digits : 10;
            $result['precision'] = $precision >= 0 ? $precision : 0;
        } elseif ($method === 'int') {
            unset($result['length'], $result['precision']);
        }

        if ($method === 'int') {
            $unsigned = null;
            if (array_key_exists('number_allow_negative', $config)) {
                $unsigned = !DraftFieldUtils::normalizeBool($config['number_allow_negative']);
            } elseif (array_key_exists('unsigned', $dbTypeParams)) {
                $unsigned = DraftFieldUtils::normalizeBool($dbTypeParams['unsigned']);
            }
            if ($unsigned === true) {
                $result['unsigned'] = true;
            } else {
                unset($result['unsigned']);
            }
        } else {
            unset($result['unsigned']);
        }
    }

    private static function applyFormType(array &$result, array $config, bool $isHiddenField): void
    {
        if (array_key_exists('form_type', $config)) {
            $formType = trim((string) ($config['form_type'] ?? ''));
            if ($isHiddenField) {
                $result['formType'] = 'hidden';
            } elseif ($formType !== '' && strcasecmp($formType, (string) ($result['method'] ?? '')) !== 0) {
                $result['formType'] = $formType;
            } else {
                unset($result['formType']);
            }
        } elseif ($isHiddenField) {
            $result['formType'] = 'hidden';
        }
    }

    private static function applyRequired(array &$result, array $config, bool $isHiddenField): void
    {
        if (array_key_exists('required', $config)) {
            if (!$isHiddenField && DraftFieldUtils::normalizeBool($config['required'])) {
                $result['required'] = true;
            } else {
                unset($result['required']);
            }
        } elseif ($isHiddenField) {
            unset($result['required']);
        }
        unset($result['nullable']);
    }

    private static function applyDefault(array &$result, array $config): void
    {
        if (array_key_exists('default', $config)) {
            $defaultValue = $config['default'];
            if ($defaultValue === '' || $defaultValue === null) {
                unset($result['default']);
            } else {
                $result['default'] = $defaultValue;
            }
        } else {
            unset($result['default']);
        }
    }

    private static function applyUploadConfig(array &$result, array $config, string $method): void
    {
        $resolvedMethod = strtolower(trim((string) ($result['method'] ?? $method)));
        if (!in_array($resolvedMethod, ['file', 'image'], true)) {
            unset($result['maxFiles']);
            return;
        }

        if (!array_key_exists('max_files', $config)) {
            return;
        }

        $maxFiles = (int) ($config['max_files'] ?? 0);
        if ($maxFiles < 1) {
            $maxFiles = 1;
        }
        $result['maxFiles'] = $maxFiles;
    }

    private static function applyFormParams(array &$result, array $config, string $name, string $method, bool $isHiddenField): void
    {
        // readonly
        $readonly = DraftFieldUtils::normalizeBool($config['readonly'] ?? false);
        if ($readonly) {
            unset($result['required']);
        }
        $formParams = is_array($result['formParams'] ?? null) ? $result['formParams'] : [];
        if (!$isHiddenField && $readonly) {
            $formParams['readonly'] = true;
        } else {
            unset($formParams['readonly']);
        }
        if (!empty($formParams)) {
            $result['formParams'] = $formParams;
        } else {
            unset($result['formParams']);
        }

        // show_if
        $showIf = trim((string) ($config['show_if'] ?? ''));
        $formParams = is_array($result['formParams'] ?? null) ? $result['formParams'] : [];
        if (!$isHiddenField && $showIf !== '') {
            $formParams['data-milk-show'] = $showIf;
        } else {
            unset($formParams['data-milk-show']);
        }
        if (!empty($formParams)) {
            $result['formParams'] = $formParams;
        } else {
            unset($result['formParams']);
        }

        // help_text
        $helpText = trim((string) ($config['help_text'] ?? ''));
        $formParams = is_array($result['formParams'] ?? null) ? $result['formParams'] : [];
        if (!$isHiddenField && $helpText !== '') {
            $formParams['help-text'] = $helpText;
        } else {
            unset($formParams['help-text']);
        }
        if (!empty($formParams)) {
            $result['formParams'] = $formParams;
        } else {
            unset($result['formParams']);
        }

        // group label for radio/checkboxes
        $methodForGroupLabel = strtolower(trim((string) ($result['method'] ?? '')));
        $formParams = is_array($result['formParams'] ?? null) ? $result['formParams'] : [];
        if (in_array($methodForGroupLabel, ['radio', 'checkboxes'], true)) {
            $explicitGroupLabel = trim((string) ($result['label'] ?? ''));
            if ($explicitGroupLabel === '') {
                $explicitGroupLabel = DraftFieldUtils::toTitle($name);
            }
            $formParams['label'] = $explicitGroupLabel;
        } else {
            unset($formParams['label']);
        }
        if (!empty($formParams)) {
            $result['formParams'] = $formParams;
        } else {
            unset($result['formParams']);
        }

        // custom_alignment
        $customAlignmentRaw = strtolower(trim((string) ($config['custom_alignment'] ?? '')));
        if ($customAlignmentRaw === 'vertical') {
            $customAlignmentRaw = 'vertical_1';
        }
        $alignmentAllowedMethods = ['checkboxes', 'radio'];
        $alignmentGroupMethods = ['checkboxes', 'radio'];
        $resolvedMethodForAlignment = strtolower(trim((string) ($result['method'] ?? $method)));
        $formParams = is_array($result['formParams'] ?? null) ? $result['formParams'] : [];
        if (
            !$isHiddenField
            && in_array($resolvedMethodForAlignment, $alignmentAllowedMethods, true)
            && in_array($customAlignmentRaw, ['horizontal', 'vertical_1', 'vertical_2', 'vertical_3', 'vertical_4'], true)
        ) {
            $result['custom_alignment'] = $customAlignmentRaw;
            if (in_array($resolvedMethodForAlignment, $alignmentGroupMethods, true)) {
                $columnsCount = 1;
                if (preg_match('/^vertical_(\\d)$/', $customAlignmentRaw, $matches)) {
                    $columnsCount = (int) $matches[1];
                }
                $formParams['inline'] = $customAlignmentRaw === 'horizontal';
                if ($customAlignmentRaw !== 'horizontal' && $columnsCount >= 2 && $columnsCount <= 4) {
                    $formParams['columns'] = $columnsCount;
                } else {
                    unset($formParams['columns']);
                }
                $formParams['label-position'] = 'left';
                if (trim((string) ($formParams['label-width'] ?? '')) === '') {
                    $formParams['label-width'] = '8rem';
                }
            } else {
                unset($formParams['inline'], $formParams['columns'], $formParams['label-position'], $formParams['label-width']);
            }
        } else {
            unset($result['custom_alignment']);
            unset($formParams['inline'], $formParams['columns'], $formParams['label-position'], $formParams['label-width']);
        }
        if (!empty($formParams)) {
            $result['formParams'] = $formParams;
        } else {
            unset($result['formParams']);
        }
    }

    private static function applyVisibility(array &$result, array $config): void
    {
        $visibility = is_array($config['visibility'] ?? null) ? $config['visibility'] : [];
        if (array_key_exists('list', $visibility)) {
            $result['hideFromList'] = !DraftFieldUtils::normalizeBool($visibility['list']);
        }
        if (array_key_exists('edit', $visibility)) {
            $result['hideFromEdit'] = !DraftFieldUtils::normalizeBool($visibility['edit']);
        }
        if (array_key_exists('view', $visibility)) {
            $result['hideFromView'] = !DraftFieldUtils::normalizeBool($visibility['view']);
        }
    }

    private static function applyValidation(array &$result, array $config, bool $isHiddenField): void
    {
        unset($result['min'], $result['max'], $result['step'], $result['validateExpr'], $result['error']);
        $validation = is_array($config['validation'] ?? null) ? $config['validation'] : [];
        if (!$isHiddenField) {
            if (array_key_exists('min', $validation) && trim((string) $validation['min']) !== '') {
                $result['min'] = $validation['min'];
            }
            if (array_key_exists('max', $validation) && trim((string) $validation['max']) !== '') {
                $result['max'] = $validation['max'];
            }
            if (array_key_exists('step', $validation) && trim((string) $validation['step']) !== '') {
                $result['step'] = $validation['step'];
            }
            if (array_key_exists('validate_expr', $validation) && trim((string) $validation['validate_expr']) !== '') {
                $result['validateExpr'] = trim((string) $validation['validate_expr']);
            }
            if (array_key_exists('error_message', $validation) && trim((string) $validation['error_message']) !== '') {
                $result['error'] = trim((string) $validation['error_message']);
            }
        }
    }

    private static function applyCalcExpr(array &$result, array $config, string $method, bool $isHiddenField): void
    {
        $calcExpr = trim((string) ($config['calc_expr'] ?? ''));
        $calcAllowedMethods = ['string', 'text', 'int', 'decimal'];
        $resolvedMethod = strtolower(trim((string) ($result['method'] ?? $method)));
        if ($calcExpr !== '' && ($isHiddenField || in_array($resolvedMethod, $calcAllowedMethods, true))) {
            $result['calcExpr'] = $calcExpr;
        } else {
            unset($result['calcExpr']);
        }
    }

    private static function applyOptionsSource(array &$result, array $config, bool $isHiddenField): void
    {
        if ($isHiddenField || !array_key_exists('options_source', $config)) {
            unset($result['optionsSource']);
            return;
        }

        $source = is_array($config['options_source'] ?? null) ? $config['options_source'] : [];
        $mode = strtolower(trim((string) ($source['mode'] ?? '')));
        $model = trim((string) ($source['model'] ?? ($source['table'] ?? '')));
        $valueField = trim((string) ($source['value_field'] ?? ''));
        $labelField = trim((string) ($source['label_field'] ?? ''));
        $where = trim((string) ($source['where'] ?? ''));

        if (!in_array($mode, ['all', 'ajax'], true)) {
            $mode = '';
        }

        if ($mode === '' || $model === '' || $valueField === '' || $labelField === '') {
            unset($result['optionsSource']);
            return;
        }

        $result['optionsSource'] = [
            'mode' => $mode,
            'model' => $model,
            'value_field' => $valueField,
            'label_field' => $labelField,
        ];
        if ($where !== '') {
            $result['optionsSource']['where'] = $where;
        }
    }

    private static function applyRelationConfig(array &$result, array $config, string $fieldName, bool $isHiddenField): void
    {
        $type = strtolower(trim((string) ($config['type'] ?? '')));
        $isRelationField = $type === 'relation';
        $isSelectLikeField = in_array($type, ['select', 'list'], true);
        $hasExistingBelongsTo = isset($result['belongsTo']) && is_array($result['belongsTo']);
        $legacySource = is_array($config['options_source'] ?? null) ? $config['options_source'] : [];
        $relationModel = trim((string) ($config['relation_model'] ?? ($legacySource['model'] ?? ($legacySource['table'] ?? ''))));
        $shouldApplyBelongsTo = ($isRelationField || $isSelectLikeField) && $relationModel !== '';
        if (!$shouldApplyBelongsTo || $isHiddenField) {
            if ($hasExistingBelongsTo) {
                unset($result['belongsTo'], $result['apiUrl']);
            }
            return;
        }

        $relationAlias = self::normalizeRelationAlias((string) ($config['relation_alias'] ?? ''));
        if ($relationAlias === '') {
            $relationAlias = self::buildRelationAliasFromModelClass($relationModel);
        }
        if ($relationAlias === '') {
            unset($result['belongsTo'], $result['apiUrl']);
            return;
        }

        $relationValueField = trim((string) ($config['relation_value_field'] ?? ($legacySource['value_field'] ?? 'id')));
        if ($relationValueField === '') {
            $relationValueField = 'id';
        }

        if ($isRelationField) {
            $result['method'] = 'int';
            $result['formType'] = 'milkSelect';
        } else {
            unset($result['apiUrl']);
        }
        $belongsTo = [
            'alias' => $relationAlias,
            'related_model' => $relationModel,
            'related_key' => $relationValueField,
        ];

        // relation where condition
        $relationWhere = trim((string) ($config['relation_where'] ?? ''));
        if ($relationWhere !== '') {
            $belongsTo['where'] = ['condition' => $relationWhere, 'params' => []];
        } else {
            // preserve existing where if present and no new value
            $existingWhere = $result['belongsTo']['where'] ?? null;
            if (is_array($existingWhere) && trim((string) ($existingWhere['condition'] ?? '')) !== '') {
                $belongsTo['where'] = $existingWhere;
            }
        }

        $result['belongsTo'] = $belongsTo;

        $relationLabelField = trim((string) ($config['relation_label_field'] ?? ($legacySource['label_field'] ?? '')));
        $relationModulePage = trim((string) ($config['relation_module_page'] ?? ''));
        if ($isRelationField && $relationLabelField !== '' && $relationModulePage !== '' && $fieldName !== '') {
            $result['apiUrl'] = [
                'url' => '?page=' . rawurlencode($relationModulePage)
                    . '&action=related-search-field'
                    . '&f=' . rawurlencode($fieldName),
                'display_field' => $relationLabelField,
            ];
        } elseif ($isRelationField && isset($result['apiUrl']) && is_array($result['apiUrl']) && $relationLabelField !== '') {
            $existingUrl = trim((string) ($result['apiUrl']['url'] ?? ''));
            if ($existingUrl !== '') {
                $result['apiUrl'] = [
                    'url' => $existingUrl,
                    'display_field' => $relationLabelField,
                ];
            }
        } else {
            unset($result['apiUrl']);
        }
    }

    private static function buildRelationAliasFromModelClass(string $modelClass): string
    {
        $modelClass = trim($modelClass, " \t\n\r\0\x0B\\");
        if ($modelClass === '') {
            return '';
        }

        $parts = explode('\\', $modelClass);
        $shortName = trim((string) end($parts));
        $shortName = preg_replace('/Model$/i', '', $shortName);
        $shortName = preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $shortName);
        $shortName = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', (string) $shortName);
        return self::normalizeRelationAlias((string) $shortName);
    }

    private static function normalizeRelationAlias(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^A-Za-z0-9_]+/', '_', $value);
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', (string) $value);
        $value = strtolower(trim((string) preg_replace('/_+/', '_', (string) $value), '_'));
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[0-9]/', $value) === 1) {
            $value = 'rel_' . $value;
        }
        return $value;
    }

    private static function applyHtmlContent(array &$result, array $config, string $method): void
    {
        if ($method === 'html') {
            $htmlContent = (string) ($config['html_content'] ?? '');
            if ($htmlContent !== '') {
                $result['html'] = $htmlContent;
            } else {
                unset($result['html']);
            }
        }
    }

    private static function applyListOptions(array &$result, array $config): void
    {
        $src = is_array($config['list_options'] ?? null) ? $config['list_options'] : [];
        if (empty($src)) {
            unset($result['listOptions']);
            return;
        }

        $out = [];

        $link = is_array($src['link'] ?? null) ? $src['link'] : [];
        $url = trim((string) ($link['url'] ?? ''));
        if ($url !== '') {
            $out['link'] = [
                'url' => $url,
                'target' => trim((string) ($link['target'] ?? 'same_window')),
            ];
        }

        if (!empty($src['html'])) {
            $out['html'] = true;
        }

        if (isset($src['truncate'])) {
            $len = (int) $src['truncate'];
            if ($len > 0) {
                $out['truncate'] = $len;
            }
        }

        $cv = is_array($src['change_values'] ?? null) ? $src['change_values'] : [];
        if (!empty($cv)) {
            $out['changeValues'] = (object) $cv;
        }

        $relationFieldsSrc = [];
        if (is_array($src['relation_fields'] ?? null)) {
            $relationFieldsSrc = $src['relation_fields'];
        } elseif (is_array($src['relationFields'] ?? null)) {
            $relationFieldsSrc = $src['relationFields'];
        }
        if (!empty($relationFieldsSrc)) {
            $relationFields = [];
            $seenRelationFields = [];
            foreach ($relationFieldsSrc as $entry) {
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
                $out['relationFields'] = $relationFields;
            }
        }

        if (!empty($out)) {
            $result['listOptions'] = $out;
        } else {
            unset($result['listOptions']);
        }
    }
}
