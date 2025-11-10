<?php
namespace Modules\Docs\Pages;
/**
 * @title Getting Started - Model
 * @guide developer
 * @order 20
 * @tags model, database, CRUD, tutorial, getting-started, beginner, AbstractModel, create-table, insert, update, delete, query
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Getting Started with Models</h1>
    <p class="text-muted">Revision: 2025/10/13</p>
    <p class="lead">This tutorial will guide you through the basic operations to create and use a Model in MilkAdmin: from table creation to data insertion, reading, updating, and deletion.</p>

    <div class="alert alert-info">
        <strong>💡 What you'll learn:</strong>
        <ul class="mb-0">
            <li>Create a Model and define the table schema</li>
            <li>Automatically generate the table in the database</li>
            <li>Insert new records (CREATE)</li>
            <li>Read data (READ)</li>
            <li>Update existing records (UPDATE)</li>
            <li>Delete records (DELETE)</li>
            <li>Drop a table</li>
        </ul>
    </div>

    <h2 class="mt-4">1. Creating a Model</h2>

    <p>A Model represents a table in the database. Let's start by creating a simple Model to manage products:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Abstracts\AbstractModel;

class ProductModel extends AbstractModel {
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

    <div class="alert alert-secondary mt-3">
        <strong>Schema explanation:</strong>
        <ul class="mb-0">
            <li><code>->id()</code> - Creates an auto-increment <code>id</code> column as primary key</li>
            <li><code>->string('name', 100)</code> - VARCHAR field of 100 characters</li>
            <li><code>->decimal('price', 10, 2)</code> - DECIMAL field for prices (10 total digits, 2 decimals)</li>
            <li><code>->text('description')</code> - TEXT field for long descriptions</li>
            <li><code>->boolean('in_stock')</code> - Boolean field (TINYINT)</li>
            <li><code>->datetime('created_at')</code> - DATETIME field for dates</li>
            <li><code>->nullable()</code> - Allows NULL values</li>
            <li><code>->required()</code> - Required field (NOT NULL)</li>
            <li><code>->default()</code> - Default value</li>
        </ul>
    </div>

    <h2 class="mt-4">2. Creating the Table</h2>

    <p>Once the Model is defined, you need to create the table in the database. The <strong>recommended</strong> way is to use CLI commands:</p>

    <div class="alert alert-success">
        <h5 class="alert-heading"><i class="bi bi-terminal"></i> Recommended Method: CLI Commands</h5>

        <p><strong>First module installation:</strong></p>
        <pre class="mb-2"><code class="language-bash">php milkadmin/cli.php products:install</code></pre>
        <p class="mb-3">This command creates the table and executes <code>afterCreateTable()</code> to insert initial data.</p>

        <p><strong>After Model schema changes:</strong></p>
        <pre class="mb-2"><code class="language-bash">php milkadmin/cli.php products:update</code></pre>
        <p class="mb-0">This command updates the table structure without executing <code>afterCreateTable()</code>.</p>
    </div>

    <div class="alert alert-warning">
        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Important</h5>
        <p class="mb-0"><strong>Table creation/update is NOT automatic!</strong> After creating or modifying a Model, you must always manually run the CLI <code>install</code> or <code>update</code> command.</p>
    </div>

    <h3>Alternative Method: buildTable() via Code</h3>
    <p>For quick tests or special situations, you can call <code>buildTable()</code> directly in your code:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$products = new ProductModel();

// Create the table if it doesn't exist, or update it if it does
if ($products->buildTable()) {
    echo "Table created/updated successfully!";
} else {
    echo "Error: " . $products->getLastError();
}</code></pre>

    <p class="alert alert-info"><strong>Note:</strong> The <code>buildTable()</code> method is smart: if the table doesn't exist it creates it (and executes <code>afterCreateTable()</code>), if it already exists it automatically updates it to reflect any schema changes (without executing <code>afterCreateTable()</code>).</p>

    <div class="alert alert-secondary">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> When to use buildTable() in code</h5>
        <p class="mb-0"><strong>Use buildTable() via code only for:</strong></p>
        <ul class="mb-0">
            <li>Testing and rapid development</li>
            <li>Custom installation scripts</li>
            <li>Modules that create tables dynamically</li>
        </ul>
        <p class="mt-2 mb-0"><strong>For production modules, always use CLI commands!</strong></p>
    </div>

    <h2 class="mt-4">3. Inserting Data (CREATE)</h2>

    <p>To insert a new record use the <code>store()</code> method:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if(!$products->store([
    'name' => 'Laptop Pro',
    'price' => 1299.99,
    'description' => 'High-performance laptop',
    'in_stock' => true,
    'created_at' => date('Y-m-d H:i:s')
])) {
    MessagesHandler::addError($products->getLastError());
}</code></pre>

    <h2 class="mt-4">4. Reading Data (READ)</h2>

    <h3>Reading all records</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $products->getAll();

echo "Found " . $result->count() . " products:\n";

while ($result->hasNext()) {
    echo "- " . $result->name . ": €" . $result->price . "\n";
    $result->next();
}</code></pre>

    <h3>Reading a single record by ID</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$product = $products->getById(1);

if (!$product->isEmpty()) {
    echo "Name: " . $product->name;
    echo "Price: €" . $product->price;
}</code></pre>

<h3>Reading multiple records</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$products = $products->getByIds([1, 2, 3]);</code></pre>
    
    <h3>Query with conditions</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Find available products with price < 100
$list_of_products = $products->where('in_stock = ?', [true])
                   ->where('price < ?', [100])
                   ->order('price', 'asc')
                   ->getResults();

foreach ($list_of_products as $product) {
    echo $product->name . ": €" . $product->price . "\n";
}</code></pre>
 <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Find available products with price < 100
$singleProduct = $products->where('in_stock = ?', [true])
                   ->where('price < ?', [100])
                   ->order('price', 'asc')
                   ->getRow();
echo $singleProduct->name . ": €" . $singleProduct->price;
</code></pre>
    <h2 class="mt-4">5. Updating Data (UPDATE)</h2>

    <p>To update an existing record, pass the ID as the second parameter to <code>store()</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$update_data = [
    'price' => 1199.99,
    'description' => 'High-performance laptop - SALE!'
];

$result = $products->store($update_data, 1);
}</code></pre>

    <h2 class="mt-4">6. Deleting Data (DELETE)</h2>

    <p>To delete a record use the <code>delete()</code> method:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $products->delete(1);

if ($result) {
    echo "Product deleted!";
    echo "Remaining products: " . $products->total();
} else {
    echo "Error: " . $products->getLastError();
}</code></pre>

    <h2 class="mt-4">7. Dropping the Table</h2>

    <p>To completely remove the table from the database:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$result = $products->dropTable();

if ($result) {
    echo "Table dropped successfully!";
}</code></pre>

    <h2 class="mt-4">8. Installing the table</h2>
    <p>To install or update the tables of a module you are creating, you can use: 
    <pre><code class="language-shell">php milkadmin/cli.php module:install
php milkadmin/cli.php module:update
    </code></pre>
    </p>
    
   
    <h2 class="mt-4">Next Steps</h2>

    <div class="alert alert-success">
        <strong>🎉 Congratulations!</strong> Now you know how to use MilkAdmin Models for basic CRUD operations.

        <p class="mt-3 mb-0"><strong>To learn more:</strong></p>
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model">Abstract Model</a> - Complete Model documentation</li>
            <li><a href="?page=docs&action=Framework/Core/query">Query Builder</a> - Advanced and complex queries</li>
            <li><a href="?page=docs&action=Framework/Core/schema">Schema</a> - Advanced table management</li>
        </ul>
    </div>
</div>