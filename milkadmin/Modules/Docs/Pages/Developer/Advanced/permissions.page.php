<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Access Control and Permissions
 * @category Advanced
 * @order 2
 * @tags permissions, access, security, authorization
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Access Control and Permissions</h1>
<p class="text-muted">Revision: 2026/01/28</p>

<p class="lead">
    Control access to modules and actions using built-in access levels and advanced permission systems.
</p>

<hr>

<h2>Basic Access Levels</h2>

<p>
    MilkAdmin provides four built-in access levels that can be applied to modules and controller methods:
</p>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Level</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>public</code></td>
                <td>Anyone can access (no authentication required)</td>
            </tr>
            <tr>
                <td><code>registered</code></td>
                <td>Only logged-in users can access</td>
            </tr>
            <tr>
                <td><code>authorized</code></td>
                <td>Requires specific permission verification</td>
            </tr>
            <tr>
                <td><code>admin</code></td>
                <td>Only administrators can access</td>
            </tr>
        </tbody>
    </table>
</div>

<hr>

<h2>Module-Level Access</h2>

<p>Set the default access level for an entire module in the <code>configure()</code> method:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class PostsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('posts')
             ->title('Posts')
             ->menu('Posts', '', 'bi bi-file-earmark-post-fill', 10)
             ->access('registered');  // Module requires login
    }
}</code></pre>

<p>The menu item will only appear to users who meet the access level requirement.</p>

<hr>

<h2>Method-Level Access Override</h2>

<p>Override the module's access level for specific methods using the <code>#[AccessLevel]</code> attribute:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
public function postsList() {
    // Inherits module's 'registered' access level
    $response = ['page' => $this->page, 'title' => $this->title];
    $response['html'] = TableBuilder::create($this->model, 'idTablePosts')
        ->asLink('title', '?page='.$this->page.'&action=edit&id=%id%')
        ->setDefaultActions()
        ->render();
    Response::render(__DIR__ . '/Views/list_page.php', $response);
}

#[AccessLevel('authorized')]
#[RequestAction('edit')]
public function postEdit() {
    // Requires specific permission verification
    $response = ['page' => $this->page, 'title' => $this->title];
    $response['form'] = FormBuilder::create($this->model, $this->page)
        ->getForm();
    Response::render(__DIR__ . '/Views/edit_page.php', $response);
}</code></pre>

<hr>

<h2>Advanced Permissions</h2>

<p>
    Define granular permissions within modules to control specific actions.
    Permissions can be managed through the Auth module's user interface.
</p>

<h3>Defining Permissions</h3>

<p>Use the <code>permissions()</code> method in module configuration:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    $rule->page('posts')
         ->access('registered')
         ->permissions([
             'access' => 'Access Posts Module',
             'delete' => 'Delete Posts'
         ]);
}</code></pre>

<div class="alert alert-info">
    The first permission (<code>access</code>) is automatically verified when using <code>access('authorized')</code>.
</div>

<hr>

<h2>Permission Verification</h2>

<p>Use <code>Permissions::check()</code> to verify specific permissions in controller methods:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('delete')]
public function deletePost() {
    if (!Permissions::check('posts.delete')) {
        $queryString = Route::getQueryString();
        Route::redirect('?page=deny&redirect='.Route::urlsafeB64Encode($queryString));
    }

    // Permission granted, proceed with deletion
    // ...
}</code></pre>

<p>Permission format: <code>'module_page.permission_name'</code></p>
<p>You can also share a single permission across multiple modules by defining a permission key in <code>group.permission</code> format. If the key contains exactly one dot, the part before the dot is used as the permission group and the part after the dot is the permission key. This makes the Users module show a single permission entry that grants access to all modules using the same group/key.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->access('authorized')
     ->permissions(['school.access' => 'Access School']);
</code></pre>

<hr>

<h2>Important: User Initialization Before Permission Checks</h2>

<div class="alert alert-warning">
    <strong>Critical:</strong> If you attempt to verify permissions before the user has been loaded, the system will treat the user as an administrator by default.
</div>

<p>
    In modules, inside the <code>configure()</code> method, before performing any <code>checkPermission</code> or <code>Permissions::check()</code> call, you must ensure that the current user has been initialized.
</p>

<h3>How to Initialize the User</h3>

<p>
    You can initialize the user by simply requesting the Auth contract:
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    // Initialize the authenticated user
    Get::make('Auth');

    // Now it's safe to check permissions
    if (!Permissions::check('posts.access')) {
        // Permission denied
    }

    $rule->page('posts')
         ->title('Posts')
         ->access('authorized')
         ->permissions([
             'access' => 'Access Posts Module',
             'delete' => 'Delete Posts'
         ]);
}</code></pre>

<p>
    Calling <code>Get::make('Auth')</code> automatically initializes the permissions for the logged-in user. Without this initialization, the permission system cannot determine the actual user's permissions and will default to administrator privileges.
</p>

<div class="alert alert-info">
    <strong>Note:</strong> This initialization is automatically handled in controller methods after the <code>modules_loaded</code> hook. However, in the <code>configure()</code> method or early module initialization, you must explicitly call <code>Get::make('Auth')</code> before checking permissions.
</div>

<hr>

<h2>Permission System Behavior</h2>

<h3>When access('authorized') is Used</h3>

<p>
    Setting a method's access level to <code>authorized</code> automatically checks the first permission defined in the module's <code>permissions()</code> array.
    If that first permission uses the <code>group.permission</code> format, the check uses the provided group/key instead of the module page.
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// In module configuration
->permissions([
    'access' => 'Access Posts Module',  // This permission is checked
    'delete' => 'Delete Posts'
])

// In controller
#[AccessLevel('authorized')]
#[RequestAction('edit')]
public function postEdit() {
// Automatically verifies 'posts.access' permission
}</code></pre>

<h3>Manual Permission Checks</h3>

<p>For additional permissions, use explicit <code>Permissions::check()</code> calls:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (!Permissions::check('posts.delete')) {
    // User lacks 'delete' permission
    // Redirect or show error
}</code></pre>

<hr>

<h2>Complete Example</h2>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class PostsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('posts')
             ->title('Posts')
             ->access('registered')
             ->permissions([
                 'access' => 'Access Posts Module',
                 'create' => 'Create Posts',
                 'edit' => 'Edit Posts',
                 'delete' => 'Delete Posts'
             ]);
    }
}

class PostsController extends AbstractController
{
    #[RequestAction('home')]
    public function postsList() {
        // Inherits 'registered' access level
    }

    #[AccessLevel('authorized')]
    #[RequestAction('edit')]
    public function postEdit() {
        // Automatically checks 'posts.access' permission

        if (!Permissions::check('posts.edit')) {
            Route::redirect('?page=deny');
        }

        // User has both 'access' and 'edit' permissions
    }

    #[AccessLevel('authorized')]
    #[RequestAction('delete')]
    public function postDelete() {
        if (!Permissions::check('posts.delete')) {
            Route::redirect('?page=deny');
        }

        // User has 'delete' permission
    }
}</code></pre>

<hr>

<h2>See Also</h2>

<ul>
    <li><a href="?page=docs&action=Framework/Core/permissions">Permissions Class Documentation</a> - Complete permission system reference</li>
    <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-post">Creating Modules (Posts Example)</a> - Module basics</li>
</ul>

</div>
