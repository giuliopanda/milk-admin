<?php
namespace Modules\Docs\Pages;

/**
 * @title Conditional Field Visibility
 * @guide developer
 * @order 45
 * @tags FormBuilder, conditional-visibility, showIf, data-milk-show, dynamic-forms, field-visibility, form-toggling
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

    <h3>showIf() - Single Field or Container</h3>
    <p>Shows a field or container when a milk expression evaluates to true.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Syntax
$formBuilder->showIf($field_or_container_id, $expression)

// Parameters:
// - $field_or_container_id: The field name or container id to show/hide
// - $expression: Milk expression that evaluates to true/false
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
            ->showIf('activation_date', '[status] == "active"')
            ->showIf('activated_by', '[status] == "active"')
            ->showIf('reason_inactive', '[status] == "inactive"')
            ->showIf('archive_date', '[status] == "archived"')
            ->showIf('archive_notes', '[status] == "archived"')
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
    <p>When you use <code>showIf()</code>, the FormBuilder:</p>
    <ol>
        <li>Adds <code>data-milk-show</code> attribute to the field wrapper or container</li>
        <li>Applies <code>style="display:none"</code> to hide the field initially</li>
        <li>JavaScript evaluates the expression on each recalculation</li>
        <li>When the expression is true, the field is shown; otherwise it is hidden</li>
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
     data-milk-show="[status] == &quot;active&quot;"
     style="display:none"&gt;
    &lt;label for="activation_date"&gt;Activation Date&lt;/label&gt;
    &lt;input type="date" name="activation_date" id="activation_date" class="form-control"&gt;
&lt;/div&gt;
</code></pre>

    <h2>Common Use Cases</h2>

    <h3>1. Dropdown-Based Conditional Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Show shipping address fields only when shipping type is 'custom'
    ->showIf('shipping_address', '[shipping_type] == "custom"')
    ->showIf('shipping_city', '[shipping_type] == "custom"')
    ->showIf('shipping_zip', '[shipping_type] == "custom"')

    // Show billing fields only when billing type is 'different'
    ->showIf('billing_address', '[billing_type] == "different"')
    ->showIf('billing_city', '[billing_type] == "different"')
    ->showIf('billing_zip', '[billing_type] == "different"')
    ->render();
</code></pre>

    <h3>2. Checkbox-Based Conditional Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Show company fields only when 'is_company' checkbox is checked
    // Note: Checkbox value is typically '1' when checked
    ->showIf('company_name', '[is_company] == 1')
    ->showIf('vat_number', '[is_company] == 1')
    ->showIf('registration_number', '[is_company] == 1')
    ->render();
</code></pre>

    <h3>3. Multiple Conditions for Same Field</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Show different fields for different payment methods
$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Show card fields when payment method is 'credit_card'
    ->showIf('card_number', '[payment_method] == "credit_card"')
    ->showIf('card_expiry', '[payment_method] == "credit_card"')
    ->showIf('card_cvv', '[payment_method] == "credit_card"')

    // Show bank fields when payment method is 'bank_transfer'
    ->showIf('bank_name', '[payment_method] == "bank_transfer"')
    ->showIf('account_number', '[payment_method] == "bank_transfer"')
    ->showIf('swift_code', '[payment_method] == "bank_transfer"')

    // Show PayPal email when payment method is 'paypal'
    ->showIf('paypal_email', '[payment_method] == "paypal"')
    ->render();
</code></pre>

    <h3>4. User Type Based Fields</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, $this->page)
    // Show admin-specific fields
    ->showIf('admin_level', '[user_type] == "admin"')
    ->showIf('permissions', '[user_type] == "admin"')
    ->showIf('department', '[user_type] == "admin"')

    // Show customer-specific fields
    ->showIf('customer_type', '[user_type] == "customer"')
    ->showIf('discount_level', '[user_type] == "customer"')
    ->showIf('credit_limit', '[user_type] == "customer"')

    // Show vendor-specific fields
    ->showIf('vendor_category', '[user_type] == "vendor"')
    ->showIf('commission_rate', '[user_type] == "vendor"')
    ->showIf('contract_date', '[user_type] == "vendor"')
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
            ->showIf('download_url', '[product_type] == "digital"')
            ->showIf('file_size', '[product_type] == "digital"')
            ->showIf('download_limit', '[product_type] == "digital"')

            // Show physical product fields when type is 'physical'
            ->showIf('weight', '[product_type] == "physical"')
            ->showIf('dimensions', '[product_type] == "physical"')
            ->showIf('shipping_class', '[product_type] == "physical"')

            // Show subscription fields when type is 'subscription'
            ->showIf('billing_period', '[product_type] == "subscription"')
            ->showIf('trial_days', '[product_type] == "subscription"')
            ->showIf('renewal_price', '[product_type] == "subscription"')

            // Show discount fields only when 'has_discount' is checked
            ->showIf('discount_percentage', '[has_discount] == 1')
            ->showIf('discount_start_date', '[has_discount] == 1')
            ->showIf('discount_end_date', '[has_discount] == 1')

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
    <p>Use <code>showIf()</code> on a container id to show/hide a full section at once.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Good - grouped related fields via container
->addContainer('CNT_ACTIVATION', ['activation_date', 'activated_by', 'activation_notes'], 3, '', 'Activation')
->showIf('CNT_ACTIVATION', '[status] == "active"')
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
    ->showIf('activation_date', '[status] == "active"')

    // Later, remove the condition
    ->removeFieldCondition('activation_date')

    ->render();
</code></pre>

    <h2>Technical Details</h2>

    <h3>JavaScript Implementation</h3>
    <p>The conditional visibility is handled by the <code>MilkForm</code> JavaScript class in <code>milk-form.js</code>. It:</p>
    <ul>
        <li>Automatically detects elements with <code>data-milk-show</code> attributes</li>
        <li>Evaluates expressions on each recalculation</li>
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
        <li>Verify field names inside the expression match the real form names (case-sensitive)</li>
        <li>Check that the expression evaluates to true/false as expected</li>
        <li>Ensure JavaScript is loaded (check browser console)</li>
        <li>Verify field is added to form before applying conditional visibility</li>
    </ol>

    <h3>Validation Issues</h3>
    <p>If required fields are causing validation errors when hidden:</p>
    <ul>
        <li>Make conditional fields optional in the model (use <code>false</code> parameter)</li>
        <li>The JavaScript automatically disables required validation for hidden fields</li>
        <li>Ensure you're using the latest version of <code>milk-form.js</code></li>
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
