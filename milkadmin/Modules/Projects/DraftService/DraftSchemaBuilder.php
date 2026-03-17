<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

class DraftSchemaBuilder
{
    /**
     * @param array<string,mixed> $oldSchema
     * @param array<int,array{name:string,builder_locked:bool,can_delete:bool,config:array<string,mixed>}> $draftFields
     * @param array<int,array{id:string,fields:array<int,string|array<int,string>>,cols:int|array<int,int>,position_before:string,title:string,attributes:array<string,mixed>}> $draftContainers
     * @return array<string,mixed>
     */
    public static function build(array $oldSchema, array $draftFields, array $draftContainers = []): array
    {
        $newSchema = $oldSchema;
        if (!is_array($newSchema['model'] ?? null)) {
            $newSchema['model'] = [];
        }

        $oldFields = is_array($newSchema['model']['fields'] ?? null) ? $newSchema['model']['fields'] : [];
        $oldFieldMap = [];
        foreach ($oldFields as $oldField) {
            if (!is_array($oldField)) {
                continue;
            }
            $name = trim((string) ($oldField['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $oldFieldMap[strtolower($name)] = $oldField;
        }

        $newFieldMap = [];
        $newFieldOrder = [];
        foreach ($draftFields as $draftField) {
            $name = trim((string) ($draftField['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $nameLower = strtolower($name);

            $baseField = is_array($oldFieldMap[$nameLower] ?? null) ? $oldFieldMap[$nameLower] : [];
            $draftConfig = is_array($draftField['config'] ?? null) ? $draftField['config'] : [];
            $isMinimalDraft = DraftFieldUtils::normalizeBool($draftConfig['_draft_minimal'] ?? false);
            $isDefinedInModelPhp = array_key_exists('can_delete', $draftField)
                ? !DraftFieldUtils::normalizeBool($draftField['can_delete'])
                : false;
            if ($isMinimalDraft && empty($baseField) && !$isDefinedInModelPhp) {
                continue;
            }

            $newFieldMap[$nameLower] = DraftSchemaFieldBuilder::build($draftField, $baseField, $isDefinedInModelPhp);
            $newFieldOrder[] = $nameLower;
        }

        $newFields = [];
        $consumed = [];

        foreach ($newFieldOrder as $nameLower) {
            $field = $newFieldMap[$nameLower] ?? null;
            if (!is_array($field)) {
                continue;
            }
            $newFields[] = $field;
            $consumed[$nameLower] = true;
        }

        // Keep legacy locked fields hidden from builder payload.
        foreach ($oldFields as $oldField) {
            if (!is_array($oldField)) {
                continue;
            }
            $name = trim((string) ($oldField['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $nameLower = strtolower($name);
            if (isset($consumed[$nameLower])) {
                continue;
            }
            if (DraftFieldUtils::isFieldBuilderLocked($oldField)) {
                $newFields[] = $oldField;
                $consumed[$nameLower] = true;
            }
        }

        $newSchema['model']['fields'] = $newFields;
        if (!empty($draftContainers)) {
            $newSchema['model']['containers'] = $draftContainers;
        } else {
            unset($newSchema['model']['containers']);
        }

        return $newSchema;
    }
}
