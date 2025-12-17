<?php
namespace Modules\Db2tables;

use App\{Route, Get};

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Export services for DB2Tables module
 * Handles CSV and SQL export functionality
 */
class Db2tablesExportServices
{
    /**
     * Export table data to CSV
     * 
     * @param string $table_name The name of the table to export
     * @param string $query Optional query to export results instead of the entire table
     * @param Db2tablesModel $model The DB2Tables model instance
     */
    public function exportCsv($table_name, $query = '', $model = null) {
        if (empty($table_name)) {
            Route::redirect('?page=db2tables');
            return;
        }
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // If a query is provided, validate it with SQLParser
        if (!empty($query)) {
            // Parse the query with SQLParser
            $parser = new SQLParser($query);
            
            // Check if parsing was successful and it's a single query
            if ($parser->getQueryCount() !== 1) {
                // If not a single query, show error and exit
                header('Content-Type: text/plain; charset=utf-8');
                fwrite($output, "Error: Only single queries are supported for CSV export.");
                fclose($output);
                exit;
            }
            
            // Parse the query and check if it's a SELECT query
            $parsed_queries = $parser->parse();
            $parsed_query = $parsed_queries[0];
            
            if (!($parsed_query instanceof \App\Database\Query)) {
                // If not a SELECT query, show error and exit
                header('Content-Type: text/plain; charset=utf-8');
                fwrite($output, "Error: Only SELECT queries are supported for CSV export.");
                fclose($output);
                exit;
            }
            
            // It's a valid SELECT query, execute it
            $db2 = Db2tablesServices::getDb();
            
            // Get the SQL and parameters
            list($query_string, $params) = $parsed_query->get();
            
            // Execute the query
            $result = $db2->query($query_string);
            if ($result === false) {
                // If query fails, show error and exit
                header('Content-Type: text/plain; charset=utf-8');
                fwrite($output, "Error executing query: ". $query_string . "\n" . $db2->last_error);
                fclose($output);
                exit;
            }
            
            // Set filename based on query
            $filename = $table_name . '_query_export_' . date('Y-m-d') . '.csv';
            
            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            
            // Get column names from result
            $first_row = $result->fetch_assoc();
            if ($first_row) {
                $headers = array_keys($first_row);
                
                // Reset result pointer
                $result->data_seek(0);
                
                // Add column headers
                fputcsv($output, $headers, ',', '"', '\\');
                
                // Add data rows
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, $row, ',', '"', '\\');
                }
            } else {
                // No results
                fputcsv($output, ['No results found for the query'], ',', '"', '\\');
            }
            
            $result->free();
        } else {
            // No query provided, export entire table as before
            
            // Get table structure for column names
            $structure = $model->getTableStructure($table_name);
            
            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $table_name . '_export_' . date('Y-m-d') . '.csv');
            
            // Add column headers
            $headers = [];
            foreach ($structure as $field) {
                $headers[] = $field->Field;
            }
            fputcsv($output, $headers, ',', '"', '\\');
            
            // Stream data in chunks to avoid memory issues
            $offset = 0;
            $limit = 100;
            
            do {
                $data = $model->getTableData($table_name, $limit, $offset);
                
                foreach ($data as $row) {
                    $row_data = [];
                    foreach ($headers as $header) {
                        $row_data[] = $row->$header ?? '';
                    }
                    fputcsv($output, $row_data, ',', '"', '\\');
                }
                
                $offset += $limit;
            } while (count($data) == $limit);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export query results as SQL INSERT statements
     * 
     * @param string $table_name The name of the table to export
     * @param string $query The query to execute for export
     * @param string $token The CSRF token for validation
     * @param Db2tablesModel $model The DB2Tables model instance
     * @return array Response with SQL content or error message
     */
    public function exportSql($table_name, $query, $model = null) {
     
        // Buffer to store SQL output
        $sql_content = '';
        
        // If a query is provided, validate it with SQLParser
        if (!empty($query)) {
            // Parse the query with SQLParser
            $parser = new SQLParser($query);
            
            // Check if parsing was successful and it's a single query
            if ($parser->getQueryCount() !== 1) {
                return [
                    'success' => false,
                    'error' => 'Only single queries are supported for SQL export'
                ];
            }
            
            // Parse the query and check if it's a SELECT query
            $parsed_queries = $parser->parse();
            $parsed_query = $parsed_queries[0];
            
            if (!($parsed_query instanceof \App\Database\Query)) {
                return [
                    'success' => false,
                    'error' => 'Only SELECT queries are supported for SQL export'
                ];
            }
            
            // It's a valid SELECT query, execute it
            $db2 = Db2tablesServices::getDb();
            
            // Get the SQL and parameters
            list($query_string, $params) = $parsed_query->get();
          
            // Execute the query
            $result = $db2->query($query_string);
            
            if ($result === false) {
                return [
                    'success' => false,
                    'error' => 'Error executing query: ' . $db2->error
                ];
            }
            
            // Add SQL comment with export info
            $sql_content .= "-- SQL Export from db2tables\n";
            $sql_content .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
            $sql_content .= "-- Query: " . $query_string . "\n\n";
            
            // Get column names from result
            $first_row = $result->fetch_assoc();
            if ($first_row) {
                // Reset result pointer
                $result->data_seek(0);
                
                // Generate INSERT statements
                $row_count = 0;
                $batch_size = 100; // Number of rows per INSERT statement
                $current_batch = 0;
                $insert_rows = [];
                
                while ($row = $result->fetch_assoc()) {
                    $row_count++;
                    $current_batch++;
                    
                    // Format values for SQL
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    
                    $insert_rows[] = "(" . implode(", ", $values) . ")";
                    
                    // Write INSERT statement when batch is full or at the end
                    if ($current_batch >= $batch_size || $result->num_rows() == $row_count) {
                        $db2 = Db2tablesServices::getDb();
                        $columns = [];
                        foreach ($row as $col => $value) {
                            $columns[] = $db2->qn($col);
                        }
                        $columns = implode(", ", $columns);
                        
                        $sql_content .= "INSERT INTO ".$db2->qn($table_name) . " ({$columns}) VALUES\n";
                        $sql_content .= implode(",\n", $insert_rows) . ";\n\n";
                        
                        // Reset batch
                        $current_batch = 0;
                        $insert_rows = [];
                    }
                }
                
                // Add summary comment
                $sql_content .= "-- Exported {$row_count} rows\n";
            } else {
                // No results
                $sql_content .= "-- No results found for the query\n";
            }
            
            $result->free();
            
            // Return success with SQL content
            return [
                'success' => true,
                'sql' => $sql_content,
                'filename' => $table_name . '_query_export_' . date('Y-m-d') . '.sql'
            ];
        } else {
            // No query provided, export entire table as INSERT statements
            
            // Get table structure for column names
            $structure = $model->getTableStructure($table_name);
            
            // Add SQL comment with export info
            $sql_content .= "-- SQL Export from db2tables\n";
            $sql_content .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
            $sql_content .= "-- Table: " . $table_name . "\n\n";
            $db2 = Db2tablesServices::getDb();
            // Get column names
            $columns = [];
            foreach ($structure as $field) {
                $columns[] = $db2->qn($field->Field);
            }
            $columns_str = implode(", ", $columns);
            
            // Stream data in chunks to avoid memory issues
            $offset = 0;
            $limit = 100;
            $row_count = 0;
            
            do {
                $data = $model->getTableData($table_name, $limit, $offset);
                $batch_rows = [];
                
                foreach ($data as $row) {
                    $row_count++;
                    
                    // Format values for SQL
                    $values = [];
                    foreach ($structure as $field) {
                        $value = $row->{$field->Field} ?? null;
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    
                    $batch_rows[] = "(" . implode(", ", $values) . ")";
                }
                
                if (!empty($batch_rows)) {
                    $sql_content .= "INSERT INTO ".$db2->qn($table_name) . " ({$columns_str}) VALUES\n";
                    $sql_content .= implode(",\n", $batch_rows) . ";\n\n";
                }
                
                $offset += $limit;
            } while (count($data) == $limit);
            
            // Add summary comment
            $sql_content .= "-- Exported {$row_count} rows\n";
            
            // Return success with SQL content
            return [
                'success' => true,
                'sql' => $sql_content,
                'filename' => $table_name . '_export_' . date('Y-m-d') . '.sql'
            ];
        }
    }
    
    /**
     * Export multiple tables to CSV
     * 
     * @param array $tables Array of table names to export
     * @param bool $includeHeaders Whether to include headers in the CSV output
     * @param Db2tablesModel $model The DB2Tables model instance
     * @return array Response with success status and file information
     */
    public function exportTablesCsv($tables, $includeHeaders = true, $model = null) {
        if (empty($tables)) {
            return [
                'success' => false,
                'error' => 'No tables selected for export'
            ];
        }
        
        // Initialize model if not provided
        if ($model === null) {
            $model = new Db2tablesModel();
        }
        
        $db2 = Db2tablesServices::getDb();
        $csv_content = '';
        
        // Create temporary file for CSV content
        $temp_file = tempnam(sys_get_temp_dir(), 'csv_export_');
        $output = fopen($temp_file, 'w');
        
        // Export each table
        foreach ($tables as $table_name) {
            // Add table name as a comment row if exporting multiple tables
            if (count($tables) > 1) {
                fputcsv($output, ['# Table: ' . $table_name], ',', '"', '\\');
            }
            
            // Get table structure for column names
            $structure = $model->getTableStructure($table_name);
            
            // Add column headers if requested
            if ($includeHeaders) {
                $headers = [];
                foreach ($structure as $field) {
                    $headers[] = $field->Field;
                }
                fputcsv($output, $headers, ',', '"', '\\');
            }
            
            // Stream data in chunks to avoid memory issues
            $offset = 0;
            $limit = 1000;
            
            do {
                $data = $model->getTableData($table_name, $limit, $offset);
                
                foreach ($data as $row) {
                    $row_data = [];
                    foreach ($structure as $field) {
                        $row_data[] = $row->{$field->Field} ?? '';
                    }
                    fputcsv($output, $row_data, ',', '"', '\\');
                }
                
                $offset += $limit;
            } while (count($data) == $limit);
            
            // Add a blank line between tables
            if (count($tables) > 1) {
                fputcsv($output, [''], ',', '"', '\\');
            }
        }
        
        fclose($output);
        
        // Read the temporary file
        $csv_content = file_get_contents($temp_file);
        unlink($temp_file); // Delete the temporary file
        
        // Generate filename
        $filename = count($tables) > 1 ? 'multiple_tables_export_' . date('Y-m-d') . '.csv' : $tables[0] . '_export_' . date('Y-m-d') . '.csv';
        
        return [
            'success' => true,
            'csv' => $csv_content,
            'filename' => $filename
        ];
    }
    
    /**
     * Export multiple tables as SQL INSERT statements
     * 
     * @param array $tables Array of table names to export
     * @param bool $includeStructure Whether to include CREATE TABLE statements
     * @param Db2tablesModel $model The DB2Tables model instance
     * @return array Response with success status and SQL content
     */
    public function exportTablesSql($tables, $includeStructure = true, $model = null) {
        if (empty($tables)) {
            return [
                'success' => false,
                'error' => 'No tables selected for export'
            ];
        }
       
        // Initialize model if not provided
        if ($model === null) {
            $model = new Db2tablesModel();
        }
        
        $db2 = Db2tablesServices::getDb();
        $sql_content = '';
        
        // Add SQL header
        $sql_content .= "-- SQL Export from db2tables\n";
        $sql_content .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
        $sql_content .= "-- Tables: " . implode(', ', $tables) . "\n\n";
        
        // Export each table
        foreach ($tables as $table_name) {
            $sql_content .= "-- Table: {$table_name}\n";
            
            // Include CREATE TABLE statement if requested
            if ($includeStructure) {
                // Get CREATE TABLE statement
                $create_result = $db2->showCreateTable($table_name);

                if ($create_result['type'] == 'table') {
                    $sql_content .= $create_result['sql'] . ";\n\n";
                } elseif ($create_result['type'] == 'view') {
                    $sql_content .= $create_result['sql'] . ";\n\n";
                }
            }
            
            // Get table structure for column names
            $structure = $model->getTableStructure($table_name);
            
            // Get column names
            $columns = [];
            foreach ($structure as $field) {
                $columns[] = "`" . $field->Field . "`";
            }
            $columns_str = implode(", ", $columns);
            
            // Stream data in chunks to avoid memory issues
            $offset = 0;
            $limit = 100;
            $row_count = 0;
            
            do {
                $data = $model->getTableData($table_name, $limit, $offset);
                $batch_rows = [];
                
                foreach ($data as $row) {
                    $row_count++;
                    
                    // Format values for SQL
                    $values = [];
                    foreach ($structure as $field) {
                        $value = $row->{$field->Field} ?? null;
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    
                    $batch_rows[] = "(" . implode(", ", $values) . ")";
                }
                
                if (!empty($batch_rows)) {
                    $sql_content .= "INSERT INTO `{$table_name}` ({$columns_str}) VALUES\n";
                    $sql_content .= implode(",\n", $batch_rows) . ";\n\n";
                }
                
                $offset += $limit;
            } while (count($data) == $limit);
            
            // Add summary comment
            $sql_content .= "-- Exported {$row_count} rows from {$table_name}\n\n";
        }
        
        // Generate filename
        $filename = count($tables) > 1 ? 'multiple_tables_export_' . date('Y-m-d') . '.sql' : $tables[0] . '_export_' . date('Y-m-d') . '.sql';
        
        return [
            'success' => true,
            'sql' => $sql_content,
            'filename' => $filename
        ];
    }
}
