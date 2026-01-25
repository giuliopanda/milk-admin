<?php
namespace Modules\Docs\Pages;
/**
 * @title Model Attributes
 * @guide developer
 * @order 40
 * @tags model, attributes, ToDisplayValue, ToDatabaseValue, SetValue, Validate, formatting, custom handlers
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Model Attributes</h1>
    <p class="text-muted">Revision: 2025/12/25</p>
    <p class="lead">Model attributes allow you to define custom handlers for formatting, transforming, validating field values, and building reusable query scopes. Using PHP 8 attributes, you can attach custom methods to specific fields and queries to control how data is displayed, stored, validated, and filtered.</p>

    <h2 class="mt-4">Quick Reference</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Attribute</th>
                    <th>Method Parameters</th>
                    <th>Method Return Value</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>#[ToDisplayValue(field_name)]</code></td>
                    <td>object $current_record</td>
                    <td>Return the formatted value</td>
                    <td>Called when accessing a field in <code>formatted</code> mode (for display)</td>
                </tr>
                <tr>
                    <td><code>#[ToDatabaseValue(field_name)]</code></td>
                    <td>object $current_record</td>
                    <td>Return the transformed value to store in the database</td>
                    <td>Called when preparing data for database storage</td>
                </tr>
                <tr>
                    <td><code>#[SetValue(field_name)]</code></td>
                    <td>array $current_record, mixed $value</td>
                     <td>Return the transformed value</td>
                    <td>Called when assigning a value to a field (via <code>fill()</code> or <code>$model->field = value</code>)</td>
                </tr>
                <tr>
                    <td><code>#[Validate(field_name)]</code></td>
                    <td>object $current_record</td>
                    <td>Return true if valid, or error message string if invalid<</td>
                    <td>Called during <code>validate()</code> operation</td>
                </tr>
                <tr>
                    <td><code>#[DefaultQuery]</code></td>
                    <td>Query $query</td>
                    <td>Return the modified Query object</td>
                    <td>Automatically applied to all SELECT queries (persistent)</td>
                </tr>
                <tr>
                    <td><code>#[Query('name')]</code></td>
                    <td>Query $query</td>
                    <td>Return the modified Query object</td>
                    <td>Named query scope, applied on-demand with <code>withQuery('name')</code> (temporary)</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">How Attributes Work</h2>

    <p>When you create a Model instance, the <code>AbstractModel</code> automatically scans all public methods in your Model class looking for these attributes. When found, it registers them as handlers for specific fields and operations.</p>

    <h2 class="mt-4">#[ToDisplayValue] - Custom Display Formatting</h2>

    <p>Use this attribute to define how a field should be displayed to users. The formatted value is used when you access fields after calling <code>setOutputMode('formatted')</code> or when using <code>getFormattedData()</code>.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDisplayValue('field_name')]
public function methodName($current_record_obj): mixed
{
    // $current_record_obj is an object containing all fields of the current record
    // Return the formatted value
}</code></pre>

    <h3>Example: Combining Multiple Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDisplayValue('full_name')]
public function getFormattedFullName($current_record_obj) {
    $parts = [];
    if (!empty($current_record_obj->first_name)) {
        $parts[] = $current_record_obj->first_name;
    }
    if (!empty($current_record_obj->last_name)) {
        $parts[] = $current_record_obj->last_name;
    }
    return implode(' ', $parts);
}

// Note: 'full_name' doesn't need to be a real database field
// It can be a virtual field computed from other fields</code></pre>



    <h2 class="mt-4">#[ToDatabaseValue] - Custom SQL Value</h2>

    <p>Use this attribute to control how a field value is prepared before saving to the database. This is useful for data transformation or encoding before storage.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDatabaseValue('field_name')]
public function methodName($current_record_obj): mixed
{
    // $current_record_obj is an object containing all fields
    // Return the value ready for SQL storage
}</code></pre>

    <h3>Example: Encrypt Before Saving</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDatabaseValue('credit_card')]
public function getSqlCreditCard($current_record_obj) {
    if (isset($current_record_obj->credit_card) && !empty($current_record_obj->credit_card)) {
        // Encrypt the credit card number before saving
        return openssl_encrypt(
            $current_record_obj->credit_card,
            'AES-256-CBC',
            ENCRYPTION_KEY,
            0,
            ENCRYPTION_IV
        );
    }
    return null;
}</code></pre>

    <h2 class="mt-4">#[SetValue] - Custom Value Transformation</h2>

    <p>Use this attribute to transform or sanitize values when they are assigned to a field. This is called automatically when you use <code>fill()</code> or direct assignment.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('field_name')]
public function methodName($current_record_array, $value): mixed
{
    // $current_record_array is an array with all current field values
    // $value is the incoming value being set
    // Return the transformed value
}</code></pre>

    <h3>Example: Clean Phone Number</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('phone')]
public function setPhoneValue($current_record_array, $value) {
    // Remove all non-numeric characters
    return preg_replace('/[^0-9+]/', '', $value);
}

// Input: "(555) 123-4567"
// Stored: "5551234567"</code></pre>

    <h2 class="mt-4">#[Validate] - Custom Field Validation</h2>

    <p>Use this attribute to define custom validation logic for a specific field. This is called during the <code>validate()</code> operation.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('field_name')]
public function methodName($current_record): bool|string
{
    // $current_record is an object with all current record 
    // Return true if valid, or error message string if invalid
}</code></pre>

    <h3>Example: Cross-Field Validation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('end_date')]
public function validateEndDate($current_record) {
    $value = $current_record->end_date;
    if (empty($value)) {
        return true; // Optional field
    }

    // Access other fields through the model
    $start_date = $current_record->start_date;

    if (empty($start_date)) {
        return "Start date must be set before end date";
    }

    if ($value < $start_date) {
        return "End date must be after start date";
    }

    return true;
}</code></pre>

    <h2 class="mt-4">Working with Relationships</h2>

    <p>You can also use attributes to format or handle relationship fields. Use the notation <code>"relationship_alias.field_name"</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class AppointmentsModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('#__appointments')
            ->id()
            ->int('doctor_id')
            ->belongsTo('doctor', DoctorsModel::class, 'doctor_id');
    }

    // Format the related doctor's name
    #[ToDisplayValue('doctor.name')]
    public function getFormattedDoctorName($current_record_obj) {
        if (isset($current_record_obj->doctor->name)) {
            return 'Dr. ' . $current_record_obj->doctor->name;
        }
        return '';
    }
}

// Usage
$appointment = $model->include('doctor')->getById(1);
$appointment->setOutputMode('formatted');
echo $appointment->doctor->name;  // "Dr. John Smith"</code></pre>

    <h2 class="mt-4">#[DefaultQuery] - Global Query Scopes</h2>

    <p>Use this attribute to define query scopes that are automatically applied to <strong>all</strong> SELECT queries on the model. Default queries are persistent and remain active until explicitly disabled.</p>

    <p><strong>Use cases:</strong></p>
    <ul>
        <li>Soft deletes: automatically filter out deleted records</li>
        <li>Multi-tenancy: filter records by tenant ID</li>
        <li>Status filters: show only active/published records</li>
        <li>Security: apply row-level security filters</li>
    </ul>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[DefaultQuery]
protected function methodName($query): Query
{
    // $query is the Query object being built
    // Return the modified Query object
}</code></pre>

    <h3>Example: Soft Deletes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class OrdersModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('orders')
            ->id()
            ->string('status', 20)
            ->datetime('deleted_at');
    }

    // This query scope is applied to ALL queries automatically
    #[DefaultQuery]
    protected function onlyActive($query) {
        return $query->where('deleted_at IS NULL');
    }
}

// Usage:
$orders = $model->getAll();  // Only non-deleted orders
// SQL: SELECT * FROM orders WHERE deleted_at IS NULL

$orders = $model->where('status = ?', ['completed'])->getResults();
// SQL: SELECT * FROM orders WHERE deleted_at IS NULL AND status = 'completed'</code></pre>

    <h3>Example: Multi-Tenancy</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class DocumentsModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('documents')
            ->id()
            ->int('tenant_id')
            ->string('title');
    }

    #[DefaultQuery]
    protected function filterByTenant($query) {
        $currentTenantId = getCurrentTenantId(); // Your auth logic
        return $query->where('tenant_id = ?', [$currentTenantId]);
    }
}

// All queries automatically filter by current tenant
$docs = $model->getAll();  // Only current tenant's documents</code></pre>

    <h3>Disabling Default Queries</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Disable a specific default query (persistent)
$orders = $model->withoutGlobalScope('onlyActive')->getAll();
// Returns ALL orders, including deleted

// Subsequent queries still have the scope disabled
$orders = $model->getAll();  // Still includes deleted orders

// Re-enable the scope
$model->enableGlobalScope('onlyActive');
$orders = $model->getAll();  // Back to only non-deleted orders

// Disable ALL default queries
$orders = $model->withoutGlobalScopes()->getAll();</code></pre>

    <h2 class="mt-4">#[Query('name')] - Named Query Scopes</h2>

    <p>Use this attribute to define reusable query constraints that can be applied <strong>on-demand</strong>. Named queries are temporary and only affect the current query.</p>

    <p><strong>Differences from DefaultQuery:</strong></p>
    <ul>
        <li><code>#[DefaultQuery]</code>: Applied automatically, persistent until disabled</li>
        <li><code>#[Query('name')]</code>: Applied manually with <code>withQuery('name')</code>, temporary (one query only)</li>
    </ul>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Query('scopeName')]
protected function methodName($query): Query
{
    // $query is the Query object being built
    // Return the modified Query object
}</code></pre>

    <h3>Example: Commonly Used Filters</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class OrdersModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('orders')
            ->id()
            ->string('status')
            ->decimal('total')
            ->datetime('created_at');
    }

    // Default: only active orders
    #[DefaultQuery]
    protected function onlyActive($query) {
        return $query->where('status != ?', ['cancelled']);
    }

    // Named scopes - apply on demand
    #[Query('recent')]
    protected function scopeRecent($query) {
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        return $query->where('created_at > ?', [$thirtyDaysAgo]);
    }

    #[Query('highValue')]
    protected function scopeHighValue($query) {
        return $query->where('total > ?', [1000]);
    }

    #[Query('pending')]
    protected function scopePending($query) {
        return $query->where('status = ?', ['pending']);
    }

    #[Query('ordered')]
    protected function scopeOrdered($query) {
        return $query->order('created_at', 'DESC');
    }
}

// Usage - apply named scopes when needed:
$orders = $model->getAll();
// Only default scope applied: active orders

$orders = $model->withQuery('recent')->getAll();
// Default + recent: active orders from last 30 days

$orders = $model->withQuery('highValue')->withQuery('ordered')->getAll();
// Default + highValue + ordered: active orders over $1000, sorted by date

// Named scopes are temporary - next query doesn't include them
$orders = $model->getAll();
// Back to just default scope: active orders</code></pre>

    <h3>Example: Complex Filtering</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class PostsModel extends AbstractModel
{
    #[DefaultQuery]
    protected function published($query) {
        return $query->where('status = ? AND publish_date <= ?',
                            ['published', date('Y-m-d H:i:s')]);
    }

    #[Query('featured')]
    protected function scopeFeatured($query) {
        return $query->where('is_featured = ?', [1]);
    }

    #[Query('byCategory')]
    protected function scopeByCategory($query) {
        // This is a dynamic scope - you can access model properties
        if (isset($this->filter_category)) {
            return $query->where('category_id = ?', [$this->filter_category]);
        }
        return $query;
    }
}

// Usage:
$model = new PostsModel();
$model->filter_category = 5;

$posts = $model->withQuery('featured')->withQuery('byCategory')->getAll();
// Published posts that are featured in category 5</code></pre>

    <h3>Combining with Manual Queries</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// You can combine scopes with manual where/order/limit
$orders = $model
    ->withQuery('recent')
    ->where('customer_id = ?', [123])
    ->order('total', 'DESC')
    ->limit(0, 10)
    ->getResults();

// SQL equivalent:
// SELECT * FROM orders
// WHERE status != 'cancelled'           -- default scope
//   AND created_at > '2024-11-25'       -- named scope 'recent'
//   AND customer_id = 123               -- manual where
// ORDER BY total DESC                    -- manual order
// LIMIT 0, 10                           -- manual limit</code></pre>

    <h2 class="mt-4">Query Scope Management Methods</h2>

    <p>The ScopeTrait provides several methods to inspect and manage query scopes:</p>

    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Returns</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>withoutGlobalScope('name')</code></td>
                    <td>Disable a default scope (persistent)</td>
                    <td>$this</td>
                </tr>
                <tr>
                    <td><code>withoutGlobalScopes()</code></td>
                    <td>Disable all default scopes (persistent)</td>
                    <td>$this</td>
                </tr>
                <tr>
                    <td><code>enableGlobalScope('name')</code></td>
                    <td>Re-enable a previously disabled scope</td>
                    <td>$this</td>
                </tr>
                <tr>
                    <td><code>withQuery('name')</code></td>
                    <td>Apply a named query scope (temporary, one query only)</td>
                    <td>$this</td>
                </tr>
                <tr>
                    <td><code>getDefaultQueries()</code></td>
                    <td>Get list of all registered default query scopes</td>
                    <td>array</td>
                </tr>
                <tr>
                    <td><code>getNamedQueries()</code></td>
                    <td>Get list of all registered named query scopes</td>
                    <td>array</td>
                </tr>
                <tr>
                    <td><code>getDisabledScopes()</code></td>
                    <td>Get list of currently disabled scopes</td>
                    <td>array</td>
                </tr>
                <tr>
                    <td><code>hasDefaultQuery('name')</code></td>
                    <td>Check if a default query exists</td>
                    <td>bool</td>
                </tr>
                <tr>
                    <td><code>hasNamedQuery('name')</code></td>
                    <td>Check if a named query exists</td>
                    <td>bool</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Example: Scope Inspection</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$model = new OrdersModel();

// Check what scopes are available
$defaultScopes = $model->getDefaultQueries();
// Returns: ['onlyActive']

$namedScopes = $model->getNamedQueries();
// Returns: ['recent', 'highValue', 'pending', 'ordered']

// Check if specific scope exists
if ($model->hasNamedQuery('recent')) {
    $orders = $model->withQuery('recent')->getAll();
}

// Check what's currently disabled
$model->withoutGlobalScope('onlyActive');
$disabled = $model->getDisabledScopes();
// Returns: ['onlyActive']</code></pre>

    <h2 class="mt-4">Best Practices</h2>

    <ul>
        <li><strong>Use DefaultQuery for:</strong> Security filters, soft deletes, multi-tenancy, status filtering that should ALWAYS apply</li>
        <li><strong>Use Query for:</strong> Common filters that users might want (recent, featured, by category), optional ordering</li>
        <li><strong>Naming:</strong> Use clear, descriptive names for scopes (<code>onlyActive</code>, <code>recent</code>, <code>highValue</code>)</li>
        <li><strong>Method naming:</strong> Prefix default scope methods with a verb (<code>onlyActive</code>, <code>filterByTenant</code>), named scopes can use <code>scope</code> prefix or descriptive names</li>
        <li><strong>Chaining:</strong> All scope methods return <code>$this</code>, so you can chain multiple scopes together</li>
        <li><strong>Testing:</strong> Remember that default scopes are persistent - use <code>withoutGlobalScopes()</code> in tests when you need all records</li>
        <li><strong>Performance:</strong> Query scopes are applied at query build time, not after fetching - they don't impact performance</li>
    </ul>

</div>