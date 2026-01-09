<?php
namespace App\Abstracts\Traits;

use App\Get;
use App\Config;

!defined('MILK_DIR') && die();

/**
 * Data Formatting Trait
 * Handles automatic conversion between different data formats:
 * - RAW: DateTime objects and PHP arrays (default)
 * - FORMATTED: Human-readable strings for display
 * - SQL: MySQL-compatible format (date strings, JSON strings)
 */
trait DataFormattingTrait
{
    /**
     * Output mode for data retrieval
     * 'raw' = DateTime objects and arrays (default)
     * 'formatted' = Human-readable strings
     * 'sql' = MySQL format
     * @var string
     */
    protected string $output_mode = 'raw';

    /**
     * Set output mode for data retrieval
     * All subsequent field access will return values in the specified format
     *
     * @param string $mode Output mode: 'raw', 'formatted', or 'sql'
     * @return void
     * @throws \InvalidArgumentException If mode is not valid
     */
    public function setOutputMode(string $mode): void {
        $valid_modes = ['raw', 'formatted', 'sql'];
        if (!in_array($mode, $valid_modes)) {
            throw new \InvalidArgumentException("Invalid output mode '$mode'. Must be one of: " . implode(', ', $valid_modes));
        }
        $this->output_mode = $mode;
    }

    /**
     * Get current output mode
     *  
     * @return string Current output mode ('raw', 'formatted', 'sql')
     */
    public function getOutputMode(): string
    {
        return $this->output_mode;
    }

    /**
     * Get formatted value for a single field
     * Converts DateTime to formatted string, arrays to comma-separated list
     *
     * @param string $name Field name
     * @return mixed Formatted value
     */
    public function getFormattedValue(string $name): mixed
    {

        // Check for attribute-based formatted getter in Model first
        $handler = $this->getMethodHandler($name, 'get_formatted');
       
        if ($handler !== null) {
            // Pass the entire row obj to the handler (works for both real and virtual fields)
            $singleData = $this->getRawData('object', false);
            return $handler($singleData);
        }

        $this->convertDatesToUserTimezone();

        $raw_value = $this->getRawValue($name);

        if ($raw_value === null) {
            return null;
        }
        // Get field rule
        $rule = $this->getRule($name);
        
        if (!$rule) {
            return $raw_value;
        }
       // print "<p>" .$name." ".$rule['type']." - SQL VALUE: " . print_r($raw_value, true) . "</p>\n";
        // Handle date/datetime/time/timestamp formatting
        if (in_array($rule['type'], ['datetime', 'date'])) {

            $return_value = Get::formatDate($raw_value, $rule['type'], true);

            return $return_value;
        } else if ($rule['type'] === 'time') {
            return Get::formatDate($raw_value, $rule['type'], true);
        } else if ($rule['type'] === 'timestamp') {
            // Convert Unix timestamp to datetime string, then format it
            if (is_numeric($raw_value)) {
                $datetime_string = date('Y-m-d H:i:s', $raw_value);
                return Get::formatDate($datetime_string, 'datetime', true);
            }
            return $raw_value;
        }

        // Handle array formatting
        if ($rule['type'] === 'array') {
            if (!is_array($raw_value)) {
                return $raw_value;
            }

            $selected_options = [];
            if (isset($rule['options']) && is_array($rule['options'])) {
                foreach ($raw_value as $value) {
                    if (isset($rule['options'][$value])) {
                        $selected_options[] = _r($rule['options'][$value]);
                    }
                }
            } else {
                $selected_options = $raw_value;
            }

            if (is_array($selected_options)) {
                $flat_values = [];
                array_walk_recursive($selected_options, function($value) use (&$flat_values) {
                    if (is_string($value) && $value !== '') {
                        $flat_values[] = $value;
                    }
                });
                return implode(', ', $flat_values);
            }
            return null;
        }

        // Handle list/enum formatting
        if (in_array($rule['type'], ['list', 'enum'])) {
            if (isset($rule['options'][$raw_value])) {
                return _r($rule['options'][$raw_value]);
            }
            return $raw_value;
        }

        return $raw_value;
    }

    /**
     * Prepare a single field value for SQL storage
     * Common logic used by both getSqlValue() and prepareData()
     *
     * @param string $field_name Field name
     * @param mixed $value Field value
     * @param array $rule Field rule configuration
     * @return mixed SQL-compatible value
     */
    protected function prepareSingleFieldValue(string $field_name, mixed $value, array $rule): mixed
    {
     
        // Check for attribute-based formatted getter in Model first
        $handler = $this->getMethodHandler($field_name, 'get_sql');
       
        if ($handler !== null) {
            // Pass the entire row obj to the handler (works for both real and virtual fields)
            $singleData = $this->getRawData('object', false);
            return $handler($singleData);
        }



        // PRIORITY 2: Handle created_at fields with auto-preservation
        if (isset($rule['_auto_created_at']) && $rule['_auto_created_at'] === true) {
            $id_field = $this->getPrimaryKey();
            $current_record = $this->getRawData('object', false);

            // If updating an existing record (has ID > 0)
            if (isset($current_record->$id_field) && $current_record->$id_field > 0) {
                $old_record = $this->getById($current_record->$id_field);
                if ($old_record && isset($old_record->$field_name)) {
                    $old_value = $old_record->$field_name;
                    if (is_a($old_value, \DateTime::class)) {
                        return $old_value->format('Y-m-d H:i:s');
                    } elseif (!is_null($old_value)) {
                        return $old_value;
                    }
                }
            }
            // For new records or if old value doesn't exist, use default or current date
            if (isset($rule['default'])) {
                return $rule['default'];
            }
            return date('Y-m-d H:i:s');
        }

        // PRIORITY 3: Check for save_value (static values)
        if (isset($rule['save_value'])) {
            return $rule['save_value'];
        }

        // Handle array conversion to JSON
        if ($rule['type'] === 'array') {
            if (is_array($value) || is_object($value)) {
                return json_encode($value);
            } else if (is_string($value)) {
                return $value; // Already JSON or string
            } else if (!is_null($value)) {
                return json_encode([$value]);
            } else {
                return null;
            }
        }

        if (in_array($rule['type'], ['datetime', 'date']) && 
            ($rule['timezone_conversion'] ?? false) &&
            is_a($value, \DateTime::class)) {
            
            // Il valore arriva dal form nel timezone dell'utente
            // Dobbiamo convertirlo a UTC per il salvataggio
            $user_timezone = Get::userTimezone();
            
            // Imposta il timezone dell'utente sul DateTime ricevuto
            $value->setTimezone(new \DateTimeZone($user_timezone));
            
            // Converti a UTC per il database
            $value->setTimezone(new \DateTimeZone('UTC'));
        }

        // Handle timestamp conversion to Unix timestamp
        if ($rule['type'] === 'timestamp') {
            if (is_a($value, \DateTime::class)) {
                // Convert DateTime to Unix timestamp
                return $value->getTimestamp();
            } elseif (is_numeric($value)) {
                // Already a Unix timestamp
                return (int)$value;
            } elseif (is_string($value) && strtotime($value) !== false) {
                // Convert date string to Unix timestamp
                return strtotime($value);
            }
            return $value;
        }

        // Handle DateTime conversion
        if (is_a($value, \DateTime::class)) {
            if ($rule['type'] === 'date') {
                return $value->format('Y-m-d');
            } else if ($rule['type'] === 'time') {
                return $value->format('H:i:s');
            } else {
                return $value->format('Y-m-d H:i:s');
            }
        }

        // Handle objects/arrays (fallback to JSON)
        if (is_object($value) || is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }

    /**
     * Get SQL value for a single field
     * Converts DateTime to MySQL format, arrays to JSON
     *
     * @param string $name Field name
     * @return mixed SQL-compatible value
     */
    public function getSqlValue(string $name): mixed
    {
        $raw_value = $this->getRawValue($name);

        if ($raw_value === null) {
            return null;
        }

        // Get field rule
        $rule = $this->getRule($name);
        if (!$rule) {
            return $raw_value;
        }

        return $this->prepareSingleFieldValue($name, $raw_value, $rule);
    }

    /**
     * Get raw value for a single field
     *
     * @param string $name Field name
     * @return mixed Raw value from database/cache
     */
    public function getRawValue(string $name): mixed
    {
        if ($name === '___action') {
            return null;
        }

        // NEW: Try records_objects first (contains Model instances)
        if (isset($this->records_objects[$this->current_index])) {
            $record_obj = $this->records_objects[$this->current_index];

            // Direct access to field (can be primitive or Model)
            if (array_key_exists($name, $record_obj)) {
                $value = $record_obj[$name];

                // NEW: getRawValue should NOT return Model instances
                // Use __get or public methods to access relationships
                if (is_object($value) && $value instanceof \App\Abstracts\AbstractModel) {
                    throw new \LogicException(
                        "getRawValue('{$name}') attempted to return a Model instance. " .
                        "Use magic property access (\$model->{$name}) or __get() to access relationships."
                    );
                }

                // Also check arrays of Models
                if (is_array($value) && !empty($value)) {
                    $firstItem = reset($value);
                    if (is_object($firstItem) && $firstItem instanceof \App\Abstracts\AbstractModel) {
                        throw new \LogicException(
                            "getRawValue('{$name}') attempted to return an array of Model instances. " .
                            "Use magic property access (\$model->{$name}) or __get() to access relationships."
                        );
                    }
                }

                return $value;
            }

            // Dot notation: delegate to Model if first part is a Model
            if (str_contains($name, '.')) {
                $parts = explode('.', $name, 2); // Split into first part and rest
                $first_part = $parts[0];
                $rest = $parts[1] ?? '';

                if (array_key_exists($first_part, $record_obj)) {
                    $value = $record_obj[$first_part];

                    // If it's a Model, delegate the rest of the path to it
                    if (is_object($value) && $value instanceof \App\Abstracts\AbstractModel) {
                        if ($rest !== '') {
                            return $value->getRawValue($rest);
                        }
                        return $value;
                    }

                    // If it's an array of Models (hasMany), handle array index
                    if (is_array($value) && !empty($rest)) {
                        $next_parts = explode('.', $rest, 2);
                        $index = $next_parts[0];
                        $remainder = $next_parts[1] ?? '';

                        if (is_numeric($index) && isset($value[$index])) {
                            $item = $value[$index];

                            // If item is a Model and there's more path, delegate
                            if ($remainder !== '' && is_object($item) && $item instanceof \App\Abstracts\AbstractModel) {
                                return $item->getRawValue($remainder);
                            }

                            // If no more path, return the item or its property
                            if ($remainder === '') {
                                return $item;
                            }

                            // Fallback to property access
                            if (is_object($item) && property_exists($item, $remainder)) {
                                return $item->$remainder;
                            }
                        }
                    }

                    // Fallback to nested array/object traversal
                    return $this->traverseDotNotation($value, $rest);
                }
            }
        }

        // Fallback to records_objects
        if (!isset($this->records_objects[$this->current_index])) {
            return null;
        }
        $record = $this->records_objects[$this->current_index];

        // Accesso diretto (preserva valori NULL)
        if (array_key_exists($name, $record)) {
            return $record[$name];
        }

        // Dot notation profonda (es. "socio.indirizzo.citta")
        if (str_contains($name, '.')) {
            return $this->traverseDotNotation($record, $name);
        }

        return null;
    }

    /**
     * Helper method to traverse dot notation on arrays/objects
     *
     * @param mixed $value Starting value
     * @param string $path Dot-separated path
     * @return mixed Traversed value
     */
    private function traverseDotNotation($value, string $path): mixed
    {
        $parts = explode('.', $path);
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } elseif (is_object($value) && property_exists($value, $part)) {
                $value = $value->$part;
            } elseif (is_object($value) && method_exists($value, '__get')) {
                // Try magic __get for Model access
                $value = $value->$part;
            } else {
                return '';  // Retrocompatibilità
            }
        }
        return $value;
    }
    /**
     * Get value with automatic type conversion without setting it.
     * Converts date strings to DateTime, JSON strings to arrays, etc.
     *
     * @param string $name Field name
     * @param mixed $value Value to convert
     * @return mixed Converted value
     */
    protected function getValueWithConversion(string $name, mixed $value): mixed {

        // Skip metadata fields
        if ($name === '___action') {
            return null;
        }

        if ($value === null) {
            return null;
        }

        // Get field rule
        $rule = $this->getRule($name);
        if ($rule) {
        }

        // If no rule exists, check if it's a relationship field
        // Relationship data (hasOne/belongsTo) needs to be stored even without a direct field rule
        if (!$rule) {
            // Check if this is a relationship alias
            if (method_exists($this, 'hasRelationship') && $this->hasRelationship($name)) {
                // It's a relationship - return the value directly without conversion
                return $value;
            }
            // Not a relationship and no rule - return value as-is (might be extension field)
            return $value;
        }

        // Check for attribute-based setter in Model first
        $handler = $this->getMethodHandler($name, 'set_value');
        if ($handler !== null) {
            // Pass current record as first param, value as second param
            if (isset($this->records_objects[$this->current_index])) {
                $value = $handler((object)$this->records_objects[$this->current_index]);
            }
            return $value;
        }

        // Handle array type: convert JSON string to array
        if ($rule['type'] === 'array') {
            if (is_string($value)) {
                $json_attr = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $json_attr;
                } else {
                    $value = [$value];
                }
            }
        }

        // Handle text/string type: if value is an array, convert to JSON
        elseif (in_array($rule['type'], ['text', 'string'])) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
        }

        // Handle datetime type: convert string to DateTime
        elseif ($rule['type'] === 'datetime' || $rule['type'] === 'date') {
            if (!is_a($value, \DateTime::class)) {
                if (strtotime($value) !== false) {
                    // Determine which timezone to use when parsing the string
                    // If timezone_conversion is disabled for this field, always use UTC
                    // Otherwise, check Config and dates_in_user_timezone flag
                    if (!($rule['timezone_conversion'] ?? false)) {
                        // No timezone conversion for this field - force UTC
                        $timezone = new \DateTimeZone('UTC');
                    } elseif (Config::get('use_user_timezone', false)) {
                        // If dates_in_user_timezone is true, incoming dates are already in user timezone
                        // Parse them in user timezone without conversion
                        // If false, incoming dates are in UTC and will be converted later
                        if ($this->dates_in_user_timezone) {
                            // Dates are already in user timezone - parse in user timezone
                            $timezone = new \DateTimeZone(Get::userTimezone());
                        } else {
                            // Dates are in UTC - parse as UTC (will be converted later by convertDatesToUserTimezone)
                            $timezone = new \DateTimeZone('UTC');
                        }
                    } else {
                        // Config says to parse dates in UTC (default)
                        $timezone = new \DateTimeZone('UTC');
                    }
                    $value = new \DateTime($value, $timezone);
                } else {
                    $value = null;
                }
            }
        }

        // Handle timestamp type: convert to Unix timestamp (integer)
        elseif ($rule['type'] === 'timestamp') {
            if (is_a($value, \DateTime::class)) {
                // Convert DateTime to Unix timestamp
                $value = $value->getTimestamp();
            } elseif (is_numeric($value)) {
                // Already a Unix timestamp - convert to integer
                $value = (int)$value;
            } elseif (is_string($value) && strtotime($value) !== false) {
                // Convert date string to Unix timestamp
                $value = strtotime($value);
            } else {
                // Invalid timestamp value
                $value = null;
            }
        }

        return $value;
    }

    /**
     * Set value with automatic type conversion and store it in records_objects.
     * Converts date strings to DateTime, JSON strings to arrays
     *
     * @param string $name Field name
     * @param mixed $value Value to set
     * @return void
     */
    protected function setValueWithConversion(string $name, mixed $value): void {

        $convertedValue = $this->getValueWithConversion($name, $value);

        $this->setRawValue($name, $convertedValue);

    }

    /**
     * Set raw value directly to records_objects
     *
     * @param string $name Field name
     * @param mixed $value Value to set
     * @return void
     */
    protected function setRawValue(string $name, mixed $value): void
    {

        // Skip metadata fields
        if ($name === '___action') {
            return;
        }


        // Se non c'è un record corrente
        if (!isset($this->records_objects[$this->current_index]) || !is_array($this->records_objects[$this->current_index])) {
            if (!is_array($this->records_objects)) {
                $this->records_objects = [];
            }
            // Creane uno nuovo
            $this->records_objects[] = [];
            $this->current_index = array_key_last($this->records_objects);
            $this->records_objects[$this->current_index]['___action'] = 'insert';
        }


        // Controlla se è una relazione - le relazioni contengono Model instances in records_objects
        if (method_exists($this, 'hasRelationship') && $this->hasRelationship($name)) {
            // Non sovrascrivere le relazioni (che sono Model instances)
            // Le relazioni vengono gestite da loadMissingRelationships
        } else {
            // Per i campi normali, aggiorna il valore
            $this->records_objects[$this->current_index][$name] = $value;
        }


        if ($name === $this->getPrimaryKey() && ($this->records_objects[$this->current_index]['___action'] ?? null) === 'insert' && !empty($value)) {
            // Se stiamo inserendo un nuovo record e stiamo impostando la chiave primaria, cambiamo lo stato in 'edit'
            $this->records_objects[$this->current_index]['___action'] = 'edit';
        }
        // Segna il record come modificato (solo se era 'original')
        if ($this->records_objects[$this->current_index]['___action'] === null) {
            $this->records_objects[$this->current_index]['___action'] = 'edit';
        }

    }

    /**
     * Override __get to apply output mode and handle relationships
     *
     * @param string $name Field name or relationship alias
     * @return mixed Field value based on current output mode or related model
     */
    public function __get(string $name)
    {
        static $call_count = 0;
        static $call_stack = [];
        $call_count++;

        $class = get_class($this);
        $call_info = "$class::$name";
        $call_stack[] = $call_info;

        $rules = $this->rule_builder->getRules();
      
       
        //foreach ($rules as $field_name => $rule) {
        //    if (isset($rule['relationship']) ) {
        //        print "<p>". $field_name . " => " . json_encode($rule['relationship'])."</p>";
        //        if($this->hasRelationship($name)) {
        //            print "<h1>TOP</h1>";
        //        }
        //         die ("OK!");
        //    }
        //}
       

        // Check if this is a relationship alias
        if ( $this->hasRelationship($name)) {
           
            // DEBUG
            $class = get_class($this);

            // NEW: Check if we have the relationship as Model in records_objects
            if (isset($this->records_objects[$this->current_index][$name])) {
                $model = $this->records_objects[$this->current_index][$name];
                // Handle single Model (belongsTo/hasOne)
                if (is_object($model) && method_exists($model, 'setOutputMode')) {
                    $model->setOutputMode($this->output_mode);
                }
                return $model;
            }

            // Trigger lazy loading
            $related = $this->getRelatedModel($name);

            // NEW: After lazy loading, check if records_objects was populated
            if (isset($this->records_objects[$this->current_index][$name])) {
                $model = $this->records_objects[$this->current_index][$name];
                // Handle single Model (belongsTo/hasOne)
                if (is_object($model) && method_exists($model, 'setOutputMode')) {
                    $model->setOutputMode($this->output_mode);
                }
                return $model;
            }

            // Fallback: Apply relationship formatters if we have a related model
            if ($related !== null && method_exists($this, 'applyRelationshipFormattersToModel')) {
                return $this->applyRelationshipFormattersToModel($related, $name, $this->output_mode);
            }

            return $related;
        }
        // Normal field access
        switch ($this->output_mode) {
            case 'formatted':
                return $this->getFormattedValue($name);
            case 'sql':
                return $this->getSqlValue($name);
            case 'raw':
            default:
                return $this->getRawValue($name);
        }
    }

    /**
     * Override __set to apply automatic type conversion
     *
     * @param string $name Field name
     * @param mixed $value Value to set
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        // Check if this is a relationship assignment
        if (method_exists($this, 'handleRelationshipAssignment')) {
            $handled = $this->handleRelationshipAssignment($name, $value);
            if ($handled) {
                return; // Relationship assignment handled
            }
        }

        // Normal field assignment
        $this->setValueWithConversion($name, $value);
    }

    // ===== Magic Methods for Property Access =====
    // Now handled by DataFormattingTrait (__get, __set)

    /**
     * Magic isset to check if field exists in current row
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        // Check if it's a defined relationship (can be lazy loaded)
        if (method_exists($this, 'hasRelationship') && $this->hasRelationship($name)) {
            return true;
        }

        // Check if the field exists in the current record
        return isset($this->records_objects[$this->current_index][$name]);
    }

    /**
     * Get all data in RAW format (DateTime objects, PHP arrays)
     * Returns data from current record or all records
     *
     * @param string $type 'object' returns stdClass, 'array' returns associative array
     * @param bool $all_records If true, returns all records. If false, returns only current record
     * @return mixed Object or array containing raw data
     */
    public function getRawData(string $type = 'object', bool $all_records = true): mixed
    {
        $rules = $this->getRules();
        $accepted_fields = array_keys($rules);

        if ($all_records) {

            $result = [];
            $count = $this->count();

            if ($count > 0) {
                $original_index = $this->current_index;

                for ($i = 0; $i < $count; $i++) {
                    $this->moveTo($i);
                    $record = $this->records_objects[$this->current_index];
                    $record_data = [];

                    // NEW: Include relationships from records_objects if available
                    if (isset($this->records_objects[$this->current_index]) && $this->depth < 100) {
                        $record_obj = $this->records_objects[$this->current_index];

                        foreach ($record_obj as $field_name => $field_value) {
                            // Skip metadata and normal fields (handled below)
                            if ($field_name === '___action' || in_array($field_name, $accepted_fields)) {
                                continue;
                            }

                            // Check if this is a relationship field (contains Model)
                            if (is_object($field_value) && $field_value instanceof \App\Abstracts\AbstractModel) {
                                // belongsTo/hasOne: single Model
                                $field_value->depth = $this->depth + 1;
                                $record_data[$field_name] = $field_value->getRawData($type, false);
                            } elseif (is_array($field_value) && !empty($field_value)) {
                                $first = reset($field_value);
                                if (is_object($first) && $first instanceof \App\Abstracts\AbstractModel) {
                                    // hasMany: array of Models
                                    $raw_array = [];
                                    foreach ($field_value as $model) {
                                        if (is_object($model) && $model instanceof \App\Abstracts\AbstractModel) {
                                            $model->depth = $this->depth + 1;
                                            $raw_array[] = $model->getRawData($type, false);
                                        }
                                    }
                                    $record_data[$field_name] = $raw_array;
                                }
                            }
                        }
                    }

                    foreach ($record as $field => $value) {
                        if ($field === '___action') {
                            continue; // Skip metadata field
                        }
                        // Don't overwrite relationship data already added
                        if (!isset($record_data[$field])) {
                            $record_data[$field] = $value;
                        }
                    }

                    if ($type === 'object') {
                        // Create object manually to preserve Model instances in relationships
                        $obj = new \stdClass();
                        foreach ($record_data as $key => $val) {
                            $obj->$key = $val;
                        }
                        $result[] = $obj;
                    } else {
                        $result[] = $record_data;
                    }
                }

                $this->moveTo($original_index);
            }

            return $result;
        } else {
            $record = $this->records_objects[$this->current_index] ?? [];
            // Return current record only
            $data = [];

            // NEW: Include relationships from records_objects if available
            if (isset($this->records_objects[$this->current_index]) && $this->depth < 100) {
                $record_obj = $this->records_objects[$this->current_index];

                foreach ($record_obj as $field_name => $field_value) {
                    // Skip metadata and normal fields (handled below)
                    if ($field_name === '___action' || in_array($field_name, $accepted_fields)) {
                        continue;
                    }

                    // Check if this is a relationship field (contains Model)
                    if (is_object($field_value) && $field_value instanceof \App\Abstracts\AbstractModel) {
                        // belongsTo/hasOne: single Model
                        $field_value->depth = $this->depth + 1;
                        $data[$field_name] = $field_value->getRawData($type, false);
                    } elseif (is_array($field_value) && !empty($field_value)) {
                        $first = reset($field_value);
                        if (is_object($first) && $first instanceof \App\Abstracts\AbstractModel) {
                            // hasMany: array of Models
                            $raw_array = [];
                            foreach ($field_value as $model) {
                                if (is_object($model) && $model instanceof \App\Abstracts\AbstractModel) {
                                    $model->depth = $this->depth + 1;
                                    $raw_array[] = $model->getRawData($type, false);
                                }
                            }
                            $data[$field_name] = $raw_array;
                        }
                    }
                }
            }

            foreach ($accepted_fields as $field) {
                if (!isset($record[$field])) {
                    //@TODO error!
                } else {
                    // Don't overwrite relationship data already added
                    if (!isset($data[$field])) {
                        $data[$field] = $record[$field];
                    }
                }
            }

            if ($type === 'object') {
                // Create object manually to preserve Model instances in relationships
                $obj = new \stdClass();
                foreach ($data as $key => $val) {
                    $obj->$key = $val;
                }
                return $obj;
            }

            return $data;
        }
    }

    /**
     * Get all data in SQL format (MySQL date strings, JSON strings)
     * Returns data from current record or all records
     *
     * @param string $type 'object' returns stdClass, 'array' returns associative array
     * @param bool $all_records If true, returns all records. If false, returns only current record
     * @return mixed Object or array containing SQL-formatted data
     */
    public function getSqlData(string $type = 'object', bool $all_records = true): mixed
    {
        $rules = $this->getRules();
        $accepted_fields = array_keys($rules);

        if ($all_records) {
            $result = [];
            $count = $this->count();

            if ($count > 0) {
                $original_index = $this->current_index;

                for ($i = 0; $i < $count; $i++) {
                    $this->moveTo($i);
                    $record_data = [];

                    // NEW: Include relationships from records_objects if available
                    if (isset($this->records_objects[$this->current_index]) && $this->depth < 100) {
                        $record_obj = $this->records_objects[$this->current_index];

                        foreach ($record_obj as $field_name => $field_value) {
                            // Skip metadata and normal fields (handled below)
                            if ($field_name === '___action' || in_array($field_name, $accepted_fields)) {
                                continue;
                            }

                            // Check if this is a relationship field (contains Model)
                            if (is_object($field_value) && $field_value instanceof \App\Abstracts\AbstractModel) {
                                // belongsTo/hasOne: single Model
                                $field_value->depth = $this->depth + 1;
                                $record_data[$field_name] = $field_value->getSqlData($type, false);
                            } elseif (is_array($field_value) && !empty($field_value)) {
                                $first = reset($field_value);
                                if (is_object($first) && $first instanceof \App\Abstracts\AbstractModel) {
                                    // hasMany: array of Models
                                    $sql_array = [];
                                    foreach ($field_value as $model) {
                                        if (is_object($model) && $model instanceof \App\Abstracts\AbstractModel) {
                                            $model->depth = $this->depth + 1;
                                            $sql_array[] = $model->getSqlData($type, false);
                                        }
                                    }
                                    $record_data[$field_name] = $sql_array;
                                }
                            }
                        }
                    }

                    foreach ($rules as $name => $rule) {
                        if (!($rule['mysql'] ?? true)) {
                            continue;
                        }

                        $record_data[$name] = $this->getSqlValue($name);
                    }

                    $result[] = $type === 'object' ? (object)$record_data : $record_data;
                }

                $this->moveTo($original_index);
            }

            return $result;
        } else {
            // Return current record only
            $data = [];

            // NEW: Include relationships from records_objects if available
            if (isset($this->records_objects[$this->current_index]) && $this->depth < 100) {
                $record_obj = $this->records_objects[$this->current_index];

                foreach ($record_obj as $field_name => $field_value) {
                    // Skip metadata and normal fields (handled below)
                    if ($field_name === '___action' || in_array($field_name, $accepted_fields)) {
                        continue;
                    }

                    // Check if this is a relationship field (contains Model)
                    if (is_object($field_value) && $field_value instanceof \App\Abstracts\AbstractModel) {
                        // belongsTo/hasOne: single Model
                        $field_value->depth = $this->depth + 1;
                        $data[$field_name] = $field_value->getSqlData($type, false);
                    } elseif (is_array($field_value) && !empty($field_value)) {
                        $first = reset($field_value);
                        if (is_object($first) && $first instanceof \App\Abstracts\AbstractModel) {
                            // hasMany: array of Models
                            $sql_array = [];
                            foreach ($field_value as $model) {
                                if (is_object($model) && $model instanceof \App\Abstracts\AbstractModel) {
                                    $model->depth = $this->depth + 1;
                                    $sql_array[] = $model->getSqlData($type, false);
                                }
                            }
                            $data[$field_name] = $sql_array;
                        }
                    }
                }
            }

            foreach ($rules as $name => $rule) {
                if (!($rule['mysql'] ?? true)) {
                    continue;
                }

                $data[$name] = $this->getSqlValue($name);
            }

            return $type === 'object' ? (object)$data : $data;
        }
    }

    /**
     * Get all data in FORMATTED format (human-readable strings)
     * Returns data from current record or all records
     *
     * @param string $type 'object' returns stdClass, 'array' returns associative array
     * @param bool $all_records If true, returns all records. If false, returns only current record
     * @return mixed Object or array containing formatted data
     */
    public function getFormattedData(string $type = 'object', bool $all_records = true): mixed
    {
        $rules = $this->getRules();
        $accepted_fields = array_keys($rules);


        $data = $this->getRawData($type, $all_records);
        if ($all_records) {
            $result = [];
            $count = $this->count();

            if ($count > 0) {
                $original_index = $this->current_index;

                for ($i = 0; $i < $count; $i++) {
                    $this->moveTo($i);
                    $record_data = [];

                    // NEW: Include relationships from records_objects if available
                    if (isset($this->records_objects[$this->current_index]) && $this->depth < 100) {
                        $record_obj = $this->records_objects[$this->current_index];

                        foreach ($record_obj as $field_name => $field_value) {
                            // Skip metadata and normal fields (handled below)
                            if ($field_name === '___action' || in_array($field_name, $accepted_fields)) {
                                continue;
                            }

                            // Check if this is a relationship field (contains Model)
                            if (is_object($field_value) && $field_value instanceof \App\Abstracts\AbstractModel) {
                                // belongsTo/hasOne: single Model
                                $field_value->depth = $this->depth + 1;
                                $record_data[$field_name] = $field_value->getFormattedData($type, false);
                            } elseif (is_array($field_value) && !empty($field_value)) {
                                $first = reset($field_value);
                                if (is_object($first) && $first instanceof \App\Abstracts\AbstractModel) {
                                    // hasMany: array of Models
                                    $formatted_array = [];
                                    foreach ($field_value as $model) {
                                        if (is_object($model) && $model instanceof \App\Abstracts\AbstractModel) {
                                            $model->depth = $this->depth + 1;
                                            $formatted_array[] = $model->getFormattedData($type, false);
                                        }
                                    }
                                    $record_data[$field_name] = $formatted_array;
                                }
                            }
                        }
                    }

                    foreach ($accepted_fields as $field) {
                        $record_data[$field] = $this->getFormattedValue($field);
                    }

                    $result[] = $type === 'object' ? (object)$record_data : $record_data;
                }

                $this->moveTo($original_index);
            }

            return $result;
        } else {
            // Return current record only
            $data = [];
            foreach ($accepted_fields as $field) {
                $data[$field] = $this->getFormattedValue($field);
            }

            // NEW: Include relationships from records_objects if available
            if (isset($this->records_objects[$this->current_index]) && $this->depth < 100) {
                $record_obj = $this->records_objects[$this->current_index];

                foreach ($record_obj as $field_name => $field_value) {
                    // Skip metadata and normal fields (already handled)
                    if ($field_name === '___action' || in_array($field_name, $accepted_fields)) {
                        continue;
                    }

                    // Check if this is a relationship field (contains Model)
                    if (is_object($field_value) && $field_value instanceof \App\Abstracts\AbstractModel) {
                        // belongsTo/hasOne: single Model
                        $field_value->depth = $this->depth + 1;
                        $data[$field_name] = $field_value->getFormattedData($type, false);
                    } elseif (is_array($field_value) && !empty($field_value)) {
                        $first = reset($field_value);
                        if (is_object($first) && $first instanceof \App\Abstracts\AbstractModel) {
                            // hasMany: array of Models
                            $formatted_array = [];
                            foreach ($field_value as $model) {
                                if (is_object($model) && $model instanceof \App\Abstracts\AbstractModel) {
                                    $model->depth = $this->depth + 1;
                                    $formatted_array[] = $model->getFormattedData($type, false);
                                }
                            }
                            $data[$field_name] = $formatted_array;
                        }
                    }
                }
            }


            return $type === 'object' ? (object)$data : $data;
        }
    }

    public function getRecordAction($record_index = null) {
        return $this->records_objects[$record_index ?? $this->current_index]['___action'] ?? null;
    }

    /**
     * Filter current records by field values and return a new model instance
     * Cycles through already loaded records and creates a new model with only matching records
     *
     * @param string $field Field name to filter by
     * @param array $values Array of values to match
     * @return static New model instance with filtered records
     */
    public function filterByField(string $field, array $values): static
    {
        $filtered = [];

        // Cycle through loaded records
        if ($this->records_objects !== null) {
            foreach ($this->records_objects as $record) {
                // Check if field exists and value is in the provided array
                if (isset($record[$field]) && in_array($record[$field], $values)) {
                    $filtered[] = $record;
                }
            }
        }

        // Create new model instance
        $new_model = new static();
        $new_model->setRules($this->getRules());
        $new_model->setResults($filtered);


        return $new_model;
    }
}
