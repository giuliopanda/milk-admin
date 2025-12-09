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
     * Set output mode to formatted and return self for method chaining
     * When called without parameters: sets mode permanently
     * When used in foreach: returns iterator with formatted data
     *
     * @param string|null $field Optional field name to get formatted value
     * @return mixed Self for chaining, or formatted field value, or iterator
     */
    public function getFormatted(?string $field): mixed{
        return $this->getFormattedValue($field);  
    }


    public function setFormatted(): void {
        $this->output_mode = 'formatted';
    }

    /**
     * Set output mode to SQL and return self for method chaining
     *
     * @param string|null $field Optional field name to get SQL value
     * @return mixed Self for chaining or SQL field value
     */
    public function getSql(?string $field = null): mixed {
        return $this->getSqlValue($field);   
    }

    public function setSql(): void {
        $this->output_mode = 'sql';
    }

    /**
     * Set output mode to raw and return self for method chaining
     *
     * @param string|null $field Optional field name to get raw value
     * @return mixed Self for chaining or raw field value
     */
    public function getRaw(?string $field = null): mixed {
         return $this->getRawValue($field);
    }

    public function setRaw(): void {
        $this->output_mode = 'raw';
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
        // Skip metadata fields
        if ($name === '___action') {
            return null;
        }
        if (isset($this->records_array[$this->current_index])) {
            $record = $this->records_array[$this->current_index];
            if (isset($record[$name])) {
                return $record[$name];
            }   else if (str_contains($name, '.')) {
                $name = explode('.', $name);
                if (count($name) == 2) {
                    $singleData = $this->getRawData('object', false);
                    return $singleData->{$name[0]}->{$name[1]} ?? '';
                }
            } 
        } 
        
        return null;
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
            if (isset($this->records_array[$this->current_index])) {
                $value = $handler((object)$this->records_array[$this->current_index]);
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
     * Set value with automatic type conversion and store it in records_array.
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
     * Set raw value directly to  records_array
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
        if (!isset($this->records_array[$this->current_index]) || !is_array($this->records_array[$this->current_index])) {
            if (!is_array($this->records_array)) {
                $this->records_array = [];
            }
            // Creane uno nuovo
            $this->records_array[] = [];
            $this->current_index = array_key_last($this->records_array);
            $this->records_array[$this->current_index]['___action'] = 'insert';
        }


        // Modifica il valore nel record
        $this->records_array[$this->current_index][$name] = $value;


        if ($name === $this->getPrimaryKey() && ($this->records_array[$this->current_index]['___action'] ?? null) === 'insert' && !empty($value)) {
            // Se stiamo inserendo un nuovo record e stiamo impostando la chiave primaria, cambiamo lo stato in 'edit'
            $this->records_array[$this->current_index]['___action'] = 'edit';
        }
        // Segna il record come modificato (solo se era 'original')
        if ($this->records_array[$this->current_index]['___action'] === null) {
            $this->records_array[$this->current_index]['___action'] = 'edit';
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
        // Check if this is a relationship alias
        if (method_exists($this, 'hasRelationship') && $this->hasRelationship($name)) {
            $related = $this->getRelatedModel($name);

            // Apply relationship formatters if we have a related model
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
        return isset($this->records_array[$this->current_index][$name]);
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
                    $record = $this->records_array[$this->current_index];
                    $record_data = [];
                       // Include relationships if requested
                    if (!empty($this->include_relationships)) {
                        $relationships_data = $this->getIncludedRelationshipsData('raw');
                        foreach ($relationships_data as $alias => $rel_data) {
                            // Convert relationship arrays to objects if object type requested
                            if ($type === 'object' && is_array($rel_data)) {
                                // Check if it's hasMany (array of arrays) or single relationship
                                if (isset($rel_data[0]) && is_array($rel_data[0])) {
                                    // hasMany: convert each item to object
                                    $record_data[$alias] = array_map(fn($item) => (object)$item, $rel_data);
                                } else {
                                    // belongsTo/hasOne: convert single array to object
                                    $record_data[$alias] = (object)$rel_data;
                                }
                            } else {
                                $record_data[$alias] = $rel_data;
                            }
                        }
                    }
                    
                    foreach ($record as $field => $value) {
                        if ($field === '___action') {
                            continue; // Skip metadata field
                        }
                        $record_data[$field] = $value;
                    }

                    $result[] = $type === 'object' ? (object)$record_data : $record_data;
                }

                $this->moveTo($original_index);
            }

            return $result;
        } else {
            $record = $this->records_array[$this->current_index];
            // Return current record only
            $data = [];
             // Include relationships if requested
            if (!empty($this->include_relationships)) {
                $relationships_data = $this->getIncludedRelationshipsData('raw');
                
                foreach ($relationships_data as $alias => $rel_data) {
                    // Convert relationship arrays to objects if object type requested
                    if ($type === 'object' && is_array($rel_data)) {
                        // Check if it's hasMany (array of arrays) or single relationship
                        if (isset($rel_data[0]) && is_array($rel_data[0])) {
                            // hasMany: convert each item to object
                            $data[$alias] = array_map(fn($item) => (object)$item, $rel_data);
                        } else {
                            // belongsTo/hasOne: convert single array to object
                            $data[$alias] = (object)$rel_data;
                        }
                    } else {
                        $data[$alias] = $rel_data;
                    }
                }
            }
            foreach ($accepted_fields as $field) {
                if (!isset($record[$field])) {
                    //@TODO error!
                } else {
                    $data[$field] = $record[$field];
                }
            }
            return $type === 'object' ? (object)$data : $data;
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

        if ($all_records) {
            $result = [];
            $count = $this->count();

            if ($count > 0) {
                $original_index = $this->current_index;

                for ($i = 0; $i < $count; $i++) {
                    $this->moveTo($i);
                    $record_data = [];
                    foreach ($rules as $name => $rule) {
                        if (!($rule['mysql'] ?? true)) {
                            continue;
                        }

                        $record_data[$name] = $this->getSqlValue($name);
                    }

                    // Include relationships if requested
                    $relationships_data = $this->getIncludedRelationshipsData('sql');
                    foreach ($relationships_data as $alias => $rel_data) {
                        $record_data[$alias] = $rel_data;
                    }
                  

                    $result[] = $type === 'object' ? (object)$record_data : $record_data;
                }

                $this->moveTo($original_index);
            }

            return $result;
        } else {
            // Return current record only
            $data = [];
            foreach ($rules as $name => $rule) {
                if (!($rule['mysql'] ?? true)) {
                    continue;
                }

                $data[$name] = $this->getSqlValue($name);
            }

            // Include relationships if requested
            $relationships_data = $this->getIncludedRelationshipsData('sql');
            foreach ($relationships_data as $alias => $rel_data) {
                $data[$alias] = $rel_data;
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
                    
                    // Include relationships if requested
                    $relationships_data = $this->getIncludedRelationshipsData('raw');
                    if (is_array($relationships_data)) {
                      
                        foreach ($relationships_data as $alias => $rel_data) {
                            if (empty($rel_data)) {
                                continue;
                            }
                            foreach ($rel_data as $krd=>$_) {
                                if ($type === 'object') {
                                    if (!isset($record_data[$alias]) || !is_object($record_data[$alias])) {
                                        $record_data[$alias] = (object)[];
                                    }
                                    $record_data[$alias]->$krd = $this->getFormattedValue($alias.".".$krd);
                                } else {
                                    if (!isset($record_data[$alias]) || !is_array($record_data[$alias])) {
                                        $record_data[$alias] = [];
                                    }
                                    $record_data[$alias][$krd] = $this->getFormattedValue($alias.".".$krd);
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

            // Include relationships if requested
            $relationships_data = $this->getIncludedRelationshipsData('formatted');
            foreach ($relationships_data as $alias => $rel_data) {
                $data[$alias] = $rel_data;
            }
            

            return $type === 'object' ? (object)$data : $data;
        }
    }

    public function getRecordAction($record_index = null) {
        return $this->records_array[$record_index ?? $this->current_index]['___action'] ?? null;
    }
}
