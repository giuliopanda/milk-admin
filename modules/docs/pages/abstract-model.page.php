<?php
namespace Modules\docs;
/**
 * @title Abstract Model   
 * @category Abstracts Class
 * @order 50
 * @tags AbstractModel, model, database, query, SQL, MySQL, get_by_id, get_by_id_or_empty, get_empty, get_by_id_for_edit, save, delete, where, order, limit, select, from, group, get, execute, get_all, first, total, build_table, drop_table, validate, clear_cache, get_last_error, has_error, set_query_params, get_filtered_columns, get_columns, add_filter, object_class, primary_key, table, CRUD, query-builder, fluent-interface, pagination, sorting, filtering, validation, schema, to_mysql_array, filter_data_by_rules, get_last_insert_id, bind_params, SQL-injection
 */
use MilkCore\Route;
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract class model</h1>

    <p>The <code>AbstractModel</code> abstract class is a base class for managing module data. The class handles a series of methods to execute queries on a MySQL table.</p>

    <pre class="language-php"><code>namespace Modules\BaseModule;
    use MilkCore\AbstractModel;
    !defined('MILK_DIR') && die(); // Avoid direct access
    class BaseModuleModel extends AbstractModel
    {
        public string $table = '#__base_module';
        public string $primary_key = 'id';
        public $object_class = 'BaseModuleObject';
    }</code></pre>

    <p>To access the model methods from the controller or router, you have the <code>$this->model</code> variable available to access the model methods.</p>
    <pre class="language-php"><code>public function action_index() {
        $this->model->get_all();
    }

    public function action_edit() {
        $this->model->get_by_id($_REQUEST['id']);
    }

    public function list($request) {
        $trows =  $this->model->limit($request['limit_start'], $request['limit'])
                            ->order($request['order_field'], $request['order_dir'])
                            ->get();
        // total takes the just executed query or the current query being prepared.
        $total = $this->model->total();
    }
    </code></pre>



    <h3 class="mt-3">Adding the object</h3>
    <p>The model returns by default a stdClass object or an array of stdClass objects. To have a custom object, you can use the <code>$object_class</code> property to specify the class to use.</p>
    <p>The class must extend <code>AbstractObject</code> and must have an <code>init_rules</code> method to define validation rules.</p>
    <pre class="language-php"><code>namespace Modules\BaseModule;
    use MilkCore\AbstractObject;
    !defined('MILK_DIR') && die(); // Avoid direct access
    class BaseModuleObject extends AbstractObject
    {
        public function init_rules() {
            $this->rule('id', [
                'type' => 'int', 'primary' => true, 'list' => false
            ]);
            $this->rule('title', [
                'type' => 'string', 'length' => 100, 'label' => 'Title'
            ]);;
        }
    }
    </code></pre>
    <p class="alert alert-warning">WARNING: isset and get_property do not work with custom Object classes.</p>
    <a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/abstracts/abstract-object.page') ?>">See the documentation on objects</a>

    <p class="alert alert-info">Primary must be only one field. The only function that accepts multiple primaries is schema.
        This is because while it's true that it must be possible to have multiple primary columns when importing data 
        (so schema must allow creating tables with multiple primary ids) the model, as well as the template for tables etc.
        are used for data editing and editing happens only for internal tables and not for imported data
        which is used for statistics. 
    </p>

    <h3 class="mt-3">Basic installation</h3>
    <p class="alert alert-info">If you use the object model, everything is already handled automatically</p>
    <pre class="language-php"><code>
    // Basic version I write the query. In this case however table update is not handled in case of changes
    public function build_table(): bool {
        // The method is called both when installing and when updating the module
        $table = 'CREATE TABLE IF NOT EXISTS `'.$this->table.'` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `description` text NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        // During installation the prefix is empty
        $this->db->prefix = Config::get('prefix');
        $this->db->query($table);
        return true;
    }

    // Advanced version with Schema for table management and updates in case of table changes when updating the module the structure is changed automatically
    public function build_table(): bool {
    $this->db->prefix = Config::get('prefix');
        $schema = Get::schema($this->table);
        $schema->id()
                ->string('title')
                ->text('description')
                ->datetime('created_at')
                ->datetime('updated_at', true)
                ->string('category',50, true)
                ->string('status', 10)
                ->index('category', ['category']);

        if ($schema->exists()) {
            return $schema->modify();
        } else {
            if
            ($schema->create()) {
                $this->fake_date();
                return true;
            } else {
                return false;
            }

        }
    }

    // If the model uses an object model it's possible to extract the schema directly from the object. In this case the function is already written in the abstract. If you still need to interact after creation or table you can write the two functions after_create_table and after_modify_table

    </code></pre>



    <h1>AbstractModel Abstract Class Documentation</h1>
    <p>The <code>AbstractModel</code> class is a base class for data management in Ito modules. It provides methods to interact with the database, execute queries, validate and save data. This document describes in detail all public methods and their specifications.</p>

        <h2 class="mt-4">Main Properties</h2>
        <ul>
        <li><code>$table</code>: (string) The table name in the database (with prefix #__).</li>
        <li><code>$primary_key</code>: (string) The primary key name of the table.</li>
        <li><code>$object_class</code>: (string) The object class associated with the model.</li>
        <li><code>$db</code>: (object) The database connection instance.</li>
        <li><code>$last_error</code>: (string) The last error that occurred.</li>
        <li><code>$error</code>: (bool) A flag to indicate if an error occurred.</li>
            <li><code>$per_page</code>: (int) The number of records per page for pagination.</li>
        </ul>

    <h2 class="mt-4">Public Methods</h2>

    <h3 class="mt-3"><code>get_by_id($id, $use_cache = true)</code></h3>
    <p>Retrieves a record by primary key.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param mixed $id Primary key value
    * @param bool $use_cache Whether to use cache for data
    * @return object|null Returns the record object or null if not found
    */
    public function get_by_id($id, bool $use_cache = true): ?object;

    // Example
    $post = $this->model->get_by_id(123);
    if ($post) {
    echo $post->title;
    }
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$id</code>: (mixed) The primary key value of the record to retrieve.</li>
                    <li><code>$use_cache</code>: (bool, optional) Whether to use cache to retrieve the record (default: <code>true</code>).</li>
                </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>object|null</code>: Returns the record object, if found, otherwise <code>null</code>.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>get_by_id_or_empty($id)</code></h3>
    <p>Returns a record by primary key, otherwise returns an empty object</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param mixed $id Primary key value
    * @return object Returns the record object or an empty object.
    */
    public function get_by_id_or_empty($id): object;

    // Example
    $post = $this->model->get_by_id_or_empty(123);
    echo $post->title;
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$id</code>: (mixed) The primary key value of the record to retrieve.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>object</code>: Returns the record object, if found, otherwise an empty object.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>get_empty(array $data = [])</code></h3>
    <p>Returns an empty model object for creating new records</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param array $data Data to use to initialize the object
    * @return object Returns an empty model object
    */
    public function get_empty(array $data = []): object;

    // Example
    $new_post = $this->model->get_empty();
    $new_post->title = "New Title";
    </code></pre>
        <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$data</code>: (array, optional) Data to use to initialize the object.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>object</code>: Returns an empty model object, possibly initialized with the provided data.</li>
            </ul>
        </li>
    </ul>


    <h3 class="mt-3"><code>get_by_id_for_edit($id, array $merge_data = [])</code></h3>
    <p>Retrieves a record for editing, applying edit rules.</p>
    <br>
    <h4 class="mt-3">Example 1: Edit a record</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    protected function action_edit_project() {
        $id = _absint($_REQUEST['id'] ?? 0);
        // Retrieve the record and apply edit rules
        $data = $this->model->get_by_id_for_edit($id, Route::get_session_data());
        
        if ($data === null) {
            Route::redirect_error('?page='.$this->page."&action=list-projects", 'Invalid id');
        }
        
        // Display the edit form
        Get::theme_page('default', 'edit-project.page.php', [
            'id' => $id, 
            'data' => $data,
            'page' => $this->page,
            'url_success' => '?page='.$this->page."&action=list-projects",
            'action_save' => 'save_projects'
        ]);
    }
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$id</code>: (mixed) The primary key value of the record to retrieve.</li>
                <li><code>$merge_data</code>: (array, optional) Additional data to merge with the retrieved record.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>object|null</code>: Returns the record object, if found, otherwise <code>null</code>.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>save(array $data, $id = null)</code></h3>
    <p>Saves or updates a record in the database.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param array $data Data to save
    * @param mixed $id Primary key for update, null for insert
    * @return bool
    */
    public function save(array $data, $id = null): bool|int;

    // Example
    $data_to_save = ['title' => 'Title update', 'content' => 'New content'];
    $result = $this->model->save($data_to_save, 123);
    if($result) {
    echo "Save success";
    } else {
    echo "Save Error: ".$this->model->last_error;
    }
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$data</code>: (array) An array of data to save.</li>
                <li><code>$id</code>: (mixed, optional) The primary key of the record to update. If <code>null</code>, a new record is created.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>bool|int</code>: Returns <code>false</code> in case of error, otherwise the ID of the inserted record (if <code>$id</code> is null) or the ID of the updated record.</li>
            </ul>
            </li>
    </ul>

    <h3 class="mt-3"><code>delete($id)</code></h3>
    <p>Deletes a record from the database.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    protected function table_action_delete_project($id, $request) {
        if ($this->model->delete($id)) {
            return true;
        } else {
            MessagesHandler::add_error($this->model->get_last_error());
            return false;
        }
    }
    </code></pre>

    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$id</code>: (mixed) The primary key of the record to delete.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>bool</code>: Returns <code>true</code> if deletion was successful, otherwise <code>false</code>.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>clear_cache()</code></h3>
    <p>Clears the results cache.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return void
    */
    public function clear_cache(): void;

    // Example
    $this->model->clear_cache();
    </code></pre>
        <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><em>None</em></li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>void</code>: This method does not return any value.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>get_last_error()</code></h3>
    <p>Returns the last error message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return string
    */
    public function get_last_error(): string;

    // Example
    echo $this->model->get_last_error();
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><em>None</em></li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>string</code>: Returns the string with the last error message.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>has_error()</code></h3>
        <p>Checks if an error occurred during the last database operation.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return bool
    */
    public function has_error(): bool;

    // Example
    if ($this->model->has_error()) {
    echo "An error occurred: ".$this->model->get_last_error();
    }
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><em>None</em></li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>bool</code>: Returns <code>true</code> if an error occurred, <code>false</code> otherwise.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3">Query building methods</h3>
    <p>These methods allow you to create and manage the query and are used for data listing in the list.page</p>

    <h4 class="mt-3"><code>where(string $condition, array $params = [])</code></h4>
        <p>Adds a WHERE clause to the current query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $condition The condition to add
    * @param array $params The parameters to pass for the query with bind_params
    * @return $this
    */
    public function where(string $condition, array $params = []): self;

    // Example
    $this->model->where('title LIKE ?', ['%test%'])->get();
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                <ul>
                    <li><code>$condition</code>: (string) The SQL condition to add to the WHERE clause.</li>
                    <li><code>$params</code>: (array, optional) An array of parameters to pass to the query to prevent SQL injection.</li>
                </ul>
            </li>
            <li><strong>Return value:</strong>
                <ul>
                    <li><code>$this</code>: Returns the current instance to allow method chaining.</li>
                </ul>
                </li>
        </ul>

        <h4 class="mt-3"><code>order(string|array $field = '', string $dir = 'asc')</code></h4>
        <p>Adds an ORDER BY clause to the current query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string|array $field Field or array of fields to order by.
    * @param string $dir Sort direction ('asc' or 'desc')
    * @return $this
    */
    public function order(string|array $field = '', string $dir = 'asc'): self;

    // Example
    $this->model->order('title', 'desc')->get();
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                <ul>
                    <li><code>$field</code>: (string|array) The field or fields to sort the results by.</li>
                    <li><code>$dir</code>: (string, optional) The sort direction ('asc' for ascending or 'desc' for descending), default 'asc'.</li>
                </ul>
            </li>
            <li><strong>Return value:</strong>
                <ul>
                    <li><code>$this</code>: Returns the current instance to allow method chaining.</li>
                </ul>
            </li>
        </ul>


    <h4 class="mt-3"><code>select(array|string $fields)</code></h4>
        <p>Adds a SELECT clause to the current query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param array|string $fields  The fields to select.
    * @return $this
    */
    public function select(array|string $fields): self;

    // Example
    $this->model->select('id, title')->get();
    $this->model->select(['id', 'title'])->get();

    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                <ul>
                    <li><code>$fields</code>: (array|string) An array or string containing the fields to select.</li>
                </ul>
            </li>
            <li><strong>Return value:</strong>
                    <ul>
                        <li><code>$this</code>: Returns the current instance to allow method chaining.</li>
                </ul>
            </li>
        </ul>
    <h4 class="mt-3"><code>from(string $from)</code></h4>
    <p>Adds a FROM clause to the current query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $from The table to query
    * @return $this
    */
    public function from(string $from): self;

    // Example
    $this->model->from('posts')->get();
    $this->model->from('posts LEFT JOIN users ON posts.user_id = user.id')->get();
    </code></pre>
    <ul>
            <li><strong>Input parameters:</strong>
                    <ul>
                    <li><code>$from</code>: (string) The table or join to query.</li>
                    </ul>
                </li>
                <li><strong>Return value:</strong>
                    <ul>
                        <li><code>$this</code>: Returns the current instance to allow method chaining.</li>
                </ul>
            </li>
        </ul>

        <h4 class="mt-3"><code>group(string $group)</code></h4>
    <p>Adds a GROUP BY clause to the current query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $group  The fields to group the results by
    * @return $this
    */
    public function group(string $group): self;

    // Example
    $this->model->select('COUNT(*), user_id')->group('user_id')->get();
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                <ul>
                    <li><code>$group</code>: (string) The field to group the results by.</li>
                </ul>
            </li>
                <li><strong>Return value:</strong>
                    <ul>
                        <li><code>$this</code>: Returns the current instance to allow method chaining.</li>
                </ul>
            </li>
        </ul>
        <h4 class="mt-3"><code>limit(int $start, int $limit)</code></h4>
    <p>Adds a LIMIT clause to the current query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param int $start Number of records to skip
    * @param int $limit Number of records to retrieve
    * @return $this
    */
    public function limit(int $start, int $limit): self;

    // Example
    $this->model->limit(10, 10)->get();
    </code></pre>
    <ul>
            <li><strong>Input parameters:</strong>
                    <ul>
                    <li><code>$start</code>: (int) The index of the first record to retrieve.</li>
                    <li><code>$limit</code>: (int) The number of records to retrieve.</li>
                    </ul>
            </li>
                <li><strong>Return value:</strong>
                    <ul>
                    <li><code>$this</code>: Returns the current instance to allow method chaining.</li>
                    </ul>
            </li>
        </ul>

        <h3 class="mt-3">Table display methods</h3>
        <p>These methods allow you to retrieve data, totals and execute queries:</p>
        <h4 class="mt-3"><code>get($query = null, $params = [])</code></h4>
    <p>Executes the current query and returns an array of objects.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $query The SQL query to execute
    * @param array $params Parameters to pass to the query to prevent SQL injection
    * @return array Returns an array of objects from the database
    */
    public function get(): array;

    // Example
    $posts = $this->model->get();
    foreach ($posts as $post) {
    echo $post->title;
    }
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                <ul>
                    <li><code>$query</code>: (string, optional) The SQL query to execute. If not specified, the current query is executed.</li>
                    <li><code>$params</code>: (array, optional) Parameters for the query to prevent SQL injection.</li>
                    </ul>
            </li>
                <li><strong>Return value:</strong>
                    <ul>
                        <li><code>array</code>: An array of objects (instances of the class specified in <code>$object_class</code>) representing records from the database.</li>
                </ul>
            </li>
        </ul>

        <h4 class="mt-3"><code>execute($query = null, $params = [])</code></h4>
            <p>Executes the current query and returns raw results.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $query The SQL query to execute
    * @param array $params Parameters to pass to the query to prevent SQL injection
    * @return array  returns an array of records (associative array)
    */
    public function execute($query = null, $params = []): array;

    // Example
    $posts = $this->model->execute();
    foreach ($posts as $post) {
    echo $post['title'];
    }
    </code></pre>
            <ul>
                <li><strong>Input parameters:</strong>
                    <ul>
                            <li><code>$query</code>: (string, optional) The SQL query to execute. If not specified, the current query is executed.</li>
                            <li><code>$params</code>: (array, optional) Parameters for the query to prevent SQL injection.</li>
                    </ul>
                    </li>
                    <li><strong>Return value:</strong>
                        <ul>
                        <li><code>array</code>: An array of associative arrays representing the query results.</li>
                    </ul>
                </li>
            </ul>

                <h4 class="mt-3"><code>get_all()</code></h4>
                <p>Executes the current query without limits, to retrieve all data.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return array Returns all results
    */
    public function get_all(): array;

    // Example
    $posts = $this->model->get_all();
    foreach ($posts as $post) {
    echo $post->title;
    }
    </code></pre>
            <ul>
                <li><strong>Input parameters:</strong>
                    <ul>
                            <li><em>None</em></li>
                        </ul>
                    </li>
                    <li><strong>Return value:</strong>
                        <ul>
                            <li><code>array</code>: An array of objects (instances of the class specified in <code>$object_class</code>) representing records from the database.</li>
                    </ul>
                </li>
        </ul>
        <h4 class="mt-3"><code>first($query = null, $params = [])</code></h4>
        <p>Executes the current query and returns a single object. The limit is implicitly set to 1</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $query The SQL query to execute
    * @param array $params Parameters to pass to the query to prevent SQL injection
    * @return object|null Returns the first record as object or null
    */
    public function first($query = null, $params = []): ?object;

    // Example
    $post = $this->model->first();
    if ($post) {
    echo $post->title;
    }
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                <ul>
                    <li><em>None</em></li>
                </ul>
                </li>
                <li><strong>Return value:</strong>
                    <ul>
                    <li><code>object|null</code>: The object corresponding to the first record or null if no data is present.</li>
                </ul>
        </li>
        </ul>
    <h4 class="mt-3"><code>total()</code></h4>
        <p>Executes the current query or the last query and returns the total number of records without limitations.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return int Returns the total number of records.
    */
    public function total(): int;

    // Example
    $total_posts = $this->model->total();
    echo "Total posts: " . $total_posts;
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                <ul>
                    <li><em>None</em></li>
                    </ul>
            </li>
                <li><strong>Return value:</strong>
                    <ul>
                        <li><code>int</code>: The total number of records in the table, without limitations.</li>
                    </ul>
            </li>
        </ul>

        <h3 class="mt-3"><code>set_query_params(array $request)</code></h3>
    <p>Sets the parameters for the query from the request (limit, order, filter) </p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param array $request The request from the browser
    * @return void
    */
    public function set_query_params(array $request): void;
    // Example
    $request = $this->get_request_params('table_posts');
    $this->model->set_query_params($request);
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                    <ul>
                    <li><code>$request</code>: (array) An array of parameters (for example taken from the query string) to configure the query.</li>
                    </ul>
            </li>
            <li><strong>Return value:</strong>
                    <ul>
                        <li><code>void</code>: This method does not return any value.</li>
                </ul>
            </li>
        </ul>
    <h3 class="mt-3"><code>build_table()</code></h3>
    <p>Creates or modifies the table if it doesn't exist or if there have been changes to the object. It's executed during module installation or update. <br> The method then calls after_modify_table and after_create_table.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return bool Returns true if the operation was successful
    */
    public function build_table(): bool;

    // Example
    $this->model->build_table();
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                    <ul>
                    <li><em>None</em></li>
                    </ul>
            </li>
                <li><strong>Return value:</strong>
                    <ul>
                        <li><code>bool</code>: Returns <code>true</code> if the operation was successful, otherwise <code>false</code>.</li>
                </ul>
            </li>
        </ul>
    <h3 class="mt-3"><code>drop_table()</code></h3>
    <p>Deletes the table if it exists. Called when running module uninstall from shell</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return bool Returns true if the operation was successful
    */
    public function drop_table(): bool;

    // Example
    $this->model->drop_table();
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                    <ul>
                    <li><em>None</em></li>
                    </ul>
                </li>
                <li><strong>Return value:</strong>
                    <ul>
                        <li><code>bool</code>: Returns <code>true</code> if the operation was successful, otherwise <code>false</code>.</li>
                    </ul>
                </li>
        </ul>
    <h3 class="mt-3"><code>validate(array $data)</code></h3>
    <p>Performs data validation before saving. $data is the array of data to pass to the query, already converted through to_mysql_array()</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param array $data The array of data to validate
    * @return bool
    */
    public function validate(array $data): bool;

    // Example
    if ($this->model->validate($data)) {
    echo "Valid data"
    } else {
    echo "Invalid data";
    }
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                    <ul>
                    <li><code>$data</code>: (array) An associative array containing the data to validate.</li>
                </ul>
            </li>
            <li><strong>Return value:</strong>
                    <ul>
                    <li><code>bool</code>: Returns true if the data is valid, false otherwise.</li>
                    </ul>
            </li>
        </ul>


    <h3 class="mt-3"><code>get_filtered_columns($key = '', $value = '')</code></h3>
    <p>Returns filtered columns based on object rules.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $key Optional key to filter
    * @param string $value Optional value to filter
    * @return array Array of filtered columns
    */
    public function get_filtered_columns($key = '', $value = ''): array;

    // Example
    $columns = $this->model->get_filtered_columns();
    </code></pre>

    <h3 class="mt-3"><code>get_columns()</code></h3>
    <p>Returns all columns defined in the object rules.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return array Array of columns
    */
    public function get_columns(): array;

    // Example
    $all_columns = $this->model->get_columns();
    </code></pre>

    <h3 class="mt-3"><code>add_filter($filter_type, $fn)</code></h3>
    <p>Adds a custom filter function.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $filter_type Filter type
    * @param callable $fn Filter function
    */
    public function add_filter($filter_type, $fn);

    // Example
    $this->model->add_filter('status', function($query, $value) {
        $query->where('status = ?', [$value]);
    });
    </code></pre>

    <h3 class="mt-3">Validation</h3>
    <p>The validate method has been updated to support:</p>
    <ul>
        <li>Custom validations through _validate in rules</li>
        <li>validate_{field} methods in the object</li>
        <li>Automatic type validation (int, float, email, url, datetime, enum, list)</li>
        <li>Length checking for strings and texts</li>
        <li>Required field validation</li>
    </ul>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    // Example of rules with custom validation
    $this->rule('email', [
        'type' => 'string',
        '_validate' => function($value, $data) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                MessagesHandler::add_error('Invalid email format');
            }
        }
    ]);
    </code></pre>

    <h4 class="mt-3">Example 2: Saving with validation</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    protected function action_save_projects() {
        $id = _absint($_REQUEST[$this->model->get_primary_key()] ?? 0);
        
        // Create an empty object with request data
        $obj = $this->model->get_empty($_REQUEST);
        
        // Convert data to database format
        $array_to_save = $obj->to_mysql_array();
        
        // Filter data to include only expected fields
        $array_to_save = $obj->filter_data_by_rules('edit', true, $array_to_save);

        // Validate and save
        if ($this->model->validate($array_to_save)) {
            if ($this->model->save($array_to_save, $id)) {
                // If it's a new record retrieve the id
                if ($id == 0) {
                    $id = $this->model->get_last_insert_id();
                    Route::redirect_success('?page='.$this->page."&action=related-tables&id=".$id, 
                        _r('Save success'));
                }
                Route::redirect_success($_REQUEST['url_success'], _r('Save success'));
            } else {
                $error = "An error occurred while saving the data. ".$this->model->get_last_error();
                $obj2 = $this->model->get_by_id_or_empty($id, $_REQUEST);
                Route::redirect_error($_REQUEST['url_error'], $error, to_mysql_array($obj2));
            }
        } 
        Route::redirect_handler_errors($_REQUEST['url_error'], $array_to_save);
    }
    </code></pre>


    <br><br><br>
    <h4 class="mt-3">Example: List management with parameters</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    protected function action_list_projects() {
        $table_id = 'table_projects';
        
        // Retrieve request parameters for the table
        $request = $this->get_request_params($table_id);
        
        // Register the delete action
        $this->call_table_action($table_id, 'delete-project', 'table_action_delete_project');
        
        // Set query parameters (limit, order, filters)
        $this->model->set_query_params($request);
        
        // Retrieve data for modellist
        $modellist_data = $this->get_modellist_data($table_id, $fn_filter_applier);
        
        // Configuration customization
        $modellist_data['page_info']['limit'] = 1000;
        $modellist_data['page_info']['pagination'] = false;
        
        // Table output
        $this->output_table_response('list-projects.page.php', $modellist_data);
    }
    </code></pre>
</div>