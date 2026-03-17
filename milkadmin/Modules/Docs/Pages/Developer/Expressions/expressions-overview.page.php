<?php
namespace Modules\Docs\Pages;

/**
 * @title Expression System
 * @guide developer
 * @order 10
 * @tags ExpressionParser, expressions, milk-expressions, calc, validation, required-if, showIf, frontend, backend
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Expression System</h1>

    <p>Milk Admin includes a <strong>mini expression language</strong> that runs both <strong>frontend</strong> and <strong>backend</strong>.
        This allows using the same logic for <em>calculations</em>, <em>validation</em>, <em>conditional required fields</em> and <em>visibility</em>.</p>

    <div class="alert alert-info">
        <strong>Implementations:</strong>
        <ul class="mb-0 mt-2">
            <li><code>milkadmin/App/ExpressionParser.php</code> (backend PHP)</li>
            <li><code>milkadmin/Theme/Assets/expression-parser.js</code> (frontend JS)</li>
        </ul>
    </div>

    <h2>What you can do with expressions</h2>
    <ul>
        <li><strong>Calculate fields</strong> automatically (e.g. total = quantity * price)</li>
        <li><strong>Validate values</strong> with custom rules (e.g. end date &gt; start date)</li>
        <li><strong>Show/hide</strong> fields or containers based on conditions</li>
        <li><strong>Make a field required</strong> only if a condition is true</li>
    </ul>

    <h2>Where they are executed</h2>
    <ul>
        <li><strong>Frontend:</strong> <code>expression-parser.js</code> + <code>milk-form.js</code> evaluate expressions in real-time on form values.</li>
        <li><strong>Backend:</strong> <code>ExpressionParser.php</code> validates data and calculates fields during <code>validate()</code> and <code>fill()</code>.</li>
    </ul>

    <div class="alert alert-warning">
        <strong>Note:</strong> visibility (show/hide) is a frontend feature. On the backend you can still use <code>requireIf()</code> and <code>validateExpr()</code>
        to maintain data consistency and security.
    </div>

    <h2>Example</h2>

    <p><strong>In the Model</strong> (RuleBuilder):</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure($rule)
{
    $rule->int('qty')->required();
    $rule->float('price')->required();

    $rule->float('total')->calcExpr('[qty] * [price]');

    // Conditional required backend
    $rule->string('notes')
        ->requireIf('[qty] &gt; 10')
        ->error('Notes required for quantity &gt; 10');
}</code></pre>

    <p><strong>In the FormBuilder</strong> (frontend):</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form->field('total');
$form->field('notes')->requireIf('[qty] &gt; 10', 'Notes required for quantity &gt; 10');
$form->showIf('notes', '[qty] &gt; 10');</code></pre>

    <p>The FormBuilder inherits expressions from the model, but it's possible to modify them or add new ones.</p>
</div>
