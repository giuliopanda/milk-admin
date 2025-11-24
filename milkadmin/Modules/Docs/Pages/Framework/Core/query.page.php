<?php
namespace Modules\Docs\Pages;
/**
 * @title Query Builder
 * @guide framework
 * @order 40
 * @tags query-builder, SQL, fluent-interface, where, whereIn, whereHas, JOIN, SELECT, ORDER, LIMIT, relationships, DatabaseException
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Query Class</h1>
    <p>TODO: To be tested</p>
    <p>Fluent interface for programmatic SQL query building. Works standalone or integrated with Models.</p>

    <div class="alert alert-info">
        <strong><i class="bi bi-info-circle"></i> Higher-Level Abstraction</strong><br>
        For complete CRUD operations with validation and relationships, use <strong>AbstractModel</strong>.
    </div>

    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle"></i> Error Handling</strong><br>
        Query failures throw <strong>DatabaseException</strong>. Always use try/catch in production.
    </div>

    <h2 class="mt-4">Usage Modes</h2>

    <div class="table-responsive mb-4">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Aspect</th>
                    <th>Through Model</th>
                    <th>Standalone</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Execution</strong></td>
                    <td>Automatic via getResults()</td>
                    <td>Manual via get() + db->query()</td>
                </tr>
                <tr>
                    <td><strong>Return Type</strong></td>
                    <td>Model instance</td>
                    <td>Array of stdClass or scalar</td>
                </tr>
                <tr>
                    <td><strong>Relationships</strong></td>
                    <td>✅ Lazy loading supported</td>
                    <td>❌ Not available</td>
                </tr>
                <tr>
                    <td><strong>whereHas()</strong></td>
                    <td>✅ Available</td>
                    <td>❌ Not available</td>
                </tr>
                <tr>
                    <td><strong>Use Case</strong></td>
                    <td>✅ Recommended for most cases</td>
                    <td>Complex queries, reports</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h4 class="mt-4">Through Model (Recommended)</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Modules\Products\ProductsModel;

$products = new ProductsModel();

$results = $products
    ->where('in_stock = ?', [true])
    ->whereIn('category_id', [1, 2, 3])
    ->order('price', 'asc')
    ->limit(0, 10)
    ->getResults();  // Returns ProductsModel with results

foreach ($results as $product) {
    echo $product->name . ": €" . $product->price;
}</code></pre>

    <h4 class="mt-4">Standalone</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Database\Query;
use App\Get;

$query = new Query('#__products', Get::db());
$query->select('id, name, price')
      ->where('in_stock = ?', [true])
      ->order('price', 'asc')
      ->limit(0, 10);

list($sql, $params) = $query->get();
$result = Get::db()->getResults($sql, $params);

foreach ($result as $row) {
    echo $row->name;
}</code></pre>

    <h2 class="mt-4">Query Building Methods</h2>

    <h4 class="text-primary mt-4">__construct(string $table, $db = null, $model = null)</h4>
    <p>Creates Query instance for specified table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Through Model (automatic)
$query = $model->query();

// Standalone
$query = new Query('#__products', Get::db());</code></pre>

    <h4 class="text-primary mt-4">select(string $fields) : Query</h4>
    <p>Specifies columns to select.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$results = $model
    ->select('id, name, price')
    ->getResults();

// Aggregate
$total = $model->query()
    ->select('COUNT(*) as total')
    ->getVar();</code></pre>

    <h4 class="text-primary mt-4">from(string $from) : Query</h4>
    <p>Adds FROM or JOIN clauses.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$query->from('LEFT JOIN categories ON products.category_id = categories.id')
      ->from('LEFT JOIN brands ON products.brand_id = brands.id');</code></pre>

     <h4 class="text-primary mt-4">where($condition, $params = [], $operator = 'AND'): Query</h4>
    <p>Adds a WHERE condition to the query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $condition SQL condition with placeholders (?)
 * @param array $params Values to bind
 * @param string $operator 'AND' or 'OR' to combine with previous conditions
 * @return Query
 */
public function where($condition, $params = [], $operator = 'AND'): Query;

// Example: Single condition
$results = $model
    ->where('price > ?', [100])
    ->getResults();

// Example: Multiple AND conditions
$results = $model
    ->where('in_stock = ?', [true])
    ->where('price > ?', [10])
    ->where('category_id = ?', [5])
    ->getResults();

// Example: OR condition
$results = $model
    ->where('status = ?', ['active'])
    ->where('status = ?', ['featured'], 'OR')
    ->getResults();

// Example: LIKE search
$results = $model
    ->where('name LIKE ?', ['%' . $search . '%'])
    ->getResults();

// Example: Complex conditions
$results = $model
    ->where('(price > ? OR discount > ?)', [100, 20])
    ->where('status = ?', ['active'])
    ->getResults();</code></pre>

    <div class="alert alert-warning mt-3">
        <strong>⚠️ SQL Injection Protection:</strong> Always use placeholders (?) and pass values in the $params array. Never concatenate user input directly into conditions.
    </div>

    <h4 class="text-primary mt-4">whereIn($field, $values, $operator = 'AND'): Query</h4>
    <p>Adds a WHERE IN clause to filter by multiple values.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $field Field name to check
 * @param array $values Array of values for IN clause
 * @param string $operator 'AND' or 'OR'
 * @return Query
 */
public function whereIn($field, $values, $operator = 'AND'): Query;

// Example: Filter by IDs through Model
$products = $model
    ->whereIn('id', [1, 5, 10, 15])
    ->getResults();

echo "Found: " . $products->count() . " products\n";
foreach ($products as $product) {
    echo "- " . $product->name . "\n";
}

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
    ->getResults();

// Example: Empty array (returns all records)
$products = $model
    ->whereIn('id', [])  // No filtering applied
    ->getResults();</code></pre>

    <h4 class="text-primary mt-4">whereHas($relationAlias, $condition, $params = []): Query</h4>
    <p>Filters records based on related data using an EXISTS subquery. The relationship must be defined in the Model's <code>configure()</code> method.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $relationAlias Relationship alias from configure()
 * @param string $condition WHERE condition for related records
 * @param array $params Parameters for the condition
 * @param string $operator 'AND' or 'OR'
 * @return Query
 */
public function whereHas($relationAlias, $condition, $params = [], $operator = 'AND'): Query;

// Example 1: Find authors with books published after 2020
// Assumes AuthorsModel has: ->id()->hasMany('books', BooksModel::class, 'author_id')
$authors = $authorsModel
    ->whereHas('books', 'published_year > ?', [2020])
    ->getResults();

echo "Authors with recent books:\n";
foreach ($authors as $author) {
    echo "- " . $author->name . "\n";
}

// Example 2: Find books with high-rated reviews
// Assumes BooksModel has: ->id()->hasMany('reviews', ReviewsModel::class, 'book_id')
$books = $booksModel
    ->whereHas('reviews', 'rating > ?', [4])
    ->order('title', 'asc')
    ->getResults();

// Example 3: Find products ordered in the last 30 days
// Assumes ProductsModel has: ->id()->hasMany('orders', OrdersModel::class, 'product_id')
$recentProducts = $productsModel
    ->whereHas('orders', 'created_at > ?', [date('Y-m-d', strtotime('-30 days'))])
    ->getResults();

// Example 4: Combine with regular WHERE
$authors = $authorsModel
    ->where('country = ?', ['USA'])
    ->whereHas('books', 'published_year > ?', [2020])
    ->getResults();

// Example 5: Multiple whereHas (AND logic)
$authors = $authorsModel
    ->whereHas('books', 'published_year > ?', [2020])
    ->whereHas('books', 'price > ?', [20])
    ->getResults();</code></pre>

    <div class="alert alert-info mt-3">
        <strong>📖 How whereHas Works:</strong>
        <p class="mb-0">The <code>whereHas()</code> method generates an SQL EXISTS subquery that checks if related records exist matching your condition. This is more efficient than JOIN + DISTINCT when you only need to filter the main table without accessing related data.</p>
    </div>

    <h4 class="mt-3">Relationship Definition Required</h4>
    <p>Before using <code>whereHas()</code>, you must define the relationship in your Model's <code>configure()</code> method:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// AuthorsModel.php
class AuthorsModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__authors')
            ->id()
            ->hasMany('books', BooksModel::class, 'author_id')  // Define relationship
            ->string('name', 100)->required()
            ->string('country', 50)->nullable();
    }
}

// BooksModel.php
class BooksModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__books')
            ->id()
            ->hasMany('reviews', ReviewsModel::class, 'book_id')  // Define relationship
            ->int('author_id')->belongsTo('author', AuthorsModel::class, 'id')
            ->string('title', 200)->required()
            ->int('published_year');
    }
}

// Now you can use whereHas:
$authors = $authorsModel
    ->whereHas('books', 'published_year > ?', [2020])
    ->getResults();</code></pre>

    <h3 class="mt-3"><code>order($field = '', $dir = 'asc'): Query</code></h3>
    <p>Adds ORDER BY clause to sort results.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string|array $field Field name(s)
 * @param string|array $dir Sort direction ('asc' or 'desc')
 * @return Query
 */
public function order($field = '', $dir = 'asc'): Query;

// Example: Single field
$products = $model
    ->order('name', 'asc')
    ->getResults();

// Example: Descending
$products = $model
    ->order('created_at', 'desc')
    ->getResults();

// Example: Multiple fields
$products = $model
    ->order(['category_id', 'price'], ['asc', 'desc'])
    ->getResults();</code></pre>

    <h3 class="mt-3"><code>limit($start, $limit): Query</code></h3>
    <p>Limits the number of results (pagination).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param int $start Offset (records to skip)
 * @param int $limit Number of records to return
 * @return Query
 */
public function limit($start, $limit): Query;

// Example: First 10 records
$products = $model
    ->limit(0, 10)
    ->getResults();

// Example: Pagination
$page = $_GET['page'] ?? 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$products = $model
    ->where('in_stock = ?', [true])
    ->order('created_at', 'desc')
    ->limit($offset, $perPage)
    ->getResults();

// Get total for pagination
$total = $model
    ->where('in_stock = ?', [true])
    ->total();</code></pre>

    <h3 class="mt-3"><code>group($group): Query</code></h3>
    <p>Adds GROUP BY clause.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $group Fields to group by
 * @return Query
 */
public function group($group): Query;

// Example: Group by category
$query = $model->query();
$query->select('category_id, COUNT(*) as total')
      ->group('category_id');

list($sql, $params) = $query->get();
$results = $db->getResults($sql, $params);</code></pre>

    <h3 class="mt-3"><code>having($condition, $params = []): Query</code></h3>
    <p>Adds HAVING clause (used with GROUP BY).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $condition HAVING condition
 * @param array $params Parameters
 * @return Query
 */
public function having($condition, $params = []): Query;

// Example: Groups with more than 5 items
$query = $model->query();
$query->select('category_id, COUNT(*) as total')
      ->group('category_id')
      ->having('COUNT(*) > ?', [5]);</code></pre>

    <h2 class="mt-4">Query Execution Methods</h2>

    <h3 class="mt-3">Through Model</h3>
    <p>When using Query through a Model, these methods execute the query and return Model instances:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// getResults() - Returns Model with all matching records
$products = $model
    ->where('in_stock = ?', [true])
    ->getResults();  // Returns ProductsModel instance

echo $products->count();  // Number of results

// getRow() - Returns Model with single record
$product = $model
    ->where('id = ?', [1])
    ->getRow();  // Returns ProductsModel instance with 1 record

if ($product && $product->count() > 0) {
    echo $product->name;
}

// getVar() - Returns single value
$count = $model->query()
    ->select('COUNT(*)')
    ->where('in_stock = ?', [true])
    ->getVar();  // Returns integer</code></pre>

    <h3 class="mt-3">Standalone</h3>
    <p>When using Query standalone, you manually execute with the database:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$query = new Query('#__products', Get::db());
$query->where('in_stock = ?', [true]);

// Get SQL and params
list($sql, $params) = $query->get();

// Execute manually
$db = Get::db();
$results = $db->getResults($sql, $params);  // Returns array of stdClass

// Or for single row
$row = $db->getRow($sql, $params);  // Returns stdClass

// Or for single value
$value = $db->getVar($sql, $params);  // Returns mixed</code></pre>

    <h2 class="mt-4">Helper Methods</h2>

    <h3 class="mt-3"><code>clean($part = ''): Query</code></h3>
    <p>Removes specific parts of the query or resets it entirely.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Clean specific part
$query->clean('limit');   // Remove LIMIT
$query->clean('where');   // Remove WHERE conditions
$query->clean('order');   // Remove ORDER BY

// Clean everything
$query->clean();

// Example: Reuse query without limit for counting
$query = $model->query()
    ->where('in_stock = ?', [true])
    ->limit(0, 10);

$results = $query->getResults();  // With limit

$query->clean('limit');
$total = $query->getVar();  // Without limit</code></pre>

    <h3 class="mt-3">Check Methods</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Check if query parts are set
$query->hasSelect();   // Has SELECT clause?
$query->hasWhere();    // Has WHERE conditions?
$query->hasOrder();    // Has ORDER BY?
$query->hasLimit();    // Has LIMIT?
$query->hasGroup();    // Has GROUP BY?</code></pre>

    <h2 class="mt-4">Complete Examples</h2>

    <h3>Example 1: Product Listing with Filters (Through Model)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Modules\Products\ProductsModel;

$products = new ProductsModel();

// Get filters from request
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 0;
$minPrice = $_GET['min_price'] ?? 0;
$page = $_GET['page'] ?? 1;
$perPage = 20;

// Build query conditionally
if ($search) {
    $products->where('name LIKE ?', ['%' . $search . '%']);
}

if ($category > 0) {
    $products->where('category_id = ?', [$category]);
}

if ($minPrice > 0) {
    $products->where('price >= ?', [$minPrice]);
}

// Execute with pagination
$offset = ($page - 1) * $perPage;
$results = $products
    ->where('in_stock = ?', [true])
    ->order('created_at', 'desc')
    ->limit($offset, $perPage)
    ->getResults();  // Returns ProductsModel

// Get total for pagination
$total = $products->total();

// Display results
echo "Showing " . $results->count() . " of $total products\n\n";

foreach ($results as $product) {
    echo "- {$product->name}: €{$product->price}\n";
}</code></pre>

    <h3>Example 2: Complex Query with JOINs (Standalone)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Database\Query;
use App\Get;

$db = Get::db();
$query = new Query('#__products', $db);

// Build complex query
$query->select('products.*, categories.name as category_name, brands.name as brand_name')
      ->from('LEFT JOIN categories ON products.category_id = categories.id')
      ->from('LEFT JOIN brands ON products.brand_id = brands.id')
      ->where('products.in_stock = ?', [true])
      ->where('categories.active = ?', [1])
      ->order('products.name', 'asc')
      ->limit(0, 10);

// Execute
list($sql, $params) = $query->get();
$results = $db->getResults($sql, $params);

foreach ($results as $row) {
    echo "{$row->name} - {$row->category_name} ({$row->brand_name})\n";
}</code></pre>

    <h3>Example 3: Using whereHas for Filtering by Relationships</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Find authors who have popular books (books with many high-rated reviews)

// Step 1: Define relationships in Models
class AuthorsModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__authors')
            ->id()->hasMany('books', BooksModel::class, 'author_id')
            ->string('name', 100)->required();
    }
}

class BooksModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__books')
            ->id()->hasMany('reviews', ReviewsModel::class, 'book_id')
            ->int('author_id')->belongsTo('author', AuthorsModel::class, 'id')
            ->string('title', 200)->required();
    }
}

// Step 2: Query authors with whereHas
$authors = $authorsModel
    ->whereHas('books', 'published_year > ?', [2020])
    ->order('name', 'asc')
    ->getResults();

echo "Authors with recent books:\n";
foreach ($authors as $author) {
    echo "- {$author->name}\n";

    // Can still access relationships via lazy loading
    $recentBooks = $author->books;  // Lazy loads books
    echo "  Books: " . count($recentBooks) . "\n";
}</code></pre>

    <h2 class="mt-4">Key Differences: Model vs Standalone</h2>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Aspect</th>
                    <th>Through Model</th>
                    <th>Standalone Query</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Return Type</strong></td>
                    <td>Model instance with results</td>
                    <td>Array of stdClass or scalar</td>
                </tr>
                <tr>
                    <td><strong>Navigation</strong></td>
                    <td>forEach, next(), prev(), array access</td>
                    <td>Array iteration only</td>
                </tr>
                <tr>
                    <td><strong>Relationships</strong></td>
                    <td>Lazy loading supported</td>
                    <td>Not available</td>
                </tr>
                <tr>
                    <td><strong>whereHas()</strong></td>
                    <td>✅ Available</td>
                    <td>❌ Not available</td>
                </tr>
                <tr>
                    <td><strong>Data Formatting</strong></td>
                    <td>Automatic (DateTime, etc.)</td>
                    <td>Raw database values</td>
                </tr>
                <tr>
                    <td><strong>Execution</strong></td>
                    <td>Automatic via getResults()</td>
                    <td>Manual via get() + db->query()</td>
                </tr>
                <tr>
                    <td><strong>Use Case</strong></td>
                    <td>✅ Recommended for most cases</td>
                    <td>Complex queries, performance optimization</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">See Also</h2>

    <div class="alert alert-success">
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-queries">Model Query Methods</a> - Query methods from Model perspective</li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-relationships">Relationships</a> - hasOne, belongsTo, hasMany, whereHas</li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-overview">Model Overview</a> - General Model concepts</li>
            <li><a href="?page=docs&action=Framework/Core/db-sql">Database</a> - Database connection and raw queries</li>
        </ul>
    </div>
</div>
