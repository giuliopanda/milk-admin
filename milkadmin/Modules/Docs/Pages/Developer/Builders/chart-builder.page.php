<?php
namespace Modules\Docs\Pages;
/**
 * @title ChartBuilder
 * @guide developer
 * @order 40
 * @tags ChartBuilder, charts, chart, builder, GetDataBuilder, query, filters, SearchBuilder, ajax, chartjs, table-chart
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>ChartBuilder</h1>

    <p>The ChartBuilder class creates charts from database queries and integrates with the same filtering system used by tables and lists. It extends <code>GetDataBuilder</code>, but it does not paginate or limit results, so charts can aggregate the full dataset.</p>

    <h2>System Overview</h2>
    <p>ChartBuilder simplifies chart rendering by providing:</p>
    <ul>
        <li><strong>Query-driven charts</strong>: Use the fluent <code>GetDataBuilder</code> API to filter and aggregate</li>
        <li><strong>Structured datasets</strong>: Define axes and datasets with <code>structure()</code></li>
        <li><strong>Chart.js integration</strong>: Render bar, line, pie, table, and other chart types</li>
        <li><strong>AJAX updates</strong>: <code>getResponse()</code> returns chart payloads for live updates</li>
        <li><strong>No pagination</strong>: The full query result is used to build the chart</li>
    </ul>

    <h2>Basic Usage</h2>
    <h3>Quick Start</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Builders\ChartBuilder;

$chart = ChartBuilder::create($this->model, 'orders_chart')
    ->select(['month', 'SUM(total) AS total'])
    ->groupBy('month')
    ->orderBy('month', 'ASC')
    ->structure([
        'month' => ['label' => 'Month', 'axis' => 'x'],
        'total' => ['label' => 'Total', 'type' => 'bar'],
    ])
    ->type('bar')
    ->render();

echo $chart;</code></pre>

    <h2>Public Methods</h2>
    <p>Main public methods for <code>ChartBuilder</code>. Query/filter methods like <code>select()</code>, <code>where()</code>, <code>groupBy()</code>, <code>having()</code>, and <code>filter()</code> are inherited from <code>GetDataBuilder</code>.</p>

    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>create()</code></td>
                <td><code>AbstractModel|string $model, string $table_id, ?array $request = null</code></td>
                <td>Factory method to create a new builder instance.</td>
            </tr>
            <tr>
                <td><code>structure()</code></td>
                <td><code>array $structure</code></td>
                <td>Defines X axis and datasets mapping from query result fields.</td>
            </tr>
            <tr>
                <td><code>type()</code></td>
                <td><code>string $type</code></td>
                <td>Sets output renderer (<code>bar</code>, <code>line</code>, <code>pie</code>, <code>table</code>, etc.).</td>
            </tr>
            <tr>
                <td><code>options()</code></td>
                <td><code>array $options</code></td>
                <td>Replaces all chart/plugin options.</td>
            </tr>
            <tr>
                <td><code>option()</code></td>
                <td><code>string $key, mixed $value</code></td>
                <td>Sets a single option key.</td>
            </tr>
            <tr>
                <td><code>setCanvasId()</code></td>
                <td><code>string $canvas_id</code></td>
                <td>Sets explicit canvas id to avoid collisions on pages with many charts.</td>
            </tr>
            <tr>
                <td><code>getChartData()</code></td>
                <td><code>none</code></td>
                <td>Returns normalized Chart.js payload: <code>labels</code> + <code>datasets</code>.</td>
            </tr>
            <tr>
                <td><code>render()</code></td>
                <td><code>none</code></td>
                <td>Returns chart HTML wrapper and plugin markup.</td>
            </tr>
            <tr>
                <td><code>getResponse()</code></td>
                <td><code>none</code></td>
                <td>Returns AJAX payload with <code>html</code> and <code>chart</code> metadata/data.</td>
            </tr>
            <tr>
                <td><code>orderBy()</code></td>
                <td><code>string $field, string $direction = 'ASC'</code></td>
                <td>Applies ordering without table pagination side effects.</td>
            </tr>
            <tr>
                <td><code>limit()</code></td>
                <td><code>int $limit</code></td>
                <td>Ignored for charts (method keeps query unlimited by design).</td>
            </tr>
            <tr>
                <td><code>createSearchBuilder()</code></td>
                <td><code>none</code></td>
                <td>Creates a linked <code>SearchBuilder</code> for filters.</td>
            </tr>
        </tbody>
    </table>

    <h2>Structure Mapping</h2>
    <p><code>structure()</code> maps fields from the SQL result to chart axes and datasets. You must define exactly one X axis; all other fields become datasets.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$chart = ChartBuilder::create($this->model, 'sales_chart')
    ->select(['month', 'SUM(total) AS total', 'COUNT(id) AS orders'])
    ->groupBy('month')
    ->orderBy('month', 'ASC')
    ->structure([
        'month' => ['label' => 'Month', 'axis' => 'x'],
        'total' => [
            'label' => 'Total',
            'type' => 'bar',
            'backgroundColor' => '#9BD0F5',
            'borderColor' => '#36A2EB',
            'borderWidth' => 1,
        ],
        'orders' => ['label' => 'Orders', 'type' => 'line'],
    ]);</code></pre>

    <h2>Chart Options</h2>
    <p>Use <code>options()</code> or <code>option()</code> to pass Chart.js options or plugin-specific settings (like height or legend positioning).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$chart = ChartBuilder::create($this->model, 'orders_chart')
    ->option('height', 320)
    ->option('legend_position', 'bottom')
    ->option('show_datalabels', 'percent');</code></pre>

    <h3>Wrapper Options</h3>
    <p>By default, the chart plugin wraps the canvas with:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;div class="chart-body" style="height: ...; overflow:auto"&gt;...&lt;/div&gt;</code></pre>
    <p>If you need to disable this wrapper (for custom layout or table rendering), set <code>wrap_chart_body</code> to <code>false</code>.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$chart = ChartBuilder::create($this->model, 'orders_table')
    ->type('table')
    ->option('wrap_chart_body', false)
    ->options([
        'itemsPerPage' => 0,
        'preset' => 'hoverable',
    ]);</code></pre>

    <p>Legacy alias supported: <code>remove_chart_body = true</code>.</p>

    <h2>Using Filters with SearchBuilder</h2>
    <p>ChartBuilder uses the same filter system as tables. Filter names must match between <code>SearchBuilder</code> and <code>ChartBuilder</code>.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Builders\ChartBuilder;
use Builders\SearchBuilder;
use App\Response;

$chart_id = 'orders_chart';

$chart = ChartBuilder::create($this->model, $chart_id)
    ->setRequestAction('home')
    ->select(['month', 'SUM(total) AS total'])
    ->filter('status_filter', function($query, $value) {
        $query->where('status = ?', [$value]);
    })
    ->groupBy('month')
    ->orderBy('month', 'ASC')
    ->structure([
        'month' => ['label' => 'Month', 'axis' => 'x'],
        'total' => ['label' => 'Total', 'type' => 'bar'],
    ]);

$search = SearchBuilder::create($chart_id)
    ->select('status_filter')
        ->label('Status')
        ->options([
            '' => 'All',
            'paid' => 'Paid',
            'pending' => 'Pending',
        ])
        ->layout('inline');

$response = array_merge($this->getCommonData(), $chart->getResponse());
$response['search_html'] = '';
$response['bottom_content'] = $search->render();

Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);</code></pre>

    <h2>Chart Tables</h2>
    <p>You can render a table from the same chart data. Set <code>type</code> to <code>table</code>.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$data = $chart->getChartData();

echo Get::themePlugin('chart', [
    'id' => 'orders_table',
    'type' => 'table',
    'data' => $data,
    'options' => [
        'preset' => 'hoverable',
        'itemsPerPage' => 0,
        'sortableTable' => true,
    ],
]);</code></pre>

    <h3>Table Options</h3>
    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Option</th>
                <th>Type</th>
                <th>Default</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>itemsPerPage</code></td>
                <td><code>int</code></td>
                <td><code>5</code></td>
                <td>Table pagination size. Use <code>0</code> to disable pagination and show all rows.</td>
            </tr>
            <tr>
                <td><code>sortableTable</code></td>
                <td><code>bool</code></td>
                <td><code>true</code></td>
                <td>Enables header click sorting. Works independently from pagination (also when <code>itemsPerPage = 0</code>).</td>
            </tr>
            <tr>
                <td><code>preset</code></td>
                <td><code>string</code></td>
                <td><code>default</code></td>
                <td>Visual preset for table style (<code>default</code>, <code>compact</code>, <code>dark</code>, <code>hoverable</code>).</td>
            </tr>
            <tr>
                <td><code>firstCellText</code></td>
                <td><code>string</code></td>
                <td><code>''</code></td>
                <td>Custom text for the top-left header cell.</td>
            </tr>
        </tbody>
    </table>

    <h2>AJAX Response</h2>
    <p><code>ChartBuilder::getResponse()</code> returns an array with <code>html</code> and <code>chart</code> keys so the frontend can update the chart without a full page reload.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$response = $chart->getResponse();

// $response['html'] contains the chart wrapper
// $response['chart'] contains { id, type, data, options }</code></pre>

    <h2>Multiple Charts on One Page</h2>
    <p>If you render multiple charts in the same view, set a custom canvas id so Chart.js instances do not collide.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$chart = ChartBuilder::create($this->model, 'orders_chart')
    ->setCanvasId('orders_chart_canvas')
    ->render();</code></pre>
</div>
