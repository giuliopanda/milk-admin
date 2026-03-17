<?php
namespace Modules\Docs\Pages;

/**
 * @title Data Loading and Management
 * @guide developer
 * @order 43
 * @tags FormBuilder, data-loading, setData, setIdRequest, getEmpty, form-data, post-data, data-management
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>FormBuilder - Data Loading and Management</h1>

    <p>How to control data loading and management in forms.</p>

    <h2>Automatic Mode (Default)</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($model)->getForm();</code></pre>

    <p>Automatically loads data if <code>$_REQUEST['id']</code> is present.</p>

    <h2>Custom ID Parameter</h2>

    <p>If you need to use a different request parameter instead of <code>id</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($model)
    ->setIdRequest('my_id')
    ->getForm();</code></pre>

    <p>This will load data from <code>$_REQUEST['my_id']</code> instead of <code>$_REQUEST['id']</code>.</p>

    <h2>Custom Data Loading</h2>

    <p>For full control over data loading, use <code>setData()</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$modelData = $this->model->getByIdForEdit(_absint($_REQUEST['id'] ?? 0));

$form = \Builders\FormBuilder::create($model)
    ->setData($modelData)
    ->getForm();</code></pre>

    <h2>Modifying Data Before Display</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$modelData = $this->model->getByIdForEdit(_absint($_REQUEST['id'] ?? 0));
$modelData->title = "Foo!";

$form = \Builders\FormBuilder::create($model)
    ->setData($modelData)
    ->getForm();</code></pre>

    <h2>Creating Data From Scratch</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$modelData = $this->model->getEmpty();
$modelData->title = "Foo!";

$form = \Builders\FormBuilder::create($model)
    ->setData($modelData)
    ->getForm();</code></pre>

    <h2>Form Data Handling</h2>

    <p>All form data is received in <code>$_POST['data']</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$id = _absint($_POST['data']['id'] ?? $_REQUEST['id'] ?? 0);</code></pre>

    <h2>Preserving User Input on Errors</h2>

    <p>When validation fails, you want to show the user's modified data, not the original database values:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$id = _absint($_POST['data']['id'] ?? $_REQUEST['id'] ?? 0);

$modelData = $this->model->getByIdForEdit($id, ($_POST['data'] ?? []));

$form = \Builders\FormBuilder::create($model)
    ->setData($modelData)
    ->getForm();</code></pre>

    <p>The second parameter <code>($_POST['data'] ?? [])</code> merges POST data over the loaded data, preserving user changes when the form is re-displayed after an error.</p>

    <h2>When to Use Custom Data Loading</h2>

    <ul>
        <li>Pre-populating fields with custom default values</li>
        <li>Loading data from sources other than <code>$_REQUEST['id']</code></li>
        <li>Modifying data before display</li>
        <li>Complex validation scenarios requiring data persistence</li>
        <li>Multi-step forms</li>
        <li>Using a different request parameter name</li>
    </ul>

    <h2>Complete Example</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Load data with POST override for error handling
$id = _absint($_POST['data']['id'] ?? $_REQUEST['id'] ?? 0);
$modelData = $this->model->getByIdForEdit($id, ($_POST['data'] ?? []));

// Modify data if needed
if (!$id) {
    $modelData->created_by = get_current_user_id();
    $modelData->status = 'draft';
}

// Create form with custom data loading
$form = \Builders\FormBuilder::create($model)
    ->setData($modelData)
    ->addStandardActions(true, '?page=posts')
    ->getForm();</code></pre>

    <h2>Summary of Data Loading Methods</h2>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Method</th>
                <th>Use Case</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Automatic (default)</td>
                <td>When <code>$_REQUEST['id']</code> contains the record ID</td>
            </tr>
            <tr>
                <td><code>setIdRequest('my_id')</code></td>
                <td>When using a different request parameter name</td>
            </tr>
            <tr>
                <td><code>setData($modelData)</code></td>
                <td>When you need full control over data loading and manipulation</td>
            </tr>
        </tbody>
    </table>

    <p><strong>Note:</strong> The automatic mode is suitable for most cases. Use <code>setIdRequest()</code> or <code>setData()</code> only when you need specific control over data loading or manipulation.</p>

</div>
