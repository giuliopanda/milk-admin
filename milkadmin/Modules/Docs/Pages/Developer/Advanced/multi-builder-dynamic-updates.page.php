<?php
namespace Modules\Docs\Pages;
/**
 * @title Multi-Builder Page with Dynamic Updates
 * @category Advanced
 * @order 3
 * @tags builders, chart, table, list, form, fetch, ajax, dynamic-updates, reload
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Multi-Builder Page with Dynamic Updates</h1>
<p class="text-muted">Revision: 2026/01/14</p>

<p class="lead">
    Learn how to create a complex page with multiple builders (Chart, Table, List, Form) that update dynamically via fetch without reloading the page.
</p>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>What you'll learn:</strong>
    <ul class="mb-0">
        <li>How to configure multiple builders on the same page</li>
        <li>How to implement dynamic update patterns (reload)</li>
        <li>How to use SearchBuilder to filter multiple elements</li>
        <li>Common mistakes to avoid and best practices</li>
        <li>How to create custom templates for ListBuilder</li>
    </ul>
</div>

<hr>


<h3>Structure</h3>
<pre class="border p-2 bg-light"><code>milkadmin_local/Modules/UpdatePatterns/
├── UpdatePatternsModel.php       # Database model
├── UpdatePatternsModule.php      # Controller with actions
└── Views/
    ├── UpdatePatternsView.php    # Main view
    ├── custom_content.php        # Custom HTML content
    └── list-item-template.php    # Custom list template</code></pre>

<hr>

<h2>Step 1: Configure the Module</h2>

<p>Let's start by defining constants for the various builder IDs. It's important to use constants to avoid typos. Each element will have its own unique ID
    that will allow us to identify and update it independently from the others.
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Local\Modules\UpdatePatterns;

use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use App\Response;
use Builders\{ChartBuilder, TableBuilder, ListBuilder, FormBuilder, SearchBuilder};

class UpdatePatternsModule extends AbstractModule
{
    // Definisci ID come costanti per evitare errori
    private const CHART_ID = \'idUpdatePatternsChart\';
    private const TABLE_ID = \'idUpdatePatternsTable\';
    private const LIST_ID = \'idUpdatePatternsList\';
    private const FORM_ID = \'formUpdatePatterns\';

    protected function configure($rule): void
    {
        $rule->page(\'updatepatterns\')
            ->title(\'Update Patterns Test\')
            ->menu(\'Update Patterns\', \'\', \'bi bi-arrow-clockwise\', 80)
            ->access(\'public\')
            ->version(260114);
    }
}'); ?></code></pre>


<h2>Step 2: Main Action (home)</h2>

<p>The main action creates all the builders, handles partial responses for AJAX, and renders the view.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
#[RequestAction(\'home\')]
public function home(): void
{
    // Create all builders
    $chartBuilder = $this->buildChart(self::CHART_ID);
    $tableBuilder = $this->buildTable(self::TABLE_ID);
    $listBuilder = $this->buildList(self::LIST_ID);
    $formBuilder = $this->buildForm(self::FORM_ID);

    // Handle partial requests for Chart, Table, List
    $this->respondPartial([
        \'chart_id\' => [self::CHART_ID => fn() => $chartBuilder->getResponse()],
        \'table_id\' => [self::TABLE_ID => fn() => $tableBuilder->getResponse()],
        \'list_id\' => [self::LIST_ID => fn() => $listBuilder->getResponse()],
    ]);

    // SearchBuilder shared between chart, table and list
    $search = SearchBuilder::create([self::CHART_ID, self::TABLE_ID, self::LIST_ID])
        ->setWrapperClass(\'d-flex align-items-center gap-2\')
        ->select(\'category_filter\')
            ->label(\'Category\')
            ->options([\'\' => \'All\', \'A\' => \'Category A\', \'B\' => \'Category B\'])
            ->layout(\'inline\');

    // Prepare data for the view
    $response = $this->getCommonData();
    $response[\'chart_html\'] = $chartBuilder->render();
    $response[\'table_html\'] = $tableBuilder->render();
    $response[\'list_html\'] = $listBuilder->render();
    $response[\'form_html\'] = $formBuilder->render();
    $response[\'search_html\'] = $search->render();

    Response::render(__DIR__ . \'/Views/UpdatePatternsView.php\', $response);
}

// Helper to handle partial AJAX responses
private function respondPartial(array $routes): void
{
    foreach ([\'chart_id\', \'table_id\', \'list_id\'] as $request_key) {
        $requested_id = $_REQUEST[$request_key] ?? \'\';
        if ($requested_id && isset($routes[$request_key][$requested_id])) {
            Response::htmlJson(($routes[$request_key][$requested_id])());
        }
    }
}'); ?></code></pre>

<div class="alert alert-success">
    <i class="bi bi-check-circle"></i>
    <strong>Best Practice:</strong> Use <code>respondPartial()</code> to handle AJAX requests from builders.
    This pattern allows Chart, Table, and List to reload independently when filters change.
</div>

<hr>

<h2>Step 3: Configure ChartBuilder</h2>

<p>ChartBuilder requires data aggregation to generate the chart.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
private function buildChart(string $chart_id): ChartBuilder
{
    return ChartBuilder::create($this->model, $chart_id)
        ->setRequestAction(\'home\')  // Action for AJAX reload
        ->select([
            \'category AS label\',
            \'SUM(value) AS value\',
            \'MIN(id) AS sort_id\'
        ])
        ->filter(\'category_filter\', function($query, $value) {
            if ($value !== \'\') $query->where(\'category = ?\', [$value]);
        })
        ->groupBy(\'category\')
        ->orderBy(\'sort_id\', \'ASC\')
        ->structure([
            \'label\' => [\'label\' => \'Category\', \'axis\' => \'x\'],
            \'value\' => [\'label\' => \'Total\', \'type\' => \'bar\', \'color\' => \'#0d6efd\'],
        ]);
}'); ?></code></pre>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Common Error #2 - Canvas Double Initialization:</strong> If you use ChartBuilder, DO NOT add inline scripts
    to initialize the chart. The framework has an automatic system (<code>ChartComponent.js</code>) that handles
    initialization via <code>.js-chart-container</code>. Adding duplicate scripts causes the error
    "Canvas is already in use".
</div>

<hr>

<h2>Step 4: Configure TableBuilder</h2>

<p>TableBuilder is the simplest, it displays data in tabular format.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
private function buildTable(string $table_id): TableBuilder
{
    return TableBuilder::create($this->model, $table_id)
        ->setRequestAction(\'home\')
        ->filter(\'category_filter\', function($query, $value) {
            if ($value !== \'\') $query->where(\'category = ?\', [$value]);
        })
        ->orderBy(\'id\', \'DESC\')
        ->limit(5)
        ->resetFields()
        ->field(\'id\')->label(\'#\')
        ->field(\'label\')->label(\'Label\')
        ->field(\'category\')->label(\'Category\')
        ->field(\'value\')->label(\'Value\')
        ->field(\'data\')->label(\'Date\');
}'); ?></code></pre>

<hr>

<h2>Step 5: Configure ListBuilder</h2>

<p>ListBuilder displays data as a Bootstrap list. It can use custom templates.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
private function buildList(string $list_id): ListBuilder
{
    return ListBuilder::create($this->model, $list_id)
        ->setRequestAction(\'home\')
        ->filter(\'category_filter\', function($query, $value) {
            if ($value !== \'\') $query->where(\'category = ?\', [$value]);
        })
        ->orderBy(\'id\', \'DESC\')
        ->limit(3)
        ->resetFields()
        ->field(\'label\')->label(\'\')
        ->field(\'category\')->label(\'\')
        ->setBoxTemplate(__DIR__ . \'/Views/list-item-template.php\');
}'); ?></code></pre>

<h3>Custom Template for ListBuilder</h3>

<p>Create a file <code>Views/list-item-template.php</code> to customize the layout of each item:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
/**
 * Available variables:
 * @var object $row - The current row from the database
 * @var array $fields_data - Formatted data [col_name => (object) [\'label\', \'value\', \'type\']]
 * @var string $primary - Name of the primary key field
 */

$label = $fields_data[\'label\']->value ?? \'\';
$category = $fields_data[\'category\']->value ?? \'\';
$id_value = isset($row->$primary) ? $row->$primary : \'\';
?>

<div class="list-group-item d-flex justify-content-between align-items-center">
    <div>
        <strong><?php _p($label); ?></strong>
        <?php if ($category): ?>
            <small class="text-muted ms-2"><?php _p($category); ?></small>
        <?php endif; ?>
    </div>
    <span class="badge bg-primary rounded-pill">#<?php _p($id_value); ?></span>
</div>'); ?></code></pre>

<div class="alert alert-danger">
    <i class="bi bi-exclamation-circle"></i>
    <strong>Common Error #3 - Wrong Template Variables:</strong> In ListBuilder template use
    <code>$fields_data</code> and <code>$row</code>, NOT <code>$data</code>. The <code>$fields_data</code> variable
    contains already formatted values, while <code>$row</code> is the database row object.
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>Note on Default Classes:</strong> As of MilkAdmin 260114, the default classes for ListBuilder are
    <code>list-group</code> for the container and <code>col-12</code> for columns, appropriate for vertical lists.
    For grid layouts, use <code>gridColumns()</code>.
</div>

<hr>

<h2>Step 6: Configure FormBuilder</h2>

<p>FormBuilder handles data creation and editing. It requires special configuration for the action.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
private function buildForm(string $form_id): FormBuilder
{
    return FormBuilder::create($this->model, $this->page)  // IMPORTANT: use $this->page
        ->setId($form_id)                                  // Set ID separately
        ->currentAction(\'home\')                            // Set current action
        ->resetFields()
        ->field(\'label\')
            ->label(\'Label\')
            ->required()
        ->field(\'category\')
            ->label(\'Category\')
            ->formType(\'select\')
            ->options([\'A\' => \'Category A\', \'B\' => \'Category B\'])
            ->required()
        ->field(\'value\')
            ->label(\'Value\')
            ->formType(\'number\')
            ->required()
        ->addActions([
            \'save\' => [
                \'label\' => \'Save\',
                \'class\' => \'btn btn-primary\',
                \'action\' => [$this, \'saveFormAction\'],
            ],
            \'reload\' => [
                \'label\' => \'Reload\',
                \'type\' => \'button\',
                \'class\' => \'btn btn-secondary\',
                \'validate\' => false,
                \'onclick\' => \'milkFormReload(this); return false;\',
                \'action\' => FormBuilder::reloadAction(),
            ],
        ]);
}'); ?></code></pre>

<div class="alert alert-danger">
    <i class="bi bi-exclamation-circle"></i>
    <strong>Common Error #4 - FormBuilder Configuration:</strong>
    <ul class="mb-0">
        <li><strong>DO NOT</strong> pass <code>$form_id</code> as second parameter to <code>create()</code></li>
        <li>The second parameter is <code>$page</code>, use <code>$this->page</code></li>
        <li>Set the form ID with <code>->setId($form_id)</code></li>
        <li>Set the current action with <code>->currentAction('home')</code></li>
    </ul>
</div>

<div class="alert alert-success">
    <i class="bi bi-check-circle"></i>
    <strong>Best Practice:</strong> The "Reload" button uses <code>FormBuilder::reloadAction()</code> to
    restore database values without validation. Useful for canceling changes or updating calculated fields.
</div>

<hr>

<h2>Step 7: Actions for Dynamic Reload</h2>

<p>Each builder has a dedicated action that adds data and returns a reload command.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
#[RequestAction(\'reload-chart\')]
public function reloadChart(): void
{
    $this->addRandomData();  // Add new data
    Response::json([
        \'success\' => true,
        \'list\' => [\'id\' => self::CHART_ID, \'action\' => \'reload\'],
        \'toast\' => [\'message\' => \'Chart reloaded!\', \'type\' => \'success\']
    ]);
}

#[RequestAction(\'reload-table\')]
public function reloadTable(): void
{
    $this->addRandomData();
    Response::json([
        \'success\' => true,
        \'list\' => [\'id\' => self::TABLE_ID, \'action\' => \'reload\'],
        \'toast\' => [\'message\' => \'Table reloaded!\', \'type\' => \'info\']
    ]);
}

#[RequestAction(\'reload-list\')]
public function reloadList(): void
{
    $this->addRandomData();
    Response::json([
        \'success\' => true,
        \'list\' => [\'id\' => self::LIST_ID, \'action\' => \'reload\'],
        \'toast\' => [\'message\' => \'List reloaded!\', \'type\' => \'warning\']
    ]);
}

private function addRandomData(): void
{
    $item = new UpdatePatternsModel();
    $item->label = \'Test \' . rand(1, 100);
    $item->category = [\'A\', \'B\', \'C\'][array_rand([\'A\', \'B\', \'C\'])];
    $item->data = date(\'Y-m-d\');
    $item->value = rand(10, 100);
    $item->save();
}'); ?></code></pre>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>Reload Pattern:</strong> Use <code>$response['list']</code> with <code>action: 'reload'</code> to
    reload Chart, Table or List. The automatic JavaScript (<code>list.reload()</code>) handles the update
    without reloading the page.
</div>

<hr>

<h2>Step 8: View Template</h2>

<p>The view organizes the builders in a Bootstrap layout. The buttons use <code>data-fetch="post"</code> for AJAX calls.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
!defined(\'MILK_DIR\') && die();
?>
<div class="bg-white p-4">
    <h1>Update Patterns - Test Builders</h1>

    <!-- Shared SearchBuilder -->
    <div class="card mb-4">
        <div class="card-body">
            <?php echo $search_html; ?>
        </div>
    </div>

    <div class="row">
        <!-- ChartBuilder -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5>Chart</h5>
                    <a href="?page=updatepatterns&action=reload-chart"
                       data-fetch="post"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-clockwise"></i> Reload
                    </a>
                </div>
                <div class="card-body">
                    <?php echo $chart_html; ?>
                </div>
            </div>
        </div>

        <!-- TableBuilder -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5>Table</h5>
                    <a href="?page=updatepatterns&action=reload-table"
                       data-fetch="post"
                       class="btn btn-sm btn-outline-info">
                        <i class="bi bi-arrow-clockwise"></i> Reload
                    </a>
                </div>
                <div class="card-body">
                    <?php echo $table_html; ?>
                </div>
            </div>
        </div>

        <!-- ListBuilder -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5>List</h5>
                    <a href="?page=updatepatterns&action=reload-list"
                       data-fetch="post"
                       class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-arrow-clockwise"></i> Reload
                    </a>
                </div>
                <div class="card-body">
                    <?php echo $list_html; ?>
                </div>
            </div>
        </div>

        <!-- FormBuilder -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Data Entry Form</h5>
                </div>
                <div class="card-body">
                    <?php echo $form_html; ?>
                </div>
            </div>
        </div>
    </div>
</div>'); ?></code></pre>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Important:</strong> <code>data-fetch</code> works ONLY on <code>&lt;a&gt;</code> tags, NOT on
    <code>&lt;button&gt;</code>. Parameters go in the <code>href</code> attribute and are automatically
    converted to POST by the framework.
</div>

<hr>

<h2>Step 9: Form Save Action</h2>

<p>The form save action can trigger the reload of other builders.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
public function saveFormAction(FormBuilder $formBuilder, array $request): array
{
    $data = $request[\'data\'] ?? [];
    $item_id = _absint($data[\'id\'] ?? 0);

    if ($item_id > 0) {
        $item = $this->model->getById($item_id);
        if (!$item) {
            return [\'success\' => false, \'message\' => \'Record not found\'];
        }
    } else {
        $item = new UpdatePatternsModel();
    }

    $item->label = $data[\'label\'] ?? \'\';
    $item->category = $data[\'category\'] ?? \'\';
    $item->value = _absint($data[\'value\'] ?? 0);
    $item->data = $data[\'data\'] ?? date(\'Y-m-d\');

    if (!$item->save()) {
        return [\'success\' => false, \'message\' => \'Error during save\'];
    }

    // After saving, reload the table
    return [
        \'success\' => true,
        \'message\' => \'Saved successfully!\',
        \'list\' => [\'id\' => self::TABLE_ID, \'action\' => \'reload\'],
    ];
}'); ?></code></pre>

<div class="alert alert-success">
    <i class="bi bi-check-circle"></i>
    <strong>Coordinated Pattern:</strong> When you save in the form, you can trigger the table (or chart/list) reload
    to immediately show the new record. Use <code>$response['list']</code> in the action response.
</div>

<hr>

<h2>Recap: Common Errors to Avoid</h2>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Error</th>
                <th>Problem</th>
                <th>Solution</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>#1 Duplicate IDs</strong></td>
                <td>Hardcoded strings cause mismatch</td>
                <td>Use <code>private const</code> for all IDs</td>
            </tr>
            <tr>
                <td><strong>#2 Canvas double init</strong></td>
                <td>Inline script + ChartComponent</td>
                <td>Remove inline script, use only automatic ChartComponent</td>
            </tr>
            <tr>
                <td><strong>#3 Template variables</strong></td>
                <td>Using <code>$data</code> instead of <code>$fields_data</code></td>
                <td>In ListBuilder template: <code>$fields_data</code> and <code>$row</code></td>
            </tr>
            <tr>
                <td><strong>#4 FormBuilder config</strong></td>
                <td>Passing form_id as $page parameter</td>
                <td>Use <code>->setId()</code> and <code>->currentAction()</code></td>
            </tr>
            <tr>
                <td><strong>#5 data-fetch on button</strong></td>
                <td><code>data-fetch</code> doesn't work on <code>&lt;button&gt;</code></td>
                <td>Always use <code>&lt;a&gt;</code> tag with <code>data-fetch</code></td>
            </tr>
        </tbody>
    </table>
</div>

<hr>

<h2>JSON Response Pattern</h2>

<p>All builders respond with JSON containing specific keys to control the UI:</p>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Key</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>list</code></td>
                <td>Reload Chart, Table or List</td>
                <td><code>['id' => 'tableId', 'action' => 'reload']</code></td>
            </tr>
            <tr>
                <td><code>toast</code></td>
                <td>Show notification</td>
                <td><code>['message' => 'Saved!', 'type' => 'success']</code></td>
            </tr>
            <tr>
                <td><code>element</code></td>
                <td>Update custom HTML</td>
                <td><code>['selector' => '#div', 'innerHTML' => $html]</code></td>
            </tr>
            <tr>
                <td><code>redirect</code></td>
                <td>Navigate to URL</td>
                <td><code>'?page=tasks'</code></td>
            </tr>
            <tr>
                <td><code>window_reload</code></td>
                <td>Reload full page</td>
                <td><code>true</code></td>
            </tr>
        </tbody>
    </table>
</div>

<hr>

<h2>When to Use This Pattern</h2>

<ul>
    <li><strong>Complex dashboards:</strong> Show the same data in different formats</li>
    <li><strong>Data exploration:</strong> Filter and visualize data from multiple perspectives</li>
    <li><strong>CRUD with preview:</strong> Data entry form + results table/list</li>
    <li><strong>Dynamic reporting:</strong> Chart + detail table + filter form</li>
    <li><strong>Resource management:</strong> Card list + table + edit form</li>
</ul>

<hr>

<h2>Reference Module</h2>

<p>
    The complete <code>UpdatePatterns</code> module is available at
    <code>milkadmin_local/Modules/UpdatePatterns/</code> and demonstrates all the patterns discussed in this article.
</p>

<p>
    You can test it by accessing:
    <a href="?page=updatepatterns">?page=updatepatterns</a>
</p>

<hr>

<h2>See Also</h2>

<ul>
    <li><a href="?page=docs&action=Developer/Advanced/fetch-based-modules">Fetch-Based Modules</a> - Base pattern for AJAX modules</li>
    <li><a href="?page=docs&action=Developer/Chart/builders-chart">ChartBuilder Documentation</a> - Complete ChartBuilder reference</li>
    <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder Documentation</a> - Complete TableBuilder reference</li>
    <li><a href="?page=docs&action=Developer/Form/builders-form">FormBuilder Documentation</a> - Complete FormBuilder reference</li>
</ul>

</div>
