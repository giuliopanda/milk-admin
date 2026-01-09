<?php
namespace Modules\Docs\Pages;
/**
 * @title Creating a Fetch-Based Table with Offcanvas
 * @guide developer
 * @order 52
 * @tags TableBuilder, Offcanvas, AJAX, fetch, activeFetch, setRequestAction, SearchBuilder, table-update, offcanvas-table
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Creating a Fetch-Based Table with Offcanvas</h1>

    <p>This guide demonstrates how to create tables that load and update via AJAX within an offcanvas panel. This pattern is ideal for selection dialogs, lookup tables, or any scenario where you need to display a searchable table in a modal context.</p>

    <div class="alert alert-info">
        <strong>Key Concept:</strong> Fetch-based tables require <strong>two separate actions</strong>:
        <ol class="mb-0">
            <li><strong>Display Action</strong>: Shows the offcanvas with the initial table</li>
            <li><strong>Update Action</strong>: Handles AJAX table updates (search, pagination, sorting)</li>
        </ol>
    </div>

    <h2>The Two-Action Pattern</h2>

    <p>When a table uses AJAX updates (fetch mode), it needs two distinct request actions:</p>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Action Type</th>
                <th>Purpose</th>
                <th>Response Type</th>
                <th>Called When</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Display Action</strong></td>
                <td>Shows the offcanvas panel with initial table</td>
                <td>JSON with <code>offcanvas_end</code> structure</td>
                <td>User clicks link to open offcanvas</td>
            </tr>
            <tr>
                <td><strong>Update Action</strong></td>
                <td>Updates the table content via AJAX</td>
                <td>JSON with <code>html</code> and <code>table_id</code></td>
                <td>User searches, sorts, or paginates</td>
            </tr>
        </tbody>
    </table>

    <h2>Required TableBuilder Methods</h2>

    <h3>1. activeFetch()</h3>
    <p>Enables AJAX mode for the table. When enabled, all table interactions (search, pagination, sorting) will use AJAX instead of full page reloads.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">TableBuilder::create($model, 'myTableId')
    ->activeFetch()  // Enable AJAX mode
    ->render();</code></pre>

    <div class="alert alert-success">
        <strong>✓ Effect:</strong> The table will submit filter changes, pagination, and sorting via AJAX requests instead of page reloads.
    </div>

    <h3>2. setRequestAction()</h3>
    <p>Specifies which action should handle the AJAX table updates. This must match the name of your update action method.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">TableBuilder::create($model, 'myTableId')
    ->activeFetch()
    ->setRequestAction('my-table-update')  // Points to update action
    ->render();</code></pre>

    <div class="alert alert-success">
        <strong>✓ Effect:</strong> When the table needs to update, it will send an AJAX request to <code>?page=mypage&action=my-table-update</code>
    </div>

    <h2>Complete Implementation Example</h2>

    <p>This example shows a product selection table in an offcanvas. Users can search products by name and select one.</p>

    <h3>Step 1: Create the Module</h3>
    <p>Create <code>milkadmin/Modules/ProductSelectorModule.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\Response;
use Builders\{TableBuilder, SearchBuilder};

class ProductSelectorModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('productSelector')
             ->title('Product Selector')
             ->menu('Product Selector')
             ->access('public');
    }

    /**
     * Display Action - Shows the offcanvas with the table
     * Called when user clicks to open the product selector
     */
    #[RequestAction('select-product')]
    public function selectProduct() {
        // Create search builder
        $search = SearchBuilder::create('idTableProducts')
            ->search('search')
            ->placeholder('Search products...')
            ->layout('full-width');

        // Create table builder
        $table = $this->createProductTable();

        // Return offcanvas response
        $response = [
            'title' => 'Select a Product',
            'offcanvas_end' => [
                'action' => 'show',
                'title' => 'Select a Product',
                'body' => $search->render() . '&lt;br&gt;' . $table->render()
            ]
        ];

        Response::json($response);
    }

    /**
     * Update Action - Handles AJAX table updates
     * Called when user searches, sorts, or paginates
     */
    #[RequestAction('select-product-update-table')]
    public function selectProductUpdateTable() {
        $table = $this->createProductTable();
        Response::json($table->getResponse());
    }

    /**
     * Helper method to create the table configuration
     * Shared between display and update actions
     */
    private function createProductTable() {
        return TableBuilder::create($this->model, 'idTableProducts')
            ->activeFetch()                              // Enable AJAX mode
            ->setRequestAction('select-product-update-table')  // Point to update action
            ->resetFields()
            ->field('name')->label('Product Name')
            ->field('price')->label('Price')
                ->fn(function($row) {
                    return '$' . number_format($row->price, 2);
                })
            ->field('category')->label('Category')
            ->addAction('select', [
                'label' => 'Select',
                'link' => '?page=productSelector&action=product-selected&id=%id%',
                'class' => 'btn-primary btn-sm',
                'icon' => 'bi-check-circle',
                'fetch' => 'get'
            ]);
    }

    /**
     * Action called when user selects a product
     */
    #[RequestAction('product-selected')]
    public function productSelected() {
        $product_id = _absint($_REQUEST['id'] ?? 0);
        $product = $this->model->find($product_id);

        $response = [
            'alert' => [
                'type' => 'success',
                'message' => 'Product selected: ' . $product->name
            ],
            'offcanvas_end' => [
                'action' => 'hide'  // Close the offcanvas
            ]
        ];

        Response::json($response);
    }
}

/**
 * Simple Product Model for demonstration
 */
class ProductSelectorModel extends AbstractModel {
    protected function configure($rule): void
    {
        $rule
            ->table('products')
            ->id()
            ->string('name', 100)->label('Product Name')
            ->decimal('price', 10, 2)->label('Price')
            ->string('category', 50)->label('Category');
    }
}</code></pre>

    <h3>Step 2: Add a Link to Open the Offcanvas</h3>
    <p>From any other page, add a link that triggers the display action:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;a href="?page=productSelector&action=select-product"
   class="btn btn-primary"
   data-fetch="get"&gt;
    &lt;i class="bi bi-search"&gt;&lt;/i&gt; Select Product
&lt;/a&gt;</code></pre>

    <div class="alert alert-success">
        <strong>✓ Result:</strong> When clicked, opens an offcanvas with a searchable product table. All table interactions happen via AJAX without closing the offcanvas.
    </div>

    <h2>Workflow Diagram</h2>

    <pre class="border p-3" style="background-color: #f8f9fa;"><code>┌─────────────────────────────────────────────────────────┐
│ 1. User clicks "Select Product" link                   │
│    data-fetch="get"                                     │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Display Action: select-product                      │
│    - Creates SearchBuilder                             │
│    - Creates TableBuilder with activeFetch()           │
│    - Returns offcanvas_end response                    │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Offcanvas opens with table                          │
│    - Table is in AJAX mode                             │
│    - setRequestAction points to update action          │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 4. User searches/sorts/paginates                        │
│    - Table sends AJAX request                          │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Update Action: select-product-update-table          │
│    - Creates same TableBuilder                         │
│    - Returns getResponse() with updated HTML           │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Table content updates via AJAX                      │
│    - Offcanvas stays open                              │
│    - Only table HTML is replaced                       │
└─────────────────────────────────────────────────────────┘</code></pre>

    <h2>Understanding getResponse()</h2>

    <p>The <code>getResponse()</code> method returns a JSON array for AJAX updates:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table->getResponse();

// Returns:
[
    'html' => '&lt;table&gt;...&lt;/table&gt;',  // Updated table HTML
    'table_id' => 'idTableProducts'      // Table identifier
]</code></pre>

    <div class="alert alert-warning">
        <strong>Important:</strong> The update action must return <code>getResponse()</code>, NOT <code>render()</code>. The render() method returns HTML string, while getResponse() returns the JSON structure needed for AJAX updates.
    </div>

    <h2>Integrating SearchBuilder</h2>

    <p>SearchBuilder automatically links to tables by sharing the same table ID:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Both use the same ID: 'idTableProducts'
$search = SearchBuilder::create('idTableProducts')
    ->search('search')
    ->placeholder('Type to search...');

$table = TableBuilder::create($model, 'idTableProducts')
    ->activeFetch()
    ->setRequestAction('update-action');</code></pre>

    <div class="alert alert-success">
        <strong>✓ Effect:</strong> When user types in the search box, the table automatically updates via the specified request action.
    </div>

    <h3>SearchBuilder Methods Used</h3>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Method</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>create($table_id)</code></td>
                <td>Static factory method. Creates a SearchBuilder linked to a specific table ID.</td>
            </tr>
            <tr>
                <td><code>search($filter_type)</code></td>
                <td>Creates a search input field. The filter_type parameter must match a filter defined in TableBuilder.</td>
            </tr>
            <tr>
                <td><code>select($filter_type)</code></td>
                <td>Creates a select dropdown filter.</td>
            </tr>
            <tr>
                <td><code>placeholder($text)</code></td>
                <td>Sets placeholder text for input fields.</td>
            </tr>
            <tr>
                <td><code>layout($type)</code></td>
                <td>Controls field layout. Options: 'inline', 'full-width', 'stacked'.</td>
            </tr>
            <tr>
                <td><code>render()</code></td>
                <td>Outputs the search form HTML.</td>
            </tr>
        </tbody>
    </table>

    <h2>Advanced Example: Multiple Filters</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('advanced-select')]
public function advancedSelect() {
    $search = SearchBuilder::create('idTableAdvanced')
        ->setWrapperClass('d-flex align-items-center gap-2')

        // Text search
        ->search('search')
            ->label('Search')
            ->placeholder('Product name...')
            ->layout('inline')
            ->floating(false)

        // Category filter
        ->select('category_filter')
            ->label('Category')
            ->options([
                '' => 'All Categories',
                'electronics' => 'Electronics',
                'clothing' => 'Clothing',
                'food' => 'Food'
            ])
            ->selected($_REQUEST['category'] ?? '')
            ->layout('inline')
            ->floating(false);

    $table = TableBuilder::create($this->model, 'idTableAdvanced')
        ->activeFetch()
        ->setRequestAction('advanced-select-update')

        // Define filters matching SearchBuilder
        ->filter('category_filter', function($query, $value) {
            if (!empty($value)) {
                $query->where('category = ?', [$value]);
            }
        }, $_REQUEST['category'] ?? '')

        ->resetFields()
        ->field('name')->label('Product')
        ->field('category')->label('Category')
        ->field('price')->label('Price');

    $response = [
        'offcanvas_end' => [
            'action' => 'show',
            'title' => 'Advanced Product Selection',
            'body' => $search->render() . '&lt;br&gt;' . $table->render()
        ]
    ];

    Response::json($response);
}

#[RequestAction('advanced-select-update')]
public function advancedSelectUpdate() {
    $table = TableBuilder::create($this->model, 'idTableAdvanced')
        ->activeFetch()
        ->setRequestAction('advanced-select-update')
        ->filter('category_filter', function($query, $value) {
            if (!empty($value)) {
                $query->where('category = ?', [$value]);
            }
        }, $_REQUEST['category'] ?? '')
        ->resetFields()
        ->field('name')->label('Product')
        ->field('category')->label('Category')
        ->field('price')->label('Price');

    Response::json($table->getResponse());
}</code></pre>

    <h2>offcanvas_end Response Structure</h2>

    <p>The display action returns a JSON response with the <code>offcanvas_end</code> key:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$response = [
    'offcanvas_end' => [
        'action' => 'show',           // 'show' to open, 'hide' to close
        'title' => 'Offcanvas Title', // Title displayed in header
        'body' => '&lt;html&gt;...&lt;/html&gt;',  // Content (search + table)
        'size' => 'lg'                // Optional: 'sm', 'lg', 'xl' (default: md)
    ]
];

Response::json($response);</code></pre>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Property</th>
                <th>Values</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>action</code></td>
                <td>'show', 'hide'</td>
                <td>Controls offcanvas visibility</td>
            </tr>
            <tr>
                <td><code>title</code></td>
                <td>string</td>
                <td>Text displayed in offcanvas header</td>
            </tr>
            <tr>
                <td><code>body</code></td>
                <td>HTML string</td>
                <td>Content to display in offcanvas body</td>
            </tr>
            <tr>
                <td><code>size</code></td>
                <td>'sm', 'lg', 'xl'</td>
                <td>Optional width size (default: medium)</td>
            </tr>
        </tbody>
    </table>

    <h2>Common Mistakes and Solutions</h2>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Problem</th>
                <th>Cause</th>
                <th>Solution</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Table doesn't update when searching</td>
                <td>Missing <code>activeFetch()</code></td>
                <td>Add <code>->activeFetch()</code> to TableBuilder</td>
            </tr>
            <tr>
                <td>AJAX request goes to wrong action</td>
                <td>Missing or wrong <code>setRequestAction()</code></td>
                <td>Ensure <code>setRequestAction('action-name')</code> matches your update action</td>
            </tr>
            <tr>
                <td>Update action returns blank table</td>
                <td>Using <code>render()</code> instead of <code>getResponse()</code></td>
                <td>Change to <code>Response::json($table->getResponse())</code></td>
            </tr>
            <tr>
                <td>Filters not working</td>
                <td>SearchBuilder filter_type doesn't match TableBuilder filter</td>
                <td>Ensure filter names match in both builders</td>
            </tr>
            <tr>
                <td>Table configuration differs between actions</td>
                <td>Duplicate code in both actions</td>
                <td>Extract table creation to a helper method</td>
            </tr>
            <tr>
                <td>SearchBuilder doesn't trigger table updates</td>
                <td>Different table IDs</td>
                <td>Use the same table ID for both SearchBuilder and TableBuilder</td>
            </tr>
        </tbody>
    </table>

    <h2>Best Practices</h2>

    <ul>
        <li><strong>Share Configuration:</strong> Create a helper method for table configuration to ensure display and update actions use identical settings</li>
        <li><strong>Match Table IDs:</strong> SearchBuilder and TableBuilder must use the same table ID</li>
        <li><strong>Match Filter Names:</strong> Filter names in SearchBuilder must exactly match those in TableBuilder</li>
        <li><strong>Always Use getResponse():</strong> Update actions must return <code>getResponse()</code>, not <code>render()</code></li>
        <li><strong>Keep Actions Simple:</strong> Display action sets up the offcanvas, update action only handles table updates</li>
    </ul>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Form/builders-form-containers"><strong>Form Containers</strong></a>: Organizing form fields with Bootstrap grid layouts</li>
        <li><strong>TableBuilder Actions</strong>: Adding custom actions and buttons to tables</li>
        <li><strong>Response System</strong>: Understanding JSON responses and fetch behavior</li>
    </ul>

</div>
