<?php

namespace Builders\Support\GetDataBuilder;

use App\Abstracts\AbstractModel;
use App\Database\Query;
use App\Modellist\{ListStructure, ModelList};

!defined('MILK_DIR') && die();

/**
 * BuilderContext - Holds shared state and dependencies for the builder
 */
class BuilderContext
{
    private AbstractModel $model;
    private string $table_id;
    private array $request;
    private ModelList $modellist;
    private Query $query;
    private ?string $page;
    private string $request_action = '';
    private bool $fetch_mode = false;
    private int $default_limit = 0;
    private array $filter_defaults = [];
    private array $sort_mappings = [];
    private array $custom_data = [];

    public function __construct(AbstractModel $model, string $table_id, ?array $request = null)
    {
        $this->model = $model;
        $this->table_id = $table_id;
        $this->request = $request ?? $this->getRequestParams($table_id);
        $this->page = $_REQUEST['page'] ?? null;
        $this->default_limit = \App\Config::get('page_info_limit', 20);
        $this->initializeModelList();
        $this->initializeQuery();
    }

    // ========================================================================
    // GETTERS
    // ========================================================================

    public function getModel(): AbstractModel
    {
        return $this->model;
    }

    public function getTableId(): string
    {
        return $this->table_id;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

    public function getModelList(): ModelList
    {
        return $this->modellist;
    }

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function getPage(): string
    {
        return $this->page ?? 'admin';
    }

    public function getDb()
    {
        return $this->model->getDb();
    }

    public function getDefaultLimit(): int
    {
        return $this->default_limit;
    }

    public function isFetchMode(): bool
    {
        return $this->fetch_mode;
    }

    public function getRequestAction(): string
    {
        return $this->request_action;
    }

    public function getSortMappings(): array
    {
        return $this->sort_mappings;
    }

    public function getFilterDefaults(): array
    {
        return $this->filter_defaults;
    }

    public function getCustomData(): array
    {
        return $this->custom_data;
    }

    // ========================================================================
    // SETTERS
    // ========================================================================

    public function setPage(string $page): void
    {
        $this->page = $page;
    }

    public function setFetchMode(bool $mode): void
    {
        $this->fetch_mode = $mode;
    }

    public function setRequestAction(string $action): void
    {
        $this->request_action = $action;
    }

    public function setDefaultLimit(int $limit): void
    {
        $this->default_limit = $limit;
        $this->modellist->setLimit($limit);
        $this->refreshQueryPagination();
    }

    public function addSortMapping(string $virtual, string $real): void
    {
        $this->sort_mappings[$virtual] = $real;
        $this->query->setSortMapping($virtual, $real);
    }

    public function addFilterDefault(string $name, mixed $value): void
    {
        $this->filter_defaults[$name] = $value;
    }

    public function setCustomData(array $data): void
    {
        $this->custom_data = $data;
    }

    public function addCustomData(string $key, mixed $value): void
    {
        if ($value === null) {
            unset($this->custom_data[$key]);
        } else {
            $this->custom_data[$key] = $value;
        }
    }

    public function setModel(AbstractModel $model) {
        $this->model = $model;
    }

    // ========================================================================
    // FILTERS
    // ========================================================================

    public function getFilters(): array
    {
        $filters = $this->request['filters'] ?? '';
        $applied = $this->filter_defaults;

        if ($filters === '') {
            return $applied;
        }

        $decoded = json_decode($filters, true);

        if (!is_array($decoded)) {
            return $applied;
        }

        foreach ($decoded as $filter) {
            $parts = explode(':', $filter, 2);
            $applied[$parts[0]] = $parts[1] ?? '';
        }

        return $applied;
    }

    public function hasFilter(string $name): bool
    {
        $filters = $this->getFilters();
        return isset($filters[$name]) && $filters[$name] !== '';
    }

    // ========================================================================
    // ORDER & PAGINATION
    // ========================================================================

    public function setOrder(string $field, string $direction): void
    {
        $this->modellist->setOrder($field, strtolower($direction));
        $this->refreshQueryPagination();
    }

    public function refreshQueryPagination(): void
    {
        $request = $this->request;

        // Order handling
        $orderField = $request['order_field'] ?? $this->modellist->default_order_field ?? $this->modellist->primary_key;
        $orderDir = $request['order_dir'] ?? $this->modellist->default_order_dir ?? 'desc';

        if (isset($this->sort_mappings[$orderField])) {
            $orderField = $this->sort_mappings[$orderField];
        }

        // Pagination handling
        $limit = _absint($request['limit'] ?? $this->default_limit);
        $page = _absint($request['page'] ?? 1);

        $limit = max(1, $limit);
        $page = max(1, $page);

        $offset = ($page * $limit) - $limit;
        $offset = max(0, $offset);

        // Apply to query
        if ($limit > 0) {
            $this->query->limit($offset, $limit);
        }

        $this->query->clean('order');
        if (str_contains($orderField, '.')) {
            [$relation, $field] = explode('.', $orderField, 2);
            if ($this->hasRelationshipAlias($relation)) {
                $this->query->orderHas($relation, $field, $orderDir);
            } else {
                $this->query->order($orderField, $orderDir);
            }
        } else {
            $this->query->order($orderField, $orderDir);
        }
    }

    // ========================================================================
    // PRIVATE METHODS
    // ========================================================================

    private function initializeModelList(): void
    {
        $this->modellist = new ModelList(
            $this->model->getTable(),
            $this->table_id,
            $this->model->getDb()
        );

        $listStructure = $this->createListStructure();
        $this->modellist->setListStructure($listStructure);
        $this->modellist->setRequest($this->request);
        $this->modellist->setModel($this->model);
    }

    private function createListStructure(): ListStructure
    {
        $structure = new ListStructure();
        $rules = $this->model->getRules('list', true);

        foreach ($rules as $key => $rule) {
            $type = $this->determineColumnType($rule);
            $label = $rule['label'] ?? $key;
            $structure->setColumn($key, $label, $type);
        }

        return $structure;
    }

    private function determineColumnType(array $rule): string
    {
        if ($rule['type'] !== 'array') {
            return 'html';
        }

        return match ($rule['form-type'] ?? null) {
            'file' => 'file',
            default => 'array'
        };
    }

    private function initializeQuery(): void
    {
        // Use the model's query() method to apply default scopes (including withCount)
        $this->query = $this->model->query();
        $this->query->clean('order');
        $this->query->clean('limit');

        // Set ModelList pagination properties from request
        $request = $this->request;
        $limit = _absint($request['limit'] ?? $this->default_limit);
        $page = _absint($request['page'] ?? 1);

        $limit = max(1, $limit);
        $page = max(1, $page);

        $this->modellist->limit = $limit;
        $this->modellist->page = $page;

        // Set order properties
        if (!$this->modellist->default_order_field) {
            $this->modellist->default_order_field = $this->modellist->primary_key;
            $this->modellist->default_order_dir = 'desc';
        }

        $orderField = $request['order_field'] ?? $this->modellist->default_order_field;
        $orderDir = $request['order_dir'] ?? $this->modellist->default_order_dir;

        $this->modellist->order_field = $orderField;
        $this->modellist->order_dir = $orderDir;

        $this->refreshQueryPagination();
    }

    private function getRequestParams(string $table_id): array
    {
        return $_REQUEST[$table_id] ?? [];
    }

    private function hasRelationshipAlias(string $alias): bool
    {
        if (!method_exists($this->model, 'getRules')) {
            return false;
        }

        $rules = $this->model->getRules();
        foreach ($rules as $rule) {
            if (!isset($rule['relationship'])) {
                continue;
            }
            if (($rule['relationship']['alias'] ?? '') === $alias) {
                return true;
            }
        }

        return false;
    }
}
