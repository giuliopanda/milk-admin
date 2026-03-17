<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;
use App\Database\Query;

!defined('MILK_DIR') && die();

class ModelScopeService
{
    public function __construct(
        private ?WithCountScopeService $withCountScopeService = null
    ) {
        $this->withCountScopeService ??= new WithCountScopeService();
    }

    public function withoutGlobalScope(array $disabledScopes, string|array $scopes): array
    {
        $scopes = is_array($scopes) ? $scopes : [$scopes];
        return array_values(array_unique(array_merge($disabledScopes, $scopes)));
    }

    public function withoutGlobalScopes(array $defaultQueries): array
    {
        return array_keys($defaultQueries);
    }

    public function enableGlobalScope(array $disabledScopes, string $scope): array
    {
        return array_values(array_diff($disabledScopes, [$scope]));
    }

    public function withQuery(array $namedQueries, array $activeNamedQueries, string $queryName, string $modelClass): array
    {
        if (!isset($namedQueries[$queryName])) {
            throw new \InvalidArgumentException("Named query '{$queryName}' not found in " . $modelClass);
        }

        $activeNamedQueries[] = $queryName;
        return $activeNamedQueries;
    }

    public function applyQueryScopes(
        Query $query,
        AbstractModel $model,
        array $defaultQueries,
        array $disabledScopes,
        array $activeNamedQueries,
        array $namedQueries
    ): array {
        if ($this->withCountScopeService->hasEnabledScopes($defaultQueries, $disabledScopes) && !$query->hasSelect()) {
            $query->select([$model->getTable() . '.*']);
        }

        foreach ($defaultQueries as $scopeName => $callback) {
            if (in_array($scopeName, $disabledScopes, true)) {
                continue;
            }

            if (str_starts_with($scopeName, 'withCount:')) {
                $this->withCountScopeService->applyScope($query, $model, $callback);
                continue;
            }

            $query = call_user_func($callback, $query);
        }

        foreach ($activeNamedQueries as $queryName) {
            if (!isset($namedQueries[$queryName])) {
                continue;
            }

            $query = call_user_func($namedQueries[$queryName], $query);
        }

        return [$query, []];
    }
}
