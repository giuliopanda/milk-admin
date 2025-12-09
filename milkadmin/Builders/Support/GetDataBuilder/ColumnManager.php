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
        $this->current_field = $key;
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

    // ========================================================================
    // COLUMN CONFIGURATION
    // ========================================================================

    public function configure(string $key, array $config): void
    {
        $existing = $this->context->getModelList()->list_structure->getColumn($key);
        $action = $existing ? 'modify' : 'add';

        if (!isset($this->custom_columns[$key])) {
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
            default => null
        };
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
