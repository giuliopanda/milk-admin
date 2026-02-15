<?php
namespace Modules\Docs\Pages;

/**
 * @title Expression Syntax
 * @guide developer
 * @order 20
 * @tags expressions, syntax, operators, functions, dates, IF, parameters
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Expression Syntax</h1>

    <p>The syntax is the same in frontend and backend. Expressions work with <strong>numbers</strong>, <strong>strings</strong>, <strong>dates</strong> and <strong>booleans</strong>.</p>

    <h2>Parameters and fields</h2>
    <p>To read a value from the form or record use the syntax <code>[field]</code>:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-text">[qty] * [price]
IF [type] == "company" THEN "Y" ELSE "N" ENDIF</code></pre>

    <div class="alert alert-info">
        <strong>Frontend:</strong> parameters are read from form fields (id, name or <code>data[field]</code>).
        <br><strong>Backend:</strong> parameters come from the record array (Model field keys).
    </div>

    <h3>Dot-notation (nested parameters)</h3>
    <p>When a parameter is an object/array, you can access nested values using dot-notation:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-text">[user.name]
[user.address.city]
[user.orders.0.total]</code></pre>
    <div class="alert alert-warning">
        <strong>Note:</strong> dot-notation works only on parameters you set programmatically (e.g. <code>setParameter("user", {...})</code> in JS or
        <code>setParameters(["user" =&gt; $user])</code> in PHP). Form fields are flat, so <code>[user.name]</code> is not automatically built from multiple inputs.
    </div>

    <h2>Operators</h2>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Category</th>
                <th>Operators</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Mathematical</td>
                <td><code>+</code> <code>-</code> <code>*</code> <code>/</code> <code>%</code> <code>^</code></td>
                <td><code>[qty] * [price]</code></td>
            </tr>
            <tr>
                <td>Comparison</td>
                <td><code>==</code> <code>!=</code> <code>&lt;&gt;</code> <code>&lt;</code> <code>&gt;</code> <code>&lt;=</code> <code>&gt;=</code></td>
                <td><code>[start] &lt;= [end]</code></td>
            </tr>
            <tr>
                <td>Logical</td>
                <td><code>AND</code> <code>OR</code> <code>NOT</code> (also <code>&amp;&amp;</code> <code>||</code> <code>!</code>)</td>
                <td><code>[a] &gt; 0 AND [b] &gt; 0</code></td>
            </tr>
            <tr>
                <td>Assignment</td>
                <td><code>=</code></td>
                <td><code>x = 5</code></td>
            </tr>
        </tbody>
    </table>

    <h2>Strings and dates</h2>
    <ul>
        <li>Strings with single or double quotes: <code>"test"</code>, <code>'test'</code></li>
        <li>Supported dates: <code>YYYY-MM-DD</code> and <code>DD/MM/YYYY</code></li>
        <li>Date operations: <code>date + days</code>, <code>date - date</code> (returns days)</li>
    </ul>

    <h2>IF / THEN / ELSE</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-text">IF [country] == "IT" THEN "IVA" ELSE "VAT" ENDIF</code></pre>

    <h2>Available Functions</h2>
    <p>Functions are identical in PHP and JS:</p>
    <ul>
        <li><code>NOW()</code></li>
        <li><code>AGE(date)</code></li>
        <li><code>ROUND(n, decimals)</code></li>
        <li><code>ABS(n)</code></li>
        <li><code>IFNULL(val, default)</code></li>
        <li><code>UPPER(str)</code></li>
        <li><code>LOWER(str)</code></li>
        <li><code>CONCAT(str1, str2, ...)</code></li>
        <li><code>TRIM(str)</code></li>
        <li><code>ISEMPTY(val)</code></li>
        <li><code>PRECISION(n, decimals)</code></li>
        <li><code>DATEONLY(datetime)</code></li>
        <li><code>TIMEADD(time, minutes)</code></li>
        <li><code>ADDMINUTES(time, minutes)</code></li>
        <li><code>COUNT(array)</code>, <code>COUNT(array, "field")</code></li>
        <li><code>SUM(array, "field")</code></li>
        <li><code>MIN(array, "field")</code></li>
        <li><code>MAX(array, "field")</code></li>
        <li><code>FIND(array, "field", value)</code></li>
        <li><code>CONTAINS(array, "field", value)</code></li>
        <li><code>FIRST(array)</code>, <code>FIRST(array, "field", default?)</code></li>
        <li><code>LAST(array)</code>, <code>LAST(array, "field", default?)</code></li>
    </ul>

    <h3>TIMEADD / ADDMINUTES</h3>
    <p>They are aliases. Input can be a time string (<code>HH:MM</code> or <code>HH:MM:SS</code>) or a Date/DateTime. The output keeps the same format as the input (if seconds were present, they are preserved). Minutes can be negative and the result wraps on 24h.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-text">TIMEADD([start_time], 45)
ADDMINUTES([start_time], -30)</code></pre>

    <h3>Array / Object helpers</h3>
    <p>These functions are designed to work with arrays of objects/arrays. The <code>"field"</code> parameter supports dot-notation too (e.g. <code>"customer.id"</code>).</p>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Function</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>COUNT(array)</code></td>
                <td>Array length</td>
            </tr>
            <tr>
                <td><code>COUNT(array, "field")</code></td>
                <td>Counts items where <code>field</code> is not null (JS: not null/undefined)</td>
            </tr>
            <tr>
                <td><code>SUM(array, "field")</code></td>
                <td>Sums numeric values of <code>field</code> (skips null/non-numeric)</td>
            </tr>
            <tr>
                <td><code>MIN(array, "field")</code></td>
                <td>Minimum numeric value of <code>field</code> (returns <code>null</code> if none)</td>
            </tr>
            <tr>
                <td><code>MAX(array, "field")</code></td>
                <td>Maximum numeric value of <code>field</code> (returns <code>null</code> if none)</td>
            </tr>
            <tr>
                <td><code>FIND(array, "field", value)</code></td>
                <td>Returns the first item where <code>field == value</code> (or <code>null</code> if not found)</td>
            </tr>
            <tr>
                <td><code>CONTAINS(array, "field", value)</code></td>
                <td>Returns <code>true</code> if an item exists where <code>field == value</code></td>
            </tr>
            <tr>
                <td><code>FIRST(array, "field"?, default?)</code></td>
                <td>Returns the first item (or its field). If array is empty/missing field, returns <code>default</code></td>
            </tr>
            <tr>
                <td><code>LAST(array, "field"?, default?)</code></td>
                <td>Returns the last item (or its field). If array is empty/missing field, returns <code>default</code></td>
            </tr>
        </tbody>
    </table>

    <h3>Quick Examples</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-text">ROUND([total] * 0.22, 2)
IF ISEMPTY([discount]) THEN 0 ELSE [discount] ENDIF
[TIME_FROM] &lt; [TIME_TO] OR ISEMPTY([TIME_TO])
DATEONLY(NOW())</code></pre>
</div>
