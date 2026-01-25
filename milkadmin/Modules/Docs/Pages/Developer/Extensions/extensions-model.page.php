<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
* @title Model Extensions
* @order 10
* @tags extensions, model-extensions, AbstractModelExtension, attributes, data-formatting, validation, lifecycle-hooks, ToDatabaseValue, ToDisplayValue, SetValue, Validate, field-processing, audit-trail, author-tracking
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

   <h1>Model Extensions</h1>

   <p>Model Extensions allow you to add fields, validation, data processing, and lifecycle hooks to your models without modifying the model class itself. They extend the <code>AbstractModelExtension</code> class.</p>

   <h2>Creating a Model Extension</h2>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\MyExtension;

use App\Abstracts\{AbstractModelExtension, RuleBuilder};
use App\Attributes\{ToDatabaseValue, ToDisplayValue};

class Model extends AbstractModelExtension
{
    // Configuration parameters
    protected $my_option = true;

    // Add fields during configuration
    public function configure(RuleBuilder $rule_builder): void
    {
        $rule_builder
            ->int('my_field')
            ->default(0);
    }

    // Process data before saving
    #[ToDatabaseValue('my_field')]
    public function processMyField($current_record)
    {
        return $current_record->my_field * 2;
    }

    // Format data for display
    #[ToDisplayValue('my_field')]
    public function formatMyField($current_record)
    {
        return "Value: " . $current_record->my_field;
    }
}</code></pre>

   <h2 class="mt-4">Extension Parameters</h2>

   <p>Extensions can have configurable parameters defined as protected properties:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class Model extends AbstractModelExtension
{
    protected $show_username = true;
    protected $show_email = false;
    protected $max_records = 100;
}</code></pre>

   <p>Pass parameters when adding the extension:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule_builder->addExtension('MyExtension', [
    'show_username' => false,
    'max_records' => 50
]);</code></pre>

   <h2 class="mt-4">Configuration Hook</h2>

   <p>The <code>configure()</code> method is called during model initialization. Use it to add fields:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure(RuleBuilder $rule_builder): void
{
    $rule_builder
        ->int('created_by')
        ->nullable(true)
        ->default(0)
        ->label('Created By')

        ->timestamp('created_at')
        ->default('CURRENT_TIMESTAMP')
        ->label('Created At');
}</code></pre>

   <h2 class="mt-4">Attribute-Based Methods</h2>

   <p>Model extensions support the same attributes as models for automatic method registration:</p>

   <h3 class="mt-4">#[ToDatabaseValue('field_name')]</h3>
   <p>Process field value before saving to database:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDatabaseValue('created_by')]
public function setCreatedBy($current_record)
{
    // Auto-fill with current user ID on insert
    if (empty($current_record->created_by)) {
        $user = Get::make('Auth')->getUser();
        return $user->id ?? 0;
    }
    return $current_record->created_by;
}</code></pre>

   <h3 class="mt-4">#[ToDisplayValue('field_name')]</h3>
   <p>Format field value for display:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDisplayValue('created_by')]
public function formatCreatedBy($current_record)
{
    $user = Get::make('Auth')->getUser($current_record->created_by);
    return $user ? $user->username : '-';
}</code></pre>

   <h3 class="mt-4">#[SetValue('field_name')]</h3>
   <p>Customize how field values are set:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('tags')]
public function setTags($current_record)
{
    // Convert array to comma-separated string
    if (is_array($current_record->tags)) {
        return implode(',', $current_record->tags);
    }
    return $current_record->tags;
}</code></pre>

   <h3 class="mt-4">#[Validate('field_name')]</h3>
   <p>Add custom validation:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('title')]
public function validateTitle($value, $field_name, $current_record)
{
    if (strlen($value) < 3) {
        return 'Title must be at least 3 characters';
    }
    return true;
}</code></pre>

   <h2 class="mt-4">Lifecycle Hooks</h2>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Hook</th>
            <th>Parameters</th>
            <th>Description</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>configure()</code></td>
            <td><code>RuleBuilder $rule_builder</code></td>
            <td>Called during model configuration to add fields and rules</td>
         </tr>
         <tr>
            <td><code>afterSave()</code></td>
            <td><code>array $records, array $results</code></td>
            <td>Called after records are saved (insert/update)</td>
         </tr>
         <tr>
            <td><code>beforeDelete()</code></td>
            <td><code>array $ids</code></td>
            <td>Called before records are deleted</td>
         </tr>
         <tr>
            <td><code>afterDelete()</code></td>
            <td><code>array $ids</code></td>
            <td>Called after records are deleted</td>
         </tr>
      </tbody>
   </table>

   <h3 class="mt-4">afterSave() Example</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function afterSave($records_array, $save_results)
{
    foreach ($save_results as $result) {
        $action = $result['action'];  // 'insert' or 'edit'
        $id = $result['id'];

        // Log the save operation
        error_log("Record {$id} was {$action}ed");
    }
}</code></pre>

   <h3 class="mt-4">afterDelete() Example</h3>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function afterDelete($ids)
{
    foreach ($ids as $id) {
        // Clean up related data
        error_log("Record {$id} was deleted");
    }
}</code></pre>

   <h2 class="mt-4">Accessing the Parent Model</h2>

   <p>Extensions have access to the parent model via <code>$this->model->get()</code>:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function myMethod()
{
    $model = $this->model->get();

    $table = $model->getTable();
    $primary_key = $model->getPrimaryKey();
    $rules = $model->getRules();

    // Use model methods
    $record = $model->getById(123);
}</code></pre>

   <h2 class="mt-4">Complete Example: Audit Trail Extension</h2>

   <p>This extension creates a complete audit trail by saving record snapshots:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\Audit;

class Model extends AbstractModelExtension
{
    protected static $maxAuditRecords = 0;  // 0 = unlimited

    public function configure(RuleBuilder $rule_builder): void
    {
        // Audit data is stored in a separate table
        // No fields needed in main model
    }

    public function afterSave($records_array, $save_results)
    {
        $parent_model = $this->model->get();
        $table_audit = $parent_model->getTable() . "_audit";
        $auditModel = new AuditModel();

        foreach ($save_results as $result) {
            $action = $result['action'];
            $recordId = $result['id'];

            // Find the saved record data
            $recordData = null;
            foreach ($records_array as $record) {
                if ($record[$parent_model->getPrimaryKey()] == $recordId) {
                    $recordData = $record;
                    break;
                }
            }

            // Save to audit table
            $audit_record = $recordData;
            $audit_record['audit_action'] = $action;
            $audit_record['audit_record_id'] = $recordId;
            $audit_record['audit_timestamp'] = time();
            $audit_record['audit_user_id'] = Get::make('Auth')->getUser()->id ?? 0;

            $auditModel->store($audit_record);
        }
    }

    public function afterDelete($ids)
    {
        $auditModel = new AuditModel();

        foreach ($ids as $deleted_id) {
            $audit_record = [
                'audit_action' => 'delete',
                'audit_record_id' => $deleted_id,
                'audit_timestamp' => time(),
                'audit_user_id' => Get::make('Auth')->getUser()->id ?? 0
            ];

            $auditModel->store($audit_record);
        }
    }
}</code></pre>

   <h2 class="mt-4">Best Practices</h2>

   <ul>
      <li><strong>Keep extensions focused</strong> - Each extension should handle one specific behavior</li>
      <li><strong>Use parameters</strong> - Make extensions configurable for different use cases</li>
      <li><strong>Document parameters</strong> - Add docblocks to protected properties</li>
      <li><strong>Handle edge cases</strong> - Check for null values and missing data</li>
      <li><strong>Use WeakReference</strong> - The parent model is stored as a WeakReference to prevent memory leaks</li>
      <li><strong>Cache data</strong> - Use static caches for frequently accessed data (like user lookups)</li>
   </ul>

   <h2 class="mt-4">Testing Extensions</h2>

   <p>Test your extension by adding it to a model:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class TestModel extends AbstractModel
{
    public function configure(RuleBuilder $rule_builder): void
    {
        $rule_builder
            ->table('#__test')
            ->id()
            ->string('title', 255)

            // Add your extension
            ->addExtension('MyExtension', [
                'my_option' => true
            ]);
    }
}</code></pre>

   <h2 class="mt-4">See Also</h2>

   <ul>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-introduction'); ?>">Extensions Introduction</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-module'); ?>">Module Extensions</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-attributes'); ?>">Model Attributes</a></li>
   </ul>

</div>
