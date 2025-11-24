<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Abstract Model - Overview
 * @guide developer
 * @order 50
 * @tags AbstractModel, model, database, overview, configure, traits, architecture
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract Model - Overview</h1>
    <p class="text-muted">Revision: 2025/10/13</p>
    <p class="lead">The <code>AbstractModel</code> class is the foundation for all data models in MilkAdmin. It provides a powerful and intuitive interface for interacting with database tables using a fluent query builder and comprehensive CRUD operations.</p>

    <div class="alert alert-info">
        <strong>💡 New Architecture:</strong> The Model has been completely refactored using traits for better organization and maintainability:
        <ul class="mb-0">
            <li><strong>QueryBuilderTrait:</strong> Query building (<code>where</code>, <code>whereIn</code>, <code>whereHas</code>, <code>order</code>, <code>limit</code>)</li>
            <li><strong>CrudOperationsTrait:</strong> CRUD operations (<code>getById</code>, <code>store</code>, <code>delete</code>)</li>
            <li><strong>SchemaAndValidationTrait:</strong> Schema and validation (<code>buildTable</code>, <code>validate</code>)</li>
            <li><strong>RelationshipsTrait:</strong> Relationships (<code>hasOne</code>, <code>belongsTo</code>, <code>hasMany</code>)</li>
            <li><strong>CollectionTrait:</strong> Result set navigation and iteration</li>
        </ul>
    </div>

    <h2 class="mt-4">Defining a Model</h2>

    <p>To create a model, extend <code>AbstractModel</code> and implement the <code>configure()</code> method where you define your table structure using a fluent interface:</p>

    <pre class="language-php"><code>namespace Modules\Products;
use App\Abstracts\AbstractModel;

class ProductsModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('#__products')
            ->id()                                    // Auto-increment primary key
            ->string('name', 100)->required()         // VARCHAR(100) NOT NULL
            ->decimal('price', 10, 2)->default(0)     // DECIMAL(10,2) DEFAULT 0
            ->text('description')->nullable()         // TEXT NULL
            ->boolean('in_stock')->default(true)      // TINYINT(1) DEFAULT 1
            ->int('category_id')->index()             // INT with INDEX
            ->created_at()                            // DATETIME
            ->datetime('updated_at')->nullable();     // DATETIME NULL
    }
}</code></pre>

    <div class="alert alert-warning mt-3">
        <strong>⚠️ Important:</strong> The <code>#__</code> prefix in table names is automatically replaced with the actual database prefix configured in your settings.
    </div>


    <h3>Standalone Usage</h3>
    <p>You can also instantiate and use models directly:</p>

    <pre class="language-php"><code>use Modules\Products\ProductsModel;

$products = new ProductsModel();

// Simple query
$allProducts = $products->getAll();

// Query with conditions
$cheapProducts = $products
    ->where('price < ?', [50])
    ->order('price', 'asc')
    ->getResults();</code></pre>

    <h2 class="mt-4">Key Concepts</h2>

    <h3>Query Builder Pattern</h3>
    <p>Most query methods return a <code>Query</code> instance, allowing you to chain multiple operations. You must call <code>getResults()</code> or <code>getRow()</code> to execute the query and get back a Model:</p>

    <pre class="language-php"><code>// Query methods return Query instance for chaining
$results = $model
    ->where('status = ?', ['active'])     // Returns Query
    ->whereIn('category_id', [1, 2, 3])   // Returns Query
    ->order('created_at', 'desc')         // Returns Query
    ->limit(0, 10)                        // Returns Query
    ->getResults();                       // Executes query → Returns Model with multiple records

// For single record
$product = $model
    ->where('id = ?', [1])
    ->getRow();                           // Executes query → Returns Model with single record (isEmpty to check if record exists)</code></pre>

    <div class="alert alert-info mt-3">
        <strong>📖 Query Execution Methods:</strong>
        <ul class="mb-0">
            <li><code>getResults()</code> - Executes the query and returns a Model with multiple records (even if 0 or 1)</li>
            <li><code>getRow()</code> - Executes the query and returns a Model with a single record, or null if not found</li>
            <li><code>getVar()</code> - Executes the query and returns a single value (useful for COUNT, SUM, etc.)</li>
        </ul>
        <p class="mb-0 mt-2">💡 <strong>Learn more:</strong> See <a href="?page=docs&action=Developer/AbstractsClass/abstract-model-queries">Query Builder Methods</a> for complete documentation.</p>
    </div>

    <h3>Result Set Navigation</h3>
    <p>Query execution returns a Model instance containing the results. You can navigate through records using multiple approaches:</p>

    <pre class="language-php"><code>$products = $model->where('in_stock = ?', [true])->getResults();

// Approach 1: Iterator (foreach)
foreach ($products as $product) {
    echo $product->name . ": €" . $product->price . "\n";
}

// Approach 2: Manual navigation
while ($products->hasNext()) {
    echo $products->name;
    $products->next();
}

// Approach 3: Array access
$firstProduct = $products[0];
$secondProduct = $products[1];</code></pre>

    <h3>Data Formatting</h3>
    <p>The Model provides three different data formats for flexible data handling:</p>

    <pre class="language-php"><code>$product = $model->getById(1);

// RAW format: DateTime objects, PHP arrays (default)
$rawDate = $product->created_at;              // Returns DateTime object
$product->setRaw();                           // Set mode permanently
echo $product->created_at->format('Y-m-d');   // 2024-01-15

// FORMATTED format: Human-readable strings
$formattedDate = $product->getFormatted('created_at');  // "15/01/2024 14:30"
$product->setFormatted();                     // Set mode permanently
echo $product->created_at;                    // "15/01/2024 14:30"

// SQL format: MySQL-compatible strings
$sqlDate = $product->getSql('created_at');    // "2024-01-15 14:30:00"
$product->setSql();                           // Set mode permanently

// Get all data in different formats
$allRaw = $products->getRawData('array', true);           // All records as arrays
$allFormatted = $products->getFormattedData('object', true);  // All records as objects
$allSql = $products->getSqlData('array', true);          // All records ready for SQL</code></pre>

    <div class="alert alert-info mt-3">
        <strong>💡 Tip:</strong> Use <code>setFormatted()</code> when displaying data in views, <code>setRaw()</code> for business logic, and <code>setSql()</code> when preparing data for manual SQL operations.
    </div>

    <h3>Two Ways to Save Data</h3>

    <h4>1. Quick Method: store()</h4>
    <p>Direct save without validation - fast and simple:</p>

    <pre class="language-php"><code>// Insert new record
$id = $model->store([
    'name' => 'New Product',
    'price' => 29.99,
    'in_stock' => true
]);

// Update existing record
$model->store([
    'name' => 'Updated Name',
    'price' => 24.99
], $id);  // Pass ID as second parameter for UPDATE</code></pre>

    <h4>2. Classic Method: fill() + validate() + save()</h4>
    <p>With full validation support:</p>

    <pre class="language-php"><code>// Get empty object or existing record
$product = $model->getEmpty($_POST);
// or
$product = $model->getById($id);

// Fill with data new
$product->fill([
    'name' => 'Product Name',
    'price' => 29.99
]);
// Or Change attribute name of current object
$product->name = 'Products';

if ($product->validate()) {
    if ($product->save()) {
        $id = $product->getLastInsertId();
        echo "Saved with ID: $id";
    } else {
        echo "Save error: " . $product->getLastError();
    }
} else {
    // Validation failed - errors in MessagesHandler
    echo "Validation failed";
}</code></pre>

<h3>Attributes</h3>
<p>You can manage the operations of individual fields by creating methods preceded by php 8+ attributes</p>
<p>See <a href="?page=docs&action=Developer/AbstractsClass/abstract-model-attributes">Attributes</a> for more details</p>

    
    <h2 class="mt-4">Next Steps</h2>

    <div class="alert alert-success">
        <strong>📚 Explore More:</strong>
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-model">Getting Started with Models</a> - Beginner tutorial</li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-queries">Query Builder Methods</a> - Advanced queries</li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-crud">CRUD Operations</a> - Detailed CRUD documentation</li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-relationships">Relationships</a> - hasOne, belongsTo, hasMany</li>
            <li><a href="?page=docs&action=Framework/Core/schema">Schema</a> - Table schema management</li>
        </ul>
    </div>
</div>
