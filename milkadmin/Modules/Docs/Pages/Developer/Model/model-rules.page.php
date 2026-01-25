<?php
namespace Modules\Docs\Pages;

/**
 * @title Model Validation Rules
 * @guide developer
 * @order 47
 * @tags model, validation, rulebuilder, required, nullable, min, max, step, pattern, regex, date, datetime, time, enum, list
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Model Validation Rules</h1>

    <p>This page documents the <strong>server-side validation rules</strong> defined in a Model <code>configure()</code> method
        with the <code>RuleBuilder</code>. These rules are evaluated by <code>AbstractModel::validate()</code> and are used by
        <code>FormBuilder</code> when saving records.</p>

    <div class="alert alert-info">
        <strong>Note:</strong> Many rules also map to HTML attributes via <code>formParams()</code> (frontend validation),
        but validation always runs on the backend.
    </div>

    <h2>Required vs Nullable</h2>
    <ul>
        <li><code>required()</code> enforces that a value is present during validation.</li>
        <li><code>nullable(false)</code> sets the column as NOT NULL in the schema; validation still needs <code>required()</code> if you want to block empty values at runtime.</li>
        <li>Default is <code>nullable(true)</code>.</li>
    </ul>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$rule->string('name', 100)
    ->required()
    ->nullable(false); // not nullable in DB

$rule->string('nickname', 50)
    ->nullable(); // can be empty
    </code></pre>

    <h2>Numeric Rules</h2>
    <ul>
        <li><code>min($value)</code> / <code>max($value)</code>: numeric range validation</li>
        <li><code>step($value)</code>: numeric step validation</li>
    </ul>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$rule->int('priority')
    ->min(0)
    ->max(100)
    ->step(5);
    </code></pre>

    <h3>Compare With Another Field (backend only)</h3>
    <p>You can pass a field name to <code>min()</code> or <code>max()</code> to compare values on the backend.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$rule->int('min_members')->max('max_members');
$rule->int('max_members')->min('min_members');
    </code></pre>

    <h2>String / Text Rules</h2>
    <ul>
        <li><code>min($value)</code> / <code>max($value)</code>: minimum/maximum length for <code>string</code>/<code>text</code></li>
        <li><code>string('field', 100)</code>: sets max length (100)</li>
        <li><code>formParams(['pattern' =&gt; '...'])</code>: regex validation (also applied server-side)</li>
    </ul>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$rule->string('code', 20)
    ->min(5)
    ->formParams(['pattern' => '^[A-Z0-9]+$']);
    </code></pre>

    <h2>Date / Time Rules</h2>
    <ul>
        <li><code>min()</code> / <code>max()</code> accept date/time strings or another field name</li>
        <li>Use formats like <code>YYYY-MM-DD</code> and <code>HH:MM</code></li>
    </ul>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$rule->date('start_date')->max('end_date');
$rule->date('end_date')->min('start_date');

$rule->time('start_time')->max('end_time');
    </code></pre>

    <h2>Enum / List</h2>
    <p>For <code>enum</code> and <code>list</code> fields, validation checks that the value is in the allowed options.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$rule->list('status')
    ->options(['new' => 'New', 'active' => 'Active', 'archived' => 'Archived']);
    </code></pre>

    <h2>Custom Validation</h2>
    <p>Use a custom validation method via the <code>#[Validate('field_name')]</code> attribute for backend rules.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
use App\Attributes\Validate;

#[Validate('code')]
protected function validateCode(object $row): ?string
{
    if (!preg_match('/^[A-Z0-9]+$/', $row->code ?? '')) {
        return 'Invalid code';
    }
    return null;
}
    </code></pre>

    <h2>Custom Validation (Frontend JS)</h2>
    <p>For custom client-side validation, listen to the <code>fieldValidation</code> event and use <code>setCustomValidity()</code>.
        This is optional and always in addition to backend validation.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">document.addEventListener('updateContainer', function() {
    const codeField = document.querySelector('[name="code"]');
    if (!codeField) return;

    codeField.addEventListener('fieldValidation', function() {
        if (!/^[A-Z0-9]+$/.test(codeField.value)) {
            codeField.setCustomValidity('Invalid code');
        } else {
            codeField.setCustomValidity('');
        }
    });
});</code></pre>

    <p>For full examples and event details, see
        <a href="?page=docs&action=Developer/Form/builders-form-validation">Form Validation with FormBuilder</a>.
    </p>
</div>
