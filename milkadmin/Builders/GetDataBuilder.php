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

    protected ?array $cached_data = null;
    // If the query fails and config debug is true, this message will be displayed
    protected ?string $customErrorMessage = null;

    public function __construct(AbstractModel|string $model, string $table_id, ?array $request = null)
    {
        $model = self::normalizeModel($model);

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
    public static function create(AbstractModel|string $model, string $table_id, ?array $request = null): static
    {
        return new static($model, $table_id, $request);
    }

    /**
     * Normalize model input to an AbstractModel instance.
     */
    private static function normalizeModel(AbstractModel|string $model): AbstractModel
    {
        if ($model instanceof AbstractModel) {
            return $model;
        }

        if (is_string($model)) {
            if (!class_exists($model)) {
                throw new BuilderException("Model class '{$model}' not found.");
            }

            $instance = new $model();
            if (!$instance instanceof AbstractModel) {
                throw new BuilderException("Model '{$model}' must extend App\\Abstracts\\AbstractModel.");
            }

            return $instance;
        }

        $type = is_object($model) ? get_class($model) : gettype($model);
        throw new BuilderException("Model must be an instance of AbstractModel or a class name string; received {$type}.");
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
        // Return cached data if available
        if ($this->cached_data !== null) {
            return $this->cached_data;
        }

        $this->callExtensionHook('beforeGetData');

        $data = $this->dataProcessor->process(
            $this->actions->getRowActions(),
            $this->actions->getBulkActions()
        );
      
        $this->cached_data = $this->callExtensionPipeline('afterGetData', $data);

        return $this->cached_data;
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

    public function getRowsData()
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

    public function toSql(): string
    {
        return $this->context->getQuery()->toSql();
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

        // IMPORTANT: Pass the MODEL as target, not $this (the builder)
        // This ensures extensions are loaded from the Module directory, not the Builder directory
        $this->loaded_extensions = ExtensionLoader::load(
            $this->extensions,
            'GetDataBuilder',
            $this->model  // <-- Changed from $this to $this->model
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

    /**
     * Check if there's a database error
     */
    public function hasError(): bool
    {
        return $this->dataProcessor->hasError();
    }

    /**
     * Get error message based on debug mode
     *
     * @param string|null $customMessage Custom message to show in production mode (when debug=false)
     * @return string Error message
     */
    public function getErrorMessage(?string $customMessage = null): string
    {
        if (!$this->hasError()) {
            return '';
        }

        $error = $this->dataProcessor->getError();
        $isDebug = Config::get('debug', false);

        if ($isDebug) {
            // In debug mode, show full error details with filtered stack trace
            $message = $error->getMessage() . "\n\n";
            $message .= "File: " . $error->getFile() . "\n";
            $message .= "Line: " . $error->getLine() . "\n\n";
            $message .= "Module Stack trace:\n" . $this->getFilteredStackTrace($error);
            return $message;
        }

        // In production mode, show generic or custom message
        return $customMessage ?? 'Si è verificato un errore durante il caricamento dei dati. Contattare l\'amministratore di sistema.';
    }

    /**
     * Filter stack trace to show only Modules files
     *
     * @param \Throwable $error
     * @return string Filtered stack trace
     */
    private function getFilteredStackTrace(\Throwable $error): string
    {
        $trace = $error->getTrace();
        $filteredLines = [];
        $index = 0;

        foreach ($trace as $item) {
            $file = $item['file'] ?? '';

            // Only include files from Modules directories
            if (strpos($file, '/Modules/') !== false) {
                $function = $item['function'] ?? '';
                $class = $item['class'] ?? '';
                $type = $item['type'] ?? '';
                $line = $item['line'] ?? '';

                $functionCall = $class ? "{$class}{$type}{$function}()" : "{$function}()";
                $filteredLines[] = "#{$index} {$file}({$line}): {$functionCall}";
                $index++;
            }
        }

        return !empty($filteredLines) ? implode("\n", $filteredLines) : "No module files in stack trace";
    }

    /**
     * Generate error alert HTML
     *
     * @param string|null $customMessage Custom message to show in production mode
     * @return string HTML alert string
     */
    protected function getErrorAlertHtml(?string $customMessage = null): string
    {
        if (!$this->hasError()) {
            return '';
        }

        $message = $this->getErrorMessage($customMessage);
        $isDebug = Config::get('debug', false);
        $alertType = $isDebug ? 'danger' : 'warning';

        $html = '<div class="alert alert-' . $alertType . '" role="alert">';
        $html .= '<strong>' . ($isDebug ? 'Database Error:' : 'Errore:') . '</strong><br>';

        if ($isDebug) {
            // In debug mode, use <pre> for better formatting of stack trace
            $html .= '<pre style="white-space: pre-wrap; font-size: 0.85em; margin-top: 10px;">';
            $html .= _rh($message);
            $html .= '</pre>';
        } else {
            // In production mode, simple text
            $html .= _rh($message);
        }

        $html .= '</div>';

        return $html;
    }
}
