<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Abstract Model   
 * @guide developer
 * @order 50
 * @tags AbstractModel, model, database, query, SQL, MySQL, get_by_id, get_by_id_or_empty, get_empty, get_by_id_for_edit, save, delete, where, order, limit, select, from, group, get, execute, get_all, first, total, build_table, drop_table, validate, clear_cache, get_last_error, has_error, set_query_params, get_filtered_columns, get_columns, add_filter, object_class, primary_key, table, CRUD, query-builder, fluent-interface, pagination, sorting, filtering, validation, schema, to_mysql_array, filter_data_by_rules, get_last_insert_id, bind_params, SQL-injection
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract Model Class</h1>
    <p class="text-muted">Revision: 2025/10/13</p>
    <p>The <code>AbstractModel</code> abstract class is a base class for managing module data. It provides a fluent interface for building queries and performing CRUD operations on MySQL tables.</p>

    <h2 class="mt-4">Defining a Model</h2>

    <p>To create a model, extend <code>AbstractModel</code> and implement the <code>configure()</code> method:</p>

    <pre class="language-php"><code>namespace Modules\Products;
use App\Abstracts\AbstractModel;

class ProductsModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('#__products')
            ->id()
            ->string('name', 100)->required()
            ->decimal('price', 10, 2)->default(0)
            ->text('description')->nullable()
            ->boolean('in_stock')->default(true)
            ->datetime('created_at')->nullable();
    }
}</code></pre>

    <div class="alert alert-success mt-4">
        <strong>📚 Related Documentation:</strong>
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-model">Getting Started with Models</a> - Quick tutorial for beginners</li>
            <li><a href="?page=docs&action=Framework/Core/schema">Schema</a> - Advanced table schema management</li>
        </ul>
    </div>
    
    <p class="alert alert-info">Primary must be only one field. The only function that accepts multiple primaries is schema.
        This is because while it's true that it must be possible to have multiple primary columns when importing data 
        (so schema must allow creating tables with multiple primary ids) the model, as well as the template for tables etc.
        are used for data editing and editing happens only for internal tables and not for imported data
        which is used for statistics. 
    </p>

    <h3 class="mt-3">Basic installation</h3>
    <p>Puoi usare la shell per installare i moduli tramite
        <pre class="language-js"><code>php milkadmin/cli.php module_name:install</code></pre>
    </p>

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

    <h3 class="mt-3"><code>getById($id, $use_cache = true)</code></h3>
    <p>Retrieves a single record by primary key. Returns a Model instance with one record or null if not found.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param mixed $id Primary key value
 * @param bool $use_cache Whether to use cache for data
 * @return static|null Returns Model instance with the record, or null if not found
 */
public function getById($id, bool $use_cache = true): ?static;

// Example
$product = $this->model->getById(123);
if ($product && $product->count() > 0) {
    echo $product->name;  // Access properties directly
    echo "Price: €" . $product->price;
}
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$id</code>: (mixed) The primary key value of the record to retrieve.</li>
                <li><code>$use_cache</code>: (bool, optional) Whether to use cache (default: <code>true</code>).</li>
                </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>static|null</code>: Returns Model instance containing one record, or <code>null</code> if not found.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>getByIdAndUpdate($id, array $merge_data = [], $mysql_array = false)</code></h3>
    <p>Returns a record by primary key, otherwise returns an empty object</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param mixed $id Primary key value
    * @param array $merge_data Optional data to merge with the record
    * @param bool $mysql_array Whether to return the record as a MySQL array (default: <code>false</code>)
    * @return object Returns the record object or an empty object.
    */
    public function getByIdAndUpdate($id, array $merge_data = [], $mysql_array = false): object;

    // Example
    $post = $this->model->getByIdAndUpdate(123);
    echo $post->title;
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$id</code>: (mixed) The primary key value of the record to retrieve.</li>
                <li><code>$merge_data</code>: (array, optional) Data to merge with the record.</li>
                <li><code>$mysql_array</code>: (bool, optional) Whether to return the record as a MySQL array (default: <code>false</code>).</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>object</code>: Returns the record object, if found, otherwise an empty object.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>getEmpty(array $data = [], $mysql_array = false)</code></h3>
    <p>Returns an empty model object for creating new records</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param array $data Data to use to initialize the object
    * @param bool $mysql_array Whether to return the record as a MySQL array (default: <code>false</code>)
    * @return object Returns an empty model object
    */
    public function getEmpty(array $data = [], $mysql_array = false): object;

    // Example
    $new_post = $this->model->getEmpty();
    $new_post->title = "New Title";
    </code></pre>
        <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$data</code>: (array, optional) Data to use to initialize the object.</li>
                <li><code>$mysql_array</code>: (bool, optional) Whether to return the record as a MySQL array (default: <code>false</code>).</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>object</code>: Returns an empty model object, possibly initialized with the provided data.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>getByIdForEdit($id, array $merge_data = [])</code></h3>
    <p>Retrieves a record for editing, applying edit rules.</p>
    <br>
    <h4 class="mt-3">Example 1: Edit a record</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    protected function actionEditProject() {
        $id = _absint($_REQUEST['id'] ?? 0);
        // Retrieve the record and apply edit rules
        $data = $this->model->getByIdForEdit($id, Route::getSessionData());
        
        if ($data === null) {
            Route::redirectError('?page='.$this->page."&action=list-projects", 'Invalid id');
        }
        
        // Display the edit form
        Response::themePage('default', 'edit-project.page.php', [
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
    $result = $this->model->store($data_to_save, 123);
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
    protected function tableActionDeleteProject($id, $request) {
        if ($this->model->delete($id)) {
            return true;
        } else {
            MessagesHandler::addError($this->model->getLastError());
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

    <h3 class="mt-3"><code>clearCache()</code></h3>
    <p>Clears the results cache.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return void
    */
    public function clearCache(): void;

    // Example
    $this->model->clearCache();
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

    <h3 class="mt-3"><code>getLastError()</code></h3>
    <p>Returns the last error message.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return string
    */
    public function getLastError(): string;

    // Example
    echo $this->model->getLastError();
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

    <h3 class="mt-3"><code>hasError()</code></h3>
        <p>Checks if an error occurred during the last database operation.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return bool
    */
    public function hasError(): bool;

    // Example
    if ($this->model->hasError()) {
    echo "An error occurred: ".$this->model->getLastError();
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

                <h4 class="mt-3"><code>getAll()</code></h4>
                <p>Executes the current query without limits, to retrieve all data.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return array Returns all results
    */
    public function getAll(): array;

    // Example
    $posts = $this->model->getAll();
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

        <h3 class="mt-3"><code>setQueryParams(array $request)</code></h3>
    <p>Sets the parameters for the query from the request (limit, order, filter) </p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param array $request The request from the browser
    * @return void
    */
    public function setQueryParams(array $request): void;
    // Example
    $request = $this->getRequestParams('table_posts');
    $this->model->setQueryParams($request);
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
    <h3 class="mt-3"><code>buildTable()</code></h3>
    <p>Creates or modifies the table if it doesn't exist or if there have been changes to the object. It's executed during module installation or update. <br> The method then calls after_modify_table and after_create_table.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return bool Returns true if the operation was successful
    */
    public function buildTable(): bool;

    // Example
    $this->model->buildTable();
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

    <h3 class="mt-3"><code>getSchemaFieldDifferences()</code></h3>
    <p>Returns an array containing the differences between the current database schema and the new schema definition after calling <code>buildTable()</code>.</p>
   
    <h3 class="mt-3"><code>dropTable()</code></h3>
    <p>Deletes the table if it exists. Called when running module uninstall from shell</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return bool Returns true if the operation was successful
    */
    public function dropTable(): bool;

    // Example
    $this->model->dropTable();
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
    <h3 class="mt-3"><code>validate(bool $validate_all = false)</code></h3>
    <p>Validates data stored in the Model using internal rules. Works with current record or all records in records_array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param bool $validate_all If true, validates all records. If false, validates only current record
    * @return bool
    */
    public function validate(bool $validate_all = false): bool;

    // Example - Validate current record
    $obj = $this->model->getEmpty($_REQUEST);
    if ($obj->validate()) {
        echo "Valid data";
    } else {
        echo "Invalid data";
    }

    // Example - Validate all records in Model
    if ($this->model->validate(true)) {
        echo "All records are valid";
    }
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                    <ul>
                    <li><code>$validate_all</code>: (bool) If true, validates all records in records_array. If false (default), validates only current record.</li>
                </ul>
            </li>
            <li><strong>Return value:</strong>
                    <ul>
                    <li><code>bool</code>: Returns true if the data is valid, false otherwise.</li>
                    </ul>
            </li>
        </ul>

  
    <h3 class="mt-3"><code>getColumns($key = '')</code></h3>
    <p>Returns all columns defined in the object rules.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return array Array of columns
    */
    public function getColumns(): array;

    // Example
    $all_columns = $this->model->getColumns();
    </code></pre>

    <h3 class="mt-3"><code>addFilter($filter_type, $fn)</code></h3>
    <p>Adds a custom filter function.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $filter_type Filter type
    * @param callable $fn Filter function
    */
    public function addFilter($filter_type, $fn);

    // Example
    $this->model->addFilter('status', function($query, $value) {
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
                MessagesHandler::addError('Invalid email format');
            }
        }
    ]);
    </code></pre>

    <h4 class="mt-3">Example 2: Saving with validation</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    protected function actionSaveProjects() {
        $id = _absint($_REQUEST[$this->model->getPrimaryKey()] ?? 0);

        // Create an object with request data (data is already inside the model)
        $obj = $this->model->getEmpty($_REQUEST);

        // Validate and save (data is internal to the model)
        if ($obj->validate()) {
            if ($obj->save()) {
                // If it's a new record retrieve the id
                if ($id == 0) {
                    $id = $obj->getLastInsertId();
                    Route::redirectSuccess('?page='.$this->page."&action=related-tables&id=".$id,
                        _r('Save success'));
                }
                Route::redirectSuccess($_REQUEST['url_success'], _r('Save success'));
            } else {
                $error = "An error occurred while saving the data. ".$this->model->getLastError();
                $obj2 = $this->model->getByIdAndUpdate($id, $_REQUEST);
                Route::redirectError($_REQUEST['url_error'], $error, toMysqlArray($obj2));
            }
        } 
        Route::redirectHandlerErrors($_REQUEST['url_error'], $array_to_save);
    }
    </code></pre>

    <br><br><br>
    <h4 class="mt-3">Example: List management with parameters</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    protected function actionListProjects() {
        $table_id = 'table_projects';
        
        // Retrieve request parameters for the table
        $request = $this->getRequestParams($table_id);
        
        // Register the delete action
        $this->callTableAction($table_id, 'delete-project', 'table_action_delete_project');
        
        // Set query parameters (limit, order, filters)
        $this->model->setQueryParams($request);
        
        // Retrieve data for modellist
        $modellist_data = $this->getModellistData($table_id, $fn_filter_applier);
        
        // Configuration customization
        $modellist_data['page_info']['limit'] = 1000;
        $modellist_data['page_info']['pagination'] = false;
        
        // Table output
        $outputType = Response::isJson() ? 'json' : 'html';
             
        $table_html = Get::themePlugin('table', $modellist_data); 
        $theme_path = realpath(__DIR__.'/Views/list.page.php');
    
        if ($outputType === 'json') {
            Response::json([
                'html' => $table_html,
                'success' => !MessagesHandler::hasErrors(),
                'msg' => MessagesHandler::errorsToString()
            ]);
        } else {
            Response::themePage('default',  $theme_path, [
                'table_html' => $table_html,
                'table_id' => $table_id,
                'page' => $this->page
            ]);
        }
    }
    </code></pre>
</div>