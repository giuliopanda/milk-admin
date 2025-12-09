<?php

namespace Builders\Support\GetDataBuilder;

use App\{Get, Config};
use App\Exceptions\DatabaseException;

!defined('MILK_DIR') && die();

/**
 * DataProcessor - Handles data fetching and row transformation
 */
class DataProcessor
{
    private BuilderContext $context;
    private ColumnManager $columns;
    private array $rows_raw = [];
    private array $query_columns = [];
    private ?array $footer_data = null;

    public function __construct(BuilderContext $context, ColumnManager $columns)
    {
        $this->context = $context;
        $this->columns = $columns;
    }

    public function setFooter(array $data): void
    {
        $this->footer_data = $data;
    }

    public function getRawRows(): array
    {
        return $this->rows_raw;
    }

    public function getQueryColumns(): array
    {
        return $this->query_columns;
    }

    /**
     * Process and return complete data array
     */
    public function process(array $rowActions, array $bulkActions): array
    {
        $modelList = $this->context->getModelList();
        $query = $this->context->getQuery();
        $request = $this->context->getRequest();

        // Apply filters
        $modelList->applyFilters($query, $_REQUEST[$this->context->getTableId()] ?? []);

        // Fetch rows
        $rows = $this->fetchRows();

        // Get total count
        $total = $this->fetchTotal();

        // Build structure and page info
        $info = $modelList->getListStructure($this->query_columns, $this->context->getModel()->getPrimaryKey());
        $pageInfo = $modelList->getPageInfo($total);

        // Apply configurations
        $this->columns->applyToModelList();
        $this->configurePageInfo($pageInfo, $rowActions, $bulkActions);

        if (!empty($rowActions)) {
            $info->setAction($rowActions);
        }

        // Handle footer
        if ($this->footer_data !== null) {
            $pageInfo->setFooter(true);
            $rows[] = $this->createFooterRow();
        }

        return [
            'rows' => $rows,
            'info' => $info,
            'page_info' => $pageInfo
        ];
    }

    /**
     * Fetch and transform rows from database
     */
    public function fetchRows(): array
    {
        $model = $this->context->getModel();
        $query = $this->context->getQuery();

        try {
            $result = $model->get($query);
            $result->with();

            $this->rows_raw = $result->getRawData();
            $this->query_columns = $result->getQueryColumns();

            return $this->transformRows($result->getFormattedData());
        } catch (DatabaseException $e) {
            if (Config::get('debug', false)) {
                throw $e;
            }

            return [];
        }
    }

    /**
     * Transform rows with custom column handlers
     */
    private function transformRows(array $rows): array
    {
        $customColumns = $this->columns->getCustomColumns();
        $properties = $this->columns->getAllProperties();

        foreach ($rows as $index => $row) {
            // First pass: Extract dot notation values
            $this->extractDotNotationValues($row, $customColumns);

            // Second pass: Apply custom functions to model columns
            $this->applyCustomFunctions($row, $index, $customColumns);

            // Third pass: Apply column properties (truncate, etc.)
            $this->applyColumnProperties($row, $properties);
        }

        return $rows;
    }

    private function extractDotNotationValues(object $row, array $customColumns): void
    {
        foreach ($customColumns as $key => $config) {
            if (!str_contains($key, '.')) {
                continue;
            }

            $value = $this->extractDotNotationValue($row, $key);
            $row->{$key} = $value;
        }
    }

    private function applyCustomFunctions(object $row, int $index, array $customColumns): void
    {
        // Apply to query columns first
        foreach ($this->query_columns as $column) {
            if (str_contains($column, '.')) {
                continue;
            }

            $config = $customColumns[$column] ?? null;

            if (!$config || !isset($config['fn']) || !is_callable($config['fn'])) {
                continue;
            }

            $row->{$column} = call_user_func($config['fn'], $this->rows_raw[$index]);
        }

        // Apply to custom/virtual columns
        foreach ($customColumns as $key => $config) {
            if (!isset($config['fn']) || !is_callable($config['fn'])) {
                continue;
            }

            // Skip if already processed
            if (!str_contains($key, '.') && in_array($key, $this->query_columns)) {
                continue;
            }

            $row->{$key} = call_user_func($config['fn'], $this->rows_raw[$index]);
        }
    }

    private function applyColumnProperties(object $row, array $properties): void
    {
        foreach ($properties as $key => $props) {
            if (!isset($row->{$key})) {
                continue;
            }

            $value = $row->{$key};

            // Apply truncate
            if (isset($props['truncate'])) {
                $value = $this->truncateValue($value, $props['truncate']);
            }

            $row->{$key} = $value;
        }
    }

    private function truncateValue(mixed $value, array $config): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $length = $config['length'];
        $suffix = $config['suffix'];

        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length) . $suffix;
    }

    private function fetchTotal(): int
    {
        $query = $this->context->getQuery();
        $db = $this->context->getDb();

        try {
            $result = $this->context->getModel()->get($query);

            if ($result->getLastError() !== '') {
                return 0;
            }

            return (int) $db->getVar(...$query->getTotal());
        } catch (DatabaseException $e) {
            if (Config::get('debug', false)) {
                throw $e;
            }

            return 0;
        }
    }

    private function configurePageInfo($pageInfo, array $rowActions, array $bulkActions): void
    {
        $action = $this->context->getRequestAction();

        if ($action !== '') {
            $pageInfo->setAction($action);
        }

        $pageInfo->setDefaultLimit($this->context->getDefaultLimit());

        if (!empty($bulkActions)) {
            $labels = [];
            foreach ($bulkActions as $key => $config) {
                if (isset($config['label'])) {
                    $labels[$key] = $config['label'];
                }
            }
            $pageInfo->setBulkActions($labels);
        }
    }

    private function createFooterRow(): object
    {
        $footer = (object) [];

        foreach ($this->query_columns as $index => $column) {
            $footer->{$column} = $this->footer_data[$index] ?? '';
        }

        return $footer;
    }

    /**
     * Extract value from dot notation path
     */
    public function extractDotNotationValue(object|array $row, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $row;

        foreach ($parts as $part) {
            if (!is_object($current) && !is_array($current)) {
                return '';
            }

            if (is_array($current) && is_numeric($part)) {
                $current = $current[$part] ?? null;
                continue;
            }

            if (is_object($current)) {
                $current = $current->{$part} ?? null;
                continue;
            }

            if (is_array($current)) {
                $current = $current[$part] ?? null;
                continue;
            }

            return '';
        }

        // Handle hasMany arrays
        if (is_array($current) && count($current) > 0) {
            return $this->formatArrayValue($current);
        }

        return $current ?? '';
    }

    private function formatArrayValue(array $value): mixed
    {
        $firstKey = array_key_first($value);

        // Array of objects (hasMany)
        if (is_numeric($firstKey) && is_object($value[$firstKey])) {
            return count($value) . ' items';
        }

        // Formatted array (image/file data)
        if (isset($value[$firstKey]['url']) || isset($value[$firstKey]['name'])) {
            return $value;
        }

        // Array of scalars
        if (!is_object($value[$firstKey]) && !is_array($value[$firstKey])) {
            return implode(', ', $value);
        }

        return $value;
    }
}
