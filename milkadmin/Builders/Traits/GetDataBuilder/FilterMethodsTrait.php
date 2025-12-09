<?php

namespace Builders\Traits\GetDataBuilder;

!defined('MILK_DIR') && die();

/**
 * FilterMethodsTrait - Filter management methods
 */
trait FilterMethodsTrait
{
    /**
     * Add custom filter with callback
     */
    public function filter(string $name, callable $callback, $defaultValue = null): static
    {
        $modelList = $this->context->getModelList();

        $modelList->addFilter($name, function ($query, $value) use ($callback) {
            if ($value !== '' && $value !== null && !empty($value)) {
                $callback($query, $value);
            }
        });

        if ($defaultValue === null) {
            return $this;
        }

        // Apply default if not in request
        if (!$this->isFilterInRequest($name)) {
            $this->context->addFilterDefault($name, $defaultValue);
            $callback($this->context->getQuery(), $defaultValue);
        }

        return $this;
    }

    /**
     * Add equals filter
     */
    public function filterEquals(string $name, string $field): static
    {
        $db = $this->context->getDb();

        $this->context->getModelList()->addFilter($name, function ($query, $value) use ($field, $db) {
            $value = trim($value);

            if ($value !== '') {
                $query->where($db->qn($field) . ' = ?', [$value]);
            }
        });

        return $this;
    }

    /**
     * Add LIKE filter
     */
    public function filterLike(string $name, string $field, string $position = 'both'): static
    {
        $db = $this->context->getDb();

        $this->context->getModelList()->addFilter($name, function ($query, $value) use ($field, $position, $db) {
            $value = trim($value);

            if ($value === '') {
                return;
            }

            $searchValue = match ($position) {
                'start' => $value . '%',
                'end' => '%' . $value,
                default => '%' . $value . '%'
            };

            $query->where($db->qn($field) . ' LIKE ?', [$searchValue]);
        });

        return $this;
    }

    /**
     * Check if a filter is present in the request
     */
    private function isFilterInRequest(string $name): bool
    {
        $filtersJson = $this->context->getRequest()['filters'] ?? '';

        if ($filtersJson === '') {
            return false;
        }

        $filters = json_decode($filtersJson, true);

        if (!is_array($filters)) {
            return false;
        }

        foreach ($filters as $filterStr) {
            $parts = explode(':', $filterStr, 2);

            if ($parts[0] === $name) {
                return true;
            }
        }

        return false;
    }
}
