<?php
namespace Modules\Docs\Pages;

/**
 * @title Expressions in Model (RuleBuilder)
 * @guide developer
 * @order 50
 * @tags Model, RuleBuilder, calcExpr, validateExpr, requireIf, backend-validation
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Expressions in Model (RuleBuilder)</h1>

    <p>In the Model you can define expressions directly in the rules. These are executed by the backend through <code>ExpressionParser</code>
        during <code>fill()</code> and <code>validate()</code>.</p>

    <h2>Available Methods</h2>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Purpose</th>
                <th>Backend</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>calcExpr($expression)</code></td>
                <td>Calculates a value from other fields</td>
                <td>Applied in <code>AbstractModel::applyCalculatedFieldsForCurrentRecord()</code></td>
            </tr>
            <tr>
                <td><code>validateExpr($expression, $message)</code></td>
                <td>Custom validation with boolean expression</td>
                <td>Evaluated by <code>ModelValidator</code></td>
            </tr>
            <tr>
                <td><code>requireIf($expression)</code></td>
                <td>Field required only if condition is true</td>
                <td>Evaluated by <code>ModelValidator</code></td>
            </tr>
        </tbody>
    </table>

    <h2>Complete Example</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure($rule)
{
    $rule->int('qty')->required();
    $rule->float('price')->required();

    // Calculated field (backend)
    $rule->float('total')->calcExpr('[qty] * [price]');

    // Custom validation
    $rule->date('end_date')
        ->validateExpr('[start_date] &lt;= [end_date]', 'End date must be later');

    // Conditional required
    $rule->string('notes')
        ->requireIf('[qty] &gt; 10')
        ->error('Notes required for quantity &gt; 10');
}</code></pre>

    <h2>Backend Behavior</h2>
    <ul>
        <li><strong>calcExpr</strong>: is executed on the current record; if the expression fails, the field is not updated.</li>
        <li><strong>validateExpr</strong>: must return <code>true</code>. If it returns a non-empty string, the string is used as an error message.</li>
        <li><strong>requireIf</strong>: if the expression is true, the field is required.</li>
    </ul>

    <div class="alert alert-info">
        <strong>Tip:</strong> defining <code>calcExpr</code>, <code>validateExpr</code> and <code>requireIf</code> in the Model allows the FormBuilder
        to automatically inherit them as <code>data-milk-*</code> on the frontend.
    </div>

    <div class="alert alert-warning">
        <strong>Frontend/Backend difference:</strong> on the frontend <code>validateExpr</code> considers only the boolean result <code>true</code> as valid.
        For custom messages use <code>data-milk-message</code> or the <code>$message</code> parameter in the FormBuilder.
    </div>
</div>
