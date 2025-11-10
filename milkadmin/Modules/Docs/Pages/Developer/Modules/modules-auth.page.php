<?php
namespace Modules\Docs\Pages;
use App\{Theme, Route, Get};
/**
 * @title Auth
 * @guide user
 * @order 60
 * @tags auth, authentication, login, logout, session, user-management, permissions, security, CSRF-protection, user-session  
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
        <h1>Auth Class Documentation</h1>
        <p>This documentation provides a detailed overview of the Auth class and its functions.</p>

        <h2>Settings</h2>
        <p>In config.php you can set the session duration in minutes before it expires.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$conf['auth_expires_session'] = 20;</code></pre>

        <p>You can also set the "remember me" duration in days.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$conf['auth_remember_me_duration'] = 30;</code></pre>
        
        <h2>Introduction</h2>
        <p>The Auth class handles authentication and users within the system. It allows login, logout, session management and user permissions.</p>

        <h2>Variables</h2>
        <ul>
            <li><strong>$current_user</strong>: Currently authenticated user.</li>
            <li><strong>$expired_session</strong>: Session duration before it expires.</li>
            <li><strong>$session</strong>: Current session data.</li>
        </ul>

        <h2>Functions</h2>

        <h4>getInstance()</h4>
        <p>Returns the singleton instance of the Auth class.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$auth = Get::make('Auth');
        </code></pre>

        <h4>getUser($id = 0)</h4>
        <p>Returns the current user or a specific user if ID is set.<br>
        After the 'modules_loaded' hook, the current user is set to guest or to the user who logged in</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$user = $auth->getUser(1);
        </code></pre>
        <p>The user has the following data:</p>
        <ul>
            <li><strong>id</strong>: User ID.</li>
            <li><strong>username</strong>: Username.</li>
            <li><strong>email</strong>: User email.</li>
            <li><strong>password</strong>: User password.</li>
            <li><strong>status</strong>: User status. 1:Active, 0 Disabled, -1 Deleted</li>
            <li><strong>is_admin</strong>: Administrator user flag.</li>
            <li><strong>is_guest</strong>: Guest user flag.</li>
            <li><strong>permissions</strong>: User permissions.</li>
        </ul>
        <p>Permissions are not used directly by the Auth class but are transferred to the Permissions class.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (Permissions::check('_user.is_guest')) {
                // the user is NOT logged in
            } </code></pre>
        <p>Outside the module you can access the Auth class as follows:</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// ...
$user = Get::make('Auth')->getUser();
$user->is_guest;
$user->is_admin;
</code></pre> 

        <h4>login($username_email = '', $password = '')</h4>
        <p>Verifies if the credentials are correct or if the user is already logged in.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$is_authenticated_in = $auth->login('username', 'password');
        </code></pre>

        <h4>isAuthenticated()</h4>
        <p>Checks if the user is logged in.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$is_authenticated = $auth->isAuthenticated();
        </code></pre>

        <h4>logout()</h4>
        <p>Logs out the current user.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$auth->logout();
        </code></pre>

        <h4>saveUser($id, $username, $email, $password = '', $status = 1, $is_admin = 0, $permissions = [])</h4>
        <p>Saves a user to the database.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$auth->saveUser(1, 'username', 'email@example.com', 'password', 1, 0, []);
        </code></pre>

        <h2>Deep Dive</h2>
        <h3>When login is performed:</h3>
        <p>First of all, you need to understand that authentication management is executed within the normal execution of the site modules when <code>Get::make('Auth');</code> is called. Inside auth.module.php the <code>modules_loaded</code> hook is executed which initializes the user.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Hooks::set('modules_loaded', function() {
    Get::make('Auth');
});</code></pre>
<p>Hooks allow you to manage the execution order. By default the order is set to 20, while auth is set to 10. This way authentication is set before all other modules.</p>

<h3>Print current user:</h3>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$user = Get::make('Auth')->getUser();
echo "&lt;pre&gt;"; print_r($user); echo "&lt;/pre&gt;";
// Output:
<?php echo "<pre>"; print_r(Get::make('Auth')->getUser()); echo "</pre>"; ?>

</code></pre>

</div>