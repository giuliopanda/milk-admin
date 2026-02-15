<?php
namespace Modules\Docs\Pages;
/**
 * @title Form Validation with FormBuilder
 * @guide developer
 * @order 46
 * @tags FormBuilder, form-validation, validation, JavaScript-validation, expression-validation, validateExpr, requireIf, calcExpr, defaultExpr, MessagesHandler, PHP-validation, client-side, server-side
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Form Validation with Expressions</h1>

    <p>
        Validation can now be defined with expressions both in <strong>FormBuilder</strong> and in the <strong>Model</strong>.
        When the form is rendered with FormBuilder, these rules are also evaluated in JavaScript by MilkForm.
    </p>

    <div class="alert alert-info">
        <strong>Recommended approach:</strong> keep business rules in the Model, and use FormBuilder expressions for UI-specific behavior.
    </div>

    <h2>Quick Matrix</h2>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Where</th>
                <th>Methods</th>
                <th>Client-side (JS)</th>
                <th>Server-side (PHP)</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Model (RuleBuilder)</strong></td>
                <td><code>validateExpr()</code>, <code>requireIf()</code></td>
                <td>✅ Yes (via FormBuilder/MilkForm)</td>
                <td>✅ Yes (ModelValidator)</td>
                <td>Best place for real business validation</td>
            </tr>
            <tr>
                <td><strong>Model (RuleBuilder)</strong></td>
                <td><code>calcExpr()</code></td>
                <td>✅ Yes (via FormBuilder/MilkForm)</td>
                <td>✅ Yes (AbstractModel)</td>
                <td>Useful for derived fields in both UI and save flow</td>
            </tr>
            <tr>
                <td><strong>FormBuilder (field-first)</strong></td>
                <td><code>validateExpr()</code>, <code>requireIf()</code>, <code>calcExpr()</code>, <code>defaultExpr()</code></td>
                <td>✅ Yes</td>
                <td>⚠️ JS/UI only</td>
                <td>Great for reactive UX; duplicate critical rules in Model</td>
            </tr>
        </tbody>
    </table>

    <h2>Model Expressions (Recommended)</h2>

    <p>Define the rule once in the Model to get server validation and automatic JS behavior in FormBuilder forms.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    $rule->table('orders')
        ->id('ID_ORDER')

        ->decimal('PRICE', 10, 2)->required()
        ->int('QTY')->required()

        ->decimal('TOTAL', 10, 2)
            ->formParams(['readonly' => true])
            ->calcExpr('[PRICE] * [QTY]')

        ->date('END_DATE')
            ->validateExpr('[END_DATE] >= [START_DATE]', 'End date must be after start date')

        ->string('VAT_NUMBER', 20)
            ->requireIf('[COUNTRY] == "IT" AND [IS_COMPANY] == 1');
}</code></pre>

    <h2>FormBuilder Expressions (Reactive UI)</h2>

    <p>These methods are available in <code>FieldFirstTrait</code> and are evaluated in JS by MilkForm:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = FormBuilder::create($this->model, $this->page)
    ->field('TOTAL')
        ->readonly()
        ->calcExpr('[PRICE] * [QTY]')

    ->field('DISCOUNT')
        ->defaultExpr('[TOTAL] * 0.10')

    ->field('END_DATE')
        ->validateExpr('[END_DATE] >= [START_DATE]', 'End date must be after start date')

    ->field('VAT_NUMBER')
        ->requireIf('[COUNTRY] == "IT" AND [IS_COMPANY] == 1', 'VAT number is required')
    ->getForm();</code></pre>

    <div class="alert alert-warning">
        <strong>Important:</strong> if a validation must always be enforced during save, define it in the Model too (not only in FormBuilder).
    </div>

    <h2>How JS Validation Is Activated</h2>

    <p>MilkForm scans expression attributes on fields and validates/reacts automatically:</p>
    <ul>
        <li><code>data-milk-validate-expr</code> → expression validation</li>
        <li><code>data-milk-required-if</code> → conditional required</li>
        <li><code>data-milk-expr</code> → calculated values</li>
        <li><code>data-milk-default-expr</code> → soft defaults</li>
        <li><code>data-milk-message</code> → custom message</li>
    </ul>

    <p>These attributes are set either directly by FormBuilder methods or mapped automatically from Model rules when rendering the form.</p>

    <h2>Server-Side Validation Flow</h2>

    <p>On save, Model validation is still the source of truth:</p>
    <ol>
        <li>FormBuilder fills the model with request data</li>
        <li><code>$model->validate()</code> runs server checks (including expression rules)</li>
        <li>If validation fails, errors are added with <code>MessagesHandler</code></li>
        <li>The form is re-rendered with invalid states/messages</li>
    </ol>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (!$this->model->validate()) {
    return $this->jsonModalError('Validation error', $formBuilder);
}

if (!$this->model->save()) {
    return $this->jsonModalError('Save error: ' . $this->model->getLastError(), $formBuilder);
}</code></pre>

    <h2>When Custom JavaScript Is Still Useful</h2>

    <p>Expression rules cover most cases. Use custom JS events only for advanced UI logic that expressions cannot easily represent.</p>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Event</th>
                <th>When Triggered</th>
                <th>Usage</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>fieldValidation</code></td>
                <td>On submit, then on field change after first submit</td>
                <td>Custom field-level checks</td>
            </tr>
            <tr>
                <td><code>customValidation</code></td>
                <td>On submit, before final validity check</td>
                <td>Form-level checks across many fields</td>
            </tr>
            <tr>
                <td><code>beforeFormSubmit</code></td>
                <td>After validation passes, before submit</td>
                <td>Pre-submit actions (loading, analytics, etc.)</td>
            </tr>
        </tbody>
    </table>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">document.addEventListener('updateContainer', function () {
    const end = document.querySelector('[name="data[END_DATE]"]');
    const start = document.querySelector('[name="data[START_DATE]"]');

    if (!end || !start) return;

    end.addEventListener('fieldValidation', function () {
        if (end.value && start.value && end.value < start.value) {
            end.setCustomValidity('End date must be after start date');
        } else {
            end.setCustomValidity('');
        }
    });
});</code></pre>

    <h2>Best Practices</h2>
    <ul>
        <li>Put critical validation in the Model first</li>
        <li>Use FormBuilder expressions for reactive UX and per-form customization</li>
        <li>Keep messages explicit (set <code>error()</code>/<code>errorMessage()</code> when needed)</li>
        <li>Use custom JS events only when expression syntax is not enough</li>
    </ul>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Model/model-rules"><strong>Model Validation Rules</strong></a></li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-fields"><strong>Field Configuration</strong></a></li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-conditional-visibility"><strong>Conditional Field Visibility</strong></a></li>
        <li><a href="?page=docs&action=Developer/Form/builders-form-containers"><strong>Form Containers</strong></a></li>
    </ul>
</div>
