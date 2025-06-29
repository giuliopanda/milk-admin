<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * SQL Query Builder Class
 * 
 * This class provides a fluent interface for building SQL queries,
 * allowing you to specify components like SELECT, WHERE, ORDER BY, and LIMIT
 * in a simple and intuitive way.
 * 
 * @example
 * ```php
 * // Basic query example
 * $query = new Query('users');
 * $query->select(['id', 'username', 'email'])
 *       ->where(['status = ?'], [1])
 *       ->order('created_at', 'DESC')
 *       ->limit(0, 10);
 * list($sql, $params) = $query->get();
 * 
 * // Join example
 * $query = new Query('orders');
 * $query->select(['orders.id', 'orders.total', 'users.name'])
 *       ->from('LEFT JOIN users ON users.id = orders.user_id')
 *       ->where(['orders.status = ?'], ['completed'])
 *       ->get();
 * ```
 *
 * @package     MilkCore
 */

class Query
{
   /**
     * The table name for the query
     *
     * @var string
     */
    var $table = '';

    /**
     * List of fields to select
     * 
     * Can be an array of field names or a string with comma-separated field names.
     *
     * @var array|string
     */
    var $select = [];

    /**
     * Additional FROM or JOIN clauses
     * 
     * Contains additional tables to join in the query.
     *
     * @var array
     */
    var $from = [];

    /**
     * WHERE conditions for the query
     * 
     * Contains the conditions to filter the query results.
     *
     * @var array
     */
    var $where = [];

    /**
     * ORDER BY clauses for the query
     * 
     * Specifies the sorting order of the query results.
     *
     * @var array
     */
    var $order = [];

    /**
     * LIMIT clause for the query
     * 
     * Limits the number of results returned by the query.
     *
     * @var array
     */
    var $limit = [];
    /**
     * GROUP BY clause for the query
     * 
     * Groups the query results by specified fields.
     *
     * @var string
     */
    var $group = '';
    /**
     * Database connection instance
     *
     * @var \MilkCore\MySql
     */
    var $db = null;
    /**
     * HAVING conditions for the query
     * 
     * Used with GROUP BY to filter grouped results.
     *
     * @var array
     */
    var $having = [];

    /**
     * Database type
     *
     * @var string
     */
    var $db_type = '';

    /**
     * Query class constructor
     * 
     * Initializes a new Query instance for the specified table.
     * 
     * @example
     * ```php
     * // Basic initialization
     * $query = new Query('users');
     * 
     * // With custom database connection
     * $customDb = new MySql($host, $user, $password, $database);
     * $query = new Query('users', $customDb);
     * ```
     *
     * @param string $table The table name to build the query on
     * @param \MilkCore\MySql|null $db Optional custom database connection
     */

    function __construct($table, $db = null) {
        $this->table = $table;
        $this->select = [];
        $this->where = [];
        $this->order = [];
        $this->limit = [];
        $this->having = [];
        if ($db == null) {
            $this->db = Get::db();
        } else {
            $this->db = $db;
        }

        $className = get_class($this->db);
        if (strpos($className, 'MySQL') !== false || strpos($className, 'MySql') !== false) {
            $this->db_type = 'mysql';
        } elseif (strpos($className, 'SQLite') !== false) {
            $this->db_type = 'sqlite';
        } elseif (strpos($className, 'Postgres') !== false || strpos($className, 'PostgreSQL') !== false) {
            $this->db_type = 'postgres';
        } else {
            // Default a MySQL se non riconosciuto
            $this->db_type = 'mysql';
        }
    }

    /**
     * Specifies the fields to select
     * 
     * Sets the fields to be selected in the query. Fields can be specified as an array
     * or as a comma-separated string.
     * 
     * @example
     * ```php
     * // Select specific fields
     * $query->select(['id', 'username', 'email']);
     * 
     * // Select with aliases
     * $query->select(['u.id', 'u.username AS user', 'COUNT(*) AS total']);
     * 
     * // Using a string
     * $query->select('id, username, email');
     * ```
     *
     * @param string|array $fields Fields to select
     * @param bool $clear Whether to clear previous selections (default: true)
     * @return $this Allows method chaining
     */
    public function select($fields, $clear = true)
    {
        if ($clear) {
            $this->select = [];
        }
        if (is_array($fields)) {
            $this->select = array_merge($this->select, $fields);
        } else {
            $this->select[] = $fields;
        }
        return $this;
    }

    /**
     * Checks if any fields have been selected
     * 
     * @return bool True if fields have been selected, false otherwise
     */
    public function has_select() {
        return count($this->select) > 0;
    }

    /**
     * Specifies additional FROM or JOIN clauses
     * 
     * Adds JOIN statements to the query. The method automatically detects
     * JOIN types (INNER, LEFT, RIGHT, etc.) from the provided string.
     * 
     * @example
     * ```php
     * // Basic join
     * $query->from('JOIN categories ON products.category_id = categories.id');
     * 
     * // Left join
     * $query->from('LEFT JOIN users ON orders.user_id = users.id');
     * 
     * // Multiple joins
     * $query->from('JOIN users ON orders.user_id = users.id')
     *       ->from('LEFT JOIN products ON order_items.product_id = products.id');
     * ```
     *
     * @param string $from JOIN statement to add
     * @param bool $clear Whether to clear previous JOINs (default: false)
     * @return $this Allows method chaining
     */
    public function from($from, $clear = false)
    {
        if ($clear) {
            $this->from = [];
        }
        $array_join = ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS', 'NATURAL', 'STRAIGHT', 'JOIN'];
        $explode_from = explode(' ', $from);
        if (in_array(strtoupper($explode_from[0]), $array_join)) {
            $this->from[] = $from;
        } else {
            $this->from[] = ', '.$from;
        }
        return $this;
    }

    /**
     * Aggiunge condizioni WHERE alla query.
     * 
     * @param array $fields Condizioni WHERE es. 'campo1 = ? AND campo2 = ?'
     * @param array $params Parametri da passare al bind_param. es. ['valore1', 'valore2']
     * @param string $operator Operatore logico da utilizzare ('AND' o 'OR').
     * @return $this Permette il chaining dei metodi.
     * @todo
     */
    public function where($where, $params = [], $operator = 'AND') {
        $operator = strtoupper($operator) == 'OR' ? 'OR' : 'AND';
        $this->where[] = [$where, $params, $operator];
        
        return $this;
    }
    
    /**
     * Verifica se ha uno o più parametri where
     */
    public function has_where() {
        return count($this->where) > 0;
    }

     /**
     * Specifica l'ordinamento dei risultati.
     * 
     * @param string|array $field Campo o array di campi su cui ordinare.
     * @param string|array $dir Direzione dell'ordinamento ('asc' o 'desc') per campo singolo,
     *                         o array di direzioni per campi multipli.
     * @return $this Permette il chaining dei metodi.
     */
    public function order($field = '', $dir = 'asc', $clear = true) {
        if ($clear) {
            $this->order = [];
        }
        if ($field != '') {
            if (is_array($field)) {
                // Gestione campi multipli
                foreach ($field as $i => $f) {
                    $direction = is_array($dir) ? ($dir[$i] ?? 'asc') : $dir;
                    $this->order[] = [$f, $direction];
                }
            } else {
                $this->order[] = [$field, $dir];
            }
        }
        return $this;
    }

    public function has_order() {
        return count($this->order) > 0;
    }

    /**
     * Imposta il limite di risultati da restituire.
     * 
     * @param int $start Primo record da selezionare.
     * @param int $limit Numero di record da selezionare.
     * @return $this Permette il chaining dei metodi.
     */
    public function limit($start, $limit) {
        $this->limit = [_absint($start), _absint($limit)];
        return $this;
    }

    /**
     * Verifica se ha un limite
     */
    public function has_limit() {
        return count($this->limit) > 0;
    }


    /**
     * Imposta il gruppo di risultati.
     * 
     */
    public function group(string $group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * Verifica se ha un gruppo
     */
    public function has_group() {
        return $this->group != '';
    }

    
    /**
     * Aggiunge condizioni HAVING alla query.
     * 
     * @param string|array $having Condizioni HAVING es. 'COUNT(*) > ?'
     * @param array $params Parametri da passare al bind_param. es. ['10']
     * @param string $operator Operatore logico da utilizzare ('AND' o 'OR').
     * @return $this Permette il chaining dei metodi.
     */
    public function having($having, $params = [], $operator = 'AND') {
        $operator = strtoupper($operator) == 'OR' ? 'OR' : 'AND';
        $this->having[] = [$having, $params, $operator];
        return $this;
    }
        
    /**
     * Verifica se ha uno o più parametri having
     */
    public function has_having() {
        return count($this->having) > 0;
    }


    /**
     * Costruisce e ritorna la query SQL e i parametri da passare al bind_param.
     * 
     * @return array Array contenente la stringa SQL e i parametri per il bind_param.
     */
    public function get($convert = true) {
        $params = []; // saranno i parametri da passare al bind_param
        if ($this->select == []) {
            $this->select = ['*'];
        }
        $sql = 'SELECT '.implode(',', $this->select).' FROM '.$this->db->qn($this->table);
        if (count($this->from) > 0) {
           // var_dump ($this->from); 
            $sql .= ' '.implode(' ', $this->from);
        }
        
        [$where, $params] = $this->build_where();
        $sql .= $where;

        if ($this->group != '') {
            $sql .= ' GROUP BY '.$this->db->qn($this->group);
        }

        if (count($this->having) > 0) {
            $sql_having = '';
            foreach ($this->having as $having) {
                $having[0] = str_replace(';','',$having[0]);
                $having[2] = strtoupper($having[2]) == 'OR' ? 'OR' : 'AND';
                if ($sql_having == '') {
                    $sql_having .= "(".$having[0].")"; 
                } else {
                    $sql_having .= ' '.$having[2].' ('.$having[0].")";
                }
                if (is_scalar($having[1])) {
                    $having[1] = [$having[1]];
                }
                $params = array_merge($params, $having[1]);
            }
            if ($sql_having != '') {
                $sql .= ' HAVING '.$sql_having;
            }
        }

        if (count($this->order) > 0) {
            $orderParts = [];
            foreach ($this->order as $orderItem) {
                $direction = strtolower($orderItem[1]) == 'asc' ? 'ASC' : 'DESC';
                $orderParts[] = $this->db->qn($orderItem[0]).' '.$direction;
            }
            $sql .= ' ORDER BY '.implode(', ', $orderParts);
        }
        
        if (count($this->limit) > 0) { 
            $sql .= ' LIMIT '._absint($this->limit[0]).','._absint($this->limit[1]);
        }
        if ( $this->db_type != '' && $convert) {
            $converter = new QueryConverter($this->db_type);
            list($sql, $params) = $converter->convert($sql, $params);
        }
        return [$sql, $params];
    }

     /**
     * Costruisce e ritorna la query SQL per calcolare il numero totale di record.
     * 
     * @return array Array contenente la stringa SQL e i parametri per il bind_param.
     */
    public function get_total() {
        $params = []; // saranno i parametri da passare al bind_param
        $sql = 'SELECT COUNT(*) FROM '.$this->db->qn($this->table);
       
        [$where, $params] = $this->build_where();
        $sql .= $where;
        
        return [$sql, $params];
    }

    /**
     * Pulisce i parametri della query.
     * @param string $single Se una stringa "select" o "from" o "where" o "order" o "limit" viene passata come parametro, verrà pulito solo quel parametro.
     */

    public function clean($single = '') {
        if ($single == 'select') {
            $this->select = [];
        } elseif ($single == 'from') {
            $this->from = [];
        } elseif ($single == 'where') {
            $this->where = [];
        } elseif ($single == 'order') {
            $this->order = [];
        } elseif ($single == 'limit') {
            $this->limit = [];
        } else {
            $this->select = [];
            $this->from = [];
            $this->where = [];
            $this->order = [];
            $this->limit = [];
        }
    }

    /**
     * Costruisce la stringa WHERE della query.
     */
    private function build_where() {
        $params = [];
        $sql_where = '';
        if (count($this->where) > 0) {
            foreach ($this->where as $where) {
                $where[0] = str_replace(';','',$where[0]);
                $where[2] = strtoupper($where[2]) == 'OR' ? 'OR' : 'AND';
                if ($sql_where == '') {
                    $sql_where .= "(".$where[0].")"; 
                } else {
                    $sql_where .= ' '.$where[2].' ('.$where[0].")";
                }
                if (is_scalar($where[1])) {
                    $where[1] = [$where[1]];
                }
                $params = array_merge($params, $where[1]);
            }
            if ($sql_where != '') {
                return [' WHERE '.$sql_where, $params];
            }
        }
        return ['', []];
    }
   
}

