<?php
namespace Modules\Docs\Pages;

/**
 * @title Complete Examples
 * @guide developer
 * @order 60
 * @tags examples, expressions, calcExpr, validateExpr, requireIf, showIf
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Complete Examples</h1>

    <h2>1) Total calculation and discount</h2>
    <p><strong>Model:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure($rule)
{
    $rule->int('qty')->required();
    $rule->float('price')->required();
    $rule->bool('is_vip');

    $rule->float('subtotal')->calcExpr('[qty] * [price]');
    $rule->float('discount')->calcExpr('IF [is_vip] == 1 THEN ROUND([subtotal] * 0.10, 2) ELSE 0 ENDIF');
    $rule->float('total')->calcExpr('[subtotal] - [discount]');

    $rule->string('invoice_note')
        ->requireIf('[is_vip] == 1')
        ->error('Notes required for VIP customers');
}</code></pre>

    <p><strong>FormBuilder:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form->field('subtotal')->calcExpr('[qty] * [price]')->disabled();
$form->field('discount')->calcExpr('IF [is_vip] == 1 THEN ROUND([subtotal] * 0.10, 2) ELSE 0 ENDIF')->disabled();
$form->field('total')->calcExpr('[subtotal] - [discount]')->disabled();
$form->field('invoice_note')->requireIf('[is_vip] == 1', 'Notes required for VIP customers');</code></pre>

    <hr>

    <h2>2) Visibility and conditional required</h2>
    <p>Show and make VAT number required only if customer type is "company".</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Model
$rule->string('customer_type')->required();
$rule->string('vat_number')->requireIf('[customer_type] == "company"');

// FormBuilder
$form->showIf('vat_number', '[customer_type] == "company"');
$form->field('vat_number')->requireIf('[customer_type] == "company"', 'VAT number required');</code></pre>

    <hr>

    <h2>3) Date validation</h2>
    <p>Ensure that the end date is equal to or later than the start date.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Model
$rule->date('start_date')->required();
$rule->date('end_date')
    ->validateExpr('[start_date] <= [end_date]', 'End date must be later');

// FormBuilder
$form->field('end_date')
    ->validateExpr('[start_date] &lt;= [end_date]', 'End date must be later');</code></pre>

    <div class="alert alert-info">
        <strong>Tip:</strong> use the same expression in Model and FormBuilder to get consistent validation on both levels.
    </div>
</div>
