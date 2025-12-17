<?php

namespace Builders;

use App\Abstracts\AbstractModel;
use App\Database\Query;
use App\{ExtensionLoader, Config};
use App\Abstracts\Traits\ExtensionManagementTrait;
use Builders\Traits\GetDataBuilder\{
    MethodFirstCompatibilityTrait,
    FieldFirstTrait,
    QueryMethodsTrait,
    FilterMethodsTrait,
    ActionMethodsTrait,
    ColumnMethodsTrait,
    ConditionMethodsTrait
};
use Builders\Support\GetDataBuilder\{
    BuilderContext,
    ColumnManager,
    ActionManager,
    DataProcessor
};
use Builders\Exceptions\BuilderException;

!defined('MILK_DIR') && die();

/**
 * GetDataBuilder - Fluent interface for creating and managing dynamic data tables
 *
 * Provides a simplified API that wraps ModelList, ListStructure, and PageInfo
 * for easier table creation with method chaining.
 *
 * @package Builders
 */
class GetDataBuilder
{
    use ExtensionManagementTrait;
    use MethodFirstCompatibilityTrait;
    use FieldFirstTrait;
    use QueryMethodsTrait;
    use FilterMethodsTrait;
    use ActionMethodsTrait;
    use ColumnMethodsTrait;
    use ConditionMethodsTrait;

    protected $model;
    protected $query;
    protected $modellist_service;
    protected $table_id;
    protected $request;
    protected $rows_raw = [];
    protected $fetch_mode = false;

    protected BuilderContext $context;
    protected ColumnManager $columns;
    protected ActionManager $actions;
    protected DataProcessor $dataProcessor;
   
    protected string $current_field = '';

    protected array $extensions = [];
    protected array $loaded_extensions = [];

    public function __construct(AbstractModel $model, string $table_id, ?array $request = null)
    {
        // Alias immediati per backward compatibility
        $this->model = $model;
        $this->table_id = $table_id;
        $this->request = $request ?? ($_REQUEST[$table_id] ?? []);

        // Create context once
        $this->context = new BuilderContext($model, $table_id, $request);

        // Aliases after context
        $this->query = $this->context->getQuery();
        $this->modellist_service = $this->context->getModelList();

        // Initialize managers with the same context
        $this->columns = new ColumnManager($this->context);
        $this->actions = new ActionManager($this->context);
        $this->dataProcessor = new DataProcessor($this->context, $this->columns);

        $this->initializeExtensions();
        $this->configure();
        $this->callExtensionHook('configure');
        $this->autoConfigureArrayColumns();
    }

    /**
     * Factory method to create builder instance
     */
    public static function create(AbstractModel $model, string $table_id, ?array $request = null): static
    {
        return new static($model, $table_id, $request);
    }

    /**
     * Configuration method for child classes
     */
    protected function configure(): void
    {
        // Override in child classes
    }

    /**
     * Get complete data array for rendering
     */
    public function getData(): array
    {
        $this->callExtensionHook('beforeGetData');

        $data = $this->dataProcessor->process(
            $this->actions->getRowActions(),
            $this->actions->getBulkActions()
        );

        return $this->callExtensionPipeline('afterGetData', $data);
    }

    /**
     * Render the table/list HTML
     */
    public function render(): string
    {
        return '';
    }

    /**
     * Get response for AJAX requests
     */
    public function getResponse(): array
    {
        $response = $this->actions->getActionResults();

        if ($this->actions->shouldUpdateTable()) {
            $this->getData();
            $response['html'] = $this->render();
        }

        $response['table_id'] = $this->context->getTableId();

        return $response;
    }

    /**
     * Create a SearchBuilder linked to this table
     */
    public function createSearchBuilder(): SearchBuilder
    {
        return new SearchBuilder($this->context->getTableId());
    }

    public function getRowsData(): array
    {
        return $this->dataProcessor->fetchRows();
    }

    // ========================================================================
    // ACCESSORS
    // ========================================================================

    public function getTableId(): string
    {
        return $this->context->getTableId();
    }

    public function getQuery(): Query
    {
        return $this->context->getQuery();
    }

    public function getRequest(): array
    {
        return $this->context->getRequest();
    }

    public function getModel(): AbstractModel
    {
        return $this->context->getModel();
    }

    public function getFilters(): array
    {
        return $this->context->getFilters();
    }

    public function getActions(): array
    {
        return $this->actions->getRowActions();
    }

    public function getBulkActions(): array
    {
        return $this->actions->getBulkActions();
    }

    public function getPage(): string
    {
        return $this->context->getPage();
    }

    public function getSql(): string
    {
        return $this->context->getQuery()->getSql();
    }

    public function isInsideRequest(): bool
    {
        return isset($_REQUEST['is-inside-request']);
    }

    public function getFunctionsResults(): ?array
    {
        return $this->actions->getFunctionResults();
    }

    public function getLoadedExtension(string $name): ?object
    {
        return $this->loaded_extensions[$name] ?? null;
    }

    // ========================================================================
    // CONFIGURATION METHODS
    // ========================================================================

    public function setPage(string $page): static
    {
        $this->context->setPage($page);
        return $this;
    }

    public function activeFetch(): static
    {
        $this->context->setFetchMode(true);
        return $this;
    }

    public function setRequestAction(string $action): static
    {
        $this->context->setRequestAction($action);
        return $this;
    }

    /**
     * Set custom data array that will be passed to the table as data-custom attribute
     *
     * @param array $data Array of custom data
     * @return static For method chaining
     *
     * @example ->setCustomData(['post_id' => 123, 'status' => 'active'])
     */
    public function setCustomData(array $data): static
    {
        $this->context->setCustomData($data);
        return $this;
    }

    /**
     * Add, modify or remove a single custom data key-value pair
     *
     * @param string $key The key to add/modify/remove
     * @param mixed $value The value to set. If null, removes the key
     * @return static For method chaining
     *
     * @example ->customData('post_id', 123)
     * @example ->customData('post_id', null) // removes the key
     */
    public function customData(string $key, mixed $value): static
    {
        $this->context->addCustomData($key, $value);
        return $this;
    }

    public function extensions(array $extensions): static
    {
        $normalized = $this->normalizeExtensions($extensions);
        $this->extensions = $this->mergeExtensions($this->extensions, $normalized);
        $this->loadExtensions();
        $this->callExtensionHook('configure');

        return $this;
    }

     /**
     * Magic method to render calendar when object is cast to string
     *
     * @return string Complete HTML calendar ready for display
     */
    public function __toString(): string {
        return $this->render()  ?? '';
    }



    /**
     * Reset current field context (for subclasses)
     */
    public function resetFieldContext(): void
    {
        $this->columns->resetCurrentField();
    }

    public function getColumnManager(): ColumnManager
    {
        return $this->columns;
    }

    public function getContext(): BuilderContext
    {
        return $this->context;
    }

    public function getDataProcessor(): DataProcessor
    {
        return $this->dataProcessor;
    }

    // ========================================================================
    // PROTECTED HELPERS
    // ========================================================================

    protected function initializeExtensions(): void
    {
        $this->extensions = $this->normalizeExtensions($this->extensions);
        $this->loadExtensions();
    }

    protected function loadExtensions(): void
    {
        if (empty($this->extensions)) {
            return;
        }

        $this->loaded_extensions = ExtensionLoader::load(
            $this->extensions,
            'GetDataBuilder',
            $this
        );
    }

    protected function callExtensionHook(string $hook, array $params = []): void
    {
        ExtensionLoader::callHook($this->loaded_extensions, $hook, array_merge([$this], $params));
    }

    protected function callExtensionPipeline(string $hook, mixed $data): mixed
    {
        foreach ($this->loaded_extensions as $extension) {
            if (method_exists($extension, $hook)) {
                $data = $extension->$hook($data);
            }
        }

        return $data;
    }

    protected function autoConfigureArrayColumns(): void
    {
        $rules = $this->context->getModel()->getRules();

        foreach ($rules as $key => $rule) {
            if (($rule['type'] ?? null) !== 'array') {
                continue;
            }

            if ($this->columns->hasCustomColumn($key)) {
                continue;
            }

            $formType = $rule['form-type'] ?? null;

            match ($formType) {
                'image' => $this->asImage($key),
                'file' => $this->asFile($key),
                default => $this->columns->hide($key)
            };
        }
    }
}
