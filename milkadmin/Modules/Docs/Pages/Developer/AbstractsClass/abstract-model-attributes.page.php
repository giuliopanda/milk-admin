<?php
namespace Modules\Docs\Pages;
/**
 * @title Model Attributes
 * @guide developer
 * @order 54
 * @tags model, attributes, ToDisplayValue, ToDatabaseValue, SetValue, Validate, formatting, custom handlers
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Model Attributes</h1>
    <p class="text-muted">Revision: 2025/12/01</p>
    <p class="lead">Model attributes allow you to define custom handlers for formatting, transforming, and validating field values. Using PHP 8 attributes, you can attach custom methods to specific fields to control how data is displayed, stored, and validated.</p>

    <h2 class="mt-4">Quick Reference</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Attribute</th>
                    <th>Method Parameters</th>
                    <th>Method Return Value</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>#[ToDisplayValue(field_name)]</code></td>
                    <td>object $current_record</td>
                    <td>Return the formatted value</td>
                    <td>Called when accessing a field in <code>formatted</code> mode (for display)</td>
                </tr>
                <tr>
                    <td><code>#[ToDatabaseValue(field_name)]</code></td>
                    <td>object $current_record</td>
                    <td>Return the transformed value to store in the database</td>
                    <td>Called when preparing data for database storage</td>
                </tr>
                <tr>
                    <td><code>#[SetValue(field_name)]</code></td>
                    <td>array $current_record, mixed $value</td>
                     <td>Return the transformed value</td>
                    <td>Called when assigning a value to a field (via <code>fill()</code> or <code>$model->field = value</code>)</td>
                </tr>
                <tr>
                    <td><code>#[Validate(field_name)]</code></td>
                    <td>object $current_record</td>
                    <td>Return true if valid, or error message string if invalid<</td>
                    <td>Called during <code>validate()</code> operation</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">How Attributes Work</h2>

    <p>When you create a Model instance, the <code>AbstractModel</code> automatically scans all public methods in your Model class looking for these attributes. When found, it registers them as handlers for specific fields and operations.</p>

    <h2 class="mt-4">#[ToDisplayValue] - Custom Display Formatting</h2>

    <p>Use this attribute to define how a field should be displayed to users. The formatted value is used when you access fields after calling <code>setFormatted()</code> or when using <code>getFormattedData()</code>.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDisplayValue('field_name')]
public function methodName($current_record_obj): mixed
{
    // $current_record_obj is an object containing all fields of the current record
    // Return the formatted value
}</code></pre>

    <h3>Example: Combining Multiple Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDisplayValue('full_name')]
public function getFormattedFullName($current_record_obj) {
    $parts = [];
    if (!empty($current_record_obj->first_name)) {
        $parts[] = $current_record_obj->first_name;
    }
    if (!empty($current_record_obj->last_name)) {
        $parts[] = $current_record_obj->last_name;
    }
    return implode(' ', $parts);
}

// Note: 'full_name' doesn't need to be a real database field
// It can be a virtual field computed from other fields</code></pre>



    <h2 class="mt-4">#[ToDatabaseValue] - Custom SQL Value</h2>

    <p>Use this attribute to control how a field value is prepared before saving to the database. This is useful for data transformation or encoding before storage.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDatabaseValue('field_name')]
public function methodName($current_record_obj): mixed
{
    // $current_record_obj is an object containing all fields
    // Return the value ready for SQL storage
}</code></pre>

    <h3>Example: Encrypt Before Saving</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[ToDatabaseValue('credit_card')]
public function getSqlCreditCard($current_record_obj) {
    if (isset($current_record_obj->credit_card) && !empty($current_record_obj->credit_card)) {
        // Encrypt the credit card number before saving
        return openssl_encrypt(
            $current_record_obj->credit_card,
            'AES-256-CBC',
            ENCRYPTION_KEY,
            0,
            ENCRYPTION_IV
        );
    }
    return null;
}</code></pre>

    <h2 class="mt-4">#[SetValue] - Custom Value Transformation</h2>

    <p>Use this attribute to transform or sanitize values when they are assigned to a field. This is called automatically when you use <code>fill()</code> or direct assignment.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('field_name')]
public function methodName($current_record_array, $value): mixed
{
    // $current_record_array is an array with all current field values
    // $value is the incoming value being set
    // Return the transformed value
}</code></pre>

    <h3>Example: Clean Phone Number</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('phone')]
public function setPhoneValue($current_record_array, $value) {
    // Remove all non-numeric characters
    return preg_replace('/[^0-9+]/', '', $value);
}

// Input: "(555) 123-4567"
// Stored: "5551234567"</code></pre>

    <h2 class="mt-4">#[Validate] - Custom Field Validation</h2>

    <p>Use this attribute to define custom validation logic for a specific field. This is called during the <code>validate()</code> operation.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('field_name')]
public function methodName($current_record): bool|string
{
    // $current_record is an object with all current record 
    // Return true if valid, or error message string if invalid
}</code></pre>

    <h3>Example: Cross-Field Validation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('end_date')]
public function validateEndDate($current_record) {
    $value = $current_record->end_date;
    if (empty($value)) {
        return true; // Optional field
    }

    // Access other fields through the model
    $start_date = $current_record->start_date;

    if (empty($start_date)) {
        return "Start date must be set before end date";
    }

    if ($value < $start_date) {
        return "End date must be after start date";
    }

    return true;
}</code></pre>

    <h2 class="mt-4">Working with Relationships</h2>

    <p>You can also use attributes to format or handle relationship fields. Use the notation <code>"relationship_alias.field_name"</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class AppointmentsModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('#__appointments')
            ->id()
            ->int('doctor_id')
            ->belongsTo('doctor', DoctorsModel::class, 'doctor_id');
    }

    // Format the related doctor's name
    #[ToDisplayValue('doctor.name')]
    public function getFormattedDoctorName($current_record_obj) {
        if (isset($current_record_obj->doctor->name)) {
            return 'Dr. ' . $current_record_obj->doctor->name;
        }
        return '';
    }
}

// Usage
$appointment = $model->include('doctor')->getById(1);
$appointment->setFormatted();
echo $appointment->doctor->name;  // "Dr. John Smith"</code></pre>

</div>