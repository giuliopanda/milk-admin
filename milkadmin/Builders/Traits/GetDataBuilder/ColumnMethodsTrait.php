<?php

namespace Builders\Traits\GetDataBuilder;

!defined('MILK_DIR') && die();

/**
 * ColumnMethodsTrait - Column configuration methods
 */
trait ColumnMethodsTrait
{
    /**
     * Add or modify a column
     */
    public function column(
        string $key,
        ?string $label = null,
        ?string $type = null,
        array $options = [],
        ?callable $fn = null
    ): static {
        $config = array_filter([
            'label' => $label,
            'type' => $type,
            'options' => $options ?: null,
            'fn' => $fn
        ], fn($v) => $v !== null);

        $this->columns->configure($key, $config);

        return $this;
    }

    /**
     * Delete a column
     */
    public function deleteColumn(string $key): static
    {
        $this->columns->delete($key);
        return $this;
    }

    /**
     * Delete multiple columns
     */
    public function deleteColumns(array $keys): static
    {
        foreach ($keys as $key) {
            $this->columns->delete($key);
        }
        return $this;
    }

    /**
     * Hide a column
     */
    public function hideColumn(string $key): static
    {
        $this->columns->hide($key);
        return $this;
    }

    /**
     * Hide multiple columns
     */
    public function hideColumns(array $keys): static
    {
        foreach ($keys as $key) {
            $this->columns->hide($key);
        }
        return $this;
    }

    /**
     * Reorder columns
     */
    public function reorderColumns(array $order): static
    {
        $this->columns->reorder($order);
        return $this;
    }

    /**
     * Show only specified columns in query
     */
    public function showOnlyColumns(array $columns): static
    {
        $this->context->getQuery()->select($columns);
        return $this;
    }
}
