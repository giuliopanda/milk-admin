<?php
namespace Modules\docs;
use MilkCore\Get;
/**
 * @title  Chart
 * @category hidden
 * @order 100
 * @tags 
 */
!defined('MILK_DIR') && die(); // Avoid direct access

$data = json_decode('{"labels":[0,1,2,3],"datasets":[{"data":["78","66","42","77"],"label":"age","type":"bar"},{"data":["60","70","67","80"],"label":"weight","type":"bar"}]}', true);

?>
<div class="bg-white p-4">
    <h1>Chart</h1>

    <p>Il plugin Chart permette di visualizzare grafici o tabelle con dati statici o dinamici.</p>
    <p>I grafici vengono gestiti con la libreria <a href="https://www.chartjs.org/" target="_blank">Chart.js</a> e le tabelle con una classe personalizzata del template. In questo modo la struttura dei dati è la stessa.</p>
    <p>Per le tabelle i datasets indicano le colonne, mentre i labels le righe. Per i grafici i datasets indicano le serie, mentre i labels le categorie.</p>
    <p>Le opzioni personalizzate per le tabelle sono:
        <ul>
            <li><code>firstCellText</code>: testo della prima cella</li>
            <li><code>preset</code>: classe aggiuntiva per la tabella e ne definisce la grafica. I possibili valori sono <b>default, compact, dark, o hoverable</b></li>
        </ul>
    <p>Puoi generare un grafico o una tabella tramite il plugin <code>chart</code> di Ito.</p>
    <pre class="bg-light p-2"><code class="language-php">
$data = json_decode('{"labels":[0,1,2,3],"datasets":[{"data":["78","66","42","77"],"label":"age","type":"bar"},{"data":["60","70","67","80"],"label":"weight","type":"bar"}]}', true);
echo Get::theme_plugin('chart', ['id'=>'table', 'type'=>'table', 'data'=> $data, 'options'=>['preset'=>'hoverable', 'firstCellText' => 'X']]);
echo Get::theme_plugin('chart', ['id'=>'chart', 'type'=>'bar', 'data'=> $data ]);
    </code></pre>

    <div class="row align-items-start my-2">
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">example table </h5>
                    <h6 class="card-subtitle mb-2 text-muted">table</h6>
                    <?php  echo Get::theme_plugin('chart', ['id'=>'table', 'type'=>'table', 'data'=> $data, 'options'=>['preset'=>'hoverable', 'firstCellText' => '#']]);
                    ?>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Card by SQL</h5>
                    <h6 class="card-subtitle mb-2 text-muted">Dati dinamici filtrabili</h6>
                    <?php echo Get::theme_plugin('chart', ['id'=>'chart', 'type'=>'bar', 'data'=> $data ]);  ?>
                </div>
            </div>
        </div>
    </div>


    <p>Esempi di tabelle</p>
   <pre class="bg-light p-2"><code class="language-php">&lt;?php echo Get::theme_plugin('chart', ['id'=>'noLabel', 'type'=>'table',  'data'=> $data,  
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
                    <h5 class="card-title">Tabella senza label o paginazione</h5>
                    <h6 class="card-subtitle mb-2 text-muted"></h6>
                   <?php echo Get::theme_plugin('chart', ['id'=>'noLabel', 'type'=>'table', 'data'=> $data,  'options'=>['preset'=>'default', 'showLabels' => false, 'cellClass'=>['col-min', ''], 'itemsPerPage' =>2, 'headerClass'=>'thead-dark']]); ?>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Opzioni per le pagine</h5>
                    scegliere il numero di righe per pagina e la posizione della paginazione
                    <ul>
                        <li><code>itemsPerPage</code>: numero di righe per pagina. 0 senza paginazione</li>
                        <li><code>cellClass</code>: array di classi che si applicano alle singole colonne.</li>
                        <li><code>showLabels</code>: mostra o nasconde le label</li>
                        <li><code>preset</code>: classe aggiuntiva per la tabella e ne definisce la grafica. I possibili valori sono <b>default, compact, dark, o hoverable</b></li>
                        <li>Classi speciali: per le colonne <code>.col-min</code> per la larghezza minima, <code>.thead-dark</code> per l'header scuro</li>
                    </ul>
                   
                </div>
            </div>
        </div>
    </div>

    <p>Se si voglio aggiornare i dati dopo l'inserimento del grafico è possibile gestire il loading state e l'aggiornamento dei dati come da esempio: </p>
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
    }, 5000);
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
                    <h6 class="card-subtitle mb-2 text-muted">table</h6>
                   <?php echo Get::theme_plugin('chart', ['id'=>'myTableData', 'type'=>'table', 'data'=> [], 'options'=>['preset'=>'hoverable', 'firstCellText' => 'X']]); ?>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Card by SQL</h5>
                    <h6 class="card-subtitle mb-2 text-muted">Dati dinamici filtrabili</h6>
                    <?php echo Get::theme_plugin('chart', ['id'=>'myChart', 'type'=>'bar', 'data'=> []]); ?>
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
    }, 5000);
  
}
</script>