<?php
namespace App\Abstracts\Traits;

use App\{Get, MessagesHandler};

!defined('MILK_DIR') && die();

/**
 * Schema and Validation Trait
 * Handles database schema operations, validation, and object utilities
 */
trait SchemaAndValidationTrait
{
   
    /**
     * Get the primary key name
     *
     * @return string The primary key name
     */
    public function getPrimaryKey(): string {
        return $this->primary_key;
    }

    /**
     * Get all primary key fields
     *
     * @return array Array of primary key field names
     */
    public function getPrimaries(): array {
        $primaries = [];
        $rules = $this->getRules('sql');
        foreach ($rules as $name => $rule) {
            if ($rule['primary'] ?? false) {
                $primaries[] = $name;
            }
        }
        return $primaries;
    }

    /**
     * Generate database schema for the model
     */
    public function getSchema(): \App\Database\SchemaMysql|\App\Database\SchemaSqlite {
        $schema = Get::schema($this->table, $this->db);
        $primaries = $this->getPrimaries();
        $rules = $this->getRules('sql');

        $skip_primary = false;
        if (count($primaries) == 1 && isset($rules[$primaries[0]]) && $rules[$primaries[0]]['type'] == 'int') {
            $schema->id($primaries[0]);
            $skip_primary = true;
        }
        foreach ($rules as $name => $rule) {
            if (in_array($name, $primaries) && count($primaries) == 1 && $skip_primary) {
                continue;
            }
            if (!($rule['mysql'] ?? true))  continue;
            
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
                    $seqence = true;
                    $pre_sequence = null;
                    foreach ($rule['options'] as $key => $_) {
                        if ($seqence) {
                            // First check if key is an integer before doing math operations
                            if (!is_int($key)) {
                                $seqence = false;
                            } elseif ($pre_sequence !== null && $pre_sequence + 1 != $key) {
                                $seqence = false;
                            }
                            $pre_sequence = $key;
                        }
                        $max = max($max, strlen((string)$key));
                        if (!is_int($key)) {
                            $is_int = false;
                        }
                    }
                    if ($is_int && $seqence) {
                        if (is_string($rule['default'])) {
                            $rule['default'] = array_search($rule['default'], $rule['options']);
                            if ($rule['default'] === false) {
                                $rule['default'] = null;
                            } 
                        }
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
            $schema->setPrimaryKey($primaries);
        }
        return $schema;
    }

   

    /**
     * Get columns from object class
     *
     * @return array The columns from the object class
     */
    public function getColumns($key = '') {
        return array_keys($this->getRules($key, true));
    }

    /**
     * Get query columns from database
     *
     * @return array The query columns
     */
    public function getQueryColumns() {
        if (!$this->get_query_columns) {
            if (isset($this->records_array) && is_array($this->records_array) && !empty($this->records_array)) {
                $this->get_query_columns = array_keys(reset($this->records_array));
            } 
        }
        return $this->get_query_columns;
    }

    public function setQueryColumns($columns) {
        $this->get_query_columns = $columns;
    }

    /**
     * Create or update the database table
     *
     * This method should be overridden in child classes because the prefix is not set during installation.
     * If using an object model, this is already handled automatically.
     *
     * @example
     * ```php
     * // Using Schema for table management
     * public function buildTable(): bool {
     *     $this->db->prefix = Config::get('prefix');
     *     $schema = Get::schema($this->table);
     *     $schema->id()
     *            ->string('title')
     *            ->text('description')
     *            ->datetime('created_at')
     *            ->datetime('updated_at', true);
     *
     *     if ($schema->exists()) {
     *         return $schema->modify();
     *     } else {
     *         return $schema->create();
     *     }
     * }
     * ```
     * @return bool True if the table was created/updated successfully, false otherwise
     */

     public function buildTable($force_update = true): bool {
        $this->last_error = '';
       
        $schema = $this->getSchema();

        // verifico se già esiste la tabella
        if ($schema->exists()) {
                if ($schema->modify($force_update)) {
                $this->schema_field_differences = $schema->getFieldDifferences();
                if (method_exists($this, 'afterModifyTable')) {
                    $this->afterModifyTable();
                }
                return true;
                } else {
                $this->last_error = $schema->getLastError();
                return false;
                }
        } else {
            if ($schema->create()) {
                $this->schema_field_differences = $schema->getFieldDifferences();
                if (method_exists($this, 'afterCreateTable')) {
                    $this->afterCreateTable();
                }
                return true;
            } else {
                $this->last_error = $schema->getLastError() ?? '';
                return false;
            }
        }
        
        return false;
    }

    /**
     * Get schema field differences after table modification
     * after buildTable() method
     *
     * @return array The schema field differences
     */
    public function getSchemaFieldDifferences(): array {
        return $this->schema_field_differences;
    }

    /**
     * Hook method called after creating the table
     *
     * @return void
     */
    protected function afterCreateTable(): void {
        // da sovrascrivere nelle classi figlie
    }

    /**
     * Hook method called after modifying the table
     *
     * @return void
     */
    protected function afterModifyTable(): void {
        // da sovrascrivere nelle classi figlie
    }

    /**
     * Drop the database table
     *
     * @return bool True if the table was dropped successfully, false otherwise
     */
    public function dropTable(): bool
    {
        $schema = Get::schema($this->table);
        return $schema->drop();
    }

    /**
     * Get the table name
     *
     * @return string The table name
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * Set the table name
     *
     * @param string $table The table name
     * @return void
     */
    public function setTable($table) {
        $this->table = $table;
    }

    /**
     * Set the database connection
     *
     * @param object $db The database connection
     * @return void
     */
    public function setDb($db) {
        $this->db = $db;
    }

    /**
     * Get the database connection
     *
     * Returns the database connection
     *
     * @return object The database connection
     */
    public function getDb() {
        return $this->db;
    }

    /**
     * Validate internal data
     *
     * Validates data stored in the Model (current record or all records)
     * Uses internal rules and respects data conversions
     *
     * @param bool $validate_all If true, validates all records. If false, validates only current record
     * @return bool True if validation passes, false otherwise
     */
    public function validate(bool $validate_all = true): bool
    {
        $rules = $this->getRules('sql');
        if (empty($rules)) {
            return true;
        }

        $records_to_validate = [];

        if ($validate_all && $this->records_array !== null) {
            foreach ($this->records_array as $index => $record) {
                $records_to_validate[] = ['index' => $index, 'data' => (array)$record];
            }
        } else {
            if ($this->records_array !== null && isset($this->records_array[$this->current_index])) {
                $records_to_validate[] = ['index' => $this->current_index, 'data' => (array)$this->records_array[$this->current_index]];
            } elseif ($this->cached_row !== null) {
                $records_to_validate[] = ['index' => $this->current_index, 'data' => $this->cached_row];
            }
        }

        foreach ($records_to_validate as $record_info) {
            $data = $record_info['data'];

            foreach ($rules as $field_name => $rule) {
                $type = $rule['form-type'] ?? $rule['type'];
                $value = $data[$field_name] ?? null;

                if ($field_name === '___action') {
                    continue;
                }

                 $handler = $this->getMethodHandler($field_name, 'validate');
                if ($handler !== null) {
                    // #[Validate(field_name)]
                    $error = $handler($this, $value);
                    if ($error) {
                        MessagesHandler::addError($error, $field_name);
                    }
                    continue;
                }

                if ((isset($rule['form-params']['required']) && $rule['form-params']['required'])
                    && ($value === null || $value === '' || $value === [])) {
                    MessagesHandler::addError('the field <b>' . $rule['label'] . '</b> is required', $field_name);
                    continue;
                }

                if ($value === null) {
                    continue;
                }

                if ($type == 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    MessagesHandler::addError('Invalid Email', $field_name);
                }

                if ($type == 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    MessagesHandler::addError('Invalid Url', $field_name);
                }

                if ($rule['type'] == 'int' && $value != 0 && !filter_var($value, FILTER_VALIDATE_INT)) {
                    MessagesHandler::addError('The field <b>' . $rule['label'] . '</b> is invalid. Must be an integer', $field_name);
                }

                if ($rule['type'] == 'float' && $value != 0 && !filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    MessagesHandler::addError('Invalid Float', $field_name);
                }

                if ($rule['type'] == 'datetime') {
                    if (!is_a($value, \DateTime::class) && (is_scalar($value) && !strtotime($value))) {
                        MessagesHandler::addError('Invalid Date', $field_name);
                    }
                }

                if ($rule['type'] == 'date') {
                    if (!is_a($value, \DateTime::class) && (is_scalar($value) && !strtotime($value))) {
                        MessagesHandler::addError('Invalid Date', $field_name);
                    }
                }

                if ($rule['type'] == 'enum' && !in_array($value, $rule['options'] ?? [])) {
                    MessagesHandler::addError('The field <b>' . $rule['label'] . '</b> is invalid', $field_name);
                }

                if ($rule['type'] == 'list' && !array_key_exists($value, $rule['options'] ?? [])) {
                    MessagesHandler::addError('Invalid List', $field_name);
                }

                if (in_array($rule['type'], ['string','text']) && ($rule['length'] ?? 0) > 0) {
                    // If value is an array, convert to JSON string for length check
                    $check_value = is_array($value) ? json_encode($value) : $value;
                    if (is_string($check_value) && strlen($check_value) > $rule['length']) {
                        MessagesHandler::addError('Field <b>' . $rule['label'] . '</b> is too long. Max length is ' . $rule['length'], $field_name);
                    }
                }

                if ($rule['type'] == 'bool' && $value != 0 && !filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
                    MessagesHandler::addError('Invalid Boolean', $field_name);
                }
            }
        }

        return !MessagesHandler::hasErrors();
    }

    /**
     * Get the table structure
     *
     * Returns the structure of the columns in the table
     *
     * @return array The table structure
     */
    protected function getTableStructure() {
        $table_structure = [];
        $ris = $this->db->getResults("SHOW COLUMNS FROM " . $this->db->qn($this->table));
        if (!is_countable($ris)) {
            return $table_structure;
        }
        foreach ($ris as $row) {
            $table_structure[$row->Field] = $row;
        }
        return $table_structure;
    }

    /**
     * Initialize class name with namespace resolution
     *
     * @param string $class The class name to initialize
     * @return string The fully qualified class name
     */
    protected function inizialeClass(string $class): string {
        $name_space = $this->getChildNameSpace();
        $class_name = $name_space."\\".$class;
        if (class_exists($class_name)) {
            return $class_name;
        } elseif (class_exists($class)) {
            return $class;
        } else {
            return '';
        }
    }

    /**
     * Get the namespace of the child class
     *
     * @return string The namespace
     */
    protected function getChildNameSpace(): string {
        return (new \ReflectionClass(get_called_class()))->getNamespaceName();
    }
}