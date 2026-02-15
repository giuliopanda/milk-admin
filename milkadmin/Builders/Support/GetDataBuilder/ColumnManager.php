<?php

namespace Builders\Support\GetDataBuilder;

use Builders\Exceptions\BuilderException;

!defined('MILK_DIR') && die();

/**
 * ColumnManager - Manages column configuration and customization
 */
class ColumnManager
{
    private BuilderContext $context;
    private array $custom_columns = [];
    private array $hidden_columns = [];
    private array $column_properties = [];
    private ?string $current_field = null;
    private ?string $current_order_field = null;
    private ?string $old_current_order_field = null;

    public function __construct(BuilderContext $context)
    {
        $this->context = $context;
    }

    // ========================================================================
    // FIELD CONTEXT
    // ========================================================================

    public function setCurrentField(string $key): void
    {
        if ($key === '') {
            throw BuilderException::invalidField($key);
        }

        $this->ensureDefaultLabel($key);

        // Salva il vecchio current_order_field in old_current_order_field
        $this->old_current_order_field = $this->current_order_field;

        // Se c'è un current_order_field, posiziona il nuovo campo dopo di esso
        if ($this->current_order_field !== null) {
            $this->moveAfter($key, $this->current_order_field);
        }

        // Imposta il nuovo campo come current_field e current_order_field
        $this->current_field = $key;
        $this->current_order_field = $key;
    }

    public function getCurrentField(): ?string
    {
        return $this->current_field;
    }

    public function resetCurrentField(): void
    {
        $this->current_field = null;
    }

    public function requireCurrentField(string $method): string
    {
        if ($this->current_field === null) {
            throw BuilderException::noCurrentField($method);
        }
        return $this->current_field;
    }

    private function ensureDefaultLabel(string $key): void
    {
        $modelList = $this->context->getModelList();
        $existing = $modelList->list_structure->getColumn($key);
        $custom = $this->custom_columns[$key] ?? null;

        $needs_config = ($existing === null);
        if ($custom && ($custom['action'] ?? null) === 'delete') {
            $needs_config = true;
        }
        if (in_array($key, $this->hidden_columns, true)) {
            $needs_config = true;
        }

        if (!$needs_config) {
            return;
        }

        $label = $custom['label'] ?? ($existing['label'] ?? $key);
        $this->configure($key, ['label' => $label]);
    }

    // ========================================================================
    // COLUMN CONFIGURATION
    // ========================================================================

    public function configure(string $key, array $config): void
    {
        $existing = $this->context->getModelList()->list_structure->getColumn($key);
        $action = $existing ? 'modify' : 'add';

        if (!isset($this->custom_columns[$key])) {
            $this->custom_columns[$key] = ['action' => $action];
        } else if (isset($this->custom_columns[$key]['action']) && $this->custom_columns[$key]['action'] === 'delete') {
            // If column was marked for deletion, reset it and set proper action
            $this->custom_columns[$key] = ['action' => $action];
        }

        $this->custom_columns[$key] = array_merge($this->custom_columns[$key], $config);

        // Remove from hidden if being configured
        $this->hidden_columns = array_diff($this->hidden_columns, [$key]);
    }

    public function setLabel(string $key, string $label): void
    {
        $this->configure($key, ['label' => $label]);
    }

    public function setType(string $key, string $type): void
    {
        $this->configure($key, ['type' => $type]);
    }

    public function setOptions(string $key, array $options): void
    {
        $this->configure($key, ['options' => $options]);
    }

    public function setFunction(string $key, callable $fn): void
    {
        $this->configure($key, ['fn' => $fn]);
    }

    public function setDisableSort(string $key, bool $disable = true): void
    {
        $this->configure($key, ['disable_sort' => $disable]);
    }

    public function setShowIfFilter(string $key, array $condition): void
    {
        $this->configure($key, ['showIfFilter' => $condition]);
    }

    /**
     * Conditionally display a cell value based on an ExpressionParser expression.
     *
     * The expression is evaluated against the current row data using parameters syntax:
     * - Example: '[STATUS] == "active"'
     *
     * If the expression evaluates to false, the column formatter (fn) is skipped and
     * the cell is replaced with $elseValue (defaults to empty string).
     */
    public function setShowIf(string $key, string $expression, mixed $elseValue = ''): void
    {
        $this->configure($key, [
            'showIf' => $expression,
            'showIfElse' => $elseValue
        ]);
    }

    // ========================================================================
    // COLUMN VISIBILITY
    // ========================================================================

    public function hide(string $key): void
    {
        if (!in_array($key, $this->hidden_columns)) {
            $this->hidden_columns[] = $key;
        }
    }

    public function delete(string $key): void
    {
        $this->custom_columns[$key] = ['action' => 'delete'];
    }

    public function reorder(array $order): void
    {
        $this->custom_columns['_reorder'] = [
            'action' => 'reorder',
            'column_order' => $order
        ];
    }

    public function moveBefore(string $fieldToMove, string $beforeField): void
    {
        $this->custom_columns['_move_before_' . $fieldToMove] = [
            'action' => 'move_before',
            'field_to_move' => $fieldToMove,
            'before_field' => $beforeField
        ];

        // Quando fai moveBefore, il campo successivo deve andare dopo il campo precedente
        // e non seguire lo spostamento, quindi resettiamo current_order_field a old_current_order_field
        $this->current_order_field = $this->old_current_order_field;
    }

    public function moveAfter(string $fieldToMove, string $afterField): void
    {
        $this->custom_columns['_move_after_' . $fieldToMove] = [
            'action' => 'move_after',
            'field_to_move' => $fieldToMove,
            'before_field' => $afterField
        ];
    }

    /**
     * Reset all fields - marks all existing columns for deletion
     * Useful when you want to start with a clean slate and only show specific columns
     *
     * @return void
     */
    public function resetFields(): void
    {
        $modelList = $this->context->getModelList();
        $allColumns = array_keys($modelList->list_structure->toArray());

        // Mark all columns for deletion
        foreach ($allColumns as $column) {
            $this->delete($column);
        }
    }

    // ========================================================================
    // COLUMN PROPERTIES
    // ========================================================================

    public function setTruncate(string $key, int $length, string $suffix = '...'): void
    {
        if (!isset($this->column_properties[$key])) {
            $this->column_properties[$key] = [];
        }

        $this->column_properties[$key]['truncate'] = [
            'length' => $length,
            'suffix' => $suffix
        ];
    }

    public function getProperties(string $key): array
    {
        return $this->column_properties[$key] ?? [];
    }

    // ========================================================================
    // ACCESSORS
    // ========================================================================

    public function getCustomColumns(): array
    {
        return $this->custom_columns;
    }

    public function getHiddenColumns(): array
    {
        return $this->hidden_columns;
    }

    public function hasCustomColumn(string $key): bool
    {
        return isset($this->custom_columns[$key]) || in_array($key, $this->hidden_columns);
    }

    public function getColumnConfig(string $key): ?array
    {
        return $this->custom_columns[$key] ?? null;
    }

    public function getAllProperties(): array
    {
        return $this->column_properties;
    }

    // ========================================================================
    // APPLY TO MODEL LIST
    // ========================================================================

    public function applyToModelList(): void
    {
        $modelList = $this->context->getModelList();
        $filters = $this->context->getFilters();

        foreach ($this->custom_columns as $key => $config) {
            if (!$this->shouldShowColumn($config, $filters)) {
                $modelList->list_structure->hideColumn($key);
                continue;
            }

            $this->applyColumnConfig($key, $config, $modelList);
        }

        foreach ($this->hidden_columns as $key) {
            $modelList->list_structure->hideColumn($key);
        }
    }

    private function shouldShowColumn(array $config, array $filters): bool
    {
        if (!isset($config['showIfFilter'])) {
            return true;
        }

        foreach ($config['showIfFilter'] as $filterKey => $expectedValue) {
            $actualValue = $filters[$filterKey] ?? null;

            if ($actualValue !== $expectedValue) {
                return false;
            }
        }

        return true;
    }

    private function applyColumnConfig(string $key, array $config, $modelList): void
    {
        $action = $config['action'] ?? 'modify';

        match ($action) {
            'add' => $this->applyAddColumn($key, $config, $modelList),
            'modify' => $this->applyModifyColumn($key, $config, $modelList),
            'delete' => $modelList->list_structure->deleteColumn($key),
            'reorder' => $modelList->list_structure->reorderColumns($config['column_order'] ?? []),
            'move_before' => $this->applyMoveBefore($config, $modelList),
            'move_after' => $this->applyMoveAfter($config, $modelList),
            default => null
        };
    }

    private function applyMoveBefore(array $config, $modelList): void
    {
        $fieldToMove = $config['field_to_move'] ?? null;
        $beforeField = $config['before_field'] ?? null;

        if (!$fieldToMove || !$beforeField) {
            return;
        }

        $structure = $modelList->list_structure;

        // Get current column order from the properties array
        $currentColumns = array_keys($structure->toArray());

        if (!in_array($fieldToMove, $currentColumns) || !in_array($beforeField, $currentColumns)) {
            return;
        }

        // Remove field from current position
        $currentColumns = array_diff($currentColumns, [$fieldToMove]);

        // Rebuild array with field in new position
        $newOrder = [];
        foreach ($currentColumns as $column) {
            if ($column === $beforeField) {
                $newOrder[] = $fieldToMove;
            }
            $newOrder[] = $column;
        }

        $structure->reorderColumns($newOrder);
    }

    private function applyMoveAfter(array $config, $modelList): void
    {
        $fieldToMove = $config['field_to_move'] ?? null;
        $afterField = $config['before_field'] ?? null; // before_field contiene il campo "after" per riuso struttura

        if (!$fieldToMove || !$afterField) {
            return;
        }

        $structure = $modelList->list_structure;

        // Get current column order from the properties array
        $currentColumns = array_keys($structure->toArray());

        if (!in_array($fieldToMove, $currentColumns) || !in_array($afterField, $currentColumns)) {
            return;
        }

        // Remove field from current position
        $currentColumns = array_diff($currentColumns, [$fieldToMove]);

        // Rebuild array with field in new position (after the specified field)
        $newOrder = [];
        foreach ($currentColumns as $column) {
            $newOrder[] = $column;
            if ($column === $afterField) {
                $newOrder[] = $fieldToMove;
            }
        }

        $structure->reorderColumns($newOrder);
    }

    private function applyAddColumn(string $key, array $config, $modelList): void
    {
        $label = $config['label'] ?? $key;
        $type = $config['type'] ?? 'html';
        $options = $config['options'] ?? [];
        $sortable = !($config['disable_sort'] ?? false);

        $modelList->list_structure->setColumn($key, $label, $type, $sortable, false, $options);
    }

    private function applyModifyColumn(string $key, array $config, $modelList): void
    {
        $structure = $modelList->list_structure;

        if (isset($config['label'])) {
            $structure->setLabel($key, $config['label']);
        }
        if (isset($config['type'])) {
            $structure->setType($key, $config['type']);
        }
        if (isset($config['disable_sort'])) {
            $structure->setOrder($key, !$config['disable_sort']);
        }
        if (isset($config['options'])) {
            $structure->setOptions($key, $config['options']);
        }
    }
}
