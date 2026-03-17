<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

class RelationshipLoaderService
{
    public function loadMissingRelationships(AbstractModel $model, string $alias, array $relationship, ?array $records): ?array
    {
        if ($records === null || $records === []) {
            return $records;
        }

        $isHasRelationship = in_array($relationship['type'] ?? null, ['hasOne', 'hasMany'], true);
        $localKey = $isHasRelationship ? ($relationship['local_key'] ?? null) : ($relationship['foreign_key'] ?? null);
        $foreignKey = $isHasRelationship ? ($relationship['foreign_key'] ?? null) : ($relationship['related_key'] ?? null);

        if (!is_string($localKey) || $localKey === '' || !is_string($foreignKey) || $foreignKey === '') {
            return $records;
        }

        $keysToLoad = [];
        $recordsNeedingLoad = [];

        foreach ($records as $index => $record) {
            if (isset($record[$alias])) {
                continue;
            }

            $keyValue = $record[$localKey] ?? null;
            if ($keyValue === null) {
                continue;
            }

            $keysToLoad[$keyValue] ??= [];
            $keysToLoad[$keyValue][] = $index;
            $recordsNeedingLoad[] = $index;
        }

        if ($keysToLoad === []) {
            return $records;
        }

        $relatedClass = $relationship['related_model'] ?? null;
        if (!is_string($relatedClass) || $relatedClass === '' || !class_exists($relatedClass)) {
            return $records;
        }

        $relatedModel = new $relatedClass();
        $query = $relatedModel->query()->whereIn($foreignKey, array_keys($keysToLoad));

        $whereConfig = $relationship['where'] ?? null;
        if (is_array($whereConfig) && isset($whereConfig['condition'])) {
            $query->where($whereConfig['condition'], $whereConfig['params'] ?? []);
        }

        $allResultsModel = $query->getResults();
        if (!$allResultsModel instanceof AbstractModel) {
            return $records;
        }

        foreach ($recordsNeedingLoad as $index) {
            $keyValue = $records[$index][$localKey] ?? null;
            if ($keyValue === null) {
                continue;
            }

            $filteredModel = $allResultsModel->filterByField($foreignKey, [$keyValue]);

            if (($relationship['type'] ?? null) === 'hasMany') {
                $records[$index][$alias] = $filteredModel;
                continue;
            }

            if ($filteredModel->count() > 0) {
                $records[$index][$alias] = $filteredModel->first();
            } else {
                $records[$index][$alias] = null;
            }
        }

        return $records;
    }

    public function loadAllMeta(AbstractModel $model, ?array $records): ?array
    {
        if ($records === null || $records === []) {
            return $records;
        }

        $allMetaConfigs = $model->getRuleBuilder()->getAllHasMeta();
        if (empty($allMetaConfigs)) {
            return $records;
        }

        $groupedByModel = [];
        foreach ($allMetaConfigs as $metaConfig) {
            $modelClass = $metaConfig['related_model'] ?? null;
            if (!is_string($modelClass) || $modelClass === '') {
                continue;
            }

            $groupedByModel[$modelClass] ??= [];
            $groupedByModel[$modelClass][] = $metaConfig;
        }

        $firstConfig = reset($allMetaConfigs);
        $localKey = $firstConfig['local_key'] ?? null;
        if (!is_string($localKey) || $localKey === '') {
            return $records;
        }

        $entityIds = [];
        foreach ($records as $record) {
            $entityId = $record[$localKey] ?? null;
            if ($entityId !== null) {
                $entityIds[$entityId] = true;
            }
        }

        if ($entityIds === []) {
            return $records;
        }

        $entityIds = array_keys($entityIds);
        foreach ($groupedByModel as $modelClass => $metaConfigs) {
            $records = $this->loadMetaBatch($modelClass, $metaConfigs, $entityIds, $localKey, $records);
        }

        return $records;
    }

    private function loadMetaBatch(string $modelClass, array $metaConfigs, array $entityIds, string $localKey, array $records): array
    {
        $firstConfig = reset($metaConfigs);
        $foreignKey = $firstConfig['foreign_key'] ?? null;
        $metaKeyColumn = $firstConfig['meta_key_column'] ?? null;
        $metaValueColumn = $firstConfig['meta_value_column'] ?? null;

        if (!is_string($foreignKey) || $foreignKey === '' || !is_string($metaKeyColumn) || $metaKeyColumn === '' || !is_string($metaValueColumn) || $metaValueColumn === '') {
            return $records;
        }

        $metaKeys = [];
        foreach ($metaConfigs as $config) {
            if (isset($config['meta_key_value'])) {
                $metaKeys[] = $config['meta_key_value'];
            }
        }

        if ($metaKeys === []) {
            return $records;
        }

        $metaModel = new $modelClass();
        $query = $metaModel->query()
            ->whereIn($foreignKey, $entityIds)
            ->whereIn($metaKeyColumn, $metaKeys);

        foreach ($metaConfigs as $config) {
            if (isset($config['where']['condition'])) {
                $query->where($config['where']['condition'], $config['where']['params'] ?? []);
            }
        }

        $results = $query->getResults();
        $metaLookup = [];

        if ($results instanceof AbstractModel && $results->count() > 0) {
            foreach ($results as $row) {
                $entityId = $row->$foreignKey;
                $key = $row->$metaKeyColumn;
                $value = $row->$metaValueColumn;

                $metaLookup[$entityId] ??= [];
                $metaLookup[$entityId][$key] = $value;
            }
        }

        foreach ($records as $index => $record) {
            $entityId = $record[$localKey] ?? null;
            if ($entityId === null) {
                continue;
            }

            foreach ($metaConfigs as $config) {
                $alias = $config['alias'] ?? null;
                $metaKey = $config['meta_key_value'] ?? null;
                if (!is_string($alias) || $alias === '' || $metaKey === null) {
                    continue;
                }

                $records[$index][$alias] = $metaLookup[$entityId][$metaKey] ?? null;
            }
        }

        return $records;
    }
}
