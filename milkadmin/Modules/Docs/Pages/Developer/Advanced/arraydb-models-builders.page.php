<?php
namespace Modules\Docs\Pages;
/**
 * @title ArrayDB with Models and Builders
 * @category Advanced
 * @order 8
 * @tags arraydb, arrayquery, model, builder, tablebuilder, listbuilder, get, addTable, db(array)
 */

use Modules\Docs\ArrayDbDocsService;

!defined('MILK_DIR') && die(); // Avoid direct access
$docTableHtml = ArrayDbDocsService::renderTable();
?>
<div class="bg-white p-4">

<h1>ArrayDB with Models and Builders</h1>
<p class="text-muted">Revision: 2026/01/20</p>

<p class="lead">
    This guide shows how to use ArrayDB with Models and Builders.
</p>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>Note:</strong> ArrayDB is in-memory and uses the <code>Get::arrayDb()</code> singleton.
    Data should be populated (or repopulated) at the start of each request.
</div>

<hr>

<h2>1) Populate ArrayDB in the module</h2>
<p>
    ArrayDB is a singleton accessible with <code>Get::arrayDb()</code>, so in-memory data can be
    initialized anywhere (modules, services, bootstrap, controller). Seeding must happen before
    using Models or Builders, so they find tables already populated.
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Get;

$db = Get::arrayDb();

if (!in_array('documentation_products', $db->getTables(), true)) {
    $db->addTable('documentation_products', [
        ['ID_PRODUCT' => 1, 'NAME' => 'Notebook', 'CATEGORY' => 'Electronics', 'PRICE' => 999.90, 'STATUS' => 'ACTIVE'],
        ['ID_PRODUCT' => 2, 'NAME' => 'Mouse', 'CATEGORY' => 'Electronics', 'PRICE' => 24.50, 'STATUS' => 'ACTIVE'],
        ['ID_PRODUCT' => 3, 'NAME' => 'Desk', 'CATEGORY' => 'Office', 'PRICE' => 189.00, 'STATUS' => 'INACTIVE'],
    ], 'ID_PRODUCT');
}</code></pre>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Note:</strong> data configuration can be done anywhere in the site.
    If you need persistence, you can serialize data to a file and reload it on startup
    (optional capability).
</div>

<hr>

<h2>2) Configure the Model for ArrayDB</h2>
<p>
    In the Model, set the table and enable the ArrayDB connection with <code>db('array')</code>.
    The rest of the configuration is the same as standard Models.
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Local\Modules\DocumentationProducts;

use App\Abstracts\AbstractModel;

class DocumentationProductsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('documentation_products')
            ->id('ID_PRODUCT')
            ->db('array')
            ->title('NAME', 100)->label('Name')->required()
            ->string('CATEGORY', 50)->label('Category')
            ->decimal('PRICE', 10, 2)->label('Price')
            ->string('STATUS', 10)->label('Status');
    }
}</code></pre>

<hr>

<h2>3) Module with table and chart</h2>
<p>
    Builders work with ArrayDB like any other database. Here we show a table and a chart
    on the same page. For JSON reloads, change the builder action with
    <code>setRequestAction()</code> and handle that action in the controller/module.
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Attributes\RequestAction;
use App\Response;
use Builders\{TableBuilder, ChartBuilder};

private const TABLE_ID = 'idTableDocumentationProducts';
private const CHART_ID = 'idChartDocumentationProducts';
private const TABLE_ACTION = 'documentation-products-table';
private const CHART_ACTION = 'documentation-products-chart';

#[RequestAction('home')]
public function home(): void
{
    $table = $this->buildTable(self::TABLE_ID);
    $chart = $this->buildChart(self::CHART_ID);

    $this->respondPartial([
        'table_id' => [self::TABLE_ID => fn() => $table->getResponse()],
        'chart_id' => [self::CHART_ID => fn() => $chart->getResponse()],
    ]);

    $response = $this->getCommonData();
    $response['table_html'] = $table->render();
    $response['chart_html'] = $chart->render();

    Response::render(__DIR__ . '/Views/DocumentationProductsView.php', $response);
}

#[RequestAction(self::TABLE_ACTION)]
public function tableJson(): void
{
    $response = $this->buildTable(self::TABLE_ID)->getResponse();
    Response::htmlJson($response);
}

#[RequestAction(self::CHART_ACTION)]
public function chartJson(): void
{
    $response = $this->buildChart(self::CHART_ID)->getResponse();
    Response::htmlJson($response);
}</code></pre>

<p class="mt-2">
    To update the table via JSON, the client sends:
    <code>?page=documentation-products&action=documentation-products-table&table_id=idTableDocumentationProducts</code>.
    The same pattern applies to the chart using <code>action=documentation-products-chart</code> and <code>chart_id</code>.
</p>
<p class="mt-2">
    On this documentation page the action is:
    <code>?page=docs&action=arraydb-models-builders-table&table_id=idTableDocumentationProductsExample</code>.
</p>

<h3 class="mt-3">View: table and chart visible</h3>
<p>
    In the page template, just print the two HTML outputs generated by the builders.
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// DocumentationProductsView.php
?&gt;
&lt;div class="container py-3"&gt;
    &lt;div class="row g-3"&gt;
        &lt;div class="col-12 col-lg-6"&gt;
            &lt;h4 class="mb-2"&gt;Chart&lt;/h4&gt;
            &lt;?php echo $chart_html ?? ''; ?&gt;
        &lt;/div&gt;
        &lt;div class="col-12 col-lg-6"&gt;
            &lt;h4 class="mb-2"&gt;Table&lt;/h4&gt;
            &lt;?php echo $table_html ?? ''; ?&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>

<h3 class="mt-3">Real table example (TableBuilder)</h3>
<p>
    This table is generated by <code>TableBuilder::render()</code> using ArrayDB.
</p>

<?php echo $docTableHtml; ?>

<div class="alert alert-success">
    <i class="bi bi-check-circle"></i>
    <strong>Tip:</strong> you can use <code>ListBuilder</code> instead of <code>TableBuilder</code>
    with the same rules. The model stays the same, only the builder changes.
</div>

<hr>

<h2>4) Configure table and chart</h2>
<p>
    The table and chart share the same ArrayDB model. Set the action for JSON reloads
    with <code>setRequestAction()</code>.
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">private function buildTable(string $table_id): TableBuilder
{
    return TableBuilder::create($this->model, $table_id)
        ->setRequestAction(self::TABLE_ACTION)
        ->field('NAME')->label('Name')
        ->field('CATEGORY')->label('Category')
        ->field('PRICE')->label('Price')
        ->field('STATUS')->label('Status');
}

private function buildChart(string $chart_id): ChartBuilder
{
    return ChartBuilder::create($this->model, $chart_id)
        ->setRequestAction(self::CHART_ACTION)
        ->select(['CATEGORY AS label', 'SUM(PRICE) AS value'])
        ->structure([
            'label' => ['label' => 'Category', 'axis' => 'x'],
            'value' => ['label' => 'Total', 'axis' => 'y'],
        ])
        ->groupBy('CATEGORY')
        ->orderBy('label', 'ASC');
}</code></pre>

<hr>

<h2>5) Filters and queries</h2>
<p>
    Builder filters and model queries work the same way. Example with a column filter:
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$tableBuilder->filter('only_active', function($query, $value) {
    if ($value === 'ACTIVE') {
        $query->where('STATUS = ?', ['ACTIVE']);
    }
});</code></pre>

</div>
