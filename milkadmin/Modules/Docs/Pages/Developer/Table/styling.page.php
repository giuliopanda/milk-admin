<?php
namespace Modules\Docs\Pages;
/**
 * @title Table Styling
 * @guide developer
 * @order 13
 * @tags TableBuilder, styling, CSS, appearance, colors, themes, row-classes, column-classes, field-first
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>TableBuilder Styling</h1>

    <p>Complete guide for customizing table appearance using the field-first pattern. Apply CSS classes directly to fields, conditionally style based on values, and create dynamic, visually appealing tables.</p>

    <h2>Table-Level Styling</h2>

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

    <h2>Field-First Styling</h2>

    <p>The field-first pattern allows you to apply styling directly to each field using method chaining. This keeps all field configurations (data, display, and styling) in one place.</p>

    <h3>Basic Field Styling</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'sales_table')
    // Right-align and bold the amount column
    ->field('amount')
        ->label('Total Amount')
        ->class('text-end fw-bold')

    // Center-align status column
    ->field('status')
        ->label('Status')
        ->class('text-center')

    // Left-align customer name
    ->field('customer_name')
        ->label('Customer')
        ->class('text-start')

    ->getTable();</code></pre>

    <h3>Header Column Styling</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'sales_table')
    // Custom header styling for specific fields
    ->field('amount')
        ->label('Amount')
        ->colHeaderClass('bg-success text-white text-end')
        ->class('text-end')

    ->field('status')
        ->label('Status')
        ->colHeaderClass('bg-info text-center')
        ->class('text-center')

    ->getTable();</code></pre>

    <h2>Conditional Styling</h2>

    <h3>Style Based on Field Value</h3>
    <p>Apply CSS classes when a field matches a specific value using <code>classValue()</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'products_table')
    // Status field with color coding
    ->field('status')
        ->label('Status')
        ->classValue('active', 'text-success fw-bold')      // Green for active
        ->classValue('inactive', 'text-danger')             // Red for inactive
        ->classValue('pending', 'text-warning')             // Orange for pending

    // Stock with conditional styling
    ->field('stock')
        ->label('Stock')
        ->classValue(10, 'text-danger fw-bold', '<')       // Red if < 10
        ->classValue(50, 'text-warning', '<')              // Orange if < 50
        ->classValue(50, 'text-success', '>=')             // Green if >= 50

    // Price highlighting
    ->field('price')
        ->label('Price')
        ->classValue(100, 'text-success fs-5', '>')        // Large green if > 100
        ->class('text-end')

    ->getTable();

// Supported operators: '==', '!=', '>', '<', '>=', '<=', 'contains'</code></pre>

    <h3>Style Based on Another Field</h3>
    <p>Apply CSS classes to a field based on the value of a different field using <code>classOtherValue()</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'products_table')
    // Highlight price when product has discount
    ->field('price')
        ->label('Price')
        ->classOtherValue('has_discount', true, 'text-success fw-bold')
        ->class('text-end')

    // Show title in muted color if not published
    ->field('title')
        ->label('Title')
        ->classOtherValue('published', false, 'text-muted')
        ->link('?page=products&action=edit&id=%id%')

    // Highlight quantity based on stock status
    ->field('quantity')
        ->label('Quantity')
        ->classOtherValue('stock_status', 'low', 'text-danger')
        ->classOtherValue('stock_status', 'out', 'text-danger text-decoration-line-through')

    ->getTable();</code></pre>

    <h2>Cell-Level Styling</h2>

    <h3>Cell Background Colors</h3>
    <p>Use <code>cellClassValue()</code> to apply background colors and other styles to individual cells.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'orders_table')
    // Status badges with background colors
    ->field('status')
        ->label('Status')
        ->cellClassValue('pending', 'bg-warning text-dark')
        ->cellClassValue('completed', 'bg-success text-white')
        ->cellClassValue('cancelled', 'bg-danger text-white')
        ->class('text-center')

    // Priority highlighting
    ->field('priority')
        ->label('Priority')
        ->cellClassValue('high', 'bg-danger text-white fw-bold')
        ->cellClassValue('medium', 'bg-warning text-dark')
        ->cellClassValue('low', 'bg-light')

    ->getTable();</code></pre>

    <h3>Cell Styling Based on Other Fields</h3>
    <p>Use <code>cellClassOtherValue()</code> to style cells based on other field values.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'products_table')
    // Highlight price cell when on sale
    ->field('price')
        ->label('Price')
        ->cellClassOtherValue('on_sale', true, 'bg-warning text-dark fw-bold')
        ->class('text-end')

    // Show discount price in green background
    ->field('discount_price')
        ->label('Discount')
        ->cellClassOtherValue('has_discount', true, 'bg-success text-white')
        ->class('text-end')

    ->getTable();</code></pre>

    <h3>Alternating Column Colors</h3>
    <p>Use <code>classAlternate()</code> to create striped column effects.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'data_table')
    ->field('value')
        ->label('Value')
        ->classAlternate('bg-light', 'bg-white')

    ->getTable();</code></pre>

    <h2>Row-Level Styling</h2>

    <h3>Conditional Row Highlighting</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'orders_table')
    // Highlight entire rows based on status
    ->rowClassByValue('status', 'cancelled', 'table-danger')
    ->rowClassByValue('status', 'pending', 'table-warning')

    // Highlight high-value orders (total > 1000)
    ->rowClassByValue('total', 1000, 'table-success', '>')

    // Supported operators: '==', '!=', '>', '<', '>=', '<='
    ->getTable();</code></pre>

    <h3>Alternating Row Colors</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'orders_table')
    // Classic striped rows
    ->rowClassAlternate('', 'table-light')

    // Or use tableColor for built-in striping
    ->tableColor('striped')

    ->getTable();</code></pre>

    <h2>Footer Styling</h2>

    <h3>Footer with Totals</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = \Builders\TableBuilder::create($model, 'sales_table')
    // Define footer content (one value per column)
    ->setFooter([
        '',              // Empty for ID column
        'Total:',        // Label
        '€ 15,420.50',   // Total amount
        '',              // Empty for actions
    ])

    // Style the footer row
    ->footerClass('table-dark fw-bold')

    ->getTable();</code></pre>

    <h2>Complete Styling Example</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$model = new \Models\OrderModel();

$table = \Builders\TableBuilder::create($model, 'orders_table')
    // Query configuration
    ->where('deleted = ?', [0])
    ->orderBy('created_at', 'desc')
    ->limit(50)

    // Table-level styling
    ->tableClass('table table-hover table-bordered')

    // Field configurations with styling
    ->field('id')
        ->label('Order #')
        ->class('text-muted')

    ->field('customer')
        ->label('Customer Name')
        ->link('?page=customers&id=%customer_id%')
        ->classOtherValue('vip_status', true, 'fw-bold text-primary')

    ->field('total')
        ->label('Total Amount')
        ->class('text-end fw-bold')
        ->colHeaderClass('bg-primary text-white text-end')
        ->classValue(5000, 'text-success fs-5', '>')       // Large green if > 5000
        ->classValue(1000, 'text-warning', '>')            // Orange if > 1000

    ->field('status')
        ->label('Status')
        ->class('text-center')
        ->cellClassValue('pending', 'bg-warning text-dark')
        ->cellClassValue('completed', 'bg-success text-white')
        ->cellClassValue('cancelled', 'bg-danger text-white')

    ->field('priority')
        ->label('Priority')
        ->classValue('high', 'badge bg-danger')
        ->classValue('medium', 'badge bg-warning')
        ->classValue('low', 'badge bg-secondary')

    ->field('created_at')
        ->label('Order Date')
        ->type('datetime')
        ->class('text-muted')

    // Row-level conditional highlighting
    ->rowClassByValue('status', 'cancelled', 'table-danger')
    ->rowClassByValue('total', 5000, 'table-success', '>')

    // Footer with totals
    ->setFooter(['', '', 'Total:', '€ 125,430.00', '', '', ''])
    ->footerClass('table-dark fw-bold')

    // Actions
    ->setDefaultActions()

    ->getTable();</code></pre>

    <h2>Method Reference</h2>

    <h3>Field-First Styling Methods</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>field()</code></td>
                <td>$key</td>
                <td>Select field for styling</td>
                <td><code>->field('status')</code></td>
            </tr>
            <tr>
                <td><code>class()</code></td>
                <td>$classes</td>
                <td>Set CSS classes for field column</td>
                <td><code>->class('text-end fw-bold')</code></td>
            </tr>
            <tr>
                <td><code>colHeaderClass()</code></td>
                <td>$classes</td>
                <td>Set CSS classes for column header</td>
                <td><code>->colHeaderClass('bg-primary text-white')</code></td>
            </tr>
            <tr>
                <td><code>classValue()</code></td>
                <td>$value, $classes, $operator</td>
                <td>Conditional classes based on field value</td>
                <td><code>->classValue('active', 'text-success')</code></td>
            </tr>
            <tr>
                <td><code>classOtherValue()</code></td>
                <td>$field, $value, $classes, $operator</td>
                <td>Conditional classes based on other field</td>
                <td><code>->classOtherValue('published', true, 'fw-bold')</code></td>
            </tr>
            <tr>
                <td><code>cellClassValue()</code></td>
                <td>$value, $classes, $operator</td>
                <td>Cell background/style based on value</td>
                <td><code>->cellClassValue('pending', 'bg-warning')</code></td>
            </tr>
            <tr>
                <td><code>cellClassOtherValue()</code></td>
                <td>$field, $value, $classes, $operator</td>
                <td>Cell background/style based on other field</td>
                <td><code>->cellClassOtherValue('on_sale', true, 'bg-success')</code></td>
            </tr>
            <tr>
                <td><code>classAlternate()</code></td>
                <td>$odd_classes, $even_classes</td>
                <td>Alternate column row colors</td>
                <td><code>->classAlternate('bg-light', 'bg-white')</code></td>
            </tr>
        </tbody>
    </table>

    <h3>Table-Level Styling Methods</h3>
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

    <h2>Supported Comparison Operators</h2>
    <ul>
        <li><code>==</code> - Equal to (default)</li>
        <li><code>!=</code> - Not equal to</li>
        <li><code>></code> - Greater than</li>
        <li><code><</code> - Less than</li>
        <li><code>>=</code> - Greater than or equal to</li>
        <li><code><=</code> - Less than or equal to</li>
        <li><code>contains</code> - String contains value</li>
    </ul>

    <h2>Best Practices</h2>
    <ul>
        <li><strong>Group related styling</strong>: Keep all styling for a field together using the field-first pattern</li>
        <li><strong>Use semantic classes</strong>: Prefer Bootstrap utility classes (text-success, bg-danger) for consistency</li>
        <li><strong>Combine methods</strong>: You can chain multiple classValue() calls for different conditions on the same field</li>
        <li><strong>Test responsiveness</strong>: Ensure your styling works on mobile devices</li>
        <li><strong>Accessibility</strong>: Don't rely solely on color; use text or icons for critical information</li>
    </ul>

</div>
