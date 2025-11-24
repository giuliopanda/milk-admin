<?php
namespace Modules\Docs\Pages;

/**
 * @title Manual Data Loading
 * @guide developer
 * @order 43
 * @tags FormBuilder, data-loading, manual-data, addFieldsFromObject, getEmpty, form-data, post-data, data-management
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>FormBuilder - Manual Data Loading</h1>

    <p>How to manually control data loading and management in forms.</p>

    <h2>Automatic Mode (Default)</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($model)->getForm();</code></pre>

    <p>Automatically loads data if <code>$_REQUEST['id']</code> is present.</p>

    <h2>Manual Data Loading</h2>

    <p>For more control over data loading and manipulation:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$data = $this->model->getByIdForEdit(_absint($_REQUEST['id']));

$form = \Builders\FormBuilder::create($model)
    ->addFieldsFromObject($data, 'edit')
    ->getForm();</code></pre>

    <h2>Modifying Data Before Display</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$data = $this->model->getByIdForEdit(_absint($_REQUEST['id']));
$data->title = "Foo!";

$form = \Builders\FormBuilder::create($model)
    ->addFieldsFromObject($data, 'edit')
    ->getForm();</code></pre>

    <h2>Creating Data From Scratch</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$data = $this->model->getEmpty();
$data->title = "Foo!";

$form = \Builders\FormBuilder::create($model)
    ->addFieldsFromObject($data, 'edit')
    ->getForm();</code></pre>

    <h2>Form Data Handling</h2>

    <p>All form data is received in <code>$_POST['data']</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$id = _absint($_POST['data']['id'] ?? $_REQUEST['id'] ?? 0);</code></pre>

    <h2>Preserving User Input on Errors</h2>

    <p>When validation fails, you want to show the user's modified data, not the original database values:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$id = _absint($_POST['data']['id'] ?? $_REQUEST['id'] ?? 0);

$data = $this->model->getByIdForEdit($id, ($_POST['data'] ?? []));</code></pre>

    <p>The second parameter <code>($_POST['data'] ?? [])</code> merges POST data over the loaded data, preserving user changes when the form is re-displayed after an error.</p>

    <h2>When to Use Manual Mode</h2>

    <ul>
        <li>Pre-populating fields with custom default values</li>
        <li>Loading data from sources other than <code>$_REQUEST['id']</code></li>
        <li>Modifying data before display</li>
        <li>Complex validation scenarios requiring data persistence</li>
        <li>Multi-step forms</li>
    </ul>

    <h2>Complete Example</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Load data with POST override for error handling
$id = _absint($_POST['data']['id'] ?? $_REQUEST['id'] ?? 0);
$data = $this->model->getByIdForEdit($id, ($_POST['data'] ?? []));

// Modify data if needed
if (!$id) {
    $data->created_by = get_current_user_id();
    $data->status = 'draft';
}

// Create form with manual data loading
$form = \Builders\FormBuilder::create($model)
    ->addFieldsFromObject($data, 'edit')
    ->addStandardActions(true, '?page=posts')
    ->getForm();</code></pre>

    <p><strong>Note:</strong> The automatic mode handles all these scenarios by default. Use manual mode only when you need specific control over data loading or manipulation.</p>

</div>
