<?php
namespace Builders;

use App\Modellist\{ListStructure, ModelList};
use App\{Get, MessagesHandler, Route};
use App\Database\{Query};

!defined('MILK_DIR') && die(); // Prevents direct access

/**
 * TableBuilder - Fluent interface for creating and managing dynamic tables
 * 
 * Provides a simplified API that wraps ModelList, ListStructure, and PageInfo
 * for easier table creation with method chaining.
 * 
 * @package Builders
 * @author MilkAdmin
 */
class TableBuilder {
    private $model;
    private $table_id;
    private $request;
    private $modellist_service;
    private $query;
    private $actions = [];
    private $bulk_actions = [];
    private $table_attrs = [];
    private $custom_columns = [];
    private $hidden_columns = [];
    private $default_limit = 10;
    private $footer_data = null;
    private $function_results = null;
    private $sort_mappings = []; // Mappatura campi ordinamento: virtual_field => real_field
    private $row_conditions = []; // Condizioni per classi sulle righe
    private $column_conditions = []; // Condizioni per classi sulle colonne
    private $page = null; // Current page name for actions
    private $action_response = null;
    private $update_table = true;
    private $filter_default = [];
    private $request_action = '';
    private $fetch_mode = false;

    /**
     * Constructor - Initialize TableBuilder with model and table ID
     * 
     * @param AbstractModel $model The model instance to use for database operations
     * @param string $table_id Unique identifier for this table (used for request parameters)
     * @param array|null $request Optional custom request parameters, defaults to $_REQUEST
     */
    public function __construct($model, $table_id, $request = null) {
        $this->model = $model;
        $this->table_id = $table_id;
        $this->request = $request ?? $this->getRequestParams($table_id);
        $this->page = $_REQUEST['page'] ?? null; // Initialize page from request
        $this->modellist_service = $this->createModellistService();
        $this->modellist_service->setModel($model);
        $this->query = $this->modellist_service->queryFromRequest();
        // $this->query =  new Query($model->getTable(), $model->db, $model);
        $this->query->clean('order');
        $this->updateQuery();
        $this->autoConfigureArrayColumns();
    }

    /**
     * Select specific columns for the query
     * 
     * @param array|string $columns Column names to select
     * @return self For method chaining
     * 
     * @example ->select(['id', 'title', 'content', 'status', 'category', 'created_at'])
     */
    public function select(array|string $columns): self {
        $this->query->select($columns);
        return $this;
    }

    /**
     * Add WHERE condition to the query
     * 
     * @param string $where WHERE clause condition
     * @param array $params Parameters for prepared statement
     * @param string $operator Operator (AND/OR) for multiple conditions
     * @return self For method chaining
     * 
     * @example ->where('id > ?', [2])
     */
    public function where($where, $params = [], $operator = 'AND'): self {
        $this->query->where($where, $params, $operator);
        return $this;
    }

    /**
     * Set default ordering for the table
     * 
     * @param string $field Field name to order by
     * @param string $direction Order direction (ASC/DESC)
     * @return self For method chaining
     * 
     * @example ->orderBy('title', 'desc')
     */
    public function orderBy($field, $direction = 'ASC'): self {
        $this->modellist_service->setOrder($field, strtolower($direction));
        $this->updateQuery();
        return $this;
    }

    /**
     * Set the number of rows per page
     * 
     * @param int $limit Number of rows to display per page
     * @return self For method chaining
     * 
     * @example ->limit(2)
     */
    public function limit($limit): self {
        $this->modellist_service->setLimit($limit);
        $this->default_limit = $limit;
        $this->updateQuery();
        return $this;
    }

    /**
     * Execute custom query modifications with callback
     * 
     * @param callable $callback Function that receives ($query, $db) parameters
     * @return self For method chaining
     */
    public function queryCustomCallback(callable $callback): self {
        $db = $this->model->getDb();
        $callback($this->query, $db);
        return $this;
    }

    // Metodi di query più specifici
    public function whereIn($field, array $values): self {
        $db = $this->model->getDb();
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        $this->query->where($db->qn($field) . " IN ({$placeholders})", $values);
        return $this;
    }

    public function whereLike($field, $value, $position = 'both'): self {
        $db = $this->model->getDb();
        $search_value = match($position) {
            'start' => $value . '%',
            'end' => '%' . $value,
            'both' => '%' . $value . '%',
            default => '%' . $value . '%'
        };
        $this->query->where($db->qn($field) . ' LIKE ?', [$search_value]);
        return $this;
    }

    public function whereBetween($field, $min, $max): self {
        $db = $this->model->getDb();
        $this->query->where($db->qn($field) . ' BETWEEN ? AND ?', [$min, $max]);
        return $this;
    }

    public function join($table, $condition, $type = 'INNER'): self {
        $this->query->from("{$type} JOIN {$table} ON {$condition}");
        return $this;
    }

    public function leftJoin($table, $condition): self {
        return $this->join($table, $condition, 'LEFT');
    }

    public function rightJoin($table, $condition): self {
        return $this->join($table, $condition, 'RIGHT');
    }

    public function groupBy($field): self {
        $this->query->group($field);
        return $this;
    }

    public function having($condition, $params = []): self {
        $this->query->having($condition, $params);
        return $this;
    }

    /**
     * Add or modify a table column
     * 
     * @param string $key Column key/name
     * @param string|null $label Display label for the column
     * @param string|null $type Column type (text, select, date, html, etc.)
     * @param array $options Options for select type columns
     * @param callable|null $fn Function to process column data ($row, $key) => string
     * @return self For method chaining
     * 
     * @example ->column('status', 'Status', 'select', ['published' => '-Published-', 'draft' => '-Draft-'])
     * @example ->column('created_at', 'Created At', 'date')
     */
    public function column($key, $label = null, $type = null, $options = [], $fn = null): self {
        // Auto-detect se è add o modify usando ListStructure
        $existing_column = $this->modellist_service->list_structure->getColumn($key);
        $action = $existing_column ? 'modify' : 'add';
        
        $this->custom_columns[$key] = [
            'label' => $label,
            'type' => $type,
            'options' => $options,
            'action' => $action,
            'fn' => $fn
        ];
        if (in_array($key, $this->hidden_columns)) {
            $this->hidden_columns = array_diff($this->hidden_columns, [$key]);
        }
       
        return $this;
    }

    public function setLabel($key, $label): self {
        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['label'] = $label;
        return $this;
    }

    /**
     * Set the type of a column
     * 
     * @param string $key Column key/name
     * @param string $type Column type (text, select, date, html, etc.)
     * @return self For method chaining
     * 
     * @example ->setType('status', 'select')
     */
    public function setType($key, $type): self {
        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['type'] = $type;
        return $this;
    }

    public function setOptions($key, $options): self {
        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['options'] = $options;
        return $this;
    }

    /**
     * Set processing function for a column
     *
     * The function receives the entire row array and should return the formatted value.
     * This is registered as a handler in the model for get_formatted operation.
     *
     * @param string $key Column key/name
     * @param callable $fn Function that processes column data ($row_array) => string
     * @return self For method chaining
     *
     * @example ->setFn('title', function($row) { return '<a href="?page=modellist&id=' . $row['id'] . '">' . $row['title'] . '</a>'; })
     * @example ->setFn('full_name', function($row) { return $row['first_name'] . ' ' . $row['last_name']; })
     */
    public function setFn($key, callable $fn): self {
        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['fn'] = $fn;
        return $this;
    }

    /**
     * Convert a column to a clickable link
     *
     * @param string $key Column key/name
     * @param string $link Link URL pattern with placeholders like %id%, %field_name%
     * @param array $options Additional options for the link (target, class, etc.)
     * @return self For method chaining
     *
     * @example ->asLink('title', '?page=modellist&action=edit&id=%id%')
     * @example ->asLink('title', '?page=view&id=%id%', ['target' => '_blank', 'class' => 'text-primary'])
     */
    public function asLink($key, $link, $options = []): self {
        // If fetch mode is active, automatically add data-fetch attribute
        if ($this->fetch_mode && !isset($options['data-fetch'])) {
            $options['data-fetch'] = 'post';
        }

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        // Automatically set type to html for links
        $this->custom_columns[$key]['type'] = 'html';
        // Store the formatter function to be registered later in getData()
        // The handler receives the entire row array from getFormattedValue
        $this->custom_columns[$key]['fn'] = function($row_array) use ($key, $link, $options) {
          
            /*
            object(stdClass)#39 (12) { ["doctor"]=> object(stdClass)#37 (4) { ["id"]=> int(6) ["name"]=> string(3) "May" ["image"]=> array(1) { [1]=> array(2) { ["url"]=> string(19) "media/firma001.jpeg" ["name"]=> string(10) "firma.jpeg" } } ["biography"]=> string(0) "" } ["id"]=> int(1) ["patient_name"]=> string(8) "Giulio11" ["patient_email"]=> string(23) "g.pandolfelli@gimema.it" ["patient_phone"]=> string(6) "123123" ["appointment_date"]=> object(DateTime)#42 (3) { ["date"]=> string(26) "2025-09-25 12:44:00.000000" ["timezone_type"]=> int(3) ["timezone"]=> string(3) "UTC" } ["doctor_id"]=> int(6) ["status"]=> string(9) "cancelled" ["notes"]=> string(44) "Updated via ResultSet at 2025-10-02 13:32:37" ["attachment"]=> array(1) { [0]=> string(0) "" } ["created_at"]=> object(DateTime)#43 (3) { ["date"]=> string(26) "2025-03-01 00:00:00.000000" ["timezone_type"]=> int(3) ["timezone"]=> string(3) "UTC" } ["updated_at"]=> object(DateTime)#44 (3) { ["date"]=> string(26) "2025-10-13 08:45:37.000000" ["timezone_type"]=> int(3) ["timezone"]=> string(3) "UTC" } }
            */
            // Replace placeholders in the link
            $final_link = $link;

            // Convert array to flat string values for URL placeholders
            $row_properties = is_array($row_array) ? $row_array : get_object_vars($row_array);

            // Get rules from model to check for date formatting
            $rules = $this->model->getRules();
            $field_rule = $rules[$key] ?? null;

            // Convert objects (like DateTime) to strings for URL replacement
            $flat_properties = [];
            foreach ($row_properties as $prop_key => $prop_value) {
                if (is_object($prop_value)) {
                    if ($prop_value instanceof \DateTime) {
                        $flat_properties[$prop_key] = $prop_value->format('Y-m-d H:i:s');
                    } else {
                      //  $flat_properties[$prop_key] = (string)$prop_value;
                    }
                } elseif (is_scalar($prop_value)) {
                    $flat_properties[$prop_key] = (string)$prop_value;
                }
            }

            $id_value = $flat_properties['id'] ?? null;
            $final_link = Route::replaceUrlPlaceholders($final_link, ['id' => $id_value, ...$flat_properties]);

            // Build link attributes
            $attributes = [];
            if (isset($options['target'])) {
                $attributes[] = 'target="' . _r($options['target']) . '"';
            }
            if (isset($options['class'])) {
                $attributes[] = 'class="' . _r($options['class']) . '"';
            }
            foreach ($options as $option => $value) {
                $attributes[] = $option . '="' . _r($value) . '"';
            }

            $attr_string = !empty($attributes) ? ' ' . implode(' ', $attributes) : '';
         
            // Get the column value to display as link text
           // $display_text = $row_properties[$key] ?? '';
          
            $display_text = $this->extractDotNotationValue($row_array, $key);
            // Format dates according to rules
            if ($field_rule && in_array($field_rule['type'], ['datetime', 'date', 'time'])) {
                if ($display_text instanceof \DateTime) {
                    $formatted = Get::formatDate($display_text, $field_rule['type']);
                    $display_text = $formatted !== '' ? $formatted : $display_text->format('Y-m-d H:i:s');
                } elseif (is_string($display_text) && $display_text !== '') {
                    $formatted = Get::formatDate($display_text, $field_rule['type']);
                    $display_text = $formatted !== '' ? $formatted : $display_text;
                }
            }

            return '<a href="' . Route::url($final_link) . '"' . $attr_string . '>' . $display_text . '</a>';
        };

        return $this;
    }

    public function fetchLink($key, $link, $options = []): self {
        $options['data-fetch'] = "post";
        $this->asLink($key, $link, $options);
        return $this;
    }

    /**
     * Activate fetch mode
     *
     * When fetch mode is active:
     * - asLink() automatically converts to FetchLink()
     * - Actions automatically get 'fetch' => true
     *
     * @return self For method chaining
     *
     * @example ->activeFetch()
     */
    public function activeFetch(): self {
        $this->fetch_mode = true;
        return $this;
    }
    

    /**
     * Convert a column to file download links
     *
     * @param string $key Column key/name containing file array data
     * @param array $options Additional options for the links (target, class, etc.)
     * @return self For method chaining
     *
     * @example ->asFile('attachments')
     * @example ->asFile('documents', ['class' => 'btn btn-link', 'target' => '_blank'])
     */
    public function asFile($key, $options = []): self {
        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        // Automatically set type to html for file links
        $this->custom_columns[$key]['type'] = 'html';

        // Store the formatter function to be registered later in getData()
        // The handler receives the entire row array from getFormattedValue
        $this->custom_columns[$key]['fn'] = function($row_array) use ($key, $options) {
            // Extract value from row array
          //  $value = $row_array[$key] ?? null;
            $value = $this->extractDotNotationValue($row_array, $key);
            // Handle JSON string format: {"1":{"url":"...","name":"..."}}
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                }
            }

            // Se non è un array o è vuoto, ritorna stringa vuota
            if (!is_array($value) || empty($value)) {
                return '';
            }

            // Build link attributes
            $default_class = $options['class'] ?? 'js-file-download';
            $target = $options['target'] ?? '_blank';

            $output = '';
            foreach ($value as $file) {
                // Support both array and object formats
                $file_url = is_array($file) ? ($file['url'] ?? false) : (is_object($file) ? ($file->url ?? false) : false);
                $file_name = is_array($file) ? ($file['name'] ?? false) : (is_object($file) ? ($file->name ?? false) : false);

                if ($file_url && $file_name) {
                    $output .= '<a href="' . htmlspecialchars($file_url) . '" target="' . htmlspecialchars($target) . '" class="' . htmlspecialchars($default_class) . '">' . htmlspecialchars($file_name) . '</a><br>';
                }
            }

            return $output;
        };

        return $this;
    }

    /**
     * Convert a column to image thumbnails
     *
     * @param string $key Column key/name containing image array data
     * @param array $options Additional options for the images (size, class, lightbox, etc.)
     * @return self For method chaining
     *
     * @example ->asImage('photos')
     * @example ->asImage('gallery', ['size' => 80, 'class' => 'rounded', 'lightbox' => true])
     */
    public function asImage($key, $options = []): self {

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        // Automatically set type to html for images
        $this->custom_columns[$key]['type'] = 'html';

        // Store the formatter function to be registered later in getData()
        // The handler receives the entire row array from getFormattedValue
        $this->custom_columns[$key]['fn'] = function($row_array) use ($key, $options) {
            // Extract value from row array
            $value = $this->extractDotNotationValue($row_array, $key);

            // DEBUG: Uncomment to debug image field
            // error_log("asImage DEBUG - Key: $key, Value type: " . gettype($value) . ", Value: " . print_r($value, true));

            // Handle JSON string format: {"1":{"url":"...","name":"..."}}
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                }
            }

            // Se non è un array o è vuoto, ritorna il valore originale o stringa vuota
            if (!is_array($value) || empty($value)) {
                // DEBUG: Uncomment to debug
                // error_log("asImage DEBUG - Returning empty for key: $key");
                return is_string($value) ? '' : $value;
            }

            // Build image attributes
            $size = $options['size'] ?? 50;
            $class = $options['class'] ?? '';
            $lightbox = $options['lightbox'] ?? false;
            $max_images = $options['max_images'] ?? null;

            $output = '<div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">';
            $count = 0;
            foreach ($value as $file) {
                // Support both array and object formats
                $file_url = is_array($file) ? ($file['url'] ?? false) : (is_object($file) ? ($file->url ?? false) : false);
                $file_name = is_array($file) ? ($file['name'] ?? '') : (is_object($file) ? ($file->name ?? '') : '');

                if ($file_url) {
                    if ($max_images !== null && $count >= $max_images) {
                        $remaining = count($value) - $count;
                        $output .= '<div style="width: ' . $size . 'px; height: ' . $size . 'px; display: flex; align-items: center; justify-content: center; background: #e9ecef; border-radius: 4px; font-size: 0.8rem;">+' . $remaining . '</div>';
                        break;
                    }

                    $img_html = '<img src="' . htmlspecialchars($file_url) . '" alt="' . htmlspecialchars($file_name) . '" style="width: ' . $size . 'px; height: ' . $size . 'px; object-fit: cover; border-radius: 4px;" class="' . htmlspecialchars($class) . '">';

                    if ($lightbox) {
                        $output .= '<a href="' . htmlspecialchars($file_url) . '" target="_blank" data-lightbox="' . htmlspecialchars($key) . '">' . $img_html . '</a>';
                    } else {
                        $output .= $img_html;
                    }
                    $count++;
                }
            }
            $output .= '</div>';

            return $output;
        };

        return $this;
    }

    /**
     * Hide a column from display
     * 
     * @param string $key Column key/name to hide
     * @return self For method chaining
     * 
     * @example ->hideColumn('updated_at')
     */
    public function hideColumn($key): self {
        $this->hidden_columns[] = $key;
        return $this;
    }

    /**
     * Hide multiple columns from display
     * 
     * @param array $keys Array of column keys/names to hide
     * @return self For method chaining
     * 
     * @example ->hideColumns(['updated_at', 'created_at'])
     */
    public function hideColumns(array $keys): self {
        foreach ($keys as $key) {
            $this->hideColumn($key);
        }
        return $this;
    }

    /**
     * Disable sorting for a specific column
     * 
     * @param string $key Column key/name to disable sorting for
     * @return self For method chaining
     * 
     * @example ->disableSort('title_original')
     */
    public function disableSort($key): self {
        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['disable_sort'] = true;
        return $this;
    }

    public function mapSort($virtual_field, $real_field): self {
        $this->sort_mappings[$virtual_field] = $real_field;
        // Apply mapping to the query object
        $this->query->setSortMapping($virtual_field, $real_field);
        return $this;
    }

    public function deleteColumn($key): self {
        $this->custom_columns[$key] = [
            'action' => 'delete'
        ];
        return $this;
    }

    public function deleteColumns(array $keys): self {
        foreach ($keys as $key) {
            $this->deleteColumn($key);
        }
        return $this;
    }

    /**
     * Reorder table columns
     * 
     * @param array $column_order Array of column names in desired order
     * @return self For method chaining
     * 
     * @example ->reorderColumns(['id', 'content'])
     */
    public function reorderColumns(array $column_order): self {
        $this->custom_columns['_reorder'] = [
            'action' => 'reorder',
            'column_order' => $column_order
        ];
        return $this;
    }

    public function showOnlyColumns(array $columns): self {
        $this->query->select($columns);
        return $this;
    }

    // Method chaining per filtri (snake_case)
    /*
    public function addFilter(callable $filter): self {
        $filter($this->modellist_service);
        return $this;
    }
    */

    /**
     * Add custom filter with callback
     *
     * The filter_name must match the data-filter-type attribute in the view
     *
     * @param string $filter_name Name of the filter (must match frontend data-filter-type)
     * @param callable $callback Function that receives ($query, $value) parameters
     * @param mixed $default_value Optional default value. If set, the filter is always applied with this value when no filter is in the request
     * @return self For method chaining
     *
     * @example ->filter('created_at', function($query, $value) { $query->where('created_at > ?', [$value]); })
     * @example ->filter('status', function($query, $value) { $query->where('status = ?', [$value]); }, 'active')
     */
    public function filter($filter_name, callable $callback, $default_value = null): self {
        $this->modellist_service->addFilter($filter_name, function($query, $value) use ($callback) {
            if ($value != '' && $value !== null && !empty($value)) {
                $callback($query, $value);
            }

        });

        // If default value is provided, apply filter immediately if not already in request
        if ($default_value !== null) {
            // Check if filter is already in the request
            $filters_json = $this->request['filters'] ?? '';
            $filter_exists = false;

            if ($filters_json != '') {
                $filters = json_decode($filters_json);
                if (is_array($filters)) {
                    foreach ($filters as $filter_str) {
                        $parts = explode(':', $filter_str, 2);
                        if ($parts[0] === $filter_name) {
                            $filter_exists = true;
                            break;
                        }
                    }
                }
            }

            // Apply default filter if not already in request
            if (!$filter_exists) {
                $this->filter_default[$filter_name] = $default_value;
                $callback($this->query, $default_value);
            }
        }

        return $this;
    }

    /**
     * Add equals filter for a field
     * 
     * @param string $filter_name Name of the filter (must match frontend data-filter-type)
     * @param string $field Database field name to filter
     * @return self For method chaining
     * 
     * @example ->filterEquals('category', 'category')
     * @example ->filterEquals('status', 'status')
     */
    public function filterEquals($filter_name, $field): self {
        $db = $this->model->getDb();
        $this->modellist_service->addFilter($filter_name, function($query, $value) use ($field, $db) {
            $value = trim($value);
            if ($value != '') {
                $query->where($db->qn($field) . ' = ?', [$value]);
            }
        });
        return $this;
    }

    public function filterLike($filter_name, $field, $position = 'both'): self {
        $db = $this->model->getDb();
        $this->modellist_service->addFilter($filter_name, function($query, $value) use ($field, $position, $db) {
            $value = trim($value);
            if ($value != '') {
                $search_value = match($position) {
                    'start' => $value . '%',
                    'end' => '%' . $value,
                    'both' => '%' . $value . '%',
                    default => '%' . $value . '%'
                };
                $query->where($db->qn($field) . ' LIKE ?', [$search_value]);
            }
        });
        return $this;
    }

    /**
     * Set row actions for the table
     * 
     * Actions can be links or callable functions. Function actions return data that
     * will be passed to the view or sent as JSON response.
     * 
     * @param array $actions Array of actions configuration:
     *   - 'label': Display text for the action
     *   - 'link': URL for link actions (supports placeholders like %primary%, %field_name%)
     *   - 'action': Callable function for custom actions
     *   - 'target': Link target (_blank, _self, etc.)
     *   - 'class': CSS classes for styling
     *   - 'confirm': Confirmation message before execution
     * @return self For method chaining
     * 
     * @example ->setActions([
     *   'edit' => ['label' => 'Edit', 'action' => [$this, 'editAction']],
     *   'link' => ['label' => 'my Link', 'link' => '?page=post&id=%primary%', 'target' => '_blank'],
     *   'delete' => ['label' => 'Delete', 'action' => [$this, 'deleteAction'], 'confirm' => 'Are you sure?']
     * ])
     */
    public function setActions(array $actions): self {
        $action_functions = [];
        
        $filters = $this->request['filters'] ?? '';
        $filter_applied = $this->filter_default;
        if ($filters != '') {
            $tmp_filters = json_decode($filters);
            foreach ($tmp_filters as $filter) {
                $filter_type = explode(':', $filter);
                $filter_applied[$filter_type[0]] = implode(':', array_slice($filter_type, 1));
            }
        }

        foreach ($actions as $key => $action_data) {
            if (is_array($action_data)) {
                // formato: 'edit' => ['label' => 'Edit', 'action' => function() {}] oppure ['label' => 'Link', 'link' => 'url']
                $action_config = [
                    'label' => $action_data['label'] ?? $key
                ];

                if (!$this->checkShowIfFilter($action_data, $filter_applied)) continue;
                    
                
                // Se ha un link, aggiungilo alla configurazione
                if (isset($action_data['link'])) {
                    $action_config['link'] = $action_data['link'];
                }
                
                // Aggiunge gli attributi opzionali per link e actions
                $optional_attrs = ['target', 'class', 'confirm', 'fetch'];
                foreach ($optional_attrs as $attr) {
                    if (isset($action_data[$attr])) {
                         if ($attr == 'fetch') {
                            $action_config['fetch'] = 'post';
                        } else if ($attr == 'class') {
                            $action_config[$attr] = 'js-single-action ' . $action_data[$attr];
                        } else {
                            $action_config[$attr] = $action_data[$attr];
                        }
                    }
                }

                // If fetch mode is active and action has a link, automatically add fetch attribute
                if ($this->fetch_mode && isset($action_config['link']) && !isset($action_config['fetch'])) {
                    $action_config['fetch'] = 'post';
                }
                
                $this->actions[$key] = $action_config;
                
                if (isset($action_data['action']) && is_callable($action_data['action'])) {
                    $action_functions[$key] = $action_data['action'];
                }
            } 
        }

        $ids = [];
        if (isset($this->request["table_ids"])) {
            if (is_array($this->request["table_ids"])) {
                $ids = $this->request["table_ids"];
            } else {
                $ids = [$this->request["table_ids"]];
            }
        }

        // Verifica se c'è una table_action da eseguire
        $table_action = $this->request['table_action'] ?? null;
        if ($table_action && isset($action_functions[$table_action])) {
            // Get records from IDs and pass to action function
            $records = $this->model->getByIds($ids);
            $this->function_results = call_user_func($action_functions[$table_action], $records, $this->request);
        }
        
        return $this;
    }

    private function checkShowIfFilter($action_data, $filter_applied) {
        $showIfFilter = true;
        if (isset($action_data['showIfFilter']) && is_array($action_data['showIfFilter']) && $filter_applied != [] && count($action_data['showIfFilter']) == 1) {
            foreach ($filter_applied as $filter_type => $filter_value) {
                foreach ($action_data['showIfFilter'] as $showIfFilter_type => $showIfFilter_value) {
                    if ($filter_type == $showIfFilter_type) {
                        if ($filter_value == $showIfFilter_value) {
                            $showIfFilter = true;
                        } else {
                            $showIfFilter = false;
                        }
                    }
                }
            }
        }
        return $showIfFilter;
    }

    /**
     * Set the page name for action links
     * 
     * @param string $page_name Page name to use in action links
     * @return self For method chaining
     * 
     * @example ->setPage('users')
     */
    public function setPage(string $page_name): self {
        $this->page = $page_name;
        return $this;
    }

    /**
     * Set default actions for the table (Edit and Delete)
     * 
     * @param array $custom_actions Additional custom actions to add
     * @return self For method chaining
     * 
     * @example ->setDefaultActions()
     * @example ->setDefaultActions(['view' => ['label' => 'View', 'link' => '?page=mypage&action=view&id=%id%']])
     */
    public function setDefaultActions(array $custom_actions = []): self {
        // Use stored page or fallback to admin
        $page_name = $this->page ?? 'admin';
        
        // Default actions
        $default_actions = [
            'edit' => [
                'label' => 'Edit',
                'link' => "?page={$page_name}&action=edit&id=%id%",
            ],
            'delete' => [
                'label' => 'Delete',
                'validate' => false,
                'class' => 'link-action-danger',
                'action' => [$this, 'actionDeleteRow'],
                'confirm' => 'Are you sure you want to delete this item?'
            ]
        ];
        
        // Merge with custom actions (custom actions override defaults if same key)
        $actions = array_merge($default_actions, $custom_actions);
        
        return $this->setActions($actions);
    }

    protected function actionDeleteRow($records, $request) {
        $return = false;
        if (is_countable($records) && count($records) > 0) {
            $pk = $this->model->getPrimaryKey();
            foreach ($records as $record) {
                // Access the primary key value from the record using object notation
                $id = $record->{$pk};
                if ($this->model->delete($id)) {
                    $return = true;
                    MessagesHandler::addSuccess('Item deleted successfully');
                } else {
                    MessagesHandler::addError($this->model->getLastError());
                    $return = false;
                    break;
                }
            }
        } else {
            MessagesHandler::addError('No items selected');
        }

        return $return;
    }

    /**
     * Set bulk actions for selected rows Call function for each selected row and pass the record and request as parameters
     * 
     * @param array $bulk_actions Array of bulk actions ['key' => 'label']
     * @return self For method chaining
     * 
     * @example ->setBulkActions(['delete' => 'delete'])
     */
    public function setBulkActions(array $bulk_actions): self {
        $this->action_response = [];
        $this->update_table = true;

        // Get current filters to check showIfFilter condition
        $filters = $this->request['filters'] ?? '';
        $filter_applied = $this->filter_default;
        if ($filters != '') {
            $tmp_filters = json_decode($filters);
            foreach ($tmp_filters as $filter) {
                $filter_type = explode(':', $filter);
                $filter_applied[$filter_type[0]] = implode(':', array_slice($filter_type, 1));
            }
        }

        // Filter bulk actions based on showIfFilter condition
        $filtered_bulk_actions = [];
        foreach ($bulk_actions as $action_key => $action_config) {
            if ($this->checkShowIfFilter($action_config, $filter_applied)) {
                $filtered_bulk_actions[$action_key] = $action_config;
            }
        }

        $this->bulk_actions = $filtered_bulk_actions;

        if (isset($this->request['table_action']) && isset($this->request['table_ids'])) {
            foreach ($this->bulk_actions as $action_key => $action_config) {
                if (!isset($action_config['mode']) || $action_config['mode'] != 'batch') {
                    $action_config['mode'] = 'single';
                } else {
                    $action_config['mode'] = 'batch';
                }
                if ($this->request['table_action'] == $action_key) {
                    if (isset($action_config['updateTable']) && $action_config['updateTable'] === false) {
                        $this->update_table = false;
                    }
                    $ids = explode(',', $this->request['table_ids']);
                    if ($action_config['mode'] == 'batch') {
                        $records = $this->model->getByIds($ids);
                        $add_result = call_user_func($action_config['action'], $records, $this->request);
                        if (is_array($add_result)) {
                            $this->action_response = array_merge($this->action_response, $add_result);
                        }
                    } else {
                        foreach ($ids as $id) {
                            $record = $this->model->getById($id);
                            $add_result = call_user_func($action_config['action'], $record, $this->request);
                            if (is_array($add_result)) {
                                $this->action_response = array_merge($this->action_response, $add_result);
                            }
                        }
                    }

                }
            }
        }
        return $this;
    }

    // Method chaining per configurazione tabella (snake_case)
    public function setTableAttrs(array $attrs): self {
        $this->table_attrs = $attrs;
        return $this;
    }

    public function addTableAttr($element, $key, $value): self {
        $this->table_attrs[$element][$key] = $value;
        return $this;
    }

    // Enhanced class management methods with js- class protection

    public function tableClass($classes): self {
        // Preserva sempre le classi essenziali per funzionalità
        $essential_classes = ['js-table'];
        
        // Aggiungi 'table' se non presente nelle classi fornite
        if (strpos($classes, 'table') === false && strpos($classes, 'table-') === false) {
            $essential_classes[] = 'table';
        }
        
        // Combina classi essenziali con quelle fornite
        $full_classes = implode(' ', $essential_classes) . ' ' . $classes;
        $this->table_attrs['table']['class'] = trim($full_classes);
        return $this;
    }

    public function rowClass($classes, $condition = null): self {
        if ($condition === null) {
            // Per le righe, aggiungi sempre js-table-tr per preservare la funzionalità JavaScript
            $classes_with_js = 'js-table-tr ' . $classes;
            $this->table_attrs['tr']['class'] = $classes_with_js;
        } else {
            // Aggiungi condizione per righe specifiche
            $this->row_conditions[] = [
                'type' => 'condition',
                'classes' => $classes,
                'condition' => $condition
            ];
        }
        return $this;
    }

    public function rowClassAlternate($odd_classes, $even_classes = null): self {
        $this->row_conditions[] = [
            'type' => 'alternate',
            'odd_classes' => $odd_classes,
            'even_classes' => $even_classes ?? ''
        ];
        return $this;
    }

    public function columnClass($column_name, $classes): self {
        $key = 'td.' . str_replace(' ', '_', $column_name);
        
        // Preserva classi js- specifiche per alcune colonne
        $js_class = '';
        if ($column_name === 'id' || $column_name === 'checkbox') {
            $js_class = 'js-td-checkbox ';
        }
        
        $this->table_attrs[$key]['class'] = $js_class . $classes;
        return $this;
    }

    public function columnClassAlternate($column_name, $odd_classes, $even_classes = null): self {
        $this->column_conditions[] = [
            'type' => 'alternate',
            'column' => $column_name,
            'odd_classes' => $odd_classes,
            'even_classes' => $even_classes ?? ''
        ];
        return $this;
    }

    public function rowClassByValue($field, $value, $classes, $comparison = '=='): self {
        $this->row_conditions[] = [
            'type' => 'value',
            'field' => $field,
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }

    public function cellClassByValue($column_name, $field, $value, $classes, $comparison = '=='): self {
        $this->column_conditions[] = [
            'type' => 'value',
            'column' => $column_name,
            'field' => $field,
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }

    public function headerClass($classes): self {
        $this->table_attrs['thead']['class'] = $classes;
        return $this;
    }

    public function bodyClass($classes): self {
        $this->table_attrs['tbody']['class'] = $classes;
        return $this;
    }

    public function headerColumnClass($column_name, $classes): self {
        $key = 'th.' . str_replace(' ', '_', $column_name);
        $this->table_attrs[$key]['class'] = $classes;
        return $this;
    }

    public function cellClass($row_index, $column_name, $classes): self {
        // Gestisce classi per singole celle specifiche
        $this->column_conditions[] = [
            'type' => 'specific_cell',
            'column' => $column_name,
            'row_index' => $row_index, // 1-indexed
            'classes' => $classes
        ];
        return $this;
    }

    public function footerClass($classes): self {
        $this->table_attrs['tfoot']['class'] = $classes;
        return $this;
    }

    public function tableColor($color): self {
        // Mappa i colori semplici alle classi Bootstrap
        $color_map = [
            // Colori base
            'primary' => 'table-primary',
            'secondary' => 'table-secondary', 
            'success' => 'table-success',
            'danger' => 'table-danger',
            'warning' => 'table-warning',
            'info' => 'table-info',
            'light' => 'table-light',
            'dark' => 'table-dark',
            
            // Aliases più semplici
            'blue' => 'table-primary',
            'gray' => 'table-secondary',
            'grey' => 'table-secondary',
            'green' => 'table-success',
            'red' => 'table-danger',
            'yellow' => 'table-warning',
            'cyan' => 'table-info',
            'white' => 'table-light',
            'black' => 'table-dark',
            
            // Colori striped
            'striped' => 'table-striped',
            'striped-primary' => 'table-striped table-primary',
            'striped-success' => 'table-striped table-success',
            'striped-danger' => 'table-striped table-danger',
            'striped-warning' => 'table-striped table-warning',
            'striped-info' => 'table-striped table-info',
            'striped-dark' => 'table-striped table-dark',
            
            // Combinazioni speciali
            'bordered' => 'table-bordered',
            'hover' => 'table-hover',
            'small' => 'table-sm'
        ];
        
        // Se il colore esiste nella mappa, usa le classi Bootstrap
        if (isset($color_map[$color])) {
            $bootstrap_classes = $color_map[$color];
        } else {
            // Se non esiste, prova a costruire la classe assumendo sia un colore Bootstrap valido
            $bootstrap_classes = 'table-' . $color;
        }
        
        // Per i colori (non utility come hover, bordered), aggiungi sempre striped e coordina header
        $utility_colors = ['striped', 'bordered', 'hover', 'small'];
        $is_color = !in_array($color, $utility_colors);
        
        if ($is_color) {
            // Aggiungi striped per i colori e imposta header coordinato
            $bootstrap_classes = 'table-striped ' . $bootstrap_classes;
            $this->headerColor($color);
        }
        
        // Aggiungi sempre la classe base 'table' di Bootstrap
        $full_classes = 'table ' . $bootstrap_classes;
        
        // Applica le classi mantenendo js-table
        $this->tableClass($full_classes);
        
        // Se non sono già state impostate altre configurazioni, applica quelle di default
        if (!isset($this->table_attrs['table']['class']) || strpos($this->table_attrs['table']['class'], 'table-row-selected') === false) {
            // Aggiungi classi di default se mancanti
            $current_classes = $this->table_attrs['table']['class'] ?? '';
            if (strpos($current_classes, 'table-hover') === false) {
                $current_classes = trim($current_classes . ' table-hover');
            }
            if (strpos($current_classes, 'table-row-selected') === false) {
                $current_classes = trim($current_classes . ' table-row-selected');
            }
            $this->table_attrs['table']['class'] = $current_classes;
        }
        
        return $this;
    }

    public function headerColor($color): self {
        // Mappa i colori per header e selezione
        $header_color_map = [
            // Colori base
            'primary' => 'table-header-primary',
            'secondary' => 'table-header-secondary', 
            'success' => 'table-header-success',
            'danger' => 'table-header-danger',
            'warning' => 'table-header-warning',
            'info' => 'table-header-info',
            'light' => 'table-header-light',
            'dark' => 'table-header-dark',
            
            // Aliases semplici
            'blue' => 'table-header-primary',
            'gray' => 'table-header-secondary',
            'grey' => 'table-header-secondary',
            'green' => 'table-header-success',
            'red' => 'table-header-danger',
            'yellow' => 'table-header-warning',
            'cyan' => 'table-header-info',
            'white' => 'table-header-light',
            'black' => 'table-header-dark'
        ];
        
        // Se il colore esiste nella mappa, usa la classe custom
        if (isset($header_color_map[$color])) {
            $header_class = $header_color_map[$color];
        } else {
            // Se non esiste, prova a costruire la classe 
            $header_class = 'table-header-' . $color;
        }
        
        // Applica le classi all'header e alla tabella per la selezione
        $this->headerClass($header_class);
        $this->addTableAttr('table', 'data-header-color', $color); // Per CSS targeting
        
        // Se non sono già state impostate classi per la tabella, applica quelle di default
        if (!isset($this->table_attrs['table']['class'])) {
            $this->tableClass('table table-hover table-row-selected');
        }
        
        return $this;
    }

    /**
     * Set footer data for the table
     * 
     * @param array $footer_data Array of footer values corresponding to each column
     * @return self For method chaining
     * 
     * @example ->setFooter(['', '', 'Total', '99999', ''])
     */
    public function setFooter(array $footer_data): self {
        $this->footer_data = $footer_data;
        return $this;
    }

    public function setRequestAction($request_action): self {
        $this->request_action = $request_action;
        return $this;
    }

    /**
     * Get complete table data array
     * 
     * @return array Array containing:
     *   - 'rows': Table row data
     *   - 'info': ListStructure instance with column information
     *   - 'page_info': PageInfo instance with pagination data
     *   - Additional data from action results (if any)
     */
    public function getData(): array {

        $db = $this->model->getDb();
        $model_list = $this->modellist_service;
        $model_list->applyFilters($this->query, ($_REQUEST[$this->table_id] ?? []));
        $model_rows = $this->model->get($this->query);
        $model_rows->with();

        // DON'T register formatters - we'll apply them manually later
        // $this->registerCustomFormatters();

        // Get RAW data first (not formatted) so custom functions can format it
        $rows_raw = $model_rows->getRawData();
        $rows = $model_rows->getFormattedData();
        $columns = $model_rows->getQueryColumns();
    
      
        foreach ($rows as $key=>$row) {
            // First pass: Extract dot notation values and set them as properties
            // This must be done BEFORE custom functions are called
            foreach ($this->custom_columns as $key_con => $column_config) {
                if (strpos($key_con, '.') !== false) {
                    // Extract value from dot notation path
                    $value = $this->extractDotNotationValue($row, $key_con);
                    // Set it as a property (PHP allows property names with dots using curly braces)
                    $row->{$key_con} = $value;
                }
            }

            // Second pass: Apply formatting to model fields
            // For fields WITH custom functions: apply the custom function (receives RAW data)
            // For fields WITHOUT custom functions: apply standard model formatting
            foreach ($columns as $column_name) {
                // Skip dot notation columns (processed in third pass)
                if (strpos($column_name, '.') !== false) {
                    continue;
                }

                // Check if this column has a custom function
                $has_custom_fn = isset($this->custom_columns[$column_name]['fn']) &&
                                is_callable($this->custom_columns[$column_name]['fn']);

                if ($has_custom_fn) {
                    // Apply custom formatter (receives raw data)
                    $row->{$column_name} = call_user_func($this->custom_columns[$column_name]['fn'], $rows_raw[$key]);
                } 
            }

            // Third pass: Process custom columns (including dot notation and virtual columns)
            foreach ($this->custom_columns as $key_cok => $column_config) {
                // Process columns with custom functions that are:
                // - Dot notation columns (e.g., 'doctor.name')
                // - Virtual/computed columns not in model (e.g., 'count_appointments')
                if (isset($column_config['fn']) && is_callable($column_config['fn'])) {
                    // Skip if already processed in second pass (model columns without dot notation)
                    if (!strpos($key_cok, '.') && in_array($key_cok, $columns)) {
                        continue;
                    }
                    // Apply custom function
                    $row->{$key_cok} = call_user_func($column_config['fn'], $rows_raw[$key]);
                }
            }
        }

        if ($model_rows->getLastError() == '') {
            $total = $db->getVar(...$this->query->getTotal());
        } else {
            $total = 0;
        }
       
        $info = $model_list->getListStructure($columns, $model_rows->getPrimaryKey());
        $page_info = $model_list->getPageInfo($total);
        if ($this->request_action != '') {
            $page_info->setAction($this->request_action);
        }
        $page_info->setDefaultLimit($this->default_limit);

        // Applica le colonne personalizzate
        $this->applyCustomColumns($model_list);

        // Applica le funzioni personalizzate alle righe
       
        // Gestione footer
        if ($this->footer_data !== null) {
            $page_info->setFooter(true);
            
            // Crea il footer row completo basato sulle colonne della query
            $footer_row = (object) [];
            foreach ($columns as $index => $column) {
                $footer_row->$column = $this->footer_data[$index] ?? '';
            }

            // Aggiungi la riga del footer ai risultati
            $rows[] = $footer_row;
        }

        // Configura azioni se specificate
        if (!empty($this->actions)) {
            $info->setAction($this->actions);
        }

        $adding_return = [];
        if ($this->function_results !== null) {
            $adding_return = $this->function_results;
            if (!is_array($adding_return)) {
                $adding_return = ['function_results' => $adding_return];
            }
        }

        if (!empty($this->bulk_actions)) {
            $bulk_actions = [];
            foreach ($this->bulk_actions as $action => $single_action) {
                $bulk_actions[$action] = $single_action['label'];
            }
            $page_info->setBulkActions($bulk_actions);
        }

        if (!empty($this->table_attrs)) {
            $page_info['table_attrs'] = $this->table_attrs;
        }

        // Passa le condizioni per classi dinamiche
        if (!empty($this->row_conditions)) {
            $page_info['row_conditions'] = $this->row_conditions;
        }

        if (!empty($this->column_conditions)) {
            $page_info['column_conditions'] = $this->column_conditions;
        }

        return [
            'rows' => $rows,
            'info' => $info,
            'page_info' => $page_info,
            ...$adding_return
        ];
    }

    /**
     * Get HTML table string
     * 
     * @return string Complete HTML table ready for display
     */
    public function render(): string {
        $data = $this->getData();
        return  Get::themePlugin('table', [
            'info' => $data['info'],
            'rows' => $data['rows'],
            'page_info' => $data['page_info'],
            'table_attrs' => $this->table_attrs
        ]);
       
    }

    /**
     * Get results from executed action functions
     * 
     * @return array|null Results from action callbacks, null if no actions executed
     */
    public function getFunctionsResults(): array {
        return $this->function_results;
    }

    /**
     * Get HTML table with additional data (for AJAX responses)
     * @return array Array containing 'html' key with table HTML plus any action results
     */
    public function getResponse(): array {
        if ($this->update_table !== false) {
            $data = $this->getData();
        }
        $response = [];
        if ($this->function_results !== null) {
            if (!is_array($this->function_results)) {
                $response = ['function_results' => $this->function_results];
            } else {
                $response = $this->function_results;
            }
        }
        if (is_array($this->action_response) && $this->action_response !== null) {
            $response = array_merge($response, $this->action_response);
        }
         if ($this->update_table !== false) {
            $response['html'] = Get::themePlugin('table', [
                'info' => $data['info'],
                'rows' => $data['rows'],
                'page_info' => $data['page_info'],
                'table_attrs' => $this->table_attrs,
            ]);
        }
        
        $response['table_id'] = $this->table_id;
        return $response;
    }

    /**
     * Factory method to create TableBuilder instance
     * 
     * @param AbstractModel $model The model instance to use
     * @param string $table_id Unique identifier for this table
     * @param array|null $request Optional custom request parameters
     * @return self New TableBuilder instance
     * 
     * @example TableBuilder::create($this->model, 'idTablePosts')
     */
    public static function create($model, $table_id, $request = null): self {
        return new self($model, $table_id, $request);
    }

    // Metodi privati
    
    private function updateQuery(): void {
      
        // Applica solo limit e order alla query esistente senza resetarla
        $request = $this->request;
        
        // Gestione order (copiata da ModelList::query_from_request)
        if (!$this->modellist_service->default_order_field) {
            $this->modellist_service->default_order_field = $this->modellist_service->primary_key;
            $this->modellist_service->default_order_dir = 'desc';
        }
        
        $order_field = $request['order_field'] ?? $this->modellist_service->default_order_field;
        $order_dir = $request['order_dir'] ?? $this->modellist_service->default_order_dir;
        
        // Apply sort mapping if exists
        if (isset($this->sort_mappings[$order_field])) {
            $order_field = $this->sort_mappings[$order_field];
        }
        
        // Gestione limit (copiata da ModelList::query_from_request)
        $limit = _absint($request['limit'] ?? $this->default_limit);
        $page = _absint($request['page'] ?? 1);
        
        if ($limit < 1) {
            $limit = $this->default_limit;
        }
        if ($page < 1) {
            $page = 1;
        }
        
        $limit_start = ($page * $limit) - $limit;
        if ($limit_start < 0) {
            $limit_start = 0;
        }
        
        // Applica limit e order alla query esistente
        if ($limit > 0) {
            $this->query->limit($limit_start, $limit);
        }
        
        if (str_contains($order_field, '.')) {
            $order_field = explode('.', $order_field);
            if (count($order_field) == 2) {
                $this->query->orderHas($order_field[0], $order_field[1], $order_dir);
            }
        } else {
            $this->query->order($order_field, $order_dir);
        }
    }

    private function applyCustomColumns($model_list): void {
        
        // Applica le modifiche alle colonne esistenti
        foreach ($this->custom_columns as $key => $column) {
            if ($column['action'] === 'add') {
                // Per add, crea la colonna con tutti i parametri specificati
                $label = $column['label'] ?? $key;
                $type = $column['type'] ?? 'html';
                $options = $column['options'] ?? [];
                $order = !($column['disable_sort'] ?? false); // Se disable_sort è true, order è false
                $model_list->list_structure->setColumn($key, $label, $type, $order, false, $options);
            } elseif ($column['action'] === 'modify') {
                // Per modify, aggiorna solo i parametri specificati
                if (isset($column['label'])) {
                    $model_list->list_structure->setLabel($key, $column['label']);
                }
                if (isset($column['type'])) {
                    $model_list->list_structure->setType($key, $column['type']);
                }
                if (isset($column['disable_sort'])) {
                    $model_list->list_structure->setOrder($key, !$column['disable_sort']);
                }
                if (isset($column['options'])) {
                    $model_list->list_structure->setOptions($key, $column['options']);
                }
            } elseif ($column['action'] === 'delete') {
                $model_list->list_structure->deleteColumn($key);
            } elseif ($column['action'] === 'reorder' && isset($column['column_order'])) {
                $model_list->list_structure->reorderColumns($column['column_order']);
            }
        }

        // Nasconde le colonne specificate
        foreach ($this->hidden_columns as $key) {
            $model_list->list_structure->hideColumn($key);
        }
    }

    private function createModellistService(): ModelList {
        $model_list = new ModelList($this->model->getTable(), $this->table_id, $this->model->getDb());
        $list = $this->model->getRules('list', true);
        // Crea la struttura delle colonne usando ListStructure
        $list_structure = new ListStructure();
        
        foreach ($list as $key => $value) {
            // Auto-detect file type based on form-type when main type is 'array'
            $column_type = $value['type'] ?? 'html';
            if ($column_type === 'array' && isset($value['form-type']) && $value['form-type'] === 'file') {
                $column_type = 'file';
            }
            $list_structure->setColumn($key, $value['label'] ?? $key, $column_type);
        }
       
        $model_list->setListStructure($list_structure);
        $model_list->setRequest($this->request);
      
        return $model_list;
    }

    /**
     * Automatically configure array columns based on their type
     * - If type=array and form-type=image: apply asImage()
     * - If type=array and form-type=file: apply asFile()
     * - If type=array (text or other): hide the column
     */
    private function autoConfigureArrayColumns(): void {
        $all_data = $this->model->getRules();

        foreach ($all_data as $key => $value) {
            $column_type = $value['type'] ?? null;

            if ($column_type === 'array') {
                $form_type = $value['form-type'] ?? null;

                if ($form_type === 'image') {
                    // Applica asImage automaticamente se non è già configurato
                    if (!isset($this->custom_columns[$key])) {
                        $this->asImage($key);
                    }
                } elseif ($form_type === 'file') {
                    // Applica asFile automaticamente se non è già configurato
                    if (!isset($this->custom_columns[$key])) {
                        $this->asFile($key);
                    }
                } else {
                    // Nascondi la colonna se è array ma non file o image
                    if (!isset($this->custom_columns[$key]) && !in_array($key, $this->hidden_columns)) {
                        $this->hideColumn($key);
                    }
                }
            }
        }
    }

    private function getRequestParams($table_id): array {
        return $_REQUEST[$table_id] ?? [];
    }

    /**
     * Extract value from dot notation path (e.g., 'doctor.name')
     * Handles belongsTo (single object), hasOne (single object), and hasMany (array of objects)
     *
     * @param object $row The current row data
     * @param string $path Dot notation path (e.g., 'doctor.name' or 'tags.0.name')
     * @return mixed The extracted value or empty string if not found
     */
    private function extractDotNotationValue($row, $path) {
        $parts = explode('.', $path);
        $current = $row;

        foreach ($parts as $index => $part) {
            // Check if current is still valid
            if (!is_object($current) && !is_array($current)) {
                return '';
            }

            // Access array element by numeric index
            if (is_array($current) && is_numeric($part)) {
                $current = $current[$part] ?? null;
                continue;
            }

            // Access object property
            if (is_object($current)) {
                if (!isset($current->$part)) {
                    return '';
                }
                $current = $current->$part;
                continue;
            }

            // Access array element by key
            if (is_array($current)) {
                if (!isset($current[$part])) {
                    return '';
                }
                $current = $current[$part];
                continue;
            }

            return '';
        }

        // Handle hasMany results (array of objects) - show count or first item
        if (is_array($current) && count($current) > 0) {
            // Check if it's an array with numeric keys (typical hasMany result)
            $first_key = array_key_first($current);
            if (is_numeric($first_key) && is_object($current[$first_key])) {
                // If it's an array of objects, show count
                return count($current) . ' items';
            }
            // If it's already a formatted array (like image/file data), return as-is
            // Check if array has 'url' or 'name' keys (typical formatted array)
            if (isset($current[$first_key]['url']) || isset($current[$first_key]['name'])) {
                return $current; // Return the array for asImage/asFile processing
            }
            // If it's an array of scalars, join them
            if (!is_object($current[$first_key]) && !is_array($current[$first_key])) {
                return implode(', ', $current);
            }
            // Otherwise return the array as-is
            return $current;
        }

        // Return scalar value, array, or empty string
        return $current ?? '';
    }

    /**
     * Create a SearchBuilder instance linked to this table
     *
     * @return SearchBuilder SearchBuilder instance for creating search forms
     *
     * @example $search_builder = $table_builder->createSearchBuilder()
     */
    public function createSearchBuilder(): SearchBuilder {
        return new SearchBuilder($this->table_id);
    }

    /**
     * Get the table ID for external use
     * 
     * @return string The table ID
     */
    public function getTableId(): string {
        return $this->table_id;
    }

    /**
     * Check if the request is from the table or external
     */
    public function isInsideRequest(): bool {
        return isset($_REQUEST['is-inside-request']);
    }

    /**
     * Register custom formatters in the model
     * This method is called before getFormattedData() to register all custom column handlers
     *
     * @return void
     */
    private function registerCustomFormatters(): void {
        foreach ($this->custom_columns as $key => $column_config) {
            if (isset($column_config['fn']) && is_callable($column_config['fn'])) {
                // Register the handler in the model for get_formatted
                $this->model->registerMethodHandler($key, 'get_formatted', $column_config['fn']);
            }
        }
    }
}