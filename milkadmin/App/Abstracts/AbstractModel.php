<?php
namespace App\Abstracts;

use App\Database\{SQLite, MySql, ResultInterface};
use App\Get;
use App\Abstracts\Traits\{QueryBuilderTrait, CrudOperationsTrait, SchemaAndValidationTrait, DataFormattingTrait, RelationshipsTrait, CollectionTrait, CascadeSaveTrait, RelationshipDataHandlerTrait};
use App\Attributes\{GetFormattedValue, BeforeSave, GetRawValue, SetValue, Validate};
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
     * @var null|MySql|SQLite
     */
    protected null|MySql|SQLite $db = null;

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
     * Current index in the result set (for navigation)
     * @var int
     */
    protected int $current_index = 0;

    /**
     * l'Array di record 
     * Ogni elemento è un array con:
     * - ___action: null='original', 'edit'='modificato', 'create'='nuovo'
     * - campi del record
     * @var array|null
     */
    protected ?array $records_array = null;

    /**
     * Array delle primary key dei record da eliminare
     * @var array
     */
    protected array $deleted_primary_keys = [];

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
        // Call configure() method for internal configuration
        $this->configure($this->rule_builder);
        $this->table = $this->rule_builder->getTable() ??  $this->table;
        $this->db_type = $this->rule_builder->getDbType() ?? $this->db_type;
        $this->primary_key = $this->rule_builder->getPrimaryKey() ?? $this->primary_key;
      
        // Handle database parameter
        if($this->db_type != '' && $this->db_type == 'db2') {
            $this->db = Get::db2();
        } else {
             $this->db = Get::db();
        }


        // Se primary_key non è impostato, usa 'id' come default
        if ($this->primary_key === '') {
            // @Todo ??
            $this->primary_key = 'id';
        }

        // Scan and cache methods with attributes
        $this->scanAndCacheAttributeMethods();
    }


    /**
     * Configuration method to be implemented by child classes
     * This method should define the model's structure and fields
     *
     * @return void
     */
    protected function configure($rule_builder): void
    {
        // To be overridden by child classes
    }

   
    /**
     * Scan Model methods for attributes and cache them
     * Scans every time a new instance is created
     *
     * @return void
     */
    protected function scanAndCacheAttributeMethods(): void
    {
        $class_name = static::class;

        // Get reflection of the Model class
        $reflection = new ReflectionClass($class_name);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip methods inherited from AbstractModel
            if ($method->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            // Check for GetFormattedValue attribute #[GetFormattedValue(field_name)]
            $attributes = $method->getAttributes(GetFormattedValue::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $this->registerMethodHandler($instance->field_name, 'get_formatted', $method->getName());
            }

            // Check for BeforeSave attribute #[BeforeSave(field_name)]
            $attributes = $method->getAttributes(BeforeSave::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $this->registerMethodHandler($instance->field_name, 'get_sql', $method->getName());
            }

            // Check for SetValue attribute #[SetValue(field_name)]
            $attributes = $method->getAttributes(SetValue::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $this->registerMethodHandler($instance->field_name, 'set_value', $method->getName());
            }

            // Check for Validate attribute #[Validate(field_name)]
            $attributes = $method->getAttributes(Validate::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $this->registerMethodHandler($instance->field_name, 'validate', $method->getName());
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
        $this->records_array = [];
        $counter = 0;
        if (is_array($result) && count($result) > 0) {
            foreach ($result as $row) {
                $data = $this->filterDataByRules($row);
                $data['___action'] = null; // null = originale, non modificato
                $this->records_array[$counter] = $data;
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
        $this->records_array = [];
        $data = $this->filterDataByRules($data);
        $data['___action'] = null; // null = originale, non modificato
        $this->records_array[] = $data;
        $this->current_index = array_key_last($this->records_array);
        $this->cleanEmptyRecords();
        $this->invalidateKeysCache();
    }

    /**
     * Add a single record new or update from an associative array
     * Used for prepare record from an array or an object without cycling through the model 
     *
     * @param array $data Record data
     * @return int
     */
    public function fill(array|object|null $data=null): int {
        $this->error = false;
        $this->last_error = '';
        // Extract relationship data before filtering
        if (is_array($data) && method_exists($this, 'extractRelationshipData')) {
            $data = $this->extractRelationshipData($data);
        }

        $data = $this->filterDataByRules($data);

        $data['___action'] = 'insert';
        if ($this->primary_key != null) {
            if (isset($data[$this->primary_key]) && $data[$this->primary_key] != 0 && $data[$this->primary_key] != '') {
                $row = $this->db->getRow('SELECT * FROM '. $this->db->qn($this->table).' WHERE '. $this->db->qn($this->primary_key).' = ?', [$data[$this->primary_key]]);
                if ($row != null) {
                    $data['___action'] = 'edit';
                    // Merge existing data with new data (new data takes precedence)
                    // This ensures that fields not provided in $data retain their existing values
                    $existing_data = (array)$row;
                    $data = array_merge($existing_data, $data);
                }
            }
        }
        $this->cleanEmptyRecords();
        $this->records_array[] = $data;
        $this->current_index = array_key_last($this->records_array);
        $this->invalidateKeysCache();

        return $this->current_index;
    }

    protected function cleanEmptyRecords(): void {
        if ($this->records_array == null) {
            return;
        }
        foreach ($this->records_array as $key => $value) {
            $count_params = 0;
            foreach ($value as $k => $_) {
                if ($k != '___action') {
                    $count_params++;
                }
            }
            if ($count_params == 0) {
                unset($this->records_array[$key]);
            }
        }
    }

    /**
     * Filter data by rules
     * If data is an array with a single element, it is extracted
     *
     * @param array|object|null $data Data to filter
     * @return array Filtered data
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

        // Add fields defined in rules
        foreach ($rules as $key => $_) {
            if (array_key_exists($key, $data)) {
                $new_data[$key] = $this->setValueWithConversion($key, $data[$key], true);
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
        if (!isset($this->records_array[$this->current_index])) {
            return false;
        }

        $record = $this->records_array[$this->current_index];

        // Se è un record originale o modificato (non 'create'), aggiungi la primary key ai deleted
        if ($record['___action'] !== 'create' && isset($record[$this->primary_key])) {
            $this->deleted_primary_keys[] = $record[$this->primary_key];
        }

        // Rimuovi dall'array (l'indice rimane vuoto per stabilità)
        unset($this->records_array[$this->current_index]);
        $this->invalidateKeysCache();
        return true;
    }

    public function isEmpty() {
        if ($this->records_array == null) {
            return true;
        }
        foreach ($this->records_array as $record) {
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

}