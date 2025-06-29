<?php
namespace MilkCore;
use MilkCore\Get;
use MilkCore\Query;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * page: modellist.class.php
 * La classe ModelList aiuta a gestisce la visualizzazione delle tabelle html
 * a partire da una tabella mysql 
 * 
 * @package     MilkCore
 * @subpackage  ModelList
 */

class ModelList
{
    public $order = true; // lo modifico pubblicamente
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
    var $table_structure = null;
    var $fn_filter = [];
    var $db;
    var $page_info;
    
    /**
     * Costruttore della classe ModelList
     * 
     * @param string $table Nome della tabella
     * @param string|null $table_id ID della tabella (opzionale)
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
        $this->add_filter('search', [$this, 'filter_search']);
    }

    /**
     * La struttura delle colonne della tabella
     * [ 'row_field' => ['type'=>string, 'label' => string, 'primary' => bool]
     * type: hidden, checkbox, text, select;
     * label: il nome della colonna
     */
    public function set_list_structure($list_structure)
    {
        if (is_array($list_structure)) {
            $this->list_structure = new ListStructure($list_structure);
        } elseif ($list_structure instanceof ListStructure) {
            $this->list_structure = $list_structure;
        }
    }
    
    /**
     * La struttura delle colonne della tabella
     */
    public function get_list_structure() {
        if (count($this->list_structure) === 0) {
            // Se non è stata impostata la imposto in automatico con i campi della tabella
            $this->list_structure['checkbox'] = ['type'=>'checkbox', 'label' => ''];
            $table_structure = $this->get_table_structure();
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
        return $this->list_structure;
    }

    /**
     * Se si chiama questa funzione non viene impostato l'ordinamento
     */
    public function set_no_order() {
        $this->order = false;
        
        // Aggiorna anche la list_structure se già impostata
        if (count($this->list_structure) > 0) {
            $this->list_structure->disable_all_order();
        }
    }
    
    /**
     * Setta il limite di righe per pagina
     */
    public function set_limit($limit){
        $this->default_limit = $limit;
    }

    /**
     * La struttura delle colonne della tabella
     */
    public function get_table_structure(): ListStructure|array|null {
        $this->db->get_columns($this->table);
        foreach ($this->db->get_columns($this->table) as $row) {
            $this->table_structure[$row->Field] = $row;
            if ($row->Key == 'PRI') {
                $this->primary_key = $row->Field;
            }
        }
        return $this->table_structure;
    }

    /**
     * Aggiunge un filtro di ricerca
     */
    public function add_filter($filter_type, $fn) {
        $this->fn_filter[$filter_type] = $fn;
    }

    public function set_primary_key($primary_key) {
        $this->primary_key = $primary_key;
    }

    /**
     * Imposta i parametri della richiesta per la query
     * @return MilkCore\Query
     */
    public function query_from_request($request = null): Query {
        if ($request == null) {
            $request = $_REQUEST[$this->table_id] ?? [];
        }
        $this->order_field = $request['order_field'] ?? $this->primary_key;
        $this->order_dir = $request['order_dir'] ?? 'desc';
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
  
        $query = new Query($this->table);
        if ($limit > 0) {
            $query->limit($limit_start, $limit);
        }
        $query->order($this->order_field, $this->order_dir);
        
        /**
         * La colonna filter è un array di filtri tipofiltri:valore
         * table.js ha le funzioni per aggiungere modificare e rimuovere i filtri
         */
        $this->filters = $request['filters'] ?? '';
        if ($this->filters != '') {
            $tmp_filters = json_decode($this->filters);
            foreach ($tmp_filters as $filter) {
                $filter_type = explode(':', $filter);
                $filter = implode(':', array_slice($filter_type, 1));
                $filter_type = $filter_type[0];
                if (isset($this->fn_filter[$filter_type])) {
                    call_user_func($this->fn_filter[$filter_type], $query, $filter);
                } 
            }
        }
        return $query;
    }

    /**
     * Filtra la query per la ricerca
     */
    public function filter_search(Query $query, $search) {
        $list_structure = $this->get_table_structure();
        if (empty($list_structure)) {
            return;
        }
        foreach ($list_structure as $field => $row) {
            $query->where('`'.$field.'` LIKE ? ', ['%'.$search.'%'], 'OR');
        }
    }

    function set_request($request) {
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
     * Page_info sono le informazioni che servono alla tabella per funzionare
     * @param int $total Numero totale di record
     * @return PageInfo
     */
    function get_page_info($total): PageInfo {
        $limit_start = ($this->page * $this->limit) - $this->limit;
        if ($limit_start < 0) {
            $limit_start = 0;
        }
       
       
        $this->page_info->set_page($_REQUEST['page'] ?? '')
            ->set_action($_REQUEST['action'] ?? '')
            ->set_id($this->table_id)
            ->set_limit($this->limit)
            ->set_limit_start($limit_start)
            ->set_order_field($this->order_field)
            ->set_order_dir($this->order_dir)
            ->set_total_record($total)
            ->set_filters($this->filters)
            ->set_footer(false)
            ->set_ajax(true)
            ->set_pagination(true)
            ->set_json((($_REQUEST['page-output'] ?? '') == 'json'));
 
        return $this->page_info;
    }

    /**
     * Draw chart 
     * @param array $data
     * @param array $structure ['label' => 'string', 'axis' => 'x|y']
     * Deve esserci un solo campo x poi gli altri sono y di default
     * 
     */
    function get_data_chart($data, $structure) {
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