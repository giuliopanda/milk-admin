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
    public function column(string $key, ?string $label = null, ?string $type = null, array $options = [], ?callable $fn = null): static {
        $config = array_filter([
            'label' => $label,
            'type' => $type,
            'options' => $options ?: null,
            'fn' => $fn
        ], fn($v) => $v !== null);

        $this->columns->configure($key, $config);
        $this->columns->setCurrentField($key);
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
     * Reset all fields - hides all existing columns from the model
     * Useful when you want to start with a clean slate and only show specific columns
     *
     * @return static For method chaining
     * @example
     * TableBuilder::create($model, 'table_id')
     *     ->resetFields()  // Hide all existing columns
     *     ->field('id')    // Show only the columns you want
     *     ->field('name')
     *     ->field('email')
     */
    public function resetFields(): static
    {
        $this->columns->resetFields();
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
