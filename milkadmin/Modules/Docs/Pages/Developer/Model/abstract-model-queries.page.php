<?php
namespace Modules\Docs\Pages;
/**
 * @title Query Builder Methods
 * @guide Models
 * @order 51
 * @tags model, query, where, whereIn, whereHas, order, limit, select, QueryBuilder
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Query Builder Methods</h1>
    <p class="text-muted">Revision: 2026/01/28</p>
    <p class="lead">The Model provides a fluent query builder interface for constructing and executing database queries. All query building methods return a <code>Query</code> instance, allowing you to chain operations together.</p>

    <div class="alert alert-info">
        <strong>💡 Method Chaining:</strong> Query methods can be chained together to build complex queries. The final method in the chain (<code>getResults()</code>, <code>getRow()</code>, etc.) executes the query and returns a Model instance with results.
    </div>

    <h2 class="mt-4">Basic Query Building</h2>

    <h3 class="mt-3"><code>where(string $condition, array $params = []): Query</code></h3>
    <p>Adds a WHERE clause to the query. Multiple WHERE clauses are combined with AND by default.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $condition SQL condition with placeholders (?)
 * @param array $params Parameters to bind (prevents SQL injection)
 * @return Query Query instance for method chaining
 */
public function where(string $condition, array $params = []): Query;

// Example: Single condition
$products = $model->where('price > ?', [100])->getResults();

// Example: Multiple conditions (AND)
$products = $model
    ->where('in_stock = ?', [true])
    ->where('price > ?', [10])
    ->where('category_id = ?', [5])
    ->getResults();

// Example: LIKE search
$products = $model
    ->where('name LIKE ?', ['%laptop%'])
    ->getResults();

// Example: Complex conditions
$products = $model
    ->where('(price > ? OR discount > ?)', [100, 20])
    ->where('status = ?', ['active'])
    ->getResults();</code></pre>

    <div class="alert alert-warning">
        <strong>⚠️ SQL Injection Protection:</strong> Always use placeholders (?) and pass values via the $params array. Never concatenate user input directly into the condition string.
    </div>

    <h3 class="mt-3"><code>whereIn(string $field, array $values, string $operator = 'AND'): Query</code></h3>
    <p>Adds a WHERE IN clause to filter records where a field matches any value in an array.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $field Field name to check
 * @param array $values Array of values for IN clause
 * @param string $operator 'AND' or 'OR' to combine with previous conditions
 * @return Query Query instance for method chaining
 */
public function whereIn(string $field, array $values, string $operator = 'AND'): Query;

// Example: Filter by multiple IDs
$products = $model
    ->whereIn('id', [1, 5, 10, 15])
    ->getResults();

// Example: Filter by categories
$products = $model
    ->whereIn('category_id', [1, 2, 3])
    ->order('name', 'asc')
    ->getResults();

// Example: String values
$products = $model
    ->whereIn('status', ['active', 'pending', 'featured'])
    ->getResults();

// Example: Combine with other conditions
$products = $model
    ->where('in_stock = ?', [true])
    ->whereIn('category_id', [1, 2, 3])
    ->where('price > ?', [10])
    ->getResults();</code></pre>

    <h3 class="mt-3"><code>whereHas(string $relationAlias, string $condition, array $params = []): Query</code></h3>
    <p>Filters records based on the existence of related records matching a condition. Uses an EXISTS subquery for optimal performance.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $relationAlias Relationship alias defined in configure()
 * @param string $condition WHERE condition for related records
 * @param array $params Parameters for the condition
 * @return Query Query instance for method chaining
 */
public function whereHas(string $relationAlias, string $condition, array $params = []): Query;

// Example: Find authors with books published after 2020
$authors = $authorsModel
    ->whereHas('books', 'published_year > ?', [2020])
    ->getResults();

// Example: Find books with high-rated reviews
$books = $booksModel
    ->whereHas('reviews', 'rating > ?', [4])
    ->getResults();

// Example: Find products with recent orders
$products = $productsModel
    ->whereHas('orders', 'created_at > ?', ['2025-01-01'])
    ->getResults();

// Example: Combine with regular WHERE
$authors = $authorsModel
    ->where('country = ?', ['USA'])
    ->whereHas('books', 'published_year > ?', [2020])
    ->getResults();</code></pre>

    <div class="alert alert-info">
        <strong>📖 Relationships Required:</strong> The relationship must be defined in the model's <code>configure()</code> method using <code>hasOne()</code>, <code>hasMany()</code>, or <code>belongsTo()</code>.
    </div>

    <h2 class="mt-4">Ordering and Limiting</h2>

    <h3 class="mt-3"><code>order(string|array $field = '', string $dir = 'asc'): Query</code></h3>
    <p>Adds an ORDER BY clause to sort query results.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string|array $field Field name or array of fields
 * @param string $dir Sort direction: 'asc' or 'desc'
 * @return Query Query instance for method chaining
 */
public function order(string|array $field = '', string $dir = 'asc'): Query;

// Example: Simple ordering
$products = $model->order('name', 'asc')->getResults();

// Example: Order by multiple fields
$products = $model
    ->order(['category_id', 'price'], ['asc', 'desc'])
    ->getResults();

// Example: Order with WHERE
$products = $model
    ->where('in_stock = ?', [true])
    ->order('price', 'desc')
    ->getResults();</code></pre>

    <h3 class="mt-3"><code>limit(int $start, int $limit = -1): Query</code></h3>
    <p>Adds a LIMIT clause for pagination or restricting result count.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param int $start Offset (number of records to skip) OR limit if $limit is -1
 * @param int $limit Number of records to retrieve (default: -1)
 * @return Query Query instance for method chaining
 */
public function limit(int $start, int $limit = -1): Query;

// Example: Limit to 10 records
$products = $model->limit(10)->getResults();

// Example: Pagination (skip 20, take 10)
$products = $model->limit(20, 10)->getResults();

// Example: Paginated query
$page = $_GET['page'] ?? 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$products = $model
    ->where('in_stock = ?', [true])
    ->order('created_at', 'desc')
    ->limit($offset, $perPage)
    ->getResults();

$total = $model->total();</code></pre>

    <h3 class="mt-3"><code>select(array|string $fields): Query</code></h3>
    <p>Specifies which columns to select. By default, all columns (*) are selected.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param array|string $fields Fields to select (string or array)
 * @return Query Query instance for method chaining
 */
public function select(array|string $fields): Query;

// Example: Select specific fields (string)
$products = $model
    ->select('id, name, price')
    ->getResults();

// Example: Select specific fields (array)
$products = $model
    ->select(['id', 'name', 'price'])
    ->getResults();

// Example: With WHERE and ORDER
$products = $model
    ->select(['id', 'name', 'price'])
    ->where('in_stock = ?', [true])
    ->order('price', 'asc')
    ->getResults();</code></pre>

    <h2 class="mt-4">Query Execution Methods</h2>

    <h3 class="mt-3"><code>getResults(): static</code></h3>
    <p>Executes the query and returns a Model instance containing all matching records.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @return static Model instance with ResultInterface containing records
 */
public function getResults(): static;

// Example
$products = $model
    ->where('price > ?', [10])
    ->order('name', 'asc')
    ->limit(0, 20)
    ->getResults();

// Access results
echo "Found: " . $products->count() . " products\n";

foreach ($products as $product) {
    echo $product->name . ": €" . $product->price . "\n";
}</code></pre>

    <h3 class="mt-3"><code>getRow(): ?static</code></h3>
    <p>Executes the query and returns a Model instance with a single record. Automatically adds LIMIT 1.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @return static|null Model instance with one record or null
 */
public function getRow(): ?static;

// Example
$product = $model
    ->where('id = ?', [1])
    ->getRow();

if ($product && $product->count() > 0) {
    echo $product->name;
}</code></pre>

    <h3 class="mt-3"><code>getVar(): mixed</code></h3>
    <p>Executes the query and returns a single value (first column of first row).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @return mixed Single value from first row, first column
 */
public function getVar(): mixed;

// Example: Get count
$count = $model
    ->query()
    ->select('COUNT(*)')
    ->where('in_stock = ?', [true])
    ->getVar();

echo "Total in stock: $count";</code></pre>

    <h2 class="mt-4">Helper Methods</h2>

    <h3 class="mt-3"><code>getAll(): static</code></h3>
    <p>Retrieves all records without any LIMIT (removes any previously set limit).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @return static Model instance with all records
 */
public function getAll(): static;

// Example
$allProducts = $model->getAll();

// Example: With WHERE but no LIMIT
$activeProducts = $model
    ->where('status = ?', ['active'])
    ->getAll();</code></pre>

    <h3 class="mt-3"><code>getFirst(string $order_field = '', string $order_dir = 'asc'): ?static</code></h3>
    <p>Retrieves the first record, optionally ordered by a specific field.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $order_field Field to order by
 * @param string $order_dir Sort direction
 * @return static|null Model instance with first record
 */
public function getFirst(string $order_field = '', string $order_dir = 'asc'): ?static;

// Example: First product by ID
$first = $model->getFirst('id', 'asc');

// Example: Latest product
$latest = $model->getFirst('created_at', 'desc');

// Example: With WHERE
$model->where('status = ?', ['active']);
$first = $model->getFirst('name', 'asc');</code></pre>

    <h3 class="mt-3"><code>total(): int</code></h3>
    <p>Returns the total count of records matching the current query conditions (ignoring LIMIT).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @return int Total number of matching records
 */
public function total(): int;

// Example: Total products
$total = $model->total();

// Example: With pagination
$products = $model
    ->where('in_stock = ?', [true])
    ->limit(0, 20)
    ->getResults();

$totalMatching = $model
    ->where('in_stock = ?', [true])
    ->total();

echo "Showing " . $products->count() . " of $totalMatching products";</code></pre>

    <h2 class="mt-4">Query State Management</h2>
    <p>Understanding how query state accumulates and resets is critical when building queries incrementally or reusing a model across multiple operations.</p>

    <h3 class="mt-3">WHERE Clauses Accumulate</h3>
    <p>Each call to <code>where()</code> adds a condition to the same <code>Query</code> instance until the query is executed. Conditions are combined with AND.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Conditions accumulate on the same query
$this->model->where('category_id = ?', [4]);
$query = $this->model->query();
$query_txt = $query->toSql();
// SELECT * FROM `products` WHERE (category_id = 4)

$query2 = $this->model->where('price > ?', [99]);
$query_txt2 = $query2->toSql();
// SELECT * FROM `products` WHERE (category_id = 4) AND (price > 99)
// ↑ both conditions are present</code></pre>

    <h3 class="mt-3">Automatic Reset After Execution</h3>
    <p>When a query is <strong>executed</strong> (via <code>getRow()</code>, <code>getResults()</code>, <code>getFirst()</code>, <code>total()</code>), the <code>current_query</code> is reset. The next call to <code>where()</code> or <code>query()</code> will create a fresh instance.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// First query: only category_id
$this->model->where('category_id = ?', [4]);
$query = $this->model->query();
$query_txt = $query->toSql();
// SELECT * FROM `products` WHERE (category_id = 4)

// Execute the query → this resets the state
$this->model->getRow();

// Second query: starts fresh, price is the only condition
$query2 = $this->model->where('price > ?', [99]);
$query_txt2 = $query2->toSql();
// SELECT * FROM `products` WHERE (price > 99)
// ↑ category_id is gone, query was reset after getRow()</code></pre>

    <div class="alert alert-warning">
        <strong>⚠️ Warning:</strong> If you don't execute the query between two <code>where()</code> blocks, the conditions will stack up. The reset only happens after an execution (<code>getRow</code>, <code>getResults</code>, <code>getFirst</code>, <code>total</code>) or by explicitly calling <code>newQuery()</code>.
    </div>

    <h3 class="mt-3">Force a New Query with <code>newQuery()</code></h3>
    <p>If you need to reset the query without executing it, use <code>newQuery()</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$this->model->where('status = ?', ['active']);

// Start fresh without executing
$query = $this->model->newQuery();
$query->where('category_id = ?', [5]);
$results = $query->getResults();
// SELECT * FROM `products` WHERE (category_id = 5)
// ↑ 'status = active' was discarded</code></pre>

    <h2 class="mt-4">Joins with from()</h2>
    <p>The <code>from()</code> method on <code>Query</code> allows adding joins or additional tables to the query. It supports full JOIN syntax including <code>LEFT JOIN</code>.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// LEFT JOIN: combine two tables
$results = $this->model->query()
    ->from('LEFT JOIN orders ON products.id = orders.product_id')
    ->where('orders.created_at > ?', ['2025-01-01'])
    ->getResults();

// Multiple JOINs
$results = $this->model->query()
    ->from('JOIN categories ON products.cat_id = categories.id')
    ->from('LEFT JOIN images ON products.id = images.product_id')
    ->select('products.*, categories.name as category, images.url')
    ->getResults();

// Subquery with from (additional table syntax)
$results = $this->model->query()
    ->from('(SELECT product_id, COUNT(*) as cnt FROM orders GROUP BY product_id) AS order_counts')
    ->where('order_counts.cnt > ?', [5])
    ->getResults();</code></pre>

    <div class="alert alert-info">
        <strong>💡 Alternative to JOINs:</strong> For filtering based on relationships defined in the model, prefer <code>whereHas()</code> which uses a more performant EXISTS subquery and doesn't require writing JOIN syntax manually.
    </div>

    <h2 class="mt-4">Testing with ArrayDb</h2>
    <p>To test queries without hitting the real database, you can convert a model to an <code>ArrayDb</code> via <code>setDbType('array')</code>. Queries are then executed in memory on mock data.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Convert the model to ArrayDb for testing
$model = new ProductModel();
$model->setDbType('array');

// Queries are now executed in memory
$results = $model->where('category_id = ?', [4])->getResults();

// Also useful to inspect the generated SQL without hitting the DB
$model->where('status = ?', ['active']);
$query = $model->query();
echo $query->toSql(); // See the built query without executing it</code></pre>

    <div class="alert alert-info">
        <strong>💡 Available DB types:</strong> <code>setDbType('db')</code> for the main database, <code>setDbType('db2')</code> for the secondary database, <code>setDbType('array')</code> for in-memory ArrayDb.
    </div>

    <h2 class="mt-4">Results and isEmpty()</h2>
    <p>Execution methods like <code>getById()</code>, <code>getRow()</code> and <code>getResults()</code> always return a <strong>new model</strong> with the loaded data. If the query finds no results, the model is still returned but will be empty.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// getById returns a new model with the data
$product = $model->getById(123);

// isEmpty() checks whether the model has data
if (!$product->isEmpty()) {
    echo "Found: " . $product->name;
} else {
    echo "Product not found";
}

// Same behavior with getRow()
$row = $model->where('code = ?', ['ABC'])->getRow();
if ($row->isEmpty()) {
    echo "No results for code ABC";
}

// And with getResults() for multiple results
$results = $model->where('status = ?', ['active'])->getResults();
if (!$results->isEmpty()) {
    echo "Found " . $results->count() . " active products";
    foreach ($results as $item) {
        echo $item->name . "\n";
    }
}</code></pre>

    <div class="alert alert-warning">
        <strong>⚠️ Don't check for null:</strong> Query methods always return a Model instance, never <code>null</code>. Always use <code>isEmpty()</code> or <code>count() > 0</code> to verify whether data was found.
    </div>

    <h2 class="mt-4">Advanced Examples</h2>

    <h3>Complex Search with Multiple Conditions</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$minPrice = $_GET['min_price'] ?? 0;

$query = $model->query();

if ($search) {
    $query->where('name LIKE ?', ['%' . $search . '%']);
}

if ($category) {
    $query->where('category_id = ?', [$category]);
}

if ($minPrice > 0) {
    $query->where('price >= ?', [$minPrice]);
}

$products = $query
    ->where('in_stock = ?', [true])
    ->order('created_at', 'desc')
    ->limit(0, 20)
    ->getResults();

$total = $model->total();</code></pre>

    <h3>Filtering with Relationships</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Find authors who have books with high-rated reviews
$authors = $authorsModel
    ->whereHas('books', 'id IN (
        SELECT book_id FROM #__reviews WHERE rating > 4
    )', [])
    ->order('name', 'asc')
    ->getResults();

// Find products that have been ordered recently
$products = $productsModel
    ->whereHas('orders', 'created_at > ?', [date('Y-m-d', strtotime('-30 days'))])
    ->order('name', 'asc')
    ->getResults();</code></pre>

    <h3>Dynamic Filtering</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Build query based on filters array
$filters = [
    'status' => 'active',
    'categories' => [1, 2, 3],
    'min_price' => 10,
    'search' => 'laptop'
];

$query = $model->query();

if (isset($filters['status'])) {
    $query->where('status = ?', [$filters['status']]);
}

if (!empty($filters['categories'])) {
    $query->whereIn('category_id', $filters['categories']);
}

if (isset($filters['min_price'])) {
    $query->where('price >= ?', [$filters['min_price']]);
}

if (isset($filters['search'])) {
    $query->where('name LIKE ?', ['%' . $filters['search'] . '%']);
}

$results = $query->getResults();</code></pre>

    <h2 class="mt-4">See Also</h2>

    <div class="alert alert-success">
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-overview">Model Overview</a> - General concepts</li>
            <li><a href="?page=docs&action=Developer/Model/abstract-model-crud">CRUD Operations</a> - Create, Read, Update, Delete</li>
            <li><a href="?page=docs&action=Developer/Model/abstract-model-relationships">Relationships</a> - hasOne, belongsTo, hasMany</li>
            <li><a href="?page=docs&action=Framework/Core/query">Query Class</a> - Low-level Query class documentation</li>
        </ul>
    </div>
</div>
