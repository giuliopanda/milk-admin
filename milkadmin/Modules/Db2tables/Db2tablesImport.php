<?php
namespace Modules\Db2tables;

use App\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Import services for DB2Tables module - Multi-database version
 * Handles CSV import functionality for both MySQL and SQLite
 */
class Db2tablesImport
{
    /**
     * Database instance
     * @var \App\Database\MySql|\App\Database\SQLite
     */
    private $db;
    
    /**
     * Constructor
     * @param \App\Database\MySql|\App\Database\SQLite|null $db Database instance (default: secondary database)
     */
    public function __construct($db = null) {
        $this->db = $db ?: Db2tablesServices::getDb();
    }

    /**
     * Import CSV data into an existing table
     * 
     * @param string $file_path Path to the temporary CSV file
     * @param string $table_name Name of the existing table
     * @param array $field_mappings Associative array mapping CSV columns to table fields
     * @param bool $has_headers Whether the CSV file has headers
     * @param bool $truncate_before_import Whether to truncate table before import
     * @param string $csv_separator CSV field separator character
     * @param string $csv_enclosure CSV field enclosure character
     * @param string $csv_escape CSV escape character
     * @return array Response with success status and import information
     */
    public function importCsvToExistingTable($file_path, $table_name, $field_mappings, $has_headers = true, $truncate_before_import = true, $csv_separator = ',', $csv_enclosure = '"', $csv_escape = '\\') {
        
        // Truncate table if requested
        if ($truncate_before_import) {
            $this->db->query("DELETE FROM " . $this->db->qn($table_name));
            
            // Reset auto_increment for MySQL
            if ($this->db->type === 'mysql') {
                $this->db->query("ALTER TABLE " . $this->db->qn($table_name) . " AUTO_INCREMENT = 1");
            }
        }
        
        // Get table structure
        $table_structure = $this->db->getColumns($table_name);
        if (empty($table_structure)) {
            return [
                'success' => false,
                'error' => 'Table does not exist or is not accessible'
            ];
        }
        
        // Create a map of field names to their types
        $field_types = [];
        foreach ($table_structure as $field) {
            $field_types[$field->Field] = $field->Type;
        }
        
        // Preserve original mappings before filtering
        $original_field_mappings = $field_mappings;
        
        // Filter out unmapped fields
        $field_mappings = array_filter($field_mappings, function($table_field) {
            return !empty($table_field);
        });
        
        // Debug info
        $debug = [
            'original_mappings' => $original_field_mappings,
            'filtered_mappings' => $field_mappings,
            'database_type' => $this->db->type
        ];
        
        // Validate field mappings against table structure
        foreach ($field_mappings as $csv_field => $table_field) {
            if (!isset($field_types[$table_field])) {
                return [
                    'success' => false,
                    'error' => "Field '{$table_field}' does not exist in table '{$table_name}'"
                ];
            }
        }
        
        // Open the CSV file
        $file = fopen($file_path, 'r');
        if (!$file) {
            return [
                'success' => false,
                'error' => 'Could not open CSV file'
            ];
        }
        
        // Skip header row if present
        if ($has_headers) {
            fgetcsv($file, 0, $csv_separator, $csv_enclosure, $csv_escape);
        }
        
        // Prepare for batch inserts
        $batch_size = ($this->db->type === 'sqlite') ? 50 : 100; // SQLite has lower limits
        $total_rows = 0;
        $batch_rows = [];
        $inserted_rows = 0;
        $field_names = array_values($field_mappings);
        $field_names_str = implode(', ', array_map(function($field) {
            return $this->db->qn($field);
        }, $field_names));
        
        // Initialize debug arrays
        $debug['values'] = [];
        $debug['inserts'] = [];
        $debug['queries'] = [];
        
        // Read and insert data in batches
        while (($row = fgetcsv($file, 0, $csv_separator, $csv_enclosure, $csv_escape)) !== false) {
            $values = [];
            $params = [];
            $csv_values = []; // For debug
            
            // Map CSV values to their corresponding table fields
            foreach ($field_mappings as $csv_field => $table_field) {
                // Find the original index of the CSV field
                $original_csv_index = array_search($csv_field, array_keys($original_field_mappings));
                if ($original_csv_index !== false && isset($row[$original_csv_index])) {
                    $value = $row[$original_csv_index];
                    $csv_values[$table_field] = $value; // For debug
                    
                    // Handle different field types
                    if ($value === '' || $value === null) {
                        $values[] = '?';
                        $params[] = null;
                    } else {
                        $values[] = '?';
                        $params[] = $value;
                    }
                    
                    // Add debug values
                    $debug['values'][] = [
                        'original_csv_index' => $original_csv_index,
                        'csv_field' => $csv_field,
                        'table_field' => $table_field,
                        'value' => $value
                    ];
                }
            }
            
            $total_rows++;
            
            // Add to batch if values array is not empty
            if (!empty($values)) {
                $batch_rows[] = [
                    'values' => $values,
                    'params' => $params
                ];
                
                // Add debug info
                $debug['inserts'][] = [
                    'params' => $params,
                    'csv_values' => $csv_values,
                    'row' => $row
                ];
            }
            
            // Insert in batches
            if (count($batch_rows) >= $batch_size) {
                $insert_result = $this->executeBatchInsert($table_name, $field_names_str, $batch_rows, $debug);
                if (!$insert_result['success']) {
                    fclose($file);
                    return [
                        'success' => false,
                        'error' => $insert_result['error'],
                        'imported_rows' => $total_rows - count($batch_rows),
                        'debug' => $debug
                    ];
                }
                
                $inserted_rows += $insert_result['inserted'];
                $batch_rows = [];
            }
        }
        
        // Insert any remaining rows
        if (!empty($batch_rows)) {
            $insert_result = $this->executeBatchInsert($table_name, $field_names_str, $batch_rows, $debug);
            if (!$insert_result['success']) {
                fclose($file);
                return [
                    'success' => false,
                    'error' => $insert_result['error'],
                    'imported_rows' => $total_rows - count($batch_rows),
                    'debug' => $debug
                ];
            }
            
            $inserted_rows += $insert_result['inserted'];
        }
        
        fclose($file);
        
        return [
            'success' => true,
            'table_name' => $table_name,
            'imported_rows' => $total_rows,
            'inserted_rows' => $inserted_rows,
            'message' => "Successfully imported {$total_rows} rows into table '{$table_name}'",
            'debug' => $debug
        ];
    }

    /**
     * Execute batch insert based on database type
     */
    private function executeBatchInsert($table_name, $field_names_str, $batch_rows, &$debug) {
        if ($this->db->type === 'sqlite') {
            // SQLite: Use transactions for better performance
            $this->db->begin();
            $inserted = 0;

            try {
                foreach ($batch_rows as $idx => $row_data) {
                    $sql = "INSERT INTO " . $this->db->qn($table_name) . " (" . $field_names_str . ") VALUES (" . implode(', ', $row_data['values']) . ")";
                    $this->db->query($sql, $row_data['params']);

                    // Check for errors using the error flag, NOT the result value
                    // (INSERT queries correctly return false when successful)
                    if ($this->db->error) {
                        throw new \Exception($this->db->last_error);
                    }
                    $inserted++;
                }

                $this->db->commit();
                return ['success' => true, 'inserted' => $inserted];

            } catch (\Exception $e) {
                $this->db->tearDown();
                return ['success' => false, 'error' => 'Error inserting data: ' . $e->getMessage()];
            }

        } else {
            // MySQL: Use multi-value INSERT
            $values_parts = [];
            $all_params = [];

            foreach ($batch_rows as $row_data) {
                $values_parts[] = '(' . implode(', ', $row_data['values']) . ')';
                $all_params = array_merge($all_params, $row_data['params']);
            }

            $insert_query = "INSERT INTO " . $this->db->qn($table_name) . " (" . $field_names_str . ") VALUES " . implode(', ', $values_parts);

            // Save query for debug
            $debug['queries'][] = $insert_query;

            $insert_result = $this->db->query($insert_query, $all_params);
            if ($insert_result !== false && !$this->db->error) {
                return ['success' => true, 'inserted' => count($batch_rows)];
            } else {
                return ['success' => false, 'error' => 'Error inserting data ' . $this->db->getLastError()." <br>".$this->db->debugPreparedQuery($insert_query, $all_params)];
            }
        }
    }

    /**
     * Import CSV data to a new table
     * 
     * @param string $table_name The name of the new table to create
     * @param array $file_data The uploaded file data
     * @param bool $has_headers Whether the CSV file has headers
     * @param Db2tablesModel $model The DB2Tables model instance
     * @param string $primary_key_type Type of primary key to use ('auto_increment' or 'existing_field')
     * @param string $primary_key_field Field to use as primary key if $primary_key_type is 'existing_field'
     * @param string $unique_field_type Type of unique field ('unique' or 'primary') if $primary_key_type is 'existing_field'
     * @param string $csv_separator CSV separator
     * @param string $csv_enclosure CSV enclosure
     * @param string $csv_escape CSV escape character
     * @return array Response with success status and import information
     */
    public function importCsvToNewTable($table_name, $file_data, $has_headers = true, $model = null, $primary_key_type = 'auto_increment', $primary_key_field = '', $unique_field_type = 'unique', $csv_separator = ',', $csv_enclosure = '"', $csv_escape = '\\') {
        // Initialize model if not provided
        if ($model === null) {
            $model = new Db2tablesModel();
        }
        
        // Validate table name
        if (empty($table_name)) {
            return [
                'success' => false,
                'error' => 'Table name is required'
            ];
        }
        
        // Validate file
        if (empty($file_data) || !isset($file_data['tmp_name']) || !file_exists($file_data['tmp_name'])) {
            return [
                'success' => false,
                'error' => 'No valid file uploaded'
            ];
        }
        
        // Check file extension
        $file_extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'csv') {
            return [
                'success' => false,
                'error' => 'Only CSV files are supported'
            ];
        }
        
        // Handle special characters
        if ($csv_separator === '\\t') $csv_separator = "\t";
        
        // Open CSV file
        $file = fopen($file_data['tmp_name'], 'r');
        if (!$file) {
            return [
                'success' => false,
                'error' => 'Failed to open the uploaded file'
            ];
        }
        
        // Read first row to determine columns
        $first_row = fgetcsv($file, 0, $csv_separator, $csv_enclosure, $csv_escape);
        if (!$first_row) {
            fclose($file);
            return [
                'success' => false,
                'error' => 'CSV file is empty or invalid'
            ];
        }
        
        // Determine column names and types
        $columns = [];
        
        if ($has_headers) {
            // Use headers as column names
            foreach ($first_row as $index => $header) {
                // Store original header name
                $original_header = $header;
                
                // Sanitize header name for SQL
                $column_name = $this->sanitizeColumnName($header, $index);
                
                // Check for duplicates
                $base_name = $column_name;
                $counter = 1;
                while (in_array($column_name, array_column($columns, 'name'))) {
                    $column_name = $base_name . '_' . $counter++;
                    if (strlen($column_name) > 60) {
                        $column_name = substr($base_name, 0, 57 - strlen((string)$counter)) . '_' . $counter;
                    }
                }
                
                // Add column to list
                $columns[$index] = [
                    'name' => $column_name,
                    'original_name' => $original_header,
                    'type' => 'VARCHAR', // Default type, will be refined later
                    'length' => 255
                ];
            }
            
            // Read next row for data type analysis
            $data_row = fgetcsv($file, 0, $csv_separator, $csv_enclosure, $csv_escape);
        } else {
            // No headers, use generic column names
            $data_row = $first_row; // First row is already data
            foreach ($data_row as $index => $value) {
                // Generate column name
                $column_name = 'column_' . ($index + 1);
                
                // Add column to list
                $columns[$index] = [
                    'name' => $column_name,
                    'original_name' => $column_name,
                    'type' => 'VARCHAR', // Default type, will be refined later
                    'length' => 255
                ];
            }
        }
        
        // Analyze data types from sample rows (read up to 100 rows for better type detection)
        rewind($file);
        if ($has_headers) {
            fgetcsv($file, 0, $csv_separator, $csv_enclosure, $csv_escape); // Skip header
        }
        
        $sample_rows = [];
        $row_count = 0;
        while (($row = fgetcsv($file, 0, $csv_separator, $csv_enclosure, $csv_escape)) !== false && $row_count < 5000) {
            $sample_rows[] = $row;
            $row_count++;
        }
        
        // Analyze types based on sample data
        $this->analyzeColumnTypes($columns, $sample_rows);
        
        // Create table using Schema class
        $schema = Get::schema($table_name, $this->db);
        
        // Add primary key if requested
        if ($has_headers && $primary_key_type === 'auto_increment') {
            $primary_key = 'milk_id';
            // Ensure unique primary key name
            $count_primary_key = 1;
            while (in_array($primary_key, array_column($columns, 'name'))) {
                $primary_key = 'milk_id' . $count_primary_key;
                $count_primary_key++;
            }
            
            // Add auto-increment ID
            $schema->id($primary_key);
        }
        
        // Add columns to schema
        foreach ($columns as $column) {
            $this->addColumnToSchema($schema, $column);
        }
        
        // Create the table
        if (!$schema->create()) {
            fclose($file);
            return [
                'success' => false,
                'error' => 'Failed to create table: ' . $schema->last_error
            ];
        }
        
        // Rewind file to start importing data
        rewind($file);
        
        // Skip header if present
        if ($has_headers) {
            fgetcsv($file, 0, $csv_separator, $csv_enclosure, $csv_escape);
        }
        
        // Prepare for batch inserts
        $batch_size = ($this->db->type === 'sqlite') ? 50 : 100;
        $total_rows = 0;
        $batch_rows = [];
        $column_names = array_column($columns, 'name');
        $column_names_str = implode(', ', array_map(function($col) {
            return $this->db->qn($col);
        }, $column_names));

        // Initialize debug array
        $debug = [];

        // Read and insert data in batches
        while (($row = fgetcsv($file, 0, $csv_separator, $csv_enclosure, $csv_escape)) !== false) {
            $values = [];
            $params = [];

            foreach ($row as $index => $value) {
                if (isset($columns[$index])) {
                    if ($value === '' || $value === null) {
                        $values[] = '?';
                        $params[] = null;
                    } else {
                        $values[] = '?';
                        $params[] = $value;
                    }
                }
            }

            // Fill with NULL if row has fewer columns than expected
            while (count($values) < count($columns)) {
                $values[] = '?';
                $params[] = null;
            }

            $batch_rows[] = [
                'values' => $values,
                'params' => $params
            ];
            $total_rows++;

            // Insert in batches
            if (count($batch_rows) >= $batch_size) {
                $insert_result = $this->executeBatchInsert($table_name, $column_names_str, $batch_rows, $debug);

                if (!$insert_result['success']) {
                    fclose($file);
                    return [
                        'success' => false,
                        'error' => $insert_result['error'],
                        'imported_rows' => $total_rows - count($batch_rows)
                    ];
                }

                $batch_rows = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch_rows)) {
            $insert_result = $this->executeBatchInsert($table_name, $column_names_str, $batch_rows, $debug);

            if (!$insert_result['success']) {
                fclose($file);
                return [
                    'success' => false,
                    'error' => $insert_result['error'],
                    'imported_rows' => $total_rows - count($batch_rows)
                ];
            }
        }
        
        fclose($file);
        
        return [
            'success' => true,
            'table_name' => $table_name,
            'columns' => $columns,
            'imported_rows' => $total_rows,
            'message' => "Successfully imported {$total_rows} rows into table '{$table_name}'"
        ];
    }
    
    /**
     * Sanitize column name for SQL
     */
    private function sanitizeColumnName($name, $index) {
        $column_name = strtolower($name);
        $column_name = preg_replace('/[^a-z0-9_]/', '_', $column_name);
        $column_name = preg_replace('/_+/', '_', $column_name);
        $column_name = trim($column_name, '_');
        
        // Ensure column name is not empty and doesn't start with a number
        if (empty($column_name) || is_numeric(substr($column_name, 0, 1))) {
            $column_name = 'column_' . $index;
        }
        
        // Limit column name length to 60 characters
        if (strlen($column_name) > 60) {
            $column_name = substr($column_name, 0, 57);
        }
        
        return $column_name;
    }
    
    /**
     * Analyze column types based on sample data
     */
    private function analyzeColumnTypes(&$columns, $sample_rows) {
        foreach ($columns as $index => &$column) {
            $all_values = [];
            $max_length = 0;
            $is_int = true;
            $is_float = true;
            $is_date = true;
            $is_datetime = true;
            $has_null = false;
            
            // Collect all values for this column
            foreach ($sample_rows as $row) {
                if (isset($row[$index])) {
                    $value = trim($row[$index]);
                    if ($value === '' || $value === null) {
                        $has_null = true;
                        continue;
                    }
                    
                    $all_values[] = $value;
                    $max_length = max($max_length, strlen($value));
                    
                    // Check if integer
                    if (!preg_match('/^-?\d+$/', $value)) {
                        $is_int = false;
                    }
                    
                    // Check if float
                    if (!is_numeric($value)) {
                        $is_float = false;
                    }
                    
                    // Check if date
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                        $is_date = false;
                    }
                    
                    // Check if datetime
                    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                        $is_datetime = false;
                    }
                }
            }
            
            // Determine the best type
            if (empty($all_values)) {
                // All values are null, keep as VARCHAR
                $column['type'] = 'VARCHAR';
                $column['length'] = 255;
                $column['nullable'] = true;
            } elseif ($is_int) {
                // Check range for INT type
                $max_val = max(array_map('intval', $all_values));
                $min_val = min(array_map('intval', $all_values));
                
                if ($min_val >= -128 && $max_val <= 127) {
                    $column['type'] = 'TINYINT';
                } elseif ($min_val >= -2147483648 && $max_val <= 2147483647) {
                    $column['type'] = 'INT';
                } else {
                    $column['type'] = 'BIGINT';
                }
                $column['nullable'] = $has_null;
                unset($column['length']);
            } elseif ($is_float) {
                // Check for decimal places
                $max_decimals = 0;
                foreach ($all_values as $val) {
                    if (strpos($val, '.') !== false) {
                        $decimals = strlen(substr($val, strpos($val, '.') + 1));
                        $max_decimals = max($max_decimals, $decimals);
                    }
                }
                
                if ($max_decimals > 0) {
                    $column['type'] = 'DECIMAL';
                    $column['precision'] = 10;
                    $column['scale'] = min($max_decimals, 4); // Limit to 4 decimal places
                } else {
                    $column['type'] = 'INT';
                }
                $column['nullable'] = $has_null;
                unset($column['length']);
            } elseif ($is_datetime) {
                $column['type'] = 'DATETIME';
                $column['nullable'] = $has_null;
                unset($column['length']);
            } elseif ($is_date) {
                $column['type'] = 'DATE';
                $column['nullable'] = $has_null;
                unset($column['length']);
            } else {
                // String type
                if ($max_length > 255) {
                    $column['type'] = 'TEXT';
                    unset($column['length']);
                } else {
                    $column['type'] = 'VARCHAR';
                    $column['length'] = max(50, min($max_length + 20, 255)); // Add some buffer
                }
                $column['nullable'] = $has_null;
            }
        }
    }
    
    /**
     * Add column to schema based on analyzed type
     */
    private function addColumnToSchema($schema, $column) {
        $type = strtoupper($column['type']);
        $name = $column['name'];
        $nullable = $column['nullable'] ?? false;
        
        switch ($type) {
            case 'TINYINT':
                $schema->tinyint($name, $nullable);
                break;
                
            case 'INT':
            case 'INTEGER':
                $schema->int($name, $nullable);
                break;
                
            case 'BIGINT':
                $schema->int($name, $nullable); // Schema class doesn't have bigint, use int
                break;
                
            case 'DECIMAL':
                $precision = $column['precision'] ?? 10;
                $scale = $column['scale'] ?? 2;
                $schema->decimal($name, $precision, $scale, $nullable);
                break;
                
            case 'VARCHAR':
                $length = $column['length'] ?? 255;
                $schema->string($name, $length, $nullable);
                break;
                
            case 'TEXT':
                $schema->text($name, $nullable);
                break;
                
            case 'DATE':
                $schema->date($name, $nullable);
                break;
                
            case 'DATETIME':
                $schema->datetime($name, $nullable);
                break;
                
            case 'TIME':
                $schema->time($name, $nullable);
                break;
                
            default:
                // Default to string
                $schema->string($name, 255, $nullable);
                break;
        }
    }
}