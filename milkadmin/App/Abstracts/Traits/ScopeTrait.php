<?php
namespace App\Abstracts\Traits;

use App\Database\Query;

/**
 * ScopeTrait
 *
 * Provides methods to manage query scopes (default and named) for AbstractModel.
 *
 * Query scopes allow you to define reusable query constraints that can be
 * automatically applied to all queries (default scopes) or applied on-demand
 * (named scopes).
 *
 * Example usage:
 * ```php
 * class OrdersModel extends AbstractModel {
 *     #[DefaultQuery]
 *     protected function onlyActive($query) {
 *         return $query->where('status = ?', ['active']);
 *     }
 *
 *     #[Query('recent')]
 *     protected function scopeRecent($query) {
 *         return $query->where('created_at > ?', [date('Y-m-d', strtotime('-30 days'))]);
 *     }
 * }
 *
 * // Usage:
 * $orders = $model->getAll(); // Applies 'onlyActive' default scope
 * $orders = $model->withoutGlobalScope('onlyActive')->getAll(); // Disables default scope
 * $orders = $model->withQuery('recent')->getAll(); // Applies named scope to current query only
 * ```
 */
trait ScopeTrait
{
    /**
     * Disable one or more global scopes for all subsequent queries
     * (persistent until re-enabled)
     *
     * @param string|array $scopes Scope name(s) to disable
     * @return static
     */
    public function withoutGlobalScope(string|array $scopes): static
    {
        $scopes = is_array($scopes) ? $scopes : [$scopes];
        $this->disabled_scopes = array_merge($this->disabled_scopes, $scopes);
        return $this;
    }

    /**
     * Disable all global scopes for all subsequent queries
     * (persistent until re-enabled)
     *
     * @return static
     */
    public function withoutGlobalScopes(): static
    {
        $this->disabled_scopes = array_keys($this->default_queries);
        return $this;
    }

    /**
     * Re-enable a previously disabled global scope
     *
     * @param string $scope Scope name to re-enable
     * @return static
     */
    public function enableGlobalScope(string $scope): static
    {
        $this->disabled_scopes = array_diff($this->disabled_scopes, [$scope]);
        return $this;
    }

    /**
     * Apply a named query scope to the NEXT query only (temporary)
     *
     * Named queries are applied only once and then automatically cleared
     * after the query executes.
     *
     * @param string $query_name Name of the query scope to apply
     * @return static
     */
    public function withQuery(string $query_name): static
    {
        if (!isset($this->named_queries[$query_name])) {
            throw new \InvalidArgumentException("Named query '{$query_name}' not found in " . static::class);
        }

        $this->active_named_queries[] = $query_name;
        return $this;
    }

    /**
     * Apply all registered query scopes to a Query object
     *
     * This method is called automatically by QueryBuilderTrait::query()
     * and applies:
     * 1. Default queries (unless disabled)
     * 2. Active named queries (for current query only)
     * 3. withCount subqueries (unless disabled)
     *
     * @param Query $query The query object to apply scopes to
     * @return Query The modified query object
     */
    protected function applyQueryScopes(Query $query): Query
    {
        if ($this->getWithCountScopeService()->hasEnabledScopes($this->default_queries, $this->disabled_scopes) && !$query->hasSelect()) {
            // No explicit SELECT, default would be *, change to table.*
            $query->select([$this->table . '.*']);
        }

        // Apply default queries (if not disabled)
        foreach ($this->default_queries as $scope_name => $callback) {
            if (in_array($scope_name, $this->disabled_scopes)) {
                continue;
            }

            // Handle withCount scopes specially
            if (str_starts_with($scope_name, 'withCount:')) {
                $this->applyWithCountScope($query, $callback);
            } else {
                // Regular default query scope
                $query = call_user_func($callback, $query);
            }
        }

        // Apply active named queries (temporary, only for this query)
        foreach ($this->active_named_queries as $query_name) {
            if (isset($this->named_queries[$query_name])) {
                $query = call_user_func($this->named_queries[$query_name], $query);
            }
        }

        // Clear active named queries after application (they're temporary)
        $this->active_named_queries = [];

        return $query;
    }

    /**
     * Apply a withCount scope by adding a COUNT subquery to the SELECT clause
     *
     * @param Query $query The query object to modify
     * @param array $config The withCount configuration
     * @return void
     */
    protected function applyWithCountScope(Query $query, array $config): void
    {
        $this->getWithCountScopeService()->applyScope($query, $this, $config);
    }

    /**
     * Get all registered default query scopes
     *
     * @return array
     */
    public function getDefaultQueries(): array
    {
        return array_keys($this->default_queries);
    }

    /**
     * Get all registered named query scopes
     *
     * @return array
     */
    public function getNamedQueries(): array
    {
        return array_keys($this->named_queries);
    }

    /**
     * Get all currently disabled scopes
     *
     * @return array
     */
    public function getDisabledScopes(): array
    {
        return $this->disabled_scopes;
    }

    /**
     * Check if a default query scope exists
     *
     * @param string $scope_name
     * @return bool
     */
    public function hasDefaultQuery(string $scope_name): bool
    {
        return isset($this->default_queries[$scope_name]);
    }

    /**
     * Check if a named query scope exists
     *
     * @param string $query_name
     * @return bool
     */
    public function hasNamedQuery(string $query_name): bool
    {
        return isset($this->named_queries[$query_name]);
    }
}
