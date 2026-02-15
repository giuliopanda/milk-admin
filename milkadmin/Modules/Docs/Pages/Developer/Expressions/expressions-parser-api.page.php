<?php
namespace Modules\Docs\Pages;

/**
 * @title API ExpressionParser (PHP & JS)
 * @guide developer
 * @order 30
 * @tags ExpressionParser, API, parse, execute, analyze, AST, debug
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>API ExpressionParser (PHP &amp; JS)</h1>

    <p>The API is very similar between PHP and JavaScript. Below are the main methods and differences.</p>

    <h2>Main Methods</h2>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>PHP</th>
                <th>JS</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>setParameters(...)</code></td>
                <td><code>setParameters(array $params)</code></td>
                <td><code>setParametersFromForm(form|object)</code></td>
                <td>Loads parameters used by <code>[field]</code> (JS merges, PHP replaces)</td>
            </tr>
            <tr>
                <td><code>setParameter(...)</code></td>
                <td><code>setParameter(string $name, mixed $value)</code></td>
                <td><code>setParameter(name, value)</code></td>
                <td>Sets a single parameter</td>
            </tr>
            <tr>
                <td><code>parse(...)</code></td>
                <td><code>parse(string $source)</code></td>
                <td><code>parse(source)</code></td>
                <td>Parses the expression into AST</td>
            </tr>
            <tr>
                <td><code>execute(...)</code></td>
                <td><code>execute(ast|string)</code></td>
                <td><code>execute(ast|string)</code></td>
                <td>Executes the AST and returns the result</td>
            </tr>
            <tr>
                <td><code>analyze(...)</code></td>
                <td><code>analyze(source, execute=true)</code></td>
                <td><code>analyze(source, execute=true)</code></td>
                <td>Returns AST, operation order, tree and result</td>
            </tr>
            <tr>
                <td><code>getOperationOrder(...)</code></td>
                <td><code>getOperationOrder(ast|string)</code></td>
                <td><code>getOperationOrder(ast|string)</code></td>
                <td>List operations in execution order</td>
            </tr>
            <tr>
                <td><code>visualizeTree(...)</code></td>
                <td><code>visualizeTree(ast|string)</code></td>
                <td><code>visualizeTree(ast|string)</code></td>
                <td>Textual representation of the AST</td>
            </tr>
            <tr>
                <td><code>formatResult(...)</code></td>
                <td><code>formatResult(mixed)</code></td>
                <td><code>formatResult(value)</code></td>
                <td>Formats dates and booleans for readable output</td>
            </tr>
            <tr>
                <td><code>reset / resetAll</code></td>
                <td><code>reset()</code> / <code>resetAll()</code></td>
                <td><code>reset()</code> / <code>resetAll()</code></td>
                <td>Reset variables (and parameters)</td>
            </tr>
            <tr>
                <td><code>getBuiltinFunctions()</code></td>
                <td><code>getBuiltinFunctions()</code></td>
                <td><code>getBuiltinFunctions()</code></td>
                <td>List of available functions</td>
            </tr>
            <tr>
                <td><code>normalizeCheckboxValue(...)</code></td>
                <td><code>normalizeCheckboxValue(mixed)</code></td>
                <td><code>normalizeCheckboxValue(value)</code></td>
                <td>Normalizes checkbox values to boolean</td>
            </tr>
        </tbody>
    </table>

    <h2>Example (PHP)</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$parser = new \App\ExpressionParser();
$parser->setParameters([
    'qty' => 3,
    'price' => 10
]);

$result = $parser->execute('[qty] * [price]'); // 30</code></pre>

    <h2>PHP: execute expressions on Model query results</h2>
    <p>Typical flow:</p>
    <ol>
        <li>Run a query on a Model (e.g. <code>query()-&gt;getResults()</code> or <code>query()-&gt;getRow()</code>)</li>
        <li>Convert results into plain data via <code>getSqlData()</code> (so you have arrays/stdClass ready for dot-notation and helpers)</li>
        <li>Inject data into the parser with <code>setParameter()</code></li>
        <li>Execute the expression (wrap in <code>try/catch</code> to handle invalid expressions)</li>
    </ol>

    <h3>Multiple rows (getResults)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$model = (new DotNotationParameterModel())
    ->query()
    ->getResults();

$parser = new \App\ExpressionParser();
$parser->setParameter('data', $model->getSqlData()); // array of stdClass rows

try {
    $lastId = $parser->execute('LAST([data], "ID")');
} catch (\Exception $e) {
    $error = $e->getMessage();
}</code></pre>

    <h3>Single row (getRow)</h3>
    <p><code>getRow()</code> returns a Model containing a single record. <code>getSqlData()</code> still returns an array (with 1 element), so you can either:</p>
    <ul>
        <li>Keep the array and continue using array helpers like <code>LAST()</code> / <code>FIRST()</code></li>
        <li>Extract the first element (<code>$ris[0]</code>) and use dot-notation like <code>[data.ID]</code></li>
    </ul>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$model = (new DotNotationParameterModel())
    ->query()
    ->getRow();

$parser = new \App\ExpressionParser();

// Option A: keep array (1 row) -> helpers like LAST() work
$parser->setParameter('data', $model->getSqlData());
$lastId = $parser->execute('LAST([data], "ID")');

// Option B: extract first row -> treat as object with dot-notation
$ris = $model->getSqlData();
$parser->setParameter('data', $ris[0]);
$id = $parser->execute('[data.ID]');</code></pre>

    <h2>Example (JS)</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">const parser = new ExpressionParser();
parser.setParameter('qty', 3);
parser.setParameter('price', 10);

const result = parser.execute('[qty] * [price]'); // 30</code></pre>

    <h2>JS: complex parameters + merging from form</h2>
    <p>On the frontend you can set complex objects/arrays once (e.g. at page load), then merge form values multiple times without resetting:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">// 1) Set complex objects (once)
parser.setParameter('user', userObject);
parser.setParameter('config', configObject);

// 2) Form updates (can happen many times)
parser.setParametersFromForm(form); // merge, not reset</code></pre>
    <ul>
        <li><code>user</code> and <code>config</code> stay intact unless the form contains a field with the same name (the form wins).</li>
        <li>Use dot-notation to access nested data: <code>[user.orders.0.total]</code>, <code>[config.flags.someFlag]</code>.</li>
        <li><code>reset()</code> clears variables only; <code>resetAll()</code> clears variables and parameters.</li>
    </ul>

    <div class="alert alert-warning">
        <strong>Important differences:</strong>
        <ul class="mb-0 mt-2">
            <li>In JS <code>setParametersFromForm(form)</code> loads values from an HTML form and <strong>merges</strong> them into existing parameters (it does not reset).</li>
            <li>In PHP <code>getVariables()</code> and <code>getParameters()</code> are available to inspect the internal state.</li>
        </ul>
    </div>
</div>
