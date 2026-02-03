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
                <td>Sets parameters available for <code>[field]</code></td>
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

    <h2>Example (JS)</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">const parser = new ExpressionParser();
parser.setParameter('qty', 3);
parser.setParameter('price', 10);

const result = parser.execute('[qty] * [price]'); // 30</code></pre>

    <div class="alert alert-warning">
        <strong>Important differences:</strong>
        <ul class="mb-0 mt-2">
            <li>In JS you can use <code>setParametersFromForm(form)</code> to load values directly from an HTML form.</li>
            <li>In PHP <code>getVariables()</code> and <code>getParameters()</code> are available to inspect the internal state.</li>
        </ul>
    </div>
</div>
