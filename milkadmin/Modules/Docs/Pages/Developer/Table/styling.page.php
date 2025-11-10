<?php
namespace Modules\Docs\Pages;
/**
 * @title Table Styling
 * @guide developer
 * @order 13
 * @tags TableBuilder, styling, CSS, appearance, colors, themes, row-classes, column-classes
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>TableBuilder Styling</h1>

    <p>Complete guide for customizing table appearance, colors, and CSS classes in TableBuilder.</p>


    <h3>Quick Color Themes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    ->tableColor('primary')    // Blue theme
    ->tableColor('success')    // Green theme
    ->tableColor('danger')     // Red theme
    ->tableColor('striped')    // Striped rows
    ->getResponse();
echo $table['html'];</code></pre>

    <h3>Custom Table Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'posts_table')
    // Combine multiple Bootstrap classes
    ->tableClass('table table-hover table-bordered table-sm')
    ->getResponse();
echo $table['html'];</code></pre>

    <h2>Row Styling</h2>

    <h3>Alternating Row Colors</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'orders_table')
    // Alternate between two CSS classes
    ->rowClassAlternate('table-light', 'table-dark')
    ->getResponse();
echo $table['html'];</code></pre>

    <h3>Conditional Row Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'orders_table')
    // Highlight cancelled orders in red
    ->rowClassByValue('status', 'cancelled', 'table-danger')

    // Highlight high-value orders in yellow (total > 1000)
    ->rowClassByValue('total', 1000, 'table-warning', '>')

    // Supported operators: '=', '>', '<', '>=', '<=', '!='
    ->getResponse();
echo $table['html'];</code></pre>

    <h2>Column Styling</h2>

    <h3>Column-Specific Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'sales_table')
    // Right-align and bold the amount column
    ->columnClass('amount', 'text-end font-weight-bold')

    // Center-align status column
    ->columnClass('status', 'text-center')

    ->getResponse();
echo $table['html'];</code></pre>

    <h3>Header Column Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'sales_table')
    // Custom header styling for specific columns
    ->headerColumnClass('amount', 'bg-success text-white')
    ->headerColumnClass('status', 'bg-info')
    ->getResponse();
echo $table['html'];</code></pre>

    <h2>Cell-Level Styling</h2>

    <h3>Conditional Cell Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'products_table')
    // Green text for active status
    ->cellClassByValue('status', 'status', 'active', 'text-success')

    // Red text for inactive status
    ->cellClassByValue('status', 'status', 'inactive', 'text-danger')

    // Bold high stock quantities (> 100)
    ->cellClassByValue('stock', 'stock', 100, 'font-weight-bold text-primary', '>')

    ->getResponse();
echo $table['html'];

// Parameters: (columnKey, fieldToCheck, valueToCompare, cssClass, operator)</code></pre>

    <h2>Footer Styling</h2>

    <h3>Footer Configuration</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'sales_table')
    // Define footer content (one value per column)
    ->setFooter([
        '',              // Empty for ID column
        'Total:',        // Label
        '€ 15,420.50',   // Total amount
        '',              // Empty for actions
    ])

    // Style the footer row
    ->footerClass('table-dark font-weight-bold')

    ->getResponse();
echo $table['html'];</code></pre>

    <h2>Complete Styling Example</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$model = new \Models\OrderModel();

$table = \Builders\TableBuilder::create($model, 'orders_table')
    ->select(['id', 'customer', 'total', 'status', 'created_at'])
    ->orderBy('created_at', 'desc')
    ->limit(50)

    // Table-level styling
    ->tableClass('table table-hover table-bordered')

    // Row styling: alternate colors
    ->rowClassAlternate('', 'table-light')

    // Conditional row highlighting
    ->rowClassByValue('status', 'cancelled', 'table-danger')
    ->rowClassByValue('total', 5000, 'table-success', '>')

    // Column alignment
    ->columnClass('total', 'text-end font-weight-bold')
    ->columnClass('status', 'text-center')
    ->headerColumnClass('total', 'bg-primary text-white')

    // Cell-specific colors
    ->cellClassByValue('status', 'status', 'pending', 'badge bg-warning')
    ->cellClassByValue('status', 'status', 'completed', 'badge bg-success')
    ->cellClassByValue('status', 'status', 'cancelled', 'badge bg-danger')

    // Footer with totals
    ->setFooter(['', '', 'Total:', '€ 125,430.00', '', ''])
    ->footerClass('table-dark font-weight-bold text-end')

    ->getResponse();
echo $table['html'];</code></pre>

    <h2>Method Reference</h2>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>tableColor()</code></td>
                <td>$color</td>
                <td>Quick theme colors (primary, success, danger, striped)</td>
            </tr>
            <tr>
                <td><code>tableClass()</code></td>
                <td>$class</td>
                <td>Custom CSS classes for table element</td>
            </tr>
            <tr>
                <td><code>rowClassAlternate()</code></td>
                <td>$class1, $class2</td>
                <td>Alternate row colors</td>
            </tr>
            <tr>
                <td><code>rowClassByValue()</code></td>
                <td>$field, $value, $class, $operator</td>
                <td>Conditional row classes based on field value</td>
            </tr>
            <tr>
                <td><code>columnClass()</code></td>
                <td>$columnKey, $class</td>
                <td>CSS classes for column cells</td>
            </tr>
            <tr>
                <td><code>headerColumnClass()</code></td>
                <td>$columnKey, $class</td>
                <td>CSS classes for column header</td>
            </tr>
            <tr>
                <td><code>cellClassByValue()</code></td>
                <td>$columnKey, $field, $value, $class, $operator</td>
                <td>Conditional cell classes based on value</td>
            </tr>
            <tr>
                <td><code>setFooter()</code></td>
                <td>$footerArray</td>
                <td>Set footer content (array of values per column)</td>
            </tr>
            <tr>
                <td><code>footerClass()</code></td>
                <td>$class</td>
                <td>CSS classes for footer row</td>
            </tr>
        </tbody>
    </table>

</div>
