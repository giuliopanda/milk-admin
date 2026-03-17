<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

class RelationshipFormatterService
{
    public function __construct(private ?RelationshipDefinitionService $definitionService = null)
    {
        $this->definitionService ??= new RelationshipDefinitionService();
    }

    public function getIncludedRelationshipsData(
        AbstractModel $model,
        string $outputMode,
        ?array $records,
        int $currentIndex,
        array $includeRelationships
    ): array {
        $result = [];

        if ($includeRelationships === [] || !isset($records[$currentIndex])) {
            return $result;
        }

        $currentRecord = $records[$currentIndex];

        foreach ($includeRelationships as $alias) {
            if (!isset($currentRecord[$alias])) {
                $result[$alias] = null;
                continue;
            }

            $relatedData = $currentRecord[$alias];

            if ($outputMode === 'raw') {
                $result[$alias] = $relatedData;
                continue;
            }

            $relationship = $this->definitionService->getRelationship($model->getRuleBuilder(), $alias);
            if ($relationship === null) {
                $result[$alias] = null;
                continue;
            }

            if (($relationship['type'] ?? null) === 'hasMany') {
                $formattedArray = [];
                if (is_array($relatedData)) {
                    foreach ($relatedData as $itemArray) {
                        $formattedArray[] = $this->convertRelatedArrayData($model, $itemArray, $outputMode, $alias);
                    }
                }
                $result[$alias] = $formattedArray;
                continue;
            }

            $result[$alias] = $this->convertRelatedArrayData($model, $relatedData, $outputMode, $alias);
        }

        return $result;
    }

    public function convertRelatedArrayData(AbstractModel $model, ?array $dataArray, string $outputMode, string $alias): ?object
    {
        if ($dataArray === null) {
            return null;
        }

        return (object) $this->applyRelationshipFormatters($model, $dataArray, $alias, $outputMode);
    }

    public function applyRelationshipFormatters(AbstractModel $model, array $data, string $alias, string $outputMode): array
    {
        $handlerType = match ($outputMode) {
            'formatted' => 'get_formatted',
            'sql' => 'get_sql',
            'raw' => 'get_raw',
            default => 'get_formatted',
        };

        $handlers = $model->getRelationshipHandlers($alias, $handlerType);

        foreach ($handlers as $fieldName => $handler) {
            if (!array_key_exists($fieldName, $data) || !is_callable($handler)) {
                continue;
            }

            $tempObject = (object) [$alias => (object) $data];
            $data[$fieldName] = $handler($tempObject);
        }

        return $data;
    }

    public function applyRelationshipFormattersToModel(AbstractModel $model, mixed $relatedData, string $alias, string $outputMode): mixed
    {
        if ($relatedData === null) {
            return null;
        }

        $relationship = $this->definitionService->getRelationship($model->getRuleBuilder(), $alias);
        if ($relationship === null) {
            return null;
        }

        if (($relationship['type'] ?? null) === 'hasMany') {
            $result = [];
            if (is_array($relatedData)) {
                foreach ($relatedData as $itemArray) {
                    $relatedModel = $this->arrayToModelInstance($model, $itemArray, $relationship, $alias, $outputMode);
                    if ($relatedModel !== null) {
                        $result[] = $relatedModel;
                    }
                }
            }
            return $result;
        }

        return $this->arrayToModelInstance($model, $relatedData, $relationship, $alias, $outputMode);
    }

    public function arrayToModelInstance(
        AbstractModel $model,
        ?array $dataArray,
        array $relationship,
        string $alias,
        string $outputMode
    ): mixed {
        if ($dataArray === null) {
            return null;
        }

        if ($outputMode !== 'raw') {
            $dataArray = $this->applyRelationshipFormatters($model, $dataArray, $alias, $outputMode);
        }

        $relatedClass = $relationship['related_model'] ?? null;
        if (!is_string($relatedClass) || $relatedClass === '' || !class_exists($relatedClass)) {
            return null;
        }

        $relatedModel = new $relatedClass();
        $relationshipData = [];
        $fieldData = [];
        $modelRelationships = [];

        foreach ($relatedModel->getRules() as $rule) {
            if (isset($rule['relationship']['alias'])) {
                $modelRelationships[] = $rule['relationship']['alias'];
            }
        }

        foreach ($dataArray as $field => $value) {
            if (in_array($field, $modelRelationships, true)) {
                $relationshipData[$field] = $value;
            } else {
                $fieldData[$field] = $value;
            }
        }

        foreach ($fieldData as $field => $value) {
            try {
                $relatedModel->$field = $value;
            } catch (\Throwable) {
                continue;
            }
        }

        if ($relationshipData !== []) {
            $reflection = new \ReflectionClass($relatedModel);
            $recordsProperty = $reflection->getProperty('records_objects');
            $currentRecords = $recordsProperty->getValue($relatedModel);

            if ($currentRecords === null || !isset($currentRecords[0])) {
                $currentRecords = [0 => []];
            }

            foreach ($relationshipData as $relationshipAlias => $relationshipValue) {
                $currentRecords[0][$relationshipAlias] = $relationshipValue;
            }

            $recordsProperty->setValue($relatedModel, $currentRecords);

            $currentIndexProperty = $reflection->getProperty('current_index');
            $currentIndexProperty->setValue($relatedModel, 0);
        }

        return $relatedModel;
    }
}
