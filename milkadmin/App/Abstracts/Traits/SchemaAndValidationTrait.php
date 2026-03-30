<?php
namespace App\Abstracts\Traits;

use App\{Get, MessagesHandler, ModelValidator, Sanitize};

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
        $schema   = Get::schema($this->table, $this->db);
        $primaries = $this->getPrimaries();
        $rules    = $this->getRules('sql');

        /**
         * Cast a default value to int|null safely.
         * Accepts int, numeric string, bool, null; returns null for anything else.
         */
        $toIntDefault = static function (mixed $v): ?int {
            if ($v === null || $v === '')          return null;
            if (is_bool($v))                       return (int) $v;
            if (is_int($v))                        return $v;
            if (is_numeric($v))                    return (int) $v;
            return null; // non-numeric string → discard
        };

        /**
         * Cast a default value to string|null safely.
         */
        $toStringDefault = static function (mixed $v): ?string {
            if ($v === null) return null;
            return (string) $v;
        };

        $nullable  = static fn(array $r): bool => (bool) ($r['nullable'] ?? false);
        $unsigned  = static fn(array $r): bool => (bool) ($r['unsigned'] ?? false);
        $isUnique  = static fn(array $r): bool => (bool) ($r['unique'] ?? false);
        $hasIndex  = static fn(array $r): bool => (bool) ($r['index'] ?? false);

        $skip_primary = false;
        if ( count($primaries) === 1 && isset($rules[$primaries[0]]) && $rules[$primaries[0]]['type'] === 'int') {
            $schema->id($primaries[0]);
            $skip_primary = true;
        }
        foreach ($rules as $name => $rule) {
            // skip single-column PK already handled above
            if ($skip_primary && count($primaries) === 1 && in_array($name, $primaries, true)) {
                continue;
            }

            // allow per-rule opt-out for MySQL
            if (!($rule['mysql'] ?? true)) {
                continue;
            }

            $type = $rule['type'] ?? 'string';

            switch ($type) {
                case 'id':
                    $schema->id($name);
                    break;
                case 'text':
                    $dbType = strtolower(trim((string) ($rule['db_type'] ?? 'text')));
                    $def    = $toStringDefault($rule['default'] ?? null);
                    $null   = $nullable($rule);

                    if ($dbType === 'tinytext'   && method_exists($schema, 'tinytext')) {
                        $schema->tinytext($name, $null, $def);
                    } elseif ($dbType === 'mediumtext' && method_exists($schema, 'mediumtext')) {
                        $schema->mediumtext($name, $null, $def);
                    } elseif ($dbType === 'longtext'   && method_exists($schema, 'longtext')) {
                        $schema->longtext($name, $null, $def);
                    } else {
                        $schema->text($name, $null, $def);
                    }
                    break;
                case 'string':
                    $schema->string(
                        $name,
                        (int) ($rule['length'] ?? 255),
                        $nullable($rule),
                        $toStringDefault($rule['default'] ?? null)
                    );
                    break;
                case 'int':
                    $schema->int(
                        $name,
                        $nullable($rule),
                        $toIntDefault($rule['default'] ?? null),
                        null,
                        $unsigned($rule)
                    );
                    break;

                case 'tinyint':
                    $schema->tinyint(
                        $name,
                        $nullable($rule),
                        $toIntDefault($rule['default'] ?? null),
                        null,
                        $unsigned($rule)
                    );
                    break;
                case 'float':
                    $schema->decimal(
                        $name,
                        (int) ($rule['length']    ?? 10),
                        (int) ($rule['precision'] ?? 2),
                        $nullable($rule),
                        $rule['default'] ?? null,   // decimal accepts numeric/null
                        null,
                        $unsigned($rule)
                    );
                    break;
                case 'bool':
                    $schema->boolean(
                        $name,
                        $nullable($rule),
                        $toIntDefault($rule['default'] ?? null) // stored as 0/1
                    );
                    break;
                case 'date':
                    $schema->date($name,  $nullable($rule), $toStringDefault($rule['default'] ?? null));
                    break;
                case 'datetime':
                    $schema->datetime($name, $nullable($rule), $toStringDefault($rule['default'] ?? null));
                    break;
                case 'timestamp':
                    $schema->timestamp($name, $nullable($rule), $toStringDefault($rule['default'] ?? null));
                    break;
                case 'time':
                    $schema->time($name, $nullable($rule), $toStringDefault($rule['default'] ?? null));
                    break;
                case 'array':
                    $schema->text($name, $nullable($rule), $toStringDefault($rule['default'] ?? null));
                    break;
                case 'list':
                    $isMultiple = !empty($rule['form-params']['multiple'])
                        || ($rule['form-params']['multiple'] ?? null) === 'multiple';

                    if ($isMultiple) {
                        $schema->text($name, $nullable($rule), $toStringDefault($rule['default'] ?? null));
                        break;
                    }

                    $maxLen     = 0;
                    $allInt     = true;
                    $sequential = true;
                    $prevKey    = null;

                    foreach ($rule['options'] as $key => $_) {
                        if (!is_int($key)) {
                            $allInt     = false;
                            $sequential = false;
                        } elseif ($sequential) {
                            if ($prevKey !== null && $prevKey + 1 !== $key) {
                                $sequential = false;
                            }
                            $prevKey = $key;
                        }
                        $maxLen = max($maxLen, strlen((string) $key));
                    }

                    if ($allInt && $sequential) {
                        // resolve a string default to its integer key
                        $rawDefault = $rule['default'] ?? null;
                        if (is_string($rawDefault) && $rawDefault !== '') {
                            $found = array_search($rawDefault, $rule['options'], true);
                            $rawDefault = ($found !== false) ? (int) $found : null;
                        }
                        $schema->int(
                            $name,
                            $nullable($rule),
                            $toIntDefault($rawDefault),
                            null,
                            $unsigned($rule)
                        );
                    } else {
                        $configured = isset($rule['length']) ? (int) $rule['length'] : 0;
                        $schema->string(
                            $name,
                            max($maxLen, $configured),
                            $nullable($rule),
                            $toStringDefault($rule['default'] ?? null)
                        );
                    }
                    break;
                case 'enum':
                    $maxLen = 0;
                    $allInt = true;

                    foreach ($rule['options'] as $value) {
                        $maxLen = max($maxLen, strlen((string) $value));
                        if (!is_int($value)) {
                            $allInt = false;
                        }
                    }

                    if ($allInt) {
                        $schema->int(
                            $name,
                            $nullable($rule),
                            $toIntDefault($rule['default'] ?? null),
                            null,
                            $unsigned($rule)
                        );
                    } else {
                        $configured = isset($rule['length']) ? (int) $rule['length'] : 0;
                        $schema->string(
                            $name,
                            max($maxLen, $configured),
                            $nullable($rule),
                            $toStringDefault($rule['default'] ?? null)
                        );
                    }
                    break;
                default:
                    $schema->string($name, 255);
            }
            if ($isUnique($rule)) {
                $schema->index($name, [$name], true);
            } elseif ($hasIndex($rule)) {
                $schema->index($name, [$name]);
            }
        }
        foreach ($this->rule_builder->getRenameFields() as $from => $to) {
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
            if (isset($this->records_objects) && !empty($this->records_objects)) {
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
                $this->afterModifyTable();
                return true;
                } else {
                $this->last_error = $schema->getLastError();
                return false;
                }
        } else {
            if ($schema->create()) {
                $this->schema_field_differences = $schema->getFieldDifferences();
                $this->afterCreateTable();
                return true;
            } else {
                $this->last_error = $schema->getLastError() ?? '';
                return false;
            }
        }
        
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
            if ($db) {
                return (bool) $db->dropTable($this->table);
            }
            $this->last_error = Sanitize::input('Schema adapter not available for database type.', 'string');
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
        $this->db_type = $db->getType();
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

        if (empty($records_to_validate)) {
            return true;
        }

        $validator = new ModelValidator($rules);
        foreach ($records_to_validate as $record_info) {
            $data = (array) $record_info['data'];
            $validator->validate($this->getExpressionParameterNormalizerService()->normalize($this, $data));
            $this->validateCustomHandlers($data, $rules);
        }

        return !MessagesHandler::hasErrors();
    }

    /**
     * Esegue le validazioni custom tramite attributi #[Validate]
     *
     * @param array $data
     * @param array $rules
     * @return void
     */
    protected function validateCustomHandlers(array $data, array $rules): void
    {
        foreach ($rules as $field_name => $_) {
            if ($field_name === '___action') {
                continue;
            }
            $handler = $this->getMethodHandler($field_name, 'validate');
            if ($handler === null) {
                continue;
            }
            $error = $handler((object) $data);
            if ($error === false || (is_string($error) && trim($error) !== '')) {
                MessagesHandler::addError($error, $field_name);
            }
        }
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
    protected function initializeClass(string $class): string {
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
