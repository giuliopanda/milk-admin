<?php
namespace Modules\Docs\Pages;
/**
 * @title CRUD Operations
 * @guide developer
 * @order 52
 * @tags model, CRUD, create, read, update, delete, store, save, fill, validate
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>CRUD Operations</h1>
    <p class="text-muted">Revision: 2025/10/13</p>
    <p class="lead">The Model provides comprehensive methods for Create, Read, Update, and Delete operations. There are two main approaches: the quick <code>store()</code> method and the classic <code>fill() + validate() + save()</code> workflow.</p>

    <div class="alert alert-info">
        <strong>💡 Two Approaches:</strong>
        <ul class="mb-0">
            <li><strong>Quick Method:</strong> <code>store($data, $id)</code> - Direct save without validation</li>
            <li><strong>Classic Method:</strong> <code>fill() + validate() + save()</code> - With full validation support</li>
        </ul>
    </div>

    <h2 class="mt-4">READ Operations</h2>

    <h3 class="mt-3"><code>getById($id, bool $use_cache = true): ?static</code></h3>
    <p>Retrieves a single record by primary key. Returns a Model instance with one record or null if not found.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param mixed $id Primary key value
 * @param bool $use_cache Whether to use internal cache
 * @return static|null Model instance with record, or null if not found
 */
public function getById($id, bool $use_cache = true): ?static;

// Example: Get product by ID
$product = $model->getById(123);

if ($product && $product->count() > 0) {
    echo "Product: " . $product->name;
    echo "Price: €" . $product->price;
    echo "Stock: " . ($product->in_stock ? 'Yes' : 'No');
} else {
    echo "Product not found";
}

// Example: Disable cache
$product = $model->getById(123, false);</code></pre>

    <div class="alert alert-warning">
        <strong>⚠️ Always Check Results:</strong> Even though getById() can return null, always check both null and count() to ensure the record exists:
        <code>if ($product && $product->count() > 0)</code>
    </div>

    <h3 class="mt-3"><code>getByIds(string|array $ids): ?static</code></h3>
    <p>Retrieves multiple records by their IDs. Accepts an array of IDs or a comma-separated string.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string|array $ids Array of IDs or comma-separated string
 * @return static|null Model instance with records
 */
public function getByIds(string|array $ids): ?static;

// Example: Array of IDs
$products = $model->getByIds([1, 5, 10, 15]);

echo "Found: " . $products->count() . " products\n";
foreach ($products as $product) {
    echo "- " . $product->name . "\n";
}

// Example: Comma-separated string
$products = $model->getByIds('1,5,10,15');</code></pre>

    <h3 class="mt-3"><code>getByIdAndUpdate($id, array $merge_data = []): static</code></h3>
    <p>Retrieves a record by ID or returns an empty object if not found. Useful for edit forms.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param mixed $id Primary key value
 * @param array $merge_data Data to merge with the record
 * @return static Model instance (existing record or empty)
 */
public function getByIdAndUpdate($id, array $merge_data = []): static;

// Example: Edit form
$id = $_GET['id'] ?? 0;
$product = $model->getByIdAndUpdate($id, $_POST);

// If ID exists and found: returns that record with $_POST merged
// If ID is 0 or not found: returns empty object with $_POST data

// Now you can use it directly in forms
echo $product->name;    // Works for both new and existing records
echo $product->price;   // Works for both new and existing records</code></pre>

    <h3 class="mt-3"><code>getEmpty(array $data = []): static</code></h3>
    <p>Returns an empty Model instance for creating new records.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param array $data Optional data to initialize the object
 * @return static Empty Model instance
 */
public function getEmpty(array $data = []): static;

// Example: Create new record
$product = $model->getEmpty([
    'name' => 'New Product',
    'price' => 29.99,
    'in_stock' => true
]);

// Example: From POST data
$product = $model->getEmpty($_POST);</code></pre>

    <h2 class="mt-4">CREATE & UPDATE Operations</h2>

    <h3 class="mt-3">Method 1: Quick Save with <code>store()</code></h3>
    <p>The fastest way to save data - directly inserts or updates without validation.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param array $data Data to save
 * @param mixed $id Primary key for UPDATE (null for INSERT)
 * @return bool|int Insert ID on success, false on failure
 */
public function store(array $data, $id = null): bool|int;

// Example: INSERT new record
$id = $model->store([
    'name' => 'Laptop Pro',
    'price' => 1299.99,
    'description' => 'High-performance laptop',
    'in_stock' => true,
    'created_at' => date('Y-m-d H:i:s')
]);

if ($id) {
    echo "Product created with ID: $id";
} else {
    echo "Error: " . $model->getLastError();
}

// Example: UPDATE existing record
$result = $model->store([
    'name' => 'Laptop Pro - Updated',
    'price' => 1199.99
], 123);  // Pass ID as second parameter

if ($result) {
    echo "Product updated successfully";
}

// Example: From POST data
$id = $_POST['id'] ?? null;
$result = $model->store($_POST, $id);

if ($result) {
    if ($id) {
        echo "Updated successfully";
    } else {
        echo "Created with ID: $result";
    }
}</code></pre>

    <div class="alert alert-info">
        <strong>💡 When to use store():</strong> Use when you don't need validation, or when data is already validated. Perfect for API endpoints or bulk operations.
    </div>

    <h3 class="mt-3">Method 2: Classic Workflow with <code>fill() + validate() + save()</code></h3>
    <p>The recommended approach for forms with validation requirements.</p>

    <h4>Step 1: Get or Create Object</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// For new record
$product = $model->getEmpty();

// For existing record
$product = $model->getById($id);

// For edit form (new or existing)
$product = $model->getByIdAndUpdate($id);</code></pre>

    <h4>Step 2: Fill with Data</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * Fills the model with data
 * @param array $data Data to fill
 * @return void
 */
public function fill(array $data): void;

// Example: Fill from POST
$product->fill($_POST);

// Example: Fill from array
$product->fill([
    'name' => 'Product Name',
    'price' => 29.99,
    'in_stock' => true
]);

// Example: Fill specific fields
$product->fill([
    'price' => 24.99,
    'description' => 'Updated description'
]);</code></pre>

    <h4>Step 3: Validate</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * Validates data according to rules defined in configure()
 * @param bool $validate_all Validate all records in result set
 * @return bool True if valid, false if validation fails
 */
public function validate(bool $validate_all = false): bool;

// Example: Validate before save
if ($product->validate()) {
    echo "Data is valid";
} else {
    echo "Validation failed";
    // Errors are stored in MessagesHandler
    $errors = MessagesHandler::getErrors();
}</code></pre>

    <h4>Step 4: Save</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * Saves the model data to database
 * @return bool True on success, false on failure
 */
public function save(): bool;

// Example: Complete workflow
$product = $model->getEmpty($_POST);

if ($product->validate()) {
    if ($product->save()) {
        $id = $product->getLastInsertId();
        echo "Saved successfully with ID: $id";
    } else {
        echo "Save error: " . $product->getLastError();
    }
} else {
    echo "Validation failed";
    foreach (MessagesHandler::getErrors() as $error) {
        echo "- $error\n";
    }
}</code></pre>

    <h3>Complete Example: Edit Form Handler</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function actionSaveProduct() {
    $id = $_POST['id'] ?? 0;

    // Get existing or create new
    $product = $this->model->getByIdAndUpdate($id, $_POST);

    // Validate
    if (!$product->validate()) {
        // Redirect back with errors
        Route::redirectError(
            $_POST['url_error'],
            'Validation failed',
            $_POST
        );
        return;
    }

    // Save
    if ($product->save()) {
        // Get ID for new records
        if ($id == 0) {
            $id = $product->getLastInsertId();
        }

        Route::redirectSuccess(
            '?page=products&action=edit&id=' . $id,
            'Product saved successfully'
        );
    } else {
        Route::redirectError(
            $_POST['url_error'],
            'Error: ' . $this->model->getLastError(),
            $_POST
        );
    }
}</code></pre>

    <h2 class="mt-4">Batch Operations</h2>

    <h3>Multiple Records with <code>fill()</code></h3>
    <p>You can fill multiple records and save them in batch:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$model = new ProductsModel();

// Fill multiple records
$model->fill(['name' => 'Product 1', 'price' => 10]);
$model->fill(['name' => 'Product 2', 'price' => 20]);
$model->fill(['name' => 'Product 3', 'price' => 30]);

// Save all at once
if ($model->save()) {
    $results = $model->getCommitResults();

    echo "Saved " . count($results) . " records:\n";
    foreach ($results as $result) {
        if ($result['result']) {
            echo "- ID: {$result['id']}, Action: {$result['action']}\n";
        } else {
            echo "- Error: {$result['last_error']}\n";
        }
    }
}</code></pre>

    <h2 class="mt-4">DELETE Operations</h2>

    <h3 class="mt-3"><code>delete($id): bool</code></h3>
    <p>Deletes a record by primary key.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param mixed $id Primary key value
 * @return bool True on success, false on failure
 */
public function delete($id): bool;

// Example: Delete single record
if ($model->delete(123)) {
    echo "Product deleted";
} else {
    echo "Delete failed: " . $model->getLastError();
}

// Example: Delete with confirmation
$id = $_GET['id'] ?? 0;

$product = $model->getById($id);
if (!$product || $product->count() == 0) {
    echo "Product not found";
    exit;
}

if ($model->delete($id)) {
    // Verify deletion
    $verify = $model->getById($id);
    if (!$verify || $verify->count() == 0) {
        echo "Product deleted successfully";
    }
}

// Example: In module action
protected function tableActionDeleteProduct($id, $request) {
    if ($this->model->delete($id)) {
        return true;
    } else {
        MessagesHandler::addError($this->model->getLastError());
        return false;
    }
}</code></pre>

    <h2 class="mt-4">Data Formatting</h2>

    <p>The Model automatically handles data type conversions. You can control the format of data:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$product = $model->getById(1);

// Set formatted mode (for display)
$product->setOutputMode('formatted');
echo $product->created_at;  // DateTime object

// Set SQL mode (for database)
$product->setOutputMode('sql');
echo $product->created_at;  // '2025-01-15 10:30:00' string

// Set raw mode
$product->setOutputMode('raw');
echo $product->created_at;  // Original value

// Get as array
$data = $product->toArray();           // Formatted
$data = $product->toArray('sql');      // SQL format
$data = $product->toArray('raw');      // Raw format</code></pre>

    <h2 class="mt-4">Utility Methods</h2>

    <h3><code>getLastInsertId(): int</code></h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// After INSERT
$id = $model->store($data);
// or
$model->save();
$id = $model->getLastInsertId();</code></pre>

    <h3><code>getLastError(): string</code></h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (!$model->store($data)) {
    echo "Error: " . $model->getLastError();
}</code></pre>

    <h3><code>hasError(): bool</code></h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$model->save();
if ($model->hasError()) {
    echo "An error occurred";
}</code></pre>

    <h3><code>getCommitResults(): array</code></h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// After save(), get detailed results
$results = $model->getCommitResults();

foreach ($results as $result) {
    echo "ID: {$result['id']}\n";
    echo "Action: {$result['action']}\n";  // 'insert' or 'edit'
    echo "Result: " . ($result['result'] ? 'success' : 'failed') . "\n";
    if (!$result['result']) {
        echo "Error: {$result['last_error']}\n";
    }
}</code></pre>

    <h2 class="mt-4">Best Practices</h2>

    <div class="alert alert-success">
        <strong>✅ Recommendations:</strong>
        <ul class="mb-0">
            <li>Use <code>store()</code> for simple, already-validated data</li>
            <li>Use <code>fill() + validate() + save()</code> for form submissions</li>
            <li>Always check <code>count() > 0</code> after <code>getById()</code></li>
            <li>Use <code>getByIdAndUpdate()</code> for edit forms to handle both new and existing records</li>
            <li>Check <code>getLastError()</code> when operations fail</li>
            <li>Use transactions for multiple related operations (see Database docs)</li>
        </ul>
    </div>

    <h2 class="mt-4">See Also</h2>

    <div class="alert alert-info">
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-overview">Model Overview</a> - General concepts</li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-queries">Query Builder</a> - Building complex queries</li>
            <li><a href="?page=docs&action=Framework/Core/schema">Schema</a> - Table management</li>
        </ul>
    </div>
</div>
