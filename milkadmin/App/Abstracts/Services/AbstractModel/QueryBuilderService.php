<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;
use App\Database\Query;

!defined('MILK_DIR') && die();

class QueryBuilderService
{
    public function query(
        AbstractModel $model,
        ?Query $currentQuery,
        array $includeRelationships,
        callable $applyScopes
    ): ?Query {
        if ($currentQuery !== null) {
            if ($currentQuery->getLastExecutedQuery() !== null) {
                $currentQuery = null;
            } else {
                return $currentQuery;
            }
        }

        $modelClass = $model::class;
        $newModel = new $modelClass();

        if ($includeRelationships !== []) {
            $newModel->with($includeRelationships);
        }

        $query = new Query($model->getTable(), $model->getDb(), $newModel);
        return $applyScopes($query);
    }

    public function where(AbstractModel $model, string $condition, array $params = []): Query
    {
        $query = $model->query();
        $query->where($condition, $params);
        return $query;
    }

    public function whereIn(AbstractModel $model, string $field, array $values, string $operator = 'AND'): Query
    {
        $query = $model->query();
        $query->whereIn($field, $values, $operator);
        return $query;
    }

    public function whereHas(AbstractModel $model, string $relationAlias, string $condition, array $params = []): Query
    {
        $query = $model->query();
        $query->whereHas($relationAlias, $condition, $params);
        return $query;
    }

    public function order(AbstractModel $model, string|array $field = '', string $dir = 'asc'): Query
    {
        $query = $model->query();
        $query->order($field, $dir);
        return $query;
    }

    public function select(AbstractModel $model, array|string $fields): Query
    {
        $query = $model->query();
        if (is_array($fields)) {
            $fields = implode(', ', $fields);
        }
        $query->select($fields);
        return $query;
    }

    public function limit(AbstractModel $model, int $start, int $limit = -1): Query
    {
        if ($limit === -1) {
            $limit = $start;
            $start = 0;
        }

        $query = $model->query();
        $query->limit($start, $limit);
        return $query;
    }

    public function getFirst(AbstractModel $model, string $orderField = '', string $orderDir = 'asc'): ?AbstractModel
    {
        $query = $model->query();
        if ($orderField !== '') {
            $query->order($orderField, $orderDir);
        }

        return $query->getRow();
    }

    public function getAll(AbstractModel $model, string $orderField = '', string $orderDir = 'asc'): array|AbstractModel
    {
        if ($model->getDb() === null) {
            return [];
        }

        $query = $model->newQuery();
        if ($orderField !== '') {
            $query->order($orderField, $orderDir);
        }

        $query->clean('limit');
        return $query->getResults();
    }

    public function total(AbstractModel $model): int
    {
        if ($model->getDb() === null) {
            return 0;
        }

        $query = $model->query();
        return (int) $query->clean('select')->select('COUNT(*) as total')->getVar();
    }

    public function setQueryParams(AbstractModel $model, mixed $request, callable $addFilters): Query
    {
        $query = $model->query();
        $query->limit($request['limit_start'] ?? 0, $request['limit'] ?? 10);

        if (($request['order_field'] ?? null) && ($request['order_dir'] ?? null)) {
            $query->order($request['order_field'], $request['order_dir']);
        }

        $addFilters($request['filters'] ?? '', $query);
        return $query;
    }

    public function filterSearch(AbstractModel $model, mixed $search, Query $query): Query
    {
        foreach ($model->getSearchableTableStructure() as $field => $_columnInfo) {
            $query->where('`' . $field . '` LIKE ? ', ['%' . $search . '%'], 'OR');
        }

        return $query;
    }
}
