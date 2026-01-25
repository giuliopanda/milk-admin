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
        $rename_fields = $this->rule_builder->getRenameFields();
        foreach ($rename_fields as $from => $to) {
            $schema->renameField($from, $to);
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
            if (isset($this->records_objects) && is_array($this->records_objects) && !empty($this->records_objects)) {
                $this->get_query_columns = array_keys(reset($this->records_objects));
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
        $schema = Get::schema($this->table, $this->db);
        if ($schema === null) {
            $db = $this->db ?? Get::db();
            if ($db && method_exists($db, 'dropTable')) {
                return (bool) $db->dropTable($this->table);
            }
            $this->last_error = _r('Schema adapter not available for database type.');
            return false;
        }
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

        // 1) Build records to validate
        $records_to_validate = [];

        if ($validate_all && $this->records_objects !== null) {
            foreach ($this->records_objects as $index => $record) {
                $records_to_validate[] = [
                    'index' => $index,
                    'data'  => (array) $record,
                ];
            }
        } else {
            if ($this->records_objects !== null && isset($this->records_objects[$this->current_index])) {
                $records_to_validate[] = [
                    'index' => $this->current_index,
                    'data'  => (array) $this->records_objects[$this->current_index],
                ];
            } elseif ($this->cached_row !== null) {
                $records_to_validate[] = [
                    'index' => $this->current_index,
                    'data'  => $this->cached_row,
                ];
            }
        }

        $parse_datetime_value = static function ($value): ?int {
            if ($value instanceof \DateTimeInterface) {
                return $value->getTimestamp();
            }
            if (!is_scalar($value)) {
                return null;
            }
            $timestamp = strtotime((string) $value);
            if ($timestamp === false) {
                return null;
            }
            return $timestamp;
        };

        $parse_time_value = static function ($value): ?int {
            if ($value instanceof \DateTimeInterface) {
                $hours = (int) $value->format('H');
                $minutes = (int) $value->format('i');
                $seconds = (int) $value->format('s');
                return ($hours * 3600) + ($minutes * 60) + $seconds;
            }
            if (!is_scalar($value)) {
                return null;
            }
            $time_string = trim((string) $value);
            if ($time_string === '') {
                return null;
            }
            if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $time_string, $matches)) {
                $hours = (int) $matches[1];
                $minutes = (int) $matches[2];
                $seconds = isset($matches[3]) ? (int) $matches[3] : 0;
                return ($hours * 3600) + ($minutes * 60) + $seconds;
            }
            $timestamp = strtotime($time_string);
            if ($timestamp === false) {
                return null;
            }
            return ((int) date('H', $timestamp) * 3600) + ((int) date('i', $timestamp) * 60) + (int) date('s', $timestamp);
        };

        $resolve_field_label = static function (string $field, array $rules): string {
            return $rules[$field]['label'] ?? $field;
        };

        // 2) Validate each record
        foreach ($records_to_validate as $record_info) {
            $data = $record_info['data'];

            foreach ($rules as $field_name => $rule) {
                if ($field_name === '___action') {
                    continue;
                }

                $form_type = $rule['form-type'] ?? null;
                if ($form_type === 'datetime-local') {
                    $form_type = 'datetime';
                }
                $rule_type = $rule['type'] ?? null;
                $type  = $form_type ?? $rule_type ?? null;
                $value = $data[$field_name] ?? null;
                $form_params = $rule['form-params'] ?? [];

                // Custom handler: #[Validate(field_name)]
                $handler = $this->getMethodHandler($field_name, 'validate');
                if ($handler !== null) {
                    $error = $handler((object) $data);
                    if ($error === false || (is_string($error) && trim($error) !== '')) {
                        MessagesHandler::addError($error, $field_name);
                    }
                    continue;
                }

                // Flags
                $required = (bool) ($rule['form-params']['required'] ?? false);
                // Support both $rule['nullable'] and $rule['form-params']['nullable']
                $nullable = (bool) ($rule['nullable'] ?? ($rule['form-params']['nullable'] ?? false));

                // Define what "empty" means for required and nullable.
                // - required: null, '' and [] are considered missing
                // - nullable: if null or '' we skip further validation
                $is_missing_for_required = ($value === null || $value === '' || $value === []);
                $is_empty_for_nullable   = ($value === null || $value === '');

                // Required check
                if ($required && $is_missing_for_required) {
                    MessagesHandler::addError(
                        'the field <b>' . ($rule['label'] ?? $field_name) . '</b> is required',
                        $field_name
                    );
                    continue;
                }

                // Nullable check (skip type validation)
                if ($nullable && $is_empty_for_nullable) {
                    continue;
                }

                // If not required and still null -> nothing to validate
                if ($value === null) {
                    continue;
                }

                // 3) Type-specific validation
                if ($type === 'email') {
                    if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                        MessagesHandler::addError('Invalid Email', $field_name);
                    }
                    continue;
                }

                if ($type === 'url') {
                    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                        MessagesHandler::addError('Invalid Url', $field_name);
                    }
                    continue;
                }

                

                $numeric_rule_types = ['id', 'int', 'tinyint', 'float'];
                $numeric_form_types = ['number', 'range'];
                $is_numeric_type = in_array($rule_type, $numeric_rule_types, true) || in_array($form_type, $numeric_form_types, true);
                if ($is_numeric_type) {
                    if (($rule['primary'] === true && ($value === null || $value === ''))) {
                        continue;
                    }
                    if (!is_scalar($value) || !is_numeric($value)) {
                        MessagesHandler::addError(
                            'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid. Must be numeric',
                            $field_name
                        );
                        continue;
                    }

                    if (in_array($rule_type, ['id', 'int', 'tinyint'], true) && filter_var($value, FILTER_VALIDATE_INT) === false) {
                        MessagesHandler::addError(
                            'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid. Must be an integer',
                            $field_name
                        );
                        continue;
                    }

                    if ($rule_type === 'float' && filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                        MessagesHandler::addError(
                            'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid. Must be a float',
                            $field_name
                        );
                        continue;
                    }

                    $numeric_value = (float) $value;
                    $min = $form_params['min'] ?? null;
                    $min_label = $min;
                    if ($min !== null && $min !== '' && !is_numeric($min) && is_string($min) && array_key_exists($min, $data)) {
                        $min_label = $resolve_field_label($min, $rules);
                        $min = $data[$min] ?? null;
                    }
                    if (($min === null || $min === '') && isset($rule['min_field']) && is_string($rule['min_field']) && array_key_exists($rule['min_field'], $data)) {
                        $min_label = $resolve_field_label($rule['min_field'], $rules);
                        $min = $data[$rule['min_field']] ?? null;
                    }
                    if ($min !== null && $min !== '' && is_numeric($min) && $numeric_value < (float) $min) {
                        MessagesHandler::addError(
                            'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be greater than or equal to ' . $min_label,
                            $field_name
                        );
                        continue;
                    }

                    $max = $form_params['max'] ?? null;
                    $max_label = $max;
                    if ($max !== null && $max !== '' && !is_numeric($max) && is_string($max) && array_key_exists($max, $data)) {
                        $max_label = $resolve_field_label($max, $rules);
                        $max = $data[$max] ?? null;
                    }
                    if (($max === null || $max === '') && isset($rule['max_field']) && is_string($rule['max_field']) && array_key_exists($rule['max_field'], $data)) {
                        $max_label = $resolve_field_label($rule['max_field'], $rules);
                        $max = $data[$rule['max_field']] ?? null;
                    }
                    if ($max !== null && $max !== '' && is_numeric($max) && $numeric_value > (float) $max) {
                        MessagesHandler::addError(
                            'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be less than or equal to ' . $max_label,
                            $field_name
                        );
                        continue;
                    }

                    $step = $form_params['step'] ?? null;
                    if ($step !== null && $step !== '' && $step !== 'any' && is_numeric($step)) {
                        $step_value = (float) $step;
                        if ($step_value > 0) {
                            $base = ($min !== null && $min !== '' && is_numeric($min)) ? (float) $min : 0.0;
                            $remainder = fmod($numeric_value - $base, $step_value);
                            $epsilon = 1.0E-9;
                            if (abs($remainder) > $epsilon && abs($remainder - $step_value) > $epsilon) {
                                MessagesHandler::addError(
                                    'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be a multiple of ' . $step,
                                    $field_name
                                );
                                continue;
                            }
                        }
                    }
                    continue;
                }

                if ($type === 'bool') {
                    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($bool === null) {
                        MessagesHandler::addError('Invalid Boolean', $field_name);
                    }
                    continue;
                }

                if ($type === 'datetime' || $type === 'date' || $type === 'time') {
                    $parsed_value = ($type === 'time') ? $parse_time_value($value) : $parse_datetime_value($value);
                    if ($parsed_value === null) {
                        MessagesHandler::addError('Invalid Date', $field_name);
                        continue;
                    }

                    $min = $form_params['min'] ?? null;
                    $min_label = $min;
                    $min_value = $min;
                    if ($min !== null && $min !== '' && is_string($min) && array_key_exists($min, $data)) {
                        $min_label = $resolve_field_label($min, $rules);
                        $min_value = $data[$min] ?? null;
                    }
                    if (($min_value === null || $min_value === '') && isset($rule['min_field']) && is_string($rule['min_field']) && array_key_exists($rule['min_field'], $data)) {
                        $min_label = $resolve_field_label($rule['min_field'], $rules);
                        $min_value = $data[$rule['min_field']] ?? null;
                    }
                    if ($min_value !== null && $min_value !== '') {
                        $parsed_min = ($type === 'time') ? $parse_time_value($min_value) : $parse_datetime_value($min_value);
                        if ($parsed_min !== null && $parsed_value < $parsed_min) {
                            MessagesHandler::addError(
                                'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be after or equal to ' . $min_label,
                                $field_name
                            );
                            continue;
                        }
                    }

                    $max = $form_params['max'] ?? null;
                    $max_label = $max;
                    $max_value = $max;
                    if ($max !== null && $max !== '' && is_string($max) && array_key_exists($max, $data)) {
                        $max_label = $resolve_field_label($max, $rules);
                        $max_value = $data[$max] ?? null;
                    }
                    if (($max_value === null || $max_value === '') && isset($rule['max_field']) && is_string($rule['max_field']) && array_key_exists($rule['max_field'], $data)) {
                        $max_label = $resolve_field_label($rule['max_field'], $rules);
                        $max_value = $data[$rule['max_field']] ?? null;
                    }
                    if ($max_value !== null && $max_value !== '') {
                        $parsed_max = ($type === 'time') ? $parse_time_value($max_value) : $parse_datetime_value($max_value);
                        if ($parsed_max !== null && $parsed_value > $parsed_max) {
                            MessagesHandler::addError(
                                'The field <b>' . ($rule['label'] ?? $field_name) . '</b> must be before or equal to ' . $max_label,
                                $field_name
                            );
                            continue;
                        }
                    }
                    continue;
                }

                if ($type === 'enum') {
                    if (!in_array($value, $rule['options'] ?? [], true)) {
                        MessagesHandler::addError(
                            'The field <b>' . ($rule['label'] ?? $field_name) . '</b> is invalid',
                            $field_name
                        );
                    }
                    continue;
                }

                if ($type === 'list') {
                    // list expects $value to be one of the keys in options
                    $key = is_scalar($value) ? (string) $value : '';
                    if (!array_key_exists($key, $rule['options'] ?? [])) {
                        MessagesHandler::addError('Invalid List', $field_name);
                    }
                    continue;
                }

                if ($rule_type === 'string' || $rule_type === 'text') {
                    $check_value = is_array($value) ? json_encode($value) : (string) $value;

                    $min_length = $form_params['minlength'] ?? ($form_params['min_length'] ?? null);
                    if (($min_length === null || $min_length === '') && isset($rule['min_length'])) {
                        $min_length = $rule['min_length'];
                    }
                    if (($min_length === null || $min_length === '') && isset($form_params['min'])) {
                        $min_length = $form_params['min'];
                    }
                    if ($min_length !== null && $min_length !== '' && is_numeric($min_length) && strlen($check_value) < (int) $min_length) {
                        MessagesHandler::addError(
                            'Field <b>' . ($rule['label'] ?? $field_name) . '</b> is too short. Min length is ' . (int) $min_length,
                            $field_name
                        );
                        continue;
                    }

                    $pattern = $form_params['pattern'] ?? ($rule['pattern'] ?? ($rule['regex'] ?? null));
                    if (is_string($pattern) && $pattern !== '') {
                        $regex = $pattern;
                        if ($pattern[0] !== '/' || strrpos($pattern, '/') === 0) {
                            $regex = '/' . str_replace('/', '\\/', $pattern) . '/';
                        }
                        if (@preg_match($regex, '') !== false && preg_match($regex, $check_value) !== 1) {
                            MessagesHandler::addError(
                                'The field <b>' . ($rule['label'] ?? $field_name) . '</b> format is invalid',
                                $field_name
                            );
                            continue;
                        }
                    }

                    $max_length = $form_params['maxlength'] ?? ($form_params['max_length'] ?? null);
                    if (($max_length === null || $max_length === '') && isset($rule['max_length'])) {
                        $max_length = $rule['max_length'];
                    }
                    if (($max_length === null || $max_length === '') && isset($form_params['max'])) {
                        $max_length = $form_params['max'];
                    }
                    if (($max_length === null || $max_length === '') && isset($rule['length'])) {
                        $max_length = $rule['length'];
                    }
                    if ($max_length !== null && $max_length !== '' && is_numeric($max_length) && strlen($check_value) > (int) $max_length) {
                        MessagesHandler::addError(
                            'Field <b>' . ($rule['label'] ?? $field_name) . '</b> is too long. Max length is ' . (int) $max_length,
                            $field_name
                        );
                    }
                    continue;
                }

                // Unknown types: no validation (by design)
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
