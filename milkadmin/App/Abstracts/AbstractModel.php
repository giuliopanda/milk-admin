<?php
namespace App\Abstracts;

use App\Database\{ArrayDb, SQLite, MySql, ResultInterface};
use App\{Get, Config, ExtensionLoader, ExpressionParser};
use App\Abstracts\Traits\{QueryBuilderTrait, CrudOperationsTrait, SchemaAndValidationTrait, DataFormattingTrait, RelationshipsTrait, CollectionTrait, CascadeSaveTrait, RelationshipDataHandlerTrait, CopyRulesTrait, ExtensionManagementTrait, ScopeTrait, VirtualTableTrait};
use App\Attributes\{ToDisplayValue, ToDatabaseValue , GetRawValue, SetValue, Validate, DefaultQuery, Query as QueryAttribute};
use ReflectionClass;
use ReflectionMethod;
use ArrayAccess;

!defined('MILK_DIR') && die();

/**
 * Abstract Model Class with Internal Configuration
 *
 * This class has been refactored to use traits for better organization:
 * - QueryBuilderTrait: Query building and execution
 * - CrudOperationsTrait: Create, Read, Update, Delete operations
 * - SchemaAndValidationTrait: Database schema and validation
 * - ResultSetTrait: Result set navigation with ArrayAccess support
 *
 * Query methods return a single Model instance containing ResultInterface with all records.
 * Navigation through records is done using next(), prev(), first(), last(), moveTo() methods.
 * ArrayAccess allows accessing records like an array: $model[0], $model[1], etc.
 *
 * Usage:
 * Child classes should override the configure() method to define their structure:
 *
 * protected function configure($rule): void
 * {
 *     $rule->table('#__appointments')
 *         ->id()
 *         ->string('patient_name', 100)->required()
 *         ->list('doctor_id', (new DoctorsModel())->getList());
 * }
 */
abstract class AbstractModel implements \ArrayAccess, \Iterator, \Countable
{
    use QueryBuilderTrait;
    use CrudOperationsTrait;
    use SchemaAndValidationTrait;
    use DataFormattingTrait;
    use RelationshipsTrait;
    use CollectionTrait;
    use CascadeSaveTrait;
    use RelationshipDataHandlerTrait;
    use CopyRulesTrait;
    use ExtensionManagementTrait;
    use ScopeTrait;
    use VirtualTableTrait;

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
     * Array di record con Model objects per le relazioni
     * Ogni elemento è un array con:
     * - ___action: null='original', 'edit'='modificato', 'create'='nuovo'
     * - campi del record (possono essere valori scalari o Model instances)
     * @var array|null
     */
    protected ?array $records_objects = null;

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
     * Constructor
     * Applica la configurazione statica se disponibile
     *
     * @param mixed $db Optional database instance to use Or string with database connection "db" or "db2"
     * @param bool $is_data_instance Internal flag to mark this as a data container instance
     */
    public function __construct(bool $is_data_instance = false)
    {
        $this->error = false;
        $this->last_error = '';
        $this->is_data_instance = $is_data_instance;

        $this->rule_builder = new RuleBuilder();

        // Normalize extensions to associative format
        $this->extensions = $this->normalizeExtensions($this->extensions);

        // Load extensions defined in property before configure
        $this->loadExtensions();

        // Call configure() method for internal configuration
        $this->configure($this->rule_builder);

        $this->table = $this->rule_builder->getTable() ??  $this->table;
        $this->db_type = $this->rule_builder->getDbType() ?? $this->db_type;
        $this->primary_key = $this->rule_builder->getPrimaryKey() ?? $this->primary_key;

        // Merge extensions from rule_builder with existing extensions
        if ($this->rule_builder->getExtensions() !== null) {
            $new_extensions = $this->rule_builder->getExtensions();
            $original_extensions = $this->extensions;

            // Merge extensions using the new method
            $this->extensions = $this->mergeExtensions($original_extensions, $new_extensions);

            // Reload extensions if new ones were added
            if (count($this->extensions) > count($original_extensions)) {
                $this->loadExtensions();
            }
        }

        // Call configure() on all loaded extensions (always, for every new instance)
        ExtensionLoader::callHook($this->loaded_extensions, 'configure', [$this->rule_builder]);

        

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

        // Scan and cache methods with attributes
        $this->scanAndCacheAttributeMethods();

        // Register withCount scopes from rules
        $this->registerWithCountScopes();

        // Scan rules for withCount definitions and register them as default queries
        foreach ($this->rule_builder as $field_name => $rule) {
            if (isset($rule['withCount']) && is_array($rule['withCount'])) {
                foreach ($rule['withCount'] as $with_count_config) {
                    $alias = $with_count_config['alias'];
                    // Register as a special default query with prefix "withCount:"
                    $this->default_queries['withCount:' . $alias] = $with_count_config;
                }
            }
        }

        // Call extension hook: after attribute methods scanned
        ExtensionLoader::callHook($this->loaded_extensions, 'onAttributeMethodsScanned', []);
    }


    /**
     * Configuration method to be implemented by child classes
     * This method should define the model's structure and fields
     *
     * @return void
     */
    protected function configure(RuleBuilder $rule_builder): void
    {
        // To be overridden by child classes
    }

    /**
     * Load extensions defined in $this->extensions array
     *
     * @return void
     * @throws \Exception If extension is not found
     */
    protected function loadExtensions(): void
    {
        if (empty($this->extensions)) {
            return;
        }

        $this->loaded_extensions = ExtensionLoader::load($this->extensions, 'Model', $this);
    }


    /**
     * Scan Model methods for attributes and cache them
     * Scans every time a new instance is created
     *
     * @return void
     */
    protected function scanAndCacheAttributeMethods(): void
    {
        // Scan the model itself
        $this->scanAttributesFromClass($this);

        // Scan all loaded extensions
        foreach ($this->loaded_extensions as $extension) {
            $this->scanAttributesFromClass($extension);
        }
    }

    /**
     * Scan a specific class (model or extension) for attribute methods
     *
     * @param object $target The object to scan (model or extension instance)
     * @return void
     */
    protected function scanAttributesFromClass(object $target): void
    {
        $reflection = new ReflectionClass($target);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);

        foreach ($methods as $method) {
            // Skip methods inherited from AbstractModel or AbstractModelExtension base classes
            $declaring_class = $method->getDeclaringClass()->getName();
            if ($declaring_class === AbstractModel::class || $declaring_class === AbstractModelExtension::class) {
                continue;
            }

            // Check for ToDisplayValue attribute #[ToDisplayValue(field_name)]
            $attributes = $method->getAttributes(ToDisplayValue::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                // If scanning an extension, register the extension's method
                $callable = ($target !== $this) ? [$target, $method->getName()] : $method->getName();
                $this->registerMethodHandler($instance->field_name, 'get_formatted', $callable);
            }

            // Check for ToDatabaseValue (before save) attribute #[ToDatabaseValue (field_name)]
            $attributes = $method->getAttributes(ToDatabaseValue::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                // If scanning an extension, register the extension's method
                $callable = ($target !== $this) ? [$target, $method->getName()] : $method->getName();
                $this->registerMethodHandler($instance->field_name, 'get_sql', $callable);
            }

            // Check for SetValue attribute #[SetValue(field_name)]
            $attributes = $method->getAttributes(SetValue::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                // If scanning an extension, register the extension's method
                $callable = ($target !== $this) ? [$target, $method->getName()] : $method->getName();
                $this->registerMethodHandler($instance->field_name, 'set_value', $callable);
            }

            // Check for Validate attribute #[Validate(field_name)]
            $attributes = $method->getAttributes(Validate::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                // If scanning an extension, register the extension's method
                $callable = ($target !== $this) ? [$target, $method->getName()] : $method->getName();
                $this->registerMethodHandler($instance->field_name, 'validate', $callable);
            }

            // Check for DefaultQuery attribute #[DefaultQuery]
            $attributes = $method->getAttributes(DefaultQuery::class);
            foreach ($attributes as $attribute) {
                // Use method name as scope name
                $scope_name = $method->getName();

                // For extensions, we need to wrap the protected method in a closure
                // that can access it via reflection
                if ($target !== $this) {
                    // Create a closure that invokes the method via reflection
                    $callable = function($query) use ($method, $target) {
                        return $method->invoke($target, $query);
                    };
                    $this->default_queries[$scope_name] = $callable;
                } else {
                    // For model's own methods, use regular callable
                    $this->default_queries[$scope_name] = [$this, $method->getName()];
                }
            }

            // Check for Query attribute #[Query('name')]
            $attributes = $method->getAttributes(QueryAttribute::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $query_name = $instance->name;

                // For extensions, we need to wrap the protected method in a closure
                // that can access it via reflection
                if ($target !== $this) {
                    // Create a closure that invokes the method via reflection
                    $callable = function($query) use ($method, $target) {
                        return $method->invoke($target, $query);
                    };
                    $this->named_queries[$query_name] = $callable;
                } else {
                    // For model's own methods, use regular callable
                    $this->named_queries[$query_name] = [$this, $method->getName()];
                }
            }
        }
    }

    /**
     * Register withCount definitions from rules as default queries
     * Called from constructor after configure()
     */
    protected function registerWithCountScopes(): void
    {
        $rules = $this->rule_builder->getRules();
        foreach ($rules as $field_name => $rule) {
            if (isset($rule['withCount']) && is_array($rule['withCount'])) {
                foreach ($rule['withCount'] as $with_count_config) {
                    $alias = $with_count_config['alias'];
                    // Register as a special default query with prefix "withCount:"
                    $this->default_queries['withCount:' . $alias] = $with_count_config;
                }
            }
        }
    }

    /**
     * Register a method handler for a specific field and operation type
     *
     * @param string $field_name
     * @param string $type | get_formatted, get_sql set_value
     * @param string|callable $method_name Method name or callable function
     * @return void
     */
    public function registerMethodHandler(string $field_name, string $type, string|callable $method_name): void
    {
        if (!isset($this->method_handlers[$field_name])) {
            $this->method_handlers[$field_name] = [];
        }

        // Store as callable - either [$this, method_name] or the callable directly
        if (is_callable($method_name)) {
            $this->method_handlers[$field_name][$type] = $method_name;
        } else {
            $this->method_handlers[$field_name][$type] = [$this, $method_name];
        }
    }

    /**
     * Remove a method handler for a specific field and operation type
     *
     * @param string $field_name
     * @param string $type | get_formatted, get_sql, set_value
     * @return void
     */
    public function removeMethodHandler(string $field_name, ?string $type = null): void
    {
        if ($type === null) {
            unset($this->method_handlers[$field_name]);
        } else {
            unset($this->method_handlers[$field_name][$type]);
        }
    }

    /**
     * Get registered method handler for a specific field and type
     *
     * @param string $field_name Field name
     * @param string $type Operation type (get_formatted, get_sql,  set_value)
     * @return callable|null
     */
    public function getMethodHandler(string $field_name, string $type): ?callable
    {
        return $this->method_handlers[$field_name][$type] ?? null;
    }

    /**
     * Check if a method handler exists for a specific field and type
     *
     * @param string $field_name Field name
     * @param string $type Operation type
     * @return bool
     */
    public function hasMethodHandler(string $field_name, string $type): bool
    {
        return isset($this->method_handlers[$field_name][$type]);
    }

    /**
     * Get all field names that have registered handlers for a specific type
     *
     * @param string $type Operation type (get_formatted, get_sql, set_value)
     * @return array Array of field names with handlers for the specified type
     */
    public function getFieldsWithHandlers(string $type): array
    {
        $fields = [];

        foreach ($this->method_handlers as $field_name => $handlers) {
            if (isset($handlers[$type])) {
                $fields[] = $field_name;
            }
        }

        return $fields;
    }

   
    /**
     * Set flag to indicate whether dates being loaded/filled are in user timezone or UTC
     *
     * @param bool $in_user_timezone True if dates are in user timezone, false if in UTC
     * @return self
     */
    public function setDatesInUserTimezone(bool $in_user_timezone): self
    {
        $this->dates_in_user_timezone = $in_user_timezone;
        return $this;
    }

  
    /**
     * Convert all datetime fields in records_objects from UTC to user timezone
     * Only converts fields with timezone_conversion = true
     * Modifies DateTime objects in-place for performance
     * Only performs conversion if Config 'use_user_timezone' is true AND dates are currently in UTC
     * Also calls convertDatesToUserTimezone recursively on nested models
     *
     * @return self For method chaining
     */
    public function convertDatesToUserTimezone(): self
    {

        if ($this->dates_in_user_timezone) {
            return $this;
        }

        // Only convert if Config says to use user timezone for forms
        if (!Config::get('use_user_timezone', false)) {
            return $this;
        }


        // No data to convert
        if (empty($this->records_objects)) {
            return $this;
        }

        $user_timezone = Get::userTimezone();
        $timezone_obj = new \DateTimeZone($user_timezone);
        $rules = $this->getRules();

        foreach ($this->records_objects as $index => &$record) {
            foreach ($record as $field_name => $field_value) {
                // Skip ___action field
                if ($field_name === '___action') {
                    continue;
                }

                // If field is a model, call convertDatesToUserTimezone on it
                if ($field_value instanceof AbstractModel) {
                    $field_value->convertDatesToUserTimezone();
                    continue;
                }

                // Check if this field is in rules and is a datetime field
                if (!isset($rules[$field_name])) {
                    continue;
                }

                $rule = $rules[$field_name];

                // Only datetime/date/time fields with timezone_conversion
                if (!in_array($rule['type'], ['datetime', 'date'])) {
                    continue;
                }

                if (!($rule['timezone_conversion'] ?? false)) {
                    continue;
                }

                // Field exists and is a DateTime object?
                if (!is_a($field_value, \DateTime::class)) {
                    continue;
                }

                // Convert in-place (modifies the DateTime object)
                $record[$field_name]->setTimezone($timezone_obj);
            }
        }
        unset($record); // Break the reference

        // Mark dates as converted to user timezone
        $this->dates_in_user_timezone = true;
        return $this;
    }

    /**
     * Convert all datetime fields in records_objects from user timezone to UTC
     * Only converts fields with timezone_conversion = true
     * Modifies DateTime objects in-place for performance
     * Only performs conversion if Config 'use_user_timezone' is true AND dates are in user timezone
     * Also calls convertDatesToUTC recursively on nested models
     *
     * @return self For method chaining
     */
    public function convertDatesToUTC(): self
    {
        // Check if we should convert dates (only if Config says forms use user timezone)
        if (!Config::get('use_user_timezone', false)) {
            return $this;
        }

        // No data to convert
        if (empty($this->records_objects)) {
            return $this;
        }

        $utc_timezone = new \DateTimeZone('UTC');
        $rules = $this->getRules();

        foreach ($this->records_objects as $index => &$record) {
            foreach ($record as $field_name => $field_value) {
                // Skip ___action field
                if ($field_name === '___action') {
                    continue;
                }

                // If field is a model, call convertDatesToUTC on it
                if ($field_value instanceof AbstractModel) {
                    $field_value->convertDatesToUTC();
                    continue;
                }

                // Check if this field is in rules and is a datetime field
                if (!isset($rules[$field_name])) {
                    continue;
                }

                $rule = $rules[$field_name];

                // Only datetime/date/time fields with timezone_conversion
                if (!in_array($rule['type'], ['datetime', 'date', 'time'])) {
                    continue;
                }

                if (!($rule['timezone_conversion'] ?? false)) {
                    continue;
                }

                // Field exists and is a DateTime object?
                if (!is_a($field_value, \DateTime::class)) {
                    continue;
                }

                // Convert to UTC in-place
                $record[$field_name]->setTimezone($utc_timezone);
            }
        }
        unset($record); // Break the reference

        // Mark dates as back in UTC
        $this->dates_in_user_timezone = false;
        return $this;
    }

    /**
     * Get relationship field handlers for a specific relationship alias
     * Returns handlers registered with pattern "alias.fieldname"
     *
     * @param string $alias Relationship alias (e.g., 'doctor')
     * @param string $type Operation type (get_formatted, get_sql, set_value)
     * @return array Array of [field_name => callable] for the relationship
     */
    public function getRelationshipHandlers(string $alias, string $type): array
    {
        $handlers = [];

        $prefix = $alias . '.';
        $prefix_len = strlen($prefix);

        foreach ($this->method_handlers as $field_name => $field_handlers) {
            // Check if field_name starts with "alias."
            if (str_starts_with($field_name, $prefix) && isset($field_handlers[$type])) {
                // Extract the actual field name without the alias prefix
                $actual_field = substr($field_name, $prefix_len);
                $handlers[$actual_field] = $field_handlers[$type];
            }
        }

        return $handlers;
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
        $this->records_objects = [];
        $counter = 0;
        if (is_array($result) && count($result) > 0) {
            foreach ($result as $row) {
                $data = $this->filterDataByRules($row);
                $data['___action'] = null; // null = originale, non modificato
                $this->records_objects[$counter] = $data;
                $this->current_index = $counter;
                $this->applyCalculatedFieldsForCurrentRecord();
                $counter++;
            }
        }
        $this->invalidateKeysCache();
        $this->current_index = 0;
    }


    /**
     * Clears the record array and sets the record from an array or object
     *
     * @param array|object|null $data Record data
     * @return void
     */
    public function setRow(array|object|null $data): void {
        $this->records_objects = [];
        $this->dates_in_user_timezone = false;
        $data = $this->filterDataByRules($data);
        $data['___action'] = null; // null = originale, non modificato

        $this->records_objects[] = $data;

        $this->current_index = array_key_last($this->records_objects);
        $this->applyCalculatedFieldsForCurrentRecord();
        $this->cleanEmptyRecords();
        $this->invalidateKeysCache();
    }

    /**
     * Add a single record new or update from an associative array
     * Used for prepare record from an array or an object without cycling through the model
     *
     * @param array $data Record data
     * @return static
     */
    public function fill(array|object|null $data=null): static {

        $this->error = false;
        $this->last_error = '';
        // Extract relationship data before filtering
        if (is_array($data) && method_exists($this, 'extractRelationshipData')) {
            $data = $this->extractRelationshipData($data);
        }

        $data = $this->filterData($data);



        if ($this->primary_key != null && ($this->primary_key != 0)) {
            if (isset($data[$this->primary_key]) && $data[$this->primary_key] !== 0 && $data[$this->primary_key] !== '') {
                // verifico se già esiste tra i $this->records_objects
                $this->dates_in_user_timezone = true;
                if (is_array($this->records_objects)) {
                    foreach ($this->records_objects as $key => $value) {
                        if ($value[$this->primary_key] == $data[$this->primary_key]) {
                            $this->records_objects[$this->current_index]['___action'] = 'edit';
                            // Merge existing data with new data (new data takes precedence)
                            // This ensures that fields not provided in $data retain their existing values
                            $this->current_index = $key;
                            foreach ($data as $key => $value) {
                                // Skip model instances - cannot set models in fill
                                if ($value instanceof AbstractModel) {
                                    continue;
                                }
                                $this->setValueWithConversion($key, $value);
                            }
                            $this->applyCalculatedFieldsForCurrentRecord();
                            return $this;
                        }
                    }
                }
                $this->dates_in_user_timezone = false;
                $row = $this->db->getRow('SELECT * FROM '. $this->db->qn($this->table).' WHERE '. $this->db->qn($this->primary_key).' = ?', [$data[$this->primary_key]]);

                if ($row != null) {
                    $this->current_index = $this->getNextCurrentIndex();

                    $this->dates_in_user_timezone = false;
                    foreach ($row as $key => $value) {
                        $this->setValueWithConversion($key, $value);
                    }
                      $this->records_objects[$this->current_index]['___action'] = 'edit';
                    // Merge existing data with new data (new data takes precedence)
                    // This ensures that fields not provided in $data retain their existing values
                    $this->dates_in_user_timezone = true;
                    foreach ($data as $key => $value) {
                        // Skip model instances - cannot set models in fill
                        if ($value instanceof AbstractModel) {
                            continue;
                        }
                        $this->setValueWithConversion($key, $value);
                    }
                } else {
                    // è nuovo anche se c'è id? Questo codice va bene?
                     $this->cleanEmptyRecords();
                    $this->current_index = $this->getNextCurrentIndex();
                      $this->dates_in_user_timezone = true;

                    foreach ($data as $key => $value) {
                        // Skip model instances - cannot set models in fill
                        if ($value instanceof AbstractModel) {
                            continue;
                        }
                        $this->setValueWithConversion($key, $value);
                    }
                     $this->records_objects[$this->current_index]['___action'] = 'insert';
                    $this->invalidateKeysCache();
                }
                $this->applyCalculatedFieldsForCurrentRecord();
                return $this;
            }
        }

        $this->cleanEmptyRecords();
        $this->current_index = $this->getNextCurrentIndex();
        $this->records_objects[$this->current_index]['___action'] = 'insert';
        $this->dates_in_user_timezone = true;
        foreach ($data as $key => $value) {
            // Skip model instances - cannot set models in fill
            if ($value instanceof AbstractModel) {
                continue;
            }
            $this->setValueWithConversion($key, $value);
        }

        $this->invalidateKeysCache();
        $this->applyCalculatedFieldsForCurrentRecord();

        return $this;
    }

    public function applyCalculatedFieldsForAllRecords(): void
    {
        foreach ($this->records_objects as $index => $_) {
            $this->current_index = $index;
            $this->applyCalculatedFieldsForCurrentRecord();
        }
    }

    /**
     * Apply calculated field expressions for the current record.
     *
     * @return void
     */
    public function applyCalculatedFieldsForCurrentRecord(): void
    {
        if (!isset($this->records_objects[$this->current_index]) || !is_array($this->records_objects[$this->current_index])) {
            return;
        }

        $rules = $this->getRules();
        $record = $this->records_objects[$this->current_index];
        $parser = null;

        foreach ($rules as $field_name => $rule) {
            $expression = $rule['calc_expr'] ?? null;
            if (!is_string($expression) || trim($expression) === '') {
                continue;
            }
            if ($parser === null) {
                $parser = new ExpressionParser();
            }
            try {
                $parser->setParameters($record);
                $result = $parser->execute($expression);
            } catch (\Throwable $e) {
                continue;
            }
            if (($rule['form-type'] ?? null) === 'checkbox') {
                // NOTE: Manteniamo boolean per coerenza con JS (milk-form.js).
                $result = $parser->normalizeCheckboxValue($result);
            }
            $this->setValueWithConversion($field_name, $result);
            $record = $this->records_objects[$this->current_index] ?? $record;
        }
    }

    protected function cleanEmptyRecords(): void {
        if ($this->records_objects == null) {
            return;
        }
        foreach ($this->records_objects as $key => $value) {
            $count_params = 0;
            foreach ($value as $k => $v) {
                if ($k != '___action') {
                    // If value is a model, call cleanEmptyRecords on it
                    if ($v instanceof AbstractModel) {
                        $v->cleanEmptyRecords();
                    }
                    $count_params++;
                }
            }
            if ($count_params == 0) {
                unset($this->records_objects[$key]);
            }
        }
    }

    public function getNextCurrentIndex(): int {
        $index = $this->current_index;
        if (!is_array($this->records_objects) || count($this->records_objects) == 0) {
            $index = 0;
        } else {
            $index = array_key_last($this->records_objects);
            do {
                $index++;
            } while (isset($this->records_objects[$index]));
        }
        return $index;
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
        if (is_object($data)) {
            $data = (array)$data;
        }
        if (!is_array($data)) {
            return [];
        }
        // verifico che non ci siano dati annidati
        // [0=>[...]]
        if (count($data) == 1) {
            $first_data = reset($data);
            if (is_array($first_data)) {
                $data = $first_data;
            }
        }
        $new_data = [];
        $rules = $this->getRules();

        // Add fields defined in rules (without conversion)
        foreach ($rules as $key => $_) {
            if (array_key_exists($key, $data)) {
                $new_data[$key] = $data[$key]; // No conversion, just copy
            }
        }

        // Also preserve relationship data (not in rules but valid)
        foreach ($data as $key => $value) {
            // Skip if already added
            if (isset($new_data[$key])) {
                continue;
            }

            // Check if this is a relationship
            if (method_exists($this, 'hasRelationship') && $this->hasRelationship($key)) {
                $new_data[$key] = $value; // Preserve relationship data as-is
            }
        }

        return $new_data;
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
        if (is_object($data)) {
            $data = (array)$data;
        }
        if (!is_array($data)) {
            return [];
        }
        // verifico che non ci siano dati annidati
        // [0=>[...]]
        if (count($data) == 1) {
            $first_data = reset($data);
            if (is_array($first_data)) {
                $data = $first_data;
            }
        }
        $new_data = [];
        $rules = $this->getRules();

        // Add fields defined in rules WITH conversion
        foreach ($rules as $key => $_) {
            if (array_key_exists($key, $data)) {
                $new_data[$key] = $this->getValueWithConversion($key, $data[$key]);
            }
        }

        // Also preserve relationship data (not in rules but valid)
        foreach ($data as $key => $value) {
            // Skip if already added
            if (isset($new_data[$key])) {
                continue;
            }

            // Check if this is a relationship
            if (method_exists($this, 'hasRelationship') && $this->hasRelationship($key)) {
                $new_data[$key] = $value; // Preserve relationship data as-is
            }
        }

        return $new_data;
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
        unset($this->records_objects[$this->current_index]);
        $this->invalidateKeysCache();
        return true;
    }

    public function isEmpty() {
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
        $rules = $this->rule_builder->getRules();
        if ($key == '') {
            return $rules;
        }
        $filtered_rules = array_filter($rules, function($item) use ($key, $value) {
            return ($item[$key] ?? '') === $value;
        });
        return $filtered_rules;  
    }

    public function getRule(string $key = ''): ?array {
        $rules = $this->rule_builder->getRules();
        if (!array_key_exists($key, $rules)) {
            return null;
        }
        return $rules[$key];
    }

    public function setRules(array $rules): void {
        $this->rule_builder->setRules($rules);
    }

    public function getRuleBuilder(): RuleBuilder {
        return $this->rule_builder;
    }


    public function getLoadedExtension($extension_name): ?object {
        return $this->loaded_extensions[$extension_name] ?? NULL;
    }

    public function getDbType(): string {
        return $this->db_type;
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
