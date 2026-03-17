<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

class ExpressionParameterNormalizerService
{
    private RelationshipDefinitionService $definitionService;

    public function __construct(?RelationshipDefinitionService $definitionService = null)
    {
        $this->definitionService = $definitionService ?? new RelationshipDefinitionService();
    }

    public function normalize(AbstractModel $model, array $data): array
    {
        $normalized = [];

        foreach ($data as $field => $value) {
            $relationship = is_string($field)
                ? $this->definitionService->getRelationship($model->getRuleBuilder(), $field)
                : null;

            $normalized[$field] = $this->normalizeValue($model, $value, $relationship, 0);
        }

        return $normalized;
    }

    private function normalizeValue(AbstractModel $owner, mixed $value, ?array $relationship, int $depth): mixed
    {
        if ($value instanceof AbstractModel) {
            return $this->normalizeModel(
                $value,
                ($relationship['type'] ?? null) === 'hasMany',
                $depth + 1
            );
        }

        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeValue($owner, $item, null, $depth + 1);
        }

        return $normalized;
    }

    private function normalizeModel(AbstractModel $model, bool $asCollection, int $depth): mixed
    {
        if ($depth >= 5) {
            return $model->getRawData('array', $asCollection);
        }

        if ($asCollection) {
            $items = [];
            $originalIndex = $model->getCurrentIndex();
            $count = $model->count();

            for ($i = 0; $i < $count; $i++) {
                $current = $model[$i];
                if ($current instanceof AbstractModel) {
                    $items[] = $this->normalizeModel($current, false, $depth);
                }
            }

            if ($count > 0) {
                $model->moveTo($originalIndex);
            }

            return $items;
        }

        $normalized = $model->getRawData('array', false);
        foreach ($this->definitionService->getRelationshipAliases($model->getRuleBuilder()) as $alias) {
            $relationship = $this->definitionService->getRelationship($model->getRuleBuilder(), $alias);
            if ($relationship === null) {
                continue;
            }

            $normalized[$alias] = $this->normalizeValue($model, $model->$alias, $relationship, $depth);
        }

        foreach ($normalized as $key => $item) {
            $normalized[$key] = $this->normalizeValue($model, $item, null, $depth + 1);
        }

        return $normalized;
    }
}
