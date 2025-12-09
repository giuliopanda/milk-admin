<?php
namespace Modules\Docs\Pages;

/**
 * @title Conditional Field Visibility
 * @guide developer
 * @order 45
 * @tags FormBuilder, conditional-visibility, toggle-fields, showFieldWhen, showFieldsWhen, dynamic-forms, field-visibility, form-toggling
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>FormBuilder - Conditional Field Visibility</h1>

    <p>The FormBuilder class provides powerful methods to show or hide form fields based on the value of other fields. This allows you to create dynamic, user-friendly forms that only display relevant fields based on user selections.</p>

    <div class="alert alert-info">
        <h5 class="alert-heading">💡 Hiding Fields Permanently</h5>
        <p class="mb-2">If you need to hide a field completely (not conditionally), you have two options:</p>
        <ul class="mb-0">
            <li><strong>In the Controller:</strong> Use <code>modifyField()</code> to set the field type as hidden:
                <pre class="mb-2 mt-2"><code class="language-php">->modifyField('field_name', ['form-type' => 'hidden'])</code></pre>
            </li>
            <li><strong>In the Model:</strong> Use <code>hideFromEdit()</code> to hide the field from edit forms:
                <pre class="mb-0 mt-2"><code class="language-php">->string('field_name', 100)->hideFromEdit()</code></pre>
            </li>
        </ul>
    </div>

    <h2>Overview</h2>
    <p>Conditional visibility allows you to:</p>
    <ul>
        <li>Show/hide fields based on dropdown selections</li>
        <li>Display additional fields when a checkbox is checked</li>
        <li>Create multi-step forms with conditional logic</li>
        <li>Reduce form complexity by hiding irrelevant fields</li>
    </ul>

    <h2>Basic Methods</h2>

    <h3>showFieldWhen() - Single Field</h3>
    <p>Shows a single field when another field has a specific value.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Syntax
$formBuilder->showFieldWhen($field_name, $toggle_field, $toggle_value)

// Parameters:
// - $field_name: The field to show/hide
// - $toggle_field: The field to watch for changes
// - $toggle_value: The value that will make the field visible
</code></pre>

    <h3>showFieldsWhen() - Multiple Fields</h3>
    <p>Shows multiple fields when another field has a specific value. This is a convenience method to avoid repeating the same condition.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Syntax
$formBuilder->showFieldsWhen([$field1, $field2, $field3], $toggle_field, $toggle_value)

// Parameters:
// - array of field names: The fields to show/hide together
// - $toggle_field: The field to watch for changes
// - $toggle_value: The value that will make all fields visible
</code></pre>

    <h2>Simple Example</h2>

    <h3>User Status Form</h3>
    <p>This example shows different fields based on the selected status value.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\Response;

class UserStatusModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('userStatus')
             ->title('User Status Form')
             ->menu('User Status')
             ->access('registered');
    }

    #[RequestAction('home')]
    public function home() {
        $form = \Builders\FormBuilder::create($this->model, $this->page)
            // Show these fields only when status = 'active'
            ->showFieldsWhen(['activation_date', 'activated_by'], 'status', 'active')

            // Show this field only when status = 'inactive'
            ->showFieldWhen('reason_inactive', 'status', 'inactive')

            // Show these fields only when status = 'archived'
            ->showFieldsWhen(['archive_date', 'archive_notes'], 'status', 'archived')

            ->addStandardActions()
            ->render();

        Response::render($form, ['title' => $this->title, 'form' => $form]);
    }
}

class UserStatusModel extends AbstractModel {
    protected function configure($rule): void
    {
        $rule
        ->table('user_status')
        ->id()
        ->string('name', 100)

        // Status dropdown - controls visibility of other fields
        ->string('status', 50)->options([
            'pending' => 'Pending',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'archived' => 'Archived'
        ])->formType('list')

        ->string('description', 255)

        // Conditional fields - shown only when status = 'active'
        ->date('activation_date', false)
        ->string('activated_by', 100, false)

        // Conditional field - shown only when status = 'inactive'
        ->text('reason_inactive', false)

        // Conditional fields - shown only when status = 'archived'
        ->date('archive_date', false)
        ->text('archive_notes', false);
    }
}
</code></pre>

    <h2>How It Works</h2>

    <h3>Behind the Scenes</h3>
    <p>When you use <code>showFieldWhen()</code> or <code>showFieldsWhen()</code>, the FormBuilder:</p>
    <ol>
        <li>Adds <code>data-togglefield</code> and <code>data-togglevalue</code> attributes to the field wrapper</li>
        <li>Applies <code>style="display:none"</code> to hide the field initially</li>
        <li>JavaScript monitors the toggle field for changes</li>
        <li>When the toggle field value matches the toggle value, the field is shown</li>
        <li>When the value doesn't match, the field is hidden again</li>
    </ol>

    <h3>Generated HTML Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;!-- The control field (status) --&gt;
&lt;div class="mb-3"&gt;
    &lt;label for="status"&gt;Status&lt;/label&gt;
    &lt;select name="status" id="status" class="form-control"&gt;
        &lt;option value="pending"&gt;Pending&lt;/option&gt;
        &lt;option value="active"&gt;Active&lt;/option&gt;
        &lt;option value="inactive"&gt;Inactive&lt;/option&gt;
        &lt;option value="archived"&gt;Archived&lt;/option&gt;
    &lt;/select&gt;
&lt;/div&gt;

&lt;!-- Conditional field - initially hidden --&gt;
&lt;div class="mb-3"
     data-togglefield="status"
     data-togglevalue="active"
     style="display:none"&gt;
    &lt;label for="activation_date"&gt;Activation Date&lt;/label&gt;
    &lt;input type="date" name="activation_date" id="activation_date" class="form-control"&gt;
&lt;/div&gt;
</code></pre>

    <h2>Common Use Cases</h2>

    <h3>1. Dropdown-Based Conditional Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Show shipping address fields only when shipping type is 'custom'
    ->showFieldsWhen(
        ['shipping_address', 'shipping_city', 'shipping_zip'],
        'shipping_type',
        'custom'
    )

    // Show billing fields only when billing type is 'different'
    ->showFieldsWhen(
        ['billing_address', 'billing_city', 'billing_zip'],
        'billing_type',
        'different'
    )
    ->render();
</code></pre>

    <h3>2. Checkbox-Based Conditional Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Show company fields only when 'is_company' checkbox is checked
    // Note: Checkbox value is typically '1' when checked
    ->showFieldsWhen(
        ['company_name', 'vat_number', 'registration_number'],
        'is_company',
        '1'
    )
    ->render();
</code></pre>

    <h3>3. Multiple Conditions for Same Field</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Show different fields for different payment methods
$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Show card fields when payment method is 'credit_card'
    ->showFieldsWhen(
        ['card_number', 'card_expiry', 'card_cvv'],
        'payment_method',
        'credit_card'
    )

    // Show bank fields when payment method is 'bank_transfer'
    ->showFieldsWhen(
        ['bank_name', 'account_number', 'swift_code'],
        'payment_method',
        'bank_transfer'
    )

    // Show PayPal email when payment method is 'paypal'
    ->showFieldWhen('paypal_email', 'payment_method', 'paypal')
    ->render();
</code></pre>

    <h3>4. User Type Based Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Show admin-specific fields
    ->showFieldsWhen(
        ['admin_level', 'permissions', 'department'],
        'user_type',
        'admin'
    )

    // Show customer-specific fields
    ->showFieldsWhen(
        ['customer_type', 'discount_level', 'credit_limit'],
        'user_type',
        'customer'
    )

    // Show vendor-specific fields
    ->showFieldsWhen(
        ['vendor_category', 'commission_rate', 'contract_date'],
        'user_type',
        'vendor'
    )
    ->render();
</code></pre>

    <h2>Complete Working Example</h2>

    <h3>E-commerce Product Form</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\Response;

class ProductModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('products')
             ->title('Product Management')
             ->menu('Products')
             ->access('registered');
    }

    #[RequestAction('edit')]
    public function edit() {
        $id = _absint($_REQUEST['id'] ?? 0);
        $product = $this->model->getByIdForEdit($id);

        $form = \Builders\FormBuilder::create($this->model, $this->page)
            ->addFieldsFromObject($product, 'edit')

            // Show digital product fields when type is 'digital'
            ->showFieldsWhen(
                ['download_url', 'file_size', 'download_limit'],
                'product_type',
                'digital'
            )

            // Show physical product fields when type is 'physical'
            ->showFieldsWhen(
                ['weight', 'dimensions', 'shipping_class'],
                'product_type',
                'physical'
            )

            // Show subscription fields when type is 'subscription'
            ->showFieldsWhen(
                ['billing_period', 'trial_days', 'renewal_price'],
                'product_type',
                'subscription'
            )

            // Show discount fields only when 'has_discount' is checked
            ->showFieldsWhen(
                ['discount_percentage', 'discount_start_date', 'discount_end_date'],
                'has_discount',
                '1'
            )

            ->addStandardActions('?page=' . $this->page, true)
            ->render();

        Response::render(['form' => $form], [
            'title' => $id > 0 ? 'Edit Product' : 'Add Product',
            'form' => $form
        ]);
    }
}

class ProductModel extends AbstractModel {
    protected function configure($rule): void
    {
        $rule
        ->table('products')
        ->id()
        ->string('name', 200)
        ->text('description', false)
        ->float('price')

        // Product type selector
        ->string('product_type', 50)->options([
            'physical' => 'Physical Product',
            'digital' => 'Digital Product',
            'subscription' => 'Subscription'
        ])->formType('list')

        // Physical product fields
        ->float('weight', false)
        ->string('dimensions', 100, false)
        ->string('shipping_class', 50, false)

        // Digital product fields
        ->string('download_url', 255, false)
        ->string('file_size', 50, false)
        ->int('download_limit', false)

        // Subscription fields
        ->string('billing_period', 50, false)
        ->int('trial_days', false)
        ->float('renewal_price', false)

        // Discount checkbox and fields
        ->checkbox('has_discount', false)
        ->float('discount_percentage', false)
        ->date('discount_start_date', false)
        ->date('discount_end_date', false);
    }
}
</code></pre>

    <h2>Best Practices</h2>

    <h3>1. Field Order Matters</h3>
    <p>Place the control field (the field being watched) before the conditional fields in your form for better UX.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Good - control field comes first
->fieldOrder(['id', 'status', 'activation_date', 'activated_by'])

// Not recommended - conditional fields appear before control
->fieldOrder(['id', 'activation_date', 'activated_by', 'status'])
</code></pre>

    <h3>2. Make Conditional Fields Optional</h3>
    <p>Fields that are conditionally shown should typically be optional in the database schema.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Good - conditional fields are optional (false parameter)
->date('activation_date', false)  // Second parameter = false means nullable
->string('activated_by', 100, false)

// Avoid - conditional required fields can cause validation issues
->date('activation_date')  // Required field that might be hidden
</code></pre>

    <h3>3. Group Related Conditional Fields</h3>
    <p>Use <code>showFieldsWhen()</code> for fields that should appear together.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Good - grouped related fields
->showFieldsWhen(['activation_date', 'activated_by', 'activation_notes'], 'status', 'active')

// Not recommended - separate calls for related fields
->showFieldWhen('activation_date', 'status', 'active')
->showFieldWhen('activated_by', 'status', 'active')
->showFieldWhen('activation_notes', 'status', 'active')
</code></pre>

    <h3>4. Clear Field Labels</h3>
    <p>Make field labels descriptive so users understand why fields appear/disappear.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Good - descriptive labels
->string('activation_date')->formLabel('Date when user was activated')
->string('reason_inactive')->formLabel('Reason for deactivation')

// Less clear
->string('activation_date')->formLabel('Date')
->string('reason_inactive')->formLabel('Reason')
</code></pre>

    <h2>Removing Conditional Visibility</h2>

    <p>If you need to remove conditional visibility from a field:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    ->showFieldWhen('activation_date', 'status', 'active')

    // Later, remove the condition
    ->removeFieldCondition('activation_date')

    ->render();
</code></pre>

    <h2>Technical Details</h2>

    <h3>JavaScript Implementation</h3>
    <p>The conditional visibility is handled by the <code>toggleEls</code> JavaScript class in <code>theme.js</code>. It:</p>
    <ul>
        <li>Automatically detects fields with <code>data-togglefield</code> attributes</li>
        <li>Monitors the control field for changes (input, change events)</li>
        <li>Shows/hides fields with smooth transitions</li>
        <li>Manages required field validation when fields are hidden</li>
    </ul>

    <h3>Browser Compatibility</h3>
    <p>The conditional visibility feature works in all modern browsers that support:</p>
    <ul>
        <li>ES6 JavaScript classes</li>
        <li>CSS transitions</li>
        <li>DOM dataset API</li>
    </ul>

    <h2>Troubleshooting</h2>

    <h3>Fields Not Showing/Hiding</h3>
    <ol>
        <li>Verify the control field name matches exactly (case-sensitive)</li>
        <li>Check that the toggle value matches the actual field value</li>
        <li>Ensure JavaScript is loaded (check browser console)</li>
        <li>Verify field is added to form before applying conditional visibility</li>
    </ol>

    <h3>Validation Issues</h3>
    <p>If required fields are causing validation errors when hidden:</p>
    <ul>
        <li>Make conditional fields optional in the model (use <code>false</code> parameter)</li>
        <li>The JavaScript automatically disables required validation for hidden fields</li>
        <li>Ensure you're using the latest version of <code>theme.js</code></li>
    </ul>

    <h2>Next Steps</h2>
    <p>Now that you understand conditional field visibility, explore:</p>
    <ul>
        <li><strong>Form Validation</strong>: Custom validation rules and error handling</li>
        <li><strong>Complex Field Types</strong>: Working with milkSelect, file uploads, and editors</li>
        <li><strong>Form Actions</strong>: Custom callbacks and action handling</li>
        <li><strong>AJAX Forms</strong>: Dynamic form submission without page reload</li>
    </ul>
</div>
