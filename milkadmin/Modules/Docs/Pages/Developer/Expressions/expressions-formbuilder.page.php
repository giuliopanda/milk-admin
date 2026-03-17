<?php
namespace Modules\Docs\Pages;

/**
 * @title Expressions in FormBuilder
 * @guide developer
 * @order 40
 * @tags FormBuilder, milk-form, data-milk-expr, data-milk-default-expr, data-milk-show, data-milk-validate-expr, data-milk-required-if
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Expressions in FormBuilder</h1>

    <p>The <strong>FormBuilder</strong> exposes fluent methods to connect expressions to form fields. These expressions are executed
        in real-time by the frontend through <code>milk-form.js</code> and <code>expression-parser.js</code>.</p>

    <h2>Main Methods</h2>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Effect</th>
                <th>HTML Attribute</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>calcExpr($expression)</code></td>
                <td>Calculates the field value</td>
                <td><code>data-milk-expr</code></td>
            </tr>
            <tr>
                <td><code>defaultExpr($expression)</code></td>
                <td>Sets a soft default (frontend only) value</td>
                <td><code>data-milk-default-expr</code></td>
            </tr>
            <tr>
                <td><code>validateExpr($expression, $message)</code></td>
                <td>Validates the field with a boolean expression</td>
                <td><code>data-milk-validate-expr</code> + <code>data-milk-message</code></td>
            </tr>
            <tr>
                <td><code>requireIf($expression, $message)</code></td>
                <td>Field required only if condition is true</td>
                <td><code>data-milk-required-if</code> + <code>data-milk-message</code></td>
            </tr>
            <tr>
                <td><code>showIf($field, $expression)</code></td>
                <td>Shows/hides a field or container</td>
                <td><code>data-milk-show</code></td>
            </tr>
        </tbody>
    </table>

    <h2>Quick Example</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form->field('total')->calcExpr('[qty] * [price]');
$form->field('end_time')->defaultExpr('TIMEADD([start_time], 45)');
$form->field('end_date')
    ->validateExpr('[start_date] &lt;= [end_date]', 'End date must be later');

$form->field('notes')->requireIf('[qty] &gt; 10', 'Notes required for quantity &gt; 10');
$form->showIf('notes', '[qty] &gt; 10');</code></pre>

    <h2>defaultExpr (soft default)</h2>
    <p><code>defaultExpr()</code> runs only on the frontend (JS). It sets a default value from an expression while keeping the field editable.</p>
    <ul>
        <li>Applied when the field is empty or was previously auto-filled by the same default expression.</li>
        <li>If the user edits the field manually, the default will not overwrite it on recalculation.</li>
        <li>Existing values (for example in edit forms) are preserved unless they were auto-filled.</li>
    </ul>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form->field('end_time')->defaultExpr('TIMEADD([start_time], 45)');</code></pre>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;input name="data[end_time]" type="time"
       data-milk-default-expr="TIMEADD([start_time], 45)" /&gt;</code></pre>

    <h2>HTML Attributes (custom form)</h2>
    <p>If you build a form manually, you can use the <code>data-milk-*</code> attributes directly:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;input name="data[qty]" type="number" /&gt;
&lt;input name="data[price]" type="number" /&gt;

&lt;input name="data[total]" type="number"
       data-milk-expr="[qty] * [price]" /&gt;

&lt;input name="data[end_time]" type="time"
       data-milk-default-expr="TIMEADD([start_time], 45)" /&gt;

&lt;input name="data[notes]" type="text"
       data-milk-required-if="[qty] &gt; 10"
       data-milk-message="Notes required for quantity &gt; 10" /&gt;</code></pre>

    <h2>Automatic mapping from Model rules</h2>
    <p>If you define expressions in the Model (e.g. <code>calcExpr()</code>, <code>validateExpr()</code>, <code>requireIf()</code>),
        the FormBuilder automatically maps them to <code>data-milk-*</code> when generating the form.
        You can still override them in the FormBuilder if needed.</p>

    <div class="alert alert-info">
        <strong>Learn more:</strong>
        <ul class="mb-0 mt-2">
            <li><a href="?page=docs&action=Developer/Form/builders-form-conditional-visibility" class="alert-link">Conditional Visibility</a></li>
            <li><a href="?page=docs&action=Developer/Form/builders-form-validation" class="alert-link">Form Validation</a></li>
        </ul>
    </div>
</div>
