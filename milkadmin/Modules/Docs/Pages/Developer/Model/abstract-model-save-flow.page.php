<?php
namespace Modules\Docs\Pages;
use App\Route;
/** 
* @title Model Save Flow 
* @guide developer 
* @order 53 
* @tags model, save, fill, getEmpty, getByIdAndUpdate, dirty, stale, action, insert, edit, prepareData, calc_expr, created_at, updated_at, save_value 
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
<h1>Model Save Flow</h1>
<p class="text-muted">Revision: 2026/03/12</p>
<p class="lead">This page describes the actual behavior of the model save system after the phase 1 refactoring. The key point is that <code>AbstractModel</code> continues to work as a multi-record container, but change tracking is now explicit and per-record.</p>

<div class="alert alert-info">
<strong>Related documentation:</strong>
For the model overview, see
<a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model'); ?>">Abstract Model</a>,
for schema/validation rules See
<a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-rulebuilder'); ?>">RuleBuilder</a>
and
<a href="<?php echo Route::url('?page=docs&action=Developer/Model/model-rules'); ?>">Model Validation Rules</a>.
</div>

<h2 class="mt-4">1. Correct Mental Model</h2>
<p>The framework does not treat the model as a single-record Active Record. A single <code>AbstractModel</code> instance can contain multiple records, maintain a current cursor, and save the entire batch with a single call to <code>save()</code>.</p>
<ul>
<li><code>records_objects</code> remains the source of truth for current values.</li>
<li><code>record_states[$index]</code> contains only tracking metadata.</li>
<li><code>___action</code> is no longer the primary source of truth, but remains the compatibility layer read by <code>save()</code>, hooks, and existing modules.</li>
<li><code>hasMany</code> relations continue to behave like living result sets: they are not independent copies of the record.</li>
</ul>

<h2 class="mt-4">2. Data structures involved</h2>
<table class="table table-bordered table-sm">
<thead class="table-light">
<tr>
<th>Structure</th>
<th>Role</th>
</tr>
</thead>
<tbody>
<tr>
<td><code>records_objects[$index]</code></td>
<td>Current record values, already loaded relationships, and metadata as <code>___action</code>.</td>
</tr>
<tr>
<td><code>new_record_indexes[$index]</code></td>
<td>Marks records created on the application side and not yet persisted. It is used to distinguish real inserts from updates.</td>
</tr>
<tr>
<td><code>record_states[$index]['originalData']</code></td>
<td>Snapshot of persistable values ​​at hydration or after a successful save.</td>
</tr>
<tr>
<td><code>record_states[$index]['dirtyFields']</code></td>
<td>Fields intentionally changed by application code or user input.</td>
</tr>
<tr>
<td><code>record_states[$index]['staleFields']</code></td>
<td>Fields corrected by the framework, typically via <code>calc_expr</code>.</td>
</tr>
<tr>
<td><code>record_states[$index]['isHydrating']</code></td>
<td>Temporary flag used to distinguish technical loading from application input.</td>
</tr>
</tbody>
</table>

<h2 class="mt-4">3. How a record's state is created</h2>

<h3 class="mt-3">Hydration from the database</h3>
<p><code>setResults()</code> and <code>setRow()</code> initialize records loaded from the database in this order:</p>
<ol>
<li>filter the data with the model rules;</li>
<li>write the values ​​to <code>records_objects</code>;</li>
<li>set <code>___action = null</code>;</li>
<li>create <code>originalData</code> with the snapshot of the persistable fields;</li>
<li>temporarily activate <code>isHydrating</code>;</li>
<li>recalculate any <code>calc_expr</code> fields;</li>
<li>turn off <code>isHydrating</code>.</li>
</ol>
<p>Practical effect: the record loaded from the DB doesn't start dirty. However, if a <code>calc_expr</code> recalculates a value different from the one saved, the field is marked as <code>stale</code> and the record becomes saveable as <code>edit</code>.</p>

<h3 class="mt-3">New record via <code>getEmpty()</code></h3>
<p><code>getEmpty()</code> doesn't immediately create a dirty record. The flow is this:</p>
<ol>
<li>create a new model;</li>
<li>initialize a pristine record with <code>___action = null</code>;</li>
<li>marks the index as new in <code>new_record_indexes</code>;</li>
<li>applies the <code>default()</code> rules with suspended tracking;</li>
<li>recalculates the <code>calc_expr</code> without turning the record dirty.</li>
</ol>
<p>This is the desired behavior for creation forms: the defaults are visible immediately, but the record is not considered modified just because it has been initialized.</p>

<h3 class="mt-3">Application input via <code>fill()</code> or direct assignment</h3>
<p>When the data arrives from the application, writing goes through <code>setValueWithConversion()</code> and then <code>setRawValue()</code>. Here's where the important things happen:</p>
<ul>
<li>The converted value is written to <code>records_objects</code>;</li>
<li>The comparison with <code>originalData</code> uses strict raw equivalence, with dedicated handling for <code>DateTimeInterface</code>;</li>
<li>If the value differs from the original, the field enters <code>dirtyFields</code>;</li>
<li>If the value returns equal to the original, the field leaves <code>dirtyFields</code>;</li>
<li>If a valid primary key is assigned to a new record, the index is removed from <code>new_record_indexes</code>;</li>
<li>Finally, <code>___action</code> is recalculated from the record's state.</li>
</ul>
<p>Important consequence: a An explicit <code>null</code> assigned by the user remains an intentional change and is persisted if the field enters the payload.</p>

<h2 class="mt-4">4. How <code>___action</code> is computed</h2>
<p><code>___action</code> is projected by <code>recomputeRecordAction()</code>. The actual rules are:</p>
<ul>
<li><code>null</code>: no dirty fields and no stale fields;</li>
<li><code>insert</code>: the record has changes and its index is still marked as new;</li>
<li><code>edit</code>: the record has changes but is not considered new.</li>
</ul>
<p>This value is synchronized in <code>records_objects[$index]['___action']</code> for compatibility with <code>beforeSave()</code>, extension hooks, and legacy code that reads the batch directly.</p>

<h2 class="mt-4">5. Dirty vs dirty</h2> 
<div class="table-responsive"> 
<table class="table table-bordered table-sm"> 
<thead class="table-light"> 
<tr> 
<th>Type</th> 
<th>Origin</th> 
<th>Effect</th> 
</tr> 
</thead> 
<tbody> 
<tr> 
<td><code>dirty</code></td> 
<td>User input or application code: <code>fill()</code>, <code>__set()</code>, <code>setValueWithConversion()</code>.</td> 
<td>The field enters the save payload.</td> 
</tr> 
<tr> 
<td><code>stale</code></td> 
<td>Technical correction of the framework, in practice mainly <code>calc_expr</code>.</td>
<td>The field enters the save payload without being treated as an intentional change.</td>
</tr>
</tbody>
</table>
</div>
<p>This separation helps avoid two classic problems: false dirty values ​​on automatically recalculated values ​​and the loss of necessary corrections on inconsistent records loaded from the database.</p>

<h2 class="mt-4">6. The role of <code>calc_expr</code></h2>
<p>Fields with <code>calc_expr</code> are recalculated both during hydration and after the relevant assignments. The computed value:

<ul>
<li>is not treated as user input;</li>
<li>if it matches the original, it does not produce any action;</li>
<li>if it differs, it updates the current record and marks the field as <code>stale</code>;</li>
<li>can therefore promote a record from <code>null</code> to <code>edit</code>.</li>
</ul>

<h2 class="mt-4">7. Special flow of <code>getByIdAndUpdate()</code></h2>
<p><code>getByIdAndUpdate($id, $merge_data)</code> remains the official mechanism for refilling forms:</p>
<ol>
<li>attempts to load the record from the DB with <code>getById()</code>;</li>
<li>if it doesn't exist, creates an empty record with <code>getEmpty($merge_data)</code>;</li>
<li>if the record exists, overlays the form data with DB values ​​with conversion;</li>
<li>removes <code>default</code> from the returned model rules when needed to avoid misleading reinitializations;</li>
<li>rebuilds the final actions with <code>rebuildActions()</code>.</li>
</ol>
<p>The result is a model ready for form rendering: untouched fields still aligned to the DB, fields modified with user temporary values, no implicit persistence.

<h2 class="mt-4">8. Payload construction with <code>prepareData()</code></h2>
<p><code>prepareData()</code> no longer serializes the entire record indiscriminately. It scrolls through the SQL rules and decides field by field what really goes into the final payload.</p> 

<h3 class="mt-3">Effective matrix</h3> 
<table class="table table-bordered table-sm"> 
<thead class="table-light"> 
<tr> 
<th>Situation</th> 
<th>INSERT</th> 
<th>UPDATE</th> 
</tr> 
</thead> 
<tbody> 
<tr> 
<td><code>dirty</code></td> field 
<td>Persisted</td> 
<td>Persisted</td> 
</tr> 
<tr> 
<td>Field <code>stale</code></td> 
<td>Persisted</td> 
<td>Persisted</td> 
</tr> 
<tr> 
<td>Field untouched with <code>default()</code></td>
<td>Persisted</td>
<td>Omitted</td>
</tr>
<tr>
<td>Untouched field without default</td>
<td>Omitted</td>
<td>Omitted</td>
</tr>
<tr>
<td>Self-managed special field</td>
<td>Persisted</td>
<td>Persisted</td>
</tr>
</tbody>
</table>
<p>This is the main fix compared to historical behavior: when inserting, an untouched field without default is no longer erroneously set to <code>null</code> just because it exists in the model definition.</p>

<h2 class="mt-4">9. Special fields always prepared</h2>
<p>Even if they aren't dirty or stale, some fields still enter <code>prepareData()</code> because they have their own semantics:</p>
<ul>
<li><code>saveValue()</code>: the value defined in the rule always wins over the value entered by the user;</li>
<li><code>created_at()</code>: the original value is preserved in the update; In insert, use the default or current timestamp.</li>
<li><code>updated_at()</code>: is updated with every active save.</li>
<li><code>created_by()</code> and <code>updated_by()</code>: are handled automatically, including by field name convention.</li>
<li>Nullable numeric/date/time fields: the empty string is converted to <code>null</code> only if the field actually enters the payload.</li>
</ul>

<h2 class="mt-4">10. What <code>save()</code> does step by step</h2>
<ol>
<li>resets errors, save results, and the last inserted ID;</li>
<li>cleans up any empty records;</li>
<li>runs <code>beforeSave</code> hooks and extensions;</li>
<li>first processes deletions in <code>deleted_primary_keys</code>;</li>
<li>for each record, recalculates <code>___action</code> from the per-record state;</li>
<li>if the record is new, not dirty, but there are persistable defaults, it can promote it to <code>insert</code>;</li>
<li>processes any cascading relationships when required;</li>
<li>extracts non-metadata data from the record;</li>
<li>builds the final payload with <code>prepareData()</code>;</li>
<li>normalizes again the action with a final safety net;</li>
<li>skips records with action <code>null</code>;</li>
<li>executes <code>INSERT</code> or <code>UPDATE</code>;</li>
<li>updates PK and save results;</li>
<li>realigns <code>originalData</code> and clears <code>dirtyFields</code>/<code>staleFields</code> for saved records;</li>
<li>executes <code>afterSave</code> hook and saves any meta.</li>
</ol>

<h2 class="mt-4">11. Post-save and state realignment</h2>
<p>After a successful save, <code>refreshRecordStateAfterSuccessfulSave()</code>:</p>
<ul>
<li>reconstructs <code>originalData</code> from the current persistable values;</li>
<li>empties <code>dirtyFields</code>;</li>
<li>empties <code>staleFields</code>;</li>
<li>resets <code>isHydrating</code>;</li>
<li>resets <code>___action</code> to <code>null</code>;</li>
<li>if it was a successful insert, removes the index from <code>new_record_indexes</code>.</li>
</ul>
<p>This also applies to the path used by <code>store()</code>, which internally builds a temporary model, executes <code>fill()</code>, <code>validate()</code>, and then <code>save()</code>.</p>

<h2 class="mt-4">12. Cases that create more confusion</h2>
<ul>
<li><strong>A visible default doesn't mean a dirty record:</strong> a record created with <code>getEmpty()</code> can show the defaults but remain with the action <code>null</code>.</li>
<li><strong><code>save()</code> saves the batch:</strong> if a model contains multiple records, the call acts on the entire tracked set.</li>
<li><strong><code>calc_expr</code> can cause an update without user input:</strong> This happens when it corrects inconsistent values ​​already present.i in the DB.</li>
<li><strong><code>saveValue()</code> is not dirty tracking:</strong> it is a final payload coercion.</li>
<li><strong>Untouched without default in insert is omitted:</strong> the framework no longer needs to implicitly transform it to <code>null</code>.</li>
</ul>

<h2 class="mt-4">13. Synthetic Example</h2>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$post = $this->model->getEmpty();

// The rule defaults are visible, but the record can still be untouched.
$post->title = 'Title';
// title enters dirtyFields, ___action becomes 'insert'

$post->slug = 'title';
// more dirty

$post->save();
// prepareData() includes dirty data, any persistable defaults
// and special fields (updated_at, created_by, etc.)
// After saving, dirty/stale data is cleared and ___action returns null</code></pre>

</div>