<?php
namespace App\Modellist;

use App\Database\Query;
use App\Get;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * page: modellist.class.php
 * The ModelList class helps manage the display of HTML tables
 * starting from a MySQL table
 *
 * @package     App
 * @subpackage ModelList
*/

class ModelList
{
    public $order = true; // I'm editing it publicly
    var $table = '';
    var $table_id = '';
    var $list_structure;
    var $primary_key = '';
    var $limit;
    var $default_limit = 10;
    var $page;
    var $filters;
    var $order_field;
    var $order_dir;
    var $default_order_field;
    var $default_order_dir;
    var $table_structure = null;
    var $fn_filter = [];
    var $db;
    var $page_info;
    var $filter_applied = [];
    var $static_model = null;
    
    /**
     * ModelList class constructor
     *
     * @param string $table Table name
     * @param string|null $table_id Table ID (optional)
     */
    public function __construct($table, $table_id = null, $db = null)
    {
        if ($db == null) {
            $db = Get::db();
        }
        $this->db = $db;
        $this->table = $table;
        if ($table_id == null) {
            $this->table_id = $_REQUEST['table_id'] ?? _raz(uniqid('table_list_', true));
        } else {
            $this->table_id = $table_id;
        }
        $this->page_info = new PageInfo();
        // Inizializzo la struttura della lista come un oggetto ListStructure
        $this->list_structure = new ListStructure();
        // aggiungo il filtro di ricerca di default search
         $this->addFilter('search', [$this, 'filterSearch']);
    }

    /**
     * Set the structure of the table columns
     * [ 'row_field' => ['type'=>string, 'label' => string, 'primary' => bool]
     * type: hidden, checkbox, text, select;
     * label: the name of the column
     */
    public function setListStructure($list_structure)
    {
        if (is_array($list_structure)) {
            $this->list_structure = new ListStructure($list_structure);
        } elseif ($list_structure instanceof ListStructure) {
            $this->list_structure = $list_structure;
        }
    }

    /**
     * Set static model
     */
    public function setModel($model) {
        $this->static_model = $model;
    }
    
    /**
     * Get the structure of the table columns
     * @param array $columns The columns of the table 
     * @param string|null $primary_key The primary key of the table Send primary key and rows allow to set structure without get_table_structure
     */
    public function getListStructure($columns = [], $primary_key = null) {
        if (count($this->list_structure) === 0) {
            // If not set, I set it automatically with the table fields
           // $this->list_structure['checkbox'] = ['type'=>'checkbox', 'label' => ''];
            if (is_array($columns) && count($columns) > 0 ) {
                if ($primary_key == null) {
                    $this->getTableStructure();
                } else {
                    $this->primary_key = $primary_key;
                }
                foreach ($columns as $field) {
                    if ($this->primary_key == $field) {
                        $this->list_structure[$field] = ['type' => 'text', 'label' => $field, 'primary' => true, 'order' =>  $this->order];
                    } else {
                        $this->list_structure[$field] = ['type' => 'text', 'label' => $field, 'order' =>  $this->order];
                    }
                }
            } else {
                $table_structure = $this->getTableStructure();
                if (is_countable($table_structure)) {
                    foreach ($table_structure as $field => $row) {
                        if ($row->Key == 'PRI') {
                            $this->list_structure[$field] = ['type' => 'text', 'label' => $field, 'primary' => true, 'order' =>  $this->order];
                        } else {
                            $this->list_structure[$field] = ['type' => 'text', 'label' => $field, 'order' =>  $this->order];
                        }
                    }
                }
            }
        } else {
            // Remove columns from list_structure that are not present in rows
            if (is_array($columns) && count($columns) > 0 ) {
                foreach ($this->list_structure as $field => $_) {
                    if ($field == $primary_key) {
                        $this->list_structure->setPrimary($field);
                        if (!in_array($field, $columns)) {
                            $this->list_structure->hideColumn($field);
                        }
                    }else if (!in_array($field, $columns)) {
                        $this->list_structure->deleteColumn($field);
                    }
                }
            }
        }
        return $this->list_structure;
    }

    /**
     * Calling this function does not set the sort order.
     */
    public function setNoOrder() {
        $this->order = false;
        
        // Aggiorna anche la list_structure se già impostata
        if (count($this->list_structure) > 0) {
            $this->list_structure->disableAllOrder();
        }
    }
    
    /**
     * Set the limit of rows per page
     */
    public function setLimit($limit, $request = null) {
        $this->default_limit = $limit;
        if (!isset($request)) {
            $request = $_REQUEST[$this->table_id] ?? [];
        }
        $limit = _absint($request['limit'] ?? $this->default_limit);
        if ($limit < 1) {
            $limit = $this->default_limit;
        }
        $this->limit = $limit;
    }

    /**
     * Get the table structure
     */
    public function getTableStructure(): ListStructure|array|null {
        $this->db->getColumns($this->table);
        foreach ($this->db->getColumns($this->table) as $row) {
            $this->table_structure[$row->Field] = $row;
            if ($row->Key == 'PRI') {
                $this->primary_key = $row->Field;
            }
        }
        return $this->table_structure;
    }

    /**
     * Add a search filter
     */
    public function addFilter($filter_type, $fn) {
        $this->fn_filter[$filter_type] = $fn;
    }

    public function setPrimaryKey($primary_key) {
        $this->primary_key = $primary_key;
    }

    /**
     * Set the default sorting order for the table
     * This defines which field and direction will be used for sorting when no user sorting is applied
     * 
     * @param string $order_field The field name to sort by
     * @param string $order_dir The sorting direction ('asc' or 'desc', default: 'desc')
     */
    public function setOrder($order_field, $order_dir="desc", $request = null) {
        $this->default_order_field = $order_field;
        $this->default_order_dir = $order_dir;
        if (!isset($request)) {
            $request = $_REQUEST[$this->table_id] ?? [];
        }
        $this->order_field = $request['order_field'] ?? $this->default_order_field;
        $this->order_dir = $request['order_dir'] ?? $this->default_order_dir;
    }

    /**
     * Set the request parameters for the query
     * @return \App\Database\Query
     */
    public function queryFromRequest($request = null): Query {
        if ($request == null) {
            $request = $_REQUEST[$this->table_id] ?? [];
        }
        if (!$this->default_order_field) {
            $this->default_order_field = $this->primary_key;
            $this->default_order_dir = 'desc';
        }
        if (!isset($request['order_field'])) {
            $this->order_field  =  $this->default_order_field;
            $this->order_dir = $this->default_order_dir;
        } else {
            $this->order_field = $request['order_field'] ?? $this->primary_key;
            $this->order_dir = $request['order_dir'] ?? 'desc';
        }
       
        $limit = _absint($request['limit'] ?? $this->default_limit);
        $page = _absint($request['page'] ?? 1);
       
        if ($limit < 1) {
            $limit = $this->default_limit;
        }
        if ($page < 1) {
            $page = 1;
        }
        $this->limit = $limit;
        $this->page = $page;
        $limit_start = ($page * $limit) - $limit;
        if ($limit_start < 0) {
            $limit_start = 0;
        }
        if ($this->static_model) {
            $query = new Query($this->table, $this->db, $this->static_model);
        } else {
            $query = new Query($this->table, $this->db);
        }
        if ($limit > 0) {
            $query->limit($limit_start, $limit);
        }
        $query->order($this->order_field, $this->order_dir);
        
        /**
         * The filter column is an array of filters type filters:value
         * table.js has functions for adding, editing, and removing filters
         */
        $this->filters = $request['filters'] ?? '';
        if ($this->filters != '') {
            $tmp_filters = json_decode($this->filters);
            foreach ($tmp_filters as $filter) {
                $filter_type = explode(':', $filter);
                $filter = implode(':', array_slice($filter_type, 1));
                $filter_type = $filter_type[0];
                if (isset($this->fn_filter[$filter_type]) && !in_array($filter_type, $this->filter_applied)) {
                    $this->filter_applied[] = $filter_type;
                    call_user_func($this->fn_filter[$filter_type], $query, $filter);
                } 
            }
        }
        return $query;
    }

    /**
     * Apply filters to the query
     */
    public function applyFilters($query, $request) {
        if ($request == null) {
            $request = $_REQUEST[$this->table_id] ?? [];
        }
        $this->filters = $request['filters'] ?? '';
        if ($this->filters != '') {
            $tmp_filters = json_decode($this->filters);
            foreach ($tmp_filters as $filter) {
                $filter_type = explode(':', $filter);
                $filter = implode(':', array_slice($filter_type, 1));
                $filter_type = $filter_type[0];
                if (isset($this->fn_filter[$filter_type]) && !in_array($filter_type, $this->filter_applied)) {
                    $this->filter_applied[] = $filter_type;
                    call_user_func($this->fn_filter[$filter_type], $query, $filter);
                } 
            }
        }
    }

    /**
     * Filter the query for search
     */
    public function filterSearch(Query $query, $search) {
        $list_structure = $this->getTableStructure();
        if (empty($list_structure)) {
            return;
        }
        $search = trim($search);
        if (strlen($search) < 2) {
            return;
        }
        $string_where = [];
        $array_var = [];
        foreach ($list_structure as $field => $row) {
            $string_where[] = $this->db->qn($field) . ' LIKE ? ';
            $array_var[] = '%'.$search.'%';
        }
        if (count($string_where) > 0) {
            $query->where(implode(" OR ", $string_where), $array_var);
        }
    }

    function setRequest($request) {
        if (isset($request['limit'])) {
            $this->limit = _absint($request['limit']);
        }
        if (isset($request['page'])) {
            $this->page = _absint($request['page']);
        }
        if (isset($request['order_field'])) {
            $this->order_field = $request['order_field'];
        }
        if (isset($request['order_dir'])) {
            $this->order_dir = $request['order_dir'];
        }
        if (isset($request['filters'])) {
            $this->filters = $request['filters'];
        }
    }

    /**
     * Page_info is the information the table needs to function.
     * @param int $total Total number of records.
     * @return PageInfo
     */
    function getPageInfo($total): PageInfo {
        $limit_start = ($this->page * $this->limit) - $this->limit;
        if ($limit_start < 0) {
            $limit_start = 0;
        }

        $this->page_info->setPage($_REQUEST['page'] ?? '')
            ->setAction($_REQUEST['action'] ?? '')
            ->setId($this->table_id)
            ->setLimit($this->limit)
            ->setLimitStart($limit_start)
            ->setOrderField($this->order_field)
            ->setOrderDir($this->order_dir)
            ->setTotalRecord($total)
            ->setFilters($this->filters)
            ->setFooter(false)
            ->setAjax(true)
            ->setPagination(true)
            ->setJson((($_REQUEST['page-output'] ?? '') == 'json'));
 
        return $this->page_info;
    }

    /**
     * Draw chart 
     * @param array $data
     * @param array $structure ['label' => 'string', 'axis' => 'x|y']
     * Must have one x field then the others are y by default
     * 
     */
    function getDataChart($data, $structure) {
        $labels = [];
        $dataset = [];
        foreach ($data as $row) {
            foreach ($structure as $field => $structure_field) {
                if ($structure_field['axis'] ?? 'y' == 'x') {
                    $labels[] = $row->$field;
                } else {
                    if (!isset($dataset[$field])) {
                        $dataset[$field] = [];
                    }
                    $dataset[$field][] = $row->$field;
                }
                
            }
        }
        $data = [
            'labels' => $labels,
            'datasets' => []
        ];
    
        $backgroundColors = ['#9BD0F5', '#FFB1C1', '#C2F5FF', '#FFC1B1', '#F5FFC2', '#C2F5FF', '#FFC1B1', '#F5FFC2'];
        $borderColors = ['#36A2EB', '#A02A4D', '#36A2EB', '#A02A4D', '#36A2EB', '#A02A4D', '#36A2EB', '#A02A4D'];
        $countColor = 0;
        foreach ($dataset as $field => $data_field) {
            if (isset($structure[$field]['type'])) {
                $type = $structure[$field]['type'];
            } else {
                $type = 'bar';
            }
            if (isset($structure[$field]['borderColor'])) {
                $borderColor = $structure[$field]['borderColor'];
            } else {
                $borderColor = $borderColors[$countColor%count($borderColors)];
            }
            if (isset($structure[$field]['backgroundColor'])) {
                $backgroundColor = $structure[$field]['backgroundColor'];
            } else {
                $backgroundColor = $backgroundColors[$countColor%count($backgroundColors)];
            }
            if (isset($structure[$field]['borderWidth'])) {
                $borderWidth = $structure[$field]['borderWidth'];
            } else {
                $borderWidth = 1;
            }
            $data['datasets'][] = [
                'type' => $type,
                'label' => $structure[$field]['label'],
                'data' => $data_field,
                'borderColor' => $borderColor,
                'backgroundColor' => $backgroundColor,
                'borderWidth' => $borderWidth
            ];
            $countColor++;
        }
        return $data;
    }
}