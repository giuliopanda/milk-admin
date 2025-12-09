<?php

namespace Builders\Traits\GetDataBuilder;

!defined('MILK_DIR') && die();

/**
 * ConditionMethodsTrait - Condition-based styling methods
 *
 * These are base methods that should be overridden in subclasses
 * (TableBuilder, ListBuilder) for specific implementations
 */
trait ConditionMethodsTrait
{
    protected array $row_conditions = [];
    protected array $column_conditions = [];
    protected array $box_conditions = [];
    protected array $field_conditions = [];

    /**
     * Get row conditions (for table)
     */
    protected function getRowConditions(): array
    {
        return $this->row_conditions;
    }

    /**
     * Get column conditions (for table)
     */
    protected function getColumnConditions(): array
    {
        return $this->column_conditions;
    }

    /**
     * Get box conditions (for list)
     */
    protected function getBoxConditions(): array
    {
        return $this->box_conditions;
    }

    /**
     * Get field conditions (for list)
     */
    protected function getFieldConditions(): array
    {
        return $this->field_conditions;
    }
}
