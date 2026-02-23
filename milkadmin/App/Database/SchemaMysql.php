<?php
namespace App\Database;

use App\{Config, Get};

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Database Schema Management Class
 * 
 * This class provides a fluent interface for creating and modifying MySQL or SQLite tables
 * programmatically. It allows for defining fields, indexes, and table properties
 * in a clean, object-oriented way.
 * 
 * @example
 * ```php
 * // Create a new users table
 * $schema = Get::schema('#__users');
 * $schema->id()
 *     ->string('username', 100)
 *     ->string('email', 255)
 *     ->string('password', 255)
 *     ->int('status', false, 1)
 *     ->timestamp('created_at')
 *     ->index('email', 'email')
 *     ->create();
 * ```
 *
 * @package     App
 * @version     1.0.0
 */

class SchemaMysql {

    public string $table;
    public string $last_error = '';
    
    private array $fields = [];
    private array $indices = [];
    private string $engine = 'InnoDB';
    private string $charset = 'utf8mb4';
    private string $collate = 'utf8mb4_unicode_ci';
    private ?array $primary_keys = [];
    private ?string $primary_key = null;  // keep for backward compatibility

    private array $differences = [];
    private \App\Database\MySql $db;
    private array $rename_fields = [];
    private ?bool $table_has_rows = null;

    public function __construct(string $table, ?\App\Database\MySql $db = null) {
        $this->table = $table;
        if ($db == null) {
            $this->db = Get::db();
        } else {
            $this->db = $db;
        }
    }

    /**
     * Creates an auto-incrementing primary key field
     * 
     * This method defines a primary key field that automatically increments with each new record.
     * By default, the field is named 'id', but you can specify a different name if needed.
     * 
     * @example
     * ```php
     * // Default primary key
     * $schema->id();
     * 
     * // Custom primary key name
     * $schema->id('user_id');
     * ```
     * 
     * @param string $name The name of the primary key field (default: 'id')
     * @return self Returns the Schema instance for method chaining
     */
    public function id(string $name = 'id'): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'int';
        $field->auto_increment = true;
        $field->primary_key = true;
        $this->primary_key = $name;  // backward compatibility
        $this->primary_keys = [$name];
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates an integer field
     * 
     * This method defines an INT field in the database table.
     * 
     * @example
     * ```php
     * // Required integer field
     * $schema->int('age');
     * 
     * // Nullable integer field
     * $schema->int('parent_id', true);
     * 
     * // Integer field with default value
     * $schema->int('status', false, 1);
     * 
     * // Integer field positioned after another field
     * $schema->int('sort_order', false, 0, 'id');
     * ```
     * 
     * @param string $name The name of the field
     * @param bool $null Whether the field can be NULL (default: true)
     * @param int|null $default The default value for the field (default: null)
     * @param string|null $after The field after which this field should be positioned (default: null)
     * @return self Returns the Schema instance for method chaining
     */
    public function int(string $name, bool $null = true, ?int $default = null, ?string $after = null, bool $unsigned = false): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'int';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $field->unsigned = $unsigned;
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates a tiny integer field
     * 
     * This method defines a TINYINT field in the database table.
     * TINYINT fields can store values from -128 to 127, or 0 to 255 if unsigned.
     * 
     * @example
     * ```php
     * // Boolean-like field (0 or 1)
     * $schema->tinyint('is_active', false, 1);
     * 
     * // Small numeric field
     * $schema->tinyint('priority', false, 5);
     * ```
     * 
     * @param string $name The name of the field
     * @param bool $null Whether the field can be NULL (default: true)
     * @param int|null $default The default value for the field (default: null)
     * @param string|null $after The field after which this field should be positioned (default: null)
     * @return self Returns the Schema instance for method chaining
     */
    public function tinyint(string $name, bool $null = true, ?int $default = null, ?string $after = null, bool $unsigned = false): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'tinyint';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $field->unsigned = $unsigned;
        $this->fields[$name] = $field;

        return $this;
    }

    /**
     * Creates a small integer field
     */
    public function smallint(string $name, bool $null = true, ?int $default = null, ?string $after = null, bool $unsigned = false): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'smallint';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $field->unsigned = $unsigned;
        $this->fields[$name] = $field;

        return $this;
    }

    /**
     * Creates a medium integer field
     */
    public function mediumint(string $name, bool $null = true, ?int $default = null, ?string $after = null, bool $unsigned = false): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'mediumint';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $field->unsigned = $unsigned;
        $this->fields[$name] = $field;

        return $this;
    }

    /**
     * Creates a big integer field
     */
    public function bigint(string $name, bool $null = true, ?int $default = null, ?string $after = null, bool $unsigned = false): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'bigint';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $field->unsigned = $unsigned;
        $this->fields[$name] = $field;

        return $this;
    }

    /**
     * Creates a string (VARCHAR) field
     *
     * This method defines a VARCHAR field in the database table.
     * VARCHAR fields are used for storing variable-length string data.
     *
     * @example
     * ```php
     * // Standard string field with default length (255)
     * $schema->string('title');
     *
     * // String field with custom length
     * $schema->string('username', 100);
     *
     * // Nullable string field
     * $schema->string('middle_name', 50, true);
     *
     * // String field with default value
     * $schema->string('status', 20, false, 'active');
     *
     * // String field positioned after another field
     * $schema->string('notes', 200, false, '', 'status');
     * ```
     *
     * @param string $name The name of the field
     * @param int $length The maximum length of the string (default: 255)
     * @param bool $null Whether the field can be NULL (default: true)
     * @param string|null $default The default value for the field (default: null)
     * @param string|null $after The field after which this field should be positioned (default: null)
     * @return self Returns the Schema instance for method chaining
     */
    public function string(string $name, int $length = 255, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'varchar';
        $field->length = $length;
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates a fixed-length CHAR field
     *
     * This method defines a CHAR field in the database table.
     * CHAR fields are used for storing fixed-length string data.
     *
     * @example
     * ```php
     * // Single character field
     * $schema->char('status', 1);
     *
     * // Fixed 10-character field
     * $schema->char('code', 10, false, 'UNKNOWN');
     * ```
     *
     * @param string $name The name of the field
     * @param int $length The fixed length of the string (default: 1)
     * @param bool $null Whether the field can be NULL (default: true)
     * @param string|null $default The default value for the field (default: null)
     * @param string|null $after The field after which this field should be positioned (default: null)
     * @return self Returns the Schema instance for method chaining
     */
    public function char(string $name, int $length = 1, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'char';
        $field->length = $length;
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates a text field
     * 
     * This method defines a TEXT field in the database table.
     * TEXT fields are used for storing large amounts of text data (up to 65,535 characters).
     * 
     * @example
     * ```php
     * // Required text field
     * $schema->text('content');
     * 
     * // Nullable text field
     * $schema->text('description', true);
     * 
     * // Text field positioned after another field
     * $schema->text('notes', false, 'content');
     * ```
     * 
     * @param string $name The name of the field
     * @param bool $null Whether the field can be NULL (default: true)
     * @param string|null $after The field after which this field should be positioned (default: null)
     * @return self Returns the Schema instance for method chaining
     */
    public function text(string $name, bool $null = true, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'text';
        $field->nullable = $null;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function tinytext(string $name, bool $null = true, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'tinytext';
        $field->nullable = $null;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function mediumtext(string $name, bool $null = true, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'mediumtext';
        $field->nullable = $null;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function longtext(string $name, bool $null = true, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'longtext';
        $field->nullable = $null;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function datetime(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'datetime';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function date(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'date';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function time(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'time';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function timestamp(string $name, bool $null = true, ?string $default = 'CURRENT_TIMESTAMP', ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'timestamp';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2, bool $null = true, ?float $default = null, ?string $after = null, bool $unsigned = false): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'decimal';
        $field->precision = $precision;
        $field->scale = $scale;
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $field->unsigned = $unsigned;
        $this->fields[$name] = $field;
        return $this;
    }

    public function boolean(string $name, bool $null = true, ?bool $default = false, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'tinyint';
        $field->length = 1;
        $field->nullable = $null;
        $field->default = $default ? 1 : 0;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function index(string $name, array $columns = [], bool $unique = false): self {
        if (empty($columns)) {
            $columns = [$name];
        }
        $this->indices[$name] = new IndexMysql($this->db, $name, $columns, $unique);
        return $this;
    }

    public function renameField(string $from, string $to): self {
        $this->rename_fields[$from] = $to;
        return $this;
    }

    public function removePrimaryKeys(): self {
        foreach ($this->primary_keys as $pk) {
            if (isset($this->fields[$pk])) {
                unset($this->fields[$pk]);
            }
        }
        $this->primary_keys = [];
        $this->primary_key = null;
        return $this;
    }

    public function setPrimaryKey(array $fields): self {
        foreach ($fields as $field) {
            if (isset($this->fields[$field])) {
                $this->fields[$field]->primary_key = true;
                $this->primary_keys[] = $field;
            }
        }
        return $this;
    }

    // CREATE / DROP
    public function create(): bool {
        $this->last_error = '';
        if (empty($this->fields)) {
            return false;
        }

        $sql = "CREATE TABLE ". $this->db->qn($this->table) . " (\n";
        // Fields
        $insertFields = [];
        $fields_sql = [];
        foreach ($this->fields as $field) {
            $insertFields[] = $field->toArray();
            $fields_sql[] = "  " . $field->toSql();
        }


        // Primary key handling
        if (!empty($this->primary_keys)) {
            $primary_keys = array_map([$this->db, 'qn'], $this->primary_keys);
            $fields_sql[] = "  PRIMARY KEY (" . implode(", ", $primary_keys) . ")";
        } elseif ($this->primary_key) {
            $fields_sql[] = "  PRIMARY KEY (" . $this->db->qn($this->primary_key) . ")";
        }

        // Indices
        foreach ($this->indices as $index) {
            $fields_sql[] = "  " . $index->toSql();
        }

        $sql .= implode(",\n", $fields_sql);
        $sql .= "\n) ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collate};";

        // Reset prefix for install flows where it may not be initialized yet.
        //@TODO 2025/06/12 - This likely works now even without resetting the prefix.
        $this->db->prefix = Config::get('prefix');

        $this->db->query($sql);
        if ($this->db->error) {
            $this->last_error = $this->db->last_error;
        }
        if (!$this->db->error) {
            $this->differences = ['action' => 'create', 'table' => $this->table, 'fields' => $insertFields];
            return true;
        }
        return false;

    }

    public function drop(): bool {
        if ($this->db->dropTable($this->table)) {
            $this->differences = ['action' => 'drop', 'table' => $this->table];
            return true;
        } else {
            return false;
        }
    }

   /**
     * ALTER TABLE METHODS - Improved version for MySQL
     */
    public function modify(bool $force_update = false): bool {
        $this->table_has_rows = null;

        // Get current structure
        $current_fields = $this->getCurrentFields();
        $current_indices = $this->getCurrentIndices();
         $diff = $this->checkDifferencesBetweenFields($current_fields);
        // Validate changes before proceeding
        if (!$this->validateModifications($current_fields) && !$force_update) {
            return false;
        }
        
        if ($this->alterTable($current_fields, $current_indices)) {
            $this->differences = ['action' => 'modify', 'fields' => $diff, 'table' => $this->table];
            return true;
        } else {
            return false;
        }
    }

    /**
     * Complex alter table updated to better handle field reordering
     */
    private function alterTable(array $current_fields, array $current_indices): bool {
        try {
            // Start transaction
            $this->db->query("START TRANSACTION");
            
            // 1. Prepare all ALTER commands
            $alter_commands = $this->prepareAlterCommands($current_fields, $current_indices);
            
            if (empty($alter_commands)) {
                $this->db->query("COMMIT");
                return true; // No changes required
            }
            
            // 2. Execute ALTER commands in sequence
            if (!$this->executeAlterCommands($alter_commands)) {
                throw new \Exception("Error during ALTER command execution: ". $this->last_error);
            }
            
            // 3. Optional: Second pass for reordering if needed
            // (uncomment if a complete reordering is desired after adding new fields)
            // if (!$this->reorder_fields_if_needed()) {
            //     throw new \Exception("Error during field reordering: ". $this->last_error);
            // }
            
            // 4. Manage indexes
            if (!$this->updateIndices($current_indices)) {
                throw new \Exception("Error during index update: ". $this->last_error);
            }
            
            // Commit transaction
            $this->db->query("COMMIT");
            return true;
            
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Analyzes differences between old and new structure
     * @param array $current_fields The current fields in the database
     * 
     * @return array Returns an array of differences
     */
    private function checkDifferencesBetweenFields(array $current_fields): array {
        $results = [];
        
        // Find fields that exist in both structures
        foreach ($current_fields as $name => $old_field) {
            if (isset($this->fields[$name])) {
                $new_field = $this->fields[$name];
                $add = [];
                
                // Check whether the field was modified
                if (!$new_field->compare($old_field)) {
                    foreach ($new_field as $key=>$value) {
                        if ($old_field->$key != $value) {
                            $add['old'][$key] = $old_field->$key;
                            $add['new'][$key] = $value;
                        }
                        $add['action'] = 'update';
                }
                }
            
            } else {
                $add['action'] = $old_field;
            }
            if ($add != []) {
                $results[$name] = $add;
            }
        }
        
        // Find new fields
        foreach ($this->fields as $name => $new_field) {
            if (!isset($current_fields[$name])) {
                $results['new_fields'][$name] = $new_field;
            }
        }
       
        return $results;
    }

    /**
     * Returns the differences analyzed during the last operation
     */
    public function getFieldDifferences(): array {
        return $this->differences;
    }

    /**
     * MySQL schema fix - ALTER TABLE handling improvements
     * 
     * Issues addressed:
     * 1. Incorrect ALTER command ordering (ADD must run before MODIFY)
     * 2. Unnecessary MODIFY statements for unchanged fields
     * 3. Correct placement of new fields using AFTER clause
     * 4. Detailed debug traces for troubleshooting
     * 
     * Key methods involved in SchemaMysql:
     * - prepareAlterCommands()
     * - prepareModifyCommands()
     * - executeAlterCommands()
     * - reorder_fields_if_needed()
     * - alterTable()
     */

    /**
     * Prepare all required ALTER commands - corrected debug version
     */
    private function prepareAlterCommands(array $current_fields, array $current_indices): array {
        $alter_commands = [];
        $debug_info = [];
        $deferred_auto_increment_fields = [];
        $table_has_rows = null;
        $rename_from = array_keys($this->rename_fields);
        $rename_to = array_values($this->rename_fields);
        
        // DEBUG: initial state
        $debug_info[] = "=== PREPARE ALTER COMMANDS DEBUG ===";
        $debug_info[] = "Current fields: " . implode(', ', array_keys($current_fields));
        $debug_info[] = "Defined fields: " . implode(', ', array_keys($this->fields));
        
        // 1. Drop indexes that will be recreated (to avoid conflicts)
        foreach ($current_indices as $name => $index) {
            if (!isset($this->indices[$name])) {
                $alter_commands[] = "DROP INDEX " . $this->db->qn($name);
                $debug_info[] = "DROP INDEX: $name";
            } elseif (isset($this->indices[$name]) && !$index->compare($this->indices[$name])) {
                $alter_commands[] = "DROP INDEX " . $this->db->qn($name);
                $debug_info[] = "DROP INDEX (for modification): $name";
            }
        }
        
        // 2. Primary key handling (drop if needed)
        $current_primary_keys = array_keys(array_filter($current_fields, fn($f) => $f->primary_key));
        $new_primary_keys = !empty($this->primary_keys) ? $this->primary_keys : ($this->primary_key ? [$this->primary_key] : []);
        
        if ($current_primary_keys !== $new_primary_keys) {
            if (!empty($current_primary_keys)) {
                // Remove AUTO_INCREMENT from old PK columns before dropping PK
                // MySQL requires AUTO_INCREMENT columns to always be a KEY
                foreach ($current_primary_keys as $pk_name) {
                    if (isset($current_fields[$pk_name]) && $current_fields[$pk_name]->auto_increment) {
                        $old_field = clone $current_fields[$pk_name];
                        $old_field->auto_increment = false;
                        $alter_commands[] = "MODIFY COLUMN " . $old_field->toSql();
                        $debug_info[] = "REMOVE AUTO_INCREMENT from: $pk_name (before DROP PK)";
                    }
                }
                $alter_commands[] = "DROP PRIMARY KEY";
                $debug_info[] = "DROP PRIMARY KEY";
            }
        }
        
        // 3. Drop removed fields
        foreach ($current_fields as $name => $field) {
            if (!isset($this->fields[$name]) && !$field->primary_key && !in_array($name, $rename_from, true)) {
                $alter_commands[] = "DROP COLUMN " . $this->db->qn($name);
                $debug_info[] = "DROP COLUMN: $name";
            }
        }
        
        // 4. Add new fields with correct placement
        $current_field_names = array_keys($current_fields);
        $defined_field_order = array_keys($this->fields);
        
        foreach ($this->fields as $name => $field) {
            if (!isset($current_fields[$name]) && !in_array($name, $rename_to, true)) {
                $debug_info[] = "New field to add: $name";
                
                // Find where to place the new field
                $index = array_search($name, $defined_field_order);
                $position_clause = "";
                
                if ($index !== false) {
                    // Find the previous field that already exists in the table
                    $after_field = null;
                    for ($i = $index - 1; $i >= 0; $i--) {
                        if (isset($current_fields[$defined_field_order[$i]])) {
                            $after_field = $defined_field_order[$i];
                            $debug_info[] = "  - Place after: $after_field";
                            break;
                        }
                    }
                    
                    if ($after_field) {
                        $position_clause = " AFTER " . $this->db->qn($after_field);
                    } elseif ($index === 0) {
                        $position_clause = " FIRST";
                        $debug_info[] = "  - Place as FIRST";
                    }
                }
                
                // Temporarily save original AFTER value
                $original_after = $field->after;
                $original_auto_increment = $field->auto_increment;
                $original_default = $field->default;
                $defer_auto_increment = $original_auto_increment && in_array($name, $new_primary_keys, true);

                // MySQL requires AUTO_INCREMENT to be indexed.
                // With ALTER commands executed one by one, PK may be added later,
                // so we defer AUTO_INCREMENT to a subsequent MODIFY.
                if ($defer_auto_increment) {
                    $field->auto_increment = false;
                }

                // In strict mode MySQL can fail with "Incorrect date value: '0000-00-00'"
                // when adding NOT NULL temporal fields without explicit defaults on non-empty tables.
                if ($this->shouldUseTemporaryDefaultForAdd($field)) {
                    if ($table_has_rows === null) {
                        $table_has_rows = $this->tableHasRows();
                    }
                    if ($table_has_rows) {
                        $temporary_default = $this->getTemporaryDefaultForAdd($field);
                        if ($temporary_default !== null) {
                            $field->default = $temporary_default;
                            $debug_info[] = "  - Temporary default for {$name}: {$temporary_default}";
                        }
                    }
                }

                $field->after = null; // Avoid duplicate AFTER clause
                
                $sql = "ADD COLUMN " . $field->toSql() . $position_clause;
                $alter_commands[] = $sql;
                $debug_info[] = "  - SQL: $sql";

                if ($defer_auto_increment) {
                    $deferred_auto_increment_fields[] = [
                        'name' => $name,
                        'position_clause' => $position_clause
                    ];
                    $debug_info[] = "  - AUTO_INCREMENT deferred for: $name";
                }
                
                // Restore original AFTER value
                $field->after = $original_after;
                $field->auto_increment = $original_auto_increment;
                $field->default = $original_default;
            }
        }

        // 4.5. Rename fields
        foreach ($this->rename_fields as $from => $to) {
            if (!isset($current_fields[$from]) || !isset($this->fields[$to])) {
                continue;
            }
            $field = $this->fields[$to];
            $sql = "CHANGE COLUMN " . $this->db->qn($from) . " " . $field->toSql();
            $alter_commands[] = $sql;
        }
        
        // 5. Prepare MODIFY commands only for fields that need changes
        $modify_commands = $this->prepareModifyCommands($current_fields);
        if (!empty($modify_commands)) {
            $debug_info[] = "MODIFY commands: " . count($modify_commands);
            $alter_commands = array_merge($alter_commands, $modify_commands);
        }
        
        // 6. Recreate primary key if needed
        if ($current_primary_keys !== $new_primary_keys && !empty($new_primary_keys)) {
            $alter_commands[] = "ADD PRIMARY KEY (`" . implode("`, `", $new_primary_keys) . "`)";
            $debug_info[] = "ADD PRIMARY KEY: " . implode(', ', $new_primary_keys);
        }

        // 7. Apply AUTO_INCREMENT after PK (required with single-step ALTER execution)
        foreach ($deferred_auto_increment_fields as $field_info) {
            $field_name = $field_info['name'];
            if (!isset($this->fields[$field_name])) {
                continue;
            }

            $field = $this->fields[$field_name];
            $original_after = $field->after;
            $field->after = null;

            $alter_commands[] = "MODIFY COLUMN " . $field->toSql() . $field_info['position_clause'];
            $debug_info[] = "MODIFY AUTO_INCREMENT: {$field_name}";

            $field->after = $original_after;
        }
        
        return $alter_commands;
    }

    /**
     * Prepare MODIFY commands for changes and reordering - corrected version
     */
    private function prepareModifyCommands(array $current_fields): array {
        $modify_commands = [];
        $debug_info = [];
        
        // Get current and desired field order
        $current_field_order = $this->getCurrentFieldOrder();
        $defined_field_order = array_keys($this->fields);
        
        // Determine whether reordering is required
        $needs_reordering = $this->needsFieldReordering($current_fields);
        
        $debug_info[] = "=== PREPARE MODIFY COMMANDS DEBUG ===";
        $debug_info[] = "Current order: " . implode(', ', $current_field_order);
        $debug_info[] = "Desired order: " . implode(', ', $defined_field_order);
        $debug_info[] = "Reordering required: " . ($needs_reordering ? 'YES' : 'NO');
        
        // Handle field changes and reordering
        foreach ($this->fields as $field_name => $field) {
            if (!isset($current_fields[$field_name])) {
                continue; // Field is new and already added
            }
            
            $needs_modify = false;
            $position_clause = "";
            
            // Check whether the field needs type/attribute changes
            if (!$field->compare($current_fields[$field_name])) {
                $needs_modify = true;
                $debug_info[] = "Field $field_name: type/attribute modification required";
            }
            
            // Check whether field repositioning is needed ONLY when global reordering is required
            if ($needs_reordering) {
                // Find the correct position for this field
                $index = array_search($field_name, $defined_field_order);
                
                if ($index !== false) {
                    // Determine which field this should be placed after
                    $after_field = null;
                    for ($i = $index - 1; $i >= 0; $i--) {
                        // Find the previous field that exists in current table
                        if (isset($current_fields[$defined_field_order[$i]])) {
                            $after_field = $defined_field_order[$i];
                            break;
                        }
                    }
                    
                    // Check whether current position differs from desired position
                    $current_index = array_search($field_name, $current_field_order);
                    $current_after = $current_index > 0 ? $current_field_order[$current_index - 1] : null;
                    
                    if ($after_field !== $current_after) {
                        $position_clause = $after_field ? " AFTER " . $this->db->qn($after_field) : " FIRST";
                        $needs_modify = true;
                        $debug_info[] = "Field $field_name: repositioning required $position_clause";
                    } elseif ($index === 0 && $current_index !== 0) {
                        // Field should be first but currently is not
                        $position_clause = " FIRST";
                        $needs_modify = true;
                        $debug_info[] = "Field $field_name: must be FIRST";
                    }
                }
            }
            
            // Add MODIFY command only if required
            if ($needs_modify) {
                $sql = "MODIFY COLUMN " . $field->toSql() . $position_clause;
                $modify_commands[] = $sql;
                $debug_info[] = "  - MODIFY SQL: $sql";
            } else {
                $debug_info[] = "Field $field_name: no changes required";
            }
        }

        return $modify_commands;
    }

    /**
     * Execute ALTER commands one at a time to identify the exact failing command
     */
    private function executeAlterCommands(array $alter_commands): bool {
        if (empty($alter_commands)) {
            return true;
        }

        $this->db->prefix = Config::get('prefix');
        $table_qn = $this->db->qn($this->table);

        foreach ($alter_commands as $command) {
            $sql = "ALTER TABLE {$table_qn} {$command};";
            $this->db->query($sql);
            if ($this->db->error) {
                $this->last_error = $this->db->last_error . " | " . $command;
                return false;
            }
        }

        return true;
    }

    private function shouldUseTemporaryDefaultForAdd(FieldMysql $field): bool
    {
        if ($field->nullable || $field->default !== null || $field->auto_increment) {
            return false;
        }

        return in_array(strtolower((string) $field->type), ['date', 'datetime', 'time'], true);
    }

    private function getTemporaryDefaultForAdd(FieldMysql $field): ?string
    {
        $type = strtolower((string) $field->type);
        if ($type === 'date') {
            return '1970-01-01';
        }
        if ($type === 'datetime') {
            return '1970-01-01 00:00:00';
        }
        if ($type === 'time') {
            return '00:00:00';
        }

        return null;
    }

    private function tableHasRows(): bool
    {
        if ($this->table_has_rows !== null) {
            return $this->table_has_rows;
        }

        try {
            $this->db->prefix = Config::get('prefix');
            $result = $this->db->query('SELECT 1 FROM ' . $this->db->qn($this->table) . ' LIMIT 1');
            $this->table_has_rows = is_object($result) && method_exists($result, 'num_rows') && $result->num_rows() > 0;
        } catch (\Throwable $e) {
            $this->table_has_rows = false;
        }

        return $this->table_has_rows;
    }

    /**
     * Check whether field reordering is required
     */
    private function needsFieldReordering(array $current_fields): bool {
        $current_order = $this->getCurrentFieldOrder();
        $defined_order = array_keys($this->fields);
        
        // Keep only fields that exist in both definitions
        $common_fields_current = array_intersect($current_order, $defined_order);
        $common_fields_defined = array_intersect($defined_order, $current_order);
        
        return $common_fields_current !== $common_fields_defined;
    }

    /**
     * Update indexes (for both modes)
     */
    private function updateIndices(array $current_indices): bool {
        // Add new indexes or recreate modified ones
        foreach ($this->indices as $name => $index) {
            if (!isset($current_indices[$name])) {
                // New index
                $sql = "ALTER TABLE " . $this->db->qn($this->table) . " ADD " . $index->toSql();
                $this->db->query($sql);
                if ($this->db->error) {
                    $this->last_error = $this->db->last_error;
                    return false;
                }
            } elseif (!$index->compare($current_indices[$name])) {
                // Modified index (already dropped during ALTER commands)
                $sql = "ALTER TABLE " . $this->db->qn($this->table) . " ADD " . $index->toSql();
                $this->db->query($sql);
                if ($this->db->error) {
                    $this->last_error = $this->db->last_error;
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Validate table structure before applying modifications
     */
    private function validateModifications(array $current_fields): bool {
        // Ensure required fields are not removed when they still contain data
        foreach ($current_fields as $name => $field) {
            if (!isset($this->fields[$name]) && !$field->nullable && !$field->primary_key) {
                // Check if the field contains non-null data
                $result = $this->db->query("SELECT COUNT(*) as count FROM " . $this->db->qn($this->table) . " WHERE " . $this->db->qn($name) . " IS NOT NULL LIMIT 1");
                if ($result) {
                    $row = $result->fetch_object();
                    if ($row->count > 0) {
                        $this->last_error = "Cannot remove field '{$name}': it contains non-null data";
                        return false;
                    }
                }
            }
        }
        
        // Check type compatibility for modifications
        foreach ($this->fields as $name => $field) {
            if (isset($current_fields[$name])) {
                if (!$this->areTypesCompatible($current_fields[$name], $field)) {
                    $this->last_error = "Field '{$name}' modification failed: incompatible types ({$current_fields[$name]->type} -> {$field->type})";
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Check whether two types are compatible for a modification
     */
    private function areTypesCompatible(FieldMysql $old_field, FieldMysql $new_field): bool {
        // Type compatibility map
        $compatible_types = [
            'tinyint' => ['tinyint', 'smallint', 'int', 'bigint'],
            'smallint' => ['smallint', 'int', 'bigint'],
            'int' => ['int', 'bigint'],
            'bigint' => ['bigint'],
            'char' => ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'],
            'varchar' => ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'],
            'tinytext' => ['tinytext', 'text', 'mediumtext', 'longtext'],
            'text' => ['text', 'mediumtext', 'longtext'],
            'mediumtext' => ['mediumtext', 'longtext'],
            'longtext' => ['longtext'],
            'decimal' => ['decimal', 'double', 'float'],
            'float' => ['float', 'double'],
            'double' => ['double']
        ];

        $old_type = strtolower($old_field->type);
        $new_type = strtolower($new_field->type);

        // Same type is always compatible
        if ($old_type === $new_type) {
            return true;
        }

        // Check compatibility using the map
        if (isset($compatible_types[$old_type])) {
            return in_array($new_type, $compatible_types[$old_type]);
        }

        return false;
    }

    public function exists(): bool {
        $result = $this->db->query("SHOW TABLES LIKE '{$this->table}'");
        return ($result !== false) ? $result->num_rows() > 0 : false;
    }

    public function getFields(): array {
        return $this->fields;
    }

    public function getLastError() {
        return $this->last_error;   
    }

    private function getCurrentFields(): array {
        $fields = [];
        $primary_keys = $this->getCurrentPrimaryKeys();
        $result = $this->db->query("SHOW FULL COLUMNS FROM " . $this->db->qn($this->table));
        while ($row = $result->fetch_object()) {
            $field = new FieldMysql($row->Field, $this->db);
            
            // Parse type and length/precision
            if (preg_match('/^(\w+)(?:\((.*?)\))?/', $row->Type, $matches)) {
                $field->type = $matches[1];
                if (isset($matches[2])) {
                    if (strpos($matches[2], ',') !== false) {
                        list($precision, $scale) = explode(',', $matches[2]);
                        $field->precision = (int)$precision;
                        $field->scale = (int)$scale;
                    } else {
                        $field->length = (int)$matches[2];
                    }
                }
            }

            $field->nullable = ($row->Null === 'YES');
            $field->default = $row->Default;
            $field->unsigned = (stripos((string) $row->Type, 'unsigned') !== false);
            $field->auto_increment = (strpos($row->Extra, 'auto_increment') !== false);
            $field->primary_key = in_array($row->Field, $primary_keys, true);

            $fields[$row->Field] = $field;
        }
        return $fields;
    }

    private function getCurrentPrimaryKeys(): array {
        $primary_keys = [];
        $result = $this->db->query("SHOW INDEX FROM " . $this->db->qn($this->table) . " WHERE Key_name = 'PRIMARY'");
        if (!$result) {
            return $primary_keys;
        }

        while ($row = $result->fetch_object()) {
            $primary_keys[] = $row->Column_name;
        }

        return $primary_keys;
    }

    private function getCurrentIndices(): array {
        $indices = [];
        $result = $this->db->query("SHOW INDEX FROM " . $this->db->qn($this->table));
        
        while ($row = $result->fetch_object()) {
            if ($row->Key_name === 'PRIMARY') {
                continue;
            }

            if (!isset($indices[$row->Key_name])) {
                $indices[$row->Key_name] = new IndexMysql(
                    $this->db,
                    $row->Key_name, 
                    [$row->Column_name],
                    ($row->Non_unique == 0)
                );
            } else {
                $indices[$row->Key_name]->columns[] = $row->Column_name;
            }
        }

        return $indices;
    }

    private function getCurrentFieldOrder(): array {
        $field_order = [];
        $result = $this->db->query("SHOW COLUMNS FROM " . $this->db->qn($this->table));
        while ($row = $result->fetch_object()) {
            $field_order[] = $row->Field;
        }
        return $field_order;
    }
}
