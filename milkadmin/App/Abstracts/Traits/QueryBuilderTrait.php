<?php
namespace App\Abstracts\Traits;

use App\Database\Query;

!defined('MILK_DIR') && die();

/**
 * Query Builder Trait
 * Handles all query building and execution operations
 */
trait QueryBuilderTrait
{
    /**
     * Filter functions
     * @var array
     */
    protected array $fn_filter = [];

    /**
     * Create a new Query instance
     * 
     * @param Query|null $query The query to use
     * @example
     * ```php
     * $rows = $this->model->query($custom_query)->getResults();
     * ```
     * @return Query The new Query instance
     */
    public function query(?Query $query = null): Query
    {
        if ($query !== null && $query instanceof Query) {
            $query->setModelClass($this);
        } else {
            $query = new Query($this->table, $this->db, $this);
        }

        // Apply query scopes (default and named queries)
        if (method_exists($this, 'applyQueryScopes')) {
            $query = $this->applyQueryScopes($query);
        }

        return $query;
    }

    /**
     * Add a WHERE clause to the query
     *
     * @example
     * ```php
     * $this->model->where('title LIKE ?', ['%test%'])->getResults();
     * ```
     *
     * @param string $condition The SQL condition to add to the WHERE clause
     * @param array $params Parameters to pass to the query to prevent SQL injection
     * @return Query Returns the Query instance for method chaining
     */
    public function where(string $condition, array $params = []): Query
    {
        $query =  $this->query();
        $new_model = new static();

        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        $query->where($condition, $params);
        return $query;
    }

    /**
     * Add a WHERE IN clause to the query
     *
     * @example
     * ```php
     * // Find posts with specific IDs
     * $this->model->whereIn('id', [1, 5, 10])->getResults();
     *
     * // Find users in specific cities
     * $this->model->whereIn('city', ['Rome', 'Milan', 'Florence'])->getResults();
     * ```
     *
     * @param string $field Field to check
     * @param array $values Array of values for the IN clause
     * @param string $operator Logical operator ('AND' or 'OR')
     * @return Query Returns the Query instance for method chaining
     */
    public function whereIn(string $field, array $values, string $operator = 'AND'): Query
    {
        $query = $this->query();
        $new_model = new static();

        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        $query->whereIn($field, $values, $operator);
        return $query;
    }

    /**
     * Filter results based on existence of related records
     * Uses EXISTS subquery to check if relationship has records matching condition
     *
     * @example
     * ```php
     * // Find actors that have films after 1990
     * $actors = $this->model->whereHas('films', 'year > ?', [1990])->getResults();
     *
     * // Find books with highly rated reviews (rating > 4)
     * $books = $this->model->whereHas('reviews', 'rating > ?', [4])->getResults();
     *
     * // Find authors that have books published after 2020
     * $authors = $this->model->whereHas('books', 'published_year > ?', [2020])->getResults();
     * ```
     *
     * @param string $relationAlias Relationship alias defined in configure()
     * @param string $condition WHERE condition for related records (e.g., 'year > ?')
     * @param array $params Parameters for the condition
     * @return Query Returns the Query instance for method chaining
     */
    public function whereHas(string $relationAlias, string $condition, array $params = []): Query
    {
        $query = $this->query();
        $new_model = new static();

        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        $query->whereHas($relationAlias, $condition, $params);
        return $query;
    }

    /**
     * Add an ORDER BY clause to the query
     *
     * @example
     * ```php
     * $this->model->order('title', 'desc')->getResults();
     * ```
     *
     * @param string|array $field Field or array of fields to order by
     * @param string $dir Direction of ordering ('asc' or 'desc')
     * @return Query Returns the Query instance for method chaining
     */
    public function order(string|array $field = '', string $dir = 'asc'): Query
    {
        $query = $this->query();
        $new_model = new static();

        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        $query->order($field, $dir);
        return $query;
    }

    /**
     * Add a SELECT clause to the query
     *
     * @example
     * ```php
     * $this->model->select('id, title')->getResults();
     * $this->model->select(['id', 'title'])->getResults();
     * ```
     *
     * @param array|string $fields Fields to select
     * @return Query Returns the Query instance for method chaining
     */
    public function select(array|string $fields): Query
    {
        $query = $this->query();
        $new_model = new static();

        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        if (is_array($fields)) {
            $fields = implode(', ', $fields);
        }
        $query->select($fields);
        return $query;
    }

    /**
     * Add a LIMIT clause to the query
     *
     * @example
     * ```php
     * $this->model->limit(10, 10)->getResults();
     * ```
     *
     * @param int $start Number of records to skip or number of recods if $limit is -1
     * @param int $limit Number of records to retrieve
     * @return Query Returns the Query instance for method chaining
     */
    public function limit(int $start, int $limit = -1): Query
    {
        if ($limit == -1) {
            $limit = $start;
            $start = 0;
        }
        $query = $this->query();
        $new_model = new static();

        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        $query->limit($start, $limit);
        return $query;
    }

 
    /**
     * Execute the current query and return a Model with single record
     *
     * @param string $query The SQL query to execute
     * @param array $params Parameters to pass to the query to prevent SQL injection
     *
     * @example
     * ```php
     * $model = $this->select('id, title')->where('status = ?', ['published'])->getOne();
     * echo $model->title; // Access the record
     * // or
     * $model = $this->getOne('SELECT id, title FROM posts WHERE status = ?', ['published']);
     * ```
     *
     * @return static|null Model instance with ResultInterface containing one record, or null if no records found
     */
    public function getFirst($order_field = '', $order_dir = 'asc'): ?static
    {
        $query = $this->query();
        $new_model = new static();

        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        if ($order_field != '') {
            $query->order($order_field, $order_dir);
        }
        return $query->getRow();
    }

    /**
     * Get all data of a specific query without limits
     *
     * Executes the current query without limits to retrieve all data.
     * Returns a Model instance with ResultInterface containing all records.
     *
     * @example
     * ```php
     * $model = $this->getAll();
     * echo $model->count(); // Total records
     * while ($model->hasNext()) {
     *     echo $model->title;
     *     $model->next();
     * }
     * ```
     *
     * @return static Model instance containing all records via ResultInterface
     */
    public function getAll($order_field = '', $order_dir = 'asc'): static
    {
        $query = $this->query();
        if ($order_field != '') {
            $query->order($order_field, $order_dir);
        }
        $new_model = new static();

        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        $query->clean('limit');
        return $query->getResults();
    }

    /**
     * Get the total count of records
     *
     * Creates a new query and returns the total number of records without limitations
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
        $query = $this->query();
        $total = (int)$query->select('COUNT(*) as total')->getVar();
        return $total;
    }

    /**
     * Set query parameters from request
     *
     * Sets query parameters (limit, order, filter) from the request and returns a Query
     *
     * @example
     * ```php
     * $request = $this->getRequestParams('table_posts');
     * $query = $this->model->setQueryParams($request);
     * $results = $query->getResults();
     * ```
     *
     * @param array $request The request from the browser
     * @return Query The query with parameters set
     */
    public function setQueryParams($request): Query {
        $query = $this->query();
        $query->limit($request['limit_start'] ?? 0, $request['limit'] ?? 10);
        if (($request['order_field'] ?? null) && ($request['order_dir'] ?? null)) {
            $query->order($request['order_field'], $request['order_dir']);
        }
        $this->addQueryFromFilters($request['filters'] ?? '', $query);
        return $query;
    }

    /**
     * Filter search results
     *
     * Adds search conditions to the provided query
     * This was moved from modellist because all query-related functionality should be in the model
     *
     * @param string $search The search term
     * @param Query $query The query to add search conditions to
     * @return Query The query with search conditions added
     */
    public function filterSearch($search, Query $query): Query {
        $list_structure = $this->getTableStructure();
        foreach ($list_structure as $field => $column_info) {
            $query->where('`'.$field.'` LIKE ? ', ['%'.$search.'%'], 'OR');
        }
        return $query;
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
    public function addFilter($filter_type, $fn) {
        $this->fn_filter[$filter_type] = $fn;
    }

    /**
     * Add filters to the query
     *
     * Adds filters to the query by calling filter_* functions
     * This is called by set_query_params
     *
     * @param string $request_filters The filters from the request
     * @param Query $query The query to add filters to
     * @return Query The query with filters added
     */
    protected function addQueryFromFilters($request_filters, Query $query): Query {
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
                        call_user_func($this->fn_filter[$filter_type], $filter, $query);
                    } else if (method_exists($this, $standard_fn_filter)) {
                        call_user_func([$this, $standard_fn_filter], $filter, $query);
                    }
                }
            }
        }
        return $query;
    }
}