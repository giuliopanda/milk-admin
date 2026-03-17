<?php

namespace Builders\Traits\GetDataBuilder;

!defined('MILK_DIR') && die();

/**
 * QueryMethodsTrait - Query building methods
 */
trait QueryMethodsTrait
{
    /**
     * Select specific columns
     */
    public function select(array|string $columns): static
    {
        $this->columns->resetCurrentField();
        $this->context->getQuery()->select($columns);
        return $this;
    }

    /**
     * Add WHERE condition
     */
    public function where(string $condition, array $params = [], string $operator = 'AND'): static
    {
        $this->columns->resetCurrentField();
        $this->context->getQuery()->where($condition, $params, $operator);
        return $this;
    }

    /**
     * Add WHERE IN condition
     */
    public function whereIn(string $field, array $values): static
    {
        $this->columns->resetCurrentField();

        $db = $this->context->getDb();
        $placeholders = str_repeat('?,', count($values) - 1) . '?';

        $this->context->getQuery()->where(
            $db->qn($field) . " IN ({$placeholders})",
            $values
        );

        return $this;
    }

    /**
     * Add WHERE LIKE condition
     */
    public function whereLike(string $field, string $value, string $position = 'both'): static
    {
        $this->columns->resetCurrentField();

        $db = $this->context->getDb();
        $searchValue = match ($position) {
            'start' => $value . '%',
            'end' => '%' . $value,
            default => '%' . $value . '%'
        };

        $this->context->getQuery()->where(
            $db->qn($field) . ' LIKE ?',
            [$searchValue]
        );

        return $this;
    }

    /**
     * Add WHERE BETWEEN condition
     */
    public function whereBetween(string $field, $min, $max): static
    {
        $this->columns->resetCurrentField();

        $db = $this->context->getDb();

        $this->context->getQuery()->where(
            $db->qn($field) . ' BETWEEN ? AND ?',
            [$min, $max]
        );

        return $this;
    }

    /**
     * Add JOIN clause
     */
    public function join(string $table, string $condition, string $type = 'INNER'): static
    {
        $this->columns->resetCurrentField();
        $this->context->getQuery()->from("{$type} JOIN {$table} ON {$condition}");
        return $this;
    }

    /**
     * Add LEFT JOIN clause
     */
    public function leftJoin(string $table, string $condition): static
    {
        return $this->join($table, $condition, 'LEFT');
    }

    /**
     * Add RIGHT JOIN clause
     */
    public function rightJoin(string $table, string $condition): static
    {
        return $this->join($table, $condition, 'RIGHT');
    }

    /**
     * Add GROUP BY clause
     */
    public function groupBy(string $field): static
    {
        $this->columns->resetCurrentField();
        $this->context->getQuery()->group($field);
        return $this;
    }

    /**
     * Add HAVING clause
     */
    public function having(string $condition, array $params = []): static
    {
        $this->columns->resetCurrentField();
        $this->context->getQuery()->having($condition, $params);
        return $this;
    }

    /**
     * Set ORDER BY
     */
    public function orderBy(string $field, string $direction = 'ASC'): static
    {
        $this->columns->resetCurrentField();
        $this->context->setOrder($field, $direction);

        $request = $this->context->getRequest();
        $hasRequestOrder = isset($request['order_field']) && $request['order_field'] !== '';
        if (!$hasRequestOrder) {
            $this->context->getQuery()->clean('order')->order($field, $direction);
        }
        return $this;
    }

    /**
     * Set LIMIT
     */
    public function limit(int $limit): static
    {
        $this->columns->resetCurrentField();
        $this->context->setDefaultLimit($limit);
        return $this;
    }

    /**
     * Execute custom query modifications
     */
    public function queryCustomCallback(callable $callback): static
    {
        $this->columns->resetCurrentField();
        $callback($this->context->getQuery(), $this->context->getDb());
        return $this;
    }
}
