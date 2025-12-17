<?php
namespace Modules\Db2tables;

use App\{Get, Token};

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Structure editing services for DB2Tables module
 * Handles table and view structure modifications using Schema class
 */
class Db2tablesStructureServices
{
    /**
     * Preview table structure changes
     * 
     * Analyzes the submitted form data and returns the SQL statements that would be executed
     * without actually making any changes to the database
     * 
     * @param string $table_name The name of the table to modify
     * @param array $fields The array of field definitions
     * @return array Response with SQL statements that would be executed
     */
    public static function previewTableStructure($table_name, $fields)
    {
        $db2 = Db2tablesServices::getDb();

        try {
            // Get the current schema instance
            $schema = $db2->describes($table_name);
            
            // Get current table structure using the Schema class
            $current_fields = $schema['struct'];
            
            // Track changes
            $changes = [
                'add' => [],
                'modify' => [],
                'rename' => [],
                'drop' => [],
                'indexes' => []
            ];
            
            // Create a new schema instance for the modified structure
            $new_schema = Get::schema($table_name, $db2);
            
            // Track current primary keys and indexes
            $current_primary_keys = [];

            foreach ($current_fields as $field_info) {
                if ($field_info->Key === 'PRI') {
                    $current_primary_keys[] = $field_info->Field;
                }
            }
            
            // Process each field from the form
            $processed_fields = [];
            foreach ($fields as $field_data) {
                $field_name = $field_data['name'] ?? '';
                $original_name = $field_data['original_name'] ?? '';
                $field_type = $field_data['type'] ?? '';
                $field_length = $field_data['length'] ?? '';
                $field_default = $field_data['default'] ?? '';
                $field_null = $field_data['null'] ?? 'NOT NULL';
                $field_index = $field_data['index'] ?? '';
                $field_auto_increment = isset($field_data['auto_increment']) ? true : false;
                
                // Skip empty field names
                if (empty($field_name)) {
                    continue;
                }
                
                $processed_fields[] = $original_name ?: $field_name;
                
                // Add field to new schema based on type
                Db2tablesStructureServices::addFieldToSchema($new_schema, $field_data);
                
                // Determine if this is a new field, modified field, or unchanged field
                if (empty($original_name)) {
                    // New field
                    $changes['add'][] = [
                        'field' => $field_name,
                        'type' => $field_type,
                        'length' => $field_length
                    ];
                } elseif ($original_name !== $field_name) {
                    // Renamed field
                    $changes['rename'][] = [
                        'from' => $original_name,
                        'to' => $field_name
                    ];
                    // Check if also modified
                    if (isset($current_fields[$original_name])) {
                        if (self::isFieldModifiedSqlite($field_data, $current_fields[$original_name])) {
                            $changes['modify'][] = [
                                'field' => $field_name,
                                'type' => $field_type,
                                'length' => $field_length,
                                'null' => $field_null,
                                'default' => $field_default,
                                'auto_increment' => $field_auto_increment
                            ];
                        }
                    }
                } elseif (isset($current_fields[$original_name])) {
                    // Check if field is modified
                    if (self::isFieldModifiedSqlite($field_data, $current_fields[$original_name])) {
                        $changes['modify'][] = [
                            'field' => $field_name,
                            'type' => $field_type,
                            'length' => $field_length,
                            'null' => $field_null,
                            'default' => $field_default,
                            'auto_increment' => $field_auto_increment
                        ];
                    }
                }
                
                // Handle indexes
                if (!empty($field_index)) {
                    // Check if this is a new index
                    $is_new_index = false;
                    
                    if ($field_index === 'PRIMARY') {
                        // Check if field is already a primary key
                        if (!in_array($field_name, $current_primary_keys)) {
                            $is_new_index = true;
                        }
                    } else {
                        // For other index types, we'd need to check the actual indexes
                        // For now, assume it's new if specified
                        $is_new_index = true;
                    }
                    
                    if ($is_new_index) {
                        $changes['indexes'][] = [
                            'action' => 'add',
                            'type' => $field_index,
                            'field' => $field_name
                        ];
                    }
                }
            }
            
            // Check for fields to be deleted
            foreach ($current_fields as $field_name => $field) {
                if (!in_array($field_name, $processed_fields)) {
                    $changes['drop'][] = [
                        'field' => $field_name
                    ];
                }
            }
            
            // Generate preview SQL (simulate what Schema::modify() would do)
            $preview_sql = Db2tablesStructureServices::generatePreviewSql($table_name, $changes);
            
            return [
                'success' => true,
                'has_changes' => !empty($changes['add']) || !empty($changes['modify']) || 
                                !empty($changes['rename']) || !empty($changes['drop']) || 
                                !empty($changes['indexes']),
                'changes' => $changes,
                'sql' => $preview_sql
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error analyzing table structure: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Helper method to check if a field is modified comparing with SQLite structure
     */
    private static function isFieldModifiedSqlite($new_field_data, $current_field_info)
    {

        // Compare type
        $current_type = strtoupper($current_field_info->Type);
        $new_type = strtoupper($new_field_data['type'] ?? '');
        
        // Handle type with length (e.g., VARCHAR(255))
        if (!empty($new_field_data['length'])) {
            $new_type_full = $new_type . '(' . $new_field_data['length'] . ')';
        } else {
            $new_type_full = $new_type;
        }
        
        // For SQLite, INTEGER and INT are equivalent
        if (($current_type === 'INTEGER' && $new_type === 'INT') || 
            ($current_type === 'INT' && $new_type === 'INTEGER')) {
            // These are equivalent, don't count as modification
        } elseif ($current_type !== $new_type_full && $current_type !== $new_type) {
            return true;
        }
        
        // Compare NULL/NOT NULL
        $current_null = ($current_field_info->Null === 'YES') ? 'NULL' : 'NOT NULL';
        $new_null = $new_field_data['null'] ?? 'NOT NULL';
        if ($current_null !== $new_null) {
            return true;
        }
        
        // Compare default values
        $current_default = $current_field_info->Default;
        $new_default = $new_field_data['default'] ?? '';
        
        // Normalize default values for comparison
        if ($current_default === null && $new_default === '') {
            // Both are effectively empty - no change
        } elseif ($current_default === null && $new_default !== '') {
            // Default value added
            return true;
        } elseif ($current_default !== null && $new_default === '') {
            // Default value removed
            return true;
        } elseif ($current_default !== null && $new_default !== '') {
            // Both have values, compare them
            // Remove quotes for comparison if they exist
            $normalized_current = trim($current_default, "'\"");
            $normalized_new = trim($new_default, "'\"");
            
            // Handle special case where one has quotes and other doesn't
            if ($current_default === "''" && $new_default === "''") {
                // Same empty string default
            } elseif ($normalized_current !== $normalized_new) {
                return true;
            }
        }
        
        // Note: SQLite doesn't have auto_increment in the same way as MySQL
        // It uses INTEGER PRIMARY KEY AUTOINCREMENT
        // This would need to be handled differently based on your schema implementation
        
        return false;
    }
    
   /**
     * Update table structure
     * 
     * Handles modifications to the table structure using Schema class
     * 
     * @param string $table_name The name of the table to modify
     * @param array $fields The array of field definitions
     * @return array Response with success/error message
     */
    public static function updateTableStructure($table_name, $fields)
    {
        $db2 = Db2tablesServices::getDb();
        
        // Start a transaction
        $db2->begin();
        
        try {
            // Create a new schema instance
            $schema = Get::schema($table_name, $db2);
            
            // Check if table exists
            $table_exists = $schema->exists();
            
            // Process each field from the form and add to schema
            $processed_fields = [];
            
            foreach ($fields as $field_data) {
                $field_name = $field_data['name'] ?? '';
                $original_name = $field_data['original_name'] ?? '';
                
                // Skip empty field names
                if (empty($field_name)) {
                    continue;
                }
                
                $processed_fields[] = $field_name;
                
                // Add field to schema based on its type
                self::addFieldToSchema($schema, $field_data);
                
                // Handle indexes
                $field_index = $field_data['index'] ?? '';
                if (!empty($field_index)) {
                    if ($field_index === 'PRIMARY') {
                        // Primary key will be handled by set_primary_key at the end
                    } elseif ($field_index === 'UNIQUE') {
                        $schema->index($field_name . '_unique', [$field_name], true);
                    } elseif ($field_index === 'INDEX') {
                        $schema->index($field_name . '_idx', [$field_name], false);
                    }
                }
            }
            
            // Set primary keys
            $primary_keys = [];
            foreach ($fields as $field_data) {
                $field_name = $field_data['name'] ?? '';
                $field_index = $field_data['index'] ?? '';
                
                if ($field_index === 'PRIMARY' && !empty($field_name)) {
                    $primary_keys[] = $field_name;
                }
            }
            
            if (!empty($primary_keys)) {
                $schema->setPrimaryKey($primary_keys);
            }
            
            // Execute the appropriate operation
            if ($table_exists) {
                // Modify existing table
                $result = $schema->modify();
            } else {
                // Create new table
                $result = $schema->create();
            }
            
            if (!$result) {
                throw new \Exception($schema->last_error ?: 'Unknown error during table modification');
            }
            
            // Commit the transaction
            $db2->commit();
            
            return [
                'success' => true,
                'message' => 'Table structure updated successfully'
            ];
        } catch (\Exception $e) {
            // Rollback the transaction on error
            $db2->tearDown();
            
            return [
                'success' => false,
                'error' => 'Error updating table structure: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Add a field to the schema based on its type and properties
     * 
     * @param \App\SchemaMysql|\App\SchemaSqlite $schema The schema instance
     * @param array $field_data The field data from the form
     */
    public static function addFieldToSchema($schema, $field_data)
    {
        $field_name = $field_data['name'] ?? '';
        $field_type = strtolower($field_data['type'] ?? '');
        $field_length = $field_data['length'] ?? '';
        $field_default = $field_data['default'] ?? null;
        $field_null = ($field_data['null'] ?? 'NOT NULL') === 'NULL';
        $field_auto_increment = isset($field_data['auto_increment']) ? true : false;
        $field_after = $field_data['after'] ?? null;
        // Clean up default value
        if ($field_default !== null && $field_default !== '') {
            // Remove quotes if they exist for string defaults
            if (is_string($field_default) && 
                $field_default !== 'CURRENT_TIMESTAMP' && 
                $field_default !== 'NULL') {
                // If it's wrapped in quotes, keep it as is
                if (!preg_match('/^[\'"].*[\'"]$/', $field_default)) {
                    // Add quotes for string defaults
                    if (in_array($field_type, ['varchar', 'char', 'text', 'longtext', 'tinytext', 'mediumtext'])) {
                        $field_default = "'{$field_default}'";
                    }
                }
            }
        } else {
            $field_default = null;
        }
        
        // Handle auto-increment primary key
        if ($field_auto_increment && in_array($field_type, ['int', 'integer', 'bigint', 'smallint', 'tinyint'])) {
            $schema->id($field_name);
            return;
        }
        
        // Handle different field types
        switch ($field_type) {
            case 'int':
            case 'integer':
                $default_int = is_numeric($field_default) ? (int)$field_default : null;
                $schema->int($field_name, $field_null, $default_int, $field_after);
                break;
                
            case 'tinyint':
                $default_int = is_numeric($field_default) ? (int)$field_default : null;
                $schema->tinyint($field_name, $field_null, $default_int, $field_after);
                break;
                
            case 'varchar':
            case 'char':
                $length = $field_length ?: 255;
                // Remove quotes from default if present
                $clean_default = $field_default;
                if ($clean_default !== null) {
                    $clean_default = trim($clean_default, "'\"");
                }
                $schema->string($field_name, (int)$length, $field_null, $clean_default, $field_after);
                break;
                
            case 'text':
            case 'tinytext':
            case 'mediumtext':
                $schema->text($field_name, $field_null, $field_after);
                break;
                
            case 'longtext':
                $schema->longtext($field_name, $field_null, $field_after);
                break;
                
            case 'datetime':
                $schema->datetime($field_name, $field_null, $field_default, $field_after);
                break;
                
            case 'date':
                $schema->date($field_name, $field_null, $field_default, $field_after);
                break;
                
            case 'time':
                $schema->time($field_name, $field_null, $field_default, $field_after);
                break;
                
            case 'timestamp':
                $default_ts = $field_default ?: ($field_null ? null : 'CURRENT_TIMESTAMP');
                $schema->timestamp($field_name, $field_null, $default_ts, $field_after);
                break;
                
            case 'decimal':
            case 'numeric':
                // Parse precision and scale from length (e.g., "10,2")
                $precision = 10;
                $scale = 2;
                if ($field_length && strpos($field_length, ',') !== false) {
                    list($precision, $scale) = explode(',', $field_length);
                }
                $default_decimal = is_numeric($field_default) ? (float)$field_default : null;
                $schema->decimal($field_name, (int)$precision, (int)$scale, $field_null, $default_decimal, $field_after);
                break;
                
            case 'real':
            case 'double':
            case 'float':
                // For SQLite compatibility, use decimal
                $default_float = is_numeric($field_default) ? (float)$field_default : null;
                $schema->decimal($field_name, 10, 2, $field_null, $default_float, $field_after);
                break;
                
            case 'boolean':
            case 'bool':
                $default_bool = null;
                if ($field_default !== null) {
                    $default_bool = ($field_default === '1' || $field_default === 'true' || $field_default === true);
                }
                $schema->boolean($field_name, $field_null, $default_bool, $field_after);
                break;
                
            case 'blob':
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
                // Treat as text for now (SQLite compatibility)
                $schema->text($field_name, $field_null, $field_after);
                break;
                
            default:
                // For other types, use string as fallback
                $schema->string($field_name, 255, $field_null, $field_default, $field_after);
                break;
        }
    }
    
    /**
     * Check if a field's definition has changed
     * 
     * @param array $field_data The field data from the form
     * @param \App\FieldMysql $current_field The current field from schema
     * @return bool True if the field is modified, false otherwise
     */
    public static function isFieldModified($field_data, $current_field)
    {
        $new_type = strtolower($field_data['type'] ?? '');
        $new_length = $field_data['length'] ?? '';
        $new_null = ($field_data['null'] ?? 'NOT NULL') === 'NULL';
        $new_default = $field_data['default'] ?? null;
        $new_auto_increment = isset($field_data['auto_increment']) ? true : false;
        
        // Compare properties
        if ($new_type !== strtolower($current_field->type)) {
            return true;
        }
        
        if ($new_length != $current_field->length && !empty($new_length)) {
            return true;
        }
        
        if ($new_null !== $current_field->nullable) {
            return true;
        }
        
        if ($new_default != $current_field->default) {
            return true;
        }
        
        if ($new_auto_increment !== $current_field->auto_increment) {
            return true;
        }
        
        return false;
    }
    
   /**
     * Generate preview SQL based on changes
     */
    private static function generatePreviewSql($table_name, $changes)
    {
        $sql_parts = [];
        
        // Handle column additions
        foreach ($changes['add'] as $add) {
            $sql = "ADD COLUMN `{$add['field']}` {$add['type']}";
            if (!empty($add['length'])) {
                $sql .= "({$add['length']})";
            }
            if (!empty($add['null'])) {
                $sql .= " " . $add['null'];
            }
            if (!empty($add['default'])) {
                $sql .= " DEFAULT " . $add['default'];
            }
            if (!empty($add['auto_increment'])) {
                $sql .= " AUTO_INCREMENT";
            }
            $sql_parts[] = $sql;
        }
        
        // Handle column modifications
        foreach ($changes['modify'] as $modify) {
            $sql = "MODIFY COLUMN `{$modify['field']}` {$modify['type']}";
            if (!empty($modify['length'])) {
                $sql .= "({$modify['length']})";
            }
            
            // Add NULL/NOT NULL specification
            if (isset($modify['null'])) {
                $sql .= " " . $modify['null'];
            }
            
            // Add DEFAULT specification
            if (isset($modify['default']) && $modify['default'] !== '') {
                $sql .= " DEFAULT " . $modify['default'];
            }
            
            // Add AUTO_INCREMENT if needed
            if (!empty($modify['auto_increment'])) {
                $sql .= " AUTO_INCREMENT";
            }
            
            $sql_parts[] = $sql;
        }
        
        // Handle column renames
        foreach ($changes['rename'] as $rename) {
            // For SQLite, you can't rename columns directly with ALTER TABLE
            // This would need special handling based on your database type
            $sql_parts[] = "RENAME COLUMN `{$rename['from']}` TO `{$rename['to']}`";
        }
        
        // Handle column drops
        foreach ($changes['drop'] as $drop) {
            $sql_parts[] = "DROP COLUMN `{$drop['field']}`";
        }
        
        // Handle indexes
        foreach ($changes['indexes'] as $index) {
            if ($index['type'] === 'PRIMARY') {
                $sql_parts[] = "ADD PRIMARY KEY (`{$index['field']}`)";
            } elseif ($index['type'] === 'UNIQUE') {
                $sql_parts[] = "ADD UNIQUE INDEX `idx_{$index['field']}` (`{$index['field']}`)";
            } elseif ($index['type'] === 'INDEX') {
                $sql_parts[] = "ADD INDEX `idx_{$index['field']}` (`{$index['field']}`)";
            }
        }
        
        // Build final SQL
        if (empty($sql_parts)) {
            return '';
        }
        
        return "ALTER TABLE `{$table_name}` " . implode(', ', $sql_parts) . ";";
    }
    
    /**
     * Update a view definition
     * 
     * @param string $view_name The new name for the view
     * @param string $original_view_name The original name of the view
     * @param string $view_definition The SQL definition for the view
     * @param string $token The security token for validation
     * @return array Response with success/error message
     */
    public static function updateViewDefinition($view_name, $original_view_name, $view_definition, $token)
    {
        // Validate token
        if (!Token::checkValue($token, 'editView' . $original_view_name)) {
            return [
                'success' => false,
                'error' => 'Invalid security token'
            ];
        }
        
        // Check if view name is valid
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $view_name)) {
            return [
                'success' => false,
                'error' => 'View name can only contain letters, numbers, and underscores'
            ];
        }
        
        $db2 = Db2tablesServices::getDb();
        
        // If view name has changed, check if the new name already exists
        if ($view_name !== $original_view_name) {
            // Get all views using the database class method
            $views = $db2->getViews(false);
            
            // Check if the new name already exists
            if (in_array($view_name, $views)) {
                return [
                    'success' => false,
                    'error' => 'A view with this name already exists'
                ];
            }
        }
        
        // Parse the SQL to verify it's a valid SELECT statement
        try {
            $parser = new SQLParser($view_definition);
            
            // Check if there's only one query
            if ($parser->getQueryCount() !== 1) {
                return [
                    'success' => false,
                    'error' => 'Only one query is allowed in a view definition'
                ];
            }
            
            // Get the first query and check if it's a SELECT
            $query = $parser->getQueries()[0];
            if (!preg_match('/^\s*SELECT\s+/i', $query)) {
                return [
                    'success' => false,
                    'error' => 'Only SELECT queries are allowed in view definitions'
                ];
            }
            
            // Start transaction
            $db2->begin();
            
            try {
                // First, drop the original view if it exists
                $drop_query = "DROP VIEW IF EXISTS " . $db2->qn($original_view_name);
                $db2->query($drop_query);
                
                if ($db2->error) {
                    throw new \Exception($db2->last_error);
                }
                
                // Then create the view with the new name and definition
                $create_query = "CREATE VIEW " . $db2->qn($view_name) . " AS " . $view_definition;
                $result = $db2->query($create_query);
                
                if ($db2->error) {
                    throw new \Exception($db2->last_error);
                }
                
                // Commit transaction
                $db2->commit();
                
                // Success response
                $message = 'View definition updated successfully';
                if ($view_name !== $original_view_name) {
                    $message = 'View renamed and definition updated successfully';
                }
                
                return [
                    'success' => true,
                    'message' => $message
                ];
            } catch (\Exception $e) {
                // Rollback transaction
                $db2->tearDown();
                
                // If there was an error, try to restore the original view
                if ($view_name !== $original_view_name) {
                    try {
                        // Get the original view definition
                        $original_definition = $db2->getViewDefinition($original_view_name);
                        if ($original_definition) {
                            $restore_query = "CREATE VIEW " . $db2->qn($original_view_name) . " AS " . $original_definition;
                            $db2->query($restore_query);
                        }
                    } catch (\Exception $restoreEx) {
                        // Ignore restore errors
                    }
                }
                
                return [
                    'success' => false,
                    'error' => 'Error executing view definition: ' . $e->getMessage()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error parsing SQL: ' . $e->getMessage()
            ];
        }
    }

    public static function truncateTable($table_name) {
        $db2 = Db2tablesServices::getDb();
        $db2->truncateTable($table_name);
        if ($db2->error) {
            return ['success' => false, 'error' => $db2->last_error];
        }
        return ['success' => true];
    }

    public static function dropTable($table_name, $is_view = false) {
        $db2 = Db2tablesServices::getDb();
        if ($is_view) {
            $db2->dropView($table_name);
        } else {
            $db2->dropTable($table_name);
        }
        if ($db2->error) {
            return ['success' => false, 'error' => $db2->last_error];
        }
        return ['success' => true];
    }

    public static function exportTable($table_name, $format) {
        //@Todo
    }

    public static function renameTable($table_name, $new_name) {
        $db2 = Db2tablesServices::getDb();
        $new_name = trim($new_name);
        $db2->renameTable($table_name, $new_name);
        if ($db2->error) {
            return ['success' => false, 'error' => $db2->last_error, 'new_name' => $new_name];
        }
        return ['success' => true, 'new_name' => $new_name];
    }

    public function duplicateTable($table_name, $new_name) {
        //@Todo
    }

    public static function getFieldTypes() {
        $db2 = Db2tablesServices::getDb();
        $db_type = $db2->type;
        $field_types = [
            'mysql' => [
                'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'DECIMAL', 'FLOAT', 'DOUBLE',
                'CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT',
                'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR',
                'BINARY', 'VARBINARY', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB', 'LONGBLOB',
                'ENUM', 'SET', 'JSON', 'BOOL', 'BOOLEAN'
            ],
            'sqlite' => [
                'INTEGER', 'REAL', 'TEXT', 'BLOB', 'NULL',
                'DATE', 'DATETIME'
            ],
            'postgres' => [
                'SMALLINT', 'INTEGER', 'BIGINT', 'DECIMAL', 'NUMERIC',
                'REAL', 'DOUBLE PRECISION', 'SERIAL', 'BIGSERIAL',
                'CHAR', 'VARCHAR', 'TEXT',
                'TIMESTAMP', 'TIMESTAMPTZ', 'DATE', 'TIME', 'TIMETZ', 'INTERVAL',
                'BOOLEAN', 'POINT', 'LINE', 'LSEG', 'BOX', 'PATH', 'POLYGON', 'CIRCLE',
                'JSON', 'JSONB', 'UUID', 'BYTEA', 'MONEY', 'XML', 'INET', 'CIDR', 'MACADDR',
                'BIT', 'VARBIT', 'TSVECTOR', 'TSQUERY'
            ]
        ];
        return $field_types[$db_type];
    }
}