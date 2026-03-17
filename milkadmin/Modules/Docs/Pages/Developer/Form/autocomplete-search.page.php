<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Adding field with autocomplete search
 * @guide developer
 * @order 45
 * @tags relationships, belongsTo, user, milkselect, autocomplete, foreign-key, table, form, dot-notation, user_id, API, search
 */

 !defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Adding field with autocomplete search</h1>
    <p class="text-muted">Revision: 2025/10/27</p>
    <p class="lead">This guide shows you how to add a field with autocomplete search to your existing module with autocomplete search, table display, and automatic form handling.</p>

    <div class="alert alert-info">
        <strong>What You'll Learn:</strong>
        <ul class="mb-0">
            <li>How to add a <code>user_id</code> field with <code>belongsTo</code> relationship</li>
            <li>How to enable autocomplete search with MilkSelect</li>
            <li>How to display usernames in tables using dot notation</li>
            <li>How to create the search API endpoint</li>
        </ul>
    </div>

    <div class="alert alert-warning">
        <strong>⚠️ Prerequisites:</strong> This guide assumes you already have a working module. If you need to create a new module from scratch, see:
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-model">Getting Started - Creating a Model</a></li>
            <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-post">Getting Started - Creating a Module with Table and Form</a></li>
        </ul>
    </div>

    <h2>Example: Projects Module</h2>
    <p>We'll use a <strong>Projects</strong> module as example, where each project can be assigned to a user. This pattern works for:</p>
    <ul>
        <li>Tasks → assigned to users</li>
        <li>Orders → created by users</li>
        <li>Posts → written by users</li>
        <li>Tickets → opened by users</li>
    </ul>

    <hr>

    <h2>Step 1: Add user_id Field to Your Model</h2>
    <p>Open your model file and add the <code>user_id</code> field configuration inside the <code>configure()</code> method.</p>

    <h3>Example: ProjectsModel.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('protected function configure($rule): void
{
    $rule->table(\'#__projects\')
        ->id()
        ->string(\'title\', 200)->required()
        // ... your other fields ...

        // ========================================
        // ADD THIS USER_ID FIELD
        // ========================================
        ->int(\'user_id\')
            ->nullable()
            ->label(\'Project Owner\')
            ->belongsTo(\'user\', \Modules\Auth\UserModel::class, \'id\')
            ->formType(\'milkSelect\')
            ->apiUrl(\'?page=projects&action=related-search-field&f=user_id\', \'username\')

        ->timestamp(\'created_at\')->hideFromEdit()->saveValue(time());
}'); ?></code></pre>

    <div class="alert alert-success">
        <strong>✅ That's all you need!</strong> Using <code>action=related-search-field&f=user_id</code> means no controller method is required.
    </div>

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
                <td><code>->int('user_id')</code></td>
                <td>Creates an integer field in the database to store the user ID</td>
            </tr>
            <tr>
                <td><code>->nullable()</code></td>
                <td>Makes the field optional (remove this if you want it required)</td>
            </tr>
            <tr>
                <td><code>->label('Project Owner')</code></td>
                <td>Sets the label shown in forms</td>
            </tr>
            <tr>
                <td><code>->belongsTo('user', UserModel::class, 'id')</code></td>
                <td>Defines the relationship: "this project belongs to a user"<br>
                    <ul class="mb-0 mt-1">
                        <li><code>'user'</code>: alias for accessing the relationship</li>
                        <li><code>UserModel::class</code>: the related model</li>
                        <li><code>'id'</code>: the key field in the users table</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td><code>->formType('milkSelect')</code></td>
                <td>Uses MilkSelect component (autocomplete dropdown)</td>
            </tr>
            <tr>
                <td><code>->apiUrl('...', 'username')</code></td>
                <td>Sets the API endpoint and the field to display<br>
                    <ul class="mb-0 mt-1">
                        <li>First parameter: your search API URL
                            <ul>
                                <li><strong>Automatic:</strong> <code>'?page=projects&action=related-search-field&f=user_id'</code></li>
                                <li><strong>Custom:</strong> <code>'?page=projects&action=search-users'</code></li>
                            </ul>
                        </li>
                        <li>Second parameter: which user field to show (username, email, etc.)</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="alert alert-success">
        <strong>✅ Important:</strong> The <code>searchRelated()</code> method is already built into the framework! You don't need to add it to your model.
    </div>

    <hr>

    <h2>Step 2: Update the Database</h2>
    <p>Run the CLI command to add the <code>user_id</code> column to your database table:</p>

    <pre class="border p-2 bg-dark text-light"><code>php milkadmin/cli.php projects:update</code></pre>

    <p>Replace <code>projects</code> with your actual module name (e.g., <code>employees:update</code>, <code>tasks:update</code>).</p>

    <hr>

    <h2>Step 3: Display Username in Table</h2>
    <p>Update your controller's list method to show the username in the table using <strong>dot notation</strong>.</p>

    <h3>Example: ProjectsController.php - list() method</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('#[RequestAction(\'home\')]
public function list()
{
    // ... your existing code ...

    $tableBuilder = TableBuilder::create($this->model, \'tblProjects\')
        // ========================================
        // ADD THIS LINE TO SHOW USERNAME
        // ========================================
        ->column(\'user.username\', \'Owner\', \'text\')

        // ... rest of your table configuration ...
        ->hideColumns([\'created_at\'])
        ->render();

    // ... rest of your code ...
}'); ?></code></pre>

    <h3>Understanding Dot Notation</h3>
    <p>The <strong>dot notation</strong> <code>user.username</code> automatically:</p>
    <ol>
        <li>Loads the <code>user</code> relationship (defined with <code>belongsTo('user', ...)</code>)</li>
        <li>Extracts the <code>username</code> field from the related user</li>
        <li>Displays it in the table</li>
    </ol>

    <div class="alert alert-info">
        <strong>💡 You can access any field from the related user:</strong>
        <ul class="mb-0">
            <li><code>->column('user.username', 'Username', 'text')</code></li>
            <li><code>->column('user.email', 'Email', 'text')</code></li>
            <li><code>->column('user.status', 'Status', 'text')</code></li>
        </ul>
    </div>

    <hr>

    <h2>Step 4: Add Search API Endpoint</h2>
    <p>There are <strong>two ways</strong> to enable the autocomplete search functionality:</p>

    <h3>Option A: Automatic Method (Recommended for Most Cases)</h3>
    <p>The <strong>simplest approach</strong> - no controller code needed! Just use the built-in <code>related-search-field</code> action:</p>

    <h4>Model Configuration</h4>
    <pre class="border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('->int(\'user_id\')
    ->nullable()
    ->label(\'User\')
    ->belongsTo(\'user\', \Modules\Auth\UserModel::class, \'id\')
    ->formType(\'milkSelect\')
    ->apiUrl(\'?page=projects&action=related-search-field&f=user_id\', \'username\')'); ?></code></pre>

    <div class="alert alert-success">
        <strong>✅ That's it!</strong> No controller method needed. The framework automatically:
        <ul class="mb-0">
            <li>Reads the relationship configuration from your model</li>
            <li>Queries the related table (users in this case)</li>
            <li>Filters by the search term</li>
            <li>Returns matching results in the correct format</li>
        </ul>
    </div>

    <h4>Understanding the URL</h4>
    <ul>
        <li><code>action=related-search-field</code> - Uses the built-in automatic handler</li>
        <li><code>&f=user_id</code> - Specifies which field relationship to search (must match your field name)</li>
        <li><code>'username'</code> - The field to display from the related table</li>
    </ul>

    <hr>

    <h3>Option B: Custom Method (For Advanced Filtering)</h3>
    <p>Use this approach when you need to <strong>filter results</strong> or apply <strong>custom business logic</strong>.</p>

    <h4>Model Configuration</h4>
    <pre class="border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('->int(\'user_id\')
    ->nullable()
    ->label(\'User\')
    ->belongsTo(\'user\', \Modules\Auth\UserModel::class, \'id\')
    ->formType(\'milkSelect\')
    ->apiUrl(\'?page=projects&action=search-users\', \'username\')'); ?></code></pre>

    <h4>Controller Method</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('#[RequestAction(\'search-users\')]
public function searchUsers() {
    $search = $_REQUEST[\'q\'] ?? \'\';
    $options = $this->model->searchRelated($search, \'user_id\');

    // OPTIONAL: Apply custom filtering here
    // Example: Only show active users
    // $options = array_filter($options, function($userId) {
    //     $user = UserModel::find($userId);
    //     return $user && $user->status === \'active\';
    // }, ARRAY_FILTER_USE_KEY);

    Response::json([
        \'success\' => \'ok\',
        \'options\' => $options
    ]);
}'); ?></code></pre>

    <div class="alert alert-info">
        <strong>💡 When to use custom methods:</strong>
        <ul class="mb-0">
            <li>Filter results based on user permissions (e.g., only show users in same department)</li>
            <li>Apply status filters (e.g., only active users, only verified users)</li>
            <li>Add custom sorting or grouping</li>
            <li>Include additional data in the response</li>
            <li>Log search queries for analytics</li>
        </ul>
    </div>

    <div class="alert alert-warning">
        <strong>⚠️ Important:</strong> When using custom methods, make sure the action name in <code>#[RequestAction('search-users')]</code> matches the one in your model's <code>apiUrl()</code>.
    </div>

    <hr>

    <h2>Step 5: The Form (Automatic!)</h2>
    <p>If you're using <code>FormBuilder</code>, the form field is generated automatically. No changes needed!</p>

    <div class="alert alert-success">
        <strong>✅ FormBuilder automatically handles:</strong>
        <ul class="mb-0">
            <li>Rendering the MilkSelect autocomplete field</li>
            <li>Loading the current user when editing</li>
            <li>Showing username instead of ID</li>
            <li>Saving the selected user ID</li>
        </ul>
    </div>

    <hr>

    <h2>Complete Example</h2>
    <p>Here's a minimal complete example using the <strong>automatic method</strong> (recommended):</p>

    <h3>1. Model Configuration</h3>
    <pre class="border p-2"><code class="language-php"><?php echo htmlspecialchars('->int(\'user_id\')
    ->nullable()
    ->label(\'User\')
    ->belongsTo(\'user\', \Modules\Auth\UserModel::class, \'id\')
    ->formType(\'milkSelect\')
    ->apiUrl(\'?page=projects&action=related-search-field&f=user_id\', \'username\')'); ?></code></pre>

    <h3>2. Table Configuration</h3>
    <pre class="border p-2"><code class="language-php"><?php echo htmlspecialchars('$tableBuilder = TableBuilder::create($this->model, \'tblProjects\')
    ->column(\'user.username\', \'Owner\', \'text\')
    // ... rest of your config ...'); ?></code></pre>

    <h3>3. Controller API Method</h3>
    <p><strong>Not needed!</strong> The <code>related-search-field</code> action handles everything automatically.</p>

    <div class="alert alert-info">
        <strong>💡 Want custom filtering?</strong> Use Option B from Step 4 instead and add your custom <code>searchUsers()</code> method to the controller.
    </div>

    <hr>

    <h2>How It Works</h2>

    <h3>1. User Opens the Form</h3>
    <pre class="border p-2">FormBuilder generates MilkSelect field
↓
If editing existing record:
  belongsTo loads the user → shows "mario" instead of "5"</pre>

    <h3>2. User Types in the Autocomplete</h3>

    <h4>With Automatic Method (related-search-field):</h4>
    <pre class="border p-2">User types "mar"
↓
After 300ms → AJAX request to: ?page=projects&action=related-search-field&f=user_id&q=mar
↓
Framework automatically:
  - Reads belongsTo config for user_id
  - Queries: SELECT * FROM users WHERE username LIKE '%mar%' LIMIT 20
↓
Returns: {"success":"ok", "options":{"1":"mario","5":"marco"}}</pre>

    <h4>With Custom Method (search-users):</h4>
    <pre class="border p-2">User types "mar"
↓
After 300ms → AJAX request to: ?page=projects&action=search-users&q=mar
↓
searchUsers() in controller calls searchRelated("mar", "user_id")
↓
+ Custom filtering/logic applied (if any)
↓
Framework queries: SELECT * FROM users WHERE username LIKE '%mar%' LIMIT 20
↓
Returns: {"success":"ok", "options":{"1":"mario","5":"marco"}}</pre>

    <h3>3. User Selects and Saves</h3>
    <pre class="border p-2">User selects "mario" (ID=1)
↓
FormBuilder saves: UPDATE projects SET user_id=1 WHERE id=10</pre>

    <hr>

    <h2>Common Variations</h2>

    <h3>Make the Field Required</h3>
    <pre class="border p-2"><code class="language-php">->int('user_id')
    ->required()  // ← Add this
    ->error('Please select a user')  // Optional custom error
    ->belongsTo('user', \Modules\Auth\UserModel::class, 'id')
    ->formType('milkSelect')
    ->apiUrl('?page=projects&action=search-users', 'username')</code></pre>

    <h3>Display Email Instead of Username</h3>
    <pre class="border p-2"><code class="language-php">// In Model:
->apiUrl('?page=projects&action=search-users', 'email')  // ← Change to 'email'

// In Table:
->column('user.email', 'User Email', 'text')</code></pre>

    <h3>Add Multiple User Fields</h3>
    <pre class="border p-2"><code class="language-php">// Owner
->int('owner_id')
    ->belongsTo('owner', \Modules\Auth\UserModel::class, 'id')
    ->formType('milkSelect')
    ->apiUrl('?page=projects&action=related-search-field&f=owner_id', 'username')
    ->label('Project Owner')

// Manager
->int('manager_id')
    ->nullable()
    ->belongsTo('manager', \Modules\Auth\UserModel::class, 'id')
    ->formType('milkSelect')
    ->apiUrl('?page=projects&action=related-search-field&f=manager_id', 'username')
    ->label('Project Manager')</code></pre>

    <p>The <code>related-search-field</code> endpoint automatically handles different fields - just change the <code>&f=</code> parameter!</p>

    <hr>

    <h2>Troubleshooting</h2>

    <h3>Problem: Shows ID instead of username in form</h3>
    <p><strong>Solution:</strong></p>
    <ul>
        <li>Check that <code>->belongsTo('user', ...)</code> is present</li>
        <li>The alias in belongsTo must match: <code>belongsTo('user', ...)</code> and <code>column('user.username', ...)</code></li>
    </ul>

    <h3>Problem: Autocomplete doesn't work</h3>
    <p><strong>Check:</strong></p>
    <ul>
        <li>The <code>apiUrl()</code> endpoint matches your action: <code>apiUrl('?page=projects&action=search-users', ...)</code></li>
        <li>The <code>#[RequestAction('search-users')]</code> exists in controller</li>
        <li>Open browser console (F12) and check for JavaScript errors</li>
        <li>Check Network tab to see if API request is sent</li>
    </ul>

    <h3>Problem: Empty username column in table</h3>
    <p><strong>Solutions:</strong></p>
    <ul>
        <li>Check that records have <code>user_id</code> values in the database</li>
        <li>The relationship alias must match: <code>belongsTo('user', ...)</code> → <code>column('user.username', ...)</code></li>
    </ul>

    <hr>

    <h2>Summary: Quick Checklist</h2>

    <div class="alert alert-success">
        <strong>✅ To add user selection to your module (Simple Method):</strong>
        <ol class="mb-0">
            <li><strong>Model:</strong> Add <code>user_id</code> field with <code>belongsTo()</code>, <code>formType('milkSelect')</code>, and <code>apiUrl('...&action=related-search-field&f=user_id', ...)</code></li>
            <li><strong>Database:</strong> Run <code>php milkadmin/cli.php yourmodule:update</code></li>
            <li><strong>Table:</strong> Add <code>->column('user.username', 'Owner', 'text')</code> in your list method</li>
            <li><strong>Controller:</strong> Nothing needed! The framework handles search automatically</li>
            <li><strong>Form:</strong> Nothing! FormBuilder handles it automatically</li>
        </ol>
    </div>

    <div class="alert alert-info">
        <strong>💡 Need custom filtering?</strong> Use <code>action=search-users</code> in <code>apiUrl()</code> and add a custom <code>searchUsers()</code> method to your controller (see Option B in Step 4).
    </div>

    <hr>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/Model/abstract-model-relationships">Model Relationships - Complete Guide</a></li>
        <li><a href="?page=docs&action=Framework/Forms/form-milkselect">MilkSelect - Autocomplete Component Reference</a></li>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder - Table Management</a></li>
        <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-post">Getting Started - Creating a Complete Module</a></li>
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
