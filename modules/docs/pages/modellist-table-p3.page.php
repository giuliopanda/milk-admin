<?php
namespace Modules\docs;
/**
 * @title Actions
 * @category Dynamic Table
 * @order 30
 * @tags table, export, CSV, download, filters, table-actions, backend-frontend, export-functionality, RegisterHook, table_action, modellist
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
<p>This section describes how to add actions to tables such as edit and delete or CSV download.</p>

<h1>Register Hook</h1>
<p>In tables you can create actions and register them in JavaScript using the registerHook method.</p>

<h3>1. JavaScript</h3>
<p>For example:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">
registerHook('table-action-table_posts-edit', function (id) {
    window.location.href = milk_url + '?page=posts&action=edit&id=' + id;
    return false;
});

registerHook('table-action-table_posts-delete', function (id) {
    return confirm('Delete this post?');
});
</code></pre>
<p>
The registerHook receives as the first parameter the string <code>'table-action-{table_id}-{action}'</code>.<br> It returns a boolean. If true, it makes the fetch call and updates the table; if false, it doesn't make the fetch call. In this example, edit redirects to the edit page and delete makes the fetch call and updates the table.</p>

<h3>2. Backend PHP</h3>
<p>In the router, the delete calls the same function that is called when generating the table. If you are extending the AbstractRouter, you just need to write:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"> $this->call_table_action($table_id, 'delete', 'table_action_delete');</code></pre>
<p>and then create a new function</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function table_action_delete($id, $request) {
    if ($this->model->delete($id)) {
        return true;
    } else {
        MessagesHandler::add_error($this->model->get_last_error());
        return false;
    }
}
</code></pre>

<p>For edit, instead, it redirects to a new page so it's handled normally in the router by writing a new action_edit function</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function action_edit() {
    $id = _absint($_REQUEST['id'] ?? 0);
    $data = $this->model->get_by_id_for_edit($id,  Route::get_session_data()); 
    Get::theme_page('default', __DIR__ . '/views/edit.page.php',  ['id' => _absint($_REQUEST['id'] ?? 0), 'data' => $data, 'page' => $this->page, 'url_success'=>'?page='.$this->page, 'action_save'=>'save']);
}</code></pre>



<h1>CSV Export with Table Filters</h1>
    
    <p>Tutorial for implementing CSV download of filtered table data using the table_action system.</p>
    
    <h3>1. Export Button Setup</h3>
    <p>Open the list.page.php file and add the export button in the title section:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    'btns' => [ ['title'=>'Add New', 'color'=>'primary', 'link'=>'?page='.$page.'&action=edit'], ['title'=>'Export CSV', 'color'=>'success', 'class'=>'js-export-csv', 'link'=>'#'] ]    
    </code></pre>

    <h3>2. JavaScript - Export Function</h3>
    <p>Create the JavaScript function to handle the download:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// Initialize export button
    // Initialize export button
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('.js-export-csv').addEventListener('click', function() {
        exportData();
    });
});

/**
 * Export table data to CSV
 */
function exportData() {
    const btnExport = document.querySelector('.js-export-csv');
    if (btnExport.classList.contains('disabled')) {
        return;
    }
    btnExport.classList.add('disabled');
    
    // Get table component
    const table = getComponent('table_posts');
    table.setActionFields('export-csv');
    table.set_page(1);
    
    // Submit form to trigger download
    const form = table.getForm();
    form.submit();
    
    // User feedback
    window.toasts.show();
    window.toasts.body('Export started, download will begin shortly...', 'primary');
    
    // Re-enable button
    setTimeout(() => {
        btnExport.classList.remove('disabled');
        window.toasts.hide();
    }, 3000);
}</code></pre>

    <h3>3. Backend PHP</h3>
    <p>In the router, add the logic to handle the export before HTML generation:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('protected function action_home() {
     // At the end of the function before output_table_response add:

        // Table form parameters are of the type {table_id}[param]. So to get them easily you can use the get_request_params function inside the AbstractRouter class
        $request = $this->get_request_params($table_id);
        if (isset($request[\'table_action\']) && $request[\'table_action\'] == \'export-csv\') {
            $this->export_csv($modellist_data[\'rows\']);
            return;
        }
    
}

private function export_csv($rows) {
    $timestamp = date(\'Y-m-d_H-i-s\');
    $filename = "posts_export_{$timestamp}.csv";
    
    header(\'Content-Type: text/csv; charset=utf-8\');
    header(\'Content-Disposition: attachment; filename="\' . $filename . \'"\');
    
    $output = fopen(\'php://output\', \'w\');
    
    // Headers
    fputcsv($output, [\'ID\', \'Title\', \'Author\', \'Status\', \'Created\'], \',\', \'"\', \'\\\\\');
    
    // Data
    foreach ($rows as $row) {
        fputcsv($output, [
            $row->id ?? \'\',
            $row->title ?? \'\',
            $row->author ?? \'\',
            $row->status ?? \'\',
            (is_a($row->created_at, \'DateTime\') ? $row->created_at->format(\'Y-m-d H:i:s\') : $row->created_at) ?? \'\'
        ], \',\', \'"\', \'\\\\\');
    }
    
    fclose($output);
    exit;
}'); ?></code></pre>

</div>