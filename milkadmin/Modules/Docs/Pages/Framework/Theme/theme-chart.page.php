<?php
namespace Modules\Docs\Pages;
use App\{Route, Get};
/**
 * @title  Chart
 * @guide framework
 * @order 100
 * @tags charts, data-visualization, Chart.js, tables, datasets, graphs, statistics, data-presentation, theme-plugin, visual-data, dynamic-charts, data-tables
 */
!defined('MILK_DIR') && die(); // Avoid direct access

$data = json_decode('{"labels":[0,1,2,3],"datasets":[{"data":["78","66","42","77"],"label":"age","type":"bar"},{"data":["60","70","67","80"],"label":"weight","type":"bar"}]}', true);

?>
<div class="bg-white p-4">
    <h1>Chart</h1>

    <p class="text-muted">Revision: 2026-01-05</p>
    <p>The Chart plugin allows you to display graphs or tables with static or dynamic data.</p>
    <p>Charts are managed with the <a href="https://www.chartjs.org/" target="_blank">Chart.js</a> library and tables with a custom template class. This way the data structure is the same.</p>
    <p>For tables, datasets indicate columns, while labels indicate rows. For charts, datasets indicate series, while labels indicate categories.</p>

    <h3 class="mt-4">Quick Start (Module)</h3>
    <p>Create a module and render a chart or table directly in your page action:</p>
    <pre class="bg-light p-2"><code class="language-php">
&lt;?php
namespace Modules\ChartDemo;

use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use App\Response;
use App\Get;

class ChartDemoModule extends AbstractModule {

    protected function configure($rule): void {
        $rule->page('chart-demo')
             ->title('Chart Demo')
             ->menu('Chart Demo', '', 'bi bi-graph-up', 50)
             ->access('admin');
    }

    #[RequestAction('home')]
    public function home(): void {
        $data = [
            'labels' => ['Jan', 'Feb', 'Mar'],
            'datasets' => [
                ['label' => 'Sales', 'data' => [12, 19, 8], 'type' => 'bar'],
                ['label' => 'Leads', 'data' => [7, 11, 5], 'type' => 'bar'],
            ],
        ];

        $chart = Get::themePlugin('chart', [
            'id' => 'sales_chart',
            'type' => 'bar',
            'data' => $data,
            'options' => [
                'legend_position' => 'top',
                'start_by_zero' => true,
            ],
        ]);

        $table = Get::themePlugin('chart', [
            'id' => 'sales_table',
            'type' => 'table',
            'data' => $data,
            'options' => [
                'preset' => 'hoverable',
                'firstCellText' => '#',
            ],
        ]);

        Response::render($chart . $table);
    }
}
    </code></pre>

    <h3 class="mt-4">Data Structure</h3>
    <pre class="bg-light p-2"><code class="language-json">
{
  "labels": ["A", "B", "C"],
  "datasets": [
    { "label": "Series 1", "data": [1, 2, 3], "type": "bar" },
    { "label": "Series 2", "data": [2, 1, 4], "type": "bar" }
  ]
}
    </code></pre>

    <h3 class="mt-4">Table Options</h3>
    <ul>
        <li><code>firstCellText</code>: text for the first cell</li>
        <li><code>preset</code>: additional class for the table that defines its appearance. Possible values are <b>default, compact, dark, or hoverable</b></li>
        <li><code>showLabels</code>: show or hide labels</li>
        <li><code>cellClass</code>: array of classes applied to individual columns</li>
        <li><code>itemsPerPage</code>: rows per page (0 disables pagination)</li>
        <li><code>headerClass</code>: class for the table header</li>
    </ul>

    <h3 class="mt-4">Chart Options (Common)</h3>
    <ul>
        <li><code>legend_position</code>: top, left, bottom, right</li>
        <li><code>start_by_zero</code>: start Y axis from zero</li>
        <li><code>scale_x</code>/<code>scale_y</code>: set axis type or hide with <code>hide</code></li>
        <li><code>title_x</code>/<code>title_y</code>: axis titles</li>
        <li><code>height</code>: fixed height for the chart container (e.g. <code>260px</code>)</li>
    </ul>

    <p>You can generate a chart or table through the <code>chart</code> plugin in Ito.</p>
    <pre class="bg-light p-2"><code class="language-php">
$data = json_decode('{"labels":[0,1,2,3],"datasets":[{"data":["78","66","42","77"],"label":"age","type":"bar"},{"data":["60","70","67","80"],"label":"weight","type":"bar"}]}', true);
echo Get::themePlugin('chart', ['id'=>'table', 'type'=>'table', 'data'=> $data, 'options'=>['preset'=>'hoverable', 'firstCellText' => 'X']]);
echo Get::themePlugin('chart', ['id'=>'chart', 'type'=>'bar', 'data'=> $data ]);
    </code></pre>

    <div class="row align-items-start my-2">
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">example table </h5>
                    <h6 class="card-subtitle mb-2 text-body-secondary">table</h6>
                    <?php  echo Get::themePlugin('chart', ['id'=>'table', 'type'=>'table', 'data'=> $data, 'options'=>['preset'=>'hoverable', 'firstCellText' => '#']]);
                    ?>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Card by SQL</h5>
                    <h6 class="card-subtitle mb-2 text-body-secondary">Dynamic filterable data</h6>
                    <?php echo Get::themePlugin('chart', ['id'=>'chart', 'type'=>'bar', 'data'=> $data ]);  ?>
                </div>
            </div>
        </div>
    </div>

    <p>Table examples</p>
   <pre class="bg-light p-2"><code class="language-php">&lt;?php echo Get::themePlugin('chart', ['id'=>'noLabel', 'type'=>'table',  'data'=> $data,  
    'options'=>[
        'preset'=>'default', 
        'showLabels' => false, 
        'cellClass'=>['col-min', ''], 
        'itemsPerPage' =>2, 
        'headerClass'=>'thead-dark'
    ]]); ?&gt;</code></pre>
    <div class="row align-items-start my-2">
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Table without labels or pagination</h5>
                    <h6 class="card-subtitle mb-2 text-body-secondary"></h6>
                   <?php echo Get::themePlugin('chart', ['id'=>'noLabel', 'type'=>'table', 'data'=> $data,  'options'=>['preset'=>'default', 'showLabels' => false, 'cellClass'=>['col-min', ''], 'itemsPerPage' =>2, 'headerClass'=>'thead-dark']]); ?>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Page options</h5>
                    Choose the number of rows per page and the pagination position
                    <ul>
                        <li><code>itemsPerPage</code>: number of rows per page. 0 for no pagination</li>
                        <li><code>cellClass</code>: array of classes applied to individual columns.</li>
                        <li><code>showLabels</code>: show or hide labels</li>
                        <li><code>preset</code>: additional class for the table that defines its appearance. Possible values are <b>default, compact, dark, or hoverable</b></li>
                        <li>Special classes: for columns <code>.col-min</code> for minimum width, <code>.thead-dark</code> for dark header</li>
                    </ul>
                   
                </div>
            </div>
        </div>
    </div>

    <p>If you want to update the data after inserting the chart, you can manage the loading state and data update as shown in the example: </p>
    <pre class="bg-light p-2"><code class="language-js">
var data1 = {"labels":[0,1,2,3,4,5,6,7,8,9],"datasets":[{"data":["78","66","42","77","70","86","58","33","56","61"],"label":"age","type":"bar"},{"data":["60","70","67","80","95","71","68","65","82","84"],"label":"weight","type":"bar"}, {"data":["160","170","167","180","195","171","168","165","182","184"],"label":"height","type":"bar"}]}
function updateData(el) {
    el.disabled = true;
    itoCharts.getLoader('table').show();
    itoCharts.getLoader('chart').show();
    setTimeout(() => {
        itoCharts.update('table', data1);
        itoCharts.update('chart', data1);
        itoCharts.getLoader('table').hide();
        itoCharts.getLoader('chart').hide();
        el.disabled = false;
    }, 3000);
}
    </code></pre>
    <div class="row my-2">
        <div class="col">
            <div class="card text-left">
                <div class="card-body">
                    <button class="btn btn-primary" onclick="updateData(this)">Insert Data</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row align-items-start my-2">
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">example table </h5>
                    <h6 class="card-subtitle mb-2 text-body-secondary">table</h6>
                   <?php echo Get::themePlugin('chart', ['id'=>'myTableData', 'type'=>'table', 'data'=> [], 'options'=>['preset'=>'hoverable', 'firstCellText' => 'X']]); ?>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Card by SQL</h5>
                    <h6 class="card-subtitle mb-2 text-body-secondary">Dynamic filterable data</h6>
                    <?php echo Get::themePlugin('chart', ['id'=>'myChart', 'type'=>'bar', 'data'=> []]); ?>
                </div>
            </div>
        </div>
    </div>
    <?php

?>
   
</div>  

<script>

var data1 = {"labels":[0,1,2,3,4,5,6,7,8,9],"datasets":[{"data":["78","66","42","77","70","86","58","33","56","61"],"label":"age","type":"bar"},{"data":["60","70","67","80","95","71","68","65","82","84"],"label":"weight","type":"bar"}, {"data":["160","170","167","180","195","171","168","165","182","184"],"label":"height","type":"bar"}]}

function updateData(el) {
    el.disabled = true;
    itoCharts.getLoader('myTableData').show();
    itoCharts.getLoader('myChart').show();
    setTimeout(() => {
        itoCharts.update('myTableData', data1);
        itoCharts.update('myChart', data1);
        itoCharts.getLoader('myTableData').hide();
        itoCharts.getLoader('myChart').hide();
        el.disabled = false;
    }, 3000);
  
}
</script>
