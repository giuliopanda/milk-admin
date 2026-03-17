<?php
namespace Modules\Docs\Pages;

/**
 * @title Projects Lite Guide
 * @guide user
 * @order 6
 * @tags projects, projects-lite, admin, forms, field-builder, manifest, user-guide
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Projects Lite: Complete Practical Guide</h1>

    <p>
        This guide describes the full <strong>Projects Lite</strong> administration flow:
        from module creation and configuration to field creation and the meaning of each option.
    </p>

    <div class="alert alert-info">
        <strong>Note:</strong> this guide reflects the <strong>Lite</strong> version:
        one main table only, no subtables, and no dedicated edit-record-view.
    </div>

    <h2>1) Create a New Project</h2>
    <p>Page: <code>?page=projects&action=create-project</code></p>
    <p>Fill in these fields:</p>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Field</th>
                <th>What It Does</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>Project Name</code></td>
                <td>Logical project name. The module name is generated from this value.</td>
            </tr>
            <tr>
                <td><code>Description</code></td>
                <td>Initial project description, saved in the manifest.</td>
            </tr>
            <tr>
                <td><code>Main Table Name</code></td>
                <td>Name of the main database table to create (new table only in Projects Lite).</td>
            </tr>
        </tbody>
    </table>

    <p>After saving, base module files are generated in <code>milkadmin_local/Modules/&lt;ModuleName&gt;</code>:</p>
    <ul>
        <li>module PHP file</li>
        <li>main model</li>
        <li><code>Project/manifest.json</code></li>
        <li><code>Project/&lt;MainForm&gt;.json</code></li>
        <li><code>Project/search_filters.json</code></li>
    </ul>

    <h2>2) Main Project Hub</h2>
    <p>Page: <code>?page=projects&action=edit&module=&lt;ModuleName&gt;</code></p>
    <p>From here you use three areas:</p>
    <ol>
        <li><strong>Module Configuration</strong>: module/menu metadata</li>
        <li><strong>Build Forms</strong>: <strong>Edit</strong> and <strong>Config</strong> buttons for the main table</li>
        <li><strong>Filters and Search</strong>: filters and URL parameters configuration</li>
    </ol>

    <h2>3) Module Configuration</h2>
    <p>Page: <code>?page=projects&action=edit-module-configuration&module=&lt;ModuleName&gt;</code></p>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Field</th>
                <th>What It Does</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>Project Title</code></td>
                <td>Functional project title in the manifest.</td>
            </tr>
            <tr>
                <td><code>Project Description</code></td>
                <td>Text description of the project.</td>
            </tr>
            <tr>
                <td><code>Menu Name</code></td>
                <td>Sidebar menu label (if empty, project name is used).</td>
            </tr>
            <tr>
                <td><code>Menu Icon</code></td>
                <td>Bootstrap icon for the module menu.</td>
            </tr>
            <tr>
                <td><code>Select Menu Group</code></td>
                <td>Groups the module under an existing menu.</td>
            </tr>
        </tbody>
    </table>

    <h2>4) Build Forms: Edit Button (Main Table Fields)</h2>
    <p>Page: <code>?page=projects&action=build-form-fields&module=&lt;ModuleName&gt;&ref=&lt;MainForm&gt;.json</code></p>
    <p>Here you create and manage fields for the main form/table. The field modal has two tabs: <strong>Basic</strong> and <strong>Advanced</strong>.</p>

    <h3>4.1 Tab Basic - Field Identification</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Field</th>
                <th>What It Does</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>Label</code></td>
                <td>Label shown in form/list.</td>
            </tr>
            <tr>
                <td><code>Field Name</code></td>
                <td>Technical field name. If empty, it is auto-generated from the Label.</td>
            </tr>
        </tbody>
    </table>

    <h3>4.2 Tab Basic - Field Type</h3>
    <p>Available types and usage:</p>
    <ul>
        <li><code>Text</code>: short text.</li>
        <li><code>Textarea</code>: long text (rich text editor option).</li>
        <li><code>Number</code>: numeric (integer/decimal, negative values, decimals).</li>
        <li><code>Email</code>, <code>Phone</code>, <code>URL</code>: specialized inputs.</li>
        <li><code>Date</code>, <code>Date &amp; Time</code>, <code>Time</code>: temporal types.</li>
        <li><code>Checkbox</code>: boolean.</li>
        <li><code>Select</code>, <code>Radio</code>, <code>Checkboxes</code>: option-based choice.</li>
        <li><code>Relation</code>: relation to another model.</li>
        <li><code>File</code>, <code>Image</code>: upload.</li>
        <li><code>Hidden</code>: hidden field.</li>
        <li><code>Custom HTML</code>: custom HTML block.</li>
    </ul>

    <p>Main type-specific parameters:</p>
    <ul>
        <li><code>Textarea</code>: <code>Use Rich Text Editor</code></li>
        <li><code>Number</code>: <code>Allow negative values</code>, <code>Number of decimals</code></li>
        <li><code>Select</code>: <code>Multiple values</code>, <code>Allow empty value</code></li>
        <li><code>Relation</code>: <code>Multiple values</code></li>
        <li><code>File/Image</code>: <code>Max number of files</code></li>
        <li><code>Custom HTML</code>: <code>HTML Content</code></li>
    </ul>

    <h3>4.3 Tab Basic - Options Management (Fields with Options)</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Option</th>
                <th>What It Does</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>Management Type: Manual/Text</code></td>
                <td>Define static options manually or in bulk (<code>value|label</code>).</td>
            </tr>
            <tr>
                <td><code>Management Type: From Table</code></td>
                <td>Load options from an external model by selecting <code>Model</code>, <code>Value Field</code>, and <code>Label Field</code>.</td>
            </tr>
            <tr>
                <td><code>WHERE Condition</code></td>
                <td>SQL filter applied when loading options.</td>
            </tr>
            <tr>
                <td><code>Relation (Model, Label column, Relation name, Where condition)</code></td>
                <td>Configure belongsTo relation, alias, and relation filter.</td>
            </tr>
        </tbody>
    </table>

    <h3>4.4 Tab Advanced - Database Type</h3>
    <ul>
        <li><code>Exclude from DB</code>: field remains in the form but is not created/updated in the database.</li>
        <li><code>Database Type</code>: final SQL type.</li>
        <li>DB parameters per type (examples): <code>Length</code>, <code>Digits</code>, <code>Precision</code>, <code>UNSIGNED</code>.</li>
    </ul>

    <h3>4.5 Tab Advanced - Field Properties</h3>
    <ul>
        <li><code>Required field</code>: mandatory input.</li>
        <li><code>Read-only</code>: visible but not editable.</li>
        <li><code>Default Value</code>: initial value.</li>
        <li><code>Custom Alignment</code>: field layout in the form.</li>
    </ul>

    <h3>4.6 Tab Advanced - Validation</h3>
    <ul>
        <li><code>Minimum value</code>, <code>Maximum value</code>, <code>Step</code>: visible only for numeric fields.</li>
        <li><code>Validation Expression</code>: custom rule.</li>
        <li><code>Error Message</code>: message shown when validation fails.</li>
    </ul>

    <h3>4.7 Tab Advanced - Behavior and List Options</h3>
    <ul>
        <li><code>Help Text</code>: helper text under the field.</li>
        <li><code>Show If (Conditional)</code>: show/hide by condition.</li>
        <li><code>Calculated Value</code>: computed value (supported types).</li>
        <li><code>Field Visibility</code>: separate visibility for <code>List</code> and <code>Edit</code>.</li>
        <li><code>Add link</code>: makes value clickable in list (<code>%field_name%</code> placeholders).</li>
        <li><code>Render as HTML</code>: render HTML in list.</li>
        <li><code>Truncate text</code>: truncate list text to max length.</li>
        <li><code>Relation fields in list</code>: adds related model fields in table list.</li>
        <li><code>Change Values</code>: maps raw value -&gt; readable label.</li>
    </ul>

    <h2>5) Build Forms: Config Button (Main Table Config)</h2>
    <p>Page: <code>?page=projects&action=build-main-form-config&module=&lt;ModuleName&gt;</code></p>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Field</th>
                <th>What It Does</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>Allow edit</code></td>
                <td>Enables/disables record editing.</td>
            </tr>
            <tr>
                <td><code>Type of edit</code></td>
                <td>Edit open mode: <code>page</code>, <code>offcanvas</code>, <code>modal</code>.</td>
            </tr>
            <tr>
                <td><code>Soft delete</code></td>
                <td>Logical deletion (if supported by the model).</td>
            </tr>
            <tr>
                <td><code>Allow delete record</code></td>
                <td>Allows or blocks deletion.</td>
            </tr>
            <tr>
                <td><code>Show created</code>, <code>Show updated</code></td>
                <td>Shows or hides creation/update metadata.</td>
            </tr>
            <tr>
                <td><code>Manage default ordering</code></td>
                <td>Enables default sorting with <code>Field name</code> and <code>Direction</code>.</td>
            </tr>
        </tbody>
    </table>

    <h2>6) Filters and Search</h2>
    <p>Page: <code>?page=projects&action=edit-filters-search&module=&lt;ModuleName&gt;</code></p>
    <ul>
        <li><strong>Search Fields</strong>: add/remove/reorder searchable fields.</li>
        <li><strong>URL Params</strong>: whitelist query-string parameters with type-safe sanitization.</li>
    </ul>

    <h2>7) Recommended Production Flow</h2>
    <ol>
        <li>Create project and main table.</li>
        <li>Configure title/menu in Module Configuration.</li>
        <li>Create main fields in Build Forms - Edit.</li>
        <li>Set table behavior in Build Forms - Config.</li>
        <li>Configure filters/search.</li>
        <li>Test create/edit/list in the generated module.</li>
    </ol>

    <div class="alert alert-warning mb-0">
        If you change builder behavior, reload the page after updating JS/CSS assets (cache-buster).
    </div>
</div>
