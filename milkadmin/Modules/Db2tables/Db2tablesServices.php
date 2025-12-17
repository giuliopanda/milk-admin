<?php
namespace Modules\Db2tables;

use App\Get;

/**
 * Classe con le funzioni di servizio per il modulo db2tables
 */

!defined('MILK_DIR') && die(); // Prevent direct access

class Db2tablesServices {

    /**
     * Get the database connection based on session selection
     *
     * @return \App\Abstracts\AbstractDb Database connection instance
     */
    static public function getDb() {
        // Check if a database selection is stored in session
        $db_selection = isset($_SESSION['db2tables_db_selection']) ? $_SESSION['db2tables_db_selection'] : 'db2';

        // Return the appropriate database connection
        if ($db_selection === 'db1') {
            return Get::db();
        } else {
            return Get::db2();
        }
    }

    /**
     *
     */
    static public function getHtmlDataTable($table_name) {
        $db2 = self::getDb();
        $table_id = 'tableDataId';
        $modellist = new \App\Modellist\ModelList($table_name, $table_id, $db2);

        $list_structure = $modellist->getListStructure();
        //$list_structure->deleteColumn('checkbox');

        foreach ($list_structure as $key=>$field) {
            if (strlen($field['label']) > 40) {
                $list_structure->setAttributeTitle($key, 'style', 'min-width: 250px;');
            } elseif (strlen($field['label']) > 15) {
                $list_structure->setAttributeTitle($key, 'style', 'min-width: 150px;');
            } 
            $list_structure->setAttributeTitle($key, 'class', 'align-middle fs-7');
        }    
        
        // Get initial data with pagination
        $query = $modellist->queryFromRequest();
        $rows = $db2->getResults(...$query->get());
        
        // Initialize $rows as an empty array if query returns null
        if ($rows === null || !is_array($rows)) {
            $rows = [];
        } else {
            // Process each row to escape HTML and truncate long text
            foreach ($rows as &$row) {
                foreach ($row as $key => $value) {
                    if ($value !== null) {
                        $escaped = _r($value);
                        if (strlen($escaped) > 300) {
                            $row->$key = substr($escaped, 0, 300) . '...';
                        } else {
                            $row->$key = $escaped;
                        }
                    }
                }
            }
        }
        $total_rows = $db2->getVar(...$query->getTotal());
        // Initialize $total_rows as 0 if query returns null
        if ($total_rows === null) {
            $total_rows = 0;
        }
        // Configure pagination
        $page_info = $modellist->getPageInfo($total_rows);

        $page_info['custom_data'] = ['table' => $table_name];
        $page_info['id'] = $table_id;
        $table_html = Get::themePlugin('table', [
            'info' => $list_structure, 
            'rows' => $rows, 
            'page_info' => $page_info
        ]);
        return $table_html;
    }

    /**
     * Gestisce la tabella di modifica dei dati
     */
    static public function getHtmlEditTable($table_name) {
        $db2 = self::getDb();
        $table_id = 'tableEditId';
        $modellist = new \App\Modellist\ModelList($table_name, $table_id, $db2);
        $table_structure = $modellist->getTableStructure();
        $list_structure = $modellist->getListStructure();
        foreach ($list_structure as $key=>$field) {
            $list_structure->setType($key, 'html');
        }
        // Check if there is a single primary key
        $primary_keys = [];
        foreach ($table_structure as $field_name => $field_info) {
            if ($field_info->Key === 'PRI') {
                $primary_keys[] = $field_name;
            }
        }
        
        // If there's no single primary key, return an alert
        if (count($primary_keys) !== 1) {
            return '<div class="alert alert-info">
                <p>This table cannot be edited because it does not have a single primary key field.</p>
                <p>Tables must have exactly one primary key field to be editable.</p>
            </div>';
        }
        
        $primary_key = $primary_keys[0];
        
        $list_structure->deleteColumn('checkbox');

        // Get initial data with pagination
        $query = $modellist->queryFromRequest();
        $rows = $db2->getResults(...$query->get());
        // Initialize $rows as an empty array if query returns null
        if ($rows === null || !is_array($rows)) {
            $rows = [];
        }
        $total_rows = $db2->getVar(...$query->getTotal());
        // Initialize $total_rows as 0 if query returns null
        if ($total_rows === null) {
            $total_rows = 0;
        }
        
        // Calculate max data lengths for each column
        $max_data_lengths = [];
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if ($value !== null) {
                    $value_length = strlen($value);
                    if (!isset($max_data_lengths[$key]) || $value_length > $max_data_lengths[$key]) {
                        $max_data_lengths[$key] = $value_length;
                    }
                }
            }
        }
        
        // Set column widths based on both label and data lengths
        foreach ($list_structure as $key => $field) {
            $label_length = strlen($field['label']);
            $data_length = $max_data_lengths[$key] ?? 0;
            
            // For text fields that will be truncated at 50 chars, use that as max
            $field_type = '';
            if (isset($table_structure[$key])) {
                $field_type = preg_replace('/\(.*\)/', '', $table_structure[$key]->Type);
            }
            $large_data_types = ['text', 'blob', 'longtext', 'mediumtext', 'longblob', 'mediumblob'];
            if (in_array($field_type, $large_data_types)) {
                $data_length = min($data_length, 50); // Truncated at 50 chars
            }
            
            // Use the larger of label length and data length to determine width
            $effective_length = max($label_length, $data_length);
            
            $adding = in_array($field_type, [$large_data_types, ...['varchar', 'char']]) ? 60 : 20;

            $width_style = '';
            if ($effective_length > 40) {
                $width_style = 'width: 300px;';
            } elseif ($effective_length > 6) {
                $width_style = 'width: ' . (($effective_length * 10)+$adding) . 'px;';
            } else {
                $width_style = 'width: 90px;';
            }
            
            // Applica lo stile sia all'intestazione che alle celle dati
            $list_structure->setAttributeTitle($key, 'style', $width_style);
            $list_structure->setAttributeData($key, 'style', $width_style);
            $list_structure->setAttributeData($key, 'class', 'edit-cell');
            $list_structure->setAttributeTitle($key, 'class', 'align-middle fs-7');
        }
        
        // Define field types that potentially contain large amounts of data
        $large_data_types = ['text', 'blob', 'longtext', 'mediumtext', 'longblob', 'mediumblob'];
       
        foreach ($rows as &$r) {
            $primary_key_value = $r->$primary_key;
            if (!is_object($r)) {
                $r = (object)$r;
            }
            foreach ($r as $key => $value) {
                $field_type = '';
                if (isset($table_structure[$key])) {
                    // Extract the base type without size/precision
                    $field_type = preg_replace('/\(.*\)/', '', $table_structure[$key]->Type);
                }
                
                // Set primary key attribute
                $primary_key_attr = ($key === $primary_key) ? ' data-primary-key="true"' : '';

                // Handle text fields based on content length
                if (in_array($field_type, $large_data_types)) {
                    if ($value === null || strlen($value) > 300) {
                        $value = substr($value ?? '', 0, 300).' ...';
                        // For large text content, show non-editable div
                        $r->$key = '<div class="large-data-field" title="Edit not allowed">' . _r($value) . '</div>';
                    } else {
                        // Calculate rows based on line breaks and content length
                        // Ensure $value is a string to avoid deprecation warnings
                        $value_str = is_null($value) ? '' : (string)$value;
                        $num_lines = substr_count($value_str, "\n") + 1;
                        $chars_per_line = 50; // Approssimativo numero di caratteri per riga
                        $content_lines = ceil(strlen($value_str) / $chars_per_line);
                        $textarea_rows = max($num_lines, $content_lines);
                        $textarea_rows = min(max(2, $textarea_rows), 8); // Minimo 2 righe, massimo 8

                        // For text content under 300 chars, show editable textarea
                        $r->$key = '<textarea class="js-auto-save-value input-cell" name="' . $key . '"' . $primary_key_attr . ' rows="' . $textarea_rows . '">' . _r($value) . '</textarea>';
                    }
                } else {
                    // For normal fields, create appropriate input based on field type

                    // Determine input type based on field type
                    if (strpos($field_type, 'varchar') === 0 || strpos($field_type, 'char') === 0) {
                        // Get the field length from the type definition
                        preg_match('/\((\d+)\)/', $table_structure[$key]->Type, $matches);
                        $maxLength = isset($matches[1]) ? (int)$matches[1] : 1024;
                        // Calculate rows based on line breaks and content length
                        // Ensure $value is a string to avoid deprecation warnings
                        $value_str = is_null($value) ? '' : (string)$value;
                        $num_lines = substr_count($value_str, "\n") + 1;
                        $chars_per_line = 50; // Approssimativo numero di caratteri per riga
                        $content_lines = ceil(strlen($value_str) / $chars_per_line);
                        $textarea_rows = max($num_lines, $content_lines);
                        $textarea_rows = min(max(2, $textarea_rows), 8); // Minimo 2 righe, massimo 8

                        // Use textarea for varchar and char fields with maxlength attribute
                        $r->$key = '<textarea class="js-auto-save-value input-cell" name="' . $key . '"' . $primary_key_attr . ' maxlength="' . $maxLength . '" rows="' . $textarea_rows . '">' . _r($value) . '</textarea>';
                    } elseif (in_array($field_type, ['date', 'datetime', 'timestamp'])) {
                        // Use date input for date fields
                        $input_type = ($field_type === 'date') ? 'date' : 'datetime-local';
                        $r->$key = '<input type="' . $input_type . '" class="js-auto-save-value input-cell" value="' . _r($value) . '" name="' . $key . '"' . $primary_key_attr . '>';
                    } else {
                        // Use regular input for other fields
                        $r->$key = '<input type="text" class="js-auto-save-value input-cell" value="' . _r($value) . '" name="' . $key . '"' . $primary_key_attr . '>';
                    }
                }
            }
            // Aggiungi i bottoni View, Edit e Delete
            $r->__act_ion__ = '<div class="d-flex">';
            // View button
            $r->__act_ion__ .= '<span class="link-action js-show-view-row" data-show-view-row="'.$primary_key_value.'" data-table="'._r($table_name).'">View</span>';
            // Edit button
            $r->__act_ion__ .= '<span class="link-action js-edit-row" data-edit-row="'.$primary_key_value.'" data-table="'._r($table_name).'">Edit</span>';
            // Delete button with confirmation
            $r->__act_ion__ .= '<span class="link-action js-delete-row text-danger" data-delete-row="'.$primary_key_value.'" data-table="'._r($table_name).'" data-confirm="Are you sure you want to delete this record?">Delete</span>';
            $r->__act_ion__ .= '</div>';
        }

        /**
         * Non uso action perché ho modificato i dati originali delle primary key per cui non riesco a gestirli
         */

        $list_structure->setColumn('__act_ion__', 'Action', 'html', false, false, [], ['class' => 'align-middle fs-7', 'style' => 'width: 180px;'], ['class' => 'bg-white  fs-7' , 'style' => 'border: 1px solid #ccc;']);

        // Move Action column to the first position
        $list_structure->reorderColumns(['__act_ion__']);

        // Configure pagination
        $page_info = $modellist->getPageInfo($total_rows);

        $page_info['custom_data'] = [
            'table' => $table_name,
            'primary_key' => $primary_key
        ];

        $page_info->setId($table_id);
        $page_info->setTableAttrs('table', ['class' => 'table-edit-cell js-edit-table mb-3']);
        $page_info->setAutoScroll(false);
        $table_html = Get::themePlugin('table', [
            'info' => $list_structure,
            'rows' => $rows,
            'page_info' => $page_info
        ]);
        return $table_html;
    }
    
    /**
     * Get detailed information about a specific field in a table
     * 
     * @param string $table Table name
     * @param string $field Field name
     * @return array Field details and statistics
     */
    static public function getFieldDetails($table, $field) {
        if (empty($table) || empty($field)) {
            return ['error' => 'Missing parameters', 'success' => false];
        }

        $db2 = self::getDb();

        if ($db2->type == "mysql") {
            $query = "SHOW FULL COLUMNS FROM ".$db2->qn($table)." WHERE Field = ?";
            $result = $db2->getRow($query, [$field]);

            if ($result) {
                // Get primary key fields
                $pkQuery = "SHOW KEYS FROM ".$db2->qn($table)." WHERE Key_name = 'PRIMARY'";
                $primaryKeys = $db2->getResults($pkQuery);
                $pkFields = array_map(function($row) { return $row->Column_name; }, $primaryKeys);
                
                // Get counts and statistics
                $statsQuery = "SELECT 
                    COUNT(*) as total_rows,
                    COUNT(DISTINCT CASE WHEN ".$db2->qn($field)." IS NOT NULL AND TRIM(".$db2->qn($field).") != '' THEN ".$db2->qn($field)." END) as distinct_count,
                    SUM(CASE WHEN ".$db2->qn($field)." IS NULL OR TRIM(".$db2->qn($field).") = '' THEN 1 ELSE 0 END) as null_count
                    FROM ".$db2->qn($table)."";
                $counts = $db2->getRow($statsQuery);

                if ($db2->last_error) {
                    return [
                        'error' => $db2->last_error,
                        'success' => false
                    ];
                }
                
                $stats = [];
                $stats['total_rows'] = $counts->total_rows;
                $stats['distinct_count'] = $counts->distinct_count;
                $stats['null_count'] = $counts->null_count;
                $stats['primary_keys'] = $pkFields;
                
                $limit = 50;
                if ($counts->distinct_count < 100) {
                    $limit = $counts->distinct_count;
                }
                $totalQuery = "SELECT COUNT(*) as total FROM ".$db2->qn($table)." WHERE ".$db2->qn($field)." IS NOT NULL";
                $total = $db2->getRow($totalQuery)->total;

                $topValuesQuery = "SELECT 
                    ".$db2->qn($field)." as value,
                    COUNT(*) as count,
                    ROUND(COUNT(*) * 100.0 / ?, 2) as percentage
                    FROM ".$db2->qn($table)."
                    WHERE ".$db2->qn($field)." IS NOT NULL
                    GROUP BY ".$db2->qn($field)."
                    ORDER BY count DESC, ".$db2->qn($field)." ASC
                    LIMIT ". $limit;
                
                $stats['top_values'] = $db2->getResults($topValuesQuery, [$total]);
                
                // If numeric type, calculate statistics
                if (strpos(strtolower($result->Type), 'int') !== false || 
                    strpos(strtolower($result->Type), 'decimal') !== false || 
                    strpos(strtolower($result->Type), 'float') !== false || 
                    strpos(strtolower($result->Type), 'double') !== false) {
                    
                    $statsQuery = "SELECT 
                        AVG(".$db2->qn($field).") as mean,
                        MIN(".$db2->qn($field).") as min,
                        MAX(".$db2->qn($field).") as max,
                        STDDEV(".$db2->qn($field).") as std_dev
                    FROM ".$db2->qn($table)." 
                    WHERE ".$db2->qn($field)." IS NOT NULL";
                    
                    $stats['numeric'] = $db2->getRow($statsQuery);
                }

                return [
                    'success' => true,
                    'data' => $result,
                    'stats' => $stats
                ];
            } else {
                return [
                    'error' => 'Field not found',
                    'success' => false
                ];
            }
        } else if ($db2->type == "sqlite") {
            // Get column info
            $query = "PRAGMA table_info(" . $db2->qn($table) . ")"; 
            $columns = $db2->getResults($query);
            $result = null;
            
            foreach ($columns as $col) {
                if ($col->name === $field) {
                    $result = (object)[
                        'Field' => $col->name,
                        'Type' => $col->type,
                        'Null' => ($col->notnull == 0 ? 'YES' : 'NO'),
                        'Default' => $col->dflt_value
                    ];
                    break;
                }
            }

            if ($result) {
                // Get primary key fields
                $pkQuery = "SELECT name FROM pragma_table_info(" . $db2->qn($table) . ") WHERE pk > 0";
                $primaryKeys = $db2->getResults($pkQuery);
                $pkFields = array_map(function($row) { return $row->name; }, $primaryKeys);
                
                // Get counts and statistics
                $statsQuery = "SELECT 
                    COUNT(*) as total_rows,
                    COUNT(DISTINCT CASE WHEN " . $db2->qn($field) . " IS NOT NULL AND TRIM(" . $db2->qn($field) . ") != '' THEN " . $db2->qn($field) . " END) as distinct_count,
                    SUM(CASE WHEN " . $db2->qn($field) . " IS NULL OR TRIM(" . $db2->qn($field) . ") = '' THEN 1 ELSE 0 END) as null_count
                    FROM " . $db2->qn($table);
                $counts = $db2->getRow($statsQuery);

                if ($db2->last_error) {
                    return [
                        'error' => $db2->last_error,
                        'success' => false
                    ];
                }
                
                $stats = [];
                $stats['total_rows'] = $counts->total_rows;
                $stats['distinct_count'] = $counts->distinct_count;
                $stats['null_count'] = $counts->null_count;
                $stats['primary_keys'] = $pkFields;
                
                $limit = 50;
                if ($counts->distinct_count < 100) {
                    $limit = $counts->distinct_count;
                }
                
                $totalQuery = "SELECT COUNT(*) as total FROM " . $db2->qn($table) . " WHERE " . $db2->qn($field) . " IS NOT NULL";
                $total = $db2->getRow($totalQuery)->total;

                $topValuesQuery = "SELECT 
                    " . $db2->qn($field) . " as value,
                    COUNT(*) as count,
                    ROUND(CAST(COUNT(*) AS FLOAT) * 100.0 / ?, 2) as percentage
                    FROM " . $db2->qn($table) . "
                    WHERE " . $db2->qn($field) . " IS NOT NULL
                    GROUP BY " . $db2->qn($field) . "
                    ORDER BY count DESC, " . $db2->qn($field) . " ASC
                    LIMIT " . $limit;
                
                $stats['top_values'] = $db2->getResults($topValuesQuery, [$total]);
                
                // If numeric type, calculate statistics
                if (preg_match('/(int|decimal|float|double|real|numeric)/i', $result->Type) && $db2->type == "mysql") {
                    $statsQuery = "SELECT 
                        AVG(CAST(" . $db2->qn($field) . " AS FLOAT)) as mean,
                        MIN(" . $db2->qn($field) . ") as min,
                        MAX(" . $db2->qn($field) . ") as max,
                        SQRT(AVG(CAST(" . $db2->qn($field) . " AS FLOAT) * CAST(" . $db2->qn($field) . " AS FLOAT)) - AVG(CAST(" . $db2->qn($field) . " AS FLOAT)) * AVG(CAST(" . $db2->qn($field) . " AS FLOAT))) as std_dev
                    FROM " . $db2->qn($table) . " 
                    WHERE " . $db2->qn($field) . " IS NOT NULL";
                    
                    $stats['numeric'] = $db2->getRow($statsQuery);
                }

                return [
                    'success' => true,
                    'data' => $result,
                    'stats' => $stats
                ];
            } else {
                return [
                    'error' => 'Field not found',
                    'success' => false
                ];
            }
        }
    }
    
    /**
     * Get database information for dashboard
     * 
     * @return array Database information including type, version, size, and tables count
     */
    static public function getDatabaseInfo() {
        $db2 = self::getDb();

        // Get database type and version
        if ($db2->type == "mysql") {
            $db_info = $db2->getRow("SELECT VERSION() as version");
            $db_version = $db_info->version ?? 'Unknown';
        
            // Determine database type from version string
            $db_type = 'Unknown';
            if (stripos($db_version, 'MariaDB') !== false) {
                $db_type = 'MariaDB';
            } elseif (stripos($db_version, 'MySQL') !== false || is_numeric(substr($db_version, 0, 1))) {
                $db_type = 'MySQL';
            }
            
            // Get tables and views count
            $tables_query = "SELECT COUNT(*) as count FROM information_schema.TABLES WHERE table_schema = DATABASE()";
            $tables_info = $db2->getRow($tables_query);
            $tables_count = $tables_info->count ?? 0;
            
            // Calculate database size
            $size_query = "SELECT 
                SUM(data_length + index_length) as total_size 
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE()";
            $size_info = $db2->getRow($size_query);
            $db_size_bytes = $size_info->total_size ?? 0;
            
            // Convert bytes to human-readable format
            $db_size = self::formatBytes($db_size_bytes);
        
        } elseif ($db2->type == "postgres") {
            $db_info = $db2->getRow("SELECT version() as version");
            $db_version = $db_info->version ?? 'Unknown';
            $db_type = 'PostgreSQL';
            
            // Get tables and views count - CORREZIONE: PostgreSQL usa current_database() invece di DATABASE()
            $tables_query = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'public'";
            $tables_info = $db2->getRow($tables_query);
            $tables_count = $tables_info->count ?? 0;
            
            // Calculate database size - CORREZIONE: PostgreSQL usa pg_database_size()
            $size_query = "SELECT pg_database_size(current_database()) as total_size";
            $size_info = $db2->getRow($size_query);
            $db_size_bytes = $size_info->total_size ?? 0;
            
            // Convert bytes to human-readable format
            $db_size = self::formatBytes($db_size_bytes);
        
        } elseif ($db2->type == "sqlite") {
            $db_info = $db2->getRow("SELECT sqlite_version() as version");
            $db_version = $db_info->version ?? 'Unknown';
            $db_type = 'SQLite';
            
            // Get tables count - CORREZIONE: SQLite usa sqlite_master
            $tables_query = "SELECT COUNT(*) as count FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
            $tables_info = $db2->getRow($tables_query);
            $tables_count = $tables_info->count ?? 0;
            
            // Calculate database size - usa filesize se $db2->dbname contiene il path del file
            if (isset($db2->dbname) && file_exists($db2->dbname)) {
                $db_size_bytes = filesize($db2->dbname);
            } else {
                // Alternativa: usa PRAGMA page_count e page_size
                $page_count_info = $db2->getRow("PRAGMA page_count");
                $page_size_info = $db2->getRow("PRAGMA page_size");
                $db_size_bytes = ($page_count_info->page_count ?? 0) * ($page_size_info->page_size ?? 0);
            }
            
            // Convert bytes to human-readable format
            $db_size = self::formatBytes($db_size_bytes);
        }
        
        return [
            'db_type' => $db_type,
            'db_version' => $db_version,
            'db_size' => $db_size,
            'tables_count' => $tables_count
        ];
    }
    
    /**
     * Format bytes to human-readable size
     * 
     * @param int $bytes The size in bytes
     * @param int $precision The number of decimal places to include
     * @return string Human-readable size with unit
     */
    static private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
