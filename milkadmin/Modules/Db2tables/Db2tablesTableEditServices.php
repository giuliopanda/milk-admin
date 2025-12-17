<?php
namespace Modules\Db2tables;

use App\{Form, Get, Token};

/**
 * Classe con le funzioni di servizio per l'edit delle tabelle del modulo db2tables
 */

!defined('MILK_DIR') && die(); // Prevent direct access

class Db2tablesTableEditServices {

    /**
     * Get table structure and primary key information
     *
     * @param string $table_name Table name
     * @return array ['structure' => array, 'primary_key' => string|null]
     */
    private static function getTableStructureAndPrimaryKey($table_name) {
        $db2 = Db2tablesServices::getDb();
        $desc = $db2->describes($table_name);
        $table_structure = $desc['struct'];

        // Find the primary key field
        $primary_key = null;
        foreach ($table_structure as $field) {
            if ($field->Key === 'PRI') {
                $primary_key = $field->Field;
                break;
            }
        }

        return [
            'structure' => $table_structure,
            'primary_key' => $primary_key
        ];
    }

    /**
     * Display a single record in an offcanvas view
     * 
     * @param string $table_name Nome della tabella
     * @param string $record_id ID del record da visualizzare
     * @return array Dati per la risposta JSON
     */
    static public function getSingleRecordView($table_name, $record_id) {
        if (empty($table_name) || empty($record_id)) {
            return [
                'success' => false,
                'error' => 'Missing required parameters (table or id)'
            ];
        }

        // Get the table structure and primary key
        $table_info = self::getTableStructureAndPrimaryKey($table_name);
        $table_structure = $table_info['structure'];
        $primary_key = $table_info['primary_key'];

        if (!$primary_key) {
            return [
                'success' => false,
                'error' => 'Table does not have a primary key'
            ];
        }

        // Get database connection
        $db2 = Db2tablesServices::getDb();

        // Get the single record
        $query = "SELECT * FROM ".$db2->qn($table_name)." WHERE ".$db2->qn($primary_key)." = ?";
        $record = $db2->getRow($query, [$record_id]);
        
        if (!$record) {
            return [
                'success' => false,
                'error' => 'Record not found'
            ];
        }
        
        // Prepare the data for the offcanvas view
        $fields = [];
        foreach ($record as $field_name => $value) {
            // Get field type from structure
            $field_type = '';
            $field_label = $field_name;
            
            foreach ($table_structure as $field) {
                if ($field->Field === $field_name) {
                    $field_type = $field->Type;
                    break;
                }
            }
            
            // Format the value based on field type
            $formatted_value = $value;
            
            // Handle NULL values
            if ($value === null) {
                $formatted_value = '<em class="text-body-secondary">NULL</em>';
            } 
            // Handle large text fields
            else if (strpos($field_type, 'text') !== false || strlen($formatted_value) > 100) {
                $formatted_value = '<div class="text-wrap" style="white-space: pre-wrap; max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; padding: 8px; border-radius: 4px;">' . _r($formatted_value) . '</div>';
            }
            // Handle date/time fields
            else if (strpos($field_type, 'date') !== false || strpos($field_type, 'time') !== false) {
                $formatted_value = _r($formatted_value);
            }
            // Handle other fields
            else {
                $formatted_value = _r($formatted_value);
            }
            
            $fields[] = [
                'name' => $field_name,
                'label' => $field_label,
                'value' => $formatted_value,
                'type' => $field_type,
                'is_primary' => ($field_name === $primary_key)
            ];
        }
        
        // Build the HTML for the offcanvas view
        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-striped table-hover">';
        $html .= '<tbody>';
        
        foreach ($fields as $field) {
            $primary_class = $field['is_primary'] ? ' table-primary' : '';
            $is_text_field = strpos($field['type'], 'text') !== false || strlen($field['value']) > 100;
            
            if ($is_text_field) {
                // For text fields, display the label and value in separate rows
                $html .= '<tr class="' . $primary_class . '">';
                $html .= '<th colspan="2">' . _r($field['label']) . '</th>';
                $html .= '</tr>';
                $html .= '<tr>';
                $html .= '<td colspan="2">' . $field['value'] . '</td>';
                $html .= '</tr>';
            } else {
                // For other fields, display label and value side by side
                $html .= '<tr class="' . $primary_class . '">';
                $html .= '<th style="width: 30%;">' . _r($field['label']) . '</th>';
                $html .= '<td>' . $field['value'] . '</td>';
                $html .= '</tr>';
            }
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        // Prepare the title
        $title = _r($table_name) . ' - Record #' . _r($record_id);
        
        // Return the HTML and title
        return [
            'success' => true,
            'html' => $html,
            'title' => $title
        ];
    }
    
    /**
     * Generate a form to edit a record in an offcanvas view
     * 
     * @param string $table_name Nome della tabella
     * @param string $record_id ID del record da modificare (0 per nuovo record)
     * @return array Dati per la risposta JSON
     */
    static public function getEditRecordForm($table_name, $record_id = '0') {
        if (empty($table_name)) {
            return [
                'success' => false,
                'error' => 'Missing required parameter (table)'
            ];
        }
        $count_null = 1;

        // Get the table structure and primary key
        $table_info = self::getTableStructureAndPrimaryKey($table_name);
        $table_structure = $table_info['structure'];
        $primary_key = $table_info['primary_key'];

        if (!$primary_key) {
            return [
                'success' => false,
                'error' => 'Table does not have a primary key'
            ];
        }

        // Get database connection
        $db2 = Db2tablesServices::getDb();

        $record = null;

        // If ID is not 0, get the existing record
        if ($record_id > 0) {
            $query = "SELECT * FROM `$table_name` WHERE `$primary_key` = ? LIMIT 1";
            $record = $db2->getRow($query, [$record_id]);
            
            if (!$record) {
                return [
                    'success' => false,
                    'error' => 'Record not found'
                ];
            }
        } else {
            // Create an empty record with default values for a new record
            $record = new \stdClass();
            
            // Add all fields from table structure with default values
            foreach ($table_structure as $field) {
                $field_name = $field->Field;
                $default_value = $field->Default;
                
                // Set default value based on field type
                if ($field->Key === 'PRI' && strpos($field->Extra, 'auto_increment') !== false) {
                    $record->{$field_name} = '0'; // Auto-increment field
                } else if ($default_value !== null) {
                    $record->{$field_name} = $default_value;
                } else if ($field->Null === 'YES') {
                    $record->{$field_name} = null;
                } else {
                    $record->{$field_name} = '';
                }
            }
        }
        
        // Generate a token for form submission security
        $token = Token::get('edit-record-'.$table_name);

        // Start building the form
        ob_start();
        ?>
        <form id="edit-record-form" class="needs-validation" novalidate>
            <input type="hidden" name="table" value="<?= _r($table_name) ?>">
            <input type="hidden" name="token" value="<?= _r($token) ?>">
        <?php
        
        // Process each field in the record
        foreach ($record as $field_name => $value) {
            // Get field type and other properties from structure
            $field_type = '';
            $is_primary = ($field_name === $primary_key);
            $is_nullable = false;
            
            foreach ($table_structure as $field) {
                if ($field->Field === $field_name) {
                    $field_type = $field->Type;
                    $is_nullable = ($field->Null === 'YES' && !$is_primary);
                    break;
                }
            }
            
            // Set up common options for all field types
            $options = [];
            
            // Add required attribute for primary key
            if ($is_primary) {
                $options['required'] = false;
                $options['class'] = 'bg-warning-subtle';
                if ($record_id != '0') {
                    $options['readonly'] = true;
                    $options['data-primary-key'] = 'true';
                }
            }
            
            // Add field label with required indicator if needed
            $label = $field_name;
            if ($is_primary) {
                $label .= ' <span class="text-danger">*</span>';
            }
            
            echo '<div class="mb-3 js-single-block' . ($is_primary ? ' bg-light' : '') . '">';
            
            // Handle NULL values for nullable fields
            if ($value === null && $is_nullable ) {
                // For NULL values, we'll add a checkbox to toggle NULL status
                if (strpos($field_type, 'text') !== false) {
                    // For text fields
                    Form::textarea($field_name, $label, '', 5, $options + ['disabled' => true]);
                } else if (strpos($field_type, 'date') !== false) {
                    // For date fields
                    Form::input('date', $field_name, $label, '', $options + ['disabled' => true]);
                } else if (strpos($field_type, 'datetime') !== false || strpos($field_type, 'timestamp') !== false) {
                    // For datetime fields
                    Form::input('datetime-local', $field_name, $label, '', $options + ['disabled' => true]);
                } else {
                    // For other fields
                    Form::input('text', $field_name, $label, '', $options + ['disabled' => true]);
                }
                
                // Add NULL checkbox
                echo '<div class="form-check mt-1">';
                Form::checkbox('null_' . $field_name.$count_null++, 'Set as NULL', '1', true, ['class' => 'js-null-checkbox', 'data-field' => _r($field_name)]);
                echo '</div>';
            } 
            // Handle large text fields
            else if (strpos($field_type, 'text') !== false || (is_string($value) && strlen($value) > 100)) {
                Form::textarea($field_name, $label, $value, 5, $options);

                // Add NULL checkbox for nullable fields
                if ($is_nullable) {
                    echo '<div class="form-check mt-1">';
                    Form::checkbox('null_' . $field_name, 'Set as NULL', '1', false, ['class' => 'js-null-checkbox', 'data-field' => _r($field_name)]);
                    echo '</div>';
                }
            }
            // Handle date fields
            else if (strpos($field_type, 'date') !== false && strpos($field_type, 'datetime') === false) {
                Form::input('date', $field_name, $label, $value, $options);

                if ($is_nullable) {
                    echo '<div class="form-check mt-1">';
                    Form::checkbox('null_' . $field_name, 'Set as NULL', '1', false, ['class' => 'js-null-checkbox', 'data-field' => _r($field_name)]);
                    echo '</div>';
                }
            }
            // Handle datetime fields
            else if (strpos($field_type, 'datetime') !== false || strpos($field_type, 'timestamp') !== false) {
                // Convert MySQL datetime format to HTML datetime-local format
                $datetime_value = $value;
                if ($value && strpos($value, ' ') !== false) {
                    $datetime_value = str_replace(' ', 'T', $value);
                }

                Form::input('datetime-local', $field_name, $label, $datetime_value, $options);

                if ($is_nullable) {
                    echo '<div class="form-check mt-1">';
                    Form::checkbox('null_' . $field_name, 'Set as NULL', '1', false, ['class' => 'js-null-checkbox', 'data-field' => _r($field_name)]);
                    echo '</div>';
                }
            }
            // Handle other fields
            else {
                Form::input('text', $field_name, $label, $value, $options);

                if ($is_nullable) {
                    echo '<div class="form-check mt-1">';
                    Form::checkbox('null_' . $field_name, 'Set as NULL', '1', false, ['class' => 'js-null-checkbox', 'data-field' => _r($field_name)]);
                    echo '</div>';
                }
            }
            
            echo '</div>';
        }
        ?>
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Cancel</button>
            <button type="submit" class="btn btn-primary"><?= ($record_id == '0' ? 'Create Record' : 'Save Changes') ?></button>
        </div>
        </form>
        <?php
        $html = ob_get_clean();
        
        // Prepare the title
        $title = ($record_id == '0') ? 'Add New Record: ' . _r($table_name) : 'Edit Record: ' . _r($table_name) . ' #' . _r($record_id);
        
        // Return the HTML and title
        return [
            'success' => true,
            'html' => $html,
            'title' => $title
        ];
    }
    
    /**
     * Save a record (create new or update existing)
     * 
     * @param array $post_data POST data from the form
     * @return array Response data for JSON
     */
    static public function saveRecord($post_data) {
        // Get the table name from the request
        $table_name = isset($post_data['table']) ? $post_data['table'] : '';
        
        if (empty($table_name)) {
            return [
                'success' => false,
                'error' => 'Missing required parameter: table'
            ];
        }
        
        // Validate token
        $token = isset($post_data['token']) ? $post_data['token'] : '';
        if (!Token::checkValue($token, 'edit-record-'.$table_name)) {
            return [
                'success' => false,
                'error' => 'Invalid security token'
            ];
        }

        // Get the table structure and primary key
        $table_info = self::getTableStructureAndPrimaryKey($table_name);
        $table_structure = $table_info['structure'];
        $primary_key = $table_info['primary_key'];

        if (!$primary_key) {
            return [
                'success' => false,
                'error' => 'Table does not have a primary key'
            ];
        }

        // Get database connection
        $db2 = Db2tablesServices::getDb();

        // Get the primary key value
        if (!isset($post_data[$primary_key])) {
            return [
                'success' => false,
                'error' => 'Missing primary key value'
            ];
        }

        $primary_key_value = $post_data[$primary_key];
        $is_new_record = empty($primary_key_value) || $primary_key_value == '0';

        // Prepare data for update/insert
        $data = [];
        $field_names = [];

        // Get all field names from the table structure
        foreach ($table_structure as $field) {
            $field_names[] = $field->Field;
        }

        // Process each field
        foreach ($field_names as $field_name) {
            if ($field_name == $primary_key) continue;
            // Skip the primary key field for updates, but include it for inserts if it's not auto_increment
            $is_auto_increment = false;
            foreach ($table_structure as $field) {
                if ($field->Field === $primary_key && strpos($field->Extra, 'auto_increment') !== false) {
                    $is_auto_increment = true;
                    break;
                }
            }

            if ($field_name === $primary_key && (!$is_new_record || $is_auto_increment)) {
                continue;
            }

            // Check if the field should be set to NULL
            // The checkbox sends value '1' when checked
            if (isset($post_data['null_' . $field_name]) && $post_data['null_' . $field_name] == '1') {
                $data[$field_name] = null;
            }
            // Otherwise use the provided value
            else if (isset($post_data[$field_name])) {
                $data[$field_name] = $post_data[$field_name];
            }
        }

        $where = $primary_key_value ? [$primary_key => $primary_key_value] : [];
        $db2->save($table_name, $data, $where);

        if (!$db2->error) {
            return [
                'success' => true,
                'message' => 'Record updated successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Error updating record: ' . $db2->last_error
            ];
        }
    }
    
    /**
     * Save a single cell edit from the editable table
     * 
     * @param object $data JSON data with primary keys, field name, and new value
     * @param string $table Table name
     * @param string|null $token Security token (optional)
     * @return array Response data for JSON
     */
    static public function saveEditSingleCell($data, $table, $token = null) {
        // Validate required data
        if (!$data || !isset($data->primary_keys) || !isset($data->field_name) || !isset($data->field_value)) {
            return [
                'success' => false,
                'error' => 'Missing required data'
            ];
        }
        
        if (empty($table)) {
            return [
                'success' => false,
                'error' => 'Table name is required'
            ];
        }
        
        // Validate token if provided
        if ($token !== null) {
            if (!Token::checkValue($token, 'editTable'.$table)) {
                return [
                    'success' => false,
                    'error' => 'Invalid security token'
                ];
            }
        }

        // Get the table structure
        $table_info = self::getTableStructureAndPrimaryKey($table);
        $table_structure = $table_info['structure'];

        // Get database connection
        $db2 = Db2tablesServices::getDb();

        // Get field information to determine if it can be NULL and its data type
        $field_info = null;
        
        // Process the field value based on field type and nullable status
        if ($table_structure) {
            foreach ($table_structure as $field) {
                if ($field->Field === $data->field_name) {
                    $field_info = $field;
                    break;
                }
            }
        }
        
        // Process the field value based on field type and nullable status
        if ($field_info) {
            // Check if the field is a primary key
            $is_primary_key = ($field_info->Key === 'PRI');
            
            // Check if field can be NULL and value is empty
            if ($data->field_value === '') {
                // Primary keys cannot be NULL
                if ($is_primary_key) {
                    return [
                        'success' => false,
                        'error' => 'Primary key fields cannot be empty or NULL'
                    ];
                } elseif ($field_info->Null === 'YES') {
                    // For non-primary key nullable fields, set to NULL
                    $data->field_value = null;
                }
            }
            
            // Check if field is a numeric type (float, decimal, double, etc.)
            if (preg_match('/(float|decimal|double|real)/i', $field_info->Type) && $data->field_value !== null) {
                // Replace comma with dot if there's only one comma or dot
                if (substr_count($data->field_value, ',') === 1 && substr_count($data->field_value, '.') === 0) {
                    $data->field_value = str_replace(',', '.', $data->field_value);
                } elseif (substr_count($data->field_value, '.') > 1 || 
                          (substr_count($data->field_value, ',') >= 1 && substr_count($data->field_value, '.') >= 1)) {
                    // Invalid format - too many decimal separators
                    return [
                        'success' => false,
                        'error' => 'Invalid number format. Use only one decimal separator.'
                    ];
                }
            }
        }
        
        // Build the WHERE clause from primary keys
        $where_conditions = [];
        $where_values = [];
        
        foreach ($data->primary_keys as $key => $value) {
            $where_conditions[] = $db2->qn($key)." = ?";
            $where_values[] = $value;
        }
        
        if (empty($where_conditions)) {
            return [
                'success' => false,
                'error' => 'No primary key provided'
            ];
        }

        $where_clause = implode(' AND ', $where_conditions);
        $query = "UPDATE ".$db2->qn($table)." SET ".$db2->qn($data->field_name)." = ? WHERE $where_clause";
        
        // Add the field value as the first parameter
        array_unshift($where_values, $data->field_value);
        
        $result = $db2->query($query, $where_values);
        
        if ($db2->last_error) {
            return [
                'success' => false,
                'error' => $db2->last_error
            ];
        }

        // Read the updated value from the database to ensure we have the most current value
        // (especially important if there are triggers or other DB-level transformations)
        $select_query = "SELECT ".$db2->qn($data->field_name)." FROM ".$db2->qn($table)." WHERE $where_clause LIMIT 1";
        // We need to remove the field value from the beginning of the array since it was for the UPDATE
        // and we only need the WHERE clause parameters for the SELECT
        $select_params = $where_values;
        array_shift($select_params);
        $updated_value = $db2->getVar($select_query, $select_params);
        
        return [
            'success' => true,
            'message' => 'Cell updated successfully',
            'updated_value' => $updated_value
        ];
    }
    
    /**
     * Delete a record from a table
     * 
     * @param string $table_name Table name
     * @param string $record_id Record ID to delete
     * @return array Response data for JSON
     */
    static public function deleteRecord($table_name, $record_id) {
        if (empty($table_name) || empty($record_id)) {
            return [
                'success' => false,
                'error' => 'Missing required parameters (table or id)'
            ];
        }

        // Get the table structure and primary key
        $table_info = self::getTableStructureAndPrimaryKey($table_name);
        $primary_key = $table_info['primary_key'];

        if (!$primary_key) {
            return [
                'success' => false,
                'error' => 'Table does not have a primary key'
            ];
        }

        // Get database connection
        $db2 = Db2tablesServices::getDb();

        // Delete the record
        $query = "DELETE FROM ".$db2->qn($table_name)." WHERE ".$db2->qn($primary_key)." = ?";

        try {
            // First check if the record exists
            $check_query = "SELECT COUNT(*) as count FROM ".$db2->qn($table_name)." WHERE ".$db2->qn($primary_key)." = ?";
            $check_result = $db2->getVar($check_query, [$record_id]);
            
            if ($check_result <= 0) {
                return [
                    'success' => false,
                    'error' => 'Record not found'
                ];
            }
            
            // Execute the delete query
            $result = $db2->query($query, [$record_id]);
            
            if ($result !== false) {
                return [
                    'success' => true,
                    'message' => 'Record deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error executing delete query: '.$db2->last_error
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}
