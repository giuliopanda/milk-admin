<?php
namespace Modules\Docs\Pages;
/**
 * @title Simple Analytics Module Example
 * @category Advanced
 * @order 10
 * @tags analytics, dataset, dataseries, reports, chartbuilder, module-example, arraydb
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Simple Analytics Module Example</h1>
<p class="text-muted">Revision: 2026/03/09</p>

<p class="lead">
    This guide shows a simplified version of the <code>FakeAnalytics</code> approach:
    create fake data, transform it with <code>App\Analytics</code> classes, and render two charts.
</p>

<div class="alert alert-info">
    <strong>Goal:</strong> keep the module small while expressing the core concepts:
    <code>DataSet</code> for grouping/aggregation, <code>DataSeries</code> for time-series transforms,
    and <code>ChartBuilder</code> for visualization.
</div>

<h2>1) Minimal Structure</h2>
<pre class="border p-2 bg-light"><code>milkadmin_local/Modules/SimpleAnalytics/
├── SimpleAnalyticsModule.php
├── SimpleAnalyticsDailyModel.php
├── SimpleAnalyticsChannelModel.php
└── Views/
    └── simple-analytics.page.php</code></pre>

<h2>2) Models (Array DB)</h2>
<p>Use <code>-&gt;db('array')</code> because data is generated in memory.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Local\Modules\SimpleAnalytics;

use App\Abstracts\AbstractModel;

class SimpleAnalyticsDailyModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table(\'simple_analytics_daily\')
            ->id(\'id\')
            ->db(\'array\')
            ->string(\'day\', 10)->label(\'Day\')
            ->decimal(\'revenue\', 12, 2)->label(\'Revenue\')
            ->decimal(\'rolling_7d\', 12, 2)->label(\'Rolling 7d\');
    }
}
'); ?></code></pre>

<h2>3) Module: Generate + Analyze + Render</h2>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Local\Modules\SimpleAnalytics;

use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use App\Analytics\DataSet;
use App\Analytics\DataSeries;
use App\Get;
use App\Response;
use Builders\ChartBuilder;

class SimpleAnalyticsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page(\'simple-analytics\')
            ->title(\'Simple Analytics\')
            ->menu(\'Simple Analytics\', \'\', \'bi bi-graph-up\', 70)
            ->access(\'public\');
    }

    #[RequestAction(\'home\')]
    public function home(): void
    {
        $this->seedTables();

        $dailyChart = ChartBuilder::create(new SimpleAnalyticsDailyModel(), \'sa_daily\')
            ->select([\'day\', \'revenue\', \'rolling_7d\'])
            ->orderBy(\'id\', \'ASC\')
            ->structure([
                \'day\' => [\'label\' => \'Day\', \'axis\' => \'x\'],
                \'revenue\' => [\'label\' => \'Revenue\', \'type\' => \'line\'],
                \'rolling_7d\' => [\'label\' => \'Rolling 7d\', \'type\' => \'line\'],
            ])
            ->type(\'line\')
            ->render();

        $channelChart = ChartBuilder::create(new SimpleAnalyticsChannelModel(), \'sa_channel\')
            ->select([\'channel\', \'net_revenue\'])
            ->orderBy(\'net_revenue\', \'DESC\')
            ->structure([
                \'channel\' => [\'label\' => \'Channel\', \'axis\' => \'x\'],
                \'net_revenue\' => [\'label\' => \'Net Revenue\', \'type\' => \'bar\'],
            ])
            ->type(\'bar\')
            ->render();

        Response::themePage(\'default\', __DIR__.\'/Views/simple-analytics.page.php\', [
            \'daily_chart_html\' => $dailyChart,
            \'channel_chart_html\' => $channelChart,
        ]);
    }

    private function seedTables(): void
    {
        // raw daily rows
        $raw = [];
        $start = new \DateTimeImmutable(\'2025-01-01\');
        for ($i = 0; $i < 45; $i++) {
            $day = $start->modify("+{$i} days")->format(\'Y-m-d\');
            $raw[] = [
                \'day\' => $day,
                \'channel\' => [\'Organic\', \'Paid\', \'Email\'][$i % 3],
                \'revenue\' => 800 + (($i * 37) % 250),
                \'refunds\' => 20 + ($i % 12),
            ];
        }

        // 1) DataSet: group and aggregate
        $daily = DataSet::fromRows($raw)
            ->groupBy(\'day\', \'day\')
            ->aggregate(\'revenue\', \'sum\', \'revenue\')
            ->aggregate(\'refunds\', \'sum\', \'refunds\')
            ->sortBy(\'day\', \'asc\')
            ->toRows(false);

        // 2) DataSeries: rolling average on daily revenue
        $series = array_map(fn($r) => [\'bucket\' => $r[\'day\'], \'value\' => $r[\'revenue\']], $daily);
        $rolling = DataSeries::rollingAverage($series, 7);
        $rollingMap = [];
        foreach ($rolling as $point) {
            $rollingMap[$point[\'bucket\']] = $point[\'value\'];
        }

        $dailyTable = [];
        $id = 1;
        foreach ($daily as $row) {
            $dailyTable[] = [
                \'id\' => $id++,
                \'day\' => $row[\'day\'],
                \'revenue\' => (float) $row[\'revenue\'],
                \'rolling_7d\' => isset($rollingMap[$row[\'day\']]) ? (float) $rollingMap[$row[\'day\']] : null,
            ];
        }

        // channel summary
        $channelTable = DataSet::fromRows($raw)
            ->map(fn($r) => $r + [\'net_revenue\' => $r[\'revenue\'] - $r[\'refunds\']])
            ->groupBy(\'channel\', \'channel\')
            ->aggregate(\'net_revenue\', \'sum\', \'net_revenue\')
            ->sortBy(\'net_revenue\', \'desc\')
            ->toRows(false);

        // expose tables to ArrayDb
        $db = Get::arrayDb();
        $db->addTable(\'simple_analytics_daily\', $dailyTable, \'id\');
        $db->addTable(\'simple_analytics_channel\', $channelTable, \'channel\');
    }
}
'); ?></code></pre>

<h2>4) Why This Works</h2>
<ul>
    <li><strong>DataSet</strong>: transforms row collections with readable pipelines.</li>
    <li><strong>GroupedDataSet::aggregate()</strong>: makes totals and KPIs per group without custom loops.</li>
    <li><strong>DataSeries</strong>: handles time operations like rolling windows and gap filling.</li>
    <li><strong>ChartBuilder</strong>: keeps chart rendering declarative and consistent with filters/reporting flows.</li>
</ul>

<h2>5) From Module to Reports</h2>
<p>
    The same pattern can feed the Reports module:
    seed or query rows, transform with <code>App\Analytics</code>, then expose normalized tables
    that Reports can aggregate and visualize.
</p>

<div class="alert alert-success">
    <strong>Tip:</strong> start with one daily table and one dimension table, then iterate.
    This keeps the first version simple and debuggable.
</div>

</div>

