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
        ->floating(false)
        ->layout('inline');

$response = array_merge($this->getCommonData(), $chart->getResponse());
$response['search_html'] = '';
$response['bottom_content'] = $search->render();

Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);</code></pre>

    <h2>Chart Tables</h2>
    <p>You can render a table from the same chart data. Set <code>type</code> to <code>table</code> and disable pagination with <code>itemsPerPage = 0</code>.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$data = $chart->getChartData();

echo Get::themePlugin('chart', [
    'id' => 'orders_table',
    'type' => 'table',
    'data' => $data,
    'options' => [
        'preset' => 'hoverable',
        'itemsPerPage' => 0,
    ],
]);</code></pre>

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
