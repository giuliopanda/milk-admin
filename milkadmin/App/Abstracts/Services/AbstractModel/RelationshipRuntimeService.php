<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

class RelationshipRuntimeService
{
    public function __construct(
        private ?RelationshipDefinitionService $definitionService = null,
        private ?RelationshipLoaderService $loaderService = null,
        private ?RelationshipFormatterService $formatterService = null
    ) {
        $this->definitionService ??= new RelationshipDefinitionService();
        $this->loaderService ??= new RelationshipLoaderService();
        $this->formatterService ??= new RelationshipFormatterService();
    }

    public function getRelatedModel(
        AbstractModel $model,
        string $alias,
        ?array $records,
        int $currentIndex,
        bool $metaLoaded
    ): array {
        if ($this->definitionService->hasMetaRelationship($model->getRuleBuilder(), $alias)) {
            if (!isset($records[$currentIndex])) {
                return [$records, $metaLoaded, null];
            }

            if (!array_key_exists($alias, $records[$currentIndex]) && !$metaLoaded) {
                $records = $this->loaderService->loadAllMeta($model, $records);
                $metaLoaded = true;
            }

            return [$records, $metaLoaded, $records[$currentIndex][$alias] ?? null];
        }

        $relationship = $this->definitionService->getRelationship($model->getRuleBuilder(), $alias);
        if ($relationship === null || !isset($records[$currentIndex])) {
            return [$records, $metaLoaded, null];
        }

        if (!isset($records[$currentIndex][$alias])) {
            $records = $this->loaderService->loadMissingRelationships($model, $alias, $relationship, $records);
        }

        return [$records, $metaLoaded, $records[$currentIndex][$alias] ?? null];
    }

    public function clearRelationshipCache(AbstractModel $model, ?array $records, ?string $alias = null): array
    {
        if ($records === null) {
            return [null, false];
        }

        foreach ($records as $index => $_record) {
            if ($alias === null) {
                foreach ($model->getRules() as $fieldName => $rule) {
                    if (isset($rule['relationship']['alias'])) {
                        unset($records[$index][$rule['relationship']['alias']]);
                    }

                    if (($rule['hasMeta'] ?? false) === true) {
                        unset($records[$index][$fieldName]);
                    }
                }

                continue;
            }

            unset($records[$index][$alias]);
        }

        return [$records, false];
    }

    public function with(AbstractModel $model, ?array $records, string|array|null $relations = null): array
    {
        $definedRelationships = $this->definitionService->getRelationshipAliases($model->getRuleBuilder());

        if ($relations === null) {
            $relationsToLoad = $definedRelationships;
        } elseif (is_string($relations)) {
            $relationsToLoad = [$relations];
        } else {
            $relationsToLoad = $relations;
        }

        $includeRelationships = [];

        foreach ($relationsToLoad as $alias) {
            if (!in_array($alias, $definedRelationships, true)) {
                continue;
            }

            $includeRelationships[] = $alias;
            $relationship = $this->definitionService->getRelationship($model->getRuleBuilder(), $alias);
            if ($relationship !== null) {
                $records = $this->loaderService->loadMissingRelationships($model, $alias, $relationship, $records);
            }
        }

        return [$records, $includeRelationships];
    }

    public function withMeta(AbstractModel $model, ?array $records, bool $metaLoaded): array
    {
        if ($metaLoaded) {
            return [$records, true];
        }

        return [$this->loaderService->loadAllMeta($model, $records), true];
    }

    public function getIncludedRelationshipsData(
        AbstractModel $model,
        string $outputMode,
        ?array $records,
        int $currentIndex,
        array $includeRelationships
    ): array {
        return $this->formatterService->getIncludedRelationshipsData(
            $model,
            $outputMode,
            $records,
            $currentIndex,
            $includeRelationships
        );
    }

    public function convertRelatedArrayData(AbstractModel $model, ?array $dataArray, string $outputMode, string $alias): ?object
    {
        return $this->formatterService->convertRelatedArrayData($model, $dataArray, $outputMode, $alias);
    }

    public function applyRelationshipFormatters(AbstractModel $model, array $data, string $alias, string $outputMode): array
    {
        return $this->formatterService->applyRelationshipFormatters($model, $data, $alias, $outputMode);
    }

    public function applyRelationshipFormattersToModel(AbstractModel $model, mixed $relatedData, string $alias, string $outputMode): mixed
    {
        return $this->formatterService->applyRelationshipFormattersToModel($model, $relatedData, $alias, $outputMode);
    }

    public function arrayToModelInstance(
        AbstractModel $model,
        ?array $dataArray,
        array $relationship,
        string $alias,
        string $outputMode
    ): mixed {
        return $this->formatterService->arrayToModelInstance($model, $dataArray, $relationship, $alias, $outputMode);
    }
}
