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
    <p class="text-muted">Revision: 2025/10/13</p>
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
