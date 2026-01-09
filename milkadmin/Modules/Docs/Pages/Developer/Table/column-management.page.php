<?php
namespace Modules\Docs\Pages;
/**
 * @title Column Management
 * @guide developer
 * @order 11
 * @tags TableBuilder, columns, visibility, ordering, resetFields, moveBefore, reorderColumns, hide, hideFromList
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Column Management</h1>

    <p>TableBuilder provides multiple ways to control which columns are displayed and in what order. This guide covers all available methods for managing table columns.</p>

    <h2>Quick Reference</h2>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th style="width: 25%">Method</th>
                <th style="width: 35%">Description</th>
                <th style="width: 20%">Scope</th>
                <th style="width: 20%">Use Case</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>->hide()</code> (RuleBuilder)</td>
                <td>Hide field from lists, forms and detail view</td>
                <td>Model-level</td>
                <td>Passwords, sensitive data</td>
            </tr>
            <tr>
                <td><code>->hideFromList()</code> (RuleBuilder)</td>
                <td>Hide field only from list/table view</td>
                <td>Model-level</td>
                <td>Long text fields shown only in forms</td>
            </tr>
            <tr>
                <td><code>->resetFields()</code></td>
                <td>Hide all existing columns, start fresh</td>
                <td>Table-level</td>
                <td>Show only specific columns</td>
            </tr>
            <tr>
                <td><code>->field()</code> / <code>->column()</code></td>
                <td>Define visible columns in order</td>
                <td>Table-level</td>
                <td>Control order and visibility</td>
            </tr>
            <tr>
                <td><code>->hide()</code> (only TableBuilder)</td>
                <td>Hide specific column in this table</td>
                <td>Table-level</td>
                <td>Hide one or few columns</td>
            </tr>
            <tr>
                <td><code>->hideColumns()</code></td>
                <td>Hide multiple columns at once</td>
                <td>Table-level</td>
                <td>Hide several columns</td>
            </tr>
            <tr>
                <td><code>->moveBefore()</code></td>
                <td>Move current field before another</td>
                <td>Table-level</td>
                <td>Adjust single column position</td>
            </tr>
            <tr>
                <td><code>->reorderColumns()</code></td>
                <td>Completely reorder all columns (use at the end!)</td>
                <td>Table-level</td>
                <td>Full control of column order</td>
            </tr>
        </tbody>
    </table>

    <h2>Model-Level Column Control (RuleBuilder)</h2>

    <p>In your Model's <code>configure($rule)</code> method, you can use RuleBuilder methods to control field visibility across the entire application.</p>

    <h3>1. Hide Field Completely</h3>
    <p>Use <code>hide()</code> in RuleBuilder to hide fields from lists, edit forms, and detail views.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class UserModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__users')
            ->id()
            ->string('username', 100)
            ->string('password', 255)->hide()  // Hidden everywhere
            ->email('email');
    }
}</code></pre>

    <h3>2. Hide From List Only</h3>
    <p>Use <code>hideFromList()</code> in RuleBuilder to hide fields from table views while keeping them available in forms.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class PostModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__posts')
            ->id()
            ->string('title', 200)
            ->text('description')->hideFromList()  // Hidden from tables only
            ->text('content')->hideFromList()      // Hidden from tables only
            ->datetime('created_at');
    }
}

// These fields won't appear in tables but are available in forms</code></pre>

    <h3>3. Other RuleBuilder Visibility Methods</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    $rule->table('#__posts')
        ->id()

        // Hide from edit form only
        ->datetime('created_at')->hideFromEdit()

        // Hide from detail view only
        ->string('internal_notes', 255)->hideFromView()

        // Virtual field (not in database)
        ->string('full_name', 100)->excludeFromDatabase();
}</code></pre>

    <h2>TableBuilder Column Control</h2>

    <h3>1. Reset and Rebuild Columns</h3>
    <p>Use <code>resetFields()</code> to start with a clean slate and show only the columns you explicitly define.</p>

    <div class="alert alert-info">
        <h5><i class="bi bi-lightbulb"></i> How resetFields() Works</h5>
        <p class="mb-0">When you call <code>resetFields()</code>, all existing columns are hidden. Then, as you call <code>field()</code> or <code>column()</code>, those columns become visible <strong>in the exact order you define them</strong>.</p>
    </div>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = TableBuilder::create($model, 'posts_table')
    ->resetFields()  // Hide ALL existing columns

    // Only these columns will be shown, in this exact order:
    ->field('id')
        ->label('ID')

    ->field('title')
        ->label('Article Title')
        ->link('?page=posts&action=edit&id=%id%')

    ->field('status')
        ->label('Status')

    ->field('created_at')
        ->label('Published')

    ->getTable();

// Result: Table shows only id, title, status, created_at (in that order)
// All other model columns are hidden</code></pre>

    <h3>2. Define Columns in Order</h3>
    <p>Simply calling <code>field()</code> or <code>column()</code> automatically positions new columns in sequence.</p>

    <div class="alert alert-success">
        <h5><i class="bi bi-stars"></i> Automatic Column Ordering (New Feature)</h5>
        <p class="mb-0">When you add fields with <code>field()</code>, they are automatically positioned after the previous field you defined. No need to manually reorder unless you use <code>moveBefore()</code>!</p>
    </div>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = TableBuilder::create($model, 'users_table')
    // Columns appear in the order you define them
    ->field('id')           // 1st column
    ->field('username')     // 2nd column (automatically after 'id')
    ->field('email')        // 3rd column (automatically after 'username')
    ->field('status')       // 4th column (automatically after 'email')
    ->field('created_at')   // 5th column (automatically after 'status')

    ->getTable();</code></pre>

    <h3>3. Hide Specific Columns</h3>
    <p>Use <code>hide()</code> in the field chain or <code>hideColumns()</code> for multiple columns.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = TableBuilder::create($model, 'posts_table')
    // Hide single column
    ->field('id')
        ->hide()  // Column exists in query but not displayed

    // Hide multiple columns at once
    ->hideColumns(['internal_notes', 'draft_content', 'temp_data'])

    ->getTable();</code></pre>

    <h3>4. Move Column Before Another</h3>
    <p>Use <code>moveBefore()</code> to reposition a single column.</p>

    <div class="alert alert-warning">
        <h5><i class="bi bi-info-circle"></i> Important Behavior with moveBefore()</h5>
        <p class="mb-0">When you use <code>moveBefore()</code>, the <strong>next field you define will be positioned after the field that was BEFORE the move</strong>, not after the moved field. This prevents the automatic ordering from following the moved column.</p>
    </div>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = TableBuilder::create($model, 'posts_table')
    ->field('id')           // Position: 1st
    ->field('title')        // Position: 2nd (after 'id')
    ->field('status')       // Position: 3rd (after 'title')
        ->moveBefore('id')  // Move 'status' before 'id'
                            // New order: status, id, title

    ->field('created_at')   // Position: after 'title' (not after 'status'!)
                            // Final order: status, id, title, created_at

    ->getTable();</code></pre>

    <h3>5. Complete Column Reordering</h3>
    <p>Use <code>reorderColumns()</code> to specify the exact order of all columns.</p>

    <div class="alert alert-danger">
        <h5><i class="bi bi-exclamation-triangle"></i> IMPORTANT: Call reorderColumns() at the END!</h5>
        <p class="mb-0">Always call <code>reorderColumns()</code> <strong>AFTER</strong> all your <code>field()</code> calls. If you call <code>field()</code> after <code>reorderColumns()</code>, the automatic ordering will position the new field and may override your manual ordering!</p>
    </div>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$table = TableBuilder::create($model, 'courses_table')
    // First: Configure all your fields
    ->field('ATTIVO_CRS')
        ->label('Active')
        ->type('select')
        ->options(['0' => 'No', '1' => 'Yes'])

    ->field('CORSO')
        ->label('Course Name')

    // Last: Define the exact column order
    ->reorderColumns(['ATTIVO_CRS', 'CORSO', 'DESCRIZIONE', 'DATA_INIZIO', 'DATA_FINE'])

    ->getTable();

// Columns will appear in the exact order specified in reorderColumns()</code></pre>

</div>
