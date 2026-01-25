<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Adding pre-loaded select with relationships
 * @guide developer
 * @order 46
 * @tags relationships, belongsTo, options, milkselect, foreign-key, table, form, dot-notation, preload, multiple-select
 */

 !defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Adding Pre-loaded Select with Relationships</h1>
    <p class="text-muted">Revision: 2025/10/28</p>
    <p class="lead">This guide shows you how to add a select field that loads all data immediately (no autocomplete fetch), perfect for small datasets like categories, departments, or roles.</p>

    <div class="alert alert-info">
        <strong>What You'll Learn:</strong>
        <ul class="mb-0">
            <li>How to add a relationship field with pre-loaded options</li>
            <li>How to create a second model and link it to the first</li>
            <li>How to display related data in tables using dot notation</li>
            <li>How to create multiple select fields for many-to-many relationships</li>
        </ul>
    </div>

    <div class="alert alert-warning">
        <strong>⚠️ When to use pre-loaded vs autocomplete:</strong>
        <ul class="mb-0">
            <li><strong>Pre-loaded (this guide):</strong> Small datasets (up to ~100 items) - categories, departments, roles, statuses</li>
            <li><strong>Autocomplete fetch:</strong> Large datasets (hundreds/thousands) - users, products, customers. See <a href="?page=docs&action=Developer/Form/autocomplete-search">Autocomplete Search Guide</a></li>
        </ul>
    </div>

    <div class="alert alert-info">
        <strong>💡 Prerequisites:</strong> This guide assumes you already have a working module. If not, see:
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-model">Getting Started - Creating a Model</a></li>
            <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-post">Getting Started - Creating a Module with Table and Form</a></li>
        </ul>
    </div>

    <h2>Example: Employees with Categories</h2>
    <p>We'll create a <strong>Categories</strong> model and link it to <strong>Employees</strong>. This pattern works for:</p>
    <ul>
        <li>Employees → Categories (departments, roles)</li>
        <li>Products → Categories</li>
        <li>Posts → Categories/Tags</li>
        <li>Tasks → Priorities/Statuses</li>
    </ul>

    <hr>

    <h2>Step 1: Create the Second Model (Categories)</h2>
    <p>First, create a simple model for the data you want to select from (e.g., categories).</p>

    <h3>File: CategoryModel.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Modules\Employees;
use App\Abstracts\AbstractModel;

class CategoryModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table(\'#__employee_categories\')
            ->id()
            ->string(\'name\', 100)->required()->label(\'Category Name\')
            ->text(\'description\')->nullable();
    }

    /**
     * Get list of categories for select options
     * Returns array: [id => name]
     */
    public function getList()
    {
        $list = $this->query()->order(\'name\')->getResults();
        $array_return = [];
        foreach ($list as $value) {
            $array_return[$value->id] = $value->name;
        }
        return $array_return;
    }
}'); ?></code></pre>

    <div class="alert alert-success">
        <strong>✅ Key Points:</strong>
        <ul class="mb-0">
            <li>The <code>getList()</code> method is essential - it returns <code>[id => name]</code> for the select</li>
            <li>Keep it in the same namespace as the main model (e.g., <code>Modules\Employees</code>)</li>
            <li>Use <code>->order('name')</code> to sort alphabetically</li>
        </ul>
    </div>

    <hr>

    <h2>Step 2: Register the Model in the Module</h2>
    <p>Before creating the database table, you need to register the CategoryModel in your module's configuration.</p>

    <h3>File: EmployeesModule.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Modules\Employees;
use App\Abstracts\AbstractModule;

class EmployeesModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page(\'employees\')
            ->title(\'Employees\')
            ->menu(\'Employees\', \'\', \'bi bi-people-fill\', 20)

            // ========================================
            // ADD THIS LINE TO REGISTER SECONDARY MODELS
            // ========================================
            ->addModels([\'category\' => CategoryModel::class])

            ->access(\'public\')
            ->version(20251028);
    }
}'); ?></code></pre>

    <div class="alert alert-success">
        <strong>✅ Important:</strong> The <code>->addModels()</code> method tells the system to manage these secondary models during installation and updates.
    </div>

    <hr>

    <h2>Step 3: Add Relationship Field to Main Model</h2>
    <p>Now add the <code>category_id</code> field to your main model (e.g., EmployeesModel).</p>

    <h3>Example: EmployeesModel.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('protected function configure($rule): void
{
    $rule->table(\'#__employees\')
        ->id()
        ->string(\'name\', 100)->required()
        ->string(\'surname\', 100)->required()

        // ========================================
        // ADD THIS CATEGORY FIELD
        // ========================================
        ->int(\'category_id\')
            ->belongsTo(\'category\', CategoryModel::class, \'id\')
            ->formType(\'milkSelect\')
            ->options((new CategoryModel())->getList())
            ->label(\'Category\')
            ->nullable()

        ->timestamp(\'created_at\')->hideFromEdit()->saveValue(time());
}'); ?></code></pre>

    <h3>Understanding Each Method</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>->int('category_id')</code></td>
                <td>Creates an integer field in the database to store the category ID</td>
            </tr>
            <tr>
                <td><code>->belongsTo('category', CategoryModel::class, 'id')</code></td>
                <td>Defines the relationship: "this employee belongs to a category"<br>
                    <ul class="mb-0 mt-1">
                        <li><code>'category'</code>: alias for accessing the relationship</li>
                        <li><code>CategoryModel::class</code>: the related model</li>
                        <li><code>'id'</code>: the key field in the categories table</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td><code>->formType('milkSelect')</code></td>
                <td>Uses MilkSelect component (dropdown with search)</td>
            </tr>
            <tr>
                <td><code>->options(...)</code></td>
                <td>Pre-loads all options at page load<br>
                    <strong>Difference from apiUrl:</strong> No AJAX fetch, all data loaded immediately
                </td>
            </tr>
            <tr>
                <td><code>->nullable()</code></td>
                <td>Makes the field optional (use <code>->required()</code> if mandatory)</td>
            </tr>
        </tbody>
    </table>

    <div class="alert alert-warning">
        <strong>⚠️ Key Difference:</strong>
        <ul class="mb-0">
            <li><code>->options(...)</code> = Pre-loads all data (this guide)</li>
            <li><code>->apiUrl(...)</code> = Fetches via AJAX while typing (autocomplete guide)</li>
        </ul>
    </div>

    <hr>

    <h2>Step 4: Create/Update the Database Tables</h2>
    <p>Run the CLI command to create all tables and update the schema:</p>

    <pre class="border p-2 bg-dark text-light"><code>php milkadmin/cli.php employees:update</code></pre>

    <p>This command will:</p>
    <ul>
        <li>Create the <code>#__employee_categories</code> table if it doesn't exist</li>
        <li>Add the <code>category_id</code> column to the <code>#__employees</code> table</li>
        <li>Execute the <code>afterCreateTable()</code> method to populate initial data (if defined)</li>
    </ul>

    <div class="alert alert-info">
        <strong>💡 Tip:</strong> You can add sample data automatically by defining the <code>afterCreateTable()</code> method in your CategoryModel:
        <pre class="mb-0"><code><?php echo htmlspecialchars('protected function afterCreateTable(): void
{
    $sql = "INSERT INTO `" . $this->table . "` (`name`, `description`) VALUES
        (\'Engineering\', \'Engineering department\'),
        (\'Sales\', \'Sales department\'),
        (\'Marketing\', \'Marketing department\');";
    $this->db->query($sql);
}'); ?></code></pre>
    </div>

    <hr>

    <h2>Step 5: Display Category in Table</h2>
    <p>Update your controller's list method to show the category name using <strong>dot notation</strong>.</p>

    <h3>Example: EmployeesController.php - list() method</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('#[RequestAction(\'home\')]
public function list()
{
    // ... your existing code ...

    $tableBuilder = TableBuilder::create($this->model, \'tblEmployees\')
        ->column(\'name\', \'Name\', \'text\')
        ->column(\'surname\', \'Surname\', \'text\')

        // ========================================
        // ADD THIS LINE TO SHOW CATEGORY
        // ========================================
        ->column(\'category.name\', \'Category\', \'text\')

        // ... rest of your table configuration ...
        ->render();

    // ... rest of your code ...
}'); ?></code></pre>

    <div class="alert alert-success">
        <strong>✅ Dot Notation:</strong> The <code>category.name</code> automatically loads the category relationship and displays the name instead of the ID.
    </div>

    <hr>

    <h2>Multiple Select - Many-to-Many Relationships</h2>
    <p>Now let's add a <strong>multiple select</strong> field where employees can be assigned to multiple categories (or doctors, skills, etc.).</p>

    <h3>Example: Appointments with Multiple Doctors</h3>
    <p>The Appointments module already has this implemented. Here's how it works:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('// In AppointmentsModel.php
->array(\'doctor_ids\')
    ->options((new DoctorsModel())->getList())
    ->label(\'Doctors\')
    ->formType(\'milkSelect\')
    ->formParams([\'type\' => \'multiple\'])'); ?></code></pre>

    <h3>Understanding Multiple Select</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>->array('doctor_ids')</code></td>
                <td>Creates a field that stores multiple values as array (JSON in database)</td>
            </tr>
            <tr>
                <td><code>->options(...)</code></td>
                <td>Pre-loads all available options</td>
            </tr>
            <tr>
                <td><code>->formType('milkSelect')</code></td>
                <td>Uses MilkSelect component</td>
            </tr>
            <tr>
                <td><code>->formParams(['type' => 'multiple'])</code></td>
                <td><strong>This makes it multiple!</strong> Allows selecting multiple items</td>
            </tr>
        </tbody>
    </table>

    <div class="alert alert-info">
        <strong>💡 Database Storage:</strong>
        <ul class="mb-0">
            <li>Multiple values are stored as JSON array in the database</li>
            <li>Example: <code>["1", "3", "5"]</code> for IDs 1, 3, and 5</li>
            <li>The framework automatically handles JSON encoding/decoding</li>
        </ul>
    </div>

    <hr>

    <h2>Complete Example: Employees with Multiple Skills</h2>
    <p>Let's add a multiple select for employee skills:</p>

    <h3>1. Create SkillModel.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Modules\Employees;
use App\Abstracts\AbstractModel;

class SkillModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table(\'#__employee_skills\')
            ->id()
            ->string(\'name\', 100)->required()->label(\'Skill Name\');
    }

    public function getList()
    {
        $list = $this->query()->order(\'name\')->getResults();
        $array_return = [];
        foreach ($list as $value) {
            $array_return[$value->id] = $value->name;
        }
        return $array_return;
    }
}'); ?></code></pre>

    <h3>2. Update EmployeesModel.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('protected function configure($rule): void
{
    $rule->table(\'#__employees\')
        ->id()
        ->string(\'name\', 100)->required()
        ->string(\'surname\', 100)->required()

        // Single select (one category)
        ->int(\'category_id\')
            ->belongsTo(\'category\', CategoryModel::class, \'id\')
            ->formType(\'milkSelect\')
            ->options((new CategoryModel())->getList())
            ->label(\'Category\')

        // ========================================
        // MULTIPLE SELECT (many skills)
        // ========================================
        ->array(\'skill_ids\')
            ->options((new SkillModel())->getList())
            ->label(\'Skills\')
            ->formType(\'milkSelect\')
            ->formParams([\'type\' => \'multiple\'])
            ->nullable()

        ->timestamp(\'created_at\')->hideFromEdit()->saveValue(time());
}'); ?></code></pre>

    <h3>3. Register SkillModel in EmployeesModule.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('protected function configure($rule): void
{
    $rule->page(\'employees\')
        ->title(\'Employees\')
        ->menu(\'Employees\', \'\', \'bi bi-people-fill\', 20)

        // ========================================
        // REGISTER BOTH SECONDARY MODELS
        // ========================================
        ->addModels([
            \'category\' => CategoryModel::class,
            \'skill\' => SkillModel::class
        ])

        ->access(\'public\')
        ->version(20251028);
}'); ?></code></pre>

    <h3>4. Create/Update Tables</h3>
    <pre class="border p-2 bg-dark text-light"><code>php milkadmin/cli.php employees:update</code></pre>

    <p>This single command will:</p>
    <ul>
        <li>Create the <code>#__employee_skills</code> table</li>
        <li>Add the <code>skill_ids</code> column (TEXT type for JSON storage) to <code>#__employees</code></li>
        <li>Execute <code>afterCreateTable()</code> for initial data population</li>
    </ul>

    <div class="alert alert-success">
        <strong>✅ That's it!</strong> The form will now show:
        <ul class="mb-0">
            <li><strong>Category:</strong> Single select dropdown (one choice)</li>
            <li><strong>Skills:</strong> Multiple select dropdown (select many)</li>
        </ul>
    </div>

    <hr>

    <h2>Displaying Multiple Values in Tables</h2>
    <p>To display multiple selected values in tables, you need custom formatting:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('// In EmployeesController.php
$tableBuilder = TableBuilder::create($this->model, \'tblEmployees\')
    ->column(\'name\', \'Name\', \'text\')
    ->column(\'category.name\', \'Category\', \'text\')

    // For multiple values, you need custom formatting
    ->column(\'skill_ids\', \'Skills\', \'callback\', function($value, $record) {
        if (empty($value)) return \'-\';
        $skillModel = new SkillModel();
        $skills = [];
        foreach ($value as $skillId) {
            $skill = $skillModel->find($skillId);
            if ($skill) $skills[] = $skill->name;
        }
        return implode(\', \', $skills);
    })

    ->render();'); ?></code></pre>

    <hr>

    <h2>Comparison: Single vs Multiple vs Autocomplete</h2>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Feature</th>
                <th>Single Pre-loaded</th>
                <th>Multiple Pre-loaded</th>
                <th>Autocomplete Fetch</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Field Type</strong></td>
                <td><code>->int('category_id')</code></td>
                <td><code>->array('skill_ids')</code></td>
                <td><code>->int('user_id')</code></td>
            </tr>
            <tr>
                <td><strong>Selection</strong></td>
                <td>One item only</td>
                <td>Multiple items</td>
                <td>One item only</td>
            </tr>
            <tr>
                <td><strong>Data Loading</strong></td>
                <td>All at page load</td>
                <td>All at page load</td>
                <td>AJAX fetch on typing</td>
            </tr>
            <tr>
                <td><strong>Configuration</strong></td>
                <td><code>->options(...)</code></td>
                <td><code>->options(...)<br>->formParams(['type' => 'multiple'])</code></td>
                <td><code>->apiUrl(...)</code></td>
            </tr>
            <tr>
                <td><strong>Best For</strong></td>
                <td>Small lists (10-100 items)</td>
                <td>Small lists (10-100 items)</td>
                <td>Large lists (100+ items)</td>
            </tr>
            <tr>
                <td><strong>Database Storage</strong></td>
                <td>Integer (single ID)</td>
                <td>JSON array of IDs</td>
                <td>Integer (single ID)</td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2>Troubleshooting</h2>

    <h3>Problem: Dropdown shows IDs instead of names</h3>
    <p><strong>Solution:</strong></p>
    <ul>
        <li>Check that <code>getList()</code> method exists in the related model</li>
        <li>Verify <code>getList()</code> returns <code>[id => name]</code> format</li>
        <li>Ensure you're calling <code>(new CategoryModel())->getList()</code> correctly</li>
    </ul>

    <h3>Problem: Multiple select not working</h3>
    <p><strong>Check:</strong></p>
    <ul>
        <li>Field type is <code>->array('field_name')</code> not <code>->int()</code></li>
        <li><code>->formParams(['type' => 'multiple'])</code> is present</li>
        <li>Database field type supports JSON storage (TEXT or JSON type)</li>
    </ul>

    <h3>Problem: Empty dropdown</h3>
    <p><strong>Solutions:</strong></p>
    <ul>
        <li>Check that the related table has data</li>
        <li>Verify <code>getList()</code> method returns non-empty array</li>
        <li>Check for PHP errors in browser console</li>
    </ul>

    <hr>

    <h2>Summary: Quick Reference</h2>

    <div class="alert alert-success">
        <strong>✅ Single Select (Pre-loaded):</strong>
        <pre class="mb-0"><code><?php echo htmlspecialchars('->int(\'category_id\')
    ->belongsTo(\'category\', CategoryModel::class, \'id\')
    ->formType(\'milkSelect\')
    ->options((new CategoryModel())->getList())'); ?></code></pre>
    </div>

    <div class="alert alert-info">
        <strong>✅ Multiple Select (Pre-loaded):</strong>
        <pre class="mb-0"><code><?php echo htmlspecialchars('->array(\'skill_ids\')
    ->options((new SkillModel())->getList())
    ->formType(\'milkSelect\')
    ->formParams([\'type\' => \'multiple\'])'); ?></code></pre>
    </div>

    <div class="alert alert-warning">
        <strong>✅ Autocomplete (Fetch):</strong>
        <pre class="mb-0"><code><?php echo htmlspecialchars('->int(\'user_id\')
    ->belongsTo(\'user\', UserModel::class, \'id\')
    ->formType(\'milkSelect\')
    ->apiUrl(\'?page=module&action=related-search-field&f=user_id\', \'username\')'); ?></code></pre>
    </div>

    <hr>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Form/autocomplete-search">Autocomplete Search - For Large Datasets</a></li>
        <li><a href="?page=docs&action=Developer/Model/abstract-model-relationships">Model Relationships - Complete Guide</a></li>
        <li><a href="?page=docs&action=Framework/Forms/form-milkselect">MilkSelect - Component Reference</a></li>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder - Table Management</a></li>
    </ul>

</div>

<style>
pre {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}
.alert {
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 0.5rem;
}
.alert-info {
    background: #cfe2ff;
    border-left: 4px solid #0d6efd;
}
.alert-success {
    background: #d1e7dd;
    border-left: 4px solid #198754;
}
.alert-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}
.alert-danger {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
}
.table-dark {
    background: #212529;
    color: white;
}
</style>
