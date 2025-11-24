<?php
namespace Modules\Docs\Pages;
/**
 * @title List Styling
 * @guide developer
 * @order 23
 * @tags ListBuilder, styling, CSS, appearance, colors, themes, box-classes, field-classes, grid, field-first
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>ListBuilder Styling</h1>

    <p>Complete guide for customizing list appearance using the field-first pattern. Lists display data as Bootstrap cards in a responsive grid layout. Apply CSS classes directly to fields and boxes with clean, readable code.</p>

    <h2>Understanding List Structure</h2>

    <p>The list plugin uses a <code>box_attrs</code> array to control the appearance of different elements. Here's the default structure:</p>

    <table class="table table-sm table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Attribute Key</th>
                <th>Element</th>
                <th>Default Classes</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>form</code></td>
                <td>Form wrapper</td>
                <td><code>card-body-overflow js-list-form container-fluid</code></td>
            </tr>
            <tr>
                <td><code>container</code></td>
                <td>Grid container (row)</td>
                <td><code>row g-3 js-box-container</code></td>
            </tr>
            <tr>
                <td><code>col</code></td>
                <td>Column wrapper for each box</td>
                <td><code>col-12 col-md-6 col-lg-4</code></td>
            </tr>
            <tr>
                <td><code>box</code></td>
                <td>Card/box element</td>
                <td><code>card h-100 js-box-item</code></td>
            </tr>
            <tr>
                <td><code>box.header</code></td>
                <td>Box header (checkbox + actions)</td>
                <td><code>card-header d-flex justify-content-between align-items-center</code></td>
            </tr>
            <tr>
                <td><code>box.body</code></td>
                <td>Box body (field rows)</td>
                <td><code>card-body</code></td>
            </tr>
            <tr>
                <td><code>box.footer</code></td>
                <td>Box footer</td>
                <td><code>card-footer</code></td>
            </tr>
            <tr>
                <td><code>field.row</code></td>
                <td>Single field container</td>
                <td><code>row mb-2 border-bottom pb-2</code></td>
            </tr>
            <tr>
                <td><code>field.label</code></td>
                <td>Field label column</td>
                <td><code>col-5 fw-bold text-muted</code></td>
            </tr>
            <tr>
                <td><code>field.value</code></td>
                <td>Field value column</td>
                <td><code>col-7</code></td>
            </tr>
            <tr>
                <td><code>checkbox.wrapper</code></td>
                <td>Checkbox wrapper</td>
                <td><code>form-check</code></td>
            </tr>
        </tbody>
    </table>

    <h2>Quick Start</h2>

    <h3>Quick Color Themes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'products_list')
    ->boxColor('primary')    // Blue theme
    ->boxColor('success')    // Green theme
    ->boxColor('danger')     // Red theme
    ->boxColor('warning')    // Yellow theme
    ->getResponse();
echo $list['html'];</code></pre>

    <h3>Custom Box Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'products_list')
    // Combine multiple Bootstrap card classes
    ->boxClass('card shadow-sm border-0')
    ->getResponse();
echo $list['html'];</code></pre>

    <h2>Grid Layout</h2>

    <h3>Responsive Columns</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'products_list')
    // Set responsive grid: 1 col on mobile, 2 on tablet, $cols on desktop
    ->gridColumns($cols)
    ->getResponse();
echo $list['html'];</code></pre>

    <p><strong>Note:</strong> Default is <code>col-12 col-md-6 col-lg-4</code> (1 column on mobile, 2 on tablet, 3 on desktop)</p>

    <h3>Custom Container and Column Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'products_list')
    // Customize the container wrapper
    ->containerClass('row g-4 justify-content-center')

    // Customize individual column wrappers
    ->colClass('col-12 col-sm-6 col-lg-4 col-xl-3')

    ->getResponse();
echo $list['html'];</code></pre>

    <h2>Box Styling</h2>

    <h3>Box Component Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'products_list')
    // Style the entire box
    ->boxClass('card shadow-lg border-primary')

    // Style box header
    ->boxHeaderClass('bg-primary text-white fw-bold')

    // Style box body
    ->boxBodyClass('bg-light')

    // Style box footer
    ->boxFooterClass('bg-secondary text-white text-center')

    ->getResponse();
echo $list['html'];</code></pre>

    <h3>Alternating Box Colors</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'products_list')
    // Alternate between two CSS classes
    ->boxClassAlternate('border-primary', 'border-success')
    ->getResponse();
echo $list['html'];</code></pre>

    <h3>Conditional Box Classes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'orders_list')
    // Highlight cancelled orders in red
    ->classByValue( 'cancelled', 'border-danger bg-danger bg-opacity-10')

    // Highlight high-value orders in yellow (total > 1000)
    ->field('total')
    ->classByValue(1000, 'border-warning shadow-lg', '>')

    // Supported operators: '==', '>', '<', '>=', '<=', '!=', 'contains'
    ->getResponse();
echo $list['html'];</code></pre>

    <h2>Field-First Styling</h2>

    <p>The field-first pattern allows you to apply styling directly to each field using method chaining. This keeps all field configurations (data, display, and styling) in one place.</p>

    <h3>Basic Field Styling</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'sales_list')
    // Style specific fields
    ->field('amount')
        ->label('Total Amount')
        ->class('text-success fw-bold fs-5')

    ->field('status')
        ->label('Status')
        ->class('text-center badge bg-primary')

    ->field('customer_name')
        ->label('Customer')
        ->class('fw-semibold text-primary')

    ->getTable();</code></pre>

    <h3>Conditional Field Styling</h3>
    <p>Apply CSS classes when a field matches a specific value using <code>classValue()</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'products_list')
    // Status field with color coding
    ->field('status')
        ->label('Status')
        ->classValue('active', 'badge bg-success')
        ->classValue('inactive', 'badge bg-danger')
        ->classValue('pending', 'badge bg-warning')

    // Stock with conditional styling
    ->field('stock')
        ->label('Stock')
        ->classValue(10, 'text-danger fw-bold fs-4', '<')   // Red if < 10
        ->classValue(50, 'text-warning', '<')               // Orange if < 50
        ->classValue(50, 'text-success', '>=')              // Green if >= 50

    // Price highlighting
    ->field('price')
        ->label('Price')
        ->classValue(100, 'text-success fs-5 fw-bold', '>') // Large green if > 100
        ->class('text-end')

    ->getTable();

// Supported operators: '==', '!=', '>', '<', '>=', '<=', 'contains'</code></pre>

    <h3>Style Based on Another Field</h3>
    <p>Apply CSS classes to a field based on the value of a different field using <code>classOtherValue()</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'products_list')
    // Highlight price when product has discount
    ->field('price')
        ->label('Price')
        ->classOtherValue('has_discount', true, 'text-success fw-bold fs-4')
        ->class('text-end')

    // Show name in muted color if not in stock
    ->field('name')
        ->label('Product Name')
        ->classOtherValue('stock', 0, 'text-muted text-decoration-line-through')
        ->link('?page=products&action=edit&id=%id%')

    // Highlight quantity based on stock status
    ->field('quantity')
        ->label('Quantity')
        ->classOtherValue('stock_status', 'low', 'text-danger fw-bold')
        ->classOtherValue('stock_status', 'out', 'text-danger text-decoration-line-through')

    ->getTable();</code></pre>

    <h2>Global Field Classes</h2>

    <p>You can also set global styles for all field rows, labels, and values:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$list = \Builders\ListBuilder::create($model, 'products_list')
    // Style all field rows
    ->fieldRowClass('mb-3 pb-2 border-bottom')

    // Style all field labels
    ->fieldLabelClass('col-4 fw-bold text-primary')

    // Style all field values
    ->fieldValueClass('col-8 text-end')

    // Then override specific fields with field-first pattern
    ->field('price')
        ->label('Price')
        ->class('text-success fs-4 fw-bold')  // Override global value class

    ->getResponse();
echo $list['html'];</code></pre>

    <h2>Custom Box Template</h2>

    <p>You can replace the default box template with your own custom template file. The template receives the following variables:</p>

    <ul>
        <li><code>$box_attrs</code> - Array of box attributes</li>
        <li><code>$box_item_attrs</code> - Specific attributes for this box (with dynamic classes)</li>
        <li><code>$fields_data</code> - Array of field objects with label, value, type, classes, and attrs</li>
        <li><code>$checkbox</code> - HTML for checkbox (if present)</li>
        <li><code>$actions</code> - HTML for actions (if present)</li>
    </ul>

    <h3>Example: Custom Template for Posts</h3>

    <p><strong>Step 1:</strong> Create the custom template file at <code>Modules/Posts/Views/custom-box.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
!defined('MILK_DIR') && die();
?>

&lt;div &lt;?php Theme\Template::addAttrs($box_attrs, 'col'); ?>>
    &lt;div &lt;?php Theme\Template::addAttrs(['box' => $box_item_attrs], 'box'); ?>>
        &lt;div &lt;?php Theme\Template::addAttrs($box_attrs, 'box.body'); ?>>
            &lt;h2>&lt;?php _p($fields_data['title']->value) ?>&lt;/h2>
            &lt;p>&lt;?php _pt($fields_data['content']->value) ?>&lt;/p>
        &lt;/div>
         &lt;?php if ($checkbox || $actions) { ?>
            &lt;div &lt;?php Theme\Template::addAttrs($box_attrs, 'box.footer'); ?>>
                &lt;?php echo $checkbox; ?>
                &lt;?php echo $actions; ?>
            &lt;/div>
        &lt;?php } ?>
    &lt;/div>
&lt;/div></code></pre>

    <p><strong>Step 2:</strong> Use the template in your controller:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// In PostsController.php
$response['html'] = ListBuilder::create($this->model, 'idTablePosts')
    ->field('content')
        ->truncate(50)

    ->gridColumns(1)

    // Set custom box template
    ->setBoxTemplate(__DIR__ . '/Views/custom-box.php')

    ->setDefaultActions()
    ->render();</code></pre>

    <h3>Template Variables Reference</h3>

    <p>Your custom template has access to these variables:</p>

    <table class="table table-sm table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Variable</th>
                <th>Type</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>$box_attrs</code></td>
                <td>array</td>
                <td>All box attributes (col, box, box.header, box.body, box.footer)</td>
            </tr>
            <tr>
                <td><code>$box_item_attrs</code></td>
                <td>array</td>
                <td>Attributes for this specific box (includes dynamic classes)</td>
            </tr>
            <tr>
                <td><code>$fields_data</code></td>
                <td>array</td>
                <td>Associative array: <code>$fields_data['field_name']->value</code>, <code>->label</code>, <code>->type</code></td>
            </tr>
            <tr>
                <td><code>$checkbox</code></td>
                <td>string</td>
                <td>HTML for checkbox (empty if no bulk actions)</td>
            </tr>
            <tr>
                <td><code>$actions</code></td>
                <td>string</td>
                <td>HTML for row actions (empty if no actions)</td>
            </tr>
        </tbody>
    </table>

    <h3>Accessing Field Data</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;!-- Access specific field values -->
&lt;?php _p($fields_data['title']->value) ?>
&lt;?php _p($fields_data['price']->value) ?>
&lt;?php _p($fields_data['created_at']->value) ?>

&lt;!-- Check field type -->
&lt;?php if ($fields_data['description']->type == 'html') {
    _ph($fields_data['description']->value);
} else {
    _p($fields_data['description']->value);
} ?>

&lt;!-- Loop through all fields -->
&lt;?php foreach ($fields_data as $field_name => $field) : ?>
    &lt;strong>&lt;?php _pt($field->label); ?>:&lt;/strong>
    &lt;?php _p($field->value); ?>
&lt;?php endforeach; ?></code></pre>

    <h2>Complete Styling Example</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$model = new \Models\ProductModel();

$list = \Builders\ListBuilder::create($model, 'products_list')
    // Query configuration
    ->where('deleted = ?', [0])
    ->orderBy('created_at', 'desc')
    ->limit(24)

    // Grid Layout: responsive columns
    ->gridColumns(3)

    // Container styling
    ->containerClass('row g-4')

    // Box-level styling
    ->boxClass('card h-100 shadow-sm')
    ->boxColor('primary')

    // Conditional box highlighting
    ->classByValue('stock', 0, 'border-danger border-3')
    ->classByValue('status', 'featured', 'shadow-lg border-warning border-2')

    // Box component styling
    ->boxHeaderClass('bg-gradient text-white')
    ->boxBodyClass('p-3')

    // Global field styling
    ->fieldRowClass('mb-2 pb-2')
    ->fieldLabelClass('col-5 text-muted small')
    ->fieldValueClass('col-7 fw-semibold')

    // Field-first styling for specific fields
    ->field('name')
        ->label('Product Name')
        ->link('?page=products&action=edit&id=%id%')
        ->classOtherValue('featured', true, 'fw-bold text-primary')

    ->field('price')
        ->label('Price')
        ->class('text-success fs-5 fw-bold text-end')
        ->classValue(100, 'text-danger fs-4', '>')  // Extra large red if > 100

    ->field('stock')
        ->label('Stock')
        ->class('text-center')
        ->classValue(10, 'text-danger fw-bold', '<')
        ->classValue(0, 'badge bg-danger', '==')

    ->field('status')
        ->label('Status')
        ->classValue('active', 'badge bg-success')
        ->classValue('inactive', 'badge bg-secondary')
        ->classValue('featured', 'badge bg-warning text-dark')

    ->field('category')
        ->label('Category')
        ->class('text-muted')

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
                <td>Set CSS classes for field value</td>
                <td><code>->class('fw-bold text-primary')</code></td>
            </tr>
            <tr>
                <td><code>classValue()</code></td>
                <td>$value, $classes, $operator</td>
                <td>Conditional classes based on field value</td>
                <td><code>->classValue('active', 'badge bg-success')</code></td>
            </tr>
            <tr>
                <td><code>classOtherValue()</code></td>
                <td>$field, $value, $classes, $operator</td>
                <td>Conditional classes based on other field</td>
                <td><code>->classOtherValue('featured', true, 'fw-bold')</code></td>
            </tr>
        </tbody>
    </table>

    <h3>Global Field Styling Methods</h3>
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
                <td><code>fieldRowClass()</code></td>
                <td>$classes</td>
                <td>CSS classes for all field row containers</td>
            </tr>
            <tr>
                <td><code>fieldLabelClass()</code></td>
                <td>$classes</td>
                <td>CSS classes for all field labels</td>
            </tr>
            <tr>
                <td><code>fieldValueClass()</code></td>
                <td>$classes</td>
                <td>CSS classes for all field values</td>
            </tr>
        </tbody>
    </table>

    <h3>Box & Layout Methods</h3>
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
                <td><code>boxColor()</code></td>
                <td>$color</td>
                <td>Quick theme colors (primary, success, danger, warning, info, light, dark)</td>
            </tr>
            <tr>
                <td><code>boxClass()</code></td>
                <td>$classes</td>
                <td>Custom CSS classes for box element</td>
            </tr>
            <tr>
                <td><code>boxHeaderClass()</code></td>
                <td>$classes</td>
                <td>CSS classes for box header</td>
            </tr>
            <tr>
                <td><code>boxBodyClass()</code></td>
                <td>$classes</td>
                <td>CSS classes for box body</td>
            </tr>
            <tr>
                <td><code>boxFooterClass()</code></td>
                <td>$classes</td>
                <td>CSS classes for box footer</td>
            </tr>
            <tr>
                <td><code>boxClassAlternate()</code></td>
                <td>$odd_classes, $even_classes</td>
                <td>Alternate box colors</td>
            </tr>
            <tr>
                <td><code>classByValue()</code></td>
                <td>$value, $classes, $operator</td>
                <td>Conditional box classes based on field value</td>
            </tr>
            <tr>
                <td><code>containerClass()</code></td>
                <td>$classes</td>
                <td>CSS classes for grid container</td>
            </tr>
            <tr>
                <td><code>colClass()</code></td>
                <td>$classes</td>
                <td>CSS classes for column wrappers</td>
            </tr>
            <tr>
                <td><code>gridColumns()</code></td>
                <td>$cols</td>
                <td>Set responsive grid columns (e.g., 2, 3, 4)</td>
            </tr>
            <tr>
                <td><code>checkboxWrapperClass()</code></td>
                <td>$classes</td>
                <td>CSS classes for checkbox wrapper</td>
            </tr>
            <tr>
                <td><code>setBoxTemplate()</code></td>
                <td>$template_path</td>
                <td>Set custom box template file (absolute path)</td>
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
        <li><strong>Use field-first pattern</strong>: Keep all configuration for a field (label, type, styling) together</li>
        <li><strong>Combine global and specific styling</strong>: Set global field styles with fieldLabelClass(), then override specific fields with field()->class()</li>
        <li><strong>Use semantic classes</strong>: Prefer Bootstrap utility classes (badge, text-success) for consistency</li>
        <li><strong>Chain multiple classValue()</strong>: Apply different styles for different conditions on the same field</li>
        <li><strong>Test responsive grid</strong>: Use gridColumns() to ensure proper display on all devices</li>
        <li><strong>Accessibility</strong>: Don't rely solely on color; use text, icons, or badges for critical information</li>
    </ul>

</div>
