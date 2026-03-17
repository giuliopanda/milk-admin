<?php
namespace Modules\Docs\Pages;
/**
 * @title Search & Filters
 * @guide developer
 * @order 15
 * @tags SearchBuilder, filters, search, fluent-interface, table-integration
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Search & Filters</h1>
<p class="text-muted">Revision: 2025/12/16</p>
    <p>SearchBuilder creates search and filter interfaces for tables and lists. It uses a fluent interface (method chaining) to build search fields, dropdowns, tab filters, and other filtering controls.</p>

    <h2>Basic Concepts</h2>

    <h3>Creation and Table ID</h3>
    <p>SearchBuilder requires a unique ID that must match the ID of the table or list to filter:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTablePosts');</code></pre>

    <h3>Automatic Search</h3>
    <p>The "search" field is automatically processed by TableBuilder across all text fields in the table. It does not require custom filter definitions.</p>

    <h3>Custom Filters</h3>
    <p>Other filter types (select, actionList, input) require explicit behavior definition through TableBuilder's <code>filter()</code> method.</p>

    <h2>Available Methods</h2>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Type</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr class="table-secondary">
                <td colspan="4"><strong>Creator Methods - Create new fields</strong></td>
            </tr>
            <tr>
                <td><code>search($filter_type)</code></td>
                <td>Creator</td>
                <td>string (default: 'search')</td>
                <td>Creates a text search field</td>
            </tr>
            <tr>
                <td><code>select($filter_type)</code></td>
                <td>Creator</td>
                <td>string</td>
                <td>Creates a dropdown select</td>
            </tr>
            <tr>
                <td><code>actionList($filter_type)</code></td>
                <td>Creator</td>
                <td>string</td>
                <td>Creates a clickable tab filter</td>
            </tr>
            <tr>
                <td><code>input($type, $filter_type)</code></td>
                <td>Creator</td>
                <td>string, string</td>
                <td>Creates a generic input (date, number, email, etc.)</td>
            </tr>
            <tr>
                <td><code>searchButton()</code></td>
                <td>Creator</td>
                <td>-</td>
                <td>Creates a manual search button</td>
            </tr>
            <tr>
                <td><code>clearButton()</code></td>
                <td>Creator</td>
                <td>-</td>
                <td>Creates a filter reset button</td>
            </tr>
            <tr class="table-secondary">
                <td colspan="4"><strong>Modifier Methods - Modify the current field</strong></td>
            </tr>
            <tr>
                <td><code>label($label)</code></td>
                <td>Modifier</td>
                <td>string</td>
                <td>Sets the field label</td>
            </tr>
            <tr>
                <td><code>placeholder($placeholder)</code></td>
                <td>Modifier</td>
                <td>string</td>
                <td>Sets the placeholder</td>
            </tr>
            <tr>
                <td><code>class($class)</code></td>
                <td>Modifier</td>
                <td>string</td>
                <td>Adds custom CSS classes to the container</td>
            </tr>
            <tr>
                <td><code>layout($layout)</code></td>
                <td>Modifier</td>
                <td>'inline'|'stacked'|'full-width'</td>
                <td>Sets the field layout</td>
            </tr>
            <tr>
                <td><code>options($options)</code></td>
                <td>Modifier</td>
                <td>array</td>
                <td>Sets options for select or actionList</td>
            </tr>
            <tr>
                <td><code>selected($value)</code></td>
                <td>Modifier</td>
                <td>string</td>
                <td>Sets selected value for select or actionList</td>
            </tr>
            <tr>
                <td><code>value($value)</code></td>
                <td>Modifier</td>
                <td>string</td>
                <td>Sets default value for input fields</td>
            </tr>
            <tr class="table-secondary">
                <td colspan="4"><strong>Configuration Methods - Global settings</strong></td>
            </tr>
            <tr>
                <td><code>setSearchMode($mode, $auto_buttons)</code></td>
                <td>Config</td>
                <td>string, bool</td>
                <td>Sets mode to 'onchange' or 'submit'</td>
            </tr>
            <tr>
                <td><code>setWrapperClass($class)</code></td>
                <td>Config</td>
                <td>string</td>
                <td>Sets CSS classes for the fields wrapper</td>
            </tr>
            <tr>
                <td><code>setContainerClasses($classes)</code></td>
                <td>Config</td>
                <td>string</td>
                <td>Sets CSS classes for the main container</td>
            </tr>
            <tr>
                <td><code>setFormClasses($classes)</code></td>
                <td>Config</td>
                <td>string</td>
                <td>Sets CSS classes for form elements</td>
            </tr>
            <tr class="table-secondary">
                <td colspan="4"><strong>Output Methods</strong></td>
            </tr>
            <tr>
                <td><code>render($options)</code></td>
                <td>Output</td>
                <td>array</td>
                <td>Generates and returns the HTML</td>
            </tr>
        </tbody>
    </table>

    <h2>Update Modes</h2>

    <p>SearchBuilder supports two filter execution modes:</p>

    <h3>onChange Mode (Default)</h3>
    <p>Filters are applied automatically on every value change. This is the default mode.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTablePosts')
    ->search('search')
    ->select('status')
        ->options(['all' => 'All', 'active' => 'Active'])
        ->selected('all');
// Filters activate automatically onChange</code></pre>

    <h3>Submit Mode</h3>
    <p>Filters are applied only when clicking the Search button. Useful for multiple or complex filters.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTablePosts')
    ->setSearchMode('submit', true) // true automatically adds buttons
    ->input('date', 'start_date')
    ->input('date', 'end_date')
    ->select('category');
// Filters activate only when clicking Search</code></pre>

    <p><strong>setSearchMode parameters:</strong></p>
    <ul>
        <li><code>$mode</code>: 'onchange' (default) or 'submit'</li>
        <li><code>$auto_buttons</code>: if true, automatically adds Search and Clear buttons in submit mode</li>
    </ul>

    <h2>Field Layouts</h2>

    <p>Each field can have a specific layout via <code>->layout()</code>:</p>

    <ul>
        <li><strong>inline</strong> (default): Label and field on the same row, horizontally aligned</li>
        <li><strong>stacked</strong>: Label above the field, vertical layout</li>
        <li><strong>full-width</strong>: Field takes full available width</li>
    </ul>

    <h2>TableBuilder Integration</h2>

    <h3>Automatic Filter (search)</h3>
    <p>The search field is automatically handled by TableBuilder across all text fields:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTablePosts')
    ->search('search')
    ->placeholder('Search posts...');

$table = TableBuilder::create($model, 'idTablePosts');
// The search filter works automatically</code></pre>

    <h3>Custom Filters</h3>
    <p>Other filters require definition with <code>filter()</code>. The filter name must match the <code>$filter_type</code> used in SearchBuilder:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTablePosts')
    ->actionList('status')
        ->label('Status:')
        ->options(['active' => 'Active', 'deleted' => 'Deleted'])
        ->selected('active');

$table = TableBuilder::create($model, 'idTablePosts')
    ->filter('status', function($query, $value) {
        if ($value === 'deleted') {
            $query->where('deleted_at IS NOT NULL');
        } else {
            $query->where('deleted_at IS NULL');
        }
    }, 'active');</code></pre>

    <h2>Examples</h2>

    <h3>Basic Search</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTablePosts')
    ->search('search')
    ->placeholder('Type to search...');</code></pre>

    <h3>Tab Filter (ActionList)</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTablePosts')
    ->actionList('status')
        ->label('Filter by:')
        ->options([
            'all' => 'All',
            'published' => 'Published',
            'draft' => 'Draft'
        ])
        ->selected('all');</code></pre>

    <h3>Dropdown Select</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTableProducts')
    ->select('category')
        ->label('Category:')
        ->options(['' => 'All', 'tech' => 'Tech', 'design' => 'Design'])
        ->selected('');</code></pre>

    <h3>Custom Input</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTableOrders')
    ->input('date', 'start_date')
        ->label('From:')
        ->placeholder('2024-01-01')
    ->input('date', 'end_date')
        ->label('To:')
        ->placeholder('2024-12-31');</code></pre>

    <h3>Multiple Filters with Layout</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = SearchBuilder::create('idTableUsers')
    ->actionList('role')
        ->label('Role:')
        ->options(['all' => 'All', 'admin' => 'Admin', 'user' => 'User'])
        ->selected('all')
        ->layout('inline')
    ->select('status')
        ->label('Status:')
        ->options(['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'])
        ->layout('inline')
    ->search('search')
        ->placeholder('Search users...')
        ->layout('full-width');

$table = TableBuilder::create($userModel, 'idTableUsers')
    ->filter('role', function($query, $value) {
        if ($value !== 'all') {
            $query->where('role = ?', [$value]);
        }
    }, 'all')
    ->filter('status', function($query, $value) {
        if (!empty($value)) {
            $query->where('status = ?', [$value]);
        }
    }, '');</code></pre>

    <h2>Important Notes</h2>

    <div class="alert alert-info">
        <ul class="mb-0">
            <li>SearchBuilder ID must exactly match TableBuilder ID</li>
            <li>The "search" filter works automatically on all text fields</li>
            <li>Custom filters require <code>filter()</code> method definition in TableBuilder</li>
            <li>Filter name in <code>filter()</code> must match <code>$filter_type</code> in SearchBuilder</li>
            <li>Default value must be consistent between SearchBuilder and TableBuilder</li>
        </ul>
    </div>

    <div class="alert alert-warning">
        <p class="mb-0"><strong>Do not wrap the table output in a div with the table ID.</strong> TableBuilder already includes the correct ID internally. An additional wrapper with the same ID breaks filter functionality.</p>
    </div>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder Documentation</a></li>
        <li><a href="?page=docs&action=Developer/Table/row-actions">Row Actions Documentation</a></li>
        <li><a href="?page=docs&action=Developer/Table/bulk-actions">Bulk Actions Documentation</a></li>
    </ul>
</div>
