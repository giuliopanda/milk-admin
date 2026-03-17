<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
* @title GetDataBuilder Extensions
* @order 13
* @tags extensions, getdatabuilder-extensions, AbstractGetDataBuilderExtension, data-hooks, configure, beforeGetData, afterGetData, query-filtering, data-processing
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

   <h1>GetDataBuilder Extensions</h1>

   <p>GetDataBuilder Extensions allow you to modify data retrieval behavior, add filters, columns, and custom actions to data lists without modifying the GetDataBuilder class itself. They extend the <code>AbstractGetDataBuilderExtension</code> class.</p>

   <h2>Creating a GetDataBuilder Extension</h2>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\MyExtension;

use App\Abstracts\AbstractGetDataBuilderExtension;

class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    // Configuration parameters
    protected bool $auto_filter = true;

    // Hook called during builder configuration
    public function configure(object $builder): void
    {
        // Add columns, filters, actions
    }

    // Hook called before data retrieval
    public function beforeGetData(): void
    {
        // Modify query before execution
    }

    // Hook called after data retrieval
    public function afterGetData(array $data): array
    {
        // Modify data array
        return $data;
    }
}</code></pre>

   <h2 class="mt-4">Accessing the Model</h2>

   <p>Get the model instance from the GetDataBuilder:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure(object $builder): void
{
    // Get the model
    $model = $builder->getModel();

    // Access model properties
    $primary_key = $model->getPrimaryKey();
    $table = $model->getTable();

    // Get loaded model extensions
    $soft_del_ext = $model->getLoadedExtension('SoftDelete');
}</code></pre>

   <h2 class="mt-4">Accessing Builder Properties</h2>

   <p>Access common builder properties and settings:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure(object $builder): void
{
    // Get current page name
    $page = $builder->getPage();

    // Get current filters
    $filters = $builder->getFilters();

    // Get database instance for query building
    $db = Get::db();

    // Add a WHERE condition
    $builder->where($db->qn('status') . ' = ?', ['active']);
}</code></pre>

   <h2 class="mt-4">Available Hooks</h2>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Hook</th>
            <th>Parameters</th>
            <th>Return</th>
            <th>Description</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>configure()</code></td>
            <td><code>object $builder</code></td>
            <td><code>void</code></td>
            <td>Add columns, filters, actions during builder configuration</td>
         </tr>
         <tr>
            <td><code>beforeGetData()</code></td>
            <td>-</td>
            <td><code>void</code></td>
            <td>Modify query before data retrieval</td>
         </tr>
         <tr>
            <td><code>afterGetData()</code></td>
            <td><code>array $data</code></td>
            <td><code>array</code></td>
            <td>Modify data array after retrieval</td>
         </tr>
      </tbody>
   </table>

   <h2 class="mt-4">Extension Parameters</h2>

   <p>Define configurable parameters as protected properties:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    // Default values
    protected bool $auto_filter = true;
    protected string $field_name = 'status';
}

// Override in module configuration
$rule_builder->addExtension('MyExtension', [
    'auto_filter' => false,
    'field_name' => 'custom_status'
]);</code></pre>

   <h2 class="mt-4">Example 1: Author GetDataBuilder Extension</h2>

   <p>Filters records to show only those created by the current user when they have "manage_own_only" permission:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\Author;

use App\Abstracts\AbstractGetDataBuilderExtension;
use App\{Get, Permissions};

class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    public function configure(object $builder): void
    {
        // Initialize Auth to load user permissions
        Get::make('Auth');

        // Administrators see all records
        if (Permissions::check('_user.is_admin')) {
            return;
        }

        $page = $builder->getPage();

        // Check if user has "manage_own_only" permission
        if ($page && Permissions::check($page . '.manage_own_only')) {
            $user = Get::make('Auth')->getUser();
            $current_user_id = $user->id ?? 0;

            if ($current_user_id > 0) {
                $db = Get::db();

                // Filter to show only own records
                $builder->where(
                    $db->qn('created_by') . ' = ?',
                    [$current_user_id]
                );
            }
        }
    }
}</code></pre>

   <h2 class="mt-4">Example 2: Adding Custom Columns</h2>

   <p>Add computed columns or modify existing ones:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\ProductStats;

use App\Abstracts\AbstractGetDataBuilderExtension;

class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    protected bool $show_stock_status = true;

    public function configure(object $builder): void
    {
        if (!$this->show_stock_status) {
            return;
        }

        // Add a custom column showing stock status
        $builder->field('stock_status')
            ->label('Stock Status')
            ->fn(function($row) {
                $quantity = $row['quantity'] ?? 0;

                if ($quantity <= 0) {
                    return '&lt;span class="badge bg-danger"&gt;Out of Stock&lt;/span&gt;';
                } elseif ($quantity < 10) {
                    return '&lt;span class="badge bg-warning"&gt;Low Stock&lt;/span&gt;';
                }

                return '&lt;span class="badge bg-success"&gt;In Stock&lt;/span&gt;';
            });
    }
}</code></pre>

   <h2 class="mt-4">Example 3: Modifying Data After Retrieval</h2>

   <p>Process data array after retrieval to add computed values:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\OrderTotals;

use App\Abstracts\AbstractGetDataBuilderExtension;

class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    public function afterGetData(array $data): array
    {
        // Add computed total to each row
        foreach ($data as &$row) {
            $price = $row['price'] ?? 0;
            $quantity = $row['quantity'] ?? 0;
            $tax_rate = $row['tax_rate'] ?? 0;

            // Calculate total with tax
            $subtotal = $price * $quantity;
            $tax = $subtotal * ($tax_rate / 100);
            $row['total'] = $subtotal + $tax;
        }

        return $data;
    }
}</code></pre>

   <h2 class="mt-4">Example 4: Adding Filters</h2>

   <p>Add dynamic filters to the query based on request parameters:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\DateFilter;

use App\Abstracts\AbstractGetDataBuilderExtension;

class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    protected string $date_field = 'created_at';

    public function configure(object $builder): void
    {
        $field = $this->date_field;

        // Add a filter for date ranges
        $builder->filter('date_range', function($query, $value) use ($field) {
            switch ($value) {
                case 'today':
                    $query->where("DATE($field) = CURDATE()");
                    break;
                case 'week':
                    $query->where("$field >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                    break;
                case 'month':
                    $query->where("$field >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    break;
            }
        });
    }
}</code></pre>

   <h2 class="mt-4">Common Use Cases</h2>

   <ul>
      <li><strong>Access control</strong> - Filter data based on user permissions</li>
      <li><strong>Soft delete filtering</strong> - Hide/show deleted records</li>
      <li><strong>Status filtering</strong> - Filter by record status (active/inactive)</li>
      <li><strong>Computed columns</strong> - Add calculated fields to the data list</li>
      <li><strong>Data enrichment</strong> - Add related data from other tables</li>
      <li><strong>Custom actions</strong> - Add row actions (restore, archive, etc.)</li>
      <li><strong>Dynamic filtering</strong> - Apply filters based on request parameters</li>
   </ul>

   <h2 class="mt-4">Best Practices</h2>

   <ul>
      <li>Use <code>configure()</code> for modifying the builder structure (columns, filters, actions)</li>
      <li>Use <code>beforeGetData()</code> for query modifications (WHERE clauses, JOINs)</li>
      <li>Use <code>afterGetData()</code> for post-processing data (calculations, formatting)</li>
      <li>Always check permissions before applying filters</li>
      <li>Use protected properties for configurable parameters</li>
      <li>Quote database identifiers with <code>$db->qn()</code> for security</li>
   </ul>

   <h2 class="mt-4">See Also</h2>

   <ul>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-introduction'); ?>">Extensions Introduction</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-searchbuilder'); ?>">SearchBuilder Extensions</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-formbuilder'); ?>">FormBuilder Extensions</a></li>
   </ul>

</div>
