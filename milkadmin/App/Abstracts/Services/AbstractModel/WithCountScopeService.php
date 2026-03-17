<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;
use App\Database\Query;

!defined('MILK_DIR') && die();

class WithCountScopeService
{
    private RelationshipDefinitionService $definitionService;

    public function __construct(?RelationshipDefinitionService $definitionService = null)
    {
        $this->definitionService = $definitionService ?? new RelationshipDefinitionService();
    }

    public function getScopes(AbstractModel $model): array
    {
        return $this->definitionService->getWithCountScopes($model->getRuleBuilder());
    }

    public function hasEnabledScopes(array $defaultQueries, array $disabledScopes): bool
    {
        foreach ($defaultQueries as $scopeName => $callback) {
            if (str_starts_with($scopeName, 'withCount:') && !in_array($scopeName, $disabledScopes, true)) {
                return true;
            }
        }

        return false;
    }

    public function applyScope(Query $query, AbstractModel $model, array $config): void
    {
        $alias = $config['alias'] ?? null;
        $localKey = $config['local_key'] ?? null;
        $foreignKey = $config['foreign_key'] ?? null;
        $relatedModelClass = $config['related_model'] ?? null;
        $whereConfig = $config['where'] ?? null;

        if (
            !is_string($alias) || $alias === '' ||
            !is_string($localKey) || $localKey === '' ||
            !is_string($foreignKey) || $foreignKey === '' ||
            !is_string($relatedModelClass) || $relatedModelClass === '' || !class_exists($relatedModelClass)
        ) {
            return;
        }

        $relatedModel = new $relatedModelClass();
        $relatedTable = $relatedModel->getRuleBuilder()->getTable();

        $subquery = $relatedModel->query();
        $correlationCondition = sprintf(
            '%s.%s = %s.%s',
            $model->qn($relatedTable),
            $model->qn($foreignKey),
            $model->qn($model->getTable()),
            $model->qn($localKey)
        );
        $subquery->where($correlationCondition);

        if (is_array($whereConfig) && isset($whereConfig['condition'])) {
            $subquery->where($whereConfig['condition'], $whereConfig['params'] ?? []);
        }

        $subquery->clean('select')->select('COUNT(*)');
        $countSelect = sprintf('(%s) AS %s', $subquery->toSql(), $model->qn($alias));
        $query->select($countSelect);
    }
}
