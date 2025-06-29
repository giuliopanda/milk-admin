<?php
namespace MilkCore;
use MilkCore\Schema;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Abstract class for object management
 *
 * This class provides a foundation for creating data objects with automatic schema creation,
 * field validation, and customizable getters and setters. It implements the IteratorAggregate
 * interface to allow iteration over object properties.
 *
 * Key features:
 * - Automatic schema creation for database tables
 * - Customizable field definitions with validation rules
 * - Getter and setter methods with formatting options
 * - Conversion between object and array/database formats
 * - Support for field types (string, int, date, array, etc.)
 *
 * @example
 * ```php
 * class  serObject extends AbstractObject
 * {
 *     public function init_rules() {
 *         $this->rule('id', [
 *             'type' => 'int',
 *             'primary' => true
 *         ]);
 *         $this->rule('username', [
 *             'type' => 'string',
 *             'length' => 50,
 *             'unique' => true
 *         ]);
 *         $this->rule('email', [
 *             'type' => 'string',
 *             'length' => 100
 *         ]);
 *         $this->rule('status', [
 *             'type' => 'list',
 *             'options' => [
 *                 'active' => 'Active',
 *                 'inactive' => 'Inactive'
 *             ]
 *         ]);
 *         $this->rule('created_at', [
 *             'type' => 'datetime'
 *         ]);
 *     }
 * }
 *
 * // Create a new user object
 * $user = new UserObject([
 *     'username' => 'johndoe',
 *     'email' => 'john@example.com',
 *     'status' => 'active'
 * ]);
 * ```
 *
 * IMPORTANT: Field names must be all lowercase without spaces or special characters
 *
 * @package     MilkCore
 * @subpackage  Abstracts
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @link        https://github.com/giuliopanda/milk-core
 * @version     1.0.0
 */

abstract class AbstractObject implements \IteratorAggregate {
    /**
     * Object attributes/properties that store the actual data
     *
     * @var array
     */
    protected array $attributes = [];
    
    /**
     * Field definitions and validation rules
     *
     * @var array
     */
    protected array $rules = [];

    /**
     * Whether to allow setting fields that are not defined in rules
     *
     * @var bool $allowUndefinedFields If true, undefined fields can be set on the object
     */
    protected bool $allowUndefinedFields = true;
    /**
     * Constructor
     *
     * Initializes the object by setting up rules and populating attributes
     * from the provided data. If attributes are provided as an object, they
     * will be converted to an array.
     *
     * @param array|object|null $attributes Initial data to populate the object
     */
    public function __construct($attributes = null) {
        $this->init_rules();

        if (!is_null($attributes)) {
            if(is_object($attributes)) {
                $attributes = (array) $attributes;
            }
            foreach ($this->rules as $name => $rule) {
                if (isset($rule['default'])) {
                    $this->attributes[$name] = $rule['default'];
                } else {
                    $this->attributes[$name] = null;
                }
            }
            foreach ($attributes as $name => $value) {
                $this->__set($name, $value);
            }
        }
    }

    /**
     * Allows iteration over the object with foreach or while
     *
     * Implements the IteratorAggregate interface to make the object's
     * attributes iterable.
     *
     * @return \Traversable An iterator for the object's attributes
     */
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->attributes);
    }

    /**
     * Initialize field rules
     *
     * This method must be implemented in child classes to define the rules
     * for each field in the object. These rules are used for validation,
     * schema creation, and form generation.
     *
     * @example
     * ```php
     * public function init_rules() {
     *     $this->rule('id', [
     *         'type' => 'int',
     *         'primary' => true
     *     ]);
     *     $this->rule('title', [
     *         'type' => 'string',
     *         'length' => 100,
     *         'label' => 'Title'
     *     ]);
     * }
     * ```
     */
    public function init_rules() {
        /* Example:
        $this->rule('id', [
            'type' => 'int',
            'primary' => true
        ]);*/
    
    }


    /**
     * Define a rule for a field
     *
     * This method defines the rules for a field, including its type, validation,
     * database schema, form display, and custom getters/setters.
     *
     * @param string $name The field name (must be lowercase without spaces or special characters)
     * @param array $options Configuration options for the field:
     *   - type: Field type (string, text, int, float, bool, date, datetime, time, list, enum, array)
     *   - length: Maximum length for string fields
     *   - precision: Decimal precision for float fields
     *   - nullable: Whether the field can be null
     *   - default: Default value
     *   - primary: Whether this is a primary key
     *   - label: Display label
     *   - options: Options for list/enum fields
     *   - index: Whether to create a database index
     *   - unique: Whether the field must be unique
     *   - list: Whether to show in list views
     *   - edit: Whether to show in edit forms
     *   - view: Whether to show in detail views
     *   - mysql: Whether to create in database
     *   - form-type: Form field type
     *   - form-label: Label for forms
     *   - form-params: Additional form parameters
     *   - _get: Custom getter function
     *   - _get_raw: Custom raw getter function
     *   - _set: Custom setter function
     *   - _edit: Custom edit function
     * @return void
     *
     * @example
     * ```php
     * // Basic string field
     * $this->rule('title', [
     *     'type' => 'string',
     *     'length' => 100,
     *     'label' => 'Title'
     * ]);
     *
     * // Dropdown with options
     * $this->rule('status', [
     *     'type' => 'list',
     *     'options' => [
     *         'active' => 'Active',
     *         'inactive' => 'Inactive'
     *     ]
     * ]);
     * ```
     */
    public function rule(string $name, array $options): void {
        if (_raz($name) != $name) {
            throw new \InvalidArgumentException("The field name ".$name. " must be all lowercase and without spaces or special characters.\n");
        }
        $defaults = [
            'type' => 'string',       // tipo PHP e SQL (primary, text, string, int, float, bool, date, datetime, time, list, enum, array)
            'length' => null,         // lunghezza massima per stringhe
            'precision' => null,      // precisione per i float
            'nullable' => true,      // se può essere null
            'default' => null,        // valore default
            'primary' => false,       // se è chiave primaria
            'label' => $name,         // etichetta per la visualizzazione
            'options' => null,        // opzioni per i list e enum
            'index' => false,         // se deve essere creato un indice nel database
            'unique' => false,        // se deve essere impostato come campo unico nel database
            'default' => null,        // valore di default
            // le visualizzazioni
            'list' => true,   // se deve essere visualizzato nella lista
            'edit' => true,   // se deve essere visualizzato nel form
            'view' => true,   // se deve essere visualizzato nella vista
            'mysql' => true,  // se deve essere creato nel database
            //
            'form-type' => null,   // tipo di campo per il form
            'form-label' => null,  // etichetta per il form
            // sempre per i form
            //   'form-params' => [
            //    'invalid-feedback'=>'Il titolo è obbligatorio',
            //     'required' => true
            // ]
            '_get' => null,  // funzione per il get quando si stampa tramite get_value
            '_get_raw' => null,  // funzione per il get raw quando si richiama il campo sempre (!ATTENZIONE QUESTA NON HA LA FUNZIONE RELATIVA CHE SOSTITUISCE QUESTO COMANDO!)
            '_set' => null,  // funzione per il set
            '_edit' => null,  // funzione per l'edit

        ];
        $options['name'] = $name;
        if ($options['type'] == 'id') {
            $options['type'] = 'int';
            $options['primary'] = true;
            $options['form-type'] = $options['form-type']  ?? 'hidden';
        }
        
        $this->rules[$name] = array_merge($defaults, $options);
    }
    /**
     * Magic method to handle property access
     *
     * Gets the value of a field, applying any custom raw getter function
     * defined in the rules. For array fields, converts from JSON if needed.
     *
     * @param string $name The field name to access
     * @return mixed The field value
     */
    public function __get(string $name) {
        if (!array_key_exists($name, $this->attributes)) {
            return null;
            //  throw new \InvalidArgumentException("La proprietà {$name} non esiste.");
        }  else if (isset($this->rules[$name]['_get_raw'])) {
            $fn = $this->rules[$name]['_get_raw'];
            if (is_callable($fn)) {
                return $fn($this->attributes[$name]);
            }
        } else if ($this->rules[$name]['type'] == 'array') {
            return $this->get_from_json_to_array($name);
        }
        return $this->attributes[$name];
    }


    /**
     * Magic method to check if a property is set
     *
     * @param string $name The field name to check
     * @return bool True if the field exists and is set
     */
    public function __isset($name) {
        return isset($this->attributes[$name]);
    }

    /**
     * Convert JSON string to array
     *
     * Arrays are stored as JSON in the database, so this method
     * converts JSON strings back to PHP arrays when retrieving data.
     *
     * @param string $name The field name
     * @return array|null The converted array or null if conversion fails
     */
    protected function get_from_json_to_array(string $name): ?array {
        $var  = $this->attributes[$name];
          // se è una stringa verifico se è un json
        if (is_string($var)) {
            $json_attr = json_decode($var, true);
        
            if (json_last_error() == JSON_ERROR_NONE) {
                if ($json_attr == '') {
                    return [];
                }
                if (!is_array($json_attr)) {
                    $json_attr = [$json_attr];
                }
                return $json_attr;
            } else {
                if (isset($this->rules[$name]['options'][$var])) {
                    return [$var];
                } else {
                    return null;
                } 
            }
        } else if (is_object($var)) {
            return (array) $var;
        } else {
            return $var;
        }
    }

    /**
     * Get formatted value for display
     *
     * Returns the formatted value of a field, applying any custom getter
     * function defined in the rules. 
     *
     * The method will:
     * 1. Check for a custom _get function in the rules
     * 2. Look for a get_{field_name} method in the class
     * 3. Apply default formatting based on field type (dates, arrays, lists)
     *
     * @param string $name The field name
     * @return mixed The formatted field value
     */
    public function get_value(string $name) {
        if (!array_key_exists($name, $this->attributes)) {
            return null;
        }
       if (isset($this->rules[$name]['_get'])) {
            $fn = $this->rules[$name]['_get'];
            if (is_callable($fn)) {
                return $fn($this->attributes[$name]);
            }
        } else if (method_exists($this, 'get_'._raz($name))) {
            return $this->{'get_'._raz($name)}($this->attributes[$name]);
        } else if (isset($this->rules[$name]) && in_array($this->rules[$name]['type'], ['datetime','date', 'time'])) {
            return Get::format_date($this->attributes[$name], $this->rules[$name]['type']);
        } else if (isset($this->rules[$name]) && $this->rules[$name]['type'] == 'array') {
            // Gestione checkbox multipli
            if (!is_array($this->attributes[$name])) {
                $this->attributes[$name] = $this->get_from_json_to_array($name);
            }
            if (is_array($this->attributes[$name])) {
               // return implode(', ', $this->attributes[$name]);
                $selected_options = [];
                foreach ($this->attributes[$name] as $value) {
                     if (isset($this->rules[$name]['options'][$value])) {
                        $selected_options[] = _r($this->rules[$name]['options'][$value]);
                    }
                }
                return implode(', ', $selected_options);
            } else {
                  return null;
            }
        } else  if (isset($this->rules[$name]) && ($this->rules[$name]['type'] == 'list' || $this->rules[$name]['type'] == 'enum')) {
             if (isset($this->rules[$name]['options'][$this->attributes[$name]])) {
                return _r($this->rules[$name]['options'][$this->attributes[$name]]);
            } else {
                 return $this->attributes[$name];
            }
        }  
        
        return $this->attributes[$name];
    }

    /**
     * Merge additional data into the object
     *
     * Updates the object's attributes with values from the provided data.
     *
     * @param array|object $data Data to merge into the object
     * @return void
     */
    public function merge($data) {
        if ($data) {
            foreach ($data as $key => $value) {
                $this->attributes[$key] = $value;
            }
         }
    }
    
    /**
     * Magic method to handle property assignment
     *
     * Sets the value of a field, with validation if the field is defined
     * in the rules. For array fields, converts from JSON if needed.
     *
     * @param string $name The field name
     * @param mixed $value The value to set
     * @return void
     * @throws \InvalidArgumentException If the field is not defined in rules and allowUndefinedFields is false
     */
    public function __set(string $name, $value): void {
        if (!array_key_exists($name, $this->rules) && !$this->allowUndefinedFields) {
            throw new \InvalidArgumentException("The property {$name} is not defined in the rules.");
        }

        // Gestione null
        if (is_null($value)) {
            $this->attributes[$name] = null;
            return;
        }
        // se il campo è di tipo array e il valore è un json lo converto in array
        if (isset($this->rules[$name]) && $this->rules[$name]['type'] == 'array') {
            if (is_string($value)) {
                $json_attr = json_decode($value, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $value = $json_attr;
                } else {
                    $value = [$value];
                }
            }
        } else if (isset($this->rules[$name]) && $this->rules[$name]['type'] == 'datetime') {
            if (is_a($value, \DateTime::class)) {
                // ok
            } else if (strtotime($value) !== false) {
               $value = new \DateTime($value);
            } else {
                $value = null;
            }
        }  else if (isset($this->rules[$name]) && $this->rules[$name]['type'] == 'date') {
            if (is_a($value, \DateTime::class)) {
                $value = $value;
            } else if (strtotime($value) !== false) {
                $value = new \DateTime($value);
            } else {
                $value = null;
            }
        } else {
            $value = $value;
        }
        $this->attributes[$name] = $value;
    }

    /**
     * Filter rules based on a key and value
     *
     * Useful for getting rules for specific purposes like list views or forms.
     *
     * @param string $key The rule key to filter by (e.g., 'list', 'edit', 'mysql')
     * @param mixed $value The value to match (typically true/false)
     * @return array Filtered rules matching the criteria
     *
     * @example
     * ```php
     * // Get all fields that should be shown in lists
     * $list_fields = $object->get_rules('list', true);
     *
     * // Get all fields that should be stored in MySQL
     * $mysql_fields = $object->get_rules('mysql', true);
     * ```
     */
    public function get_rules($key = '', $value = true) {
        if ($key == '') {
            return $this->rules;
        }
        $filtered_rules = array_filter($this->rules, function($item) use ($key, $value) {
            return ($item[$key] ?? '') === $value;
        });
        return $filtered_rules;  
    }


    public function get_rule($key = '') {
        if (!array_key_exists($key, $this->rules)) {
            return null;
        }
        return $this->rules[$key];
    }

    /**
     * Filter data based on field rules
     *
     * Returns only the data fields that match the specified rule criteria.
     *
     * @param string $key The rule key to filter by
     * @param mixed $value The value to match
     * @param array|null $data The data to filter (defaults to object attributes)
     * @return array Filtered data
     *
     * @example
     * ```php
     * // Get only the data for fields that should be stored in MySQL
     * $mysql_data = $object->filter_data_by_rules('mysql', true);
     * ```
     */
    function filter_data_by_rules(string $key, mixed $value = true, ?array $data = null): array {
        if ($data == null) {
            $data = $this->attributes;
        }
        $accepted_values = array_keys($this->get_rules($key));
        foreach ($data as $key => $_) {
            if (!in_array($key, $accepted_values)) {
                unset($data[$key]);
            }
        }
        return $data;
    }


    /**
     * Convert the object to an array
     *
     * Returns all object attributes as an associative array.
     *
     * @return array The object's attributes as an array
     */
    public function to_array(): array {
        return $this->attributes;
    }

    /**
     * Convert the object to an array for MySQL storage
     *
     * Prepares the object data for database storage by:
     * 1. Applying any custom _set functions
     * 2. Converting arrays to JSON strings
     * 3. Only including fields marked for MySQL storage
     *
     * @return array The prepared data for database storage
     */
    public function to_mysql_array(): array {
        $data = [];
        foreach ($this->rules as $name => $rule) {
            if ($rule['mysql']) {
                if (isset($this->rules[$name]['_set'])) {
                    $fn = $this->rules[$name]['_set'];
                    if (is_callable($fn)) {
                        $data[$name] = $fn(($this->attributes[$name] ?? null), true);
                    }
                } else if (method_exists($this, 'set_'._raz($name))) {
                    $data[$name] =  $this->{'set_'._raz($name)}(($this->attributes[$name] ?? null), true);
                } else if ($rule['type'] == 'array') {
                    $data[$name] = json_encode(($this->attributes[$name] ?? []));
                } else if ($rule['type'] == 'datetime') {
                    if (is_a($this->attributes[$name], \DateTime::class)) {
                        $data[$name] = $this->attributes[$name]->format('Y-m-d H:i:s');
                    } else if ($this->attributes[$name] != null) {
                        // Verifica se è una data valida
                        if (strtotime($this->attributes[$name]) !== false) {
                            $data[$name] = $this->attributes[$name];
                        } 
                    }
                }  else if ($rule['type'] == 'date') {
                    if (is_a($this->attributes[$name], \DateTime::class)) {
                        $data[$name] = $this->attributes[$name]->format('Y-m-d');
                    } else if ($this->attributes[$name] != null) {
                        // Verifica se è una data valida
                        if (strtotime($this->attributes[$name]) !== false) {
                            $data[$name] = $this->attributes[$name];
                        } 
                    }
                } else {
                    $data[$name] = $this->attributes[$name];
                }  
            
                if (isset($data[$name]) && is_a( $data[$name], \DateTime::class)) {
                    $data[$name] = $data[$name]->format('Y-m-d H:i:s');
                } else if (isset($data[$name]) && (is_object($data[$name]) || is_array($data[$name]))) {
                    $data[$name] = json_encode($data[$name]);
                }
            }
        }

        return $data;
    }

    /**
     * Check if a property exists in the attributes
     *
     * This method is used instead of PHP's property_exists() because
     * we need to check if the property exists in the attributes array.
     *
     * @param string $name The property name to check
     * @return bool True if the property exists in attributes
     */
    public function property_exists($name) {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Get all primary key fields
     *
     * Returns an array with the names of all fields marked as primary keys.
     * Note: Multiple primary keys are supported for schema creation but
     * not fully supported in the model!
     *
     * @return array List of primary key field names
     */
    public function get_primaries(): array {
        $primaries = [];
        foreach ($this->rules as $name => $rule) {
            if ($rule['primary']) {
                $primaries[] = $name;
            }
        }
        return $primaries;
    }

    /**
     * Get the primary key field name
     *
     * Returns the name of the primary key field if there is exactly one,
     * otherwise returns null.
     *
     * @return string|null The primary key field name or null if none or multiple
     */
    public function get_primary_key() {
        $primaries = $this->get_primaries();
        if (count($primaries) == 1) {
            return $primaries[0];
        }
        return null;
    }

    /**
     * Generate database schema for the object
     *
     * Creates a Schema object based on the field rules defined in the object.
     * This is used for automatic table creation and migration.
     *
     * @param string $table The table name
     * @return Schema The generated schema object
     *
     * @example
     * ```php
     * $schema = $object->get_schema('users');
     * $mysql->create_table($schema);
     * ```
     */
    public function get_schema($table, $db = null): SchemaMysql|SchemaSqlite {
        $schema = Get::schema($table, $db);
        $primaries = $this->get_primaries();
        if (count ($primaries) == 1 && $this->rules[$primaries[0]]['type'] == 'int') {
            $schema->id($primaries[0]);
        } 

        foreach ($this->rules as $name => $rule) {
            if (in_array($name, $primaries) && count($primaries) == 1) {
                continue;
            }
            if (!$rule['mysql'])  continue;
            
            switch ($rule['type']) {
                case 'id':
                    $schema->id($name);
                    break;
                case 'text':
                    $schema->text($name, $rule['nullable'], $rule['default']);
                    break;
                case 'string':
                    $schema->string($name, $rule['length'] ?? 255, $rule['nullable'], $rule['default']);
                    break;
                case 'int':
                    $schema->int($name, $rule['nullable'], $rule['default']);
                    break;
                case 'tinyint':
                    $schema->tinyint($name, $rule['nullable'], $rule['default']);
                    break;
                case 'float':
                    $schema->decimal($name, $rule['length'] ?? 10, $rule['precision'] ?? 2, $rule['nullable'], $rule['default']);
                    break;
                case 'bool':
                    $schema->boolean($name, $rule['nullable'], $rule['default']);
                    break;
                case 'date':
                    $schema->date($name, $rule['nullable'], $rule['default']);
                    break;
                case 'datetime':
                    $schema->datetime($name, $rule['nullable'], $rule['default']);
                    break;
                case 'date':
                    $schema->date($name, $rule['nullable'], $rule['default']);
                    break;
                case 'timestamp':
                    $schema->timestamp($name, $rule['nullable'], $rule['default']);
                    break;
                case 'time':
                    $schema->time($name, $rule['nullable'], $rule['default']);
                    break;
                case 'array':
                    $schema->text($name, $rule['nullable'], $rule['default']);
                    break;
                case 'list':
                    $max = 0;
                    $is_int = true;
                    foreach ($rule['options'] as $key => $_) {
                        $max = max($max, strlen($key));
                        if (!is_int($key)) {
                            $is_int = false;
                        }
                    }
                    if ($is_int) {
                        $schema->int($name, $rule['nullable'], $rule['default']);
                    } else {
                        $schema->string($name, $max ?? 255, $rule['nullable'], $rule['default']);
                    }
                    break;
                case 'enum':
                    $max = 0;
                    $is_int = true;
                    foreach ($rule['options'] as $value) {
                        $max = max($max, strlen($value));
                        if (!is_int($value)) {
                            $is_int = false;
                        }
                    }
                    if ($is_int) {
                        $schema->int($name, $rule['nullable'], $rule['default']);
                    } else {
                        $schema->string($name, ($max ?? 255), $rule['nullable'], $rule['default']);
                    }
                    break;
                default:
                    $schema->string($name, 255);
            }
            if ($rule['unique']) {
                $schema->index($name,[$name], true);
            } elseif ($rule['index']) {
                $schema->index($name, [$name]);
            }
        }
        if (count($primaries) > 1) {
            $schema->set_primary_key($primaries);
        }
        return $schema;
    }

}


/**
 * Base implementation of AbstractObject
 *
 * This class provides a simple implementation of AbstractObject
 * that can be used directly without defining custom rules.
 * 
 * @package     MilkCore
 * @subpackage  Abstracts
 * @ignore
 */

class BaseModuleObject extends AbstractObject
{
    
}