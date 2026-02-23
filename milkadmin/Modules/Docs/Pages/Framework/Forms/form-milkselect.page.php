<?php
namespace Modules\Docs\Pages;
use App\{Get, Form};
/**
 * @title MilkSelect - Dynamic Autocomplete
 * @guide framework
 * @order 25
 * @tags form, select, autocomplete, ajax, api, dynamic, relationship, belongsTo
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

    <div class="alert alert-primary">
        <h5 class="alert-heading">Quick Form Creation with FormBuilder</h5>
        <p class="mb-0">
            This is a manual, artisanal system for creating autocomplete select fields. If you need to create forms quickly from your Models,
            we recommend using the <strong>FormBuilder</strong> which can generate complete forms in minutes:
            <br><br>
            <a href="?page=docs&action=Developer/Form/builders-form" class="alert-link">
                <strong>→ Getting Started - Forms with FormBuilder</strong>
            </a>
        </p>
    </div>

    <h1>MilkSelect - Dynamic Autocomplete Select</h1>
    <p>MilkSelect is a powerful autocomplete component that supports both static options and dynamic API-based loading. It's perfect for handling relationships and large datasets.</p>

  
    <hr>

    <h2>Basic Usage - Static Options</h2>
    <p>For small, static datasets, you can pass options directly:</p>

    <h3>Single Selection</h3>
    <div class="bg-light p-3 mb-3">
        <?php
        Form::milkSelect('country', 'Country', ['IT' => 'Italy', 'FR' => 'France', 'DE' => 'Germany'], 'IT');
        ?>
    </div>
    <pre class="border p-2"><code class="language-php">Form::milkSelect('country', 'Country',
    ['IT' => 'Italy', 'FR' => 'France', 'DE' => 'Germany'],
    'IT'  // selected value
);</code></pre>

    <h3>Multiple Selection</h3>
    <div class="bg-light p-3 mb-3">
        <?php
        Form::milkSelect('tags', 'Tags',
            ['php' => 'PHP', 'js' => 'JavaScript', 'py' => 'Python'],
            ['php', 'js'],
            ['type' => 'multiple']
        );
        ?>
    </div>
    <pre class="border p-2"><code class="language-php">Form::milkSelect('tags', 'Tags',
    ['php' => 'PHP', 'js' => 'JavaScript', 'py' => 'Python'],
    ['php', 'js'],  // selected values (array)
    ['type' => 'multiple']
);</code></pre>

    <hr>

    <h2>Dynamic API Loading - The Power Feature</h2>
    <p>For large datasets or when you need search functionality, use API loading. This is the recommended approach for relationships.</p>

    <h3>Step-by-Step Implementation</h3>

    <h4>Step 1: Configure the Model Field</h4>
    <p>In your model's <code>configure()</code> method, use <code>apiUrl()</code> to specify the API endpoint and display field:</p>

    <pre class="border p-2"><code class="language-php">// Example: CarsModel.php
class CarsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__cars')
            ->id()
            ->string('model', 100)->required()
            ->string('color', 50)

            // Single selection with belongsTo
            ->int('manufacturer_id')
                ->belongsTo('manufacturer', ManufacturersModel::class, 'id')
                ->where('active = 1')  // Optional: filter related records
                ->formType('milkSelect')
                ->apiUrl('?page=cars&action=search-manufacturers&f=manufacturer_id', 'name')
                ->required()
                ->error('Please select a manufacturer');
    }
}</code></pre>

    <div class="alert alert-info">
        <strong>📌 Important:</strong>
        <ul>
            <li><code>apiUrl()</code> takes 2 parameters: the API endpoint and the field to display</li>
            <li>The URL must include <code>&f=field_name</code> so the handler knows which field/relationship to use</li>
            <li><code>belongsTo()</code> is required for automatic relationship handling</li>
            <li><code>where()</code> must be called immediately after <code>belongsTo()</code> — it filters both the form display and the API search results</li>
            <li>The field type should be <code>int</code> for single selection with belongsTo</li>
        </ul>
    </div>

    <h4>Step 2: Create the Controller Action</h4>
    <p>Add an action in your Controller to handle the search requests:</p>

    <pre class="border p-2"><code class="language-php">// Example: CarsController.php
class CarsController extends AbstractController
{
    #[RequestAction('search-manufacturers')]
    public function searchManufacturers() {
        $search = $_REQUEST['q'] ?? '';
        $options = $this->model->searchRelated($search, 'manufacturer_id');
        Response::json([
            'success' => 'ok',
            'options' => $options
        ]);
    }
}</code></pre>

    <div class="alert alert-warning">
        <strong>⚠️ Note:</strong> The action name must match the one in <code>apiUrl()</code>. The search term is passed as <code>q</code> parameter.
    </div>

    <h4>Step 2b: Built-in Action (Alternative)</h4>
    <p>If you don't need custom search logic, you can skip creating a controller action entirely. The framework provides a built-in <code>related-search-field</code> action
    that works automatically with any <code>belongsTo</code> relationship:</p>

    <pre class="border p-2"><code class="language-php">// In your Model - just point apiUrl to the built-in action:
->int('manufacturer_id')
    ->belongsTo('manufacturer', ManufacturersModel::class, 'id')
    ->where('active = 1')
    ->formType('milkSelect')
    ->apiUrl('?page=cars&action=related-search-field&f=manufacturer_id', 'name')</code></pre>

    <div class="alert alert-success">
        <strong>✅ No controller code needed!</strong> The built-in handler automatically:
        <ul>
            <li>Reads the <code>belongsTo</code> relationship configuration from the field specified by <code>&f=</code></li>
            <li>Instantiates the related model and searches by the display field</li>
            <li>Applies <code>where()</code> conditions if defined on the relationship</li>
            <li>Returns the first 20 matching results</li>
        </ul>
    </div>

    <h4>Step 3: Add Search Method to Model</h4>
    <p>Use the generic <code>searchRelated()</code> method that reads configuration from the relationship:</p>

    <pre class="border p-2"><code class="language-php">// Add to your Model (e.g., CarsModel.php)
/**
 * Generic search for autocomplete based on relationship configuration
 */
public function searchRelated(string $search = '', string $field_name = 'manufacturer_id'): array
{
    $rules = $this->getRules();

    // Get the relationship configuration
    if (!isset($rules[$field_name]['relationship']) ||
        $rules[$field_name]['relationship']['type'] !== 'belongsTo') {
        return [];
    }

    $relationship = $rules[$field_name]['relationship'];
    $related_model_class = $relationship['related_model'];
    $display_field = $rules[$field_name]['api_display_field'] ?? 'name';
    $related_key = $relationship['related_key'] ?? 'id';

    // Instantiate related model
    $relatedModel = new $related_model_class();
    $query = $relatedModel->query();

    // Filter by search term on display field
    if (!empty($search)) {
        $query->where("$display_field LIKE ?", '%' . $search . '%');
    }

    $results = $query->limit(0, 20)->getResults();
    $options = [];

    foreach ($results as $result) {
        $options[$result->$related_key] = $result->$display_field;
    }

    return $options;
}</code></pre>

    <div class="alert alert-success">
        <strong>✅ Benefits:</strong> This method is generic and reusable! It automatically reads:
        <ul>
            <li>The related model class from <code>belongsTo()</code></li>
            <li>The display field from <code>apiUrl()</code></li>
            <li>The key field from the relationship</li>
            <li>The <code>where()</code> condition if defined on the relationship</li>
        </ul>
    </div>

    <hr>

    <h2>Multiple Selection WITHOUT belongsTo</h2>
    <p>For many-to-many relationships or when you don't need belongsTo, use a text field with multiple mode:</p>

    <pre class="border p-2"><code class="language-php">// In your Model
->text('category_ids')
    ->label('Categories')
    ->formType('milkSelect')
    ->apiUrl('?page=products&action=search-categories', 'name')
    ->formParams(['type' => 'multiple'])
    ->excludeFromDatabase();  // if it's not a real DB column</code></pre>

    <div class="alert alert-info">
        <strong>📌 Key Differences:</strong>
        <ul>
            <li>Use <code>text</code> type instead of <code>int</code></li>
            <li>No <code>belongsTo()</code> relationship</li>
            <li>Add <code>['type' => 'multiple']</code> in formParams</li>
            <li>Data is saved as JSON array: <code>["1","2","3"]</code></li>
        </ul>
    </div>

    <hr>

    <h2>Complete Working Example</h2>
    <p>Here's a minimal but complete example you can use as a starting point:</p>

    <h3>1. Create the Models</h3>

    <h4>ManufacturersModel.php</h4>
    <pre class="border p-2"><code class="language-php">namespace Modules\Examples;
use App\Abstracts\AbstractModel;

class ManufacturersModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__manufacturers')
            ->id()
            ->string('name', 100)->required()
            ->string('country', 50);
    }
}</code></pre>

    <h4>CarsModel.php</h4>
    <pre class="border p-2"><code class="language-php">namespace Modules\Examples;
use App\Abstracts\AbstractModel;

class CarsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__cars')
            ->id()
            ->string('model', 100)->required()
            ->int('manufacturer_id')
                ->belongsTo('manufacturer', ManufacturersModel::class, 'id')
                ->where('active = 1')  // Only show active manufacturers
                ->formType('milkSelect')
                ->apiUrl('?page=examples-cars&action=related-search-field&f=manufacturer_id', 'name')
                ->required();
    }
}</code></pre>

    <div class="alert alert-info">
        <strong>📌 Note:</strong> Using the built-in <code>related-search-field</code> action, no <code>searchRelated()</code> method override is needed.
        The framework handles everything automatically, including the <code>where()</code> condition.
    </div>

    <h3>2. Create the Controller</h3>
    <pre class="border p-2"><code class="language-php">namespace Modules\Examples;
use App\{Response};
use App\Abstracts\AbstractController;
use App\Attributes\RequestAction;
use Builders\{TableBuilder, FormBuilder};

class CarsController extends AbstractController
{
    #[RequestAction('home')]
    public function carsList() {
        $response = TableBuilder::create($this->model, 'idTableCars')
            ->column('manufacturer.name', 'Manufacturer')
            ->setDefaultActions()
            ->getResponse();

        $response['page'] = $this->page;
        $response['title'] = 'Cars Management';
        Response::render(__DIR__ . '/views/cars_list.php', $response);
    }

    #[RequestAction('car-edit')]
    public function carEdit() {
        $response = ['page' => $this->page, 'title' => 'Edit Car'];
        $response['form'] = FormBuilder::create($this->model)->getForm();
        Response::render(__DIR__ . '/views/car_edit.php', $response);
    }

    #[RequestAction('search-manufacturers')]
    public function searchManufacturers() {
        $search = $_REQUEST['q'] ?? '';
        $options = $this->model->searchRelated($search, 'manufacturer_id');
        Response::json([
            'success' => 'ok',
            'options' => $options
        ]);
    }
}</code></pre>

    <hr>

    <h2>How It Works</h2>
    <ol>
        <li><strong>Initial Load:</strong> When editing an existing record, the system uses lazy loading to display the current value (e.g., "Toyota" instead of "1")</li>
        <li><strong>User Types:</strong> After 300ms, a fetch request is sent to the API with the search term</li>
        <li><strong>API Response:</strong> The server returns filtered options: <code>{"success":"ok", "options":{"1":"Toyota","2":"Honda"}}</code></li>
        <li><strong>Display:</strong> Options are shown in the dropdown</li>
        <li><strong>Selection:</strong> The ID is saved in the database, but the name is displayed to the user</li>
    </ol>

    <hr>

    <h2>Configuration Options</h2>

    <h3>Field Configuration (in Model)</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>apiUrl()</code></td>
                <td>$url, $display_field</td>
                <td>Set API endpoint and field to display</td>
            </tr>
            <tr>
                <td><code>belongsTo()</code></td>
                <td>$alias, $model, $key</td>
                <td>Define relationship for automatic handling</td>
            </tr>
            <tr>
                <td><code>where()</code></td>
                <td>$condition, $params</td>
                <td>Filter related records (must be called right after <code>belongsTo()</code>). Applied to both form display and API search.</td>
            </tr>
            <tr>
                <td><code>formType()</code></td>
                <td>'milkSelect'</td>
                <td>Set field type to MilkSelect</td>
            </tr>
            <tr>
                <td><code>formParams()</code></td>
                <td>['type' => 'multiple']</td>
                <td>Enable multiple selection mode</td>
            </tr>
            <tr>
                <td><code>required()</code></td>
                <td>-</td>
                <td>Make field required</td>
            </tr>
            <tr>
                <td><code>error()</code></td>
                <td>$message</td>
                <td>Custom validation error message</td>
            </tr>
        </tbody>
    </table>

    <h3>Form Options</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Option</th>
                <th>Values</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>type</code></td>
                <td>'single', 'multiple'</td>
                <td>Selection mode</td>
            </tr>
            <tr>
                <td><code>required</code></td>
                <td>true, false</td>
                <td>Field validation</td>
            </tr>
            <tr>
                <td><code>class</code></td>
                <td>string</td>
                <td>Additional CSS classes</td>
            </tr>
            <tr>
                <td><code>placeholder</code></td>
                <td>string</td>
                <td>Placeholder text (not with floating)</td>
            </tr>
            <tr>
                <td><code>floating</code></td>
                <td>true, false</td>
                <td>Enable floating label (default: true)</td>
            </tr>
            <tr>
                <td><code>invalid-feedback</code></td>
                <td>string</td>
                <td>Custom error message</td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2>API Response Format</h2>
    <p>Your API endpoint must return JSON in this format:</p>

    <pre class="border p-2"><code class="language-json">{
    "success": "ok",
    "options": {
        "1": "Toyota",
        "2": "Honda",
        "3": "Ford"
    }
}</code></pre>

    <p>Or for simple arrays without keys:</p>
    <pre class="border p-2"><code class="language-json">{
    "success": "ok",
    "options": ["Red", "Blue", "Green"]
}</code></pre>

    <div class="alert alert-danger">
        <strong>⚠️ Error Handling:</strong> If the response has <code>"success" !== "ok"</code> or fetch fails, a simple alert will be shown to the user.
    </div>

    <hr>

    <h2>Filtering with where()</h2>
    <p>You can filter the related records shown in the autocomplete dropdown using <code>where()</code> on the relationship. The condition is applied consistently in all contexts:</p>

    <ul>
        <li><strong>Form display</strong> (lazy loading existing value)</li>
        <li><strong>API search</strong> (autocomplete results via <code>searchRelated()</code> or built-in <code>related-search-field</code>)</li>
    </ul>

    <h3>Example: Show only active categories</h3>
    <pre class="border p-2"><code class="language-php">->int('category_id')->label('Category')
    ->belongsTo('category', CategoriesModel::class, 'id')
    ->where('active = 1')
    ->formType('milkSelect')
    ->apiUrl('?page=mypage&action=related-search-field&f=category_id', 'name')</code></pre>

    <h3>Example: Filter with parameters</h3>
    <pre class="border p-2"><code class="language-php">->int('teacher_id')->label('Teacher')
    ->belongsTo('teacher', UsersModel::class, 'id')
    ->where('role = ? AND status = ?', ['teacher', 'active'])
    ->formType('milkSelect')
    ->apiUrl('?page=courses&action=related-search-field&f=teacher_id', 'name')</code></pre>

    <div class="alert alert-info">
        <strong>📌 Important:</strong> <code>where()</code> must be called <strong>immediately after</strong> <code>belongsTo()</code>.
        Calling it after <code>apiUrl()</code> or <code>formType()</code> will throw a <code>LogicException</code>.
    </div>

    <h3>JSON Configuration</h3>
    <p>When using JSON schema (Projects extension), the where condition is defined inside the <code>belongsTo</code> object:</p>
    <pre class="border p-2"><code class="language-json">{
    "name": "category_id",
    "method": "int",
    "formType": "milkSelect",
    "belongsTo": {
        "alias": "category",
        "related_model": "CategoriesModel",
        "related_key": "id",
        "where": {
            "condition": "active = 1",
            "params": []
        }
    },
    "apiUrl": {
        "url": "?page=mypage&action=related-search-field&f=category_id",
        "display_field": "name"
    }
}</code></pre>

    <hr>

    <h2>Best Practices</h2>

    <div class="alert alert-success">
        <strong>✅ Do:</strong>
        <ul>
            <li>Use <code>apiUrl()</code> for large datasets (> 50 items)</li>
            <li>Use <code>belongsTo()</code> with single selection for automatic relationship handling</li>
            <li>Limit API results to 20-50 items for better performance</li>
            <li>Use the generic <code>searchRelated()</code> method for consistency</li>
            <li>Add indexes on search fields in your database</li>
        </ul>
    </div>

    <div class="alert alert-warning">
        <strong>❌ Don't:</strong>
        <ul>
            <li>Don't use <code>belongsTo()</code> with array/text fields (only with int)</li>
            <li>Don't return more than 100 results from the API</li>
            <li>Don't forget to sanitize the search parameter in your API</li>
            <li>Don't use static options for large datasets</li>
        </ul>
    </div>

    <hr>

    <h2>Troubleshooting</h2>

    <h3>Problem: Shows ID instead of name</h3>
    <p><strong>Solution:</strong> Make sure you're using <code>belongsTo()</code> and the relationship is properly configured. The lazy loading will handle displaying the correct value.</p>

    <h3>Problem: API not called when typing</h3>
    <p><strong>Solution:</strong> Check that:</p>
    <ul>
        <li>The <code>apiUrl()</code> endpoint is correct</li>
        <li>The router action exists and is accessible</li>
        <li>The browser console shows no JavaScript errors</li>
    </ul>

    <h3>Problem: No options shown</h3>
    <p><strong>Solution:</strong> Verify the API response format matches the expected structure. Check the network tab in browser DevTools.</p>

    <hr>

    <h2>Advanced: Custom Search Logic</h2>
    <p>You can override <code>searchRelated()</code> for custom search logic:</p>

    <pre class="border p-2"><code class="language-php">public function searchManufacturers(string $search = ''): array
{
    $model = new ManufacturersModel();
    $query = $model->query();

    if (!empty($search)) {
        // Custom logic: search in name AND country
        $query->where("(name LIKE ? OR country LIKE ?)",
            '%' . $search . '%',
            '%' . $search . '%'
        );
    }

    $results = $query->orderBy('name ASC')->limit(0, 20)->getResults();
    $options = [];

    foreach ($results as $item) {
        // Custom format: show both name and country
        $options[$item->id] = $item->name . ' (' . $item->country . ')';
    }

    return $options;
}</code></pre>

</div>

<style>
.alert { padding: 1rem; margin: 1rem 0; border-radius: 0.5rem; }
.alert-info { background: #cfe2ff; border-left: 4px solid #0d6efd; }
.alert-success { background: #d1e7dd; border-left: 4px solid #198754; }
.alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; }
.alert-danger { background: #f8d7da; border-left: 4px solid #dc3545; }
</style>
