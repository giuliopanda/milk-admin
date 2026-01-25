<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Abstract Model
 * @guide developer
 * @order 35
 * @tags AbstractModel, model, database, query, SQL, MySQL, get_by_id, get_by_id_or_empty, get_empty, get_by_id_for_edit, save, delete, where, order, limit, select, from, group, get, execute, get_all, first, total, build_table, drop_table, validate, clear_cache, get_last_error, has_error, set_query_params, get_filtered_columns, get_columns, add_filter, object_class, primary_key, table, CRUD, query-builder, fluent-interface, pagination, sorting, filtering, validation, schema, to_mysql_array, filter_data_by_rules, get_last_insert_id, bind_params, SQL-injection, registerVirtualTable, ArrayDb, virtual_table
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract Model Class</h1>
    <p class="text-muted">Revision: 2025/11/24</p>
    <p>The <code>AbstractModel</code> abstract class is a base class for managing module data. It provides a fluent interface for building queries and performing CRUD operations on MySQL tables.</p>

    <h2 class="mt-4">Defining a Model</h2>

    <div class="alert alert-info">
        <strong>Table Structure Documentation:</strong>
        For complete information about defining table structures and using the RuleBuilder, see the <a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-rulebuilder'); ?>">RuleBuilder documentation</a>.
    </div>

    <p>To create a model, extend <code>AbstractModel</code> and implement the <code>configure()</code> method:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules\Products;
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

    <div class="alert alert-info">
        <strong>📋 Quick Reference:</strong> This table provides a complete overview of all available methods. Click on a method name to jump to its detailed documentation.
    </div>

    <h3 class="mt-3">Understanding Query Builder and Execution</h3>

    <div class="alert alert-primary">
        <h5>🔄 Model vs Query Return Types</h5>
        <p>The framework uses a dual execution system where methods return different types based on the calling context:</p>

        <h6 class="mt-3">Query Builder Methods (where, order, limit, etc.)</h6>
        <p>These methods return a <code>Query</code> object, <strong>not a Model</strong>. You are temporarily leaving the Model context:</p>
        <pre><code class="language-php">$query = $model->where('status = ?', ['active'])->order('name');
// $query is a Query object, not a Model</code></pre>

        <h6 class="mt-3">Execution Methods (getAll, get, getResults, getRow, getVar)</h6>
        <p><strong>Important:</strong> These methods behave differently depending on how they are called:</p>

        <table class="table table-sm table-bordered mt-2">
            <thead>
                <tr>
                    <th>Called from</th>
                    <th>Return Type</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Model</strong><br><code>$model->getAll()</code></td>
                    <td><code>Model</code></td>
                    <td>The Query automatically receives the Model class via <code>setModelClass()</code></td>
                </tr>
                <tr>
                    <td><strong>Query Builder Chain</strong><br><code>$model->where(...)->getAll()</code></td>
                    <td><code>Model</code></td>
                    <td>The Model class is propagated through the query chain</td>
                </tr>
                <tr>
                    <td><strong>Standalone Query</strong><br><code>$query->getAll()</code></td>
                    <td><code>array</code></td>
                    <td>No Model class was set, returns raw database array</td>
                </tr>
            </tbody>
        </table>

        <h6 class="mt-3">Example</h6>
        <pre><code class="language-php">// Case 1: Called from Model → returns Model
$products = $model->getAll();
foreach ($products as $product) {
    echo $product->name; // Model instance
}

// Case 2: Query builder chain → returns Model
$products = $model->where('price > ?', [10])->getAll();
// Still returns Model because setModelClass() was set automatically

// Case 3: Standalone Query → returns array
$query = new Query($db);
$results = $query->from('products')->getAll();
// Returns array because no Model was associated</code></pre>

        <p class="mb-0"><strong>Key Takeaway:</strong> When using the Model API (which is the recommended approach), execution methods always return <code>Model</code> instances. They only return <code>array</code> when using standalone Query objects directly.</p>
    </div>

    <h3 class="mt-3">Methods Summary</h3>

    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th style="width: 20%">Method</th>
                    <th style="width: 30%">Description</th>
                    <th style="width: 15%">Returns</th>
                    <th style="width: 35%">Example</th>
                </tr>
            </thead>
            <tbody>
                <!-- CRUD Operations -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>CRUD Operations</strong></td>
                </tr>
                <tr>
                    <td><code><a href="#fill">fill()</a></code></td>
                    <td>Fill model with data from array/object</td>
                    <td><span class="badge bg-success">Model</span></td>
                    <td><code>$model->fill(['name' => 'Test'])</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getEmpty">getEmpty()</a></code></td>
                    <td>Get empty model instance for creating new records</td>
                    <td><span class="badge bg-warning">object</span></td>
                    <td><code>$model->getEmpty(['status' => 1])</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getById">getById()</a></code></td>
                    <td>Retrieve single record by primary key (use isEmpty() to check)</td>
                    <td><span class="badge bg-success">Model</span></td>
                    <td><code>$model->getById(1)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getByIds">getByIds()</a></code></td>
                    <td>Retrieve multiple records by IDs (use isEmpty() to check)</td>
                    <td><span class="badge bg-success">Model</span></td>
                    <td><code>$model->getByIds([1,2,3])</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getByIdAndUpdate">getByIdAndUpdate()</a></code></td>
                    <td>Get record by ID or return empty if not found</td>
                    <td><span class="badge bg-warning">object</span></td>
                    <td><code>$model->getByIdAndUpdate(123)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getByIdForEdit">getByIdForEdit()</a></code></td>
                    <td>Retrieve record for editing with timezone conversion</td>
                    <td><span class="badge bg-warning">object|null</span></td>
                    <td><code>$model->getByIdForEdit(1, [])</code></td>
                </tr>
                <tr>
                    <td><code><a href="#store">store()</a></code></td>
                    <td>Save single record directly (insert or update)</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->store($data, 1)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#save">save()</a></code></td>
                    <td>Save batch of changes (insert/update/delete operations)</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$obj->save()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#beforeSave">beforeSave()</a></code></td>
                    <td>Hook called before save operations</td>
                    <td><span class="badge bg-info">bool|void</span></td>
                    <td><code>protected function beforeSave($records)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#afterSave">afterSave()</a></code></td>
                    <td>Hook called after save operations</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>protected function afterSave($data, $results)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#delete">delete()</a></code></td>
                    <td>Delete record by primary key or single loaded record</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->delete(1)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#beforeDelete">beforeDelete()</a></code></td>
                    <td>Hook called before delete operations</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>protected function beforeDelete($ids)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#afterDelete">afterDelete()</a></code></td>
                    <td>Hook called after delete operations</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>protected function afterDelete($ids)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#deleteAll">deleteAll()</a></code></td>
                    <td>Delete all stored records</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->deleteAll()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#detach">detach()</a></code></td>
                    <td>Mark current record for deletion</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$obj->detach()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getLastInsertId">getLastInsertId()</a></code></td>
                    <td>Get last inserted record ID</td>
                    <td><span class="badge bg-info">int</span></td>
                    <td><code>$model->getLastInsertId()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getCommitResults">getCommitResults()</a></code></td>
                    <td>Get detailed results from last save() operation</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getCommitResults()</code></td>
                </tr>
                <tr>
                    <td><code>getLastInsertIds()</code></td>
                    <td>Get array of all inserted IDs from batch save</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getLastInsertIds()</code></td>
                </tr>
                <tr>
                    <td><code>searchRelated()</code></td>
                    <td>Search in related records</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->searchRelated('John', 'name')</code></td>
                </tr>
                <tr>
                    <td><code>setResultsByIds()</code></td>
                    <td>Set results manually by array of IDs</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->setResultsByIds([1,2,3])</code></td>
                </tr>
                <tr>
                    <td><code>saveCurrentRecord()</code></td>
                    <td>Save only the current record</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->saveCurrentRecord()</code></td>
                </tr>
                <tr>
                    <td><code>get()</code></td>
                    <td>Execute query and return results</td>
                    <td><span class="badge bg-success">Model|array|null</span></td>
                    <td><code>$model->get($query, $params)</code></td>
                </tr>

                <!-- Query Builder -->
                <tr class="table-secondary">
                    <td colspan="4">
                        <strong>Query Builder Methods</strong>
                        <div class="text-muted small mt-1">
                            ⚠️ These methods return a <code>Query</code> object (you leave the Model context).
                            Execution methods like <code>getAll()</code> will return the Model automatically because <code>setModelClass()</code> is set internally.
                        </div>
                    </td>
                </tr>
                <tr>
                    <td><code><a href="#where">where()</a></code></td>
                    <td>Add WHERE condition to query</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->where('price > ?', [10])</code></td>
                </tr>
                <tr>
                    <td><code><a href="#whereIn">whereIn()</a></code></td>
                    <td>Add WHERE IN condition to query</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->whereIn('id', [1,2,3])</code></td>
                </tr>
                <tr>
                    <td><code><a href="#whereHas">whereHas()</a></code></td>
                    <td>Filter by relationship existence</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->whereHas('rel', 'id > ?', [1])</code></td>
                </tr>
                <tr>
                    <td><code><a href="#order">order()</a></code></td>
                    <td>Add ORDER BY clause</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->order('name', 'asc')</code></td>
                </tr>
                <tr>
                    <td><code><a href="#select">select()</a></code></td>
                    <td>Specify columns to select</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->select(['id', 'name'])</code></td>
                </tr>
                <tr>
                    <td><code><a href="#limit">limit()</a></code></td>
                    <td>Add LIMIT clause for pagination</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->limit(0, 10)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getAll">getAll()</a></code></td>
                    <td>Get all records without limits</td>
                    <td><span class="badge bg-success">Model</span>|<span class="badge bg-warning">array</span></td>
                    <td><code>$model->getAll()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getFirst">getFirst()</a></code></td>
                    <td>Get first record</td>
                    <td><span class="badge bg-success">Model|null</span></td>
                    <td><code>$model->getFirst('id', 'desc')</code></td>
                </tr>
                <tr>
                    <td><code><a href="#total">total()</a></code></td>
                    <td>Get total count of records</td>
                    <td><span class="badge bg-info">int</span></td>
                    <td><code>$model->total()</code></td>
                </tr>
                <tr>
                    <td><code>from()</code></td>
                    <td>Add FROM clause to query</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->from('table')->get()</code></td>
                </tr>
                <tr>
                    <td><code>group()</code></td>
                    <td>Add GROUP BY clause</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->group('category')->get()</code></td>
                </tr>
                <tr>
                    <td><code>query()</code></td>
                    <td>Get or set Query object</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->query()</code></td>
                </tr>
                <tr>
                    <td><code>filterSearch()</code></td>
                    <td>Apply search filter to query</td>
                    <td><span class="badge bg-primary">Query</span></td>
                    <td><code>$model->filterSearch('text', $query)</code></td>
                </tr>

                <!-- Relationships -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>Relationship Methods</strong></td>
                </tr>
                <tr>
                    <td><code><a href="#with">with()</a></code></td>
                    <td>Eager load relationships</td>
                    <td><span class="badge bg-success">Model</span></td>
                    <td><code>$model->with(['doctor', 'patient'])</code></td>
                </tr>
                <tr>
                    <td><code><a href="#clearRelationshipCache">clearRelationshipCache()</a></code></td>
                    <td>Clear loaded relationship data</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->clearRelationshipCache()</code></td>
                </tr>
                <tr>
                    <td><code>getIncludeRelationships()</code></td>
                    <td>Get list of relationships to include</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getIncludeRelationships()</code></td>
                </tr>
                <tr>
                    <td><code>getRelationshipHandlers()</code></td>
                    <td>Get handlers for relationship field</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getRelationshipHandlers('rel', 'type')</code></td>
                </tr>

                <!-- Navigation -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>Navigation/Iterator Methods</strong></td>
                </tr>
                <tr>
                    <td><code><a href="#next">next()</a></code></td>
                    <td>Move to next record</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$results->next()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#prev">prev()</a></code></td>
                    <td>Move to previous record</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$results->prev()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#first">first()</a></code></td>
                    <td>Move to first record</td>
                    <td><span class="badge bg-success">Model|null</span></td>
                    <td><code>$results->first()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#last">last()</a></code></td>
                    <td>Move to last record</td>
                    <td><span class="badge bg-success">Model|null</span></td>
                    <td><code>$results->last()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#moveTo">moveTo()</a></code></td>
                    <td>Move to specific record index</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$results->moveTo(5)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#count">count()</a></code></td>
                    <td>Get number of records</td>
                    <td><span class="badge bg-info">int</span></td>
                    <td><code>$results->count()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#hasNext">hasNext()</a></code></td>
                    <td>Check if next record exists</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$results->hasNext()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#hasPrev">hasPrev()</a></code></td>
                    <td>Check if previous record exists</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$results->hasPrev()</code></td>
                </tr>
                <tr>
                    <td><code>getCurrentIndex()</code></td>
                    <td>Get current record index</td>
                    <td><span class="badge bg-info">int</span></td>
                    <td><code>$results->getCurrentIndex()</code></td>
                </tr>
                <tr>
                    <td><code>getNextCurrentIndex()</code></td>
                    <td>Get next available record index</td>
                    <td><span class="badge bg-info">int</span></td>
                    <td><code>$model->getNextCurrentIndex()</code></td>
                </tr>
                <tr>
                    <td><code>moveNext()</code></td>
                    <td>Move to next record (alias of next)</td>
                    <td><span class="badge bg-success">Model|null</span></td>
                    <td><code>$results->moveNext()</code></td>
                </tr>

                <!-- Data Formatting -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>Data Formatting Methods</strong></td>
                </tr>
                <tr>
                    <td><code><a href="#getRawData">getRawData()</a></code></td>
                    <td>Get raw data from database</td>
                    <td><span class="badge bg-warning">array|object</span></td>
                    <td><code>$results->getRawData('array', true)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getFormattedData">getFormattedData()</a></code></td>
                    <td>Get formatted data for display</td>
                    <td><span class="badge bg-warning">array|object</span></td>
                    <td><code>$results->getFormattedData('array', true)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getSqlData">getSqlData()</a></code></td>
                    <td>Get data in SQL format</td>
                    <td><span class="badge bg-warning">array|object</span></td>
                    <td><code>$results->getSqlData('array', true)</code></td>
                </tr>
                <tr>
                    <td><code><a href="#toArray">toArray()</a></code></td>
                    <td>Convert model to array</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->toArray()</code></td>
                </tr>
                <tr>
                    <td><code>setOutputMode()</code></td>
                    <td>Set output mode ('raw', 'formatted', or 'sql')</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->setOutputMode('formatted')</code></td>
                </tr>
                <tr>
                    <td><code>getOutputMode()</code></td>
                    <td>Get current output mode</td>
                    <td><span class="badge bg-warning">string</span></td>
                    <td><code>$model->getOutputMode()</code></td>
                </tr>
                <tr>
                    <td><code>getFormattedValue()</code></td>
                    <td>Get single formatted value</td>
                    <td><span class="badge bg-warning">mixed</span></td>
                    <td><code>$model->getFormattedValue('name')</code></td>
                </tr>
                <tr>
                    <td><code>getSqlValue()</code></td>
                    <td>Get single SQL value</td>
                    <td><span class="badge bg-warning">mixed</span></td>
                    <td><code>$model->getSqlValue('date')</code></td>
                </tr>
                <tr>
                    <td><code>getRawValue()</code></td>
                    <td>Get single raw value</td>
                    <td><span class="badge bg-warning">mixed</span></td>
                    <td><code>$model->getRawValue('field')</code></td>
                </tr>
                <tr>
                    <td><code>getRecordAction()</code></td>
                    <td>Get record action (insert/update/delete)</td>
                    <td><span class="badge bg-warning">string|null</span></td>
                    <td><code>$model->getRecordAction()</code></td>
                </tr>

                <!-- Schema & Validation -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>Schema & Validation Methods</strong></td>
                </tr>
                <tr>
                    <td><code><a href="#validate">validate()</a></code></td>
                    <td>Validate model data</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$obj->validate()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#buildTable">buildTable()</a></code></td>
                    <td>Create or update database table</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->buildTable()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#dropTable">dropTable()</a></code></td>
                    <td>Drop database table</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->dropTable()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getRules">getRules()</a></code></td>
                    <td>Get schema rules</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getRules()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getRule">getRule()</a></code></td>
                    <td>Get single field rule</td>
                    <td><span class="badge bg-warning">array|null</span></td>
                    <td><code>$model->getRule('price')</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getPrimaryKey">getPrimaryKey()</a></code></td>
                    <td>Get primary key field name</td>
                    <td><span class="badge bg-warning">string</span></td>
                    <td><code>$model->getPrimaryKey()</code></td>
                </tr>
                <tr>
                    <td><code>getRuleBuilder()</code></td>
                    <td>Get RuleBuilder to modify schema</td>
                    <td><span class="badge bg-warning">RuleBuilder</span></td>
                    <td><code>$model->getRuleBuilder()</code></td>
                </tr>
                <tr>
                    <td><code>setRules()</code></td>
                    <td>Set schema rules</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->setRules($rules)</code></td>
                </tr>
                <tr>
                    <td><code>getPrimaries()</code></td>
                    <td>Get array of all primary keys</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getPrimaries()</code></td>
                </tr>
                <tr>
                    <td><code>getSchema()</code></td>
                    <td>Get Schema object (MySQL or SQLite)</td>
                    <td><span class="badge bg-warning">Schema</span></td>
                    <td><code>$model->getSchema()</code></td>
                </tr>
                <tr>
                    <td><code>getSchemaFieldDifferences()</code></td>
                    <td>Get differences between DB and schema</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getSchemaFieldDifferences()</code></td>
                </tr>
                <tr>
                    <td><code>getColumns()</code></td>
                    <td>Get all defined columns</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getColumns()</code></td>
                </tr>
                <tr>
                    <td><code>getQueryColumns()</code></td>
                    <td>Get query columns</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getQueryColumns()</code></td>
                </tr>
                <tr>
                    <td><code>setQueryColumns()</code></td>
                    <td>Set query columns</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->setQueryColumns($cols)</code></td>
                </tr>
                <tr>
                    <td><code>getTable()</code></td>
                    <td>Get table name</td>
                    <td><span class="badge bg-warning">string</span></td>
                    <td><code>$model->getTable()</code></td>
                </tr>
                <tr>
                    <td><code>setTable()</code></td>
                    <td>Set table name</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->setTable('table_name')</code></td>
                </tr>
                <tr>
                    <td><code>getDb()</code></td>
                    <td>Get database connection</td>
                    <td><span class="badge bg-warning">MySql|SQLite</span></td>
                    <td><code>$model->getDb()</code></td>
                </tr>
                <tr>
                    <td><code>setDb()</code></td>
                    <td>Set database connection</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->setDb($db)</code></td>
                </tr>
                <tr>
                    <td><code>getDbType()</code></td>
                    <td>Get database type (db or db2)</td>
                    <td><span class="badge bg-warning">string</span></td>
                    <td><code>$model->getDbType()</code></td>
                </tr>

                <!-- Utility -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>Utility Methods</strong></td>
                </tr>
                <tr>
                    <td><code><a href="#isEmpty">isEmpty()</a></code></td>
                    <td>Check if model is empty</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->isEmpty()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#hasError">hasError()</a></code></td>
                    <td>Check if error occurred</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->hasError()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#getLastError">getLastError()</a></code></td>
                    <td>Get last error message</td>
                    <td><span class="badge bg-warning">string</span></td>
                    <td><code>$model->getLastError()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#clearCache">clearCache()</a></code></td>
                    <td>Clear query results cache</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->clearCache()</code></td>
                </tr>
                <tr>
                    <td><code><a href="#setQueryParams">setQueryParams()</a></code></td>
                    <td>Set query parameters from request</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->setQueryParams($request)</code></td>
                </tr>
                <tr>
                    <td><code>getLoadedExtension()</code></td>
                    <td>Get loaded extension instance</td>
                    <td><span class="badge bg-warning">object|null</span></td>
                    <td><code>$model->getLoadedExtension('ext_name')</code></td>
                </tr>

                <!-- Method Handlers (Attribute System) -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>Method Handlers (Attribute System)</strong></td>
                </tr>
                <tr>
                    <td><code>registerMethodHandler()</code></td>
                    <td>Register custom attribute handler</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->registerMethodHandler('field', 'type', 'method')</code></td>
                </tr>
                <tr>
                    <td><code>removeMethodHandler()</code></td>
                    <td>Remove attribute handler</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->removeMethodHandler('field', 'type')</code></td>
                </tr>
                <tr>
                    <td><code>getMethodHandler()</code></td>
                    <td>Get handler for field and type</td>
                    <td><span class="badge bg-warning">callable|null</span></td>
                    <td><code>$model->getMethodHandler('field', 'type')</code></td>
                </tr>
                <tr>
                    <td><code>hasMethodHandler()</code></td>
                    <td>Check if handler exists</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->hasMethodHandler('field', 'type')</code></td>
                </tr>
                <tr>
                    <td><code>getFieldsWithHandlers()</code></td>
                    <td>Get fields with handlers of type</td>
                    <td><span class="badge bg-warning">array</span></td>
                    <td><code>$model->getFieldsWithHandlers('ToDisplayValue')</code></td>
                </tr>

                <!-- Timezone Handling -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>Timezone Handling</strong></td>
                </tr>
                <tr>
                    <td><code>setDatesInUserTimezone()</code></td>
                    <td>Enable/disable user timezone conversion</td>
                    <td><span class="badge bg-success">Model</span></td>
                    <td><code>$model->setDatesInUserTimezone(true)</code></td>
                </tr>
                <tr>
                    <td><code>convertDatesToUserTimezone()</code></td>
                    <td>Convert dates from UTC to user timezone</td>
                    <td><span class="badge bg-success">Model</span></td>
                    <td><code>$model->convertDatesToUserTimezone()</code></td>
                </tr>
                <tr>
                    <td><code>convertDatesToUTC()</code></td>
                    <td>Convert dates from user timezone to UTC</td>
                    <td><span class="badge bg-success">Model</span></td>
                    <td><code>$model->convertDatesToUTC()</code></td>
                </tr>

                <!-- Data Management -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>Data Management</strong></td>
                </tr>
                <tr>
                    <td><code>setResults()</code></td>
                    <td>Set results manually</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->setResults($array)</code></td>
                </tr>
                <tr>
                    <td><code>setRow()</code></td>
                    <td>Set single row data</td>
                    <td><span class="badge bg-secondary">void</span></td>
                    <td><code>$model->setRow($data)</code></td>
                </tr>
                <!-- ArrayDb / Virtual Tables -->
                <tr class="table-secondary">
                    <td colspan="4"><strong>ArrayDb / Virtual Tables</strong></td>
                </tr>
                <tr>
                    <td><code><a href="#registerVirtualTable">registerVirtualTable()</a></code></td>
                    <td>Register current model data as an ArrayDb virtual table</td>
                    <td><span class="badge bg-info">bool</span></td>
                    <td><code>$model->registerVirtualTable('products')</code></td>
                </tr>
            </tbody>
        </table>
    </div>

   

    <h2 class="mt-5">Detailed Method Documentation</h2>

    <h3 class="mt-3" id="fill"><code>fill(array|object|null $data = null)</code></h3>
    <p>Fills the model with data from an array or object. This is the primary method for loading data into a model instance.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param array|object|null $data Data to fill the model with
 * @return static Returns the model instance for method chaining
 */
public function fill(array|object|null $data = null): static;

// Example 1: Create new record
$product = new ProductsModel();
$product->fill([
    'name' => 'New Product',
    'price' => 29.99,
    'in_stock' => true
]);
$product->save();

// Example 2: Update existing record
$product = new ProductsModel();
$product->fill([
    'id' => 123,
    'name' => 'Updated Product Name'
]);
$product->save(); // Will update record with id=123

// Example 3: Fill from request data
$product = new ProductsModel();
$product->fill($_POST);
if ($product->validate()) {
    $product->save();
}
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$data</code>: (array|object|null) The data to fill the model with. Can be an associative array or object.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>static</code>: Returns the model instance for method chaining.</li>
            </ul>
        </li>
        <li><strong>Behavior:</strong>
            <ul>
                <li>If <code>$data</code> contains a primary key and the record exists in database, it loads the record and marks it for update</li>
                <li>If the primary key is not found or missing, creates a new record marked for insert</li>
                <li>Only fields defined in the model's schema are accepted</li>
                <li>Performs automatic type conversion based on field types</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3" id="store"><code>store(array $data, $id = null)</code></h3>
    <p>Saves a single record directly to the database (immediate save). This is different from <code>save()</code> which handles batch operations.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param array $data Data to save
 * @param mixed $id Primary key for update, null for insert
 * @return bool True if successful, false otherwise
 */
public function store(array $data, $id = null): bool;

// Example 1: Insert new record
$success = $this->model->store([
    'name' => 'New Product',
    'price' => 29.99
]);

// Example 2: Update existing record
$success = $this->model->store([
    'name' => 'Updated Product',
    'price' => 39.99
], 123); // Update record with id=123

if ($success) {
    $new_id = $this->model->getLastInsertId();
    echo "Record saved with ID: " . $new_id;
} else {
    echo "Error: " . $this->model->getLastError();
}
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$data</code>: (array) An array of data to save.</li>
                <li><code>$id</code>: (mixed, optional) The primary key of the record to update. If null, creates a new record.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>bool</code>: Returns <code>true</code> if save was successful, <code>false</code> otherwise.</li>
            </ul>
        </li>
        <li><strong>Note:</strong> Use <code>store()</code> for saving single records immediately. Use <code>save()</code> for batch operations.</li>
    </ul>

    <h3 class="mt-3" id="getById"><code>getById($id, $use_cache = true)</code></h3>
    <p>Retrieves a single record by primary key. Always returns a Model instance. Use <code>isEmpty()</code> to check if the record was found.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param mixed $id Primary key value
 * @param bool $use_cache Whether to use cache for data
 * @return static Always returns Model instance (use isEmpty() to check if record exists)
 */
public function getById($id, bool $use_cache = true): static;

// Example 1: Check if record exists
$product = $this->model->getById(123);
if (!$product->isEmpty()) {
    echo $product->name;  // Access properties directly
    echo "Price: €" . $product->price;
}

// Example 2: Using count()
$product = $this->model->getById(123);
if ($product->count() > 0) {
    echo $product->name;
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
                <li><code>static</code>: Always returns a Model instance. Use <code>isEmpty()</code> or <code>count()</code> to check if the record was found.</li>
            </ul>
        </li>
        <li><strong>Note:</strong> This method never returns <code>null</code>. It always returns a Model instance, even if no record is found. Use <code>isEmpty()</code> to check if the record exists.</li>
    </ul>

    <h3 class="mt-3" id="getByIdAndUpdate"><code>getByIdAndUpdate($id, array $merge_data = [], $mysql_array = false)</code></h3>
    <p>Returns a record by primary key, otherwise returns an empty object</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">    /**
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

    <h3 class="mt-3" id="getEmpty"><code>getEmpty(array $data = [], $mysql_array = false)</code></h3>
    <p>Returns an empty model object for creating new records</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">    /**
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

    <h3 class="mt-3" id="getByIdForEdit"><code>getByIdForEdit($id, array $merge_data = [])</code></h3>
    <p>Retrieves a record for editing, applying edit rules.</p>
    <br>
    <h4 class="mt-3">Example 1: Edit a record</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">    protected function actionEditProject() {
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
    }</code></pre>
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

    <h3 class="mt-3" id="save"><code>save(bool $cascade = true, $reset_save_result = true)</code></h3>
    <p>Saves all tracked changes (batch operation). Processes all inserts, updates, and deletes marked through <code>fill()</code> and <code>detach()</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">    /**
    * @param bool $cascade If true, saves related hasOne relationships
    * @param bool $reset_save_result If true, reset save results after commit
    * @return bool True if all operations succeeded, false otherwise
    */
    public function save(bool $cascade = true, $reset_save_result = true): bool;

    // Example 1: Create new record
    $product = new ProductsModel();
    $product->fill([
        'name' => 'New Product',
        'price' => 29.99
    ]);
    if ($product->save()) {
        echo "Product created with ID: " . $product->getLastInsertId();
    }

    // Example 2: Update existing record
    $product = new ProductsModel();
    $product->fill(['id' => 123, 'price' => 39.99]);
    if ($product->save()) {
        echo "Product updated successfully";
    }

    // Example 3: Batch operations
    $product = new ProductsModel();
    $product->fill(['name' => 'Product 1', 'price' => 10]); // Insert
    $product->fill(['id' => 5, 'price' => 20]);            // Update
    $product->fill(['id' => 10])->detach();                // Delete

    if ($product->save()) {
        $results = $product->getCommitResults();
        // Returns array of all operations performed
    } else {
        echo "Error: " . $product->getLastError();
    }
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$cascade</code>: (bool, optional) If true, automatically saves hasOne relationships. Default: true.</li>
                <li><code>$reset_save_result</code>: (bool, optional) If true, resets save results after commit. Default: true.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>bool</code>: Returns <code>true</code> if all operations succeeded, <code>false</code> otherwise.</li>
            </ul>
        </li>
        <li><strong>Important:</strong>
            <ul>
                <li>Use <code>save()</code> for batch operations with multiple records</li>
                <li>Use <code>store()</code> for immediate single record saves</li>
                <li>All operations are executed in order: DELETE, then INSERT, then UPDATE</li>
                <li>Use <code>getCommitResults()</code> to get detailed information about each operation</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3" id="beforeSave"><code>beforeSave(array $records)</code></h3>
    <p>Hook method called before save operations. Override this method in your model to execute custom logic before records are saved. Return <code>false</code> to cancel the save operation.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param array $records Array of records to be saved
 * @return bool|void Return false to cancel save operation
 */
protected function beforeSave(array $records): bool|void;

// Example: Auto-generate slug before saving
protected function beforeSave(array $records): void {
    foreach ($records as $index => $record) {
        if (empty($record['slug']) && !empty($record['title'])) {
            $this->records_array[$index]['slug'] = $this->generateSlug($record['title']);
        }
    }
}
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$records</code>: (array) Array of records that will be saved.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>bool|void</code>: Return <code>false</code> to cancel the save operation, otherwise return nothing or <code>true</code>.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3" id="afterSave"><code>afterSave(array $data, array $results)</code></h3>
    <p>Hook method called after save operations complete. Override this method in your model to execute custom logic after records are saved (e.g., logging, sending notifications, updating related data).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param array $data Array of saved record data
 * @param array $results Array of save operation results
 * @return void
 */
protected function afterSave(array $data, array $results): void;

// Example: Send notification after product is created
protected function afterSave(array $data, array $results): void {
    foreach ($results as $result) {
        if ($result['action'] === 'insert' && $result['result']) {
            $this->sendProductCreatedNotification($result['id']);
        }
    }
}
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$data</code>: (array) Array containing the saved record data.</li>
                <li><code>$results</code>: (array) Array of save operation results with structure: [['id' => int, 'action' => string, 'result' => bool, 'last_error' => string], ...]</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>void</code>: This method does not return any value.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3" id="delete"><code>delete($id = null)</code></h3>
    <p>Deletes a record from the database. If <code>$id</code> is not provided, it only works when exactly one record is loaded in <code>records_objects</code>; otherwise it throws an exception to prevent accidental deletions.</p>

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
                <li><code>$id</code>: (mixed|null) The primary key of the record to delete. If null, exactly one record must be loaded in <code>records_objects</code> or an exception is thrown.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>bool</code>: Returns <code>true</code> if deletion was successful, otherwise <code>false</code>.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3" id="deleteAll"><code>deleteAll()</code></h3>
    <p>Deletes all stored records from the database.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    // Example: delete all stored records
    if ($model->deleteAll()) {
        echo "All records deleted";
    } else {
        echo "Delete failed: " . $model->getLastError();
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
                <li><code>bool</code>: Returns <code>true</code> if deletion was successful, otherwise <code>false</code>.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3" id="clearCache"><code>clearCache()</code></h3>
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

    <h3 class="mt-3" id="getLastError"><code>getLastError()</code></h3>
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

    <h3 class="mt-3" id="hasError"><code>hasError()</code></h3>
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

    <h4 class="mt-3" id="where"><code>where(string $condition, array $params = [])</code></h4>
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

        <h4 class="mt-3" id="order"><code>order(string|array $field = '', string $dir = 'asc')</code></h4>
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

    <h4 class="mt-3" id="select"><code>select(array|string $fields)</code></h4>
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
    <h4 class="mt-3" id="from"><code>from(string $from)</code></h4>
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

        <h4 class="mt-3" id="group"><code>group(string $group)</code></h4>
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
        <h4 class="mt-3" id="limit"><code>limit(int $start, int $limit)</code></h4>
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
        <h4 class="mt-3" id="get"><code>get($query = null, $params = [])</code></h4>
    <p>Executes the query and returns results. When called from a Model, it always returns a Model instance.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param Query|string $query A Query object or SQL string
    * @param array $params Parameters to pass to the query to prevent SQL injection
    * @return static Model instance with query results
    */
    public function get(Query $query, ?array $params = []): AbstractModel|array|null|false;

    // Example 1: Execute Query object (returns Model)
    $query = $this->model->query()->where('status = ?', ['active']);
    $posts = $this->model->get($query);
    foreach ($posts as $post) {
        echo $post->title; // Model instance
    }

    // Example 2: Execute with raw SQL (for compatibility)
    $posts = $this->model->get("SELECT * FROM posts WHERE status = ?", ['active']);
    </code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                <ul>
                    <li><code>$query</code>: (Query|string) A Query object or SQL string to execute.</li>
                    <li><code>$params</code>: (array, optional) Parameters for the query to prevent SQL injection.</li>
                    </ul>
            </li>
                <li><strong>Return value:</strong>
                    <ul>
                        <li><code>Model</code>: When called with a Query object, returns Model instance (setModelClass is automatically set).</li>
                        <li><code>array|null</code>: When called with raw SQL string, behavior depends on the context.</li>
                </ul>
            </li>
            <li><strong>Note:</strong> This method is primarily used internally. For most use cases, use <code>getAll()</code>, <code>getById()</code>, or query builder methods directly.</li>
        </ul>

        <h4 class="mt-3" id="execute"><code>execute($query = null, $params = [])</code></h4>
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

                <h4 class="mt-3" id="getAll"><code>getAll($order_field = '', $order_dir = 'asc')</code></h4>
                <p>Executes the current query without limits to retrieve all data. <strong>When called from a Model, returns a Model instance (not an array).</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @param string $order_field Optional field to order by
    * @param string $order_dir Order direction ('asc' or 'desc')
    * @return static|array Model instance (when called from Model) or array (standalone Query)
    */
    public function getAll($order_field = '', $order_dir = 'asc'): static|array;

    // Example 1: Called from Model (returns Model instance)
    $posts = $this->model->getAll();
    foreach ($posts as $post) {
        echo $post->title; // $post is a Model instance
    }

    // Example 2: With ordering
    $posts = $this->model->getAll('created_at', 'desc');

    // Example 3: With query builder chain (still returns Model)
    $posts = $this->model->where('status = ?', ['active'])->getAll();
    // Returns Model because setModelClass() is automatically set

    // Example 4: Check if results are empty
    $posts = $this->model->getAll();
    if (!$posts->isEmpty()) {
        foreach ($posts as $post) {
            echo $post->title;
        }
    }
    </code></pre>
            <ul>
                <li><strong>Input parameters:</strong>
                    <ul>
                            <li><code>$order_field</code>: (string, optional) Field name to order results by.</li>
                            <li><code>$order_dir</code>: (string, optional) Order direction: 'asc' or 'desc' (default: 'asc').</li>
                        </ul>
                    </li>
                    <li><strong>Return value:</strong>
                        <ul>
                            <li><code>static</code>: <strong>Model instance</strong> when called from a Model (recommended usage).</li>
                            <li><code>array</code>: Empty array if no database connection, or raw array if called from standalone Query.</li>
                    </ul>
                </li>
                <li><strong>Important:</strong> Unlike the documentation might suggest, <code>getAll()</code> returns a <strong>Model instance</strong> when called from a Model, not an array. Use <code>isEmpty()</code> or <code>count()</code> to check results. The Model instance is iterable and acts like an array in foreach loops.</li>
        </ul>
        <h4 class="mt-3" id="first"><code>first($query = null, $params = [])</code></h4>
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
    <h4 class="mt-3" id="total"><code>total()</code></h4>
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

        <h3 class="mt-3" id="setQueryParams"><code>setQueryParams(array $request)</code></h3>
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
    <h3 class="mt-3" id="buildTable"><code>buildTable()</code></h3>
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
   
    <h3 class="mt-3" id="dropTable"><code>dropTable()</code></h3>
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
    <h3 class="mt-3" id="validate"><code>validate(bool $validate_all = false)</code></h3>
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

  
    <h3 class="mt-3" id="getColumns"><code>getColumns($key = '')</code></h3>
    <p>Returns all columns defined in the object rules.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    /**
    * @return array Array of columns
    */
    public function getColumns(): array;

    // Example
    $all_columns = $this->model->getColumns();
    </code></pre>

    <h3 class="mt-3" id="addFilter"><code>addFilter($filter_type, $fn)</code></h3>
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

    <h3 class="mt-3" id="with"><code>with(string|array|null $relations = null)</code></h3>
    <p>Eager load relationships. Loads specified relationships immediately to avoid N+1 query problems.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string|array|null $relations Relationship(s) to include
 * @return static Returns the model instance for method chaining
 */
public function with(string|array|null $relations = null): static;

// Example 1: Load single relationship
$appointments = $this->model->with('doctor')->getAll();
foreach ($appointments as $appointment) {
    echo $appointment->doctor->name; // Already loaded, no extra query
}

// Example 2: Load multiple relationships
$appointments = $this->model->with(['doctor', 'patient'])->getAll();

// Example 3: Load all defined relationships
$appointments = $this->model->with()->getAll();
    </code></pre>

    <h3 class="mt-3" id="whereIn"><code>whereIn(string $field, array $values, string $operator = 'AND')</code></h3>
    <p>Add WHERE IN condition to query. Useful for filtering by multiple values.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $field Field name
 * @param array $values Array of values
 * @param string $operator 'AND' or 'OR'
 * @return Query
 */
public function whereIn(string $field, array $values, string $operator = 'AND'): Query;

// Example: Find products with specific IDs
$products = $this->model->whereIn('id', [1, 5, 10, 25])->getAll();

// Example: Find products in specific categories
$products = $this->model->whereIn('category', ['Electronics', 'Books'])->getAll();
    </code></pre>

    <h3 class="mt-3" id="whereHas"><code>whereHas(string $relationAlias, string $condition, array $params = [])</code></h3>
    <p>Filter records based on relationship existence. Uses EXISTS subquery.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $relationAlias Relationship alias
 * @param string $condition WHERE condition for related records
 * @param array $params Parameters for the condition
 * @return Query
 */
public function whereHas(string $relationAlias, string $condition, array $params = []): Query;

// Example: Find doctors who have appointments after a certain date
$doctors = $this->model->whereHas('appointments', 'date > ?', ['2024-01-01'])->getAll();
    </code></pre>

    <h3 class="mt-3" id="getByIds"><code>getByIds(string|array $ids)</code></h3>
    <p>Retrieve multiple records by their primary keys. Always returns a Model instance. Use <code>isEmpty()</code> to check if records were found.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string|array $ids Comma-separated list or array of IDs
 * @return static Always returns Model instance (use isEmpty() to check if records exist)
 */
public function getByIds(string|array $ids): static;

// Example 1: Array of IDs
$products = $this->model->getByIds([1, 5, 10, 25]);
if (!$products->isEmpty()) {
    foreach ($products as $product) {
        echo $product->name;
    }
}

// Example 2: Comma-separated string
$products = $this->model->getByIds('1,5,10,25');
foreach ($products as $product) {
    echo $product->name;
}
    </code></pre>
    <ul>
        <li><strong>Note:</strong> This method never returns <code>null</code>. Use <code>isEmpty()</code> or <code>count()</code> to check if any records were found.</li>
    </ul>

    <h3 class="mt-3" id="detach"><code>detach()</code></h3>
    <p>Mark current record for deletion. The record will be deleted when <code>save()</code> is called.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return bool True if record was marked for deletion
 */
public function detach(): bool;

// Example: Mark record for deletion
$product = $this->model->getById(123);
$product->detach();
$product->save(); // Now the record is actually deleted
    </code></pre>

    <h3 class="mt-3" id="isEmpty"><code>isEmpty()</code></h3>
    <p>Check if model is empty (has no data).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return bool True if model is empty
 */
public function isEmpty(): bool;

// Example
$product = $this->model->getById(999); // Non-existent ID
if ($product->isEmpty()) {
    echo "Product not found";
}
    </code></pre>

    <h3 class="mt-3" id="getLastInsertId"><code>getLastInsertId()</code></h3>
    <p>Get the last inserted record ID from <code>save()</code> or <code>store()</code> operation.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return int The last insert ID
 */
public function getLastInsertId(): int;

// Example
$product = new ProductsModel();
$product->fill(['name' => 'New Product', 'price' => 29.99]);
if ($product->save()) {
    $new_id = $product->getLastInsertId();
    echo "Created product with ID: " . $new_id;
}
    </code></pre>

    <h3 class="mt-3" id="getCommitResults"><code>getCommitResults()</code></h3>
    <p>Get detailed results from last <code>save()</code> operation. Returns array with information about each operation performed.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return array Array of operations: [['id' => int, 'action' => string, 'result' => bool, 'last_error' => string], ...]
 */
public function getCommitResults(): array;

// Example
$product = new ProductsModel();
$product->fill(['name' => 'Product 1']);
$product->fill(['id' => 5, 'price' => 20]);
$product->save();

$results = $product->getCommitResults();
foreach ($results as $result) {
    echo "ID: {$result['id']}, Action: {$result['action']}, Success: " . ($result['result'] ? 'Yes' : 'No');
    if (!$result['result']) {
        echo ", Error: {$result['last_error']}";
    }
    echo "\n";
}
    </code></pre>

    <h3 class="mt-3" id="getFirst"><code>getFirst($order_field = '', $order_dir = 'asc')</code></h3>
    <p>Get the first record from query results.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $order_field Field to order by
 * @param string $order_dir Direction ('asc' or 'desc')
 * @return static|null First record or null
 */
public function getFirst($order_field = '', $order_dir = 'asc'): ?static;

// Example
$latest_product = $this->model->getFirst('created_at', 'desc');
if ($latest_product) {
    echo $latest_product->name;
}
    </code></pre>

    <h3 class="mt-3" id="getRules"><code>getRules(string $key = '', mixed $value = true)</code></h3>
    <p>Get schema rules defined in configure() method.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $key Optional filter key
 * @param mixed $value Optional filter value
 * @return array Array of rules
 */
public function getRules(string $key = '', mixed $value = true): array;

// Example 1: Get all rules
$rules = $this->model->getRules();

// Example 2: Get required fields only
$required_fields = $this->model->getRules('required', true);
    </code></pre>

    <h3 class="mt-3" id="getRule"><code>getRule(string $key = '')</code></h3>
    <p>Get rule for a specific field.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $key Field name
 * @return array|null Rule array or null if not found
 */
public function getRule(string $key = ''): ?array;

// Example
$price_rule = $this->model->getRule('price');
if ($price_rule) {
    echo "Price type: " . $price_rule['type'];
}
    </code></pre>

    <h3 class="mt-3" id="getPrimaryKey"><code>getPrimaryKey()</code></h3>
    <p>Get the primary key field name.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return string Primary key field name
 */
    public function getPrimaryKey(): string;

// Example
$pk = $this->model->getPrimaryKey();
echo "Primary key: " . $pk; // Output: "id"
    </code></pre>

    <h3 class="mt-3" id="registerVirtualTable"><code>registerVirtualTable(string $tableName, ?string $autoIncrementColumn = null)</code></h3>
    <p>Registers the current model results as an ArrayDb virtual table so you can run SQL queries on the data. Only scalar fields are included; array and object fields are ignored.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * @param string $tableName Virtual table name (can include #__ prefix token)
 * @param string|null $autoIncrementColumn Auto-increment column (defaults to primary key)
 * @return bool True on success, false on failure
 */
public function registerVirtualTable(string $tableName, ?string $autoIncrementColumn = null): bool;

// Example 1: Basic usage (PHP + SQL)
$modelData->registerVirtualTable('product');
$complete = \App\Get::ArrayDb()->getResults(
    'SELECT * FROM product WHERE status = "COMPLETE"'
);

// Equivalent PHP
$new_data = array_filter($modelData->getFormattedData(), function ($row) {
    return $row->status === 'COMPLETE';
});

// Example 2: Why SQL is more readable for joins
$modelProducts->registerVirtualTable('products');
$modelCustomers->registerVirtualTable('customers');
$db = \App\Get::ArrayDb();

// SQL - clear and concise
$result = $db->getResults('SELECT p.*, c.name AS customer_name, c.email
                           FROM products p
                           JOIN customers c ON p.customer_id = c.id
                           WHERE p.status = "PENDING"');

// PHP - complex and inefficient
$products = $modelProducts->getFormattedData();
$customers = $modelCustomers->getFormattedData();
$customersMap = array_column($customers, null, 'id');

$result = array_map(function ($p) use ($customersMap) {
    $customer = $customersMap[$p->customer_id] ?? null;
    return (object) [
        ...(array) $p,
        'customer_name' => $customer->name ?? null,
        'email' => $customer->email ?? null
    ];
}, array_filter($products, fn($p) => $p->status === 'PENDING'));
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$tableName</code>: (string) The virtual table name to register in ArrayDb.</li>
                <li><code>$autoIncrementColumn</code>: (string|null, optional) Auto-increment column (defaults to the model primary key).</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>bool</code>: Returns <code>true</code> on success, <code>false</code> if the table name is invalid or no data is available.</li>
            </ul>
        </li>
    </ul>

    <h2 class="mt-5">Complete Usage Examples</h2>

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
