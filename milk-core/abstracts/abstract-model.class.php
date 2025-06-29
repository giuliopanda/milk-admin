<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Evita l'accesso diretto

/**
 * Abstract Model Class
 *
 * This is a base class for module data management. It provides basic CRUD operations,
 * error handling, and methods for building complex queries. This class works with a single
 * primary key and does not support multiple primary keys.
 *
 * @example
 * ```php
 * namespace Modules\BaseModule;
 * use MilkCore\AbstractModel;
 * 
 * !defined('MILK_DIR') && die(); // Prevent direct access
 * 
 * class BaseModuleModel extends AbstractModel
 * {
 *     public string $table = '#__base_module';
 *     public string $primary_key = 'id';
 *     public $object_class = 'BaseModuleObject';
 * }
 * ```
 *
 * @package     MilkCore
 * @subpackage  Abstracts
 */

abstract class AbstractModel
{
    /**
     * Database table name
     *
     * The name of the table in the database (with prefix #__)
     *
     * @var string
     */
    protected string $table = '';

    /**
     * Primary key name
     *
     * The name of the primary key field in the table.
     * If object_class is set, the primary key is taken from there.
     *
     * @var string
     */
    protected string $primary_key = '';

    /**
     * Database connection instance
     *
     * @var null|MySql|SQLite
     */
    protected null|MySql|SQLite $db = null;

    /**
     * Query results cache
     *
     * Used to store query results for faster retrieval
     *
     * @var array
     */
    protected array $cache = [];

    /**
     * Last error message
     *
     * Contains the last error message that occurred during an operation
     *
     * @var string
     */
    public string $last_error = '';

    /**
     * Error flag
     *
     * Indicates whether an error has occurred during the last operation
     *
     * @var bool
     */
    protected bool $error = false;

    /**
     * Records per page
     *
     * The number of records to display per page for pagination
     *
     * @var int
     */
    protected int $per_page = 20;

    /**
     * Associated object class
     *
     * The class of the object associated with this model.
     * This class should extend AbstractObject.
     *
     * @var string
     */
     protected string $object_class = 'MilkCore\BaseModuleObject';

    /**
     * Current query
     *
     * The current query being built
     *
     * @var Query|null
     */
    protected ?Query $current_query = null;

    /**
     * Last executed query
     *
     * The last query that was executed
     *
     * @var Query|null
     */
    protected ?Query $last_query = null;

    /**
     * Filter functions
     *
     * Array of filter functions to apply to queries
     *
     * @var array
     */
    protected array $fn_filter = [];

    /**
     * Constructor
     *
     * Initializes the model with a database connection and sets up the object class
     *
     * @param null|MySql|SQLite $db Optional database instance to use
     */
    public function __construct(null|MySql|SQLite $db = null) 
    {
        $this->db = $db ?? Get::db();
        $this->error = false;
        $this->last_error = '';

        
        if (is_scalar($this->object_class) && $this->object_class !== '') {
            $this->object_class = $this->iniziale_class($this->object_class);
            if ($this->primary_key === '' && $this->object_class && class_exists($this->object_class) && method_exists($this->object_class, 'get_primary_key')) {
                $primary_key = (new $this->object_class())->get_primary_key();
                $this->primary_key = $primary_key !== null ? $primary_key : '';
            }
        } else {
            throw new \RuntimeException('Object class not defined');
        }
    }

    /**
     * Create a new Query instance
     *
     * Creates a new Query instance for building complex queries
     *
     * @return Query A new Query instance
     */
    protected function new_query(): Query 
    {
        return new Query($this->table, $this->db);
    }

    /**
     * Get a record by its primary key
     *
     * Retrieves a record from the database using its primary key value
     *
     * @example
     * ```php
     * $post = $this->model->get_by_id(123);
     * if ($post) {
     *     echo $post->title;
     * }
     * ```
     *
     * @param mixed $id The primary key value
     * @param bool $use_cache Whether to use cache for data retrieval
     * @return object|null The record object or null if not found
     */
    public function get_by_id($id, bool $use_cache = true): ?object 
    {
        $this->error = false;
        $this->last_error = '';
        if ($use_cache && isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $query = $this->new_query();
        $result = $this->db->get_row(
            ...$query->where($this->primary_key . ' = ?', [$id])->get()
        );
        
        if ($result) {
            $result = new $this->object_class($result);
            if ($use_cache) {
                $this->cache[$id] = $result;
            }
        }
        return $result;
    }

    /**
     * Get a record by ID or return an empty object
     *
     * Returns a record by its primary key, or an empty object if not found
     *
     * @example
     * ```php
     * $post = $this->model->get_by_id_or_empty(123);
     * echo $post->title; // No need to check if $post exists
     * ```
     *
     * @param mixed $id The primary key value
     * @param array $merge_data Optional data to merge with the record
     * @return object The record object or an empty object
     */
    public function get_by_id_or_empty($id, array $merge_data = []): object {
        $obj = $this->get_by_id($id);
        if (!$obj) {
            $obj = $this->get_empty();
        }
        $obj->merge($merge_data);
        return $obj;
    }

    /**
     * Get a record for editing
     *
     * Retrieves a record for editing, applying edit rules
     *
     * @example
     * ```php
     * $data = $this->model->get_by_id_for_edit($id, Route::get_session_data());
     * if ($data === null) {
     *     Route::redirect_error('?page='.$this->page."&action=list", 'Invalid id');
     * }
     * ```
     *
     * @param mixed $id The primary key value
     * @param array $merge_data Additional data to merge with the record
     * @return object The record object with edit rules applied
     */
    function get_by_id_for_edit($id, array $merge_data = []): object {
        $data = $this->get_by_id_or_empty($id);
        $data->merge($merge_data);
        $data = $this->apply_edit_rules($data);
        return $data;
    }

    /**
     * Apply edit rules to a record
     *
     * Applies edit rules defined in the object class to a record
     *
     * @param object $data The record object
     * @return object The record object with edit rules applied
     */
    function apply_edit_rules(object $data): object {
        if (!method_exists($data, 'get_rules')) {
            return $data;
        }
        $rules = $data->get_rules();
        foreach ($data as $key => $value) {
            $name = _raz($key);
            if (isset($rules[$name]['_edit'])) {
                $fn = $rules[$name]['_edit'];
                if (is_callable($fn)) {
                    $data->$key = $fn($value, $data);
                }
            } else if (method_exists($data, 'edit_' . $name)) {
                $data->$key = $data->{'edit_' . $name}($value, $data);
            }
        }
        return $data;
    }

    public function get_primary_key(): string {
        return $this->primary_key;
    }

    /**
     * Get an empty object
     *
     * Returns an empty object of the associated class
     *
     * @example
     * ```php
     * $new_post = $this->model->get_empty();
     * $new_post->title = "New Title";
     * ```
     *
     * @param array $data Data to initialize the object with
     * @return object An empty object of the associated class
     */
    public function get_empty(array $data = []): object{
        return new $this->object_class($data);
    }

    /**
     * Save or update a record
     *
     * Saves or updates a record in the database
     *
     * @example
     * ```php
     * $data_to_save = ['title' => 'Updated title', 'content' => 'New content'];
     * $result = $this->model->save($data_to_save, 123);
     * if($result) {
     *     echo "Save success";
     * } else {
     *     echo "Save Error: ".$this->model->last_error;
     * }
     * ```
     *
     * @param array $data Data to save
     * @param mixed $id Primary key for update, If null the primary key will be used from the data array
     * @return bool|int False on error, otherwise the ID of the inserted/updated record
     */
    public function save(array $data, $id = null): bool
    {
        $this->error = false;
        $this->last_error = '';
        if ($this->primary_key != null && $id == null) {
            if (isset($data[$this->primary_key])) {
                $id = $data[$this->primary_key];
            }
        }
        try {
            $data = $this->prepare_data($data);
          
            if ($id != null && $id != 0 && $id != '') {
                // verifico se esiste il record altrimenti lo inserisco
                if (!$this->get_by_id($id)) {
                    $id = null;
                } 
            }
            if ($id != null && $id != 0 && $id != '') {
                $success = $this->db->update(
                    $this->table, 
                    $data, 
                    [$this->primary_key => $id],
                    1
                );
                
                if ($success) {
                    unset($this->cache[$id]);
                    return true;
                } else {
                    $this->error = true;
                    $this->last_error = $this->db->last_error;
                    Logs::set('errors', 'ERROR', 'Query Error : '.$this->db->last_error);
                    return false;
                }
                   
            }
            if (($id == null || $id == '' || $id == 0) && isset( $data[$this->primary_key])) {
                unset($data[$this->primary_key]);
            }
            if (!$this->db->insert($this->table, $data)) {
                
                $this->error = true;
                $this->last_error = $this->db->last_error;
                Logs::set('errors', 'ERROR', 'Query Error : '.$this->db->last_error);
                return false;
            }
            return true;
            
        } catch (\Exception $e) {
            $this->error = true;
            $this->last_error = $e->getMessage();
            Logs::set('errors', 'ERROR', 'Query Error : '.$this->db->last_error);
            return false;
        }
    }

    public function get_last_insert_id(): int {
        return (int)$this->db->insert_id();
    }

    /**
     * Delete a record
     *
     * Deletes a record from the database
     *
     * @example
     * ```php
     * if ($this->model->delete($id)) {
     *     return true;
     * } else {
     *     MessagesHandler::add_error($this->model->get_last_error());
     *     return false;
     * }
     * ```
     *
     * @param mixed $id Primary key of the record to delete
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete($id): bool 
    {
        $this->error = false;
        $this->last_error = '';

        try {
            $success = $this->db->delete(
                $this->table, 
                [$this->primary_key => $id]
            );

            if ($success) {
                unset($this->cache[$id]);
            }

            return (bool)$success;
        } catch (\Exception $e) {
            $this->error = true;
            $this->last_error = $e->getMessage();
            return false;
        }
    }

  
    /**
     * Clear the results cache
     *
     * Empties the cache of query results
     *
     * @example
     * ```php
     * $this->model->clear_cache();
     * ```
     *
     * @return void
     */
    public function clear_cache(): void 
    {
        $this->cache = [];
    }

    /**
     * Get the last error message
     *
     * Returns the last error message that occurred
     *
     * @example
     * ```php
     * echo $this->model->get_last_error();
     * ```
     *
     * @return string The last error message
     */
    public function get_last_error(): string 
    {
        return $this->last_error;
    }

    /**
     * Check if an error occurred
     *
     * Checks if an error occurred during the last database operation
     *
     * @example
     * ```php
     * if ($this->model->has_error()) {
     *     echo "An error occurred: ".$this->model->get_last_error();
     * }
     * ```
     *
     * @return bool True if an error occurred, false otherwise
     */
    public function has_error(): bool 
    {
        return $this->error;
    }

    /**
     * Reset the current query
     *
     * Resets the current query and stores it as the last query
     *
     * @return void
     */
    public function reset_query(): void 
    {
        $this->last_query = $this->current_query;
        $this->current_query = null;
    }

    /**
     * Add a WHERE clause to the current query
     *
     * @example
     * ```php
     * $this->model->where('title LIKE ?', ['%test%'])->get();
     * ```
     *
     * @param string $condition The SQL condition to add to the WHERE clause
     * @param array $params Parameters to pass to the query to prevent SQL injection
     * @return self Returns the current instance for method chaining
     */
    public function where(string $condition, array $params = []): self 
    {
        $this->get_current_query()->where($condition, $params);
        return $this;
    }

    /**
     * Add an ORDER BY clause to the current query
     *
     * @example
     * ```php
     * $this->model->order('title', 'desc')->get();
     * ```
     *
     * @param string|array $field Field or array of fields to order by
     * @param string $dir Direction of ordering ('asc' or 'desc')
     * @return self Returns the current instance for method chaining
     */
    public function order(string|array $field = '', string $dir = 'asc'): self 
    {
        $this->get_current_query()->order($field, $dir);
        return $this;
    }

    /**
     * Add a SELECT clause to the current query
     *
     * @example
     * ```php
     * $this->model->select('id, title')->get();
     * $this->model->select(['id', 'title'])->get();
     * ```
     *
     * @param array|string $fields Fields to select
     * @return self Returns the current instance for method chaining
     */
    public function select(array|string $fields): self 
    {
        $this->reset_query();
        if (is_array($fields)) {
            $fields = implode(', ', $fields);
        }
        $this->get_current_query()->select($fields);
        return $this;
    }

    /**
     * Add a FROM clause to the current query
     *
     * @example
     * ```php
     * $this->model->from('posts')->get();
     * $this->model->from('posts LEFT JOIN users ON posts.user_id = user.id')->get();
     * ```
     *
     * @param string $from The table or join to query
     * @return self Returns the current instance for method chaining
     */
    public function from(string $from): self 
    {
        $this->get_current_query()->from($from);
        return $this;
    }

    /**
     * Add a GROUP BY clause to the current query
     *
     * @example
     * ```php
     * $this->model->select('COUNT(*), user_id')->group('user_id')->get();
     * ```
     *
     * @param string $group The field to group results by
     * @return self Returns the current instance for method chaining
     */
    public function group(string $group): self 
    {
        $this->get_current_query()->group($group);
        return $this;
    }

    /**
     * Add a LIMIT clause to the current query
     *
     * @example
     * ```php
     * $this->model->limit(10, 10)->get();
     * ```
     *
     * @param int $start Number of records to skip or number of recods if $limit is -1
     * @param int $limit Number of records to retrieve
     * @return self Returns the current instance for method chaining
     */
    public function limit(int $start, int $limit = -1): self 
    {
        if ($limit == -1) {
            $limit = $start;
            $start = 0;
        }
        $this->get_current_query()->limit($start, $limit);
        return $this;
    }

    /**
     * Execute the current query and return results as objects
     *
     * @param string $query The SQL query to execute
     * @param array $params Parameters to pass to the query to prevent SQL injection
     *
     * @example
     * ```php
     * $this->model->get('SELECT * FROM posts WHERE status = ?', ['published']);
     * // or
     * $this->model->select('id, title')->where('status = ?', ['published'])->get();
     * ```
     *
     * @return array An array of objects (instances of the class specified in $object_class)
     */
    public function get($query = null, $params = []): array 
    {
        if ($query != null) {
            $results = $this->db->get_results($query, $params);
        } else {
            $query = $this->get_current_query();
            $results = $this->db->get_results(...$query->get());
            $this->reset_query();
        }
        
        if ($results) {
            return array_map(function($obj) {
                return new $this->object_class($obj);
            }, $results);
        }

        return $results ?: [];
    }

    /**
     * Execute the current query and return a single object
     * @param string $query The SQL query to execute
     * @param array $params Parameters to pass to the query to prevent SQL injection
     *
     * @example
     * ```php
     * $this->model->select('id, title')->where('status = ?', ['published'])->get_one();
     * // or
     * $this->model->get_one('SELECT id, title FROM posts WHERE status = ?', ['published']);
     * ```
     *
     * @return object|null The first record as an object or null if no records found
     */
    public function get_one($query = null, $params = []): ?object 
    {
        if ($query != null) {
            $result = $this->db->get_row($query, $params);
        } else {
            $query = $this->get_current_query();
            $result = $this->db->get_row(...$query->get());
            $this->reset_query();
        }
        
        if ($result) {
            return new $this->object_class($result);
        }

        return null;
    }

    /**
     * Filtra i dati in base alle proprietà dell'oggetto se specificato
     */
    public function get_filtered_columns($key = '', $value = ''): array{
        // se c'è l'object class allora uso la classe per filtrare
        if ($this->object_class && class_exists($this->object_class)) {
            $obj = new $this->object_class();
            return $obj->get_rules($key, $value);
        } else {
            return [];
        }
    }

    public function get_columns() {
        if ($this->object_class && class_exists($this->object_class)) {
            $obj = new $this->object_class();
            return $obj->get_rules();
        } else {
            return [];
        }
    }

    /**
     * Execute the current query and return raw results
     * 
     * @param string $query The SQL query to execute
     * @param array $params Parameters to pass to the query to prevent SQL injection
     *
     * @example
     * ```php
     * $this->model->select('id, title')->where('status = ?', ['published'])->execute();
     * // or
     * $this->model->execute('SELECT * FROM posts');
     * // RETURN array of associative raw results
     * ```
     *
     * @return array An array of associative raw results representing the query results
     */
    public function execute($query = null, $params = []): array {
        if ($query != null) {
            $results = $this->db->get_results($query, $params);
        } else {
            $query = $this->get_current_query();
            $results = $this->db->get_results(...$query->get());
            $this->reset_query();
        }

        return $results ?: [];
    }

    /**
     * Get all data without limits
     *
     * Executes the current query without limits to retrieve all data
     *
     * @example
     * ```php
     * $posts = $this->model->get_all();
     * foreach ($posts as $post) {
     *     echo $post->title;
     * }
     * ```
     *
     * @return array An array of objects (instances of the class specified in $object_class)
     */
    public function get_all() {
        $this->error = false;
        $this->last_error = '';
        $query = $this->get_current_query();
        $query->clean('limit');
        $results = $this->db->get_results( ...$query->get() );
        $this->reset_query();
        if ($results) {
            return array_map(function($obj) {
                return new $this->object_class($obj);
            }, $results);
        }
        return $results ?: [];
    }


    /**
     * Get the first result from the current query
     *
     * Executes the current query and returns a single object
     *
     * @param string $query The SQL query to execute
     * @param array $params Parameters to pass to the query to prevent SQL injection
     *
     * @example
     * ```php
     * $post = $this->model->first('SELECT * FROM posts WHERE status = ?', ['published']);
     * // or
     * $post = $this->model->select('id, title')->where('status = ?', ['published'])->first();
     * ```
     *
     * @return object|null The first record as an object or null if no records found
     */
    public function first($query = null, $params = []): ?object 
    {
       
        if ($query != null) {
            $result = $this->db->get_row($query, $params);
        }  else {
            $query = $this->get_current_query();
            $result = $this->db->get_row(...$query->get());
        }
        $this->reset_query();
        
        if ($result) {
            return new $this->object_class($result);
        }

        return null;
    }

    /**
     * Get the total count of records
     *
     * Executes the current query or the last executed query and returns the total number of records without limitations
     *
     * @example
     * ```php
     * $total_posts = $this->model->total();
     * echo "Total posts: " . $total_posts;
     * ```
     *
     * @return int The total number of records
     */
    public function total(): int {
        $query = $this->current_query ?? $this->last_query;
        if ($query === null) {
            $query = $this->new_query();
        }
        $query->clean('limit');
        $total = (int)$this->db->get_var(...$query->select('COUNT(*) as total')->get());
        return $total;
    }

    /**
     * Set query parameters from request
     *
     * Sets query parameters (limit, order, filter) from the request
     *
     * @example
     * ```php
     * $request = $this->get_request_params('table_posts');
     * $this->model->set_query_params($request);
     * ```
     *
     * @param array $request The request from the browser
     * @return void
     */
    public function set_query_params($request) {
        $this->limit($request['limit_start'] ?? 0, $request['limit'] ?? 10);
        if (($request['order_field'] ?? null) && ($request['order_dir'] ?? null)) {
            $this->order($request['order_field'], $request['order_dir']);
        }
        $this->add_query_from_filters($request['filters'] ?? '');
    }

    /**
     * Create or update the database table
     *
     * This method should be overridden in child classes because the prefix is not set during installation.
     * If using an object model, this is already handled automatically.
     *
     * @example
     * ```php
     * // Using Schema for table management
     * public function build_table(): bool {
     *     $this->db->prefix = Config::get('prefix');
     *     $schema = Get::schema($this->table);
     *     $schema->id()
     *            ->string('title')
     *            ->text('description')
     *            ->datetime('created_at')
     *            ->datetime('updated_at', true);
     *     
     *     if ($schema->exists()) {
     *         return $schema->modify();
     *     } else {
     *         return $schema->create();
     *     }
     * }
     * ```
     *
     * @return bool True if the table was created/updated successfully, false otherwise
     */
   
     public function build_table($force_update = true): bool {
        $this->last_error = '';
        if ($this->object_class && class_exists($this->object_class)) {    
            $schema = (new $this->object_class())->get_schema($this->table, $this->db);
          
            // verifico se già esiste la tabella
            if ($schema->exists()) {
               
                 if ($schema->modify($force_update)) {
                    if (method_exists($this, 'after_modify_table')) {
                        $this->after_modify_table();
                    }
                    return true;
                 } else {
                    $this->last_error = $schema->get_last_error();
                    return false;
                 }
            } else {
                if ($schema->create()) {
                    if (method_exists($this, 'after_create_table')) {
                        $this->after_create_table();
                    }
                    return true;
                } else {
                    $this->last_error = $schema->get_last_error();
                    return false;
                }
            }  
        } 
        return false;
        
    }

    protected function after_create_table() {
        // da sovrascrivere nelle classi figlie
    }
    protected function after_modify_table() {
        // da sovrascrivere nelle classi figlie
    }


    public function drop_table(): bool
    {
        $schema = Get::schema($this->table);
        return $schema->drop();
    }

    
    public function get_table() {
        return $this->table;
    }

    
    /**
     * Prepare data before saving
     *
     * This method can be overridden in child classes to modify data before saving
     *
     * @param array $data The data to prepare
     * @return array The prepared data
     */
    protected function prepare_data(array $data): array 
    {
         // da sovrascrivere nelle classi figlie
        return $data;
    }

    /**
     * Filter data based on object properties
     *
     * This method can be overridden in child classes to filter data
     *
     * @param array $data The data to filter
     * @return array The filtered data
     */
    protected function filter_data(array $data): array {
         // da sovrascrivere nelle classi figlie
        return $data;
    }

    /**
     * Get or create the current query
     *
     * Gets the current query or creates a new one if it doesn't exist
     *
     * @return Query The current query
     */
    protected function get_current_query(): Query 
    {
        if ($this->current_query === null) {
            $this->current_query = $this->new_query();
        }
        $this->last_query = null;
        return $this->current_query;
    }

    /**
     * Filter search results
     *
     * Adds search conditions to the current query
     * This was moved from modellist because all query-related functionality should be in the model
     *
     * @param string $search The search term
     * @return void
     */
    public function filter_search($search) {
        $query = $this->get_current_query();
        $list_structure = $this->get_table_structure();
        foreach ($list_structure as $field => $_) {
            $query->where('`'.$field.'` LIKE ? ', ['%'.$search.'%'], 'OR');
        }
    }

    /**
     * Validate data
     *
     * Performs validation on the data and returns true or false based on the validation result
     *
     * @param array $data Data to validate
     * @return bool True if validation passes, false otherwise
     */
    public function validate(array $data): bool
    {   
        $obj = new $this->object_class($data);

        if(is_object($obj) && !method_exists($obj, 'get_rules')) {
            return true;
        }
        $rules = $obj->get_rules();
        foreach ($rules as $key => $rule) {
            $name = _raz($key);
            $type = $rule['form-type'] ?? $rule['type'];
            $value = $data[$key] ?? null;
            // custom validation
            if (isset($rules[$name]['_validate'])) {
                $fn = $rules[$name]['_validate'];
                if (is_callable($fn)) {
                    $fn($value, $data);
                    continue;
                }
            } else if (method_exists($obj, 'validate_' . $key)) {
                $obj->{'validate_' . $key}($value, $data);
                continue;
            }
            if ((isset($rule['form-params']['required']) && $rule['form-params']['required']) && ($value === null || $value === '' || $value === [])) {
                MessagesHandler::add_error('the field <b>' . $rule['label'] . '</b> is required', $key);
                continue;
            }
            if ($type == 'email' && $value != null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                 MessagesHandler::add_error('Invalid Email', $key);
            } 
             if ($type == 'url' && $value != null &&  !filter_var($value, FILTER_VALIDATE_URL)) {
                 MessagesHandler::add_error('Invalid Url', $key);
            }
            if ($rule['type'] == 'int' && $value != null && $value != 0 && !filter_var($value, FILTER_VALIDATE_INT)) {
                 MessagesHandler::add_error('The field <b>' . $rule['label'] . '</b> is invalid. Must be an integer', $key);
            }
            if ($rule['type'] == 'float' && $value != null && $value != 0 && !filter_var($value, FILTER_VALIDATE_FLOAT)) {
                 MessagesHandler::add_error('Invalid Float', $key);
            }
            if ($rule['type'] == 'datetime' && $value != null && (is_scalar($value) && !strtotime($value))) {
                 MessagesHandler::add_error('Invalid Date', $key);
            }
            if ($rule['type'] == 'date' && $value != null && (is_scalar($value) && !strtotime($value))) {
                 MessagesHandler::add_error('Invalid Date', $key);
            }
            if ($rule['type'] == 'enum' && $value != null && !in_array($value, $rule['options'])) {
                 MessagesHandler::add_error('The field <b>' . $rule['label'] . '</b> is invalid', $key);
            }
            if ($rule['type'] == 'list' && $value != null && !array_key_exists($value, $rule['options'])) {
                 MessagesHandler::add_error('Invalid List', $key);
            }
            if (in_array($rule['type'], ['string','text']) && $value != null && (strlen($value) > $rule['length'] && $rule['length'] > 0)) {
                MessagesHandler::add_error('Field <b>' . $rule['label'] . '</b> is too long. Max length is ' . $rule['length'], $key);
            }

            if ($rule['type'] == 'bool' && $value != null && $value != 0 && !filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                 MessagesHandler::add_error('Invalid Boolean', $key);
            }
          
        }
        return !MessagesHandler::has_errors();
    }

    /**
     * Get the table structure
     *
     * Returns the structure of the columns in the table
     *
     * @return array The table structure
     */
    protected function get_table_structure() {
        $table_structure = [];
        $ris = $this->db->get_results("SHOW COLUMNS FROM " . $this->db->qn($this->table));
        if (!is_countable($ris)) {
            return $table_structure;
        }
        foreach ($ris as $row) {
            $table_structure[$row->Field] = $row;
        }
        return $table_structure;
    }

    /**
     * Add a filter function
     *
     * Adds a filter function to be used when filtering query results
     *
     * @param string $filter_type The type of filter
     * @param callable $fn The filter function
     * @return void
     */
    public function add_filter($filter_type, $fn) {
        $this->fn_filter[$filter_type] = $fn;
    }

    /**
     * Add filters to the query
     *
     * Adds filters to the query by calling filter_* functions
     * This is called by set_query_params
     *
     * @param string $request_filters The filters from the request
     * @return void
     */
    protected function add_query_from_filters($request_filters) {
        if ($request_filters != '') {
            $tmp_filters = json_decode($request_filters);
            // se non è un json valido
            if (JSON_ERROR_NONE === json_last_error()) {
                foreach ($tmp_filters as $filter) {
                    $filter_type = explode(':', $filter);
                    $filter = implode(':', array_slice($filter_type, 1));
                    $filter_type = $filter_type[0];
                    $standard_fn_filter =  'filter_' ._raz($filter_type);
                    if (isset($this->fn_filter[$filter_type])) {
                        call_user_func($this->fn_filter[$filter_type], $filter);
                    } else if (method_exists($this, $standard_fn_filter)) {
                        call_user_func([$this, $standard_fn_filter], $filter);
                    }
                }
            }
        }
    }


    protected function iniziale_class($class) {
        $name_space = $this->get_child_name_space();
        $class_name = $name_space."\\".$class;
        if (class_exists($class_name)) {
            return $class_name;
        } elseif (class_exists($class)) {
            return $class;
        } else {
            die('Class not found: '.$class);
        }
    }

    protected  function get_child_name_space(): string {
        return (new \ReflectionClass(get_called_class()))->getNamespaceName();
    }

}