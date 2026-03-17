<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;
use App\Abstracts\RuleBuilder;

!defined('MILK_DIR') && die();

class RelationshipDataService
{
    public function __construct(
        private ?RelationshipDefinitionService $relationshipDefinitionService = null
    ) {
        $this->relationshipDefinitionService ??= new RelationshipDefinitionService();
    }

    public function extractRelationshipData(
        AbstractModel $model,
        array $data,
        int $currentIndex,
        array $dirtyMeta
    ): array {
        foreach ($data as $key => $value) {
            if ($this->relationshipDefinitionService->hasMetaRelationship($model->getRuleBuilder(), $key)) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = json_encode($value);
                }

                $dirtyMeta = $this->markMetaDirty($dirtyMeta, $currentIndex, $key, $data[$key]);
                continue;
            }

            if (!$this->relationshipDefinitionService->hasRelationship($model->getRuleBuilder(), $key)) {
                continue;
            }

            $relationship = $this->relationshipDefinitionService->getRelationship($model->getRuleBuilder(), $key);
            if (!is_array($relationship) || !is_string($relationship['type'] ?? null)) {
                continue;
            }

            if (in_array($relationship['type'], ['hasOne', 'hasMany', 'belongsTo'], true) && is_array($value)) {
                $validationError = $this->validateRelationshipArray($key, $relationship['type'], $value);
                if ($validationError !== null) {
                    return [[], $dirtyMeta, true, $validationError];
                }
            } elseif (in_array($relationship['type'], ['hasOne', 'hasMany', 'belongsTo'], true) && $value instanceof AbstractModel) {
                return [[], $dirtyMeta, true, "Cannot assign Model instance to relationship '{$key}'. Use array notation instead."];
            }
        }

        return [$data, $dirtyMeta, false, ''];
    }

    public function markMetaDirty(array $dirtyMeta, int $index, string $alias, mixed $value): array
    {
        if (!isset($dirtyMeta[$index])) {
            $dirtyMeta[$index] = [];
        }

        $dirtyMeta[$index][$alias] = $value;
        return $dirtyMeta;
    }

    public function hasDirtyMeta(array $dirtyMeta, int $index): bool
    {
        return !empty($dirtyMeta[$index]);
    }

    public function getDirtyMeta(array $dirtyMeta, int $index): array
    {
        return $dirtyMeta[$index] ?? [];
    }

    public function clearDirtyMeta(array $dirtyMeta, int $index): array
    {
        unset($dirtyMeta[$index]);
        return $dirtyMeta;
    }

    public function saveMeta(
        ?RuleBuilder $ruleBuilder,
        mixed $db,
        array $dirtyMeta,
        mixed $entityId,
        int $index
    ): array {
        if ($ruleBuilder === null || $db === null) {
            return [true, $dirtyMeta];
        }

        if (!$this->hasDirtyMeta($dirtyMeta, $index)) {
            return [true, $dirtyMeta];
        }

        $dirty = $this->getDirtyMeta($dirtyMeta, $index);
        if ($dirty === []) {
            return [true, $dirtyMeta];
        }

        $allMeta = $ruleBuilder->getAllHasMeta();
        if ($allMeta === []) {
            return [true, $dirtyMeta];
        }

        $byModel = [];
        foreach ($dirty as $alias => $value) {
            foreach ($allMeta as $config) {
                if (($config['alias'] ?? null) !== $alias) {
                    continue;
                }

                $modelClass = $config['related_model'] ?? null;
                if (!is_string($modelClass) || $modelClass === '') {
                    continue;
                }

                $byModel[$modelClass] ??= [];
                $byModel[$modelClass][] = [
                    'config' => $config,
                    'value' => $value,
                ];
                break;
            }
        }

        foreach ($byModel as $modelClass => $items) {
            if (!$this->saveMetaBatch($db, $modelClass, $items, $entityId)) {
                return [false, $dirtyMeta];
            }
        }

        return [true, $this->clearDirtyMeta($dirtyMeta, $index)];
    }

    public function deleteMeta(?RuleBuilder $ruleBuilder, mixed $db, mixed $entityId): bool
    {
        if ($ruleBuilder === null || $db === null) {
            return true;
        }

        $allMeta = $ruleBuilder->getAllHasMeta();
        if ($allMeta === []) {
            return true;
        }

        $byModel = [];
        foreach ($allMeta as $config) {
            $modelClass = $config['related_model'] ?? null;
            if (!is_string($modelClass) || $modelClass === '') {
                continue;
            }

            $byModel[$modelClass] ??= $config;
        }

        foreach ($byModel as $modelClass => $config) {
            $metaModel = new $modelClass();
            $metaTable = $metaModel->getRuleBuilder()->getTable();
            $foreignKey = $config['foreign_key'];

            $db->query(
                'DELETE FROM ' . $db->qn($metaTable) .
                ' WHERE ' . $db->qn($foreignKey) . ' = ?',
                [$entityId]
            );
        }

        return true;
    }

    private function validateRelationshipArray(string $key, string $relationshipType, array $value): ?string
    {
        if ($relationshipType === 'hasMany') {
            foreach ($value as $index => $childData) {
                if (!is_array($childData)) {
                    continue;
                }

                foreach ($childData as $field => $fieldValue) {
                    if (is_array($fieldValue) || is_object($fieldValue)) {
                        return "Relationship '{$key}' child record {$index} field '{$field}' cannot contain nested arrays or objects";
                    }
                }
            }

            return null;
        }

        foreach ($value as $field => $fieldValue) {
            if (is_array($fieldValue) || is_object($fieldValue)) {
                return "Relationship '{$key}' field '{$field}' cannot contain nested arrays or objects";
            }
        }

        return null;
    }

    private function saveMetaBatch(mixed $db, string $modelClass, array $items, mixed $entityId): bool
    {
        if ($items === []) {
            return true;
        }

        $firstConfig = $items[0]['config'];
        $foreignKey = $firstConfig['foreign_key'];
        $metaKeyColumn = $firstConfig['meta_key_column'];
        $metaValueColumn = $firstConfig['meta_value_column'];

        $metaModel = new $modelClass();
        $metaTable = $metaModel->getRuleBuilder()->getTable();
        $metaPk = (string) ($metaModel->getPrimaryKey() ?? '');
        if ($metaPk === '' || $metaTable === null || $metaTable === '') {
            return false;
        }

        foreach ($items as $item) {
            $config = $item['config'];
            $value = $item['value'];
            $metaKey = $config['meta_key_value'];

            $existing = $db->getRow(
                'SELECT ' . $db->qn($metaPk) . ' FROM ' . $db->qn($metaTable) .
                ' WHERE ' . $db->qn($foreignKey) . ' = ? AND ' . $db->qn($metaKeyColumn) . ' = ?',
                [$entityId, $metaKey]
            );

            if ($value === null || $value === '') {
                if ($existing) {
                    $db->query(
                        'DELETE FROM ' . $db->qn($metaTable) .
                        ' WHERE ' . $db->qn($foreignKey) . ' = ? AND ' . $db->qn($metaKeyColumn) . ' = ?',
                        [$entityId, $metaKey]
                    );
                }
                continue;
            }

            if ($existing) {
                $db->query(
                    'UPDATE ' . $db->qn($metaTable) .
                    ' SET ' . $db->qn($metaValueColumn) . ' = ? ' .
                    ' WHERE ' . $db->qn($foreignKey) . ' = ? AND ' . $db->qn($metaKeyColumn) . ' = ?',
                    [$value, $entityId, $metaKey]
                );
                continue;
            }

            $db->query(
                'INSERT INTO ' . $db->qn($metaTable) .
                ' (' . $db->qn($foreignKey) . ', ' . $db->qn($metaKeyColumn) . ', ' . $db->qn($metaValueColumn) . ')' .
                ' VALUES (?, ?, ?)',
                [$entityId, $metaKey, $value]
            );
        }

        return true;
    }
}
