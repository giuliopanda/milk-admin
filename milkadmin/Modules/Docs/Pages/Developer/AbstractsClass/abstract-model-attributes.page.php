<?php
namespace Modules\Docs\Pages;
/**
 * @title Model Attributes
 * @guide developer
 * @order 54
 * @tags model, attributes, GetFormattedValue, BeforeSave, SetValue, ValidateField, formatting, custom handlers
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Model Attributes</h1>
    <p class="text-muted">Revision: 2025/10/15</p>
    <p class="lead">Model attributes allow you to define custom handlers for formatting, transforming, and validating field values. Using PHP 8 attributes, you can attach custom methods to specific fields to control how data is displayed, stored, and validated.</p>

    <div class="alert alert-info">
        <strong>💡 Available Attributes:</strong>
        <ul class="mb-0">
            <li><code>#[GetFormattedValue('field_name')]</code> - Custom formatted output for display</li>
            <li><code>#[BeforeSave('field_name')]</code> - Custom SQL value for database storage</li>
            <li><code>#[SetValue('field_name')]</code> - Custom transformation when setting values</li>
            <li><code>#[Validate('field_name')]</code> - Custom field validation logic</li>
        </ul>
    </div>

    <h2 class="mt-4">How Attributes Work</h2>

    <p>When you create a Model instance, the <code>AbstractModel</code> automatically scans all public methods in your Model class looking for these attributes. When found, it registers them as handlers for specific fields and operations.</p>

    <p>These handlers are called automatically at the right time:</p>
    <ul>
        <li><strong>GetFormattedValue:</strong> Called when accessing a field in <code>formatted</code> mode (for display)</li>
        <li><strong>BeforeSave:</strong> Called when preparing data for database storage</li>
        <li><strong>SetValue:</strong> Called when assigning a value to a field (via <code>fill()</code> or <code>$model->field = value</code>)</li>
        <li><strong>ValidateField:</strong> Called during <code>validate()</code> operation</li>
    </ul>

    <h2 class="mt-4">#[GetFormattedValue] - Custom Display Formatting</h2>

    <p>Use this attribute to define how a field should be displayed to users. The formatted value is used when you access fields after calling <code>setFormatted()</code> or when using <code>getFormattedData()</code>.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[GetFormattedValue('field_name')]
public function methodName($current_record_obj): mixed
{
    // $current_record_obj is an object containing all fields of the current record
    // Return the formatted value
}</code></pre>

    <h3>Example: Uppercase Name</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules\Doctors;
use App\Abstracts\AbstractModel;
use App\Attributes\GetFormattedValue;

class DoctorsModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('#__doctors')
            ->id()
            ->string('name', 100)->required()
            ->text('biography');
    }

    #[GetFormattedValue('name')]
    public function getFormattedName($current_record_obj) {
        if (isset($current_record_obj->name)) {
            return strtoupper($current_record_obj->name);
        }
        return '';
    }
}

// Usage
$doctor = $model->getById(1);

// Raw value (default)
echo $doctor->name;  // "john smith"

// Formatted value
$doctor->setFormatted();
echo $doctor->name;  // "JOHN SMITH"</code></pre>

    <h3>Example: Formatted Date with Additional Text</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[GetFormattedValue('appointment_date')]
public function getFormattedAppointmentDate($current_record_obj) {
    if (isset($current_record_obj->appointment_date) &&
        $current_record_obj->appointment_date instanceof \DateTime) {
        return 'Scheduled for: ' . $current_record_obj->appointment_date->format('d/m/Y H:i');
    }
    return 'Not scheduled';
}

// Output: "Scheduled for: 15/10/2025 14:30"</code></pre>

    <h3>Example: Combining Multiple Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[GetFormattedValue('full_name')]
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

    <h2 class="mt-4">#[BeforeSave] - Custom SQL Value</h2>

    <p>Use this attribute to control how a field value is prepared before saving to the database. This is useful for data transformation or encoding before storage.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[BeforeSave('field_name')]
public function methodName($current_record_obj): mixed
{
    // $current_record_obj is an object containing all fields
    // Return the value ready for SQL storage
}</code></pre>

    <h3>Example: Encrypt Before Saving</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[BeforeSave('credit_card')]
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

    <h3>Example: JSON Encode with Special Format</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[BeforeSave('metadata')]
public function getSqlMetadata($current_record_obj) {
    if (isset($current_record_obj->metadata) && is_array($current_record_obj->metadata)) {
        // Custom JSON encoding with pretty print
        return json_encode($current_record_obj->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    return '{}';
}</code></pre>

    <h2 class="mt-4">#[SetValue] - Custom Value Transformation</h2>

    <p>Use this attribute to transform or sanitize values when they are assigned to a field. This is called automatically when you use <code>fill()</code> or direct assignment.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('field_name')]
public function methodName($value, $current_record_obj): mixed
{
    // $value is the incoming value being set
    // $current_record_obj is an array with all current field values
    // Return the transformed value
}</code></pre>

    <h3>Example: Sanitize and Format Input</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('name')]
public function setNameValue($value, $current_record_obj) {
    // Trim whitespace and capitalize first letter
    return ucfirst(trim($value));
}

// Usage
$doctor = $model->getEmpty();
$doctor->fill(['name' => '  john smith  ']);
echo $doctor->name;  // "John smith" (trimmed and capitalized)</code></pre>

    <h3>Example: Clean Phone Number</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('phone')]
public function setPhoneValue($value, $current_record_obj) {
    // Remove all non-numeric characters
    return preg_replace('/[^0-9+]/', '', $value);
}

// Input: "(555) 123-4567"
// Stored: "5551234567"</code></pre>

    <h3>Example: Convert Relative Date Strings</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[SetValue('reminder_date')]
public function setReminderDateValue($value, $current_record_obj) {
    // Allow friendly strings like "tomorrow", "next week"
    if (is_string($value)) {
        try {
            return new \DateTime($value);
        } catch (\Exception $e) {
            return null;
        }
    }
    return $value;
}

// Usage
$appointment->fill(['reminder_date' => 'tomorrow']);
// Automatically converted to DateTime object</code></pre>

    <h2 class="mt-4">#[Validate] - Custom Field Validation</h2>

    <p>Use this attribute to define custom validation logic for a specific field. This is called during the <code>validate()</code> operation.</p>

    <h3>Signature</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('field_name')]
public function methodName($value, $current_record_obj): bool|string
{
    // $value is the field value to validate
    // $current_record_obj is an array with all current field values
    // Return true if valid, or error message string if invalid
}</code></pre>

    <h3>Example: Email Domain Validation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('email')]
public function validateEmail($value, $current_record_obj) {
    if (empty($value)) {
        return "Email is required";
    }

    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format";
    }

    // Only allow company domains
    $allowed_domains = ['company.com', 'company.org'];
    $domain = substr(strrchr($value, "@"), 1);

    if (!in_array($domain, $allowed_domains)) {
        return "Email must be from company domain";
    }

    return true;
}</code></pre>

    <h3>Example: Cross-Field Validation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('end_date')]
public function validateEndDate($value, $current_record_obj) {
    if (empty($value)) {
        return true; // Optional field
    }

    if (!isset($current_record_array['start_date'])) {
        return "Start date must be set before end date";
    }

    if ($value < $current_record_array['start_date']) {
        return "End date must be after start date";
    }

    return true;
}</code></pre>

    <h3>Example: Conditional Required Field</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[Validate('billing_address')]
public function validateBillingAddress($value, $current_record_obj) {
    // Billing address required only if payment method is "invoice"
    if (isset($current_record_array['payment_method']) &&
        $current_record_array['payment_method'] === 'invoice') {
        if (empty($value)) {
            return "Billing address is required for invoice payment";
        }
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
    #[GetFormattedValue('doctor.name')]
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

    <h2 class="mt-4">Advanced: Programmatic Handler Registration</h2>

    <p>Besides using attributes, you can also register handlers programmatically using the <code>registerMethodHandler()</code> method:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$model = new ProductsModel();

// Register a custom formatter using a closure
$model->registerMethodHandler('price', 'get_formatted', function($record_array) {
    return '€' . number_format($record_array->price, 2, ',', '.');
});

$product = $model->getById(1);
$product->setFormatted();
echo $product->price;  // "€29,99"

// Remove a handler
$model->removeMethodHandler('price', 'get_formatted');

// Check if handler exists
if ($model->hasMethodHandler('price', 'get_formatted')) {
    echo "Price formatter is registered";
}

// Get a specific handler
$handler = $model->getMethodHandler('name', 'get_formatted');
if ($handler !== null) {
    $result = $handler($record);
}
</code></pre>

</div>
