<?php
namespace App\Abstracts;

use App\Database\{ArrayDb, MySql, Query, SQLite};
use App\{Get, ExtensionLoader};
use App\Abstracts\Services\AbstractModel\{ExpressionParameterNormalizerService, ModelBootstrapService, ModelHandlerService, ModelRecordService, ModelScopeService, ModelTimezoneService, ModelUtilityService, QueryBuilderService, RelationshipDataService, RelationshipDefinitionService, RelationshipRuntimeService, WithCountScopeService};
use App\Abstracts\Traits\{CascadeSaveTrait, CollectionTrait, CrudOperationsTrait, DataFormattingTrait, RecordStateTrait, SchemaAndValidationTrait};

!defined('MILK_DIR') && die();

/** Base model with internal configuration, query orchestration and record state support. */
/**
 * @implements \ArrayAccess<int|string, mixed>
 * @implements \Iterator<int|string, mixed>
 * @phpstan-consistent-constructor
 */
abstract class AbstractModel implements \ArrayAccess, \Iterator, \Countable
{
    use CrudOperationsTrait;
    use SchemaAndValidationTrait;
    use DataFormattingTrait;
    use CollectionTrait;
    use CascadeSaveTrait;
    use RecordStateTrait;

    /**
     * Instance cache for methods with attributes defined in Models
     * Structure: [field_name => [type => callable]]
     * @var array
     */
    protected array $method_handlers = [];

    /**
     * Database table name
     * @var string
     */
    protected string $table = '';

    /**
     * Primary key name
     * @var string
     */
    protected string $primary_key = '';

    /**
     * Database connection instance
     * @var null|MySql|SQLite|ArrayDb
     */
    protected null|MySql|SQLite|ArrayDb $db = null;

    /**
     * Database connection type || db or db2
     * @var string
     */
    protected string $db_type = '';

    /**
     * Last error message
     * @var string
     */
    public string $last_error = '';

    /**
     * Error flag
     * @var bool
     */
    protected bool $error = false;

    /**
     * Flag to indicate if this instance is a data container (created from query results)
     * @var bool
     */
    protected bool $is_data_instance = false;

    /**
     * RuleBuilder instance for Object field configuration
     * @var RuleBuilder|null
     */
    private ?RuleBuilder $rule_builder = null;

    /**
     * List of extension names to load for this model
     * Format: ['ExtensionName' => ['param1' => 'value1', 'param2' => 'value2']]
     * or simple: ['ExtensionName'] (will be normalized to ['ExtensionName' => []])
     * @var array
     */
    protected array $extensions = [];

    /**
     * Loaded extension instances
     * @var array
     */
    protected array $loaded_extensions = [];

    /**
     * Current index in the result set (for navigation)
     * @var int
     */
    protected int $current_index = 0;

    /**
     * Cached current row used by result-set style operations.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $cached_row = null;

    /**
     * Array di record con Model objects per le relazioni
     * Ogni elemento è un array con:
     * - ___action: null='original', 'edit'='modificato', 'create'='nuovo'
     * - campi del record (possono essere valori scalari o Model instances)
     * @var array|null
     */
    protected ?array $records_objects = null;

    /**
     * Tracks records created client-side and not yet persisted.
     * These records can start with defaults without becoming dirty.
     *
     * @var array<int, bool>
     */
    protected array $new_record_indexes = [];

    /**
     * Internal per-record state used by the save refactoring foundation.
     *
     * `records_objects` remains the source of truth for current values.
     * This array only stores tracking metadata keyed by record index.
     *
     * @var array<int, array{
     *     originalData: array<string, mixed>,
     *     dirtyFields: array<string, true>,
     *     staleFields: array<string, true>,
     *     isHydrating: bool
     * }>
     */
    protected array $record_states = [];

    /**
     * Profondità di annidamento per evitare cicli infiniti nelle relazioni
     * Parte da 1, incrementa ad ogni livello di relazione
     * Blocca a 5 per prevenire ricorsione infinita
     * @var int
     */
    protected int $depth = 1;

    /**
     * Array delle primary key dei record da eliminare
     * @var array
     */
    protected array $deleted_primary_keys = [];

    /**
     * Default query scopes - applied automatically to all SELECT queries
     * Format: ['scope_name' => callable]
     * @var array
     */
    protected array $default_queries = [];

    /**
     * Named query scopes - can be applied on-demand with withQuery()
     * Format: ['query_name' => callable]
     * @var array
     */
    protected array $named_queries = [];

    /**
     * Disabled global scopes (persistent until re-enabled)
     * @var array
     */
    protected array $disabled_scopes = [];

    /**
     * Active named queries to apply to the next query only (temporary)
     * @var array
     */
    protected array $active_named_queries = [];

    /** @var array<int, string> */
    protected array $include_relationships = [];

    /**
     * Track meta values that have been modified and need saving.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $dirty_meta = [];

    protected bool $meta_loaded = false;

    /**
     * Filter functions registered for list/query filtering.
     *
     * @var array<string, callable>
     */
    protected array $fn_filter = [];

    /**
     * Current query instance reused across chained calls.
     */
    protected ?Query $current_query = null;

    /**
     * Temporary storage for relationship data during fill()
     * @var array|null
     */
    protected ?array $_temp_relationship_data = null;

    /**
     * 
     */
    protected ?array $get_query_columns = null;

    /*
        * Field differences after schema modification
        * @var array
        */
    protected array $schema_field_differences = [];

    /**
     * Last stored record ID
     * @var int|null
     */
    protected $last_stored_record_id = null;

    /**
     * User timezone for date conversions
     * @var string|null
     */
    protected ?string $user_timezone = null;

    /**
     * Flag to track if dates in records_objects are currently in user timezone or UTC
     * false = dates are in UTC (default when loaded from database)
     * true = dates have been converted to user timezone
     * @var bool
     */
    protected bool $dates_in_user_timezone = false;

    /**
     * Internal flag used to bypass action tracking during framework-driven initialization.
     *
     * @var bool
     */
    protected bool $suspend_action_tracking = false;

    private ?RelationshipDefinitionService $relationship_definition_service = null;
    private ?RelationshipRuntimeService $relationship_runtime_service = null;
    private ?WithCountScopeService $with_count_scope_service = null;
    private ?ExpressionParameterNormalizerService $expression_parameter_normalizer_service = null;
    /** @var array<class-string, object> */
    private array $service_instances = [];

    /**
     * Constructor
     * Applica la configurazione statica se disponibile
     *
     * @param bool $is_data_instance Internal flag to mark this as a data container instance
     */
    public function __construct(bool $is_data_instance = false)
    {
        $this->error = false;
        $this->last_error = '';
        $this->is_data_instance = $is_data_instance;
        $bootstrap = $this->service(ModelBootstrapService::class)->boot(
            $this,
            $this->extensions,
            $this->table,
            $this->db_type,
            $this->primary_key
        );

        $this->rule_builder = $bootstrap['rule_builder'];
        $this->extensions = $bootstrap['extensions'];
        $this->loaded_extensions = $bootstrap['loaded_extensions'];
        $this->table = $bootstrap['table'];
        $this->db_type = $bootstrap['db_type'];
        $this->primary_key = $bootstrap['primary_key'];
        $this->default_queries = $bootstrap['default_queries'];
        $this->named_queries = $bootstrap['named_queries'];

        // Handle database parameter
        if ($this->db_type === 'db2') {
            $this->db = Get::db2();
            $this->db_type = 'db2';
        } elseif ($this->db_type === 'array' || $this->db_type === 'arraydb') {
            $this->db = Get::arrayDb();
            $this->db_type = 'array';
        } else {
            $this->db = Get::db();
            $this->db_type = 'db';
        }


        // Se primary_key non è impostato, usa 'id' come default
        if ($this->primary_key === '') {
            // @Todo ??
            $this->primary_key = 'id';
        }

        foreach ($this->getWithCountScopeService()->getScopes($this) as $scopeName => $config) {
            $this->default_queries[$scopeName] = $config;
        }

        // Call extension hook: after attribute methods scanned
        ExtensionLoader::callHook($this->loaded_extensions, 'onAttributeMethodsScanned', []);
    }

    private function service(string $class): object
    {
        return $this->service_instances[$class] ??= new $class();
    }

    protected function getRelationshipDefinitionService(): RelationshipDefinitionService
    {
        $this->relationship_definition_service ??= new RelationshipDefinitionService();
        return $this->relationship_definition_service;
    }

    protected function getRelationshipRuntimeService(): RelationshipRuntimeService
    {
        $this->relationship_runtime_service ??= new RelationshipRuntimeService();
        return $this->relationship_runtime_service;
    }

    protected function getExpressionParameterNormalizerService(): ExpressionParameterNormalizerService
    {
        $this->expression_parameter_normalizer_service ??= new ExpressionParameterNormalizerService();
        return $this->expression_parameter_normalizer_service;
    }

    protected function getWithCountScopeService(): WithCountScopeService
    {
        $this->with_count_scope_service ??= new WithCountScopeService();
        return $this->with_count_scope_service;
    }

    /**
     * Configuration method to be implemented by child classes
     * This method should define the model's structure and fields
     */
    protected function configure(RuleBuilder $rule_builder): void
    {
        // To be overridden by child classes
    }

    public function registerMethodHandler(string $field_name, string $type, string|callable $method_name): void
    {
        $this->method_handlers = $this->service(ModelHandlerService::class)->register(
            $this->method_handlers,
            $field_name,
            $type,
            is_callable($method_name) ? $method_name : [$this, $method_name]
        );
    }

    public function removeMethodHandler(string $field_name, ?string $type = null): void
    {
        $this->method_handlers = $this->service(ModelHandlerService::class)->remove($this->method_handlers, $field_name, $type);
    }

    public function getMethodHandler(string $field_name, string $type): ?callable
    {
        return $this->service(ModelHandlerService::class)->get($this->method_handlers, $field_name, $type);
    }

    public function hasMethodHandler(string $field_name, string $type): bool
    {
        return $this->service(ModelHandlerService::class)->has($this->method_handlers, $field_name, $type);
    }

    public function getFieldsWithHandlers(string $type): array
    {
        return $this->service(ModelHandlerService::class)->getFieldsWithHandlers($this->method_handlers, $type);
    }

    public function query(): ?Query
    {
        $this->current_query = $this->service(QueryBuilderService::class)->query(
            $this,
            $this->current_query,
            $this->include_relationships,
            fn (Query $query): Query => $this->applyQueryScopes($query)
        );

        return $this->current_query;
    }

    public function newQuery(): ?Query
    {
        $this->current_query = null;
        return $this->query();
    }

    public function where(string $condition, array $params = []): Query
    {
        return $this->service(QueryBuilderService::class)->where($this, $condition, $params);
    }

    public function whereIn(string $field, array $values, string $operator = 'AND'): Query
    {
        return $this->service(QueryBuilderService::class)->whereIn($this, $field, $values, $operator);
    }

    public function whereHas(string $relationAlias, string $condition, array $params = []): Query
    {
        return $this->service(QueryBuilderService::class)->whereHas($this, $relationAlias, $condition, $params);
    }

    public function order(string|array $field = '', string $dir = 'asc'): Query
    {
        return $this->service(QueryBuilderService::class)->order($this, $field, $dir);
    }

    public function select(array|string $fields): Query
    {
        return $this->service(QueryBuilderService::class)->select($this, $fields);
    }

    public function limit(int $start, int $limit = -1): Query
    {
        return $this->service(QueryBuilderService::class)->limit($this, $start, $limit);
    }

    public function getFirst($order_field = '', $order_dir = 'asc'): ?static
    {
        $result = $this->service(QueryBuilderService::class)->getFirst($this, (string) $order_field, (string) $order_dir);
        $this->current_query = null;
        return ($result instanceof static) ? $result : null;
    }

    public function getAll($order_field = '', $order_dir = 'asc'): static|array
    {
        $result = $this->service(QueryBuilderService::class)->getAll($this, (string) $order_field, (string) $order_dir);
        $this->current_query = null;
        return $result;
    }

    public function total(): int
    {
        $total = $this->service(QueryBuilderService::class)->total($this);
        $this->current_query = null;
        return $total;
    }

    public function setQueryParams($request): Query
    {
        return $this->service(QueryBuilderService::class)->setQueryParams(
            $this,
            $request,
            fn ($filters, Query $query): Query => $this->addQueryFromFilters($filters, $query)
        );
    }

    public function filterSearch($search, Query $query): Query
    {
        return $this->service(QueryBuilderService::class)->filterSearch($this, $search, $query);
    }

    public function getSearchableTableStructure(): array
    {
        return $this->getTableStructure();
    }

    public function addFilter($filter_type, $fn): void
    {
        $this->fn_filter[$filter_type] = $fn;
    }

    protected function addQueryFromFilters($request_filters, Query $query): Query
    {
        if ($request_filters != '') {
            $tmp_filters = json_decode($request_filters);
            if (JSON_ERROR_NONE === json_last_error()) {
                foreach ($tmp_filters as $filter) {
                    $filter_type = explode(':', $filter);
                    $filter = implode(':', array_slice($filter_type, 1));
                    $filter_type = $filter_type[0];
                    $sanitizedFilterType = preg_replace('/[^0-9a-zA-Z_]/', '', (string) $filter_type);
                    $standard_fn_filter = 'filter_' . (is_string($sanitizedFilterType) ? $sanitizedFilterType : '');
                    if (isset($this->fn_filter[$filter_type])) {
                        call_user_func($this->fn_filter[$filter_type], $filter, $query);
                    } elseif (method_exists($this, $standard_fn_filter)) {
                        call_user_func([$this, $standard_fn_filter], $filter, $query);
                    }
                }
            }
        }

        return $query;
    }

    public function withoutGlobalScope(string|array $scopes): static
    {
        $this->disabled_scopes = $this->service(ModelScopeService::class)->withoutGlobalScope($this->disabled_scopes, $scopes);
        return $this;
    }

    public function withoutGlobalScopes(): static
    {
        $this->disabled_scopes = $this->service(ModelScopeService::class)->withoutGlobalScopes($this->default_queries);
        return $this;
    }

    public function enableGlobalScope(string $scope): static
    {
        $this->disabled_scopes = $this->service(ModelScopeService::class)->enableGlobalScope($this->disabled_scopes, $scope);
        return $this;
    }

    public function withQuery(string $query_name): static
    {
        $this->active_named_queries = $this->service(ModelScopeService::class)->withQuery(
            $this->named_queries,
            $this->active_named_queries,
            $query_name,
            static::class
        );
        return $this;
    }

    protected function applyQueryScopes(Query $query): Query
    {
        [$query, $this->active_named_queries] = $this->service(ModelScopeService::class)->applyQueryScopes(
            $query,
            $this,
            $this->default_queries,
            $this->disabled_scopes,
            $this->active_named_queries,
            $this->named_queries
        );

        return $query;
    }

    public function getDefaultQueries(): array
    {
        return array_keys($this->default_queries);
    }

    public function getNamedQueries(): array
    {
        return array_keys($this->named_queries);
    }

    public function getDisabledScopes(): array
    {
        return $this->disabled_scopes;
    }

    public function hasDefaultQuery(string $scope_name): bool
    {
        return isset($this->default_queries[$scope_name]);
    }

    public function hasNamedQuery(string $query_name): bool
    {
        return isset($this->named_queries[$query_name]);
    }

    public function clearRelationshipCache(?string $alias = null): void
    {
        [$this->records_objects, $this->meta_loaded] = $this->getRelationshipRuntimeService()->clearRelationshipCache($this, $this->records_objects, $alias);
    }

    public function getIncludeRelationships(): array
    {
        return $this->include_relationships;
    }

    public function with(string|array|null $relations = null): static
    {
        [$this->records_objects, $this->include_relationships] = $this->getRelationshipRuntimeService()->with($this, $this->records_objects, $relations);
        return $this;
    }

    public function withMeta(): static
    {
        [$this->records_objects, $this->meta_loaded] = $this->getRelationshipRuntimeService()->withMeta($this, $this->records_objects, $this->meta_loaded);
        return $this;
    }

    public function setDatesInUserTimezone(bool $in_user_timezone): self
    {
        $this->dates_in_user_timezone = $in_user_timezone;
        return $this;
    }

    public function convertDatesToUserTimezone(): self
    {
        [$this->records_objects, $this->dates_in_user_timezone] = $this->service(ModelTimezoneService::class)
            ->convertToUserTimezone($this, $this->records_objects, $this->dates_in_user_timezone);
        return $this;
    }

    public function convertDatesToUTC(): self
    {
        [$this->records_objects, $this->dates_in_user_timezone] = $this->service(ModelTimezoneService::class)
            ->convertToUtc($this, $this->records_objects, $this->dates_in_user_timezone);
        return $this;
    }

    public function getRelationshipHandlers(string $alias, string $type): array
    {
        return $this->service(ModelHandlerService::class)->getRelationshipHandlers($this->method_handlers, $alias, $type);
    }

    protected function extractRelationshipData(array $data): array
    {
        [$data, $this->dirty_meta, $hasError, $lastError] = $this->service(RelationshipDataService::class)
            ->extractRelationshipData($this, $data, $this->current_index, $this->dirty_meta);

        if ($hasError) {
            $this->error = true;
            $this->last_error = $lastError;
        }

        return $data;
    }

    protected function markMetaDirty(string $alias, mixed $value): void
    {
        $this->dirty_meta = $this->service(RelationshipDataService::class)
            ->markMetaDirty($this->dirty_meta, $this->current_index, $alias, $value);
    }

    public function hasDirtyMeta(?int $index = null): bool
    {
        $index ??= $this->current_index;
        return $this->service(RelationshipDataService::class)->hasDirtyMeta($this->dirty_meta, $index);
    }

    public function getDirtyMeta(?int $index = null): array
    {
        $index ??= $this->current_index;
        return $this->service(RelationshipDataService::class)->getDirtyMeta($this->dirty_meta, $index);
    }

    public function getAllDirtyMeta(): array
    {
        return $this->dirty_meta;
    }

    public function clearDirtyMeta(?int $index = null): void
    {
        $index ??= $this->current_index;
        $this->dirty_meta = $this->service(RelationshipDataService::class)->clearDirtyMeta($this->dirty_meta, $index);
    }

    public function saveMeta(mixed $entity_id, ?int $index = null): bool
    {
        $index ??= $this->current_index;
        [$result, $this->dirty_meta] = $this->service(RelationshipDataService::class)->saveMeta(
            $this->rule_builder,
            $this->db,
            $this->dirty_meta,
            $entity_id,
            $index
        );

        return $result;
    }

    public function deleteMeta(mixed $entity_id): bool
    {
        return $this->service(RelationshipDataService::class)->deleteMeta($this->rule_builder, $this->db, $entity_id);
    }

    public function copyRules($destination_rule): void
    {
        $this->service(ModelUtilityService::class)->copyRules($this, $destination_rule);
    }

    public function registerVirtualTable(string $tableName, ?string $autoIncrementColumn = null): bool
    {
        [$result, $this->error, $this->last_error] = $this->service(ModelUtilityService::class)
            ->registerVirtualTable($this, $tableName, $autoIncrementColumn);

        return $result;
    }

    protected function afterSave(mixed $after_save_data, mixed $save_results): void
    {
        $this->service(ModelUtilityService::class)->afterSave($this, $save_results);
    }

    protected function beforeDelete(mixed $entity_id): bool
    {
        return $this->service(ModelUtilityService::class)->beforeDelete();
    }

    protected function afterDelete(mixed $entity_ids): void
    {
        $this->service(ModelUtilityService::class)->afterDelete($this, $entity_ids);
    }

    /**
     * Get the last error message
     *
     * Returns the last error message that occurred
     *
     * @example
     * ```php
     * echo $this->model->getLastError();
     * ```
     *
     * @return string The last error message
     */
    public function getLastError(): string  {
        return $this->last_error;
    }

    /**
     * Check if an error occurred
     *
     * Checks if an error occurred during the last database operation
     *
     * @example
     * ```php
     * if ($this->model->hasError()) {
     *     echo "An error occurred: ".$this->model->getLastError();
     * }
     * ```
     *
     * @return bool True if an error occurred, false otherwise
     */
    public function hasError(): bool
    {
        return $this->error;
    }

    /**
     * Clears the record array and set the results from a ResultInterface object
     *
     * @param array $result Record data
     * @return void
     */
    public function setResults(array $result): void {
        $this->service(ModelRecordService::class)->setResults($this, $result);
    }


    /**
     * Clears the record array and sets the record from an array or object
     *
     * @param array|object|null $data Record data
     * @return void
     */
    public function setRow(array|object|null $data): void {
        $this->service(ModelRecordService::class)->setRow($this, $data);
    }

    /**
     * Add a single record new or update from an associative array
     * Used for prepare record from an array or an object without cycling through the model
     *
     * @param array $data Record data
     * @return static
     */
    public function fill(array|object|null $data=null): static {
        return $this->service(ModelRecordService::class)->fill($this, $data);
    }

    public function applyCalculatedFieldsForAllRecords(): void
    {
        $this->service(ModelRecordService::class)->applyCalculatedFieldsForAllRecords($this);
    }

    /**
     * Apply calculated field expressions for the current record.
     *
     * @return void
     */
    public function applyCalculatedFieldsForCurrentRecord(): void
    {
        $this->service(ModelRecordService::class)->applyCalculatedFieldsForCurrentRecord($this);
    }

    protected function cleanEmptyRecords(): void {
        $this->service(ModelRecordService::class)->cleanEmptyRecords($this);
    }

    public function getNextCurrentIndex(): int {
        return $this->service(ModelRecordService::class)->getNextCurrentIndex($this);
    }

    protected function markRecordAsNew(int $index, bool $is_new = true): void
    {
        $this->service(ModelRecordService::class)->markRecordAsNew($this, $index, $is_new);
    }

    protected function isNewRecordIndex(int $index): bool
    {
        return $this->service(ModelRecordService::class)->isNewRecordIndex($this, $index);
    }

    protected function withoutActionTracking(callable $callback): mixed
    {
        return $this->service(ModelRecordService::class)->withoutActionTracking($this, $callback);
    }

    protected function initializePristineNewRecord(): void
    {
        $this->service(ModelRecordService::class)->initializePristineNewRecord($this);
    }

    protected function applyDefaultValuesForCurrentRecord(): void
    {
        $this->service(ModelRecordService::class)->applyDefaultValuesForCurrentRecord($this);
    }

    /**
     * Filter data by rules without type conversion
     * Only filters which fields are accepted, does not convert values
     * If data is an array with a single element, it is extracted
     *
     * @param array|object|null $data Data to filter
     * @return array Filtered data (values remain unchanged)
     */
    protected function filterData(array|object|null $data=null): array {
        return $this->service(ModelRecordService::class)->filterData($this, $data);
    }

    /**
     * Filter data by rules WITH type conversion
     * Filters which fields are accepted AND converts values to proper types
     * If data is an array with a single element, it is extracted
     *
     * @param array|object|null $data Data to filter
     * @return array Filtered data with converted values
     */
    protected function filterDataByRules(array|object|null $data=null): array {
        return $this->service(ModelRecordService::class)->filterDataByRules($this, $data);
    }
    

    /**
     * Rimuove il record corrente
     * Aggiunge la primary key all'elenco dei record da eliminare
     *
     * @return bool True se il record è stato marcato per l'eliminazione
     */
    public function detach(): bool
    {
        // Verifica che esista il record corrente
        if (!isset($this->records_objects[$this->current_index])) {
            return false;
        }

        $record = $this->records_objects[$this->current_index];

        // Se è un record originale o modificato (non 'create'), aggiungi la primary key ai deleted
        if ($record['___action'] !== 'insert' && isset($record[$this->primary_key])) {
            $this->deleted_primary_keys[] = $record[$this->primary_key];
        }

        // Rimuovi dall'array (l'indice rimane vuoto per stabilità)
        unset($this->new_record_indexes[$this->current_index]);
        unset($this->records_objects[$this->current_index]);
        $this->invalidateKeysCache();
        return true;
    }

    public function isEmpty(): bool
    {
        if ($this->records_objects == null) {
            return true;
        }
        foreach ($this->records_objects as $record) {
            foreach ($record as $key=> $value) {
                if ( $key != '___action' && $value != null) {
                    return false;
                }
           }
        }
        return true;
    }

    public function getRules(string $key = '', mixed $value = true): array {
        $rules = $this->ensureRuleBuilder()->getRules();
        if ($key == '') {
            return $rules;
        }
        $filtered_rules = array_filter($rules, function($item) use ($key, $value) {
            return ($item[$key] ?? '') === $value;
        });
        return $filtered_rules;  
    }

    /**
     * Get rules defined directly in the model configure() method, excluding all extensions.
     *
     * It creates a raw instance without running constructor logic and invokes configure()
     * against a fresh RuleBuilder, so extension hooks are never executed.
     *
     * @return array<string,mixed>
     */
    public function getRulesDefinedInModelConfigureOnly(): array
    {
        try {
            $reflection = new \ReflectionClass($this);
            $rawModel = $reflection->newInstanceWithoutConstructor();
            $builder = new RuleBuilder();

            $configureMethod = $reflection->getMethod('configure');
            $configureMethod->invoke($rawModel, $builder);

            $rules = $builder->getRules();
            return $rules;
        } catch (\Throwable) {
            return [];
        }
    }

    private function ensureRuleBuilder(): RuleBuilder
    {
        if ($this->rule_builder === null) {
            $this->rule_builder = new RuleBuilder();
        }
        return $this->rule_builder;
    }

    public function getRule(string $key = ''): ?array {
        $rules = $this->ensureRuleBuilder()->getRules();
        if (!array_key_exists($key, $rules)) {
            return null;
        }
        return $rules[$key];
    }

    public function setRules(array $rules): void {
        $this->ensureRuleBuilder()->setRules($rules);
    }

    public function getRuleBuilder(): RuleBuilder {
        return $this->ensureRuleBuilder();
    }


    public function getLoadedExtension(string $extension_name): ?object
    {
        return $this->loaded_extensions[$extension_name] ?? NULL;
    }

    public function getDbType(): string {
        return $this->db_type;
    }

    public function qn(string $value): string
    {
        if ($this->db !== null) {
            return (string) $this->db->qn($value);
        }

        return $value;
    }

    /**
     * Set the database type
     *
     * @param string $db_type The database type (db, db2, array)
     * @return void
     */
    public function setDbType(string $db_type): void {
        $this->db_type = $db_type;
        if ($this->db_type === 'db2') {
            $this->db = Get::db2();
        } elseif ($this->db_type === 'array' || $this->db_type === 'arraydb') {
            $this->db = Get::arrayDb();
        } else {
            $this->db = Get::db();
            $this->db_type = 'db';
        }
    }
}
