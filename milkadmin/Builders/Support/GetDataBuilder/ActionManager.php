<?php

namespace Builders\Support\GetDataBuilder;

use App\{MessagesHandler, Config, Route};
use Builders\Exceptions\BuilderException;

!defined('MILK_DIR') && die();

/**
 * ActionManager - Manages row actions and bulk actions
 */
class ActionManager
{
    private BuilderContext $context;
    private array $row_actions = [];
    private array $bulk_actions = [];
    private array $action_functions = []; // Store callable actions
    private mixed $function_results = null;
    private array $action_response = [];
    private bool $update_table = true;
    private bool $executed = false;

    public function __construct(BuilderContext $context)
    {
        $this->context = $context;
    }

    // ========================================================================
    // ROW ACTIONS
    // ========================================================================

    public function addRowAction(string $key, array $config): void
    {
        $filters = $this->context->getFilters();

        if (!$this->passesFilterCondition($config, $filters)) {
            return;
        }

        $actionConfig = $this->buildRowActionConfig($key, $config);
        $this->row_actions[$key] = $actionConfig;

        // Store callable action for later execution
        if (isset($config['action']) && is_callable($config['action'])) {
            $this->action_functions[$key] = $config['action'];
        }
    }

    public function setRowActions(array $actions): void
    {
        $this->row_actions = [];
        $this->action_functions = []; // Reset also action_functions

        foreach ($actions as $key => $config) {
            $this->addRowAction($key, $config);
            // addRowAction already stores callable in $this->action_functions
        }

        $this->executeRowActionIfRequested($this->action_functions);
    }

    public function getRowActions(): array
    {
        return $this->row_actions;
    }

    // ========================================================================
    // BULK ACTIONS
    // ========================================================================

    public function addBulkAction(array $config): void
    {
        if (!isset($config['label'])) {
            throw new BuilderException('Bulk action must have a "label" parameter');
        }

        $key = $config['key'] ?? $this->generateKeyFromLabel($config['label']);
        unset($config['key']);

        $this->bulk_actions[$key] = $config;
    }

    public function setBulkActions(array $actions): void
    {
        $this->action_response = [];
        $this->update_table = true;

        $filters = $this->context->getFilters();
        $this->bulk_actions = $this->filterByCondition($actions, $filters);

        $this->executeBulkActionIfRequested();
    }

    public function getBulkActions(): array
    {
        return $this->bulk_actions;
    }

    public function getBulkActionLabels(): array
    {
        $labels = [];

        foreach ($this->bulk_actions as $key => $config) {
            if (isset($config['label'])) {
                $labels[$key] = $config['label'];
            }
        }

        return $labels;
    }

    // ========================================================================
    // DEFAULT ACTIONS
    // ========================================================================

    public function setDefaultActions(array $customActions = [], $deleteHandler = null): void
    {
        $page = $this->context->getPage();

        $defaults = [
            'edit' => [
                'label' => 'Edit',
                'link' => "?page={$page}&action=edit&id=%id%",
            ],
            'delete' => [
                'label' => 'Delete',
                'validate' => false,
                'class' => 'link-action-danger',
                'action' => $deleteHandler,
                'confirm' => 'Are you sure you want to delete this item?'
            ]
        ];

        $this->setRowActions(array_merge($this->row_actions, $defaults, $customActions));
    }

    // ========================================================================
    // RESULTS & STATE
    // ========================================================================

    public function getFunctionResults(): mixed
    {
        return $this->function_results;
    }

    public function getActionResults(): array
    {
        // Execute row actions if not already executed
        if (!$this->executed && !empty($this->action_functions)) {
            $this->executeRowActionIfRequested($this->action_functions);
        }

        $response = [];

        if ($this->function_results !== null) {
            $response = $this->normalizeResults($this->function_results);
        }

        if (is_array($this->action_response)) {
            $response = array_merge($this->action_response, $response);
        }

        return $response;
    }

    public function shouldUpdateTable(): bool
    {
        return $this->update_table;
    }

    // ========================================================================
    // PRIVATE METHODS
    // ========================================================================

    private function buildRowActionConfig(string $key, array $config): array
    {
        $actionConfig = ['label' => $config['label'] ?? $key];

        if (isset($config['link'])) {
            $actionConfig['link'] = $config['link'];
        }

        $optionalAttrs = ['target', 'class', 'confirm', 'fetch'];

        foreach ($optionalAttrs as $attr) {
            if (!isset($config[$attr])) {
                continue;
            }

            if ($attr === 'fetch') {
                $actionConfig['fetch'] = 'post';
            } elseif ($attr === 'class') {
                $actionConfig['class'] = 'js-single-action ' . $config[$attr];
            } else {
                $actionConfig[$attr] = $config[$attr];
            }
        }

        // Auto-add fetch if in fetch mode
        if ($this->context->isFetchMode() && isset($actionConfig['link']) && !isset($actionConfig['fetch'])) {
            $actionConfig['fetch'] = 'post';
        }

        return $actionConfig;
    }

    private function executeRowActionIfRequested(array $actionFunctions): void
    {
        $request = $this->context->getRequest();
        $tableAction = $request['table_action'] ?? null;

        if (!$tableAction || !isset($actionFunctions[$tableAction]) || $this->executed) {
            return;
        }

        $ids = $this->parseIds($request);
        $records = $this->context->getModel()->getByIds($ids);

        $this->executed = true;
        $this->function_results = call_user_func($actionFunctions[$tableAction], $records, $request);
    }

    private function executeBulkActionIfRequested(): void
    {
        $request = $this->context->getRequest();
        $tableAction = $request['table_action'] ?? null;

        if (!$tableAction || !isset($request['table_ids']) || $this->executed) {
            return;
        }

        foreach ($this->bulk_actions as $actionKey => $config) {
            if ($tableAction !== $actionKey || !isset($config['action'])) {
                continue;
            }

            if (($config['updateTable'] ?? true) === false) {
                $this->update_table = false;
            }

            $ids = explode(',', $request['table_ids']);
            $mode = $config['mode'] ?? 'single';

            $this->executeBulkAction($config['action'], $ids, $mode, $request);
            $this->executed = true;

            break;
        }
    }

    private function executeBulkAction(mixed $action, array $ids, string $mode, array $request): void
    {
        $model = $this->context->getModel();

        if ($mode === 'batch') {
            $records = $model->getByIds($ids);
            $result = call_user_func($action, $records, $request);

            if (is_array($result)) {
                $this->action_response = array_merge($this->action_response, $result);
            }

            return;
        }

        // Single mode
        foreach ($ids as $id) {
            $record = $model->getById($id);
            $result = call_user_func($action, $record, $request);

            if (is_array($result)) {
                $this->action_response = array_merge($this->action_response, $result);
            }
        }
    }

    private function passesFilterCondition(array $config, array $filters): bool
    {
        if (!isset($config['showIfFilter']) || empty($filters)) {
            return true;
        }

        foreach ($config['showIfFilter'] as $filterKey => $expectedValue) {
            $actualValue = $filters[$filterKey] ?? null;

            if ($actualValue === $expectedValue) {
                return true;
            }

            return false;
        }

        return true;
    }

    private function filterByCondition(array $actions, array $filters): array
    {
        return array_filter($actions, fn($config) => $this->passesFilterCondition($config, $filters));
    }

    private function parseIds(array $request): array
    {
        $tableIds = $request['table_ids'] ?? [];

        if (is_string($tableIds) && str_contains($tableIds, ',')) {
            return explode(',', $tableIds);
        }

        if (is_array($tableIds)) {
            return $tableIds;
        }

        return [$tableIds];
    }

    private function generateKeyFromLabel(string $label): string
    {
        return strtolower(str_replace(' ', '_', $label));
    }

    private function normalizeResults(mixed $results): array
    {
        if (is_array($results)) {
            return $results;
        }

        if (is_bool($results)) {
            return ['success' => $results];
        }

        return ['function_results' => $results];
    }
}
