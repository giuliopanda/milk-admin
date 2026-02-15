<?php

namespace Builders\Support\GetDataBuilder;

use App\{Get, Config, ExpressionParser};
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
    private ?DatabaseException $error = null;
    private ExpressionParser $expressionParser;
    /** @var array<string, array> */
    private array $expressionAstCache = [];

    public function __construct(BuilderContext $context, ColumnManager $columns)
    {
        $this->context = $context;
        $this->columns = $columns;
        $this->expressionParser = new ExpressionParser();
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

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getError(): ?DatabaseException
    {
        return $this->error;
    }

    /**
     * Process and return complete data array
     */
    public function process(array $rowActions, array $bulkActions): array
    {
        $modelList = $this->context->getModelList();
        $query = $this->context->getQuery();

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
            $this->context->setModel($result);
            $this->rows_raw = $result->getRawData();
            $this->query_columns = $result->getQueryColumns();

            return $this->transformRows($result);
        } catch (DatabaseException $e) {
            // Save error for later display
            $this->error = $e;
            return [];
        }
    }

    /**
     * Transform rows with custom column handlers
     */
    private function transformRows( $result): array
    {
        $rows = $result->getFormattedData();
        $customColumns = $this->columns->getCustomColumns();
        $properties = $this->columns->getAllProperties();

        [$showIfConfigs, $dotKeys] = $this->prepareShowIfConfigs($customColumns);

        foreach ($rows as $index => $row) {
            // First pass: Extract dot notation values
           
            $result->moveTo($index);
             $this->extractDotNotationValues($row, $customColumns);

            // 1) showIf (must run BEFORE custom formatter functions)
            $skipColumns = [];
            if (!empty($showIfConfigs)) {
                $rawRow = $this->rows_raw[$index] ?? null;
                $params = $this->buildShowIfParameters($row, $rawRow, $dotKeys);

                foreach ($showIfConfigs as $colKey => $cfg) {
                    $expr = $cfg['expr'];
                    if ($expr === '') {
                        continue;
                    }

                    $shouldShow = $this->evaluateShowIfCondition($expr, $params);
                    if ($shouldShow) {
                        continue;
                    }

                    $elseValue = $cfg['else'];
                    if (is_callable($elseValue)) {
                        $elseValue = call_user_func($elseValue, $result);
                    }

                    $row->{$colKey} = $elseValue;
                    $skipColumns[$colKey] = true;
                }
            }

            // Second pass: Apply custom functions to model columns
            $this->applyCustomFunctions($row, $index, $customColumns, $result, $skipColumns);

            // Third pass: Apply column properties (truncate, etc.)
            $this->applyColumnProperties($row, $properties);
           
        }
        
        return $rows;
    }

    /**
     * @return array{0: array<string, array{expr: string, else: mixed}>, 1: array<int, string>}
     */
    private function prepareShowIfConfigs(array $customColumns): array
    {
        $showIfConfigs = [];
        $dotKeys = [];

        foreach ($customColumns as $key => $config) {
            if (!is_string($key) || $key === '' || str_starts_with($key, '_')) {
                continue;
            }

            if (($config['action'] ?? null) === 'delete') {
                continue;
            }

            if (str_contains($key, '.')) {
                $dotKeys[] = $key;
            }

            if (!isset($config['showIf']) || !is_string($config['showIf'])) {
                continue;
            }

            $showIfConfigs[$key] = [
                'expr' => trim($config['showIf']),
                'else' => $config['showIfElse'] ?? ''
            ];
        }

        $dotKeys = array_values(array_unique($dotKeys));

        return [$showIfConfigs, $dotKeys];
    }

    private function buildShowIfParameters(object $row, ?object $rawRow, array $dotKeys): array
    {
        $params = [];

        if ($rawRow !== null) {
            foreach (get_object_vars($rawRow) as $key => $value) {
                $this->addShowIfParamAliases($params, (string)$key, $value);
            }
        }

        // Provide dot-notation keys as direct params (e.g. [author.name])
        if (!empty($dotKeys)) {
            $source = $rawRow ?? $row;
            foreach ($dotKeys as $dotKey) {
                $value = $this->extractDotNotationValue($source, $dotKey);
                $this->addShowIfParamAliases($params, $dotKey, $value);
            }
        }

        // Fallback: merge also formatted row values without overriding raw values
        foreach (get_object_vars($row) as $key => $value) {
            $this->addShowIfParamAliases($params, (string)$key, $value, false);
        }

        return $params;
    }

    private function addShowIfParamAliases(array &$params, string $key, mixed $value, bool $override = true): void
    {
        if ($key === '') {
            return;
        }

        $candidates = [$key, strtolower($key), strtoupper($key)];
        foreach ($candidates as $candidate) {
            if ($override) {
                $params[$candidate] = $value;
                continue;
            }
            if (!array_key_exists($candidate, $params)) {
                $params[$candidate] = $value;
            }
        }
    }

    private function evaluateShowIfCondition(string $expression, array $params): bool
    {
        try {
            if (!isset($this->expressionAstCache[$expression])) {
                $this->expressionAstCache[$expression] = $this->expressionParser->parse($expression);
            }
            $ast = $this->expressionAstCache[$expression];

            $result = $this->expressionParser
                ->resetAll()
                ->setParameters($params)
                ->execute($ast);

            // For showIf we accept truthy values, not only strict booleans.
            return $this->expressionParser->normalizeCheckboxValue($result);
        } catch (\Throwable $e) {
            // Don't break the whole table for a configuration error.
            // In case of failure, default to "show" and log the error.
            error_log('TableBuilder showIf error: ' . $e->getMessage() . ' | expr: ' . $expression);
            return true;
        }
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

    private function applyCustomFunctions(object $row, int $index, array $customColumns, $result, array $skipColumns = []): void
    {
        // Apply to query columns first
        foreach ($this->query_columns as $column) {
            if (str_contains($column, '.')) {
                continue;
            }

            if (isset($skipColumns[$column])) {
                continue;
            }

            $config = $customColumns[$column] ?? null;

            if (!$config || !isset($config['fn']) || !is_callable($config['fn'])) {
                continue;
            }

            $row->{$column} = call_user_func($config['fn'], $result);
        }

        // Apply to custom/virtual columns
        foreach ($customColumns as $key => $config) {
            if (!isset($config['fn']) || !is_callable($config['fn'])) {
                continue;
            }

            if (isset($skipColumns[$key])) {
                continue;
            }

            // Skip if already processed
            if (!str_contains($key, '.') && in_array($key, $this->query_columns)) {
                continue;
            }

            $row->{$key} = call_user_func($config['fn'], $result);
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
            // Execute COUNT query directly without fetching all rows first
            return (int) $db->getVar(...$query->getTotal());
        } catch (DatabaseException $e) {
            // Save error for later display (if not already set)
            if ($this->error === null) {
                $this->error = $e;
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

        // Set primary key for table actions
        $pageInfo->setPrimaryKey($this->context->getModel()->getPrimaryKey());

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

        // Apply filter defaults to page_info for frontend sync
        $filterDefaults = $this->context->getFilterDefaults();
        if (!empty($filterDefaults)) {
            // Get current filters from request
            $currentFilters = $this->context->getRequest()['filters'] ?? '';

            // If no filters in request, apply defaults
            if ($currentFilters === '') {
                $filterArray = [];
                foreach ($filterDefaults as $name => $value) {
                    if ($value !== '' && $value !== null) {
                        $filterArray[] = $name . ':' . $value;
                    }
                }
                if (!empty($filterArray)) {
                    $pageInfo['filters'] = json_encode($filterArray);
                }
            }
        }

        // Apply custom_data if set
        $customData = $this->context->getCustomData();
        if (!empty($customData)) {
            $pageInfo['custom_data'] = $customData;
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
